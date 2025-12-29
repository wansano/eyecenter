<?php
include('../PUBLIC/connect.php');
session_start();

$debug = isset($_GET['debug']) && (string)$_GET['debug'] === '1';
$debugSqlError = '';

// Feedback après POST (PRG)
$errors = isset($_GET['ok']) ? (int) $_GET['ok'] : 0;

// Mode édition (sur la même page) : autorisé uniquement si status = 0
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editRow = null;

try {
	// Détection de colonnes (schémas différents selon DB)
	$hasDepartement = false;
	$hasStatus = false;
	$statusCol = null;
	try {
		$bdd->query('SELECT departement FROM organigramme LIMIT 1');
		$hasDepartement = true;
	} catch (PDOException $e) {
		$hasDepartement = false;
	}
	try {
		$bdd->query('SELECT status FROM organigramme LIMIT 1');
		$hasStatus = true;
		$statusCol = 'status';
	} catch (PDOException $e) {
		$hasStatus = false;
		$statusCol = null;
		// fallback : certaines DB utilisent "statuts"
		try {
			$bdd->query('SELECT statuts FROM organigramme LIMIT 1');
			$hasStatus = true;
			$statusCol = 'statuts';
		} catch (PDOException $e2) {
			$hasStatus = false;
			$statusCol = null;
		}
	}

	// Actions activer / désactiver / supprimer (archiver)
	if (isset($_POST['activer']) || isset($_POST['desactiver']) || isset($_POST['supprimer'])) {
		// Si la colonne status/statuts n'existe pas, on ne peut pas gérer les états.
		if (!$hasStatus || !$statusCol) {
			$errors = 1;
			$debugSqlError = 'La colonne organigramme.status/statuts est absente (activer/désactiver/archiver indisponible).';
		} else {
			$newStatus = null;
			$id = 0;
			$okCode = 0;

			if (isset($_POST['activer'])) {
				$id = (int) $_POST['activer'];
				$newStatus = 1;
				$okCode = 2;
			} elseif (isset($_POST['desactiver'])) {
				$id = (int) $_POST['desactiver'];
				$newStatus = 0;
				$okCode = 3;
			} elseif (isset($_POST['supprimer'])) {
				$id = (int) $_POST['supprimer'];
				$newStatus = 3;
				$okCode = 4;
			}

			if ($id > 0 && $newStatus !== null) {
				$stmt = $bdd->prepare('UPDATE organigramme SET ' . $statusCol . ' = ? WHERE id_organigramme = ?');
				$stmt->execute([$newStatus, $id]);
				header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=' . $okCode);
				exit;
			}
		}
	}

	// Ajout service (modal)
	if (isset($_POST['add_service'])) {
		$celulle = isset($_POST['celulle']) ? trim((string)$_POST['celulle']) : '';
		$departement = isset($_POST['departement']) ? trim((string)$_POST['departement']) : '';

		if ($celulle === '') {
			$errors = 1;
		} else {
			$cols = ['celulle'];
			$vals = [$celulle];
			$placeholders = ['?'];

			if ($hasDepartement) {
				$cols[] = 'departement';
				$vals[] = $departement;
				$placeholders[] = '?';
			}
			if ($hasStatus && $statusCol) {
				$cols[] = $statusCol;
				$vals[] = 0;
				$placeholders[] = '?';
			}

			$stmt = $bdd->prepare('INSERT INTO organigramme (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')');
			$stmt->execute($vals);
			header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=6');
			exit;
		}
	}

    // Enregistrement modification (uniquement si désactivé)
    if (isset($_POST['save_edit'])) {
        $id = isset($_POST['id_organigramme']) ? (int) $_POST['id_organigramme'] : 0;
        $celulle = isset($_POST['celulle']) ? trim((string)$_POST['celulle']) : '';
        $departement = isset($_POST['departement']) ? trim((string)$_POST['departement']) : '';

        if ($id > 0) {
			$st = 0;
			if ($hasStatus && $statusCol) {
				$stmt = $bdd->prepare('SELECT ' . $statusCol . ' FROM organigramme WHERE id_organigramme = ? LIMIT 1');
				$stmt->execute([$id]);
				$st = (int) $stmt->fetchColumn();
			}

			if ($st === 0) {
				if ($hasDepartement) {
					$upd = $bdd->prepare('UPDATE organigramme SET celulle = ?, departement = ? WHERE id_organigramme = ?');
					$upd->execute([$celulle, $departement, $id]);
				} else {
					$upd = $bdd->prepare('UPDATE organigramme SET celulle = ? WHERE id_organigramme = ?');
					$upd->execute([$celulle, $id]);
				}
				header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=5');
				exit;
			}
        }
        $errors = 1;
    }

    // Charger la ligne en édition si demandée
    if ($editId > 0) {
		$cols = ['id_organigramme', 'celulle'];
		if ($hasDepartement) $cols[] = 'departement';
		if ($hasStatus && $statusCol) $cols[] = $statusCol;
		$stmt = $bdd->prepare('SELECT ' . implode(', ', $cols) . ' FROM organigramme WHERE id_organigramme = ? LIMIT 1');
        $stmt->execute([$editId]);
        $editRow = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($editRow && $hasStatus && $statusCol && (int)($editRow[$statusCol] ?? 0) !== 0) {
			$editRow = null;
		}
    }
} catch (PDOException $e) {
    error_log('listedesservices.php error: ' . $e->getMessage());
    $errors = 1;
	$debugSqlError = $e->getMessage();
}

