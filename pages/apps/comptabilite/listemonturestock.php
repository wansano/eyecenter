<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();
$errors=0;

include('../PUBLIC/header.php'); ?>

	<body>
		<section class="body">

			<?php require '../PUBLIC/navbarmenu.php'; ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Liste des montures de la marque <?= model_produits($_GET['marque'])?></h2>
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
													<th>N° MONTURE</th>
                                                    <th>COULEUR</th>
                                                    <th>DESCRIPTION</th>
												</tr>
											</thead>
											<tbody>
											    <?php
													$reponse = $bdd->prepare('SELECT * FROM produits WHERE id_model = ? AND retour = 0 AND vendu = 0 ORDER BY id_produit');
													$reponse->execute([htmlspecialchars($_GET['marque'])]);
													while ($donnees1 = $reponse->fetch(PDO::FETCH_ASSOC)) {
														echo '<tr>';
														echo '<td>' . htmlspecialchars($donnees1['code_produit']) . '</td>';
														echo '<td>' . htmlspecialchars($donnees1['couleur']) . '</td>';
														echo '<td>' . htmlspecialchars($donnees1['description']) . '</td>';
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
