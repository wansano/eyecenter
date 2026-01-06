<?php
include('../PUBLIC/connect.php');
include('../PUBLIC/fonction.php');
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
											<div class="mb-3 text-end">
												<button type="button" class="btn btn-primary" id="openAddCompteBtn">
													Ajouter un compte
												</button>
											</div>
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

				<!-- Modal: Ajouter un compte -->
				<div class="modal fade" id="addCompteModal" tabindex="-1" aria-hidden="true">
					<div class="modal-dialog modal-lg modal-dialog-centered">
						<div class="modal-content">
							<div class="modal-header">
								<h5 class="modal-title">Ajouter un compte</h5>
								<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
							</div>
							<div class="modal-body" id="addCompteModalBody">
								<div class="text-muted">Chargement...</div>
							</div>
						</div>
					</div>
				</div>

				<script>
					(function () {
						const openBtn = document.getElementById('openAddCompteBtn');
						const modalEl = document.getElementById('addCompteModal');
						const modalBody = document.getElementById('addCompteModalBody');
						let pendingReload = false;

						function getModalInstance() {
							if (!window.bootstrap || !window.bootstrap.Modal) return null;
							return window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
						}

						async function loadAddCompteForm() {
							modalBody.innerHTML = '<div class="text-muted">Chargement...</div>';
							try {
								const res = await fetch('addacount.php', { credentials: 'same-origin' });
								const html = await res.text();
								modalBody.innerHTML = html;
							} catch (e) {
								modalBody.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement du formulaire.</div>';
							}
						}

						function showErrors(errors) {
							const el = modalBody.querySelector('#addCompteErrors');
							if (!el) return;
							const items = (errors || []).map(msg => '<li>' + String(msg) + '</li>').join('');
							el.innerHTML = '<strong>Erreur(s)</strong><br><ul class="mb-0">' + items + '</ul>';
							el.style.display = '';
						}

						async function submitAddCompte(form) {
							const submitBtn = modalBody.querySelector('#addCompteSubmitBtn');
							if (submitBtn) submitBtn.disabled = true;
							const fd = new FormData(form);

							try {
								const res = await fetch('addacount.php', {
									method: 'POST',
									body: fd,
									credentials: 'same-origin'
								});

								let payload = null;
								try {
									payload = await res.json();
								} catch (e) {
									payload = null;
								}

								if (res.ok && payload && payload.success) {
									pendingReload = true;
									const instance = getModalInstance();
									if (instance) instance.hide();
									return;
								}

								showErrors((payload && payload.errors) ? payload.errors : ['Une erreur est survenue.']);
							} catch (e) {
								showErrors(['Une erreur est survenue.']);
							} finally {
								if (submitBtn) submitBtn.disabled = false;
							}
						}

						if (openBtn && modalEl) {
							openBtn.addEventListener('click', async function () {
								await loadAddCompteForm();
								const instance = getModalInstance();
								if (instance) instance.show();
							});
						}

						modalEl.addEventListener('submit', function (e) {
							const form = e.target;
							if (form && form.id === 'addCompteForm') {
								e.preventDefault();
								submitAddCompte(form);
							}
						});

						modalEl.addEventListener('hidden.bs.modal', function () {
							if (pendingReload) {
								window.location.reload();
							}
						});
					})();
				</script>

            <?php include('../PUBLIC/footer.php');?>
