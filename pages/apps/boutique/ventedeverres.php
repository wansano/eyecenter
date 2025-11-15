<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

$errors = 0;
$existe = 0;

if (isset($_POST['vendre'])) {
    $affectation = $_GET['affectation'] ?? null;
    $patient = $_GET['client'] ?? null;
    $categorie = $_POST['categorie'] ?? null;
    $compte = $_POST['compte'] ?? null;
    $collaborateur = $_POST['collaborateur'] ?? null;
    $taux = $_POST['taux'] ?? 0;

    // Vérifier paiement déjà effectué
    $req1 = $bdd->prepare('SELECT COUNT(*) FROM paiements WHERE id_affectation=?');
    $req1->execute([$affectation]);
    $existe = $req1->fetchColumn() > 0 ? 3 : 0;

    if ($existe == 0) {
        // Récupérer infos catégorie
        $reponse2 = $bdd->prepare('SELECT prix_vente, prix_montage, quantite FROM categorie_produits WHERE id_categorie=?');
        $reponse2->execute([$categorie]);
        $donnees2 = $reponse2->fetch();
        $prixverre = $donnees2['prix_vente'] ?? 0;
        $prixmontage = $donnees2['prix_montage'] ?? 0;
        $quantitedispo = $donnees2['quantite'] ?? 0;

        // Enregistrer la vente
        $req = $bdd->prepare('INSERT INTO ventes_produits (id_affectation, id_categorie, id_patient, id_caissier, prix_verre, compte, collaborateur) VALUES(?,?,?,?,?,?,?)');
        $req->execute([$affectation, $categorie, $patient, $_SESSION['auth'], $prixverre, $compte, $collaborateur]);
        $errors = 4;

        if ($errors == 4) {
            // Mettre à jour la quantité
            $quantitedispo -= 1;
            $reqQ = $bdd->prepare('UPDATE categorie_produits SET quantite=? WHERE id_categorie=?');
            $reqQ->execute([$quantitedispo, $categorie]);

            // Mettre à jour le débit collaborateur
            $reponse3 = $bdd->prepare('SELECT debit FROM collaborateurs WHERE id_collaborateur=?');
            $reponse3->execute([$collaborateur]);
            $debitcollaborateur = $reponse3->fetchColumn() ?? 0;
            $debitcollaborateur += $prixmontage;
            $reqQ = $bdd->prepare('UPDATE collaborateurs SET debit=? WHERE id_collaborateur=?');
            $reqQ->execute([$debitcollaborateur, $collaborateur]);

            $errors = 5;

            if ($errors == 5) {
                $code = genererNumeroPaiement();
                $mtotal = $prixverre;
                $bdd->prepare('UPDATE affectations SET status=?, montant=?, taux=?, type_paiement=?, datetraitement=? WHERE id_affectation=?')
                    ->execute([4, $mtotal, $taux, $compte, date('Y-m-d'), $affectation]);
                $motif = $bdd->prepare('SELECT type FROM affectations WHERE id_affectation=?');
                $motif->execute([$affectation]);
                $motif = $motif->fetchColumn();
                $paie = $bdd->prepare('INSERT INTO paiements (id_affectation, code, types, montant, compte, patient, caisse) VALUES(?,?,?,?,?,?,?)');
                $paie->execute([$affectation, $code, $motif, $mtotal, $compte, $patient, $_SESSION['auth']]);
                $reponse3 = $bdd->prepare('SELECT debit FROM comptes WHERE id_compte=?');
                $reponse3->execute([$compte]);
                $debitcompte = $reponse3->fetchColumn() ?? 0;
                $debitcompte += $mtotal;
                $reqQ = $bdd->prepare('UPDATE comptes SET debit=? WHERE id_compte=?');
                $reqQ->execute([$debitcompte, $compte]);
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
                    <h2>Vente des verres uniquement</h2>
                </header>
                <!-- start: page -->
                        <div class="col-md-12">
							<section class="card">
								<div class="card-body">
                                    <?php
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
                                    ?>
                                    <form>
                                        <div class="row form-group pb-3">
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="formGroupExampleInput">Prix monture</label>
                                                    <input type="text" id="prixmonture" class="form-control" value="0" disabled>
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
                                    <form class="form-horizontal" novalidate="novalidate" method="POST" action="saleproductshopexternal.php?client=<?php echo $_GET['client'].'&affectation='.$_GET['affectation']?>" enctype="multipart/form-data">
                                        <input type="hidden" name="vendre" value="1">
                                        <div class="row form-group pb-3">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="productSelect">Catégorie de verres</label>
                                                    <select class="form-control populate" id="productSelect" name="categorie" onchange="fetchPrice()" required>
                                                        <option value=""> ------ Choisir le produit ----- </option>';
                                                        <?php $type = $bdd->prepare('SELECT * FROM categorie_produits WHERE quantite>0 AND status = ?');
                                                        $type->execute(array(1));
                                                        while ($categorie = $type->fetch(PDO::FETCH_ASSOC)) {
                                                            echo '<option value="'.$categorie['id_categorie'].'">'. htmlspecialchars($categorie['categorie']) . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="formGroupExampleInput">Mode de reglement</label>
                                                    <select class="form-control" name="compte" required="">';
                                                       <?php $type = $bdd->prepare('SELECT * FROM comptes WHERE status=? AND compte_pour=?');
                                                        $type -> execute([1,2]);
                                                        while ($type_paiement = $type->fetch(PDO::FETCH_ASSOC))
                                                        {   $conf = $type_paiement['defaut'];
                                                            if ($conf==1) {
                                                                echo '<option value="'.$type_paiement['id_compte'].'">'.$type_paiement['nom_compte'].'</option>';
                                                            }
                                                        } ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="formGroupExampleInput">Remise/Ristourne</label>
                                                    <select name="taux" id="" class="form-control">';
                                                        <?php $rabais = $bdd->prepare('SELECT * FROM taux WHERE status=1 AND taux_pour = ?');
                                                        $rabais -> execute([1]);
                                                        while ($taux = $rabais->fetch(PDO::FETCH_ASSOC))
                                                        { $status = $taux['taux'];
                                                            if ($status==0) {
                                                                echo '<option value="0">Non Appliqué</option>';
                                                            }
                                                            if (($status!=0) AND ($status!=3)) {
                                                            echo '<option value="'.$taux['taux'].'">'.$taux['taux'].'%</option>';
                                                            }
                                                        } ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="formGroupExampleInput">L'opticien</label>
                                                    <select name="collaborateur" data-plugin-selectTwo class="form-control populate">
                                                        <optgroup label="Choisir le collaborateur">';
                                                               <?php $coll = $bdd->prepare('SELECT * FROM collaborateurs WHERE statut=? AND collaborateur_pour=?');
                                                                $coll -> execute([1, 2]);
                                                                while ($collaborateur = $coll->fetch(PDO::FETCH_ASSOC))
                                                                {
                                                                    echo '<option value="'.$collaborateur['id_collaborateur'].'">'.$collaborateur['nom_collaborateur'].'</option>';
                                                                } ?>
                                                        </optgroup>
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
						</div>
                <!-- end: page -->
            </section>
        </div>
        <?php include('../PUBLIC/footer.php'); ?>