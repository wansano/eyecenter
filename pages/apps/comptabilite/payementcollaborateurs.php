<?php
include('../PUBLIC/connect.php');
session_start();

$collaborateur = $_GET['collaborateur'] ?? null;
$errors = 0;
$existe = 0;

// Récupérer les informations de paiement
try {
    $reponse1 = $bdd->prepare('SELECT * FROM collaborateurs WHERE id_collaborateur = ?');
    $reponse1->execute([$collaborateur]);
    $donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC);

    $debit = $donnees1['debit'];
    $credit = $donnees1['credit'];
    $solde = $donnees1['solde'];

    // Vérification de la soumission du formulaire
    if (isset($_POST['payer'])) {
        
        $req1 = $bdd->prepare('SELECT * FROM paiements_collaborateurs WHERE id_collaborateur = ? AND compte  = ? AND date_ajout = ? ');
        $req1 -> execute([$collaborateur, $_POST['compte'], $_POST['dateajout']]);
        while ($data = $req1->fetch(PDO::FETCH_ASSOC))
        { 
            $existe=1; 
        }
        
        if ($existe == 0) {
        // Récupérer le solde du compte
        $req0 = $bdd->prepare('SELECT solde, credit, debit FROM comptes WHERE id_compte = ?');
        $req0->execute([$_POST['compte']]);
        $donnes = $req0->fetch(PDO::FETCH_ASSOC);

        $soldecompte = $donnes['solde'];
        $creditcompte = $donnes['credit'];
        $debitcompte = $donnes['debit'];

        // Vérification des conditions avant l'insertion
        if ($soldecompte > 0 && $_POST['montant'] <= $soldecompte) {

            // Démarrer la transaction pour garantir la cohérence des données
            $bdd->beginTransaction();

            try {
                // Insertion dans la table remboursements
                $paie = $bdd->prepare('INSERT INTO paiements_collaborateurs 
                    (id_collaborateur, paye_a, montant_paye, compte, motif, date_ajout, payeur)
                    VALUES ( ?, ?, ?, ?, ?, ?, ?)');
                $paie->execute([
                    $collaborateur, 
                    $_POST['payea'],
                    $_POST['montant'], 
                    $_POST['compte'],  
                    $_POST['motif'], 
                    $_POST['dateajout'],
                    $_SESSION['auth'],
                ]);

                // Mise à jour du crédit et du solde du compte
                $creditcompte += $_POST['montant'];
                $req3 = $bdd->prepare('UPDATE comptes SET credit = :credit WHERE id_compte = :compte');
                $req3->execute([
                    'credit' => $creditcompte,
                    'compte' => $_POST['compte']
                ]);

                // Mise à jour du statut et montant dans collaborateurs
                $credit += $_POST['montant'];
                $req = $bdd->prepare('UPDATE collaborateurs SET credit = :credit WHERE id_collaborateur = :collaborateur');
                $req->execute([
                    'credit' => $credit,
                    'collaborateur' => $collaborateur]);

                // Valider la transaction
                $bdd->commit();
                $errors = 5;
            } catch (Exception $e) {
                $errors = 2;
            }
        } else { $errors = 3; }
    }
    }
} catch (PDOException $e) {
     $e->getMessage();
}


$req0 = $bdd->prepare('SELECT id_paie FROM paiements_collaborateurs ORDER BY id_paie DESC LIMIT 0, 1');
$req0->execute();
$donnes = $req0->fetch(PDO::FETCH_ASSOC);
$idpaie = $donnes['id_paie'];

