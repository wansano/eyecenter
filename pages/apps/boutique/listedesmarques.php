<?php
include('../PUBLIC/connect.php');
session_start();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$errors = 0;
$ok = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;
$openAddModal = false;
$openEditModal = false;

// Ajouter une marque
if (isset($_POST['ajouter_marque'])) {
    $openAddModal = true;
    $marque = isset($_POST['marque']) ? trim((string)$_POST['marque']) : '';
    $description = isset($_POST['description']) ? trim((string)$_POST['description']) : '';

    if ($marque === '') {
        $errors = 10;
    } else {
        try {
            $st = $bdd->prepare('INSERT INTO marques (marque, description, status, quantite, date_miseajour) VALUES (?, ?, 1, 0, NOW())');
            $st->execute([$marque, ($description === '' ? null : $description)]);
            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=1');
            exit;
        } catch (Exception $e) {
            error_log('listedesmarques add error: ' . $e->getMessage());
            $errors = 11;
        }
    }
}

// Modifier une marque
if (isset($_POST['modifier_marque'])) {
    $openEditModal = true;
    $idMarque = isset($_POST['id_marque']) ? (int)$_POST['id_marque'] : 0;
    $marque = isset($_POST['marque']) ? trim((string)$_POST['marque']) : '';
    $description = isset($_POST['description']) ? trim((string)$_POST['description']) : '';
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    if ($status !== 0 && $status !== 1) {
        $status = 1;
    }

    if ($idMarque <= 0 || $marque === '') {
        $errors = 20;
    } else {
        try {
            $st = $bdd->prepare('UPDATE marques SET marque = ?, description = ?, status = ?, date_miseajour = NOW() WHERE id_marque = ? AND status <> 3');
            $st->execute([$marque, ($description === '' ? null : $description), $status, $idMarque]);
            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=2');
            exit;
        } catch (Exception $e) {
            error_log('listedesmarques edit error: ' . $e->getMessage());
            $errors = 21;
        }
    }
}

// Activer / Désactiver / Supprimer
if (isset($_POST['activer'])) {
    try {
        $id = (int)$_POST['activer'];
        $reponse = $bdd->prepare('UPDATE marques SET status = 1, date_miseajour = NOW() WHERE id_marque = ?');
        $reponse->execute([$id]);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=3');
        exit;
    } catch (Exception $e) {
        error_log('listedesmarques activer error: ' . $e->getMessage());
        $errors = 30;
    }
}

if (isset($_POST['desactiver'])) {
    try {
        $id = (int)$_POST['desactiver'];
        $reponse = $bdd->prepare('UPDATE marques SET status = 0, date_miseajour = NOW() WHERE id_marque = ?');
        $reponse->execute([$id]);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=4');
        exit;
    } catch (Exception $e) {
        error_log('listedesmarques desactiver error: ' . $e->getMessage());
        $errors = 31;
    }
}

