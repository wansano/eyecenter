<?php
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

// Constantes pour les types de paiement
const PAIEMENT_TOTAL = 0;
const PAIEMENT_PARTIEL = 1;

// Classe pour la gestion des messages
class MessageManager {
    private static $messages = [];
    
    public static function addError($message) {
        self::$messages['error'][] = $message;
    }
    
    public static function addSuccess($message) {
        self::$messages['success'][] = $message;
    }
    
    public static function getMessages() {
        return self::$messages;
    }
    
    public static function hasMessages() {
        return !empty(self::$messages);
    }
}

// Fonction pour valider les données du formulaire
function validateProductData($data) {
    $errors = [];
    
    if (empty($data['productcode'])) {
        $errors[] = "Le code produit est requis";
    }
    if (empty($data['categorie'])) {
        $errors[] = "La catégorie est requise";
    }
    if (empty($data['compte'])) {
        $errors[] = "Le compte est requis";
    }
    if (empty($data['collaborateur'])) {
        $errors[] = "Le collaborateur est requis";
    }
    
    return $errors;
}

// Fonction pour mettre à jour les stocks
function updateStocks($bdd, $modelId, $categoryId) {
    try {
        // Mise à jour du stock du modèle
        $stmt = $bdd->prepare('UPDATE model_produits SET quantite = quantite - 1 WHERE id_model = ? AND quantite > 0');
        $stmt->execute([$modelId]);
        
        // Mise à jour du stock de la catégorie
        $stmt = $bdd->prepare('UPDATE categorie_produits SET quantite = quantite - 1 WHERE id_categorie = ? AND quantite > 0');
        $stmt->execute([$categoryId]);
        
        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de la mise à jour des stocks : " . $e->getMessage());
        return false;
    }
}

// Classe pour la gestion des ventes
class VenteManager {
    private $bdd;
    
    public function __construct($bdd) {
        $this->bdd = $bdd;
    }
    
