<?php
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

$errors = 0;
$existe = 0;

if (isset($_POST['recherche'])) {
    $productCode = htmlspecialchars($_POST['productcode'] ?? '');
    $req1 = $bdd->prepare('SELECT * FROM produits WHERE code_produit=? AND vendu=0');
    $req1->execute([$productCode]);
    if ($req1->fetch()) {
        echo '<script>document.location.href="ventedelunette.php?client=' . $_GET['client'] . '&affectation=' . $_GET['affectation'] . '&codeproduit=' . $productCode . '"</script>';
    } else {
        $existe = 1;
    }
}

if (isset($_POST['vendre'])) {
    $affectation = $_GET['affectation'] ?? null;
    $patient = $_GET['client'] ?? null;
    $codeproduit = $_GET['codeproduit'] ?? null;
    $modePaiement = $_POST['estAssure'] ?? 0;
    $categorie = $_POST['categorie'] ?? null;
    $compte = $_POST['compte'] ?? null;
    $collaborateur = $_POST['collaborateur'] ?? null;
    $taux = $_POST['taux'] ?? 0;
    $acompte = 0;
    
    if (!$affectation || !$patient || !$codeproduit || !$categorie || !$compte || !$collaborateur) {
        $errors = 1;
    } elseif (paiementDejaEffectue($bdd, $affectation)) {
        $existe = 3;
    } else {
        // Récupération des infos produit
        $reponse1 = $bdd->prepare('SELECT * FROM produits WHERE code_produit=?');
        $reponse1->execute([$codeproduit]);
        $donnees1 = $reponse1->fetch();
        if (!$donnees1) {
            $errors = 2;
        } else {
            $produit = $donnees1['id_produit'];
            $prixmonture = $donnees1['prix'];
            $model = $donnees1['id_model'];
            // Récupération des infos catégorie
            $reponse2 = $bdd->prepare('SELECT * FROM categorie_produits WHERE id_categorie=?');
            $reponse2->execute([$categorie]);
            $donnees2 = $reponse2->fetch();
            if (!$donnees2) {
                $errors = 3;
            } else {
                $prixverre = $donnees2['prix_vente'];
                $prixmontage = $donnees2['prix_montage'];
                // Paiement
                if ($modePaiement == 0) {
                    // Paiement total
                    $acompte = $prixmonture + $prixverre;
                } else {
                    // Paiement partiel
                    $acompteN = str_replace([' ', ','], '', $_POST['acompte'] ?? '0');
                    $acompte = floatval($acompteN);
                }
                // Enregistrement vente
                $req = $bdd->prepare('INSERT INTO ventes_produits (id_affectation, id_produit, id_categorie, id_patient, id_caissier, prix_monture, prix_verre, compte, collaborateur) VALUES(?,?,?,?,?,?,?,?,?)');
                $req->execute([$affectation, $produit, $categorie, $patient, $_SESSION['auth'], $prixmonture, $prixverre, $compte, $collaborateur]);
                // Mise à jour des stocks et débits
                updateQuantiteModel($bdd, $model);
                updateQuantiteCategorie($bdd, $categorie);
                updateProduitVendu($bdd, $categorie, $codeproduit);
                updateCollaborateurDebit($bdd, $collaborateur, $prixmontage);
                // Paiement
                $code = genererNumeroPaiement();
                $mtotal = $prixmonture + $prixverre;
                $bdd->prepare('UPDATE affectations SET status=?, montant=?, taux=?, type_paiement=?, datetraitement=? WHERE id_affectation=?')
                    ->execute([4, $mtotal, $taux, $compte, date('Y-m-d'), $affectation]);
                $motif = $bdd->prepare('SELECT type FROM affectations WHERE id_affectation=?');
                $motif->execute([$affectation]);
                $motif = $motif->fetchColumn();
                if ($modePaiement == 0) {
                    $paie = $bdd->prepare('INSERT INTO paiements (id_affectation, code, types, montant, montant_paye, compte, patient, caisse) VALUES(?,?,?,?,?,?,?,?)');
                    $paie->execute([$affectation, $code, $motif, $mtotal, $mtotal, $compte, $patient, $_SESSION['auth']]);
                    updateCompteDebit($bdd, $compte, $mtotal);
                } else {
                    $paie = $bdd->prepare('INSERT INTO paiements (id_affectation, code, types, montant, montant_paye, compte, patient, caisse) VALUES(?,?,?,?,?,?,?,?)');
                    $paie->execute([$affectation, $code, $motif, $mtotal, $acompte, $compte, $patient, $_SESSION['auth']]);
                    updateCompteDebit($bdd, $compte, $acompte);
                }
                $errors = 6;
            }
        }
    }
}

include('../PUBLIC/header.php');
?>