if (isset($_POST['supprimer'])) {
    try {
        $id = (int)$_POST['supprimer'];
        $reponse = $bdd->prepare('UPDATE marques SET status = 3, date_miseajour = NOW() WHERE id_marque = ?');
        $reponse->execute([$id]);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=5');
        exit;
    } catch (Exception $e) {
        error_log('listedesmarques supprimer error: ' . $e->getMessage());
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
						<h2>Liste des marques </h2>
					</header>

					<!-- start: page -->
					<div class="col-md-12">
						<div class="row">
							<div class="col">
								<section class="card">
									<div class="card-body">
                                        <?php 
                                                if ($ok === 1) {
                                                    echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Marque ajoutée avec succès.</li></div>';
                                                }
                                                if ($ok === 2) {
                                                    echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Marque modifiée avec succès.</li></div>';
                                                }
                                                if ($ok === 3) {
                                                    echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Marque activée avec succès.</li></div>';
                                                }
                                                if ($ok === 4) {
                                                    echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Marque désactivée avec succès.</li></div>';
                                                }
                                                if ($ok === 5) {
                                                    echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Marque supprimée avec succès.</li></div>';
                                                }

                                                if ($errors == 10) {
                                                    echo '<div class="alert alert-warning"><li>Veuillez saisir le nom de la marque.</li></div>';
                                                }
                                                if ($errors == 11) {
                                                    echo '<div class="alert alert-danger"><li>Erreur technique lors de l\'ajout de la marque.</li></div>';
                                                }
                                                if ($errors == 20) {
                                                    echo '<div class="alert alert-warning"><li>Veuillez renseigner la marque à modifier (ID + nom).</li></div>';
                                                }
                                                if ($errors == 21) {
                                                    echo '<div class="alert alert-danger"><li>Erreur technique lors de la modification de la marque.</li></div>';
                                                }
                                                if (in_array($errors, [30,31,32], true)) {
                                                    echo '<div class="alert alert-danger"><li>Erreur technique lors de l\'action. Réessayez.</li></div>';
                                                }
                                        ?>
    									<div class="mb-3">
    										<button class="btn btn-success btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addMarqueModal">Ajouter une marque</button>
    									</div>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
                                                    <th>ID MARQUE</th>
													<th>MARQUE</th>
                                                    <th>QTE DISPO</th>
													<th>DESCRIPTION</th>
													<th>DATE MODIFICATION</th>
													<th>ACTION</th>
												</tr>
											</thead>
											<tbody>
											<?php
                                                    $reponse1 = $bdd->prepare('SELECT * FROM marques WHERE status <> 3 ORDER BY id_marque');
                                                    $reponse1->execute();
                                                    while ($donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC)) {
                                                        $status = (int)($donnees1['status'] ?? 0);
                                                        $id = (int)($donnees1['id_marque'] ?? 0);
                                                        $nom = (string)($donnees1['marque'] ?? '');
                                                        $qte = (string)($donnees1['quantite'] ?? '0');
                                                        $desc = (string)($donnees1['description'] ?? '');
                                                        $dateMaj = (string)($donnees1['date_miseajour'] ?? '');

                                                        echo '<tr>';
                                                        echo '<td>' . h('ECML' . $id) . '</td>';
                                                        echo '<td>' . h($nom) . '</td>';
                                                        echo '<td>' . h($qte) . '</td>';
                                                        echo '<td>' . h($desc) . '</td>';
                                                        echo '<td>' . h($dateMaj) . '</td>';
                                                        echo '<td>';

                                                        if ($status === 0) {
                                                            echo '<form action="' . h($_SERVER['PHP_SELF']) . '" method="post" style="display:inline-block">';
                                                            echo '<input type="hidden" name="activer" value="' . h($id) . '">';
                                                            echo '<button type="submit" class="btn btn-sm btn-warning"><i class="fa fa-unlock-alt"></i> activer</button>';
                                                            echo '</form> ';

                                                            echo '<button type="button" class="btn btn-sm btn-info js-edit-marque" '
                                                            . 'data-bs-toggle="modal" data-bs-target="#editMarqueModal" '
                                                            . 'data-id="' . h($id) . '" '
                                                            . 'data-marque="' . h($nom) . '" '
                                                            . 'data-description="' . h($desc) . '" '
                                                            . 'data-status="' . h($status) . '" '
                                                            . '><i class="fa fa-edit"></i> modifier</button> ';

                                                            echo '<form action="' . h($_SERVER['PHP_SELF']) . '" method="post" style="display:inline-block" onsubmit="return confirm(\'Supprimer cette marque ?\');">';
                                                            echo '<input type="hidden" name="supprimer" value="' . h($id) . '">';
                                                            echo '<button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i> supprimer</button>';
                                                            echo '</form>';
                                                        }

                                                        if ($status === 1) {
                                                            echo '<form action="' . h($_SERVER['PHP_SELF']) . '" method="post" style="display:inline-block">';
                                                            echo '<input type="hidden" name="desactiver" value="' . h($id) . '">';
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
				    </section>
			    </div>

    		<!-- Modal ajout marque -->
    		<div class="modal fade" id="addMarqueModal" tabindex="-1" aria-hidden="true">
    			<div class="modal-dialog modal-lg">
    				<div class="modal-content">
    					<form method="POST" action="<?php echo h($_SERVER['PHP_SELF']); ?>" novalidate="novalidate">
    						<div class="modal-header">
    							<h5 class="modal-title">Ajouter une marque</h5>
    							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    						</div>
    						<div class="modal-body">
    							<div class="row form-group pb-3">
    								<div class="col-md-6">
    									<label class="col-form-label">Marque</label>
    									<input type="text" name="marque" class="form-control" required value="<?php echo isset($_POST['marque']) ? h($_POST['marque']) : ''; ?>">
    								</div>
    								<div class="col-md-6">
    									<label class="col-form-label">Description</label>
    									<input type="text" name="description" class="form-control" value="<?php echo isset($_POST['description']) ? h($_POST['description']) : ''; ?>">
    								</div>
    							</div>
    						</div>
    						<div class="modal-footer">
    							<button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
    							<button class="btn btn-primary" type="submit" name="ajouter_marque" value="1">Ajouter</button>
    						</div>
    					</form>
    				</div>
    			</div>
    		</div>

    		<!-- Modal modification marque -->
    		<div class="modal fade" id="editMarqueModal" tabindex="-1" aria-hidden="true">
    			<div class="modal-dialog modal-lg">
    				<div class="modal-content">
    					<form method="POST" action="<?php echo h($_SERVER['PHP_SELF']); ?>" novalidate="novalidate">
    						<div class="modal-header">
    							<h5 class="modal-title">Modifier une marque</h5>
    							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    						</div>
    						<div class="modal-body">
    							<input type="hidden" name="id_marque" id="edit_id_marque" value="">
    							<div class="row form-group pb-3">
    								<div class="col-md-6">
    									<label class="col-form-label">Marque</label>
    									<input type="text" name="marque" id="edit_marque" class="form-control" required value="">
    								</div>
    								<div class="col-md-6">
    									<label class="col-form-label">Statut</label>
    									<select name="status" id="edit_status" class="form-control">
    										<option value="1">Actif</option>
    										<option value="0">Inactif</option>
    									</select>
    								</div>
    							</div>
    							<div class="row form-group pb-3">
    								<div class="col-md-12">
    									<label class="col-form-label">Description</label>
    									<textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
    								</div>
    							</div>
    						</div>
    						<div class="modal-footer">
    							<button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
    							<button class="btn btn-primary" type="submit" name="modifier_marque" value="1">Enregistrer</button>
    						</div>
    					</form>
    				</div>
    			</div>
    		</div>

            <script>
                (function () {
                    document.addEventListener('click', function (e) {
                        var btn = e.target.closest('.js-edit-marque');
                        if (!btn) return;
                        var id = btn.getAttribute('data-id') || '';
                        var marque = btn.getAttribute('data-marque') || '';
                        var desc = btn.getAttribute('data-description') || '';
                        var status = btn.getAttribute('data-status') || '1';

                        var elId = document.getElementById('edit_id_marque');
                        var elMarque = document.getElementById('edit_marque');
                        var elDesc = document.getElementById('edit_description');
                        var elStatus = document.getElementById('edit_status');
                        if (elId) elId.value = id;
                        if (elMarque) elMarque.value = marque;
                        if (elDesc) elDesc.value = desc;
                        if (elStatus) elStatus.value = status;
                    });

                    var shouldOpenAdd = <?php echo $openAddModal ? 'true' : 'false'; ?>;
                    if (shouldOpenAdd) {
                        document.addEventListener('DOMContentLoaded', function () {
                            try {
                                var el = document.getElementById('addMarqueModal');
                                if (!el || typeof bootstrap === 'undefined') return;
                                new bootstrap.Modal(el).show();
                            } catch (e) {}
                        });
                    }

                    var shouldOpenEdit = <?php echo $openEditModal ? 'true' : 'false'; ?>;
                    if (shouldOpenEdit) {
                        document.addEventListener('DOMContentLoaded', function () {
                            try {
                                var el = document.getElementById('editMarqueModal');
                                if (!el || typeof bootstrap === 'undefined') return;
                                new bootstrap.Modal(el).show();
                            } catch (e) {}
                        });
                    }
                })();
            </script>
        <?php include '../PUBLIC/footer.php';?>
