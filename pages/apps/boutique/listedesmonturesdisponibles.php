<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$errors = 0;
$existe = 0;
$openAddModal = false;
$openEditModal = false;
$editData = null;
$searchCode = '';
$ok = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;

// Ajout monture via modal
if (isset($_POST['ajouter_monture'])) {
    $openAddModal = true;
    $codeMonture = isset($_POST['codeMonture']) ? trim((string)$_POST['codeMonture']) : '';
    $noLivraison = isset($_POST['nolivraison']) ? trim((string)$_POST['nolivraison']) : '';
    $idMarque = isset($_POST['marque']) ? (int)$_POST['marque'] : 0;
    $prixMonture = isset($_POST['prixmonture']) ? (float)$_POST['prixmonture'] : 0;
    $couleur = isset($_POST['couleur']) ? trim((string)$_POST['couleur']) : '';
    $typeMonture = isset($_POST['typeMonture']) ? trim((string)$_POST['typeMonture']) : '';

    if ($codeMonture === '' || $noLivraison === '' || $idMarque <= 0 || $couleur === '') {
        $errors = 8;
    } else {
        try {
            $req1 = $bdd->prepare('SELECT 1 FROM montures WHERE code_monture = ? LIMIT 1');
            $req1->execute([$codeMonture]);
            $existe = $req1->fetchColumn() ? 1 : 0;

            if ($existe == 0) {
                $reponse2 = $bdd->prepare('SELECT quantite FROM approvisionnements WHERE no_livraison = ? LIMIT 1');
                $reponse2->execute([$noLivraison]);
                $donnees2 = $reponse2->fetch(PDO::FETCH_ASSOC);

                if (!$donnees2) {
                    $errors = 6;
                } else {
                    $qteAppro = (int)($donnees2['quantite'] ?? 0);
                    if ($qteAppro < 1) {
                        $errors = 5;
                    } else {
                        $reponse1 = $bdd->prepare('SELECT quantite FROM marques WHERE id_marque = ? LIMIT 1');
                        $reponse1->execute([$idMarque]);
                        $quantiteModeleRaw = $reponse1->fetchColumn();
                        if ($quantiteModeleRaw === false) {
                            $errors = 7;
                        } else {
                            $quantiteModele = (int)$quantiteModeleRaw;

                            $bdd->beginTransaction();
                            $req = $bdd->prepare('INSERT INTO montures (code_monture, no_livraison, id_marque, couleur, prix, monture_pour) VALUES(?,?,?,?,?,?)');
                            $req->execute([$codeMonture, $noLivraison, $idMarque, $couleur, $prixMonture, ($typeMonture === '' ? null : $typeMonture)]);

                            $reqQ = $bdd->prepare('UPDATE marques SET quantite = ? WHERE id_marque = ?');
                            $reqQ->execute([$quantiteModele + 1, $idMarque]);

                            $reqA = $bdd->prepare('UPDATE approvisionnements SET quantite = quantite - 1 WHERE no_livraison = ? AND quantite > 0');
                            $reqA->execute([$noLivraison]);
                            if ($reqA->rowCount() < 1) {
                                throw new Exception('Quantité approvisionnement insuffisante');
                            }

                            $bdd->commit();
                            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=2');
                            exit;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            if ($bdd->inTransaction()) {
                $bdd->rollBack();
            }
            error_log('listedemonturesdisponibles add error: ' . $e->getMessage());
            $errors = 9;
        }
    }
}

// Modification monture via modal
if (isset($_POST['modifier_monture'])) {
    $openEditModal = true;
    $idMonture = isset($_POST['id_monture']) ? (int)$_POST['id_monture'] : 0;
    $codeMonture = isset($_POST['codeMonture']) ? trim((string)$_POST['codeMonture']) : '';
    $idMarque = isset($_POST['marque']) ? (int)$_POST['marque'] : 0;
    $prixMonture = isset($_POST['prixmonture']) ? (float)$_POST['prixmonture'] : 0;
    $couleur = isset($_POST['couleur']) ? trim((string)$_POST['couleur']) : '';
    $typeMonture = isset($_POST['typeMonture']) ? trim((string)$_POST['typeMonture']) : '';

    if ($idMonture <= 0 || $codeMonture === '' || $idMarque <= 0 || $couleur === '') {
        $errors = 18;
    } else {
        try {
            $bdd->beginTransaction();

            $stCur = $bdd->prepare('SELECT id_marque, vendu, retour FROM montures WHERE id_monture = ? LIMIT 1');
            $stCur->execute([$idMonture]);
            $cur = $stCur->fetch(PDO::FETCH_ASSOC);
            if (!$cur) {
                throw new Exception('Monture introuvable');
            }
            if ((int)($cur['vendu'] ?? 0) === 1) {
                throw new Exception('Monture déjà vendue');
            }
            if ((int)($cur['retour'] ?? 0) === 1) {
                throw new Exception('Monture déjà retournée');
            }

            $oldMarque = (int)($cur['id_marque'] ?? 0);

            // Empêcher doublon de code si changé
            $stDup = $bdd->prepare('SELECT 1 FROM montures WHERE code_monture = ? AND id_monture <> ? LIMIT 1');
            $stDup->execute([$codeMonture, $idMonture]);
            if ($stDup->fetchColumn()) {
                $bdd->rollBack();
                $errors = 11; // code déjà utilisé
            } else {
                $stU = $bdd->prepare('UPDATE montures SET code_monture = ?, id_marque = ?, couleur = ?, prix = ?, monture_pour = ? WHERE id_monture = ?');
                $stU->execute([$codeMonture, $idMarque, $couleur, $prixMonture, ($typeMonture === '' ? null : $typeMonture), $idMonture]);

                // Ajuster quantités si la marque change
                if ($oldMarque > 0 && $oldMarque !== $idMarque) {
                    $stDec = $bdd->prepare('UPDATE marques SET quantite = GREATEST(quantite - 1, 0) WHERE id_marque = ?');
                    $stDec->execute([$oldMarque]);

                    $stInc = $bdd->prepare('UPDATE marques SET quantite = quantite + 1 WHERE id_marque = ?');
                    $stInc->execute([$idMarque]);
                }

                $bdd->commit();
                header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=3');
                exit;
            }
        } catch (Exception $e) {
            if ($bdd->inTransaction()) {
                $bdd->rollBack();
            }
            error_log('listedemonturesdisponibles edit error: ' . $e->getMessage());
            if ($errors === 0) {
                $errors = 19;
            }
        }
    }
}

// Recherche monture par code (pour modification)
if (isset($_POST['chercher_monture'])) {
    $openEditModal = true;
    $searchCode = isset($_POST['searchCode']) ? trim((string)$_POST['searchCode']) : '';
    if ($searchCode === '') {
        $errors = 17;
    } else {
        try {
            $st = $bdd->prepare('SELECT * FROM montures WHERE code_monture = ? AND vendu = 0 AND retour = 0 LIMIT 1');
            $st->execute([$searchCode]);
            $editData = $st->fetch(PDO::FETCH_ASSOC);
            if (!$editData) {
                $errors = 16;
            }
        } catch (Exception $e) {
            error_log('listedemonturesdisponibles search error: ' . $e->getMessage());
            $errors = 15;
        }
    }
}

// Retour monture (marquer comme retournée)
if (isset($_POST['retourner_monture'])) {
    $idMonture = isset($_POST['id_monture']) ? (int)$_POST['id_monture'] : 0;
    if ($idMonture <= 0) {
        $errors = 28;
    } else {
        try {
            $bdd->beginTransaction();

            $stCur = $bdd->prepare('SELECT id_marque, no_livraison, vendu, retour FROM montures WHERE id_monture = ? LIMIT 1');
            $stCur->execute([$idMonture]);
            $cur = $stCur->fetch(PDO::FETCH_ASSOC);
            if (!$cur) {
                throw new Exception('Monture introuvable');
            }
            if ((int)($cur['vendu'] ?? 0) === 1) {
                throw new Exception('Monture déjà vendue');
            }
            if ((int)($cur['retour'] ?? 0) === 1) {
                throw new Exception('Monture déjà retournée');
            }

            $stR = $bdd->prepare('UPDATE montures SET retour = 1 WHERE id_monture = ? AND vendu = 0 AND retour = 0');
            $stR->execute([$idMonture]);
            if ($stR->rowCount() < 1) {
                throw new Exception('Retour impossible');
            }

            $idMarque = (int)($cur['id_marque'] ?? 0);
            $noLivraison = (string)($cur['no_livraison'] ?? '');

            if ($idMarque > 0) {
                $stDec = $bdd->prepare('UPDATE marques SET quantite = GREATEST(quantite - 1, 0) WHERE id_marque = ?');
                $stDec->execute([$idMarque]);
            }

            if ($noLivraison !== '') {
                $stIncA = $bdd->prepare('UPDATE approvisionnements SET quantite = quantite + 1 WHERE no_livraison = ?');
                $stIncA->execute([$noLivraison]);
            }

            $bdd->commit();
            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=4');
            exit;
        } catch (Exception $e) {
            if ($bdd->inTransaction()) {
                $bdd->rollBack();
            }
            error_log('listedemonturesdisponibles retour error: ' . $e->getMessage());
            $errors = 29;
        }
    }
}

include('../PUBLIC/header.php'); ?>

	<body>
		<section class="body">

			<?php require '../PUBLIC/navbarmenu.php'; ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Liste globals des montures en stock</h2>
					</header>

					<!-- start: page -->
					<div class="col-md-12">
						<div class="row">
							<div class="col">
								<section class="card">
                                    <header class="card-header">
                                        <h3 class="card-title" style="text-transform:inherit; font-weight:lighter; ">
                                            <button class="btn btn-info btn-sm" onclick="exportAllDataToExcel('datatable-default', 'liste_des_montures_en_stock')">Exporter au format excel</button>
                                            <button class="btn btn-success btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addMontureModal">Ajouter une monture</button>
                                            <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#editMontureModal">Modifier une monture</button>
                                        </h3>
                                    </header>
									<div class="card-body">
                                        <?php
                                        if ($ok === 2) {
                                            echo '<div class="alert alert-success"><li><strong>Succès</strong><br>La monture a été ajoutée avec succès.</li></div>';
                                        }
                                        if ($ok === 3) {
                                            echo '<div class="alert alert-success"><li><strong>Succès</strong><br>La monture a été modifiée avec succès.</li></div>';
                                        }
                                        if ($ok === 4) {
                                            echo '<div class="alert alert-success"><li><strong>Succès</strong><br>La monture a été retournée avec succès.</li></div>';
                                        }
                                        if ($errors == 5) {
                                            echo '<div class="alert alert-danger"><li>Ajout non effectué : quantité insuffisante.</li></div>';
                                        }
                                        if ($errors == 6) {
                                            echo '<div class="alert alert-warning"><li>Bon de livraison introuvable. Vérifiez le N° sélectionné.</li></div>';
                                        }
                                        if ($errors == 7) {
                                            echo '<div class="alert alert-warning"><li>Marque introuvable. Merci de sélectionner une marque valide.</li></div>';
                                        }
                                        if ($errors == 8) {
                                            echo '<div class="alert alert-warning"><li>Veuillez remplir tous les champs obligatoires (code, livraison, marque, couleur).</li></div>';
                                        }
                                        if ($errors == 9) {
                                            echo '<div class="alert alert-danger"><li>Erreur technique lors de l\'ajout. Réessayez, puis contactez l\'administrateur si le problème persiste.</li></div>';
                                        }
                                        if ($errors == 11) {
                                            echo '<div class="alert alert-warning"><li>Le code de cette monture est déjà utilisé.</li></div>';
                                        }
                                        if ($errors == 15) {
                                            echo '<div class="alert alert-danger"><li>Erreur technique lors de la recherche de la monture. Réessayez.</li></div>';
                                        }
                                        if ($errors == 16) {
                                            echo '<div class="alert alert-warning"><li>Monture introuvable.</li></div>';
                                        }
                                        if ($errors == 17) {
                                            echo '<div class="alert alert-warning"><li>Veuillez saisir le code de la monture.</li></div>';
                                        }
                                        if ($errors == 18) {
                                            echo '<div class="alert alert-warning"><li>Veuillez remplir tous les champs obligatoires pour la modification (code, marque, couleur).</li></div>';
                                        }
                                        if ($errors == 19) {
                                            echo '<div class="alert alert-danger"><li>Erreur technique lors de la modification. Réessayez, puis contactez l\'administrateur si besoin.</li></div>';
                                        }
                                        if ($errors == 28) {
                                            echo '<div class="alert alert-warning"><li>Retour non effectué : monture invalide.</li></div>';
                                        }
                                        if ($errors == 29) {
                                            echo '<div class="alert alert-danger"><li>Erreur technique lors du retour de la monture. Réessayez.</li></div>';
                                        }
                                        if ($existe == 1) {
                                            echo '<div class="alert alert-warning"><li>La monture existe déjà dans le système.</li></div>';
                                        }
                                        ?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
													<th>CODE MONTURE</th>
                                                    <th>MARQUE</th>
                                                    <th>COULEUR</th>
                                                    <th>MONTURE POUR </th>
                                                    <th>PRIX</th>
                                                    <th>DATE AJOUT</th>
                                                    <th>ACTIONS</th>
												</tr>
											</thead>
											<tbody>
											    <?php
                                                    $reponse = $bdd->prepare('SELECT m.*, ma.marque AS marque_nom FROM montures m LEFT JOIN marques ma ON ma.id_marque = m.id_marque WHERE m.retour = 0 AND m.vendu = 0 ORDER BY m.id_monture');
													$reponse->execute();
													while ($donnees1 = $reponse->fetch(PDO::FETCH_ASSOC)) {
                                                        $idM = (int)($donnees1['id_monture'] ?? 0);
														echo '<tr>';
                                                        echo '<td>' . strtoupper(h($donnees1['code_monture'] ?? '')) . '</td>';
                                                        echo '<td>' . h($donnees1['marque_nom'] ?? '') . '</td>';
                                                        echo '<td>' . h($donnees1['couleur'] ?? '') . '</td>';
                                                    echo '<td>' . h($donnees1['monture_pour'] ?? '') . '</td>';
                                                    echo '<td>' . h($donnees1['prix'] ?? '') . '</td>';
                                                    echo '<td>' . h(date('d/m/Y', strtotime((string)($donnees1['date_creation'] ?? 'now')))) . '</td>';
                                                        echo '<td>';
                                                        echo '<form method="POST" action="' . h($_SERVER['PHP_SELF']) . '" style="display:inline-block" onsubmit="return confirm(\'Confirmer le retour de cette monture ?\');">';
                                                        echo '<input type="hidden" name="retourner_monture" value="1">';
                                                        echo '<input type="hidden" name="id_monture" value="' . h($idM) . '">';
                                                        echo '<button class="btn btn-warning btn-sm" type="submit">Retourner</button>';
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
                    <br>

				</section>
			</div>

        <!-- Modal ajout monture -->
        <div class="modal fade" id="addMontureModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form class="form-horizontal" novalidate="novalidate" method="POST" action="<?php echo h($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
                        <div class="modal-header">
                            <h5 class="modal-title">Ajouter une monture</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="ajouter_monture" value="1">
                            <div class="row form-group pb-3">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="col-form-label">N° de la monture</label>
                                        <input type="text" pattern="^\S*$" oninput="this.value = this.value.replace(/\s/g, '')" name="codeMonture" class="form-control" required value="<?php echo isset($_POST['codeMonture']) ? h($_POST['codeMonture']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="col-form-label">Du bon de livraison N°</label>
                                        <select class="form-control populate" name="nolivraison" required>
                                            <option value="">---- choisir ----</option>
                                            <?php
                                            $type = $bdd->prepare('SELECT no_livraison FROM approvisionnements WHERE quantite > 0 AND statut = ? AND (type_commande = ? OR type_commande = ?)');
                                            $type->execute(['livré', 'montures', 'montures et lentilles']);
                                            $selectedLiv = isset($_POST['nolivraison']) ? trim((string)$_POST['nolivraison']) : '';
                                            while ($livraison = $type->fetch(PDO::FETCH_ASSOC)) {
                                                $no = (string)($livraison['no_livraison'] ?? '');
                                                if ($no === '') { continue; }
                                                $sel = ($selectedLiv !== '' && $selectedLiv === $no) ? ' selected' : '';
                                                echo '<option value="' . h($no) . '"' . $sel . '>' . h($no) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="col-form-label">De la marque</label>
                                        <select class="form-control populate" name="marque" required>
                                            <option value="">---- choisir ----</option>
                                            <?php
                                            $type = $bdd->prepare('SELECT id_marque, marque FROM marques WHERE status = 1 ORDER BY marque');
                                            $type->execute();
                                            $selectedMarque = isset($_POST['marque']) ? (int)$_POST['marque'] : 0;
                                            while ($model = $type->fetch(PDO::FETCH_ASSOC)) {
                                                $id = (int)($model['id_marque'] ?? 0);
                                                $label = (string)($model['marque'] ?? '');
                                                if ($id <= 0 || $label === '') { continue; }
                                                $sel = ($selectedMarque > 0 && $selectedMarque === $id) ? ' selected' : '';
                                                echo '<option value="' . h($id) . '"' . $sel . '>' . h($label) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="col-form-label">Couleur</label>
                                        <input type="text" name="couleur" class="form-control" required value="<?php echo isset($_POST['couleur']) ? h($_POST['couleur']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="col-form-label">Type de la monture</label>
                                        <?php $selType = isset($_POST['typeMonture']) ? (string)$_POST['typeMonture'] : ''; ?>
                                        <select class="form-control populate" name="typeMonture">
                                            <option value="">---- choisir ----</option>
                                            <option value="Adulte Homme" <?php echo ($selType === 'Adulte Homme') ? 'selected' : ''; ?>>Adulte Homme</option>
                                            <option value="Adulte Femme" <?php echo ($selType === 'Adulte Femme') ? 'selected' : ''; ?>>Adulte Femme</option>
                                            <option value="Enfant" <?php echo ($selType === 'Enfant') ? 'selected' : ''; ?>>Enfant</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="col-form-label">Prix de la monture</label>
                                        <input type="number" step="50000" name="prixmonture" class="form-control" value="<?php echo isset($_POST['prixmonture']) ? h($_POST['prixmonture']) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                            <button class="btn btn-primary" type="submit">Ajouter la monture</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal modification monture -->
        <div class="modal fade" id="editMontureModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form class="form-horizontal" novalidate="novalidate" method="POST" action="<?php echo h($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
                        <div class="modal-header">
                            <h5 class="modal-title">Modifier une monture</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <?php
                            $editId = (int)($editData['id_monture'] ?? (isset($_POST['id_monture']) ? (int)$_POST['id_monture'] : 0));
                            $editCode = (string)($editData['code_monture'] ?? (isset($_POST['codeMonture']) ? (string)$_POST['codeMonture'] : ''));
                            $editCouleur = (string)($editData['couleur'] ?? (isset($_POST['couleur']) ? (string)$_POST['couleur'] : ''));
                            $editPrix = (string)($editData['prix'] ?? (isset($_POST['prixmonture']) ? (string)$_POST['prixmonture'] : ''));
                            $editType = (string)($editData['monture_pour'] ?? (isset($_POST['typeMonture']) ? (string)$_POST['typeMonture'] : ''));
                            $selectedMarqueEdit = (int)($editData['id_marque'] ?? (isset($_POST['marque']) ? (int)$_POST['marque'] : 0));
                            $searchCodeVal = $searchCode !== '' ? $searchCode : (isset($_POST['searchCode']) ? (string)$_POST['searchCode'] : '');
                            ?>

                            <div class="row form-group pb-3">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="col-form-label">Code de la monture</label>
                                        <input type="text" pattern="^\S*$" oninput="this.value = this.value.replace(/\s/g, '')" name="searchCode" class="form-control" required value="<?php echo h($searchCodeVal); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3" style="display:flex; align-items:end;">
                                    <button class="btn btn-info" type="submit" name="chercher_monture" value="1">Afficher</button>
                                </div>
                            </div>

                            <?php if ($editId > 0): ?>
                                <div class="alert alert-success"><li>Monture trouvée : vous pouvez modifier les informations ci-dessous.</li></div>
                                <input type="hidden" name="id_monture" value="<?php echo h($editId); ?>">
                            <?php endif; ?>

                            <div class="row form-group pb-3">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="col-form-label">N° de la monture</label>
                                        <input type="text" pattern="^\S*$" oninput="this.value = this.value.replace(/\s/g, '')" name="codeMonture" class="form-control" required value="<?php echo h($editCode); ?>" <?php echo ($editId > 0 ? '' : 'disabled'); ?>>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="col-form-label">De la marque</label>
                                        <select class="form-control populate" name="marque" required <?php echo ($editId > 0 ? '' : 'disabled'); ?>>
                                            <option value="">---- choisir ----</option>
                                            <?php
                                            $type = $bdd->prepare('SELECT id_marque, marque FROM marques WHERE status = 1 ORDER BY marque');
                                            $type->execute();
                                            while ($model = $type->fetch(PDO::FETCH_ASSOC)) {
                                                $id = (int)($model['id_marque'] ?? 0);
                                                $label = (string)($model['marque'] ?? '');
                                                if ($id <= 0 || $label === '') { continue; }
                                                $sel = ($selectedMarqueEdit > 0 && $selectedMarqueEdit === $id) ? ' selected' : '';
                                                echo '<option value="' . h($id) . '"' . $sel . '>' . h($label) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="col-form-label">Couleur</label>
                                        <input type="text" name="couleur" class="form-control" required value="<?php echo h($editCouleur); ?>" <?php echo ($editId > 0 ? '' : 'disabled'); ?>>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="col-form-label">Type de la monture</label>
                                        <select class="form-control populate" name="typeMonture" <?php echo ($editId > 0 ? '' : 'disabled'); ?>>
                                            <option value="">---- choisir ----</option>
                                            <option value="Adulte Homme" <?php echo ($editType === 'Adulte Homme') ? 'selected' : ''; ?>>Adulte Homme</option>
                                            <option value="Adulte Femme" <?php echo ($editType === 'Adulte Femme') ? 'selected' : ''; ?>>Adulte Femme</option>
                                            <option value="Enfant" <?php echo ($editType === 'Enfant') ? 'selected' : ''; ?>>Enfant</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="col-form-label">Prix de la monture</label>
                                        <input type="number" step="50000" name="prixmonture" class="form-control" value="<?php echo h($editPrix); ?>" <?php echo ($editId > 0 ? '' : 'disabled'); ?>>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                            <button class="btn btn-primary" type="submit" name="modifier_monture" value="1" <?php echo ($editId > 0 ? '' : 'disabled'); ?>>Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include '../PUBLIC/footer.php';?>

        <script>
                $(document).ready(function() {
                    if (!$.fn.DataTable.isDataTable('#datatable-default')) {
                        $('#datatable-default').DataTable({
                            paging: true,
                            pageLength: 10
                        });
                    }
                });
                function exportAllDataToExcel(tableID, filename = '') {
                    var table = $('#' + tableID).DataTable();
                    table.page.len(-1).draw();
                    exportTableToExcel(tableID, filename);
                    table.page.len(10).draw();
                }
                function exportTableToExcel(tableID, filename = ''){
                    var downloadLink;
                    var dataType = 'application/vnd.ms-excel';
                    var tableSelect = document.getElementById(tableID);
                    var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
                    filename = filename ? filename + '.xls' : 'liste_des_produits_en_stock.xls';
                    downloadLink = document.createElement("a");
                    document.body.appendChild(downloadLink);
                    downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
                    downloadLink.download = filename;
                    downloadLink.click();
                    document.body.removeChild(downloadLink);
                }
            </script>
        <script>
            (function () {
                var shouldOpen = <?php echo $openAddModal ? 'true' : 'false'; ?>;
                if (!shouldOpen) return;
                document.addEventListener('DOMContentLoaded', function () {
                    try {
                        var el = document.getElementById('addMontureModal');
                        if (!el || typeof bootstrap === 'undefined') return;
                        var modal = new bootstrap.Modal(el);
                        modal.show();
                    } catch (e) {
                        // noop
                    }
                });
            })();
        </script>

        <script>
            (function () {
                var shouldOpen = <?php echo $openEditModal ? 'true' : 'false'; ?>;
                if (!shouldOpen) return;
                document.addEventListener('DOMContentLoaded', function () {
                    try {
                        var el = document.getElementById('editMontureModal');
                        if (!el || typeof bootstrap === 'undefined') return;
                        var modal = new bootstrap.Modal(el);
                        modal.show();
                    } catch (e) {
                        // noop
                    }
                });
            })();
        </script>