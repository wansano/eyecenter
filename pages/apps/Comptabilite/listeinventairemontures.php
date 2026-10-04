<?php
include('../PUBLIC/connect.php');
session_start();
$errors=0;

$reponse1 = $bdd->prepare('SELECT * FROM seuilstock WHERE id_seuil=?');
$reponse1 -> execute([1]);
    while ($donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC))
    {
        $seuilreserve = $donnees1['seuilreserve'];
        $delaisappro = $donnees1['delaireapprovisionnement'];
        $seuilrupture = $donnees1['seuilrupture'];
    }

?>

<?php include('../PUBLIC/header.php'); ?>

	<body>
		<section class="body">

			<?php require '../PUBLIC/navbarmenu.php'; ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Inventaires de montures</h2>
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
													<th>MARQUE</th>
                                                    <th>EN STOCK</th>
                                                    <th>VENDUS</th>
													<th>DESCRIPTION</th>
													<th>DATE MODIFICATION</th>
													<th>ETAT</th>
												</tr>
											</thead>
											<tbody>
											<?php
												$reponse1 = $bdd->prepare('SELECT * FROM model_produits WHERE status != 3 ORDER BY id_model');
												$reponse1->execute();
												foreach ($reponse1 as $donnees1) {
													$quantite = $donnees1['quantite'];
													// Récupérer le nombre de ventes pour ce modèle (toutes catégories)
													$reqVendus = $bdd->prepare('SELECT COUNT(*) FROM produits WHERE id_model = ? AND vendu = 1');
													$reqVendus->execute([$donnees1['id_model']]);
													$vendus = $reqVendus->fetchColumn();
													echo '<tr>';
													echo '<td>' . htmlspecialchars($donnees1['model']) . '</td>';
													echo '<td> <a href="listemonturestock.php?marque='.htmlspecialchars($donnees1['id_model']).'">' . $quantite . '</a></td>';
													echo '<td>' . $vendus . '</td>';
													echo '<td>' . htmlspecialchars($donnees1['description']) . '</td>';
													echo '<td>' . htmlspecialchars($donnees1['date_miseajour']) . '</td>';
													echo '<td>';
													if ($quantite > $seuilreserve) {
														echo '<button class="btn btn-sm btn-success">Disponible</button>';
													} elseif ($quantite > 0 && $quantite <= $seuilreserve) {
														echo '<button class="btn btn-sm btn-warning">Reserve</button>';
													} else {
														echo '<button class="btn btn-sm btn-danger">Rupture</button>';
													}
													echo '</td>';
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
                    <br>

				</section>
			</div>
        <?php include '../PUBLIC/footer.php';?>
