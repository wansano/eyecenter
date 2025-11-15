<?php
include('../PUBLIC/connect.php');
session_start();
$errors = 0;
$existe = 0;
$depense = $_GET['id_depense'] ? intval($_GET['id_depense']) : null;

    try {

        // Récupérer les informations de la dépense
        $reponse1 = $bdd->prepare('SELECT * FROM depenses WHERE id_depense = ?');
        $reponse1->execute([$depense]);
        $donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC);

        if (!$donnees1) {
            die('Aucune donnée trouvée pour cet ID.');
        }

        $montant = $donnees1['solde'];
        $montantpaye = $donnees1['montant_paye'];
        $collaborateur = $donnees1['id'];
        $description = $donnees1['description'];

        // Vérification de la soumission du formulaire
        if (isset($_POST['montant']) && isset($_POST['budget'])) {

            // Récupération du budget
            $req0 = $bdd->prepare('SELECT solde, montant_utilise, montant_initial FROM budgets WHERE id_budget = ?');
            $req0->execute([$_POST['budget']]);
            $donnes = $req0->fetch(PDO::FETCH_ASSOC);

            if (!$donnes) {
                die('Budget introuvable.');
            }

            $solde = $donnes['solde'];
            $credit = $donnes['montant_utilise'];
            $debit = $donnes['montant_initial'];
            $status = $donnees1['status'];
            $montantSaisi = floatval($_POST['montant']);
            $equivalence = $credit + $montantSaisi;

             // Empêcher les paiements si la dépense est déjà payée
             $req1 = $bdd->prepare('SELECT * FROM paiement_depenses WHERE montant_paye=? AND id_demandeur=? AND id_budget=? AND payeur=? AND id_depense=?');
             $req1 -> execute(array($montantSaisi, $collaborateur, $_POST['budget'], $_SESSION['auth'], $depense));
             while ($data = $req1->fetch(PDO::FETCH_ASSOC))
             { $existe=1; }

             if ($existe == 0) {
        
            //  Vérifications avant mise à jour
            if ($solde > 0 && $montantSaisi <= $montant && $equivalence <= $debit) {

                // Démarrer une transaction sécurisée
                $bdd->beginTransaction();

                try {

                    $montantpaye += $montantSaisi;

                    $ajout = $bdd->prepare('INSERT INTO paiement_depenses (id_depense, montant, id_demandeur, id_budget, payeur) VALUES (?,?,?,?,?)');
                    $ajout->execute([$depense, $montantSaisi, $collaborateur, $_POST['budget'], $_SESSION['auth']]);
                    $errors = 1;

                    if ($errors == 1) {
                    // Mise à jour de la table `depenses`
                    $paie = $bdd->prepare('UPDATE depenses 
                        SET montant_paye = :montant, 
                            datefin = :dateajout, 
                            id_budget = :budget, 
                            payeur = :payeur, 
                            status = :statut 
                        WHERE id_depense = :depense');
                    $paie->execute([
                        'montant' => $montantpaye,
                        'dateajout' => $_POST['dateajout'],
                        'budget' => $_POST['budget'],
                        'payeur' => $_SESSION['auth'],
                        'statut' => 4,
                        'depense' => $depense
                    ]);

                    // Mise à jour du budget (crédit)
                    $credit += $montantSaisi;
                    $req3 = $bdd->prepare('UPDATE budgets SET montant_utilise = :credit WHERE id_budget = :budget');
                    $req3->execute([
                        'credit' => $credit,
                        'budget' => $_POST['budget']
                    ]);
                
                    // Validation de la transaction
                    $bdd->commit();
                    $errors = 5; 
                    }
                }  catch (Exception $e) {
                    // Annulation en cas d'erreur
                    $bdd->rollBack();
                    echo "Erreur lors du paiement : " . $e->getMessage();
                }
            }
         } else { $existe = 3;}
        }
    } catch (PDOException $e) {
        echo "Erreur de connexion : " . htmlspecialchars($e->getMessage());
    }
    
