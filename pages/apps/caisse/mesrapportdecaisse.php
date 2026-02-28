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
													<th>B500</th>
                                                    <th>B1000</th>
													<th>B2000</th>
													<th>B5000</th>
                                                    <th>B10000</th>
                                                    <th>B20000</th>
													<th>MONTANT EN LETTRE</th>
													<th>CONFORMITE</th>
													<th>ACTION</th>
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

														$entree = getEntreePaiements($donnees1['compte'], $donnees1['date_rapportement'], $donnees1['date_rapportement'], $bdd, (int)$donnees1['id_user']);
														$entreePreuve = getEntreePreuve($donnees1['compte'], $donnees1['date_rapportement'], $donnees1['date_rapportement'], $bdd, (int)$donnees1['id_user']);
														
														echo '<tr>';
														echo '<td>PR_C' . $i++ . '</td>';
														echo '<td>' . htmlspecialchars($donnees1['date_rapportement']) . '</td>';
														echo '<td>' . htmlspecialchars(type_paiement($donnees1['compte'])) . '</td>';
														echo '<td>' . number_format($donnees1['montant']) . ' ' . htmlspecialchars($devise) . '</td>';
																			echo '<td>' . number_format($donnees1['b0'] ?? 0) . '</td>';
														echo '<td>' . number_format($donnees1['b1']) . '</td>';
														echo '<td>' . number_format($donnees1['b2']) . '</td>';
														echo '<td>' . number_format($donnees1['b5']) . '</td>';
														echo '<td>' . number_format($donnees1['b10']) . '</td>';
														echo '<td>' . number_format($donnees1['b20']) . '</td>';
														echo '<td>' . htmlspecialchars(extrairePremiersMots($donnees1['montant_lettre'])) . '</td>';
														echo '<td>';
														if ($entree == $entreePreuve) {
															echo '<a class="text-success">conforme</a>';
														} else {
															echo '<a class="text-danger">non conforme</a>';
														}
														echo'</td>';
														echo '<td><button type="button" class="btn btn-sm btn-primary btn-print-rapport" data-print-url="imprimer_rapportcaisse.php?id=' . htmlspecialchars($donnees1['id_preuve']) . '"><i class="fa fa-file-pdf"></i> imprimer</button></td>';
														echo '</tr>';
													}
															if (!$hasRows) {
																echo '<tr><td colspan="13" class="text-center">Aucun rapport trouvé pour ce mois.</td></tr>';
													}
												?>
											</tbody>
										</table>
									</div>
								</section>
							</div>
						</div>
			    </div>

								<!-- Modal impression rapport -->
								<div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
									<div class="modal-dialog modal-xl modal-dialog-scrollable">
										<div class="modal-content">
											<div class="modal-header">
												<h5 class="modal-title" id="printModalTitle">Impression</h5>
												<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
											</div>
											<div class="modal-body p-0">
												<iframe id="printModalFrame" title="Aperçu impression" style="width:100%; height:80vh; border:0;"></iframe>
											</div>
											<div class="modal-footer">
												<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
												<button type="button" class="btn btn-primary" id="btnPrintModal"><i class="fa fa-print"></i> Imprimer</button>
											</div>
										</div>
									</div>
								</div>

								<script>
									(function(){
										var modalEl = document.getElementById('printModal');
										var frameEl = document.getElementById('printModalFrame');
										var btnPrint = document.getElementById('btnPrintModal');

										function openPrintModal(url, title){
											if (!modalEl || !frameEl || !window.bootstrap) return;
											if (title) {
												var titleEl = document.getElementById('printModalTitle');
												if (titleEl) titleEl.textContent = title;
											}
											frameEl.src = url;
											var instance = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
											instance.show();
										}

										function printCurrentModalFrame(){
											if (!frameEl || !frameEl.contentWindow) return;
											frameEl.contentWindow.focus();
											frameEl.contentWindow.print();
										}

										document.addEventListener('DOMContentLoaded', function(){
											document.querySelectorAll('.btn-print-rapport').forEach(function(btn){
												btn.addEventListener('click', function(){
													var url = btn.getAttribute('data-print-url');
													if (url) openPrintModal(url, 'Impression rapport de caisse');
												});
											});

											if (btnPrint) btnPrint.addEventListener('click', printCurrentModalFrame);

											if (modalEl) {
												modalEl.addEventListener('hidden.bs.modal', function(){
													if (frameEl) frameEl.src = '';
												});
											}
										});
									})();
								</script>

					</section>
				</div>
			</section>
			<?php include('../PUBLIC/footer.php');?>