// Charger la liste (évite une page blanche si la table/colonnes sont absentes)
$rows = [];
try {
	$cols = ['id_organigramme', 'celulle'];
	if (!isset($hasDepartement)) {
		// au cas où la détection n'a pas tourné (rare)
		$hasDepartement = false;
		$hasStatus = false;
		$statusCol = null;
		try { $bdd->query('SELECT departement FROM organigramme LIMIT 1'); $hasDepartement = true; } catch (PDOException $e) {}
		try { $bdd->query('SELECT status FROM organigramme LIMIT 1'); $hasStatus = true; $statusCol = 'status'; } catch (PDOException $e) {}
		if (!$hasStatus) {
			try { $bdd->query('SELECT statuts FROM organigramme LIMIT 1'); $hasStatus = true; $statusCol = 'statuts'; } catch (PDOException $e) {}
		}
	}
	if ($hasDepartement) $cols[] = 'departement';
	if ($hasStatus && $statusCol) $cols[] = $statusCol;
	$stmt = $bdd->prepare('SELECT ' . implode(', ', $cols) . ' FROM organigramme ORDER BY id_organigramme');
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
	error_log('listedesservices.php list error: ' . $e->getMessage());
	$errors = 1;
	$debugSqlError = $e->getMessage();
	$rows = [];
}