<body>
    <section class="body">

        <?php require('../PUBLIC/navbarmenu.php'); ?>

        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Vente de lunettes</h2>
                </header>

                <!-- start: page -->
                <?php

                if (!isset($_GET['codeproduit'])) {
                    echo '
                            <div class="col-md-12">
                                <section class="card">
                                    <div class="card-body">';
                                        if ($existe == 1) {
                                            echo '
                                                <div class="alert alert-danger">
                                                    <li>Ce produit n\'existe pas ou à été déjà vendu dans le système.</li>
                                                </div>
                                                ';
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
                    while ($donnees1 = $reponse1->fetch()) {
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
                                if ($errors==6) {
                                    echo '
                                        <div class="alert alert-success">
                                        <strong>Succès paiement éffectué !</strong> <br/>  
                                        <li>Vous pouvez ré-imprimer le reçu de paiement en cliquant sur <a href="../caisse/imprimer_recu.php?affectation='.$_GET['affectation'].'" target="_blank"><i class="fa fa-file-pdf-o"></i> Reçu de paiement</a>.</li>
                                        </div>
                                        ';
                                            }
                                    if ($existe==3) {
                                    echo '
                                        <div class="alert alert-danger">
                                            <strong>Erreur de Paiement !</strong> <br/>  
                                            <li>Paiement déjà éffectué par le client.</li>
                                            <li>Vous pouvez ré-imprimer le reçu de paiement en cliquant sur <a href="../caisse/imprimer_recu.php?affectation='.$_GET['affectation'].'" target="_blank"><i class="fa fa-file-pdf-o"></i> Reçu de paiement</a>.</li>
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
                                                <label class="col-form-label" for="formGroupExampleInput">Prix monture</label>
                                                <input type="text" id="prixmonture" class="form-control" value="'.number_format($prixvente).'" disabled>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label" for="productPrice">Prix des verres</label>
                                                <input type="text" class="form-control" id="productPrice" name="product_price" disabled>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label" for="productPrice">Prix total</label>
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
                                    <form class="form-horizontal" novalidate="novalidate" method="POST" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'?client='.$_GET['client'].'&affectation='.$_GET['affectation'].'&codeproduit='.$_GET['codeproduit'].'" enctype="multipart/form-data">
										<input type="hidden" name="vendre" value="1">
                                        <div class="row form-group pb-3">
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="productSelect">Type de verres</label>
                                                    <select class="form-control populate" id="productSelect" name="categorie" onchange="fetchPrice()" required>
                                                        <option value=""> --- Choisir les verres --- </option>';
                                                        $type = $bdd->prepare('SELECT * FROM categorie_produits WHERE quantite>0 AND status = ?');
                                                        $type->execute(array(1));
                                                        while ($categorie = $type->fetch(PDO::FETCH_ASSOC)) {
                                                            echo '<option value="'.$categorie['id_categorie'].'">'. htmlspecialchars($categorie['categorie']) . '</option>';
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
                                                    <input type="text" class="form-control" name="acompte" id="acompte" placeholder="Montant de l\'accompte" min="0" step="1">
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="formGroupExampleInput">Mode de reglement</label>
                                                    <select class="form-control" name="compte" required="">';
                                                        $type = $bdd->prepare('SELECT * FROM comptes WHERE status=? AND compte_pour=?');
                                                        $type -> execute([1,2]);
                                                        while ($type_paiement = $type->fetch(PDO::FETCH_ASSOC))
                                                        {   $conf = $type_paiement['defaut'];
                                                            if ($conf==1) {
                                                                echo '<option value="'.$type_paiement['id_compte'].'">'.$type_paiement['nom_compte'].'</option>';
                                                            }
                                                        } 
                                                        echo '
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="formGroupExampleInput">Remise/Ristourne</label>
                                                    <select name="taux" class="form-control">';
                                                            $rabais = $bdd->prepare('SELECT * FROM taux WHERE status=1 AND taux_pour = ?');
                                                            $rabais -> execute([1]);
                                                            while ($taux = $rabais->fetch(PDO::FETCH_ASSOC))
                                                            { $status = $taux['taux'];
                                                                if ($status==0) {
                                                                    echo '<option value="0">Non Appliqué</option>';
                                                                }
                                                                if (($status!=0) AND ($status!=3)) {
                                                                echo '<option value="'.$taux['taux'].'">'.$taux['taux'].'%</option>';
                                                                }
                                                            } echo'
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="formGroupExampleInput">L\'Opticien</label>
                                                    <select name="collaborateur" data-plugin-selectTwo class="form-control populate" data-plugin-options="{ "minimumInputLength": 2 }">
                                                        <optgroup label="Choisir le collaborateur">';
                                                                $coll = $bdd->prepare('SELECT * FROM collaborateurs WHERE statut=? AND collaborateur_pour=?');
                                                                $coll -> execute([1, 2]);
                                                                while ($collaborateur = $coll->fetch(PDO::FETCH_ASSOC))
                                                                {
                                                                    echo '<option value="'.$collaborateur['id_collaborateur'].'">'.$collaborateur['nom_collaborateur'].'</option>';
                                                                } echo '
                                                        </optgroup>
                                                    </select>
                                                </div>
										    </div>
                                        </div>
                                        <footer class="card-footer text-end">
                                            <button class="btn btn-primary" type="submit">valider la vente</button>
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
        <?php include('../PUBLIC/footer.php'); ?>
        <?php if ($errors == 6 && $affectation): ?>
        <script>
            window.onload = function() {
                window.open('../caisse/imprimer_recu.php?affectation=<?= $affectation ?>', '_blank');
            };
        </script>
    <?php endif; ?>
    <script>
            function toggleAccompteField() {
                const accompteField = document.getElementById("accompteField");
                const estPaiementPartiel = document.querySelector('input[name="estAssure"]:checked').value === "1";
                accompteField.style.display = estPaiementPartiel ? "block" : "none";
                if (!estPaiementPartiel) {
                    document.querySelector('input[name="acompte"]').value = '';
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
            const montantInput = document.getElementById('acompte');
            if (montantInput) {
                montantInput.addEventListener('input', function(e) {
            let selectionStart = this.selectionStart;
            let oldLength = this.value.length;
            let value = this.value.replace(/\s/g, '');
            value = value.replace(/\D/g, '');
            if (value) {
                let formatted = value.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                this.value = formatted;
                // Ajuster la position du curseur
                let newLength = formatted.length;
                let diff = newLength - oldLength;
                this.setSelectionRange(selectionStart + diff, selectionStart + diff);
            } else {
                this.value = '';
            }
                    });
                }
            });
        </script>