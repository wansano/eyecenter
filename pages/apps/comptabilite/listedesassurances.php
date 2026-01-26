<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();
$errors = 0;

// Devise (fallback)
$devise = 'GNF';
try {
	$stDev = $bdd->query('SELECT devise FROM profil_entreprise LIMIT 1');
	if ($stDev) {
		$dev = $stDev->fetchColumn();
		if ($dev !== false && trim((string)$dev) !== '') {
			$devise = trim((string)$dev);
		}
	}
} catch (Throwable $e) {
	// ignore
}

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

function appec_getAssuranceIdColumn(PDO $bdd): ?string
{
	if (!function_exists('dbTableHasColumn')) return null;
	if (dbTableHasColumn($bdd, 'assurances', 'id_assurance')) return 'id_assurance';
	if (dbTableHasColumn($bdd, 'assurances', 'd_assurance')) return 'd_assurance';
	if (dbTableHasColumn($bdd, 'assurances', 'id')) return 'id';
	return null;
}

function appec_updateAssurance(PDO $bdd, int $id, array $data): bool
{
	$idCol = appec_getAssuranceIdColumn($bdd) ?: 'id_assurance';

	$sets = [];
	$params = [];

	$cols = [
		'assurance' => 'assurance',
		'contrat' => 'contrat',
		'type_assurance' => 'type_assurance',
		'telephone' => 'telephone',
		'adresse' => 'adresse',
	];

	foreach ($cols as $key => $colName) {
		$canUse = true;
		if (function_exists('dbTableHasColumn')) {
			$canUse = dbTableHasColumn($bdd, 'assurances', $colName);
		}
		if (!$canUse) continue;
		if (!array_key_exists($key, $data)) continue;
		$sets[] = $colName . ' = ?';
		$params[] = $data[$key];
	}

	if (empty($sets)) return false;

	$params[] = $id;
	$sql = 'UPDATE assurances SET ' . implode(', ', $sets) . ' WHERE ' . $idCol . ' = ? LIMIT 1';
	$st = $bdd->prepare($sql);
	return $st->execute($params);
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

if (isset($_POST['modifier_assurance'])) {
	$id = isset($_POST['id_assurance']) ? (int)$_POST['id_assurance'] : 0;
	$nom = trim((string)($_POST['assurance'] ?? ''));
	$contrat = trim((string)($_POST['contrat'] ?? ''));
	$type = trim((string)($_POST['type_assurance'] ?? ''));
	$telephone = trim((string)($_POST['telephone'] ?? ''));
	$adresse = trim((string)($_POST['adresse'] ?? ''));
	$status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
	if ($status !== 0 && $status !== 1) $status = 1;

	if ($id > 0 && $nom !== '') {
		try {
			appec_updateAssurance($bdd, $id, [
				'assurance' => $nom,
				'contrat' => $contrat,
				'type_assurance' => $type,
				'telephone' => $telephone,
				'adresse' => $adresse,
				'status' => $status,
			]);
			$errors = 5;
		} catch (Throwable $e) {
			$errors = 0;
		}
	} else {
		$errors = 0;
	}
}

// Endpoint AJAX: détails assurance pour modal
if (isset($_GET['ajax_details'])) {
	header('Content-Type: application/json; charset=UTF-8');
	$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
	if ($id <= 0) {
		echo json_encode(['success' => false, 'message' => 'ID invalide.']);
		exit;
	}

	try {
		$idCol = null;
		if (function_exists('dbTableHasColumn')) {
			if (dbTableHasColumn($bdd, 'assurances', 'id_assurance')) {
				$idCol = 'id_assurance';
			} elseif (dbTableHasColumn($bdd, 'assurances', 'd_assurance')) {
				$idCol = 'd_assurance';
			} elseif (dbTableHasColumn($bdd, 'assurances', 'id')) {
				$idCol = 'id';
			}
		}
		if (!$idCol) {
			$idCol = 'id_assurance';
		}

		$st = $bdd->prepare('SELECT * FROM assurances WHERE ' . $idCol . ' = ? LIMIT 1');
		$st->execute([$id]);
		$row = $st->fetch(PDO::FETCH_ASSOC);
		if (!$row) {
			echo json_encode(['success' => false, 'message' => 'Assurance introuvable.']);
			exit;
		}

		$status = (int)($row['status'] ?? 1);
		$adresseRaw = (string)($row['adresse'] ?? '');
		$adresseAff = adress($adresseRaw) ?: $adresseRaw;

		$patientsCount = null;
		if (function_exists('dbTableHasColumn') && dbTableHasColumn($bdd, 'patients', 'assurance')) {
			$stC = $bdd->prepare('SELECT COUNT(*) FROM patients WHERE assurance = ?');
			$stC->execute([$id]);
			$patientsCount = (int)$stC->fetchColumn();
		}

		$credit = null;
		$debit = null;
		$solde = null;
		if (function_exists('dbTableHasColumn') && dbTableHasColumn($bdd, 'assurances', 'debit')) {
			$debit = $row['debit'] ?? null;
		}
		if (function_exists('dbTableHasColumn') && dbTableHasColumn($bdd, 'assurances', 'credit')) {
			$credit = $row['credit'] ?? null;
		}
		if (function_exists('dbTableHasColumn') && dbTableHasColumn($bdd, 'assurances', 'solde')) {
			$solde = $row['solde'] ?? null;
		}

		echo json_encode([
			'success' => true,
			'assurance' => [
				'id' => (int)($row['id_assurance'] ?? $row['d_assurance'] ?? $row['id'] ?? $id),
				'nom' => (string)($row['assurance'] ?? ''),
				'contrat' => (string)($row['contrat'] ?? ''),
				'type' => (string)($row['type_assurance'] ?? ''),
				'telephone' => (string)($row['telephone'] ?? ''),
				'adresse' => (string)$adresseAff,
				'status' => $status,
				'date_creation' => (string)($row['date_creation'] ?? ''),
			],
			'situation' => [
				'patients' => $patientsCount,
				'debit' => $debit,
				'credit' => $credit,
				'solde' => $solde,
			],
		], JSON_UNESCAPED_UNICODE);
		exit;
	} catch (Throwable $e) {
		error_log('[ASSURANCE DETAILS] ' . $e->getMessage());
		echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement des détails.']);
		exit;
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
										if ($errors == 5) {
											echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Assureur modifié avec succès.</li></div>';
										}
										?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
													<th>ID</th>
													<th>ASSURANCE</th>
													<th>N° CONTRAT</th>
													<th>TYPE</th>
													<th>CONTACT</th>
													<th>ADRESSE</th>
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
													echo '<td>' . htmlspecialchars((string)$adresseAff, ENT_QUOTES, 'UTF-8') . '</td>';
													echo '<td>' . ($status === 1 ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-secondary">Inactif</span>') . '</td>';
													echo '<td>';

													echo '<button type="button" class="btn btn-sm btn-info me-1 js-assurance-details" data-id="' . htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') . '"><i class="fa fa-eye"></i> détails</button> ';

													if ($status === 0) {
														echo '<form action="listedesassurances.php" method="post" class="d-inline">';
														echo '<input type="hidden" name="activer" value="' . htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') . '">';
														echo '<button type="submit" class="btn btn-sm btn-warning"><i class="fa fa-unlock-alt"></i> activer</button>';
														echo '</form> ';

														echo '<button type="button" class="btn btn-sm btn-primary me-1 js-edit-assurance" '
														. 'data-id="' . htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') . '" '
														. 'data-nom="' . htmlspecialchars((string)($row['assurance'] ?? ''), ENT_QUOTES, 'UTF-8') . '" '
														. 'data-contrat="' . htmlspecialchars((string)($row['contrat'] ?? ''), ENT_QUOTES, 'UTF-8') . '" '
														. 'data-type="' . htmlspecialchars((string)($row['type_assurance'] ?? ''), ENT_QUOTES, 'UTF-8') . '" '
														. 'data-telephone="' . htmlspecialchars((string)($row['telephone'] ?? ''), ENT_QUOTES, 'UTF-8') . '" '
														. 'data-adresse="' . htmlspecialchars((string)$adresseAff, ENT_QUOTES, 'UTF-8') . '" '
														. 'data-status="' . htmlspecialchars((string)$status, ENT_QUOTES, 'UTF-8') . '">'
														. '<i class="fa fa-edit"></i> modifier</button> ';

														echo '<form action="listedesassurances.php" method="post" class="d-inline">';
														echo '<input type="hidden" name="supprimer" value="' . htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') . '">';
														echo '<button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-times"></i> archiver</button>';
														echo '</form>';
													}

													if ($status === 1) {
														echo '<form action="listedesassurances.php" method="post" class="d-inline">';
														echo '<input type="hidden" name="desactiver" value="' . htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') . '">';
														echo '<button type="submit" class="btn btn-sm btn-success"><i class="fa fa-lock"></i> désactiver</button>';
														echo '</form> ';

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

					<!-- Modal: Détails assurance -->
					<div class="modal fade" id="assuranceDetailsModal" tabindex="-1" aria-hidden="true">
						<div class="modal-dialog modal-lg modal-dialog-scrollable">
							<div class="modal-content">
								<div class="modal-header">
									<h5 class="modal-title">Détails de l'assurance</h5>
									<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
								</div>
								<div class="modal-body">
									<div id="assuranceDetailsAlert" class="alert d-none" role="alert"></div>
									<div class="table-responsive">
										<table class="table table-bordered mb-0">
											<tbody>
												<tr><th style="width:35%">ID</th><td id="ad_id">-</td></tr>
												<tr><th>Assurance</th><td id="ad_nom">-</td></tr>
												<tr><th>N° Contrat</th><td id="ad_contrat">-</td></tr>
												<tr><th>Type</th><td id="ad_type">-</td></tr>
												<tr><th>Contact</th><td id="ad_tel">-</td></tr>
												<tr><th>Adresse</th><td id="ad_adresse">-</td></tr>
												<tr><th>Status</th><td id="ad_status">-</td></tr>
												<tr><th colspan="2" class="table-light">Situation</th></tr>
												<tr><th>Patients assurés</th><td id="ad_patients">-</td></tr>
											</tbody>
										</table>
									</div>
								</div>
								<div class="modal-footer">
									<a class="btn btn-primary" id="btnFacturationFromDetails" href="facturationassurance.php" role="button"><i class="fa fa-file-text-o"></i>Facturation</a>
									<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
								</div>
							</div>
						</div>
					</div>

					<!-- Modal: Modifier assureur -->
					<div class="modal fade" id="editAssuranceModal" tabindex="-1" aria-hidden="true">
						<div class="modal-dialog modal-lg modal-dialog-centered">
							<div class="modal-content">
								<form method="post" action="listedesassurances.php">
									<div class="modal-header">
										<h5 class="modal-title">Modifier un assureur</h5>
										<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
									</div>
									<div class="modal-body">
										<input type="hidden" name="modifier_assurance" value="1">
										<input type="hidden" name="id_assurance" id="edit_assurance_id" value="">
										<div class="row g-3">
											<div class="col-md-6">
												<label class="form-label">Assurance</label>
												<input type="text" class="form-control" name="assurance" id="edit_assurance_nom" required>
											</div>
											<div class="col-md-6">
												<label class="form-label">N° Contrat</label>
												<input type="text" class="form-control" name="contrat" id="edit_assurance_contrat">
											</div>
											<div class="col-md-6">
												<label class="form-label">Type</label>
												<input type="text" class="form-control" name="type_assurance" id="edit_assurance_type">
											</div>
											<div class="col-md-6">
												<label class="form-label">Contact</label>
												<input type="text" class="form-control" name="telephone" id="edit_assurance_tel">
											</div>
											<div class="col-md-12">
												<label class="form-label">Adresse</label>
												<input type="text" class="form-control" name="adresse" id="edit_assurance_adresse">
											</div>
										</div>
									</div>
									<div class="modal-footer">
										<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
										<button type="submit" class="btn btn-primary">Enregistrer</button>
									</div>
								</form>
							</div>
						</div>
					</div>

					<script>
						(function () {
							const openBtn = document.getElementById('openAddAssuranceBtn');
							const modalEl = document.getElementById('addAssuranceModal');
							const modalBody = document.getElementById('addAssuranceModalBody');
							const detailsModalEl = document.getElementById('assuranceDetailsModal');
							const editModalEl = document.getElementById('editAssuranceModal');
							const detailsAlertEl = document.getElementById('assuranceDetailsAlert');
							const btnFacturationDetails = document.getElementById('btnFacturationFromDetails');
							const devise = <?php echo json_encode($devise, JSON_UNESCAPED_UNICODE); ?>;
							let pendingReload = false;

							function showModal(modalElToShow) {
								if (!modalElToShow) return;
								if (window.bootstrap && window.bootstrap.Modal) {
									const inst = window.bootstrap.Modal.getInstance(modalElToShow) || new window.bootstrap.Modal(modalElToShow);
									inst.show();
									return;
								}
								if (window.jQuery && typeof jQuery(modalElToShow).modal === 'function') {
									jQuery(modalElToShow).modal('show');
								}
							}

							function getModalInstance() {
								if (!window.bootstrap || !window.bootstrap.Modal) return null;
								return window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
							}

							function getDetailsModalInstance() {
								if (!window.bootstrap || !window.bootstrap.Modal) return null;
								return window.bootstrap.Modal.getInstance(detailsModalEl) || new window.bootstrap.Modal(detailsModalEl);
							}

							function setDetailsAlert(type, msg) {
								if (!detailsAlertEl) return;
								if (!msg) {
									detailsAlertEl.className = 'alert d-none';
									detailsAlertEl.textContent = '';
									return;
								}
								detailsAlertEl.className = 'alert alert-' + type;
								detailsAlertEl.textContent = msg;
								detailsAlertEl.classList.remove('d-none');
							}

							function setText(id, val) {
								const el = document.getElementById(id);
								if (!el) return;
								el.textContent = (val === null || val === undefined || val === '') ? '-' : String(val);
							}

							function formatMoneyMaybe(v) {
								if (v === null || v === undefined || v === '') return '-';
								const n = Number(v);
								if (!Number.isFinite(n)) return String(v);
								return n.toLocaleString('fr-FR');
							}

							function formatMoneyWithCurrency(v) {
								const base = formatMoneyMaybe(v);
								if (base === '-' || !devise) return base;
								return base + ' ' + devise;
							}

							function statusLabel(status) {
								const s = Number(status);
								if (s === 1) return 'Actif';
								if (s === 0) return 'Inactif';
								if (s === 3) return 'Archivé';
								return String(status ?? '-');
							}

							async function loadAssuranceDetails(id) {
								setDetailsAlert(null, '');
								setText('ad_id', '-');
								setText('ad_nom', '-');
								setText('ad_contrat', '-');
								setText('ad_type', '-');
								setText('ad_tel', '-');
								setText('ad_adresse', '-');
								setText('ad_status', '-');
								setText('ad_patients', '-');
								setText('ad_debit', '-');
								setText('ad_credit', '-');
								setText('ad_solde', '-');
								if (btnFacturationDetails) {
									btnFacturationDetails.classList.add('disabled');
									btnFacturationDetails.setAttribute('aria-disabled', 'true');
									btnFacturationDetails.setAttribute('tabindex', '-1');
									btnFacturationDetails.href = 'facturationassurance.php';
								}

								try {
									const res = await fetch('listedesassurances.php?ajax_details=1&id=' + encodeURIComponent(id), { headers: { 'Accept': 'application/json' } });
									const json = await res.json();
									if (!json || !json.success) {
										setDetailsAlert('warning', (json && json.message) ? json.message : 'Impossible de charger les détails.');
										return;
									}

									const a = json.assurance || {};
									const sit = json.situation || {};
									var assuranceId = (a && a.id !== undefined && a.id !== null) ? a.id : id;
									setText('ad_id', 'ASEC' + String(assuranceId));
									setText('ad_nom', a.nom);
									setText('ad_contrat', a.contrat);
									setText('ad_type', a.type);
									setText('ad_tel', a.telephone);
									setText('ad_adresse', a.adresse);
									setText('ad_status', statusLabel(a.status));
									setText('ad_patients', (sit.patients === null || sit.patients === undefined) ? '-' : sit.patients);
									setText('ad_debit', formatMoneyWithCurrency(sit.debit));
									setText('ad_credit', formatMoneyWithCurrency(sit.credit));
									setText('ad_solde', formatMoneyWithCurrency(sit.solde));

									if (btnFacturationDetails) {
										btnFacturationDetails.href = 'facturationassurance.php?assurance_id=' + encodeURIComponent(String(assuranceId));
										btnFacturationDetails.classList.remove('disabled');
										btnFacturationDetails.removeAttribute('aria-disabled');
										btnFacturationDetails.removeAttribute('tabindex');
									}
								} catch (e) {
									setDetailsAlert('danger', 'Erreur lors du chargement des détails.');
								}
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

							document.addEventListener('click', async function (e) {
								const btn = e.target && e.target.closest ? e.target.closest('.js-assurance-details') : null;
								if (!btn) return;
								const id = btn.getAttribute('data-id');
								await loadAssuranceDetails(id);
								const inst = getDetailsModalInstance();
								if (inst) inst.show();
							});

							document.addEventListener('click', function (e) {
								const btn = e.target && e.target.closest ? e.target.closest('.js-edit-assurance') : null;
								if (!btn) return;
								const id = btn.getAttribute('data-id') || '';
								const nom = btn.getAttribute('data-nom') || '';
								const contrat = btn.getAttribute('data-contrat') || '';
								const type = btn.getAttribute('data-type') || '';
								const tel = btn.getAttribute('data-telephone') || '';
								const adresse = btn.getAttribute('data-adresse') || '';
								const status = btn.getAttribute('data-status') || '1';

								const elId = document.getElementById('edit_assurance_id');
								const elNom = document.getElementById('edit_assurance_nom');
								const elContrat = document.getElementById('edit_assurance_contrat');
								const elType = document.getElementById('edit_assurance_type');
								const elTel = document.getElementById('edit_assurance_tel');
								const elAdresse = document.getElementById('edit_assurance_adresse');
								const elStatus = document.getElementById('edit_assurance_status');

								if (elId) elId.value = id;
								if (elNom) elNom.value = nom;
								if (elContrat) elContrat.value = contrat;
								if (elType) elType.value = type;
								if (elTel) elTel.value = tel;
								if (elAdresse) elAdresse.value = adresse;
								if (elStatus) elStatus.value = (String(status) === '0') ? '0' : '1';

								showModal(editModalEl);
							});

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
