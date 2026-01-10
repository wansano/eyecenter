<?php
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

$errors = 0;
$existe = 0;

$showPatientModal = false;
$patientIdFound = 0;
$patientRow = null;
$lastOrdonnance = null;
$lastOrdonnanceAffectation = 0;
$patientModalError = '';

// Nouveau flux: saisir N° dossier -> modal infos patient + dernière ordonnance
if (isset($_POST['recherche_patient'])) {
    $dossier = (int)($_POST['dossier'] ?? 0);

    if ($dossier <= 0) {
        $patientModalError = 'Numéro de dossier invalide.';
    } else {
        try {
            $st = $bdd->prepare('SELECT * FROM patients WHERE id_patient = ? LIMIT 1');
            $st->execute([$dossier]);
            $patientRow = $st->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$patientRow) {
                $patientModalError = 'Patient introuvable.';
            } else {
                $patientIdFound = (int)$patientRow['id_patient'];

                $stO = $bdd->prepare('
                    SELECT m.*, a.id_affectation
                    FROM mesures m
                    JOIN affectations a ON a.id_affectation = m.id_affectation
                    WHERE a.id_patient = ?
                    ORDER BY m.date_traitement DESC
                    LIMIT 1
                ');
                $stO->execute([$patientIdFound]);
                $lastOrdonnance = $stO->fetch(PDO::FETCH_ASSOC) ?: null;
                $lastOrdonnanceAffectation = $lastOrdonnance ? (int)($lastOrdonnance['id_affectation'] ?? 0) : 0;

                $showPatientModal = true;
            }
        } catch (PDOException $e) {
            error_log('[infopatientvente] recherche_patient: ' . $e->getMessage());
            $patientModalError = 'Erreur lors de la recherche.';
        }
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
                    $affectationParam = isset($_GET['affectation']) ? (int)$_GET['affectation'] : 0;
                    echo '
                            <div class="col-md-12">
                                <section class="card">
                                    <div class="card-body">';

                                        if ($patientModalError !== '') {
                                            echo '
                                                <div class="alert alert-danger">
                                                    <li>' . htmlspecialchars($patientModalError) . '</li>
                                                </div>
                                            ';
                                        }
                                        echo '
                                        <form class="form-horizontal" novalidate="novalidate" method="POST" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'?affectation='.$affectationParam.'" enctype="multipart/form-data">
                                            <input type="hidden" name="recherche_patient" value="1">
                                            <div class="row form-group pb-3">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label class="col-form-label" for="dossierInput">Saisir le numéro de dossier</label>
                                                        <input type="number" class="form-control" name="dossier" id="dossierInput" placeholder="Ex: 123" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <footer class="card-footer text-end">
                                                <button class="btn btn-primary" type="submit">Rechercher</button>
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

        <!-- Modal: informations patient + dernière ordonnance lunettes -->
        <div class="modal fade" id="patientVenteModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Informations du patient</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php if ($patientIdFound > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped mb-3">
                                    <tbody>
                                        <tr><th style="width:35%">N° dossier</th><td><?php echo (int)$patientIdFound; ?></td></tr>
                                        <tr><th>Nom &amp; Prénoms</th><td><?php echo htmlspecialchars((string)nom_patient($patientIdFound)); ?></td></tr>
                                        <tr><th>Genre</th><td><?php echo htmlspecialchars((string)return_sexe($patientIdFound)); ?></td></tr>
                                        <tr><th>Date de naissance</th><td><?php echo htmlspecialchars((string)return_age($patientIdFound)); ?></td></tr>
                                        <tr><th>Téléphone</th><td><?php echo htmlspecialchars((string)return_phone($patientIdFound)); ?></td></tr>
                                    </tbody>
                                </table>
                            </div>

                            <h6 class="mb-2">Dernière ordonnance de lunettes</h6>
                            <?php if ($lastOrdonnance): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0">
                                        <tbody>
                                            <tr><th style="width:35%">Date</th><td><?php echo htmlspecialchars((string)($lastOrdonnance['date_traitement'] ?? '')); ?></td></tr>
                                            <tr><th>Type de réfraction</th><td><?php echo htmlspecialchars((string)($lastOrdonnance['refraction'] ?? '')); ?></td></tr>
                                            <tr><th>Oeil droit (OD)</th><td><?php echo htmlspecialchars((string)($lastOrdonnance['od'] ?? '')); ?></td></tr>
                                            <tr><th>Oeil gauche (OG)</th><td><?php echo htmlspecialchars((string)($lastOrdonnance['os'] ?? '')); ?></td></tr>
                                            <tr><th>Addition</th><td><?php echo htmlspecialchars((string)($lastOrdonnance['addit'] ?? '')); ?></td></tr>
                                            <tr><th>EIP</th><td><?php echo htmlspecialchars((string)($lastOrdonnance['eip'] ?? '')); ?></td></tr>
                                            <tr><th>Détails</th><td><?php echo nl2br(htmlspecialchars((string)($lastOrdonnance['details'] ?? ''))); ?></td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning mb-0">Aucune ordonnance de lunettes trouvée pour ce patient.</div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-danger mb-0">Aucun patient sélectionné.</div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>

                        <?php if ($lastOrdonnanceAffectation > 0): ?>
                            <button type="button" class="btn btn-info" id="btnOpenOrdonnancePrint" data-affectation="<?php echo (int)$lastOrdonnanceAffectation; ?>">Imprimer l'ordonnance</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-info" disabled>Imprimer l'ordonnance</button>
                        <?php endif; ?>

                        <?php
                            $affectationForSale = isset($_GET['affectation']) ? (int)$_GET['affectation'] : 0;
                            $canSale = ($patientIdFound > 0 && $affectationForSale > 0);
                        ?>
                        <?php if ($canSale): ?>
                            <a class="btn btn-primary" href="ventelunette.php?client=<?php echo (int)$patientIdFound; ?>&affectation=<?php echo (int)$affectationForSale; ?>">Effectuer une vente</a>
                        <?php else: ?>
                            <button type="button" class="btn btn-primary" disabled>Effectuer une vente</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Impression (aperçu + impression) -->
        <div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="printModalTitle">Impression</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0" style="height:80vh;">
                        <iframe id="printFrame" src="about:blank" style="width:100%; height:100%;" frameborder="0"></iframe>
                    </div>
                    <div class="modal-footer">
                        <button type="button" id="printBtn" class="btn btn-primary">Imprimer</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>

        <?php include('../PUBLIC/footer.php'); ?>

        <?php if ($showPatientModal) { ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof bootstrap === 'undefined') return;
                var el = document.getElementById('patientVenteModal');
                if (!el) return;
                var modal = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
                modal.show();
            });
        </script>
        <?php } ?>
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

            // Modal d'impression (iframe) — utilisé pour l'ordonnance des lunettes
            (function() {
                var printModalEl = document.getElementById('printModal');
                var printFrameEl = document.getElementById('printFrame');
                var printBtnEl = document.getElementById('printBtn');
                var printTitleEl = document.getElementById('printModalTitle');

                function openPrintModal(url, titleText) {
                    if (!url) return;
                    if (printTitleEl) printTitleEl.textContent = titleText || 'Impression';
                    if (!printModalEl || !printFrameEl) {
                        window.open(url, '_blank');
                        return;
                    }
                    printFrameEl.src = url;
                    if (window.bootstrap && window.bootstrap.Modal) {
                        var instance = window.bootstrap.Modal.getInstance(printModalEl) || new window.bootstrap.Modal(printModalEl);
                        instance.show();
                        return;
                    }
                    if (window.jQuery && typeof jQuery(printModalEl).modal === 'function') {
                        jQuery(printModalEl).modal('show');
                        return;
                    }
                    window.open(url, '_blank');
                }

                // Bouton "Imprimer l'ordonnance" depuis le modal patient
                var btnOpenOrd = document.getElementById('btnOpenOrdonnancePrint');
                if (btnOpenOrd) {
                    btnOpenOrd.addEventListener('click', function () {
                        var affectation = this.getAttribute('data-affectation');
                        if (!affectation) return;

                        // Fermer le modal patient avant d'ouvrir celui d'impression
                        try {
                            var patientEl = document.getElementById('patientVenteModal');
                            if (patientEl && window.bootstrap && window.bootstrap.Modal) {
                                var patientInstance = window.bootstrap.Modal.getInstance(patientEl);
                                if (patientInstance) patientInstance.hide();
                            }
                        } catch (e) {
                            // noop
                        }

                        var url = '../optometrie/imprimer_mesure.php?affectation=' + encodeURIComponent(affectation) + '&autoprint=0';
                        openPrintModal(url, 'Ordonnance des lunettes');
                    });
                }

                // Bouton Imprimer du modal impression
                if (printBtnEl) {
                    printBtnEl.addEventListener('click', function () {
                        try {
                            var win = printFrameEl && printFrameEl.contentWindow ? printFrameEl.contentWindow : null;
                            if (win && typeof win.printPdf === 'function') {
                                win.printPdf();
                                return;
                            }
                            if (win && typeof win.print === 'function') {
                                if (typeof win.focus === 'function') win.focus();
                                win.print();
                            }
                        } catch (err) {
                            // noop
                        }
                    });
                }

                // Reset iframe à la fermeture
                if (printModalEl) {
                    printModalEl.addEventListener('hidden.bs.modal', function () {
                        if (printFrameEl) printFrameEl.src = 'about:blank';
                    });
                    if (window.jQuery && typeof jQuery(printModalEl).on === 'function') {
                        jQuery(printModalEl).on('hidden.bs.modal', function () {
                            if (printFrameEl) printFrameEl.src = 'about:blank';
                        });
                    }
                }
            })();

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