function utilisateur($nom){
include('../PUBLIC/connect.php');
$reponse1 = $bdd->prepare('SELECT * FROM users WHERE id=?');
$reponse1 -> execute(array($nom));
$utilisateur=" ";
while ($donnees1 = $reponse1->fetch())
    {
    $utilisateur=$donnees1['pseudo'];

    }
    return $utilisateur;
    }

	include('../PUBLIC/header.php');
 ?>

	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Paiement de depense</h2>

						<div class="right-wrapper text-end">
							<ol class="breadcrumbs">
								<li>
									<a href="welcome.php?profil=ecv2">
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
                                <h2 class="card-title">Formulaire de paiement de depense</h2>
                            </header>
								<div class="card-body">
                                    <?php
                                        if ($errors==5) {
                                        echo '
                                            <div class="alert alert-success">
                                                <strong>Succès</strong> <br/>  
                                                <li>Le paiement a été effectué avec succès. !</li>
                                            </div>
                                            ';
                                                }
                                        if ($existe==1) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <li>Cette requette à dejà été traité.</li>
                                                <li>Ou le paiement n\'a pas reussi, merci de vérifier les informations saisies</li>
                                            </div>';
                                        }
                                        if ($existe==3) {
                                            echo '
                                                <div class="alert alert-danger">
                                                    <li>Erreur de solde</li>
                                                    <li>Le solde du budget est inssufisant.</li>
                                                </div>';
                                        }
                                echo '
                                    <div class="alert alert-info">
                                        <li>Reglement d\'une dépense autorisé, et initier par <strong>'.utilisateur($collaborateur).'</strong>, d\'un montant de
                                        <strong>'.number_format($montant).' '.$devise.'</strong></li>
                                        <li><strong>Motif : </strong>'.$description.'</li>
                                    </div>';
                                    ?>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="productPrice">Disponibilité du budget</label> <?= $devise; ?> 
                                            <input type="text" class="form-control" id="soldeBudget" name="price_display" style="background-color:#64F584;" disabled>
                                        </div>
                                    </div>
                                    <form class="form-horizontal" method="POST" action="paiementdepense.php?id_depense=<?= htmlspecialchars($_GET['id_depense']); ?>" enctype="multipart/form-data">
                                        <!-- Champ caché pour garder l'ID de la dépense -->
                                        <input type="hidden" name="id_depense" value="<?= htmlspecialchars($_GET['id_depense']); ?>">
                                        
                                        <!-- Montant Payé -->
                                        <div class="row form-group pb-3">
                                            <div class="col-md-2">
                                                <label class="col-form-label" for="montant">Montant Payé</label>
                                                <input type="number" class="form-control" name="montant" id="montant" required>
                                            </div>

                                            <!-- Sélection du Budget -->
                                            <div class="col-md-3">
                                                <label class="col-form-label" for="budgetSelect">Choisir le budget de règlement</label>
                                                <select class="form-control" name="budget" id="budgetSelect" onchange="soldeBudget()" required>
                                                    <option value=""> ------ Choisir le budget ----- </option>
                                                    <?php 
                                                        $type = $bdd->prepare('SELECT * FROM budgets WHERE status = 1 AND YEAR(date_debut) <= YEAR(CURDATE()) AND YEAR(date_fin) >= YEAR(CURDATE())');
                                                        $type -> execute();
                                                        while ($buget = $type->fetch()) {
                                                            echo '<option value="'.htmlspecialchars($buget['id_budget']).'">'.htmlspecialchars($buget['nom_budget']).'</option>';
                                                        } 
                                                    ?>
                                                </select>
                                            </div>

                                            <!-- Date de Paiement -->
                                            <div class="col-md-2">
                                                <label class="col-form-label" for="dateajout">Date de Paiement</label>
                                                <input type="date" class="form-control" name="dateajout" id="dateajout" required>
                                            </div>
                                        </div>

                                        <!-- Bouton de soumission -->
                                        <footer class="card-footer text-end">
                                            <button class="btn btn-primary" type="submit">Procéder au paiement</button>
                                        </footer>
                                    </form>
                            </div>
						</section>
					</div>
					<!-- end: page -->
				</section>
			</div>
            <?php include('../PUBLIC/footer.php');?>