function collab($nom){
include('../PUBLIC/connect.php');
$reponse1 = $bdd->prepare('SELECT * FROM collaborateurs WHERE id_collaborateur=?');
$reponse1 -> execute(array($nom));
$collab=" ";
while ($donnees1 = $reponse1->fetch())
    {
    $collab=$donnees1['nom_collaborateur'];

    }
    return $collab;
    }
 
 include('../PUBLIC/header.php');
 ?>

	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Paiement d'un collaborateur</h2>

						<div class="right-wrapper text-end">
							<ol class="breadcrumbs">
								<li>
									<a href="#">
										<i class="bx bx-home-alt"></i>
									</a>
								</li>

								<li><span>Acceuil</span></li>

							</ol>

							<a class="sidebar-right-toggle" data-open="sidebar-right"></a>
						</div>
					</header>

					<!-- start: page -->
                    <div class="col-md-12">
							<section class="card">
								<header class="card-header">
									<h2 class="card-title">Formulaire de paiement d'un collaborateur</h2>
								</header>
								<div class="card-body">
                                    <?php
                                        if ($errors==5) {
                                        echo '
                                            <div class="alert alert-success">
                                                <strong>Succès</strong><br/>  
                                                <li>Le paiement de '.number_format($_POST['montant']).' '.$devise.' à été éffectuer avec succès !</li>
                                                <li>Vous pouvez imprimer le reçu de paiement en cliquant sur <a href="bondepaiementcollaborateur.php?paiement='.$idpaie.'" target="_blank"><i class="fa fa-file-pdf-o"></i> Reçu de paiement</a>.</li>
                                            </div>
                                            ';
                                                }
                                        if ($errors==3) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <li>Erreur</li>
                                                <li>Le montant de paiement est superieur à la redevance, merci de vérifier le montant saisie</li>
                                            </div>
                                            ';}
                                        if ($errors==2) {
                                            echo '
                                                <div class="alert alert-danger">
                                                    <li>Erreur</li>
                                                    <li>Solde compte insuffisant, merci d\'approvisonner le compte</li>
                                                </div>
                                                ';}
                                        if ($existe==1) {
                                            echo '
                                                <div class="alert alert-danger">
                                                    <li>Erreur</li>
                                                    <li>Ce paiement à été déjà effectuée</li>
                                                     <li>Vous pouvez réimprimer le reçu de paiement en cliquant sur <a href="bondepaiementcollaborateur.php?paiement='.$idpaie.'" target="_blank"><i class="fa fa-file-pdf-o"></i> Reçu de paiement</a>.</li>
                                                </div>
                                                ';}
                                    echo '
                                    <div class="alert alert-info">
                                    <li>Nous avons au solde du collaborateur</li>
                                    </div>';
                                    ?>
                                <div class="row form-group pb-3">
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <label class="col-form-label" for="productPrice">Collaborateur</label>
                                            <input type="text" class="form-control" value="<?php echo collab($collaborateur); ?>" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="productPrice">Solde à payer</label>
                                            <input type="text" class="form-control" value="<?php echo number_format($solde).' '.$devise; ?>" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="productPrice">Disponibilité dans le compte </label> <?= $devise ?>
                                            <input type="text" class="form-control" id="soldeCompte" name="price_display" style="background-color:#64F584;" disabled>
                                        </div>
                                    </div>
                                </div>
                                <form class="form-horizontal" novalidate="novalidate" method="POST" action="payementcollaborateurs.php?collaborateur=<?php echo $collaborateur;?>" enctype="multipart/form-data">
                                <input type="hidden" name="payer" value="<?php echo $collaborateur;?>">
                                    <div class="row form-group pb-3">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Payé à</label>
                                                <input type="text" name="payea" class="form-control" required="">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Montant</label>
                                                <input type="number" name="montant" class="form-control" min="1" step="1" required="">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Choisir le mode de règlement</label>
                                                <select class="form-control" name="compte" id="compteSelect" onchange="soldeCompte()" required="">
                                                    <option value=""> ------ Choisir le compte ----- </option>
                                                <?php 
                                                    $type = $bdd->prepare('SELECT * FROM comptes WHERE status=? AND solde>=?');
                                                    $type -> execute([1, $solde]);
                                                    while ($type_paiement = $type->fetch())
                                                    {
                                                        echo '<option value="'.$type_paiement['id_compte'].'">'.$type_paiement['nom_compte'].'</option>';
                                                    } 
                                                ?>
                                            </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Date paiement</label>
                                                <input type="date" name="dateajout" class="form-control" required="">
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Motif de paiement</label>
                                                <textarea name="motif" class="form-control" rows="3" placeholder="Obligatoire" required=""></textarea>
                                            </div>
                                        </div>
								    </div>
                                    <footer class="card-footer text-end">
                                        <button class="btn btn-primary" type="submit">Payer</button>
                                    </footer>
                                </form>
							</section>
						</div>
					</div>
					<!-- end: page -->
				</section>
			</div>
            <?php include('../PUBLIC/footer.php');?>