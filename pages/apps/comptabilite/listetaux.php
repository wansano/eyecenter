<?php
include('../PUBLIC/connect.php');
session_start();
$errors=0;

if (isset($_POST['activer'])) 
{	
	$reponse1 = $bdd->prepare('SELECT * FROM taux WHERE id_taux = ?');
	$reponse1->execute([$_POST['activer']]);
	$donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC);
	$pour = $donnees1['taux_pour'];

		$reponse = $bdd->prepare('UPDATE taux SET status = ? WHERE taux_pour = ?');
		$reponse ->execute([0, $pour]);

		$reponse = $bdd->prepare('UPDATE taux SET status = ? WHERE id_taux = ?');
		$reponse ->execute([1, $_POST['activer']]);
		$errors=2;
}

if (isset($_POST['supprimer'])) 
{
    $reponse = $bdd->prepare('UPDATE taux SET status = ? WHERE id_taux = ?');
    $reponse ->execute([3, $_POST['supprimer']]);
    $errors=4;
}                
  
include('../PUBLIC/header.php');
	?>

	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Liste des taux de remise</h2>
					</header>

					<!-- start: page -->
					<div class="col-md-12">
						<div class="row">
							<div class="col">
								<section class="card">
									<div class="card-body">
										<div class="mb-3 text-end">
											<button type="button" class="btn btn-primary" id="openAddTauxBtn">Ajouter un taux</button>
										</div>
                                    	<?php 
                                            if ($errors==2) {
                                            echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Taux activé avec succès.</li>
                                                </div>
                                                '; }

                                            if ($errors==4) {
                                                echo '
                                                <div class="alert alert-danger">
                                                <li><strong>Succès !</strong>
                                                <br>Ce taux à été supprimer avec succès.</li>
                                                </div>
                                                '; }
                                        ?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
                                                    <th>DATE AJOUT</th>
                                                    <th>TAUX %</th>
													<th>TAUX AFFECTE</th>
                                                    <th>ACTION</th>
												</tr>
											</thead>
											<tbody>
											<?php
												$reponse1 = $bdd->prepare('SELECT * FROM taux WHERE status != ? ORDER BY id_taux');
												$reponse1 -> execute([3]);
												while ($donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC))
													{
														$status = (int)($donnees1['status'] ?? 0);
														$tauxpour = (int)($donnees1['taux_pour'] ?? 0);
														$labelPour = ($tauxpour === 1) ? 'Boutique' : 'Clinique';
														$idTaux = (int)($donnees1['id_taux'] ?? 0);

														echo '<tr>';
														echo '<td>' . htmlspecialchars((string)($donnees1['date'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
														echo '<td>' . htmlspecialchars((string)($donnees1['taux'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
														echo '<td>' . htmlspecialchars($labelPour, ENT_QUOTES, 'UTF-8') . '</td>';
														echo '<td>';

														if ($status === 1) {
															echo '<button type="button" class="btn btn-sm btn-success" disabled>actif actuellement</button>';
														}

														if ($status === 0) {
															echo '<form action="listetaux.php" method="post" class="d-inline">';
															echo '<input type="hidden" name="activer" value="' . htmlspecialchars((string)$idTaux, ENT_QUOTES, 'UTF-8') . '">';
															echo '<button type="submit" class="btn btn-sm btn-warning"><i class="fa fa-unlock-alt"></i> activer</button>';
															echo '</form> ';

															echo '<form action="listetaux.php" method="post" class="d-inline">';
															echo '<input type="hidden" name="supprimer" value="' . htmlspecialchars((string)$idTaux, ENT_QUOTES, 'UTF-8') . '">';
															echo '<button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-times"></i> supprimer</button>';
															echo '</form>';
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

				<!-- Modal: Ajouter un taux -->
				<div class="modal fade" id="addTauxModal" tabindex="-1" aria-hidden="true">
					<div class="modal-dialog modal-lg modal-dialog-centered">
						<div class="modal-content">
							<div class="modal-header">
								<h5 class="modal-title">Ajouter un taux de remise</h5>
								<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
							</div>
							<div class="modal-body" id="addTauxModalBody">
								<div class="text-muted">Chargement...</div>
							</div>
						</div>
					</div>
				</div>

								<!-- Modal: Réactiver un taux archivé -->
								<div class="modal fade" id="reactivateTauxModal" tabindex="-1" aria-hidden="true">
									<div class="modal-dialog modal-md modal-dialog-centered">
										<div class="modal-content">
											<div class="modal-header">
												<h5 class="modal-title">Taux déjà archivé</h5>
												<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
											</div>
											<div class="modal-body">
												<div id="reactivateTauxMessage" class="mb-2">
													Ce taux existe déjà mais il a été archivé. Voulez-vous le réactiver ?
												</div>
												<div id="reactivateTauxErrors" class="alert alert-danger" style="display:none;"></div>
											</div>
											<div class="modal-footer">
												<button type="button" class="btn btn-default" data-bs-dismiss="modal">Annuler</button>
												<button type="button" class="btn btn-warning" id="confirmReactivateTauxBtn">Réactiver</button>
											</div>
										</div>
									</div>
								</div>

				<script>
					(function () {
						const openBtn = document.getElementById('openAddTauxBtn');
						const modalEl = document.getElementById('addTauxModal');
						const modalBody = document.getElementById('addTauxModalBody');
										const reactivateModalEl = document.getElementById('reactivateTauxModal');
										const reactivateMsgEl = document.getElementById('reactivateTauxMessage');
										const reactivateErrEl = document.getElementById('reactivateTauxErrors');
										const confirmReactivateBtn = document.getElementById('confirmReactivateTauxBtn');
						let pendingReload = false;
										let pendingReactivate = { id_taux: null, activer_immediatement: 0 };

						function getModalInstance() {
							if (!window.bootstrap || !window.bootstrap.Modal) return null;
							return window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
						}

										function getReactivateModalInstance() {
											if (!window.bootstrap || !window.bootstrap.Modal) return null;
											return window.bootstrap.Modal.getInstance(reactivateModalEl) || new window.bootstrap.Modal(reactivateModalEl);
										}

						async function loadTauxForm() {
							modalBody.innerHTML = '<div class="text-muted">Chargement...</div>';
							try {
								const res = await fetch('addtaux.php', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
								const html = await res.text();
								modalBody.innerHTML = html;
							} catch (e) {
								modalBody.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement du formulaire.</div>';
							}
						}

						function showErrors(errors) {
							const el = modalBody.querySelector('#addTauxErrors');
							if (!el) return;
							const okEl = modalBody.querySelector('#addTauxSuccess');
							if (okEl) { okEl.style.display = 'none'; okEl.textContent = ''; }
							const items = (errors || []).map(msg => '<li>' + String(msg) + '</li>').join('');
							el.innerHTML = '<strong>Erreur(s)</strong><br><ul class="mb-0">' + items + '</ul>';
							el.style.display = '';
						}

						function showSuccess(message) {
							const el = modalBody.querySelector('#addTauxSuccess');
							if (!el) return;
							const errEl = modalBody.querySelector('#addTauxErrors');
							if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
							el.innerHTML = '<strong>Succès</strong><br>' + String(message || 'Taux ajouté avec succès.');
							el.style.display = '';
						}

						async function submitTaux(form) {
							const submitBtn = modalBody.querySelector('#addTauxSubmitBtn') || form.querySelector('button[type="submit"]');
							if (submitBtn) submitBtn.disabled = true;
							const fd = new FormData(form);

							try {
								const res = await fetch(form.getAttribute('action') || 'addtaux.php', {
									method: 'POST',
									body: fd,
									credentials: 'same-origin',
									headers: { 'X-Requested-With': 'XMLHttpRequest' }
								});

								let payload = null;
								try { payload = await res.json(); } catch (e) { payload = null; }

												if (res.ok && payload && payload.success) {
									pendingReload = true;
									showSuccess(payload.message || 'Taux ajouté avec succès.');
									const instance = getModalInstance();
									setTimeout(function () {
										if (instance) instance.hide();
									}, 900);
									return;
								}

												// Taux déjà archivé : proposer la réactivation
												if (payload && payload.code === 'DELETED_EXISTS' && payload.existing && payload.existing.id_taux) {
													pendingReactivate.id_taux = payload.existing.id_taux;
													pendingReactivate.activer_immediatement = payload.existing.activer_immediatement ? 1 : 0;
													if (reactivateErrEl) { reactivateErrEl.style.display = 'none'; reactivateErrEl.textContent = ''; }
													if (reactivateMsgEl) {
														reactivateMsgEl.textContent = (payload.message || 'Ce taux existe déjà mais il est archivé.') + ' Voulez-vous le réactiver ?';
													}
													const rInstance = getReactivateModalInstance();
													if (rInstance) rInstance.show();
													return;
												}

								showErrors((payload && payload.errors) ? payload.errors : ['Une erreur est survenue.']);
							} catch (e) {
								showErrors(['Une erreur est survenue.']);
							} finally {
								if (submitBtn) submitBtn.disabled = false;
							}
						}

										async function reactivateTaux() {
											if (!pendingReactivate.id_taux) return;
											if (confirmReactivateBtn) confirmReactivateBtn.disabled = true;
											try {
												const fd = new FormData();
												fd.append('reactiver_id', String(pendingReactivate.id_taux));
												fd.append('activer_immediatement', String(pendingReactivate.activer_immediatement || 0));

												const res = await fetch('addtaux.php', {
													method: 'POST',
													body: fd,
													credentials: 'same-origin',
													headers: { 'X-Requested-With': 'XMLHttpRequest' }
												});

												let payload = null;
												try { payload = await res.json(); } catch (e) { payload = null; }

												if (res.ok && payload && payload.success) {
													pendingReload = true;
													const rInstance = getReactivateModalInstance();
													if (rInstance) rInstance.hide();
													const instance = getModalInstance();
													if (instance) instance.hide();
													return;
												}

												if (reactivateErrEl) {
													reactivateErrEl.textContent = (payload && payload.errors && payload.errors[0]) ? payload.errors[0] : 'Une erreur est survenue.';
													reactivateErrEl.style.display = '';
												}
											} catch (e) {
												if (reactivateErrEl) {
													reactivateErrEl.textContent = 'Une erreur est survenue.';
													reactivateErrEl.style.display = '';
												}
											} finally {
												if (confirmReactivateBtn) confirmReactivateBtn.disabled = false;
											}
										}

						if (openBtn && modalEl) {
							openBtn.addEventListener('click', async function () {
								await loadTauxForm();
								const instance = getModalInstance();
								if (instance) instance.show();
							});
						}

										if (confirmReactivateBtn) {
											confirmReactivateBtn.addEventListener('click', function () {
												reactivateTaux();
											});
										}

						modalEl.addEventListener('submit', function (e) {
							const form = e.target;
							if (form && form.id === 'addTauxForm') {
								e.preventDefault();
								submitTaux(form);
							}
						});

						modalEl.addEventListener('hidden.bs.modal', function () {
							if (pendingReload) {
								window.location.reload();
							}
						});

										reactivateModalEl.addEventListener('hidden.bs.modal', function () {
											if (reactivateErrEl) { reactivateErrEl.style.display = 'none'; reactivateErrEl.textContent = ''; }
											pendingReactivate = { id_taux: null, activer_immediatement: 0 };
										});
					})();
				</script>
            <?php include('../PUBLIC/footer.php');?>