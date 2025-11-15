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
						<h2>Liste globals des montures en stock</h2>
					</header>

					<!-- start: page -->
					<div class="col-md-12">
						<div class="row">
							<div class="col">
								<section class="card">
                                    <header class="card-header">
                                        <h3 class="card-title" style="text-transform:inherit; font-weight:lighter; ">
                                            <button class="btn btn-info btn-sm" onclick="exportAllDataToExcel('datatable-default', 'liste_des_produits_en_stock')">Exporter au format excel</button>
                                        </h3>
                                    </header>
									<div class="card-body">
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
													<th>N° MONTURE</th>
                                                    <th>MARQUE</th>
                                                    <th>COULEUR</th>
                                                    <th>DESCRIPTION</th>
                                                    <th>DATE AJOUT</th>
												</tr>
											</thead>
											<tbody>
											    <?php
													$reponse = $bdd->prepare('SELECT * FROM produits WHERE retour = 0 AND vendu = 0 ORDER BY id_produit');
													$reponse->execute();
													while ($donnees1 = $reponse->fetch(PDO::FETCH_ASSOC)) {
														echo '<tr>';
														echo '<td>' . strtoupper(htmlspecialchars($donnees1['code_produit'])) . '</td>';
														echo '<td>' . htmlspecialchars(model_produits($donnees1['id_model'])) . '</td>';
                                                        echo '<td>' . htmlspecialchars($donnees1['couleur']) . '</td>';
														echo '<td>' . htmlspecialchars($donnees1['description']) . '</td>';
                                                        echo '<td>' . htmlspecialchars(date('d/m/Y', strtotime($donnees1['date_creation']))) . '</td>';
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
        <script>
                $(document).ready(function() {
                    if (!$.fn.DataTable.isDataTable('#datatable-default')) {
                        $('#datatable-default').DataTable({
                            paging: true,
                            pageLength: 10
                        });
                    }
                });
                function exportAllDataToExcel(tableID, filename = '') {
                    var table = $('#' + tableID).DataTable();
                    table.page.len(-1).draw();
                    exportTableToExcel(tableID, filename);
                    table.page.len(10).draw();
                }
                function exportTableToExcel(tableID, filename = ''){
                    var downloadLink;
                    var dataType = 'application/vnd.ms-excel';
                    var tableSelect = document.getElementById(tableID);
                    var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
                    filename = filename ? filename + '.xls' : 'liste_des_produits_en_stock.xls';
                    downloadLink = document.createElement("a");
                    document.body.appendChild(downloadLink);
                    downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
                    downloadLink.download = filename;
                    downloadLink.click();
                    document.body.removeChild(downloadLink);
                }
            </script>