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
												<div class="btn-group" role="group" aria-label="Comptes">
													<button type="button" class="btn btn-primary" id="openAddCompteBtn">Ajouter un compte</button>
													<button type="button" class="btn btn-default" id="openEditCompteByCodeBtn">Modifier un compte</button>
												</div>
											</div>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">

											<thead>
												<tr>
                                                    <th>CODE</th>
                                                    <th>COMPTE</th>
                                                    <th>MODE PAIEMENT</th>
                                                    <th>DEBIT</th>
													<th>CREDIT</th>
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
								const openEditByCodeBtn = document.getElementById('openEditCompteByCodeBtn');
						const modalEl = document.getElementById('addCompteModal');
						const modalBody = document.getElementById('addCompteModalBody');
						const modalTitle = modalEl ? modalEl.querySelector('.modal-title') : null;
						let pendingReload = false;

						function getModalInstance() {
							if (!window.bootstrap || !window.bootstrap.Modal) return null;
							return window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
						}

						async function loadCompteForm(url, title) {
							if (modalTitle) modalTitle.textContent = title || 'Compte';
							modalBody.innerHTML = '<div class="text-muted">Chargement...</div>';
							try {
								const res = await fetch(url, { credentials: 'same-origin' });
								const html = await res.text();
								modalBody.innerHTML = html;
							} catch (e) {
								modalBody.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement du formulaire.</div>';
							}
						}

								function renderLookupByCode() {
									if (modalTitle) modalTitle.textContent = 'Modifier un compte';
									modalBody.innerHTML = '' +
										'<div class="alert alert-info">Saisissez le <strong>code</strong> du compte à éditer.</div>' +
										'<div id="editCompteLookupAlert" class="alert alert-danger" style="display:none;"></div>' +
										'<form id="editCompteLookupForm" novalidate>' +
											'<div class="row g-3 align-items-end">' +
												'<div class="col-md-6">' +
													'<label class="col-form-label" for="editCompteCodeInput">Code du compte</label>' +
													'<input type="text" class="form-control" id="editCompteCodeInput" required>' +
												'</div>' +
												'<div class="col-md-6">' +
													'<button type="submit" class="btn btn-primary" id="editCompteLookupBtn">Rechercher</button>' +
												'</div>' +
											'</div>' +
										'</form>';
								}

						function showErrors(errors) {
								const el = ensureAlertContainer('addCompteErrors', 'alert alert-danger');
								const okEl = modalBody.querySelector('#addCompteSuccess');
								if (okEl) { okEl.style.display = 'none'; okEl.textContent = ''; }
							const items = (errors || []).map(msg => '<li>' + String(msg) + '</li>').join('');
							el.innerHTML = '<strong>Erreur(s)</strong><br><ul class="mb-0">' + items + '</ul>';
							el.style.display = '';
						}

							function showSuccess(message) {
								const el = ensureAlertContainer('addCompteSuccess', 'alert alert-success');
								const errEl = modalBody.querySelector('#addCompteErrors');
								if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
								el.innerHTML = '<strong>Succès</strong><br>' + String(message || 'Opération réussie.');
								el.style.display = '';
							}

							function ensureAlertContainer(id, className) {
								let el = modalBody.querySelector('#' + id);
								if (el) return el;
								el = document.createElement('div');
								el.id = id;
								el.className = className;
								el.style.display = 'none';
								modalBody.insertBefore(el, modalBody.firstChild);
								return el;
							}

						async function submitCompte(form) {
							const submitBtn = modalBody.querySelector('#addCompteSubmitBtn') || form.querySelector('button[type="submit"]');
							if (submitBtn) submitBtn.disabled = true;
								// reset alerts
								const errEl = modalBody.querySelector('#addCompteErrors');
								if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
								const okEl = modalBody.querySelector('#addCompteSuccess');
								if (okEl) { okEl.style.display = 'none'; okEl.textContent = ''; }
							const fd = new FormData(form);

							try {
								const actionUrl = form.getAttribute('action') || 'addacount.php';
								const res = await fetch(actionUrl, {
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
										showSuccess(payload.message || 'Compte enregistré avec succès.');
										// laisse le temps de lire le message
										const instance = getModalInstance();
										setTimeout(function () {
											if (instance) instance.hide();
										}, 900);
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
								await loadCompteForm('addacount.php', 'Ajouter un compte');
								const instance = getModalInstance();
								if (instance) instance.show();
							});
						}

								if (openEditByCodeBtn && modalEl) {
									openEditByCodeBtn.addEventListener('click', function () {
										renderLookupByCode();
										const instance = getModalInstance();
										if (instance) instance.show();
										try {
											const inp = modalBody.querySelector('#editCompteCodeInput');
											if (inp) inp.focus();
										} catch (e) {}
									});
								}

						// Édition depuis la liste
						document.addEventListener('click', async function (e) {
							const btn = e.target && e.target.closest ? e.target.closest('.js-edit-compte') : null;
							if (!btn) return;
							const idCompte = btn.getAttribute('data-id-compte');
							if (!idCompte) return;
							await loadCompteForm('editacount.php?id_compte=' + encodeURIComponent(idCompte), 'Éditer le compte');
							const instance = getModalInstance();
							if (instance) instance.show();
						});

							// Recherche par code puis chargement du formulaire d'édition
							modalEl.addEventListener('submit', async function (e) {
								const form = e.target;
								if (!form || form.id !== 'editCompteLookupForm') return;
								e.preventDefault();
								const alertEl = modalBody.querySelector('#editCompteLookupAlert');
								const btn = modalBody.querySelector('#editCompteLookupBtn');
								const inp = modalBody.querySelector('#editCompteCodeInput');
								const code = inp ? String(inp.value || '').trim() : '';
								if (!code) {
									if (alertEl) {
										alertEl.textContent = 'Veuillez saisir le code du compte.';
										alertEl.style.display = '';
									}
									return;
								}
								if (alertEl) { alertEl.style.display = 'none'; alertEl.textContent = ''; }
								if (btn) btn.disabled = true;
								try {
									await loadCompteForm('editacount.php?code=' + encodeURIComponent(code), 'Éditer le compte');
								} finally {
									if (btn) btn.disabled = false;
								}
							});

						modalEl.addEventListener('submit', function (e) {
							const form = e.target;
							if (form && (form.id === 'addCompteForm' || form.id === 'editCompteForm')) {
								e.preventDefault();
								submitCompte(form);
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
