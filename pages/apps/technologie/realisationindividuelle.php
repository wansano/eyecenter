<?php
include('../PUBLIC/connect.php');
include('../PUBLIC/fonction.php');
session_start();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function is_medical_service(int $serviceId): bool {
    // D'après la base: médecins = 1, 2, 3, 4
    return in_array($serviceId, [1, 2, 3, 4], true);
}

function is_cashier_service(int $serviceId): bool {
    // D'après la base: caisse = 8
    return $serviceId === 8;
}

function is_secretary_service(int $serviceId): bool {
    // D'après la base: secrétariat administratif = 7
    return $serviceId === 7;
}

function table_has_column(PDO $bdd, string $table, string $column): bool {
    try {
        $bdd->query('SELECT `' . str_replace('`', '', $column) . '` FROM `' . str_replace('`', '', $table) . '` LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function resolve_first_existing_column(PDO $bdd, string $table, array $candidates): ?string {
    foreach ($candidates as $col) {
        if (table_has_column($bdd, $table, $col)) {
            return $col;
        }
    }
    return null;
}

function get_user_row(PDO $bdd, int $userId): ?array {
    if ($userId <= 0) {
        return null;
    }
    $stmt = $bdd->prepare('SELECT id, pseudo, email, type, id_service, status, date_engagement FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function get_user_org_info(PDO $bdd, int $userId): array {
    $out = ['departement' => '', 'celulle' => ''];
    if ($userId <= 0) {
        return $out;
    }

    $userServiceCol = resolve_first_existing_column($bdd, 'users', ['id_service', 'id_organigramme', 'service']);
    if ($userServiceCol === null) {
        return $out;
    }

    $stmt = $bdd->prepare('SELECT ' . $userServiceCol . ' FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $serviceId = (int)($stmt->fetchColumn() ?: 0);
    if ($serviceId <= 0) {
        return $out;
    }

    $hasDepartement = table_has_column($bdd, 'organigramme', 'departement');
    $cellCol = resolve_first_existing_column($bdd, 'organigramme', ['celulle', 'cellule']);
    if ($cellCol === null) {
        return $out;
    }

    $cols = ($hasDepartement ? 'departement,' : '') . $cellCol;
    $stmt = $bdd->prepare('SELECT ' . $cols . ' FROM organigramme WHERE id_organigramme = ? LIMIT 1');
    $stmt->execute([$serviceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return $out;
    }

    if ($hasDepartement) {
        $out['departement'] = trim((string)($row['departement'] ?? ''));
    }
    $out['celulle'] = trim((string)($row[$cellCol] ?? ''));

    return $out;
}

function get_medical_realisations(PDO $bdd, int $userId, string $dateDebut, string $dateFin): array {
    $tables = ['consultations', 'controles', 'examens', 'chirurgies', 'soins', 'mesures'];
    $queryParts = [];
    $params = [];

    foreach ($tables as $index => $table) {
        $userParam = ":userId{$index}";
        $dateDebParam = ":dateDeb{$index}";
        $dateFinParam = ":dateFin{$index}";

        $queryParts[] = "
            SELECT id_type, COUNT(*) AS count
            FROM {$table}
            WHERE traitant = {$userParam}
              AND DATE(date_traitement) BETWEEN {$dateDebParam} AND {$dateFinParam}
            GROUP BY id_type
        ";

        $params[$userParam] = $userId;
        $params[$dateDebParam] = $dateDebut;
        $params[$dateFinParam] = $dateFin;
    }

    $unionSql = implode(' UNION ALL ', $queryParts);

    $finalSql = "
        SELECT t.id_type, t.nom_type, SUM(sub.count) AS total
        FROM ({$unionSql}) AS sub
        JOIN traitements t ON t.id_type = sub.id_type
        GROUP BY t.id_type, t.nom_type
        ORDER BY t.id_type
    ";

    $stmt = $bdd->prepare($finalSql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_cashier_stats(PDO $bdd, int $userId, string $dateDebut, string $dateFin): array {
    $out = [
        'nb_paiements' => 0,
        'montant_paiements' => 0.0,
        'montant_global_paiements' => 0.0,
        'nb_preuves' => 0,
        'montant_preuves' => 0.0,
    ];

    // Paiements validés (remboursement=0)
    $stmt = $bdd->prepare('SELECT COUNT(*), COALESCE(SUM(montant_paye), 0) FROM paiements WHERE caisse = ? AND remboursement = 0 AND DATE(datepaiement) BETWEEN ? AND ?');
    $stmt->execute([$userId, $dateDebut, $dateFin]);
    $row = $stmt->fetch(PDO::FETCH_NUM);
    if ($row) {
        $out['nb_paiements'] = (int)($row[0] ?? 0);
        $out['montant_paiements'] = (float)($row[1] ?? 0);
    }

    // Montant global (toutes caisses)
    $stmt = $bdd->prepare('SELECT COALESCE(SUM(montant_paye), 0) FROM paiements WHERE remboursement = 0 AND DATE(datepaiement) BETWEEN ? AND ?');
    $stmt->execute([$dateDebut, $dateFin]);
    $out['montant_global_paiements'] = (float)($stmt->fetchColumn() ?: 0);

    // Preuves de caisse
    $stmt = $bdd->prepare('SELECT COUNT(*), COALESCE(SUM(montant), 0) FROM preuvedecaisse WHERE id_user = ? AND DATE(date_rapportement) BETWEEN ? AND ?');
    $stmt->execute([$userId, $dateDebut, $dateFin]);
    $row = $stmt->fetch(PDO::FETCH_NUM);
    if ($row) {
        $out['nb_preuves'] = (int)($row[0] ?? 0);
        $out['montant_preuves'] = (float)($row[1] ?? 0);
    }

    return $out;
}

function get_secretary_stats(PDO $bdd, int $userId, string $dateDebut, string $dateFin): array {
    $out = [
                'available' => true,
                'reason' => "",
                'nb_affectations' => 0,
                'nb_patients_affectes' => 0,
                'nb_affectations_ayant_paye' => 0,
    ];

        // 1) Affectations réalisées par ce secrétaire sur la période (affectations.affecte_par)
        $stmt = $bdd->prepare('SELECT COUNT(*) FROM affectations WHERE affecte_par = ? AND DATE(date) BETWEEN ? AND ?');
        $stmt->execute([$userId, $dateDebut, $dateFin]);
        $out['nb_affectations'] = (int)($stmt->fetchColumn() ?: 0);

        // 2) Patients affectés (distinct)
        $stmt = $bdd->prepare('SELECT COUNT(DISTINCT id_patient) FROM affectations WHERE affecte_par = ? AND DATE(date) BETWEEN ? AND ?');
        $stmt->execute([$userId, $dateDebut, $dateFin]);
        $out['nb_patients_affectes'] = (int)($stmt->fetchColumn() ?: 0);

        // 3) Affectations de ce secrétaire ayant payé sur la période (paiements validés)
        $sql = '
                SELECT COUNT(DISTINCT a.id_affectation)
                FROM affectations a
                JOIN paiements p ON p.id_affectation = a.id_affectation
                WHERE a.affecte_par = ?
                    AND p.remboursement = 0
                    AND DATE(p.datepaiement) BETWEEN ? AND ?
        ';
        $stmt = $bdd->prepare($sql);
        $stmt->execute([$userId, $dateDebut, $dateFin]);
        $out['nb_affectations_ayant_paye'] = (int)($stmt->fetchColumn() ?: 0);

    return $out;
}

// Récupération des employés (users)
$users = [];
try {
    $stmt = $bdd->prepare('SELECT id, pseudo, email, type FROM users WHERE status = 1 ORDER BY pseudo');
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('realisationindividuelle.php users list: ' . $e->getMessage());
    $users = [];
}

$today = date('Y-m-d');
$selectedUserId = 0;
$selectedServiceId = 0;
$dateDebut = $today;
$dateFin = $today;

$result = null;
$formError = null;
$shouldOpenModal = false;

if (isset($_POST['afficher'])) {
    $selectedServiceId = (int)($_POST['service'] ?? 0);
    $selectedUserId = (int)($_POST['employe'] ?? 0);
    if ($selectedUserId <= 0) {
        $selectedUserId = (int)($_POST['medecin'] ?? 0); // compat si le champ s'appelle encore "medecin"
    }
    $dateDebut = trim((string)($_POST['datedebut'] ?? $today));
    $dateFin = trim((string)($_POST['datefin'] ?? $today));

    $isValidDateDeb = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut);
    $isValidDateFin = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin);

    if ($selectedUserId <= 0) {
        $formError = "Veuillez sélectionner un employé.";
    } elseif (!$isValidDateDeb || !$isValidDateFin) {
        $formError = "Veuillez renseigner des dates valides.";
    } elseif ($dateDebut > $today || $dateFin > $today) {
        $formError = "Les dates ne peuvent pas être supérieures à la date du jour.";
    } elseif ($dateDebut > $dateFin) {
        $formError = "La date de début doit être inférieure ou égale à la date de fin.";
    } else {
        try {
            $userRow = get_user_row($bdd, $selectedUserId);
            if (!$userRow) {
                $formError = "Employé introuvable.";
            } else {
                $serviceIdUser = (int)($userRow['id_service'] ?? 0);
                $orgInfo = get_user_org_info($bdd, $selectedUserId);
                $result = [
                    'user' => $userRow,
                    'org' => $orgInfo,
                    'date_debut' => $dateDebut,
                    'date_fin' => $dateFin,
                    'role' => null,
                    'medical' => null,
                    'cashier' => null,
                    'secretary' => null,
                ];

                if (is_medical_service($serviceIdUser)) {
                    $result['role'] = 'medical';
                    $result['medical'] = get_medical_realisations($bdd, $selectedUserId, $dateDebut, $dateFin);
                } elseif (is_cashier_service($serviceIdUser)) {
                    $result['role'] = 'cashier';
                    $result['cashier'] = get_cashier_stats($bdd, $selectedUserId, $dateDebut, $dateFin);
                } elseif (is_secretary_service($serviceIdUser)) {
                    $result['role'] = 'secretary';
                    $result['secretary'] = get_secretary_stats($bdd, $selectedUserId, $dateDebut, $dateFin);
                } else {
                    $result['role'] = 'other';
                }

                $shouldOpenModal = true;
            }
        } catch (Throwable $e) {
            error_log('realisationindividuelle.php compute: ' . $e->getMessage());
            $formError = "Erreur lors du calcul. Vérifier les logs.";
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
						<h2>Réalisation individuelle</h2>
					</header>

					<div class="col-md-12">
						<section class="card">
							<div class="card-body">

								<?php if ($formError): ?>
									<div class="alert alert-danger">
										<?= h($formError) ?>
									</div>
								<?php endif; ?>

								<form method="post" class="form-horizontal" novalidate>
									<div class="row">
                                        <div class="col-md-3 mb-3">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Département concerné</label>
                                                    <select name="service" class="form-control populate" id="serviceSelect" data-plugin-selectTwo data-plugin-options='{ "minimumInputLength": 0 }' onchange="updateMedecins();">
                                                        <option value=""> ------ choisir ----- </option>
                                                        <?php
                                                        $coll = $bdd->prepare('SELECT * FROM organigramme WHERE id_organigramme IN (?, ?, ?,?, ?, ?, ?, ?)');
                                                        $coll->execute([1, 2, 3, 4, 5, 8, 13, 14]); // exemple d'IDs de services principaux
                                                        while ($services = $coll->fetch(PDO::FETCH_ASSOC)) {
                                                            $optValue = (int)($services['id_organigramme'] ?? 0);
                                                            $optLabel = (string)($services['celulle'] ?? '');
                                                            $selected = ($optValue > 0 && $optValue === (int)$selectedServiceId) ? 'selected' : '';
                                                            echo '<option value="' . h((string)$optValue) . '" ' . $selected . '>' . h($optLabel) . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                            </div>
                                        </div>
										<div class="col-md-3">
                                            <div class="form-group">
                                                    <label class="col-form-label" for="medecinSelect">Choisir le personnel concerné</label>
                                                    <select class="form-control populate" id="medecinSelect" name="employe" data-plugin-selectTwo data-plugin-options='{ "minimumInputLength": 0 }' required>
                                                <option value=""> ------ Choisir un departement ----- </option>
                                                </select>
                                            </div>
                                        </div>
										<div class="col-md-2 mb-3">
											<label class="col-form-label">Date début</label>
                                            <input type="date" name="datedebut" class="form-control" value="<?= h($dateDebut) ?>" max="<?= h($today) ?>" required>
										</div>
										<div class="col-md-2 mb-3">
											<label class="col-form-label">Date fin</label>
                                            <input type="date" name="datefin" class="form-control" value="<?= h($dateFin) ?>" max="<?= h($today) ?>" required>
										</div>
										<div class="col-md-1 mb-3 d-flex align-items-end">
											<button type="submit" name="afficher" class="btn btn-primary">Afficher</button>
										</div>
									</div>
								</form>
							</div>
						</section>
					</div>

				</section>
			</div>

			<!-- Modal Résultat -->
			<div class="modal fade" id="resultModal" tabindex="-1" aria-hidden="true">
				<div class="modal-dialog modal-xl modal-dialog-scrollable">
					<div class="modal-content">
						<div class="modal-header">
							<h5 class="modal-title">Résultats</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body">
							<?php if (!$result): ?>
								<div class="alert alert-info">Aucun résultat à afficher.</div>
							<?php else:
								$u = $result['user'];
                                $org = $result['org'] ?? ['departement' => '', 'celulle' => ''];
								$role = (string)$result['role'];
							?>
								<div class="row">
									<div class="col-md-12">
										<section class="card">
											<div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-bordered mb-0">
                                                        <tbody>
                                                            <tr>
                                                                <th style="width: 30%;">Employé</th>
                                                                <td><?= h((string)($u['pseudo'] ?? '')) ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Email</th>
                                                                <td><?= h((string)($u['email'] ?? '')) ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Département</th>
                                                                <td><?= h((string)($org['departement'] !== '' ? $org['departement'] : '-')) ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Cellule</th>
                                                                <td><?= h((string)($org['celulle'] !== '' ? $org['celulle'] : '-')) ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Date d'engagement</th>
                                                                <td><?= h((string)($u['date_engagement'] ?? '')) ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Période</th>
                                                                <td>du <?= h($result['date_debut']) ?> au <?= h($result['date_fin']) ?></td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
											</div>
										</section>
									</div>
								</div>

								<?php if ($role === 'medical'): ?>
									<h5 class="mt-3">Réalisations (personnel médical)</h5>
									<div class="row mt-2">
										<?php
										$rows = $result['medical'] ?? [];
										if (empty($rows)):
										?>
											<div class="col-md-12"><div class="alert alert-info">Aucune réalisation trouvée sur la période.</div></div>
										<?php else:
											foreach ($rows as $r):
										?>
											<div class="col-md-3 mb-3">
												<section class="card">
													<div class="card-body text-center">
														<div class="h4 font-weight-bold text-primary mb-1"><?= h((string)($r['total'] ?? 0)) ?></div>
														<p class="text-xs text-muted mb-0"><?= h((string)($r['nom_type'] ?? '')) ?></p>
													</div>
												</section>
											</div>
										<?php endforeach; endif; ?>
									</div>

								<?php elseif ($role === 'cashier'): ?>
									<h5 class="mt-3">Performance (caisse)</h5>
									<?php $c = $result['cashier'] ?? []; ?>
									<div class="row mt-2">
										<div class="col-md-3 mb-3">
											<section class="card"><div class="card-body text-center">
												<div class="h4 text-primary mb-1"><?= h((string)($c['nb_paiements'] ?? 0)) ?></div>
												<p class="text-xs text-muted mb-0">Paiements validés</p>
											</div></section>
										</div>
										<div class="col-md-3 mb-3">
											<section class="card"><div class="card-body text-center">
												<div class="h4 text-primary mb-1"><?= h((string)($c['nb_preuves'] ?? 0)) ?></div>
												<p class="text-xs text-muted mb-0">Preuves de caisse</p>
											</div></section>
										</div>
										<div class="col-md-3 mb-3">
											<section class="card"><div class="card-body text-center">
												<div class="h4 text-primary mb-1"><?= h(number_format((float)($c['montant_paiements'] ?? 0), 0, ',', ' ')) ?></div>
												<p class="text-xs text-muted mb-0">Montant validé (employé)</p>
											</div></section>
										</div>
										<div class="col-md-3 mb-3">
											<section class="card"><div class="card-body text-center">
                                                <div class="h4 text-primary mb-1"><?= h(number_format((float)($c['montant_preuves'] ?? 0), 0, ',', ' ')) ?></div>
                                                <p class="text-xs text-muted mb-0">Montant preuves de caisse</p>
											</div></section>
										</div>
									</div>

								<?php elseif ($role === 'secretary'): ?>
									<h5 class="mt-3">Performance (secrétariat)</h5>
                                    <?php $s = $result['secretary'] ?? ['available' => false, 'reason' => "Indisponible"]; ?>
                                    <?php if (empty($s['available'])): ?>
                                        <div class="alert alert-warning"><?= h((string)($s['reason'] ?? 'Indicateurs indisponibles.')) ?></div>
                                    <?php else: ?>
										<div class="row mt-2">
											<div class="col-md-4 mb-3">
												<section class="card"><div class="card-body text-center">
                                                    <div class="h4 text-primary mb-1"><?= h((string)($s['nb_affectations'] ?? 0)) ?></div>
                                                    <p class="text-xs text-muted mb-0">Affectations réalisées</p>
												</div></section>
											</div>
											<div class="col-md-4 mb-3">
												<section class="card"><div class="card-body text-center">
                                                    <div class="h4 text-primary mb-1"><?= h((string)($s['nb_patients_affectes'] ?? 0)) ?></div>
                                                    <p class="text-xs text-muted mb-0">Patients affectés</p>
												</div></section>
											</div>
											<div class="col-md-4 mb-3">
												<section class="card"><div class="card-body text-center">
                                                    <div class="h4 text-primary mb-1"><?= h((string)($s['nb_affectations_ayant_paye'] ?? 0)) ?></div>
                                                    <p class="text-xs text-muted mb-0">Affectations ayant payé</p>
												</div></section>
											</div>
										</div>
									<?php endif; ?>

								<?php else: ?>
									<div class="alert alert-info mt-3">Aucun indicateur défini pour ce profil.</div>
								<?php endif; ?>

							<?php endif; ?>
						</div>
						<div class="modal-footer">
                            <?php if ($result): ?>
                                <?php $canPrint = (($result['role'] ?? '') !== 'other'); ?>
                                <button type="button" class="btn btn-primary" id="btnPrintResult" <?php echo $canPrint ? '' : 'disabled'; ?>>
                                    Imprimer
                                </button>
                            <?php endif; ?>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
						</div>
					</div>
				</div>
			</div>

            <!-- Modal Impression -->
            <div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Impression en pdf</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" style="padding:0;">
                            <iframe id="printFrame" src="about:blank" style="width:100%; height:80vh; border:0;"></iframe>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                        </div>
                    </div>
                </div>
            </div>

			<?php if ($shouldOpenModal): ?>
				<script>
					document.addEventListener('DOMContentLoaded', function () {
						var el = document.getElementById('resultModal');
						if (!el || typeof bootstrap === 'undefined') return;
						var m = new bootstrap.Modal(el);
						m.show();
					});
				</script>
			<?php endif; ?>

            <script>
                function resetSelect(selectEl, placeholder) {
                    if (!selectEl) return;
                    selectEl.innerHTML = '';
                    var opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = placeholder || '---';
                    selectEl.appendChild(opt);
                    if (window.jQuery && jQuery(selectEl).data('select2')) {
                        jQuery(selectEl).val('').trigger('change');
                    }
                }

                function updateMedecins() {
                    var serviceEl = document.getElementById('serviceSelect');
                    var medecinSelect = document.getElementById('medecinSelect');
                    var serviceId = serviceEl ? String(serviceEl.value || '') : '';
                    if (!medecinSelect) return;

                    if (!serviceId) {
                        resetSelect(medecinSelect, '------ Choisir un departement -----');
                        return;
                    }

                    resetSelect(medecinSelect, 'Chargement...');

                    fetch(`../public/getPersonnel.php?service=${encodeURIComponent(serviceId)}`)
                        .then(function (resp) {
                            if (!resp.ok) throw new Error('HTTP ' + resp.status);
                            return resp.json();
                        })
                        .then(function (data) {
                            var list = (data && Array.isArray(data.medecins)) ? data.medecins : [];
                            resetSelect(medecinSelect, list.length ? '------ Choisir le personnel -----' : 'Aucun personnel pour ce service');
                            for (var i = 0; i < list.length; i++) {
                                var m = list[i] || {};
                                var opt = document.createElement('option');
                                opt.value = m.id;
                                opt.textContent = m.pseudo;
                                medecinSelect.appendChild(opt);
                            }

                            var selectedUserId = <?php echo json_encode((int)$selectedUserId); ?>;
                            if (selectedUserId) {
                                medecinSelect.value = String(selectedUserId);
                            }

                            if (window.jQuery && jQuery(medecinSelect).data('select2')) {
                                jQuery(medecinSelect).trigger('change');
                            }
                        })
                        .catch(function (err) {
                            console.error('Erreur chargement personnel:', err);
                            resetSelect(medecinSelect, 'Erreur de chargement');
                        });
                }

                document.addEventListener('DOMContentLoaded', function () {
                    var serviceEl = document.getElementById('serviceSelect');
                    if (serviceEl && serviceEl.value) {
                        updateMedecins();
                    }
                });
            </script>

            <?php if ($result): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        var btn = document.getElementById('btnPrintResult');
                        var frame = document.getElementById('printFrame');
                        var printModalEl = document.getElementById('printModal');
                        if (!btn || !frame || !printModalEl || typeof bootstrap === 'undefined') return;

                        var canPrint = <?php echo json_encode((($result['role'] ?? '') !== 'other')); ?>;
                        if (!canPrint) {
                            btn.disabled = true;
                            return;
                        }

                        btn.addEventListener('click', function () {
                            if (btn.disabled) return;
                            var userId = <?php echo json_encode((int)($result['user']['id'] ?? 0)); ?>;
                            var debut = <?php echo json_encode((string)($result['date_debut'] ?? '')); ?>;
                            var fin = <?php echo json_encode((string)($result['date_fin'] ?? '')); ?>;

                            var url = 'imprimer_realisationindividuelle.php'
                                + '?employe=' + encodeURIComponent(userId)
                                + '&debut=' + encodeURIComponent(debut)
                                + '&fin=' + encodeURIComponent(fin);

                            frame.src = url;
                            var pm = new bootstrap.Modal(printModalEl);
                            pm.show();
                        });
                    });
                </script>
            <?php endif; ?>

			<?php include('../PUBLIC/footer.php'); ?>
		</section>
	</body>
</html>
