<?php
include('../PUBLIC/connect.php');
session_start();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function appec_lentilles_columns(PDO $bdd): array {
    static $cols = null;
    if (is_array($cols)) {
        return $cols;
    }

    $cols = [];
    try {
        $st = $bdd->query('SHOW COLUMNS FROM lentilles');
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            if (isset($row['Field'])) {
                $cols[(string)$row['Field']] = true;
            }
        }
    } catch (Exception $e) {
        // si la requête échoue, on laisse $cols vide et on retombe sur les requêtes historiques
        error_log('appec_lentilles_columns error: ' . $e->getMessage());
    }

    return $cols;
}

function appec_lentilles_has_col(array $cols, string $name): bool {
    return isset($cols[$name]);
}

$errors = 0;
$ok = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;
$openAddModal = false;
$openEditModal = false;

if (!isset($devise) || trim((string)$devise) === '') {
    $devise = 'GNF';
}

// Ajouter lentille
if (isset($_POST['ajouter_lentille'])) {
    $openAddModal = true;
    $lentille = isset($_POST['lentille']) ? trim((string)$_POST['lentille']) : '';
    $code = isset($_POST['code_lentille']) ? trim((string)$_POST['code_lentille']) : '';
    $quantite = 0;
    $prixAchat = isset($_POST['prix_achat']) ? (float)$_POST['prix_achat'] : 0;
    $pVente = isset($_POST['p_vente']) ? (float)$_POST['p_vente'] : 0;
    $description = isset($_POST['description']) ? trim((string)$_POST['description']) : '';

    if ($lentille === '' || $code === '') {
        $errors = 10;
    } else {
        $cols = appec_lentilles_columns($bdd);
        try {
            // éviter doublon de code
            $stDup = $bdd->prepare('SELECT 1 FROM lentilles WHERE code_lentille = ? LIMIT 1');
            $stDup->execute([$code]);
            if ($stDup->fetchColumn()) {
                $errors = 11;
            } else {
                if (empty($cols)) {
                    // fallback (sans prix_vente car calculé en base)
                    $st = $bdd->prepare('INSERT INTO lentilles (lentille, code_lentille, prix_achat, p_vente, description) VALUES (?,?,?,?,?)');
                    $st->execute([
                        $lentille,
                        $code,
                        $prixAchat,
                        $pVente,
                        ($description === '' ? null : $description),
                    ]);
                } else {
                    // insertion robuste (colonnes optionnelles selon schéma)
                    $insertCols = [];
                    $placeholders = [];
                    $params = [];

                    $insertCols[] = 'lentille';
                    $placeholders[] = '?';
                    $params[] = $lentille;

                    $insertCols[] = 'code_lentille';
                    $placeholders[] = '?';
                    $params[] = $code;

                    if (appec_lentilles_has_col($cols, 'prix_achat')) {
                        $insertCols[] = 'prix_achat';
                        $placeholders[] = '?';
                        $params[] = $prixAchat;
                    }
                    // ne jamais écrire prix_vente (calculé automatiquement en base)
                    if (appec_lentilles_has_col($cols, 'p_vente')) {
                        $insertCols[] = 'p_vente';
                        $placeholders[] = '?';
                        $params[] = $pVente;
                    }
                    if (appec_lentilles_has_col($cols, 'description')) {
                        $insertCols[] = 'description';
                        $placeholders[] = '?';
                        $params[] = ($description === '' ? null : $description);
                    }

                    $sql = 'INSERT INTO lentilles (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
                    $st = $bdd->prepare($sql);
                    $st->execute($params);
                }
                header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=1');
                exit;
            }
        } catch (PDOException $e) {
            // ex: contrainte UNIQUE sur code_lentille
            if ((string)$e->getCode() === '23000') {
                $errors = 11;
            } else {
                error_log('listedeslentilles add PDO error: ' . $e->getMessage());
                if (isset($e->errorInfo) && is_array($e->errorInfo)) {
                    error_log('listedeslentilles add PDO errorInfo: ' . json_encode($e->errorInfo));
                }
                $errors = 12;
            }
        } catch (Exception $e) {
            error_log('listedeslentilles add error: ' . $e->getMessage());
            $errors = 12;
        }
    }
}

