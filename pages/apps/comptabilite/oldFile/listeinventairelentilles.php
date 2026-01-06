<?php
include('../PUBLIC/connect.php');
session_start();
$errors=0;

$reponse1 = $bdd->prepare('SELECT * FROM seuilstock WHERE id_seuil=?');
$reponse1 -> execute(array(1));
    while ($donnees1 = $reponse1->fetch())
    {
        $seuilreserve=$donnees1['seuilreserve'];
        $delaisappro=$donnees1['delaireapprovisionnement'];
        $seuilrupture=$donnees1['seuilrupture'];
    }

?>

<?php include('../PUBLIC/header.php'); ?>

	<body>
		<section class="body">

			<?php require '../PUBLIC/navbarmenu.php'; ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Inventaires de catégories de verres</h2>
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
													<th>CATEGORIE</th>
                                                    <th>CODE</th>
                                                    <th>EN STOCK</th>
                                                    <th>VENDUS</th>
                                                    <th>MONTANT TOTAL</th>
													<th>DATE MODIFICATION</th>
													<th>ETAT</th>
												</tr>
											</thead>
											<tbody>
											<?php
                                                $query = $bdd->prepare('
                                                SELECT cp.categorie, cp.quantite, cp.code_categorie, COUNT(vp.id_categorie) AS total_vendus, COALESCE(SUM(vp.prix_verre), 0) AS montant_total_ventes, cp.date_miseajour
                                                FROM categorie_produits cp
                                                LEFT JOIN ventes_produits vp ON cp.id_categorie = vp.id_categorie
                                                GROUP BY cp.id_categorie, cp.categorie, cp.quantite, cp.code_categorie, cp.date_miseajour
                                                ORDER BY cp.categorie
                                                ');
                                                $query->execute();
                                                foreach ($query as $resultat) {
                                                    echo '<tr>';
                                                    echo '<td>' . htmlspecialchars($resultat['categorie']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($resultat['code_categorie']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($resultat['quantite']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($resultat['total_vendus']) . '</td>';
                                                    echo '<td>' . htmlspecialchars(number_format($resultat['montant_total_ventes'])) . ' ' . $devise . '</td>';
                                                    echo '<td>' . htmlspecialchars($resultat['date_miseajour']) . '</td>';
                                                    echo '<td>';
                                                    $quantite = (int)$resultat['quantite'];
                                                    if ($quantite >= $seuilreserve) {
                                                        echo '<button class="btn btn-sm btn-success">Disponible</button>';
                                                    } elseif ($quantite >= 1 && $quantite <= $seuilrupture) {
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
                    
				</section>
			</div>
        <?php include '../PUBLIC/footer.php';?>
