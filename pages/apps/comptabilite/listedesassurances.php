<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();
$errors = 0;

function updateAssuranceStatus(PDO $bdd, int $id, int $status): bool
{
    try {
        $st = $bdd->prepare('UPDATE assurances SET status = ? WHERE id_assurance = ?');
        return $st->execute([$status, $id]);
    } catch (Throwable $e) {
        $st = $bdd->prepare('UPDATE assurances SET status = ? WHERE d_assurance = ?');
        return $st->execute([$status, $id]);
    }
}

function fetchAssurances(PDO $bdd): array
{
    try {
        $st = $bdd->prepare('SELECT * FROM assurances ORDER BY id_assurance');
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $st = $bdd->prepare('SELECT * FROM assurances ORDER BY d_assurance');
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (isset($_POST['activer'])) {
    updateAssuranceStatus($bdd, (int)$_POST['activer'], 1);
    $errors = 2;
}

if (isset($_POST['desactiver'])) {
    updateAssuranceStatus($bdd, (int)$_POST['desactiver'], 0);
    $errors = 3;
}

if (isset($_POST['supprimer'])) {
    updateAssuranceStatus($bdd, (int)$_POST['supprimer'], 3);
    $errors = 4;
}

include('../PUBLIC/header.php');
?>
	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Liste des Assurances</h2>
					</header>

					<!-- start: page -->
					<div class="col-md-12">
						<div class="row">
							<div class="col">
								<section class="card">
									<div class="card-body">
										<div class="mb-3 text-end">
											<button type="button" class="btn btn-primary" id="openAddAssuranceBtn">Ajouter un assureur</button>
										</div>
										<?php
											if ($errors == 2) {
												echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Assureur activé avec succès.</li></div>';
											}
											if ($errors == 3) {
												echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Assureur désactivé avec succès.</li></div>';
											}
											if ($errors == 4) {
												echo '<div class="alert alert-warning"><li><strong>Succès !</strong><br>Assureur archivé avec succès.</li></div>';
											}
										?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
													<th>ID</th>
													<th>ASSURANCE</th>
													<th>N° CONTRAT</th>
													<th>TYPE</th>
													<th>TELEPHONE</th>
													<th>COURRIEL</th>
													<th>ADRESSE</th>
													<th>DEBIT</th>
													<th>CREDIT</th>
													<th>SOLDE</th>
													<th>DATE CREATION</th>
													<th>STATUS</th>
													<th>ACTION</th>
												</tr>
											</thead>
											<tbody>
											<?php
												$rows = fetchAssurances($bdd);
												foreach ($rows as $row) {
													$status = (int)($row['status'] ?? 1);
													if ($status === 3) {
														continue;
													}

													$id = (int)($row['id_assurance'] ?? $row['d_assurance'] ?? 0);
													$adresseRaw = (string)($row['adresse'] ?? '');
													$adresseAff = adress($adresseRaw) ?: $adresseRaw;

													echo '<tr>';
													echo '<td>ASEC' . htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') . '</td>';
													echo '<td>' . htmlspecialchars((string)($row['assurance'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
													echo '<td>' . htmlspecialchars((string)($row['contrat'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
													echo '<td>' . htmlspecialchars((string)($row['type_assurance'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
													echo '<td>' . htmlspecialchars((string)($row['telephone'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
													echo '<td>' . htmlspecialchars((string)($row['email'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
													echo '<td>' . htmlspecialchars((string)$adresseAff, ENT_QUOTES, 'UTF-8') . '</td>';
													echo '<td>' . htmlspecialchars((string)($row['debit'] ?? 0), ENT_QUOTES, 'UTF-8') . '</td>';
													echo '<td>' . htmlspecialchars((string)($row['credit'] ?? 0), ENT_QUOTES, 'UTF-8') . '</td>';
													echo '<td>' . htmlspecialchars((string)($row['solde'] ?? 0), ENT_QUOTES, 'UTF-8') . '</td>';
													echo '<td>' . htmlspecialchars((string)($row['date_creation'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
													echo '<td>' . ($status === 1 ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-secondary">Inactif</span>') . '</td>';
													echo '<td>';

													if ($status === 0) {
														echo '<form action="listedesassurances.php" method="post" class="d-inline">';
														echo '<input type="hidden" name="activer" value="' . htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') . '">';
														echo '<button type="submit" class="btn btn-sm btn-warning"><i class="fa fa-unlock-alt"></i> activer</button>';
														echo '</form> ';
													}

													if ($status === 1) {
														echo '<form action="listedesassurances.php" method="post" class="d-inline">';
														echo '<input type="hidden" name="desactiver" value="' . htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') . '">';
														echo '<button type="submit" class="btn btn-sm btn-success"><i class="fa fa-lock"></i> désactiver</button>';
														echo '</form> ';
													}

													echo '<form action="listedesassurances.php" method="post" class="d-inline">';
													echo '<input type="hidden" name="supprimer" value="' . htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') . '">';
													echo '<button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-times"></i> archiver</button>';
													echo '</form>';

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

					<!-- Modal: Ajouter un assureur -->
					<div class="modal fade" id="addAssuranceModal" tabindex="-1" aria-hidden="true">
						<div class="modal-dialog modal-lg modal-dialog-centered">
							<div class="modal-content">
								<div class="modal-header">
									<h5 class="modal-title">Ajouter un assureur</h5>
									<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
								</div>
								<div class="modal-body" id="addAssuranceModalBody">
									<div class="text-muted">Chargement...</div>
								</div>
							</div>
						</div>
					</div>

					<script>
						(function () {
							const openBtn = document.getElementById('openAddAssuranceBtn');
							const modalEl = document.getElementById('addAssuranceModal');
							const modalBody = document.getElementById('addAssuranceModalBody');
							let pendingReload = false;

							function getModalInstance() {
								if (!window.bootstrap || !window.bootstrap.Modal) return null;
								return window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
							}

							async function loadAssuranceForm() {
								modalBody.innerHTML = '<div class="text-muted">Chargement...</div>';
								try {
									const res = await fetch('addassurance.php', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
									const html = await res.text();
									modalBody.innerHTML = html;
								} catch (e) {
									modalBody.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement du formulaire.</div>';
								}
							}

							function showErrors(errors) {
								const el = modalBody.querySelector('#addAssuranceErrors');
								if (!el) return;
								const okEl = modalBody.querySelector('#addAssuranceSuccess');
								if (okEl) { okEl.style.display = 'none'; okEl.textContent = ''; }
								const items = (errors || []).map(msg => '<li>' + String(msg) + '</li>').join('');
								el.innerHTML = '<strong>Erreur(s)</strong><br><ul class="mb-0">' + items + '</ul>';
								el.style.display = '';
							}

							function showSuccess(message) {
								const el = modalBody.querySelector('#addAssuranceSuccess');
								if (!el) return;
								const errEl = modalBody.querySelector('#addAssuranceErrors');
								if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
								el.innerHTML = '<strong>Succès</strong><br>' + String(message || 'Assureur ajouté avec succès.');
								el.style.display = '';
							}

							async function submitAssurance(form) {
								const submitBtn = modalBody.querySelector('#addAssuranceSubmitBtn') || form.querySelector('button[type="submit"]');
								if (submitBtn) submitBtn.disabled = true;
								const fd = new FormData(form);

								try {
									const res = await fetch(form.getAttribute('action') || 'addassurance.php', {
										method: 'POST',
										body: fd,
										credentials: 'same-origin',
										headers: { 'X-Requested-With': 'XMLHttpRequest' }
									});

									let payload = null;
									try { payload = await res.json(); } catch (e) { payload = null; }

									if (res.ok && payload && payload.success) {
										pendingReload = true;
										showSuccess(payload.message || 'Assureur ajouté avec succès.');
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
									await loadAssuranceForm();
									const instance = getModalInstance();
									if (instance) instance.show();
								});
							}

							modalEl.addEventListener('submit', function (e) {
								const form = e.target;
								if (form && form.id === 'addAssuranceForm') {
									e.preventDefault();
									submitAssurance(form);
								}
							});

							modalEl.addEventListener('hidden.bs.modal', function () {
								if (pendingReload) {
									window.location.reload();
								}
							});
						})();
					</script>
				</section>
			</div>
			<?php include('../PUBLIC/footer.php'); ?>