    // Fonction pour traiter une vente totale
    public function processVenteTotale($data) {
        try {
            $this->bdd->beginTransaction();
            
            // Vérification du produit
            $produit = $this->getProduit($data['codeproduit']);
            if (!$produit) {
                throw new Exception("Produit non trouvé ou déjà vendu");
            }

            // Vérification de catégorie verre
            $verres = $this->getCategorie($data['categorie']);
            if (!$produit) {
                throw new Exception("Produit non trouvé ou déjà vendu");
            }
            
            // Insertion de la vente
            $stmt = $this->bdd->prepare('
                INSERT INTO ventes_produits (id_affectation, id_produit, id_categorie, id_patient, id_caissier, 
                prix_monture, prix_verre, compte, collaborateur) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            
            $stmt->execute([
                $data['affectation'],
                $produit['id_produit'],
                $data['categorie'],
                $data['client'],
                $_SESSION['auth'],
                $produit['prix'],
                $verres['prix_verre'],
                $data['compte'],
                $data['collaborateur']
            ]);
            
            // Mise à jour du statut du produit
            $this->updateProduitStatus($data['codeproduit'], $data['categorie']);
            
            $this->bdd->commit();
            return true;
        } catch (Exception $e) {
            $this->bdd->rollBack();
            throw $e;
        }
    }
    
    // Fonction pour traiter une vente partielle
    public function processVentePartielle($data) {
        try {
            $this->bdd->beginTransaction();
            
            // Vérification du produit
            $produit = $this->getProduit($data['codeproduit']);
            if (!$produit) {
                throw new Exception("Produit non trouvé ou déjà vendu");
            }
            
            // Calcul du total
            $montantTotal = $produit['prix'] + $data['prix_verre'];
            $montantPaye = $data['montant'];
            
            // Vérification du montant
            if ($montantPaye >= $montantTotal) {
                throw new Exception("Le montant de l'acompte ne peut pas être supérieur ou égal au prix total");
            }
            
            // Insertion de la vente
            $stmt = $this->bdd->prepare('
                INSERT INTO ventes_produits 
                (id_affectation, id_produit, id_categorie, id_patient, id_caissier, 
                prix_monture, prix_verre, montant_paye, compte, collaborateur) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            
            $stmt->execute([
                $data['affectation'],
                $produit['id_produit'],
                $data['categorie'],
                $data['client'],
                $_SESSION['auth'],
                $produit['prix'],
                $data['prix_verre'],
                $montantPaye,  // Montant payé (acompte)
                $data['compte'],
                $data['collaborateur']
            ]);

            // Mise à jour compte patient : 
            
            
            // Mise à jour du statut du produit
            $this->updateProduitStatus($data['codeproduit'], $data['categorie']);
            
            $this->bdd->commit();
            return true;
        } catch (Exception $e) {
            $this->bdd->rollBack();
            throw $e;
        }
    }
    
    private function getProduit($codeProduit) {
        $stmt = $this->bdd->prepare('SELECT * FROM produits WHERE code_produit = ? AND vendu = 0');
        $stmt->execute([$codeProduit]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getCategorie($prix_verre) {
        $stmt = $this->bdd->prepare('SELECT * FROM categorie_produits WHERE id_categorie = ? AND quantite > 0');
        $stmt->execute([$prix_verre]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function updateProduitStatus($codeProduit, $categorie) {
        $stmt = $this->bdd->prepare('UPDATE produits SET id_categorie = ?, vendu = 1 WHERE code_produit = ?');
        $stmt->execute([$categorie, $codeProduit]);
    }
}

// Initialisation des variables
$existe = 0;
$errors = [];

// Traitement de la recherche
if (isset($_POST['recherche'])) {
    $productCode = filter_var($_POST['productcode'], FILTER_SANITIZE_STRING);
    
    try {
        $req1 = $bdd->prepare('SELECT COUNT(*) FROM produits WHERE code_produit = ? AND vendu = 0');
        $req1->execute([$productCode]);
        
        if ($req1->fetchColumn() > 0) {
            // Produit trouvé
            header("Location: saleproductshop.php?client=" . urlencode($_GET['client']) . 
                   "&affectation=" . urlencode($_GET['affectation']) . 
                   "&codeproduit=" . urlencode($productCode));
            exit();
        } else {
            // Produit non trouvé ou déjà vendu
            $existe = 1;
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la recherche du produit : " . $e->getMessage());
        $errors[] = "Une erreur est survenue lors de la recherche du produit";
        $existe = 1;
    }
}

// Validation du formulaire de vente côté serveur
function validateSaleForm($data, $bdd) {
    $errors = [];
    
    // Vérification des champs obligatoires
    if (empty($data['categorie'])) {
        $errors[] = "Veuillez sélectionner une catégorie de verre";
    }
    if (empty($data['compte'])) {
        $errors[] = "Veuillez sélectionner un mode de règlement";
    }
    if (empty($data['collaborateur'])) {
        $errors[] = "Veuillez sélectionner un opticien";
    }
    
    // Vérification du produit
    $stmt = $bdd->prepare('SELECT prix FROM produits WHERE code_produit = ? AND vendu = 0');
    $stmt->execute([$data['codeproduit']]);
    $produit = $stmt->fetch(PDO::FETCH_ASSOC);

    // Recupération du prix des verres
    $stmt = $bdd->prepare('SELECT prix_vente, prix_montage FROM categorie_produits WHERE id_categorie = ?');
    $stmt->execute([$data['categorie']]);
    $verres = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$produit) {
        $errors[] = "Le produit n'existe pas ou a déjà été vendu";
        return $errors;
    }

    if (!$verres) {
        $errors[] = "La catégorie de verres est épuisée";
        return $errors;
    }
    
    // Vérification du paiement partiel
    if ($data['estAssure'] === "1") {
        if (!isset($data['acompte']) || empty($data['acompte'])) {
            $errors[] = "Le montant de l'acompte est requis pour un paiement partiel";
        } else {
            $acompte = floatval($data['acompte']);
            $prixTotal = $produit['prix'] + floatval($data['prix_verre']);
            
            if ($acompte <= 0) {
                $errors[] = "Le montant de l'acompte doit être supérieur à 0";
            }
            if ($acompte >= $prixTotal) {
                $errors[] = "L'acompte ne peut pas être supérieur ou égal au prix total";
            }
        }
    }
    
    return $errors;
}

// Traitement de la vente
if (isset($_POST['vendre'])) {
    try {
        // Validation des paramètres
        if (!isset($_GET['affectation'], $_GET['client'], $_GET['codeproduit'])) {
            throw new Exception("Paramètres manquants pour la vente");
        }
        
        $data = [
            'affectation' => $_GET['affectation'],
            'client' => $_GET['client'],
            'codeproduit' => $_GET['codeproduit'],
            'categorie' => $_POST['categorie'],
            'compte' => $_POST['compte'],
            'collaborateur' => $_POST['collaborateur'],
            'prix_verre' => $verres['prix_vente'] ?? 0,
            'estAssure' => $_POST['estAssure'] ?? "0",
            'acompte' => $_POST['acompte'] ?? null
        ];
        
        // Validation du formulaire
        $errors = validateSaleForm($data, $bdd);
        
        if (!empty($errors)) {
            foreach ($errors as $error) {
                MessageManager::addError($error);
            }
        } else {
            $venteManager = new VenteManager($bdd);
            
            if ($data['estAssure'] === "1") {
                // Paiement partiel
                $venteManager->processVentePartielle($data);
                MessageManager::addSuccess("Vente partielle enregistrée avec succès");
            } else {
                // Paiement total
                $venteManager->processVenteTotale($data);
                MessageManager::addSuccess("Vente totale enregistrée avec succès");
            }
            
            // Redirection après succès
            header("Location: listeclientensalle.php");
            exit();
        }
        
    } catch (Exception $e) {
        error_log("Erreur lors de la vente : " . $e->getMessage());
        MessageManager::addError($e->getMessage());
    }
}

// Inclusion du header et affichage de la page
include('../PUBLIC/header.php');
?>

<body>
    <section class="body">

        <?php require('../PUBLIC/navbarmenu.php'); ?>

        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Vente de monture et verres</h2>
                </header>

                <!-- start: page -->
                <?php

                if (!isset($_GET['codeproduit'])) {
                    echo '
                            <div class="col-md-12">
                                <section class="card">
                                    <div class="card-body">';
                                        // Affichage des messages d'erreur et de succès (MessageManager)
                                        if (class_exists('MessageManager') && MessageManager::hasMessages()) {
                                            $messages = MessageManager::getMessages();
                                            if (!empty($messages['error'])) {
                                                foreach ($messages['error'] as $msg) {
                                                    echo '<div class="alert alert-danger"><li>' . htmlspecialchars($msg) . '</li></div>';
                                                }
                                            }
                                            if (!empty($messages['success'])) {
                                                foreach ($messages['success'] as $msg) {
                                                    echo '<div class="alert alert-success"><li>' . htmlspecialchars($msg) . '</li></div>';
                                                }
                                            }
                                        }
                                        if ($existe == 1) {
                                            echo '
                                                <div class="alert alert-danger">
                                                    <li>Ce produit n\'existe pas ou a déjà été vendu dans le système.</li>
                                                </div>
                                                ';
                                        }
                                        if (!empty($errors)) {
                                            foreach ($errors as $error) {
                                                echo '
                                                <div class="alert alert-danger">
                                                    <li>' . htmlspecialchars($error) . '</li>
                                                </div>
                                                ';
                                            }
                                        }
                                        echo '
                                        <form class="form-horizontal" novalidate="novalidate" method="POST" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'?client='.$_GET['client'].'&affectation='.$_GET['affectation'].'" enctype="multipart/form-data">
                                            <input type="hidden" name="recherche" value="1">
                                            <div class="row form-group pb-3">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label class="col-form-label" for="formGroupExampleInput">Saisir le code reference du produit à vendre</label>
                                                        <input type="text" class="form-control" name="productcode" id="formGroupExampleInput" placeholder="" require>
                                                    </div>
                                                </div>
                                            </div>
                                            <footer class="card-footer text-end">
                                                <button class="btn btn-primary" type="submit" >Suivant</button>
                                            </footer>
                                        </form>
                                        </div>
                                </section>
                            </div>';
                }

                if (isset($_GET['codeproduit'])) {
                    $reponse1 = $bdd->prepare('SELECT * FROM produits WHERE code_produit=?');
                    $reponse1->execute(array($_GET['codeproduit']));
                    while ($donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC)) {
                        $codeproduit = $donnees1['code_produit'];
                        $categories = $donnees1['id_categorie'];
                        $models = $donnees1['id_model'];
                        $couleurs = $donnees1['couleur'];
                        $descriptions = $donnees1['description'];
                        $prixvente = $donnees1['prix'];
                        $status = $donnees1['vendu'];
                    }
                    echo '
                        <div class="col-md-12">
							<section class="card">
								<div class="card-body">';
                                // Affichage des messages d'erreur et de succès (MessageManager)
                                if (class_exists('MessageManager') && MessageManager::hasMessages()) {
                                    $messages = MessageManager::getMessages();
                                    if (!empty($messages['error'])) {
                                        foreach ($messages['error'] as $msg) {
                                            echo '<div class="alert alert-danger"><li>' . htmlspecialchars($msg) . '</li></div>';
                                        }
                                    }
                                    if (!empty($messages['success'])) {
                                        foreach ($messages['success'] as $msg) {
                                            echo '<div class="alert alert-success"><li>' . htmlspecialchars($msg) . '</li></div>';
                                        }
                                    }
                                }
                                if ($errors==6) {
                                    echo '
                                        <div class="alert alert-success">
                                        <strong>Succès paiement éffectué !</strong> <br/>  
                                        <li>Vous pouvez ré-imprimer le reçu de paiement en cliquant sur <a href="../clinique/recudepaiement.php?affectation='.$_GET['affectation'].'" target="_blank"><i class="fa fa-file-pdf-o"></i> Reçu de paiement</a>.</li>
                                        </div>
                                        ';
                                            }
                                    if ($existe==3) {
                                    echo '
                                        <div class="alert alert-danger">
                                            <strong>Erreur de Paiement !</strong> <br/>  
                                            <li>Paiement déjà éffectué par le client.</li>
                                            <li>Vous pouvez ré-imprimer le reçu de paiement en cliquant sur <a href="recudepaiement.php?affectation='.$_GET['affectation'].'" target="_blank"><i class="fa fa-file-pdf-o"></i> Reçu de paiement</a>.</li>
                                        </div>
                                        ';}
                                    echo '
                                    <form>
                                    <div class="row form-group pb-3">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Code Produit</label>
                                                <input type="text" class="form-control" value="' . $codeproduit . '" disabled>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Model du produit</label>
                                                <input type="text" class="form-control" value="' . model_produits($models) . '" disabled>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Couleur</label>
                                                <input type="text" class="form-control" value="' . $couleurs . '" disabled>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Etat</label>
                                                <input type="text" class="form-control" value="En vente" disabled>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Autre description du produit</label>
                                                <textarea class="form-control" rows="3" id="formGroupExampleInput" disabled>' . $descriptions . '</textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Prix monture '.$devise.'</label>
                                                <input type="text" id="prixmonture" class="form-control" value="'.number_format($prixvente).'" disabled>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label" for="productPrice">Prix des verres '.$devise.'</label>
                                                <input type="text" class="form-control" id="productPrice" name="product_price" disabled>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label" for="productPrice">Prix total '.$devise.'</label>
                                                <input type="text" class="form-control" id="totalPrice" name="total_price" style="background-color:#64F584;" disabled>
                                            </div>
                                        </div>
                                    </div>
                                    </form>
                                </div>
					    </div>';
                    }
                    
                    if (isset($_GET['codeproduit'])) {
                        echo'
                        <div class="col-md-12">
							<section class="card">
								<div class="card-body">                                    
                                    <form class="form-horizontal" method="POST" action="saleproductshop.php?client='.$_GET['client'].'&affectation='.$_GET['affectation'].'&codeproduit='.$_GET['codeproduit'].'" enctype="multipart/form-data" onsubmit="return validateForm()">
                                        <input type="hidden" name="vendre" value="1">
                                        <input type="hidden" name="prix_verre" id="prix_verre_hidden" value="0">
                                        <div class="row form-group pb-3">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="productSelect">Type de verres</label>
                                                    <select class="form-control populate" id="productSelect" name="categorie" onchange="fetchPrice()" required>
                                                        <option value=""> ------ Choisir les verres ----- </option>';
                                                        $type = $bdd->prepare('SELECT * FROM categorie_produits WHERE quantite>0 AND status = ?');
                                                        $type->execute([1]);
                                                        while ($categorie = $type->fetch(PDO::FETCH_ASSOC)) {
                                                            $prixVerre = isset($categorie['prix_vente']) ? (float)$categorie['prix_vente'] : 0;
                                                            echo '<option value="' . $categorie['id_categorie'] . '" data-prix="' . $prixVerre . '">' . htmlspecialchars($categorie['categorie']) . '</option>';
                                                        }
                                                        echo '
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group d-flex align-items-center h-100">
                                                    <div class="mt-4">
                                                        <input type="radio" name="estAssure" id="paiementtotal" value="0" onclick="toggleAccompteField()" checked>
                                                        <label for="paiementtotal">Paiement Total</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group d-flex align-items-center h-100">
                                                    <div class="mt-4">
                                                        <input type="radio" name="estAssure" id="paiementpartiel" value="1" onclick="toggleAccompteField()">
                                                        <label for="paiementpartiel">Paiement Partiel</label>
                                                    </div>
                                                </div>
                                            </div>    
                                            <div class="col-md-2" id="accompteField" style="display:none;">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="formGroupExampleInput">Accompte versé en '.$devise.'</label>
                                                    <input type="number" class="form-control" name="acompte" placeholder="Montant de l\'accompte" min="0" step="1">
                                                </div>
                                            </div>

                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="formGroupExampleInput">Mode de reglement</label>
                                                    <select class="form-control" name="compte" required="">';
                                                        $type = $bdd->prepare('SELECT * FROM comptes WHERE status=? AND compte_pour=?');
                                                        $type -> execute([1,2]);
                                                        while ($compte = $type->fetch(PDO::FETCH_ASSOC))
                                                        {   $conf = $compte['defaut'];
                                                            if ($conf==1) {
                                                                echo '<option value="'.$compte['id_compte'].'">'.$compte['nom_compte'].'</option>';
                                                            }
                                                        } 
                                                        echo '
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="formGroupExampleInput">L\'opticien</label>
                                                    <select name="collaborateur" data-plugin-selectTwo class="form-control populate" data-plugin-options="{ "minimumInputLength": 2 }">
                                                        <optgroup label="Choisir le collaborateur">';
                                                                $coll = $bdd->prepare('SELECT * FROM collaborateurs WHERE statut=? AND collaborateur_pour=?');
                                                                $coll -> execute([1, 2]);
                                                                while ($collaborateur = $coll->fetch(PDO::FETCH_ASSOC))
                                                                {
                                                                    echo '<option value="'.$collaborateur['id_collaborateur'].'">'.$collaborateur['nom_collaborateur'].'</option>';
                                                                }
                                                        echo '</optgroup>
                                                    </select>
                                                </div>
										    </div>
                                        </div>
                                        <footer class="card-footer text-end">
                                            <button class="btn btn-primary" type="submit">Effectuer la vente</button>
                                        </footer>
                                	</form>
                                </div>
							</section>
						</div>';
                    }
                ?>
                <!-- end: page -->
            </section>
        </div>

        <script>
            let prixTotal = 0;

            function fetchPrice() {
                const select = document.getElementById('productSelect');
                const prixMonture = parseFloat(document.getElementById('prixmonture').value.replace(/[^0-9.-]+/g, ""));
                const prixVerres = parseFloat(select.options[select.selectedIndex].getAttribute('data-prix') || 0);
                document.getElementById('productPrice').value = prixVerres ? prixVerres.toLocaleString() : '';
                prixTotal = prixMonture + prixVerres;
                document.getElementById('totalPrice').value = prixTotal ? prixTotal.toLocaleString() : '';
                document.getElementById('prix_verre_hidden').value = prixVerres;
            }

            function toggleAccompteField() {
                const accompteField = document.getElementById("accompteField");
                const estPaiementPartiel = document.querySelector('input[name="estAssure"]:checked').value === "1";
                accompteField.style.display = estPaiementPartiel ? "block" : "none";
                if (!estPaiementPartiel) {
                    document.querySelector('input[name="acompte"]').value = '';
                }
            }

            function validateForm() {
                const estPaiementPartiel = document.querySelector('input[name="estAssure"]:checked').value === "1";
                if (estPaiementPartiel) {
                    const acompte = parseFloat(document.querySelector('input[name="acompte"]').value);
                    if (isNaN(acompte) || acompte <= 0) {
                        alert('Veuillez saisir un montant d\'acompte valide');
                        return false;
                    }
                    if (acompte >= prixTotal) {
                        alert('L\'acompte ne peut pas être supérieur ou égal au prix total');
                        return false;
                    }
                }
                const categorie = document.getElementById('productSelect').value;
                const compte = document.querySelector('select[name="compte"]').value;
                const collaborateur = document.querySelector('select[name="collaborateur"]').value;
                if (!categorie || !compte || !collaborateur) {
                    alert('Veuillez remplir tous les champs obligatoires');
                    return false;
                }
                return true;
            }

            document.addEventListener('DOMContentLoaded', function() {
                fetchPrice(); // Initialiser les prix si une catégorie est déjà sélectionnée
                document.getElementById('productSelect').addEventListener('change', fetchPrice);
                document.querySelectorAll('input[name="estAssure"]').forEach(function(radio) {
                    radio.addEventListener('change', toggleAccompteField);
                });
            });
        </script>

        <?php include('../PUBLIC/footer.php'); ?>