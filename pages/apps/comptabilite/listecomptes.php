<?php
include('../PUBLIC/connect.php');
session_start();
$errors=0;

include('../PUBLIC/header.php'); 
?>

<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Liste et disponibilité des comptes</h2>
					</header>

					<!-- start: page -->
					<div class="col-md-12">
						<div class="row">
							<div class="col">
								<section class="card">
									<div class="card-body">
									<?php 
										if ($types=="caisse") {

											if ($errors==7) {
											echo '
												<div class="alert alert-success">
												<li><strong>Succès !</strong>
												<br>Le paiement des frais de traitement à été annuler.</li>
												</div>
												';
											}
											}
									?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">

											<thead>
												<tr>
                                                    <th>CODE</th>
                                                    <th>COMPTE</th>
                                                    <th>MODE PAIEMENT</th>
                                                    <th>MONTANT DEBIT</th>
													<th>MONTANT CREDIT</th>
													<th>SOLDE</th>
                                                    <th>CONFIDENTIALITE</th>
                                                    <th>TAUX</th>
													<th>RAISON</th>
												</tr>
											</thead>
											<tbody>
											<?php
												$reponse1 = $bdd->prepare('SELECT * FROM comptes ORDER BY id_compte');
												$reponse1->execute();
												while ($donnees1 = $reponse1->fetch()) {
													echo '<tr>';
													echo '<td>' . htmlspecialchars($donnees1['code']) . '</td>';
													echo '<td>' . htmlspecialchars($donnees1['nom_compte']) . '</td>';
													echo '<td>' . htmlspecialchars($donnees1['types']) . '</td>';
													echo '<td>' . number_format((float)$donnees1['debit'], 0, ',', ' ') . ' '.$devise.'</td>';
													echo '<td>' . number_format((float)$donnees1['credit'], 0, ',', ' ') . ' '.$devise.'</td>';
													echo '<td>' . number_format((float)$donnees1['solde'], 0, ',', ' ') . ' '.$devise.'</td>';
													echo '<td>' . (($donnees1['defaut'] == 0) ? 'Privé' : 'Public') . '</td>';
													echo '<td>' . htmlspecialchars($donnees1['taux']) . '</td>';
													echo '<td>' . (($donnees1['compte_pour'] == 1) ? 'Clinique' : 'Boutique') . '</td>';
													echo '</tr>';
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
