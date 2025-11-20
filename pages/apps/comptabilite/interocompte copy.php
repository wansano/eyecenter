<?php
    require_once('../PUBLIC/connect.php');
	require_once('../PUBLIC/fonction.php');
	session_start();
    $errors=0; $existe=0;
    if (isset($_POST['verification'])) {
		$compte = (int) $_POST['compte'];
		$debut = $_POST['debut'];
		$fin = $_POST['fin'];
	
		if ($compte !== 0) {
			$req1 = $bdd->prepare('SELECT * FROM comptes WHERE id_compte=?');
			$req1->execute([$compte]);
			if ($data = $req1->fetch()) {
				header("Location: interocompte.php?compte={$data['id_compte']}&debut=$debut&fin=$fin");
				exit;
			}
		} else {
			header("Location: interocompte.php?compte=0&debut=$debut&fin=$fin");
			exit;
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
						<h2>Interogation des comptes</h2>
					</header>

					<!-- start: page -->
                    <div class="col-md-12">
						<section class="card">
							<div class="card-body">
                                    <?php
                                        if ($errors==1) {
                                        echo '
                                            <div class="alert alert-success">
                                                <strong>Succès</strong> <br/>  
                                                <li>Ce numero de compte <strong>'.$_POST['id_compte'].'</strong> n\'existe pas dans le système.</li>
                                            </div>';
                                        }
                                    ?>
                                <form class="form-horizontal" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                    <input type="hidden" name="verification" value="1">
									<div class="row form-group pb-3">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput"> Choisir le compte à intérroger </label>
                                                <select class="form-control" name="compte" required>
													<option value="0">Tous les comptes</option>
                                                    <?php 
                                                        $type = $bdd->prepare('SELECT * FROM comptes');
                                                        $type -> execute();
                                                        while ($type_paiement = $type->fetch(PDO::FETCH_ASSOC))
                                                        {
                                                            echo '<option value="'.$type_paiement['id_compte'].'">'.$type_paiement['nom_compte'].'</option>';
                                                        } 
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Date début</label>
                                                <input type="date" name="debut" class="form-control" required>
											</div>
										</div>
                                        <div class="col-md-3">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Date fin</label>
												<input type="date" class="form-control" name="fin" required>
											</div>
										</div>
                                    </div>
                                    <footer class="card-footer text-end">
                                        <button class="btn btn-primary" type="submit">interroger</button>
                                    </footer>
                                </form>
                            </div>
						</section>
					</div>
                <br>

                <?php

                // Fonction pour récupérer la somme des paiements pour un compte et une période donnée

                if (isset($_GET['compte']) AND isset($_GET['debut']) AND isset($_GET['fin'])) {   
				
					if ($_GET['compte']!=0) {

						$reponse1 = $bdd->prepare('SELECT * FROM comptes WHERE id_compte=?');
						$reponse1 -> execute([$_GET['compte']]);
						while ($donnees1 = $reponse1->fetch())
							{ $compte=$donnees1['id_compte'];}

						$entree = getEntreePaiements($_GET['compte'], $_GET['debut'], $_GET['fin'], $bdd);

						$entreePreuve = getEntreePreuve($_GET['compte'], $_GET['debut'], $_GET['fin'], $bdd);

						$solde = $entree - $entreePreuve;
						
						if ($compte!=0) {
						echo '
						<div class="col-md-12">
							<div class="row">
								<div class="col">
									<section class="card">
										<div class="card-body">
											<table class="table table-bordered table-striped mb-0" id="datatable-default">
												<thead>
													<tr>
														<th>PERIODE</th>
														<th>COMPTE</th>
														<th>TYPE</th>
														<th>ENTREE</th>
														<th>RAPPORT CAISSIER</th>
														<th>DIFFERENCE</th>
														<th>ACTION</th>
													</tr>
												</thead>
												<tbody>';
													$reponse1 = $bdd->prepare('SELECT * FROM comptes WHERE id_compte=? ORDER BY id_compte');
													$reponse1->execute([$_GET['compte']]);
													while ($donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC)) {
														echo '<tr>';
														echo '<td>' . htmlspecialchars(($_GET['debut'])) . ' au '.$_GET['fin'].'</td>';
														echo '<td>' . htmlspecialchars($donnees1['nom_compte']) . '</td>';
														echo '<td>' . htmlspecialchars($donnees1['types']) . '</td>';
														echo '<td>' . number_format($entree) . ' ' . htmlspecialchars($devise) . ' </td>';
														echo '<td>' . number_format($entreePreuve) . ' ' . htmlspecialchars($devise) . ' </td>';
														echo '<td>' . number_format($solde) . ' ' . htmlspecialchars($devise) . ' </td>';
														echo '<td><a href="cumulationdesfaits.php?debut='.$_GET['debut'].'&fin='.$_GET['fin'].'" class="btn btn-sm btn-info">cumul global</a></td>';
														echo '</tr>';
													}
													echo '
												</tbody>
											</table>
										</div>
									</section>
								</div>
							</div>
						</div>
						
						<div class="col-md-12">
							<section class="panel panel-transparent">
								<div class="row">
									<header class="panel panel-transparent">
										<h4 class="panel-title">Prestations <a href="../impression/_rapportinterrogation.php?compte='.$_GET['compte'].'&debut='.$_GET['debut'].'&fin='.$_GET['fin'].'&montant='.$entree.'&rapportcaisse='.$entreePreuve.'&solde='.$solde.'" target="_blank">imprimer le rapport <i class="fa fa-file-pdf"></i> </a> </h4>
									</header>';
										$total = $bdd->prepare('SELECT * FROM traitements ORDER BY id_type');
										$total -> execute();
										while ($data = $total->fetch(PDO::FETCH_ASSOC)) {
											$nom = $data['id_type'];
											$nb = nombrejourPeriodeCompte($nom, $_GET['debut'], $_GET['fin'], $_GET['compte']);
											if ($nb != 0) {
												echo '
													<div class="col-md-2">
														<section class="card fixed-size">
															<div class="card-body">
																<div class="h6 text-bold mb-1">'.$nb.'</div>
																<a href="cumulationdesfaits.php?debut='.$_GET['debut'].'&fin='.$_GET['fin'].'&type='.$nom.'" class="text-xxs text-muted mb-none truncate">'.extrairePremiersMots($data['nom_type'], 10).'</a>
															</div>
														</section>
													</div>
												';
												}
											} 
									echo '
								</div>
							</section>
						</div>';}
					} else {

						$montant = $bdd->prepare('SELECT compte, SUM(montant) AS entree FROM paiements WHERE remboursement=0 AND datepaiement BETWEEN :debut AND :fin GROUP BY compte');
						$montant -> execute(array(':debut' => $_GET['debut'],':fin' => $_GET['fin']));
						$data_montants = $montant->fetchAll(PDO::FETCH_ASSOC);
						
						// Initialisation des variables de total
						$montanttotal = 0;
						$entreePreuveTotal = 0;
						$soldetotal = 0;
						
						echo '
						<div class="col-md-12">
							<div class="row">
								<div class="col">
									<section class="card">
										<div class="card-body">
											<table class="table table-bordered table-striped mb-0" id="datatable-default">
												<thead>
													<tr>
														<th>PERIODE</th>
														<th>COMPTE</th>
														<th>TYPE</th>
														<th>ENTREE</th>
														<th>RAPPORT CAISSIER</th>
														<th>DIFFERENCE</th>
														<th>ACTION</th>
													</tr>
												</thead>
												<tbody>';
													$rowCount = count($data_montants);
													$rowIndex = 0;
													foreach ($data_montants as $data_montant) { 
														$entree = ($data_montant['entree'] !== null) ? $data_montant['entree'] : 0;
														$entreePreuve = getEntreePreuve($data_montant['compte'], $_GET['debut'], $_GET['fin'], $bdd);
														$solde = $entree - $entreePreuve;
														echo '<tr>';
														echo '<td>' . htmlspecialchars($_GET['debut']) . ' au ' . htmlspecialchars($_GET['fin']) . '</td>';
														echo '<td>' . htmlspecialchars(compte($data_montant['compte'])) . '</td>';
														echo '<td>' . htmlspecialchars(type_paiement($data_montant['compte'])) . '</td>';
														echo '<td>' . number_format($entree) . ' ' . htmlspecialchars($devise) . '</td>';
														echo '<td>' . number_format($entreePreuve) . ' ' . htmlspecialchars($devise) . '</td>';
														echo '<td>' . number_format($solde) . ' ' . htmlspecialchars($devise) . '</td>';
														if ($rowIndex === 0) {
															echo '<td rowspan="' . $rowCount . '" class="align-middle text-center"><a href="cumulationdesfaits.php?debut=' . htmlspecialchars($_GET['debut']) . '&fin=' . htmlspecialchars($_GET['fin']) . '" class="btn btn-sm btn-info">cumul global</a></td>';
														}
														echo '</tr>';
														$rowIndex++;

														$montanttotal += $entree;
														$entreePreuveTotal += $entreePreuve;
														$soldetotal += $solde;
													}
													echo '
												</tbody>
											</table>
										</div>
									</section>
								</div>
							</div>
						</div>
						
						<div class="col-md-12">
							<section class="panel panel-transparent">
								<div class="row">
									<header class="panel panel-transparent">
										<h4 class="panel-title">Prestations <a href="imprimer_interrogation.php?compte='.$_GET['compte'].'&debut='.$_GET['debut'].'&fin='.$_GET['fin'].'&montant='.$montanttotal.'&rapportcaisse='.$entreePreuveTotal.'&solde='.$soldetotal.'" target="_blank">imprimer le rapport <i class="fa fa-file-pdf"></i> </a> </h4>
									</header>';
										$total = $bdd->prepare('SELECT * FROM traitements ORDER BY id_type');
										$total -> execute();
										while ($data = $total->fetch(PDO::FETCH_ASSOC)) {
											$nom = $data['id_type'];
											$nb = nombrejour_periode($nom, $_GET['debut'], $_GET['fin']);
											if ($nb > 0) {
												echo '
													<div class="col-md-2">
														<section class="card fixed-size">
															<div class="card-body">
																<div class="h6 text-bold mb-1">'.$nb.'</div>
																<a href="cumulationdesfaits.php?debut='.$_GET['debut'].'&fin='.$_GET['fin'].'&type='.$nom.'" class="text-xxs text-muted mb-none truncate">'.extrairePremiersMots($data['nom_type'], 10).'</a>
															</div>
														</section>
													</div>
												';
												}
											}
										}
										echo '
												</div>
											</section>
										</div>';
										}
									?>
										<!-- end: page -->
									</section>
								</div>
        				<?php include('../public/footer.php'); ?>