// Modifier lentille
if (isset($_POST['modifier_lentille'])) {
    $openEditModal = true;
    $id = isset($_POST['id_lentille']) ? (int)$_POST['id_lentille'] : 0;
    $lentille = isset($_POST['lentille']) ? trim((string)$_POST['lentille']) : '';
    $code = isset($_POST['code_lentille']) ? trim((string)$_POST['code_lentille']) : '';
    $prixAchat = isset($_POST['prix_achat']) ? (float)$_POST['prix_achat'] : 0;
    $pVente = isset($_POST['p_vente']) ? (float)$_POST['p_vente'] : 0;

    if ($id <= 0 || $lentille === '' || $code === '') {
        $errors = 20;
    } else {
        $cols = appec_lentilles_columns($bdd);
        try {
            // éviter doublon de code
            $stDup = $bdd->prepare('SELECT 1 FROM lentilles WHERE code_lentille = ? AND id_lentille <> ? LIMIT 1');
            $stDup->execute([$code, $id]);
            if ($stDup->fetchColumn()) {
                $errors = 21;
            } else {
                if (empty($cols)) {
                    // fallback (sans prix_vente car calculé en base)
                    $st = $bdd->prepare('UPDATE lentilles SET lentille = ?, code_lentille = ?, prix_achat = ?, p_vente = ? WHERE id_lentille = ?');
                    $st->execute([
                        $lentille,
                        $code,
                        $prixAchat,
                        $pVente,
                        $id,
                    ]);
                } else {
                    // update robuste (colonnes optionnelles selon schéma)
                    $sets = [];
                    $params = [];

                    $sets[] = 'lentille = ?';
                    $params[] = $lentille;

                    $sets[] = 'code_lentille = ?';
                    $params[] = $code;

                    if (appec_lentilles_has_col($cols, 'prix_achat')) {
                        $sets[] = 'prix_achat = ?';
                        $params[] = $prixAchat;
                    }
                    // ne jamais écrire prix_vente (calculé automatiquement en base)
                    if (appec_lentilles_has_col($cols, 'p_vente')) {
                        $sets[] = 'p_vente = ?';
                        $params[] = $pVente;
                    }

                    if (count($sets) === 0) {
                        throw new Exception('Aucune colonne à mettre à jour (schéma lentilles non détecté).');
                    }

                    $sql = 'UPDATE lentilles SET ' . implode(', ', $sets) . ' WHERE id_lentille = ?';
                    $params[] = $id;

                    $st = $bdd->prepare($sql);
                    $st->execute($params);
                }
                header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=2');
                exit;
            }
        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                // ex: contrainte UNIQUE sur code_lentille
                $errors = 21;
            } else {
                error_log('listedeslentilles edit PDO error: ' . $e->getMessage());
                if (isset($e->errorInfo) && is_array($e->errorInfo)) {
                    error_log('listedeslentilles edit PDO errorInfo: ' . json_encode($e->errorInfo));
                }
                $errors = 22;
            }
        } catch (Exception $e) {
            error_log('listedeslentilles edit error: ' . $e->getMessage());
            $errors = 22;
        }
    }
}

if (isset($_POST['activer'])) {
    try {
        $id = (int)$_POST['activer'];
        $reponse = $bdd->prepare('UPDATE lentilles SET status = 1 WHERE id_lentille = ?');
        $reponse->execute([$id]);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=3');
        exit;
    } catch (Exception $e) {
        error_log('listedeslentilles activer error: ' . $e->getMessage());
        $errors = 30;
    }
}

if (isset($_POST['desactiver'])) {
    try {
        $id = (int)$_POST['desactiver'];
        $reponse = $bdd->prepare('UPDATE lentilles SET status = 0 WHERE id_lentille = ?');
        $reponse->execute([$id]);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=4');
        exit;
    } catch (Exception $e) {
        error_log('listedeslentilles desactiver error: ' . $e->getMessage());
        $errors = 31;
    }
}

