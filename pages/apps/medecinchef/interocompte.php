<?php
    require_once('../PUBLIC/connect.php');
	require_once('../PUBLIC/fonction.php');
	session_start();
    $errors=0; $existe=0; $errorsFutureDate=0; $errorsRange=0;
    if (isset($_POST['verification'])) {
		$compte = (int) $_POST['compte'];
		$debut = $_POST['debut'];
		$fin = $_POST['fin'];
		$today = date('Y-m-d');
		// Validation: pas de dates futures et ordre logique
		if ($debut > $today || $fin > $today) {
			$errorsFutureDate = 1; // On ne redirige pas, on affiche le message
		} elseif ($debut > $fin) {
			$errorsRange = 1;
		} else {
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
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput"> Choisir le compte à intérroger </label>
                                                <select class="form-control" name="compte" required>
													<option value="0">Tous les comptes</option>
                                                    <?php 
                                                        $type = $bdd->prepare('SELECT * FROM comptes WHERE status = 1');
                                                        $type -> execute();
                                                        while ($type_paiement = $type->fetch(PDO::FETCH_ASSOC))
                                                        {
                                                            echo '<option value="'.$type_paiement['id_compte'].'">'.$type_paiement['nom_compte'].'</option>';
                                                        } 
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
										<div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Date début</label>
												<input type="date" name="debut" class="form-control" required max="<?php echo date('Y-m-d'); ?>">
											</div>
										</div>
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Date fin</label>
												<input type="date" class="form-control" name="fin" required max="<?php echo date('Y-m-d'); ?>">
											</div>
										</div>
                                    </div>
									<?php if($errorsFutureDate==1): ?>
									<div class="alert alert-danger"><strong>Erreur:</strong> Dates futures interdites. Choisir uniquement une date passée ou du jour.</div>
									<?php endif; ?>
									<?php if($errorsRange==1): ?>
									<div class="alert alert-danger"><strong>Erreur:</strong> La date de début ne peut pas être supérieure à la date de fin.</div>
									<?php endif; ?>
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

						// Détecter la colonne montant_paye si elle existe (sinon fallback sur montant)
						$paiementsAmountCol = 'montant';
						try {
							$stCols = $bdd->query('SHOW COLUMNS FROM paiements');
							if ($stCols) {
								while ($c = $stCols->fetch(PDO::FETCH_ASSOC)) {
									if (($c['Field'] ?? '') === 'montant_paye') {
										$paiementsAmountCol = 'montant_paye';
										break;
									}
								}
							}
						} catch (Throwable $e) {
							$paiementsAmountCol = 'montant';
						}

						$reponse1 = $bdd->prepare('SELECT * FROM comptes WHERE id_compte=?');
						$reponse1 -> execute([$_GET['compte']]);
						while ($donnees1 = $reponse1->fetch())
							{ $compte=$donnees1['id_compte'];}

						$entree = getEntreePaiements($_GET['compte'], $_GET['debut'], $_GET['fin'], $bdd);

						$entreePreuve = getEntreePreuve($_GET['compte'], $_GET['debut'], $_GET['fin'], $bdd);

						$solde = $entree - $entreePreuve;
						$remboursementTotal = 0;
						$rembStmt = $bdd->prepare('SELECT COALESCE(SUM(`' . $paiementsAmountCol . '`), 0) AS remboursement_total FROM paiements WHERE compte = :compte AND remboursement = 1 AND datepaiement BETWEEN :debut AND :fin');
						$rembStmt->execute([
							':compte' => $_GET['compte'],
							':debut' => $_GET['debut'],
							':fin' => $_GET['fin'],
						]);
						if ($rowRemb = $rembStmt->fetch(PDO::FETCH_ASSOC)) {
							$remboursementTotal = ($rowRemb['remboursement_total'] !== null) ? (float)$rowRemb['remboursement_total'] : 0;
						}
						
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
														<th>REMBOURSEMENT</th>
																								<th>TOTAL</th>
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
														echo '<td>' . number_format($entree, 0, '', ' ') . ' ' . htmlspecialchars($devise) . ' </td>';
																$rembUrl = 'ajax_remboursements.php?compte='.(int)$_GET['compte'].'&debut='.urlencode($_GET['debut']).'&fin='.urlencode($_GET['fin']);
																$rembHtml = number_format($remboursementTotal, 0, '', ' ') . ' ' . htmlspecialchars($devise);
																if ((float)$remboursementTotal > 0) {
																	$rembHtml = '<a href="#" class="js-open-remb" data-url="'.htmlspecialchars($rembUrl).'">'.$rembHtml.'</a>';
																}
																echo '<td>' . $rembHtml . ' </td>';
																$totalLigne = (float)$entree + (float)$remboursementTotal;
																echo '<td>' . number_format($totalLigne, 0, '', ' ') . ' ' . htmlspecialchars($devise) . ' </td>';
														echo '<td>' . number_format($entreePreuve, 0, '', ' ') . ' ' . htmlspecialchars($devise) . ' </td>';
																$diffLigne = $totalLigne - (float)$entreePreuve;
																echo '<td>' . number_format($diffLigne, 0, '', ' ') . ' ' . htmlspecialchars($devise) . ' </td>';
																echo '<td>' . ($entreePreuve > 0 ? '<a href="imprimer_interrogation.php?compte='.$_GET['compte'].'&debut='.$_GET['debut'].'&fin='.$_GET['fin'].'&montant='.$totalLigne.'&rapportcaisse='.$entreePreuve.'&solde='.$diffLigne.'" class="btn btn-sm btn-success js-open-rapport"><i class="fa fa-file-pdf"></i> Rapport</a> ' : '').'
															<a href="cumulationdesfaits.php?debut='.$_GET['debut'].'&fin='.$_GET['fin'].'" class="btn btn-sm btn-info">cumul global</a></td>';
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
						';}
						} else {

						// Détecter la colonne montant_paye si elle existe (sinon fallback sur montant)
						$paiementsAmountCol = 'montant';
						try {
							$stCols = $bdd->query('SHOW COLUMNS FROM paiements');
							if ($stCols) {
								while ($c = $stCols->fetch(PDO::FETCH_ASSOC)) {
									if (($c['Field'] ?? '') === 'montant_paye') {
										$paiementsAmountCol = 'montant_paye';
										break;
									}
								}
							}
						} catch (Throwable $e) {
							$paiementsAmountCol = 'montant';
						}

						$montant = $bdd->prepare('SELECT compte, SUM(montant) AS entree FROM paiements WHERE (remboursement = 0 OR remboursement IS NULL) AND datepaiement BETWEEN :debut AND :fin GROUP BY compte');
						$montant -> execute(array(':debut' => $_GET['debut'],':fin' => $_GET['fin']));
						$data_montants = $montant->fetchAll(PDO::FETCH_ASSOC);

						$remboursementsByCompte = [];
						$remboursementTotal = 0;
						$remb = $bdd->prepare('SELECT compte, COALESCE(SUM(`' . $paiementsAmountCol . '`), 0) AS remboursement_total FROM paiements WHERE remboursement = 1 AND datepaiement BETWEEN :debut AND :fin GROUP BY compte');
						$remb->execute([':debut' => $_GET['debut'], ':fin' => $_GET['fin']]);
						foreach ($remb->fetchAll(PDO::FETCH_ASSOC) as $rowRemb) {
							$compteKey = $rowRemb['compte'];
							$val = ($rowRemb['remboursement_total'] !== null) ? (float)$rowRemb['remboursement_total'] : 0;
							$remboursementsByCompte[$compteKey] = $val;
							$remboursementTotal += $val;
						}
						
						// Pré-calcul des totaux pour conditionner le bouton Rapport
						$montanttotal = 0;
						$entreePreuveTotal = 0;
						foreach ($data_montants as $dm_tot) {
							$montanttotal += ($dm_tot['entree'] !== null) ? $dm_tot['entree'] : 0;
							$entreePreuveTotal += getEntreePreuve($dm_tot['compte'], $_GET['debut'], $_GET['fin'], $bdd);
						}
						$soldetotal = $montanttotal - $entreePreuveTotal;
						
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
														<th>REMBOURSEMENT</th>
																								<th>TOTAL</th>
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
														$remboursement = isset($remboursementsByCompte[$data_montant['compte']]) ? $remboursementsByCompte[$data_montant['compte']] : 0;
														$entreePreuve = getEntreePreuve($data_montant['compte'], $_GET['debut'], $_GET['fin'], $bdd);
														$solde = $entree - $entreePreuve;
														echo '<tr>';
														echo '<td>' . htmlspecialchars($_GET['debut']) . ' au ' . htmlspecialchars($_GET['fin']) . '</td>';
														echo '<td>' . htmlspecialchars(compte($data_montant['compte'])) . '</td>';
														echo '<td>' . htmlspecialchars(type_paiement($data_montant['compte'])) . '</td>';
														echo '<td>' . number_format($entree, 0, '', ' ') . ' ' . htmlspecialchars($devise) . '</td>';
																	$rembUrl = 'ajax_remboursements.php?compte='.(int)$data_montant['compte'].'&debut='.urlencode($_GET['debut']).'&fin='.urlencode($_GET['fin']);
																	$rembHtml = number_format($remboursement, 0, '', ' ') . ' ' . htmlspecialchars($devise);
																	if ((float)$remboursement > 0) {
																		$rembHtml = '<a href="#" class="js-open-remb" data-url="'.htmlspecialchars($rembUrl).'">'.$rembHtml.'</a>';
																	}
																	echo '<td>' . $rembHtml . '</td>';
																	$totalLigne = (float)$entree + (float)$remboursement;
																	echo '<td>' . number_format($totalLigne, 0, '', ' ') . ' ' . htmlspecialchars($devise) . '</td>';
														echo '<td>' . number_format($entreePreuve, 0, '', ' ') . ' ' . htmlspecialchars($devise) . '</td>';
																	$diffLigne = $totalLigne - (float)$entreePreuve;
																	echo '<td>' . number_format($diffLigne, 0, '', ' ') . ' ' . htmlspecialchars($devise) . '</td>';
														if ($rowIndex === 0) {
															echo '<td rowspan="' . $rowCount . '" class="align-middle text-center">
																				'.($entreePreuveTotal > 0 ? '<a href="imprimer_interrogation.php?compte='.$_GET['compte'].'&debut='.$_GET['debut'].'&fin='.$_GET['fin'].'&montant='.(($montanttotal + $remboursementTotal)).'&rapportcaisse='.$entreePreuveTotal.'&solde='.(($montanttotal + $remboursementTotal) - $entreePreuveTotal).'" class="btn btn-sm btn-success js-open-rapport"><i class="fa fa-file-pdf"></i> Rapport</a> ' : '').'
															<a href="cumulationdesfaits.php?debut=' . htmlspecialchars($_GET['debut']) . '&fin=' . htmlspecialchars($_GET['fin']) . '" class="btn btn-sm btn-info">cumul global</a>
															</td>';
														}
														echo '</tr>';
															$rowIndex++;
													}
													echo '
												</tbody>
											</table>
										</div>
									</section>
								</div>
							</div>
						</div>';
					} }
				?>

					<!-- end: page -->
				</section>
			</div>
		</section>

	<!-- Modal Rapport (aperçu + impression) -->
	<div class="modal fade" id="rapportModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-xl modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Rapport d'interrogation</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body p-0" style="height:80vh;">
					<iframe id="rapportFrame" src="about:blank" style="width:100%; height:100%;" frameborder="0"></iframe>
				</div>
				<div class="modal-footer">
					<button type="button" id="rapportPrintBtn" class="btn btn-primary">Imprimer</button>
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Modal Liste des remboursements -->
	<div class="modal fade" id="rembModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-xl modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Liste des remboursements</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body" id="rembModalBody">
					<div class="text-muted">Chargement...</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
				</div>
			</div>
		</div>
	</div>

	<script>
	document.addEventListener('DOMContentLoaded', function () {
		const rapportModalEl = document.getElementById('rapportModal');
		const rapportFrameEl = document.getElementById('rapportFrame');
		const rapportPrintBtnEl = document.getElementById('rapportPrintBtn');
		const rapportTitleEl = rapportModalEl ? rapportModalEl.querySelector('.modal-title') : null;
		const rembModalEl = document.getElementById('rembModal');
		const rembModalBodyEl = document.getElementById('rembModalBody');

		function withAutoPrintDisabled(url) {
			if (!url) return url;
			return url + (url.indexOf('?') >= 0 ? '&' : '?') + 'autoprint=0';
		}

		function openPdfModal(url, title) {
			if (!url) return;
			if (!window.bootstrap || !rapportModalEl || !rapportFrameEl) {
				if (typeof window.openPrintModal === 'function') {
					window.openPrintModal(url, title || 'Impression');
				}
				return;
			}
			if (rapportTitleEl) {
				rapportTitleEl.textContent = title || 'Impression';
			}
			rapportFrameEl.src = withAutoPrintDisabled(url);
			const instance = window.bootstrap.Modal.getInstance(rapportModalEl) || new window.bootstrap.Modal(rapportModalEl);
			instance.show();
		}

		document.addEventListener('click', function (e) {
			const btn = e.target.closest('.js-open-rapport, .js-open-pdf');
			if (!btn) return;
			const href = btn.getAttribute('href');
			if (!href || href === '#') return;
			e.preventDefault();
			const title = btn.getAttribute('data-title') || (btn.classList.contains('js-open-rapport') ? "Rapport d'interrogation" : 'Impression');
			openPdfModal(href, title);
		});

		function openRembModal(url) {
			if (!url) return;
			if (!window.bootstrap || !rembModalEl || !rembModalBodyEl) {
				window.open(url, '_blank');
				return;
			}
			rembModalBodyEl.innerHTML = '<div class="text-muted">Chargement...</div>';
			const instance = window.bootstrap.Modal.getInstance(rembModalEl) || new window.bootstrap.Modal(rembModalEl);
			instance.show();
			fetch(url, { credentials: 'same-origin' })
				.then(r => r.text())
				.then(html => { rembModalBodyEl.innerHTML = html; })
				.catch(() => { rembModalBodyEl.innerHTML = '<div class="alert alert-danger">Impossible de charger la liste.</div>'; });
		}

		document.addEventListener('click', function (e) {
			const btn = e.target.closest('.js-open-remb');
			if (!btn) return;
			const url = btn.getAttribute('data-url');
			if (!url) return;
			e.preventDefault();
			openRembModal(url);
		});

		if (rapportPrintBtnEl) {
			rapportPrintBtnEl.addEventListener('click', function () {
				try {
					const win = rapportFrameEl && rapportFrameEl.contentWindow ? rapportFrameEl.contentWindow : null;
					if (win && typeof win.printPdf === 'function') {
						win.printPdf();
						return;
					}
					if (win && typeof win.print === 'function') {
						win.print();
					}
				} catch (err) {
					// noop
				}
			});
		}

		if (rapportModalEl) {
			rapportModalEl.addEventListener('hidden.bs.modal', function () {
				if (rapportFrameEl) rapportFrameEl.src = 'about:blank';
			});
		}

		if (rembModalEl) {
			rembModalEl.addEventListener('hidden.bs.modal', function () {
				if (rembModalBodyEl) rembModalBodyEl.innerHTML = '<div class="text-muted">Chargement...</div>';
			});
		}
	});
	</script>

   <?php include('../public/footer.php'); ?>
