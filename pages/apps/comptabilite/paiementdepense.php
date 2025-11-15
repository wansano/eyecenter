<?php
include('../PUBLIC/connect.php');
session_start();

$depense = $_GET['depense'] ?? null;
$errors = 0;
$existe = 0;

// Récupérer les informations de paiement
try {
    $reponse1 = $bdd->prepare('SELECT * FROM depenses WHERE id_depense = :depense');
    $reponse1->execute(['depense' => $depense]);
    $donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC);

    $debit = $donnees1['montant'];
    $credit = $donnees1['montant_paye'];
    $solde = $donnees1['solde'];
    $demandeur = $donnees1['id'];
    $description = $donnees1['description'];

    // Vérification de la soumission du formulaire
    if (isset($_POST['payer'])) {
        
        $req1 = $bdd->prepare('SELECT * FROM paiements_depenses WHERE id_depense = ? AND compte  = ? AND date_ajout = ? ');
        $req1 -> execute([$depense, $_POST['compte'], $_POST['dateajout']]);
        while ($data = $req1->fetch(PDO::FETCH_ASSOC))
        { 
            $existe=1; 
        }
        
        if ($existe == 0) {

        // Récupérer le solde du compte
        $req0 = $bdd->prepare('SELECT solde, montant_utilise, montant_initial FROM budgets WHERE id_budget = :compte');
        $req0->execute(['compte' => $_POST['compte']]);
        $donnes = $req0->fetch(PDO::FETCH_ASSOC);

        $soldecompte = $donnes['solde'];
        $creditcompte = $donnes['montant_utilise'];
        $debitcompte = $donnes['montant_initial'];
        
        //valider que le solde ne sera jamais inferieur
        $validation = $creditcompte+$_POST['montant'];

        // Vérification des conditions avant l'insertion
        if ($soldecompte >= $debit && $_POST['montant'] <= $debit && $validation<=$debitcompte) {

            // Démarrer la transaction pour garantir la cohérence des données
            $bdd->beginTransaction();

            try {
                // Insertion dans la table remboursements
                $paie = $bdd->prepare('INSERT INTO paiements_depenses 
                    (id_depense, montant_paye, compte, motif, date_ajout, payeur)
                    VALUES (?, ?, ?, ?, ?, ?)');
                $paie->execute([
                    $depense,
                    $_POST['montant'], 
                    $_POST['compte'],  
                    $description, 
                    $_POST['dateajout'],
                    $_SESSION['auth'],
                ]);

                // Insertion dans la table depenses
                $upt = $bdd->prepare('UPDATE depenses SET montant_paye = :montant,
                datefin = :date_ajout, id_budget = :budget, payeur = :payeur, status = :statut WHERE id_depense = :depense');
                $upt->execute([
                    'montant' => $_POST['montant'], 
                    'date_ajout' => $_POST['dateajout'], 
                    'budget' => $_POST['compte'],
                    'payeur' => $_SESSION['auth'], 
                    'statut'=> 4, 
                    'depense' => $depense
                ]);

                // Mise à jour du crédit et du solde du compte
                $creditcompte += $_POST['montant'];
                $req3 = $bdd->prepare('UPDATE budgets SET montant_utilise = :credit WHERE id_budget = :compte');
                $req3->execute([
                    'credit' => $creditcompte,
                    'compte' => $_POST['compte']
                ]);

                // Mise à jour du statut et montant dans depenses
                $credit += $_POST['montant'];
                $req = $bdd->prepare('UPDATE depenses SET montant_paye = :credit WHERE id_depense = :depense');
                $req->execute([
                    'credit' => $credit,
                    'depense' => $depense]);

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


$req0 = $bdd->prepare('SELECT id_paie FROM paiements_depenses ORDER BY id_paie DESC LIMIT 0, 1');
$req0->execute();
while ($donnees4 = $req0->fetch(PDO::FETCH_ASSOC)) {
    $idpaie = $donnees4['id_paie'];
}

function utilisateur($nom){
include('../PUBLIC/connect.php');
$reponse1 = $bdd->prepare('SELECT * FROM users WHERE id= :user');
$reponse1 -> execute(['user'=>($nom)]);
$utilisateur=" ";
while ($donnees1 = $reponse1->fetch())
    {
    $utilisateur=$donnees1['pseudo'];

    }
    return $utilisateur;
    }
function responsable($nom){
include('../PUBLIC/connect.php');
$reponse1 = $bdd->prepare('SELECT * FROM users WHERE id= :user');
$reponse1 -> execute(['user'=>($nom)]);
$responsable=" ";
while ($donnees1 = $reponse1->fetch())
    {
    $responsable=$donnees1['responsable'];

    }
    return $responsable;
    }
 
 include('../PUBLIC/header.php');
 ?>

	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Paiement d'une demande depense collaborateur interne</h2>

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
									<h2 class="card-title">Formulaire de paiement d'une depense</h2>
								</header>
								<div class="card-body">
                                    <?php
                                        if ($errors==5) {
                                        echo '
                                            <div class="alert alert-success">
                                                <strong>Succès</strong><br/>  
                                                <li>Le paiement de '.number_format($_POST['montant']).' '.$devise.' à été éffectuer avec succès !</li>
                                                <li>Vous pouvez imprimer le reçu de paiement en cliquant sur <a href="bondepaiementdepense.php?paiement='.$idpaie.'" target="_blank"><i class="fa fa-file-pdf-o"></i> Reçu de paiement</a>.</li>
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
                                                     <li>Vous pouvez réimprimer le reçu de paiement en cliquant sur <a href="bondepaiementdepense.php?paiement='.$idpaie.'" target="_blank"><i class="fa fa-file-pdf-o"></i> Reçu de paiement</a>.</li>
                                                </div>
                                                ';}
                                    echo '
                                    <div class="alert alert-info">
                                    <li>Nous avons au solde du demandeur de depense à éffectuer </li>
                                    <li><i class="fa fa-file"></i> <a href="#"> voir la facture proforma soumis par le demandeur</a> </li>
                                    </div>';
                                    ?>
                                <div class="row form-group pb-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="productPrice">Demandeur</label>
                                            <input type="text" class="form-control" value="<?php echo utilisateur($demandeur); ?>" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="productPrice">Responsable</label>
                                            <input type="text" class="form-control" value="<?php echo utilisateur(responsable($demandeur)); ?>" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="productPrice">Montant à payer</label>
                                            <input type="text" class="form-control" value="<?php echo number_format($solde).' '.$devise; ?>" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="productPrice">Disponibilité compte </label> <?= $devise ?>
                                            <input type="text" class="form-control" id="soldeBudget" name="price_display" style="background-color:#64F584;" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Motif de la demande</label>
                                            <textarea name="motif" class="form-control" rows="3" disabled=""> <?php echo $description; ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                <form class="form-horizontal" novalidate="novalidate" method="POST" action="paiementdepense.php?depense=<?php echo $depense;?>" enctype="multipart/form-data">
                                <input type="hidden" name="payer" value="<?php echo $depense;?>">
                                    <div class="row form-group pb-3">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Choisir le mode de règlement</label>
                                                <select class="form-control" name="compte" id="budgetSelect" onchange="soldeBudget()" required>
                                                    <option value=""> ------ Choisir le budget ----- </option>
                                                    <?php 
                                                        $type = $bdd->prepare('SELECT * FROM budgets WHERE status = :statut AND solde >= :solde AND YEAR(date_debut) <= YEAR(CURDATE()) AND YEAR(date_fin) >= YEAR(CURDATE())');
                                                        $type -> execute(['statut'=>1, 'solde'=>$solde]);
                                                        while ($buget = $type->fetch(PDO::FETCH_ASSOC)) {
                                                            echo '<option value="'.htmlspecialchars($buget['id_budget']).'">'.htmlspecialchars($buget['nom_budget']).'</option>';
                                                        } 
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Montant</label>
                                                <input type="number" name="montant" class="form-control" min="1" step="1" required="">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Date paiement</label>
                                                <input type="date" name="dateajout" class="form-control" required="">
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