include('../PUBLIC/header.php');
?>
	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Liste des services</h2>
					</header>

					<!-- start: page -->
					<div class="col-md-12">
						<div class="row">
							<div class="col">
								<section class="card">
									<div class="card-body">
											<button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addServiceModal"><i class="fa fa-plus"></i> ajouter un service</button> <br/> <br>
										<?php
											if ($errors==1) {
												echo '<div class="alert alert-danger"><li><strong>Erreur !</strong><br>Une erreur est survenue, veuillez vérifier les logs.';
												if ($debug && $debugSqlError !== '') {
													echo '<br><small>' . htmlspecialchars($debugSqlError, ENT_QUOTES, 'UTF-8') . '</small>';
												}
												echo '</li></div>';
											}
											if ($errors==2) {
												echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Service activé avec succès.</li></div>';
											}
											if ($errors==3) {
												echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Service désactivé avec succès.</li></div>';
											}
											if ($errors==4) {
												echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Service archivé avec succès.</li></div>';
											}
											if ($errors==5) {
												echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Service modifié avec succès.</li></div>';
											}
												if ($errors==6) {
													echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Service ajouté avec succès. Veuillez l\'activer.</li></div>';
												}
										?>

											<!-- Modal ajout service -->
											<div class="modal fade" id="addServiceModal" tabindex="-1" aria-hidden="true">
												<div class="modal-dialog">
													<div class="modal-content">
														<form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>">
															<div class="modal-header">
																<h5 class="modal-title">Ajouter un service</h5>
																<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
															</div>
															<div class="modal-body">
																<input type="hidden" name="add_service" value="1">
																<div class="mb-3">
																	<label class="form-label" for="add_celulle">Cellule</label>
																	<input type="text" class="form-control" name="celulle" id="add_celulle" required>
																</div>
																<?php if (!empty($hasDepartement)): ?>
																	<div class="mb-3">
																		<label class="form-label" for="add_departement">Département</label>
																		<input type="text" class="form-control" name="departement" id="add_departement" required>
																	</div>
																<?php endif; ?>
															</div>
															<div class="modal-footer">
																<button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
																<button type="submit" class="btn btn-primary">Enregistrer</button>
															</div>
														</form>
													</div>
												</div>
											</div>

										<?php if ($editRow): ?>
											<div class="alert alert-info">
												<strong>Modification du service #<?php echo htmlspecialchars((string)$editRow['id_organigramme'], ENT_QUOTES, 'UTF-8'); ?></strong>
												<form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" class="mt-2">
													<input type="hidden" name="save_edit" value="1">
													<input type="hidden" name="id_organigramme" value="<?php echo htmlspecialchars((string)$editRow['id_organigramme'], ENT_QUOTES, 'UTF-8'); ?>">
													<div class="row">
														<div class="col-md-4">
															<label class="col-form-label">Cellule</label>
															<input type="text" class="form-control" name="celulle" value="<?php echo htmlspecialchars((string)$editRow['celulle'], ENT_QUOTES, 'UTF-8'); ?>" required>
														</div>
																		<?php if (!empty($hasDepartement)): ?>
																		<div class="col-md-4">
																				<label class="col-form-label">Département</label>
																				<input type="text" class="form-control" name="departement" value="<?php echo htmlspecialchars((string)($editRow['departement'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
																			</div>
																		<?php endif; ?>
														<div class="col-md-4 d-flex align-items-end">
															<button type="submit" class="btn btn-primary me-2">Enregistrer</button>
															<a class="btn btn-secondary" href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>">Annuler</a>
														</div>
													</div>
												</form>
											</div>
										<?php endif; ?>

										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
													<th>ID</th>
													<th>CELULE</th>
															<?php if (!empty($hasDepartement)): ?><th>DEPARTEMENT</th><?php endif; ?>
															<th>STATUS</th>
												</tr>
											</thead>
											<tbody>
											<?php
												foreach ($rows as $row) {
															$status = (int) ($hasStatus && !empty($statusCol) ? ($row[$statusCol] ?? 0) : 0);
															if (!empty($hasStatus) && $status === 3) { continue; }
													$idOrg = (int) $row['id_organigramme'];
													echo '<tr>';
													echo '<td>' . htmlspecialchars((string)$idOrg, ENT_QUOTES, 'UTF-8') . '</td>';
													echo '<td>' . htmlspecialchars((string)($row['celulle'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
															if (!empty($hasDepartement)) {
																echo '<td>' . htmlspecialchars((string)($row['departement'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
															}
													echo '<td>';

															if (!empty($hasStatus) && $status === 0) {
															// Désactivé => activer + modifier + supprimer
															echo '<div class="d-flex gap-1">';
															echo '<form action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" method="post">';
															echo '<input type="hidden" name="activer" value="' . htmlspecialchars((string)$idOrg, ENT_QUOTES, 'UTF-8') . '">';
															echo '<button type="submit" class="btn btn-sm btn-warning"><i class="fa fa-unlock-alt"></i> activer</button>';
															echo '</form>';

															echo '<a class="btn btn-sm btn-info" href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '?edit=' . htmlspecialchars((string)$idOrg, ENT_QUOTES, 'UTF-8') . '"><i class="fa fa-edit"></i> modifier</a>';

															echo '<form action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" method="post">';
															echo '<input type="hidden" name="supprimer" value="' . htmlspecialchars((string)$idOrg, ENT_QUOTES, 'UTF-8') . '">';
															echo '<button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-times"></i> supprimer</button>';
															echo '</form>';
															echo '</div>';
														} elseif (!empty($hasStatus)) {
														// Activé => désactiver (supprimer uniquement si status=0)
														echo '<div class="d-flex gap-1">';
														echo '<form action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" method="post">';
														echo '<input type="hidden" name="desactiver" value="' . htmlspecialchars((string)$idOrg, ENT_QUOTES, 'UTF-8') . '">';
														echo '<button type="submit" class="btn btn-sm btn-success"><i class="fa fa-lock"></i> désactiver</button>';
														echo '</form>';
														echo '</div>';
															} else {
																echo '<span class="text-muted">Indisponible (colonne status/statuts absente)</span>';
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
			<?php include('../PUBLIC/footer.php');?>
