<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

include('../PUBLIC/header.php'); 
?>

<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Liste des mes rapports de caisse</h2>
					</header>

					<!-- start: page -->
					<div class="col-md-12">
						<div class="row">
							<div class="col">
								<section class="card">
									<div class="card-body">
										<table class="table table-bordered table-striped mb-0" id="datatable-default">

											<thead>
												<tr>
                                                    <th>N°</th>
                                                    <th>DATE</th>
                                                    <th>COMPTE</th>
													<th>MONTANT</th>
                                                    <th>B1000</th>
													<th>B2000</th>
													<th>B5000</th>
                                                    <th>B10000</th>
                                                    <th>B20000</th>
													<th>MONTANT EN LETTRE</th>
													<th>CONFORMITE</th>
												</tr>
											</thead>
											<tbody>
												<?php
													$reponse1 = $bdd->prepare('SELECT * FROM preuvedecaisse WHERE id_user = ? AND MONTH(date_rapportement) = MONTH(CURDATE()) AND YEAR(date_rapportement) = YEAR(CURDATE()) ORDER BY id_preuve DESC LIMIT 0, 10');
													$reponse1->execute([$_SESSION['auth']]);
													$i = 1;
													$hasRows = false;
													while ($donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC)) {
														$hasRows = true;

														$entree = getEntreePaiements($donnees1['compte'], $donnees1['date_rapportement'], $donnees1['date_rapportement'], $bdd);
														$entreePreuve = getEntreePreuve($donnees1['compte'], $donnees1['date_rapportement'], $donnees1['date_rapportement'], $bdd);
														
														echo '<tr>';
														echo '<td>PR_C' . $i++ . '</td>';
														echo '<td>' . htmlspecialchars($donnees1['date_rapportement']) . '</td>';
														echo '<td>' . htmlspecialchars(type_paiement($donnees1['compte'])) . '</td>';
														echo '<td>' . number_format($donnees1['montant']) . ' ' . htmlspecialchars($devise) . '</td>';
														echo '<td>' . number_format($donnees1['b1']) . '</td>';
														echo '<td>' . number_format($donnees1['b2']) . '</td>';
														echo '<td>' . number_format($donnees1['b5']) . '</td>';
														echo '<td>' . number_format($donnees1['b10']) . '</td>';
														echo '<td>' . number_format($donnees1['b20']) . '</td>';
														echo '<td>' . htmlspecialchars(extrairePremiersMots($donnees1['montant_lettre'])) . '</td>';
														echo '<td>';
														if ($entree == $entreePreuve) {
															echo '<button class="btn btn-sm btn-success">conforme</button>';
														} else {
															echo '<button class="btn btn-sm btn-danger">non conforme</button>';
														}
														echo'</td>';
														echo '</tr>';
													}
													if (!$hasRows) {
														echo '<tr><td colspan="11" class="text-center">Aucun rapport trouvé pour ce mois.</td></tr>';
													}
												?>
											</tbody>
										</table>
									</div>
								</section>
							</div>
						</div>
			    </div>
            <?php include('../PUBLIC/footer.php');?>