if (isset($_POST['supprimer'])) {
    try {
        $id = (int)$_POST['supprimer'];
        $reponse = $bdd->prepare('UPDATE lentilles SET status = 3 WHERE id_lentille = ?');
        $reponse->execute([$id]);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=5');
        exit;
    } catch (Exception $e) {
        error_log('listedeslentilles supprimer error: ' . $e->getMessage());
        $errors = 32;
    }
}

?>

<?php include('../PUBLIC/header.php'); ?>

	<body>
		<section class="body">

			<?php require '../PUBLIC/navbarmenu.php'; ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Liste des catégories de lentilles</h2>
					</header>

					<!-- start: page -->
					<div class="col-md-12">
						<div class="row">
							<div class="col">
								<section class="card">
									<div class="card-body">
                                        <?php 
                                                if ($ok === 1) {
                                                    echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Lentille ajoutée avec succès.</li></div>';
                                                }
                                                if ($ok === 2) {
                                                    echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Lentille modifiée avec succès.</li></div>';
                                                }
                                                if ($ok === 3) {
                                                    echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Lentille activée avec succès.</li></div>';
                                                }
                                                if ($ok === 4) {
                                                    echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Lentille désactivée avec succès.</li></div>';
                                                }
                                                if ($ok === 5) {
                                                    echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Lentille supprimée avec succès.</li></div>';
                                                }

                                                if ($errors == 10) {
                                                    echo '<div class="alert alert-warning"><li>Veuillez renseigner Lentille et Code.</li></div>';
                                                }
                                                if ($errors == 11) {
                                                    echo '<div class="alert alert-warning"><li>Ce code lentille existe déjà.</li></div>';
                                                }
                                                if ($errors == 12) {
                                                    echo '<div class="alert alert-danger"><li>Erreur technique lors de l\'ajout de la lentille.</li></div>';
                                                }
                                                if ($errors == 20) {
                                                    echo '<div class="alert alert-warning"><li>Veuillez renseigner les champs obligatoires pour la modification.</li></div>';
                                                }
                                                if ($errors == 21) {
                                                    echo '<div class="alert alert-warning"><li>Ce code lentille est déjà utilisé par une autre lentille.</li></div>';
                                                }
                                                if ($errors == 22) {
                                                    echo '<div class="alert alert-danger"><li>Erreur technique lors de la modification de la lentille.</li></div>';
                                                }
                                                if (in_array($errors, [30,31,32], true)) {
                                                    echo '<div class="alert alert-danger"><li>Erreur technique lors de l\'action. Réessayez.</li></div>';
                                                }
                                        ?>
    										<div class="mb-3">
    											<button class="btn btn-success btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addLentilleModal">Ajouter une lentille</button>
    										</div>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
													<th>LENTILLE</th>
													<th>CODE</th>
                                                    <th>QTE</th>
													<th>PRIX ACHAT</th>
                                                    <th>P. VENTE (%)</th>
                                                    <th>PRIX VENTE</th>
                                                    <th>DESCRIPTION</th>
													<th>ACTION</th>
												</tr>
											</thead>
											<tbody>
											<?php
                                                    $reponse1 = $bdd->prepare('SELECT * FROM lentilles WHERE status <> 3 ORDER BY id_lentille');
                                                    $reponse1->execute();
                                                    while ($donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC)) {
                                                        $status = (int)($donnees1['status'] ?? 0);
                                                        $id = (int)($donnees1['id_lentille'] ?? 0);
                                                        $lent = (string)($donnees1['lentille'] ?? '');
                                                        $code = (string)($donnees1['code_lentille'] ?? '');
                                                        $qte = (string)($donnees1['quantite'] ?? '0');
                                                        $pa = (float)($donnees1['prix_achat'] ?? 0);
                                                        $pv = (float)($donnees1['prix_vente'] ?? 0);
                                                        $pvente = (string)($donnees1['p_vente'] ?? '0');
                                                        $desc = (string)($donnees1['description'] ?? '');

                                                        echo '<tr>';
                                                        echo '<td>' . h($lent) . '</td>';
                                                        echo '<td>' . h($code) . '</td>';
                                                        echo '<td>' . h($qte) . '</td>';
                                                        echo '<td>' . h(number_format($pa, 0, ',', ' ')) . ' ' . h($devise) . '</td>';
                                                        echo '<td>' . h($pvente) . '</td>';
                                                        echo '<td>' . h(number_format($pv, 0, ',', ' ')) . ' ' . h($devise) . '</td>';
                                                        echo '<td>' . h($desc) . '</td>';
                                                        echo '<td>';

                                                        if ($status === 0) {
                                                            echo '<form action="' . h($_SERVER['PHP_SELF']) . '" method="post" style="display:inline-block">'
                                                                . '<input type="hidden" name="activer" value="' . h($id) . '">' 
                                                                . '<button type="submit" class="btn btn-sm btn-warning"><i class="fa fa-unlock-alt"></i> activer</button>'
                                                                . '</form> ';

                                                            echo '<button type="button" class="btn btn-sm btn-info js-edit-lentille" '
                                                                . 'data-bs-toggle="modal" data-bs-target="#editLentilleModal" '
                                                                . 'data-id="' . h($id) . '" '
                                                                . 'data-lentille="' . h($lent) . '" '
                                                                . 'data-code="' . h($code) . '" '
                                                                . 'data-prixachat="' . h($pa) . '" '
                                                                . 'data-pvente="' . h($pvente) . '" '
                                                                . '><i class="fa fa-edit"></i> modifier</button> ';

                                                            echo '<form action="' . h($_SERVER['PHP_SELF']) . '" method="post" style="display:inline-block" onsubmit="return confirm(\'Supprimer cette lentille ?\');">'
                                                                . '<input type="hidden" name="supprimer" value="' . h($id) . '">' 
                                                                . '<button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i> supprimer</button>'
                                                                . '</form>';
                                                        }

                                                        if ($status === 1) {
                                                            echo '<form action="' . h($_SERVER['PHP_SELF']) . '" method="post" style="display:inline-block">'
                                                                . '<input type="hidden" name="desactiver" value="' . h($id) . '">' 
                                                                . '<button type="submit" class="btn btn-sm btn-success"><i class="fa fa-lock"></i> désactiver</button>'
                                                                . '</form> ';
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
				    </section>
			    </div>

    		<!-- Modal ajout lentille -->
    		<div class="modal fade" id="addLentilleModal" tabindex="-1" aria-hidden="true">
    			<div class="modal-dialog modal-lg">
    				<div class="modal-content">
    					<form method="POST" action="<?php echo h($_SERVER['PHP_SELF']); ?>" novalidate="novalidate">
    						<div class="modal-header">
    							<h5 class="modal-title">Ajouter une lentille</h5>
    							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    						</div>
    						<div class="modal-body">
    							<div class="row form-group pb-3">
    								<div class="col-md-6">
    									<label class="col-form-label">Lentille</label>
    									<input type="text" name="lentille" class="form-control" required value="<?php echo isset($_POST['lentille']) ? h($_POST['lentille']) : ''; ?>">
    								</div>
    								<div class="col-md-6">
    									<label class="col-form-label">Code</label>
    									<input type="text" name="code_lentille" class="form-control" required value="<?php echo isset($_POST['code_lentille']) ? h($_POST['code_lentille']) : ''; ?>">
    								</div>
    							</div>
    							<div class="row form-group pb-3">
    								<div class="col-md-4">
    									<label class="col-form-label">Prix achat</label>
    									<input type="number" step="1" name="prix_achat" class="form-control" value="<?php echo isset($_POST['prix_achat']) ? h($_POST['prix_achat']) : '0'; ?>">
    								</div>
    								<div class="col-md-4">
                                        <label class="col-form-label">Pourcentage vente</label>
                                        <input type="number" step="0.00" name="p_vente" class="form-control" value="<?php echo isset($_POST['p_vente']) ? h($_POST['p_vente']) : '0'; ?>">
    								</div>
    									<div class="col-md-4"></div>
    							</div>
    							<div class="row form-group pb-3">
    								<div class="col-md-12">
    									<label class="col-form-label">Description</label>
    									<textarea name="description" class="form-control" rows="3"><?php echo isset($_POST['description']) ? h($_POST['description']) : ''; ?></textarea>
    								</div>
    							</div>
    						</div>
    						<div class="modal-footer">
    							<button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
    							<button class="btn btn-primary" type="submit" name="ajouter_lentille" value="1">Ajouter</button>
    						</div>
    					</form>
    				</div>
    			</div>
    		</div>

    		<!-- Modal modification lentille -->
    		<div class="modal fade" id="editLentilleModal" tabindex="-1" aria-hidden="true">
    			<div class="modal-dialog modal-lg">
    				<div class="modal-content">
    					<form method="POST" action="<?php echo h($_SERVER['PHP_SELF']); ?>" novalidate="novalidate">
    						<div class="modal-header">
    							<h5 class="modal-title">Modifier une lentille</h5>
    							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    						</div>
    						<div class="modal-body">
    							<input type="hidden" name="id_lentille" id="edit_id_lentille" value="">
    							<div class="row form-group pb-3">
    								<div class="col-md-4">
    									<label class="col-form-label">Lentille</label>
    									<input type="text" name="lentille" id="edit_lentille" class="form-control" required value="">
    								</div>
    								<div class="col-md-4">
    									<label class="col-form-label">Code</label>
    									<input type="text" name="code_lentille" id="edit_code_lentille" class="form-control" required value="">
    								</div>
                                    <div class="col-md-4">
                                        <label class="col-form-label">Pourcentage vente (p_vente)</label>
                                        <input type="number" step="0.01" name="p_vente" id="edit_p_vente" class="form-control" value="0">
                                    </div>
    							</div>
    							<div class="row form-group pb-3">
    								<div class="col-md-4">
    									<label class="col-form-label">Prix achat</label>
    									<input type="number" step="1" name="prix_achat" id="edit_prix_achat" class="form-control" value="0">
    								</div>
                                    <div class="col-md-8">
                                        <small class="text-muted">Le prix de vente est calculé automatiquement par la base à partir de prix d'achat et p_vente.</small>
                                    </div>
    							</div>
    						</div>
    						<div class="modal-footer">
    							<button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
    							<button class="btn btn-primary" type="submit" name="modifier_lentille" value="1">Enregistrer</button>
    						</div>
    					</form>
    				</div>
    			</div>
    		</div>

            <script>
                (function () {
                    document.addEventListener('click', function (e) {
                        var btn = e.target.closest('.js-edit-lentille');
                        if (!btn) return;

                        function setVal(id, v) {
                            var el = document.getElementById(id);
                            if (!el) return;
                            el.value = (v === undefined || v === null) ? '' : String(v);
                        }

                        setVal('edit_id_lentille', btn.getAttribute('data-id'));
                        setVal('edit_lentille', btn.getAttribute('data-lentille'));
                        setVal('edit_code_lentille', btn.getAttribute('data-code'));
                        setVal('edit_prix_achat', btn.getAttribute('data-prixachat'));
                        setVal('edit_p_vente', btn.getAttribute('data-pvente'));
                    });

                    var shouldOpenAdd = <?php echo $openAddModal ? 'true' : 'false'; ?>;
                    if (shouldOpenAdd) {
                        document.addEventListener('DOMContentLoaded', function () {
                            try {
                                var el = document.getElementById('addLentilleModal');
                                if (!el || typeof bootstrap === 'undefined') return;
                                new bootstrap.Modal(el).show();
                            } catch (e) {}
                        });
                    }

                    var shouldOpenEdit = <?php echo $openEditModal ? 'true' : 'false'; ?>;
                    if (shouldOpenEdit) {
                        document.addEventListener('DOMContentLoaded', function () {
                            try {
                                var el = document.getElementById('editLentilleModal');
                                if (!el || typeof bootstrap === 'undefined') return;
                                new bootstrap.Modal(el).show();
                            } catch (e) {}
                        });
                    }
                })();
            </script>
        <?php include '../PUBLIC/footer.php';?>
