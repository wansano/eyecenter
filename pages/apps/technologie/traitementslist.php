<?php
include('../PUBLIC/connect.php');
include('../PUBLIC/fonction.php');
session_start();

// Feedback après POST (PRG pattern)
$errors = isset($_GET['ok']) ? (int) $_GET['ok'] : 0;

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function operation_label($operation) {
    $op = (int) $operation;
    if ($op === 1) return 'Opérable';
    if ($op === 0) return 'Consultation';
    if ($op === 2) return 'Soins';
    if ($op === 3) return 'Imagerie';
    if ($op === 4) return 'Contrôle';
    if ($op === 6) return 'Rapport médical';
    if ($op === 7) return 'Rapport médical évacuation';
    if (in_array($op, [5, 10, 11, 12], true)) return 'Optique';
    if ($op === -1) return 'N/A';
    return 'N/A';
}

function status_cellule($idOrganigramme) {
    global $bdd;
    $id = (int) $idOrganigramme;
    if ($id <= 0) return 1;
    try {
        $statusCol = null;
        try { $bdd->query('SELECT status FROM organigramme LIMIT 1'); $statusCol = 'status'; } catch (PDOException $e) {}
        if ($statusCol === null) {
            try { $bdd->query('SELECT statuts FROM organigramme LIMIT 1'); $statusCol = 'statuts'; } catch (PDOException $e) {}
        }
        if ($statusCol === null) return 1;
        $stmt = $bdd->prepare('SELECT ' . $statusCol . ' FROM organigramme WHERE id_organigramme = ? LIMIT 1');
        $stmt->execute([$id]);
        $val = $stmt->fetchColumn();
        return ($val === false) ? 1 : (int) $val;
    } catch (PDOException $e) {
        error_log('traitementslist.php status_cellule error: ' . $e->getMessage());
        return 1;
    }
}

// Détection colonnes traitements (compat)
$traitementsHasIdOrg = false;
$traitementsHasModel = false;
$traitementsHasOperation = false;
$traitementsHasPrixAssurance = false;
try { $bdd->query('SELECT id_organigramme FROM traitements LIMIT 1'); $traitementsHasIdOrg = true; } catch (PDOException $e) {}
try { $bdd->query('SELECT model FROM traitements LIMIT 1'); $traitementsHasModel = true; } catch (PDOException $e) {}
try { $bdd->query('SELECT operation FROM traitements LIMIT 1'); $traitementsHasOperation = true; } catch (PDOException $e) {}
try { $bdd->query('SELECT prix_assurance FROM traitements LIMIT 1'); $traitementsHasPrixAssurance = true; } catch (PDOException $e) {}

// Actions + Ajout traitement (PRG)
try {
    // Activer / Désactiver / Supprimer
    $action = null;
    $idType = 0;
    $newStatus = null;
    $okCode = 0;

    if (isset($_POST['activer'])) {
        $action = 'activer';
        $idType = (int) $_POST['activer'];
        $newStatus = 1;
        $okCode = 2;
    } elseif (isset($_POST['desactiver'])) {
        $action = 'desactiver';
        $idType = (int) $_POST['desactiver'];
        $newStatus = 0;
        $okCode = 3;
    } elseif (isset($_POST['supprimer'])) {
        $action = 'supprimer';
        $idType = (int) $_POST['supprimer'];
        $newStatus = 3;
        $okCode = 4;
    }

    if ($action !== null && $idType > 0 && $newStatus !== null) {
        $stmt = $bdd->prepare('UPDATE traitements SET status = ? WHERE id_type = ?');
        $stmt->execute([$newStatus, $idType]);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=' . $okCode);
        exit;
    }

    // Ajouter traitement
    if (isset($_POST['add_traitement'])) {
        $nomType = isset($_POST['nom_type']) ? trim((string) $_POST['nom_type']) : '';
        $montant = isset($_POST['montant']) ? (float) $_POST['montant'] : 0.0;
        $prixAssurance = isset($_POST['prix_assurance']) ? (float) $_POST['prix_assurance'] : 0.0;
        $idCellule = isset($_POST['id_organigramme']) ? (int) $_POST['id_organigramme'] : 0;
        $operation = isset($_POST['operation']) ? (int) $_POST['operation'] : -1;

        if ($nomType === '' || $montant < 0 || $prixAssurance < 0 || $idCellule <= 0) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=6');
            exit;
        }

        // Unicité par cellule
        $whereParts = ['nom_type = ?'];
        $params = [$nomType];
        if ($traitementsHasIdOrg) {
            $whereParts[] = 'id_organigramme = ?';
            $params[] = $idCellule;
        }
        if ($traitementsHasModel) {
            $whereParts[] = 'model = ?';
            $params[] = $idCellule;
        }

        if (count($whereParts) < 2) {
            // Impossible de relier à une cellule => on bloque
            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=6');
            exit;
        }

        $sqlCheck = 'SELECT COUNT(*) FROM traitements WHERE ' . $whereParts[0] . ' AND (' . implode(' OR ', array_slice($whereParts, 1)) . ')';
        $stmt = $bdd->prepare($sqlCheck);
        $stmt->execute($params);
        $exists = (int) $stmt->fetchColumn() > 0;
        if ($exists) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=7');
            exit;
        }

        $cols = ['nom_type', 'montant', 'status'];
        $vals = [$nomType, $montant, 0];

        if ($traitementsHasPrixAssurance) {
            $cols[] = 'prix_assurance';
            $vals[] = $prixAssurance;
        }

        if ($traitementsHasOperation) {
            $cols[] = 'operation';
            $vals[] = $operation;
        }
        if ($traitementsHasIdOrg) {
            $cols[] = 'id_organigramme';
            $vals[] = $idCellule;
        }
        if ($traitementsHasModel) {
            $cols[] = 'model';
            $vals[] = $idCellule;
        }

        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sqlInsert = 'INSERT INTO traitements (' . implode(',', $cols) . ') VALUES (' . $placeholders . ')';
        $stmt = $bdd->prepare($sqlInsert);
        $stmt->execute($vals);

        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=5');
        exit;
    }

    // Modifier traitement
    if (isset($_POST['edit_traitement_save'])) {
        $editIdType = isset($_POST['id_type']) ? (int) $_POST['id_type'] : 0;
        $nomType = isset($_POST['nom_type']) ? trim((string) $_POST['nom_type']) : '';
        $montant = isset($_POST['montant']) ? (float) $_POST['montant'] : 0.0;
        $prixAssurance = isset($_POST['prix_assurance']) ? (float) $_POST['prix_assurance'] : 0.0;
        $idCellule = isset($_POST['id_organigramme']) ? (int) $_POST['id_organigramme'] : 0;
        $operation = isset($_POST['operation']) ? (int) $_POST['operation'] : -1;

        if ($editIdType <= 0 || $nomType === '' || $montant < 0 || $prixAssurance < 0 || $idCellule <= 0) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=9');
            exit;
        }

        // Vérifier existence
        $stmt = $bdd->prepare('SELECT id_type FROM traitements WHERE id_type = ? LIMIT 1');
        $stmt->execute([$editIdType]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=11');
            exit;
        }

        // Unicité par cellule (hors id courant)
        $whereParts = ['nom_type = ?'];
        $params = [$nomType];
        if ($traitementsHasIdOrg) {
            $whereParts[] = 'id_organigramme = ?';
            $params[] = $idCellule;
        }
        if ($traitementsHasModel) {
            $whereParts[] = 'model = ?';
            $params[] = $idCellule;
        }

        if (count($whereParts) < 2) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=9');
            exit;
        }

        $sqlCheck = 'SELECT COUNT(*) FROM traitements WHERE id_type <> ? AND ' . $whereParts[0] . ' AND (' . implode(' OR ', array_slice($whereParts, 1)) . ')';
        $stmt = $bdd->prepare($sqlCheck);
        $stmt->execute(array_merge([$editIdType], $params));
        $exists = (int) $stmt->fetchColumn() > 0;
        if ($exists) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=10');
            exit;
        }

        $setParts = ['nom_type = ?', 'montant = ?'];
        $vals = [$nomType, $montant];

        if ($traitementsHasPrixAssurance) {
            $setParts[] = 'prix_assurance = ?';
            $vals[] = $prixAssurance;
        }
        if ($traitementsHasOperation) {
            $setParts[] = 'operation = ?';
            $vals[] = $operation;
        }
        if ($traitementsHasIdOrg) {
            $setParts[] = 'id_organigramme = ?';
            $vals[] = $idCellule;
        }
        if ($traitementsHasModel) {
            $setParts[] = 'model = ?';
            $vals[] = $idCellule;
        }

        $bdd->beginTransaction();
        $stmt = $bdd->prepare('UPDATE traitements SET ' . implode(', ', $setParts) . ' WHERE id_type = ?');
        $stmt->execute(array_merge($vals, [$editIdType]));
        $bdd->commit();

        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=8');
        exit;
    }
} catch (PDOException $e) {
    error_log('traitementslist.php action error: ' . $e->getMessage());
    $errors = 1;
}
include('../PUBLIC/header.php');

// Préparer liste des cellules (organigramme) pour les modals
// - Ajout: uniquement departement "Clinique" (si colonne disponible)
// - Modification: toutes les cellules
$serviceOptionsHtmlAll = '';
$serviceOptionsHtmlClinique = '';
try {
    $hasDepartement = false;
    $statusCol = null;
    try { $bdd->query('SELECT departement FROM organigramme LIMIT 1'); $hasDepartement = true; } catch (PDOException $e) { $hasDepartement = false; }
    try { $bdd->query('SELECT status FROM organigramme LIMIT 1'); $statusCol = 'status'; } catch (PDOException $e) {}
    if ($statusCol === null) {
        try { $bdd->query('SELECT statuts FROM organigramme LIMIT 1'); $statusCol = 'statuts'; } catch (PDOException $e) {}
    }

    $cols = 'id_organigramme, celulle' . ($hasDepartement ? ', departement' : '');

    // 1) Toutes les cellules (pour modification)
    $sqlAll = 'SELECT ' . $cols . ' FROM organigramme';
    if ($statusCol !== null) {
        $sqlAll .= ' WHERE ' . $statusCol . ' != 3';
    }
    $sqlAll .= ' ORDER BY ' . ($hasDepartement ? 'departement, ' : '') . 'celulle';

    $stmt = $bdd->prepare($sqlAll);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $idOrg = (int) ($row['id_organigramme'] ?? 0);
        $celulle = (string) ($row['celulle'] ?? '');
        $departement = $hasDepartement ? (string) ($row['departement'] ?? '') : '';
        if ($idOrg <= 0 || $celulle === '') { continue; }
        $label = ($departement !== '') ? ($departement . ' - ' . $celulle) : $celulle;
        $serviceOptionsHtmlAll .= '<option value="' . h((string)$idOrg) . '">' . h($label) . '</option>';
    }

    // 2) Uniquement departement Clinique (pour ajout)
    if ($hasDepartement) {
        $where = [];
        $params = [];
        // Valeurs DB parfois: "Clinique", "CLINIQUE OPHTA", etc.
        // On filtre large, et on fallback vers toutes les cellules si vide.
        $where[] = 'LOWER(TRIM(departement)) LIKE ?';
        $params[] = '%clinique%';
        if ($statusCol !== null) {
            $where[] = $statusCol . ' != 3';
        }

        $sqlClinique = 'SELECT ' . $cols . ' FROM organigramme WHERE ' . implode(' AND ', $where)
            . ' ORDER BY departement, celulle';
        $stmt2 = $bdd->prepare($sqlClinique);
        $stmt2->execute($params);
        while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            $idOrg = (int) ($row['id_organigramme'] ?? 0);
            $celulle = (string) ($row['celulle'] ?? '');
            $departement = (string) ($row['departement'] ?? '');
            if ($idOrg <= 0 || $celulle === '') { continue; }
            $label = ($departement !== '') ? ($departement . ' - ' . $celulle) : $celulle;
            $serviceOptionsHtmlClinique .= '<option value="' . h((string)$idOrg) . '">' . h($label) . '</option>';
        }

        // Si aucune cellule "Clinique" trouvée, on retombe sur toutes les cellules
        if ($serviceOptionsHtmlClinique === '') {
            $serviceOptionsHtmlClinique = $serviceOptionsHtmlAll;
        }
    } else {
        // Pas de colonne departement => on retombe sur toutes les cellules
        $serviceOptionsHtmlClinique = $serviceOptionsHtmlAll;
    }
} catch (PDOException $e) {
    error_log('traitementslist.php modal services error: ' . $e->getMessage());
}
?>
	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Liste des traitements disponible</h2>
					</header>

					<!-- start: page -->
					<div class="col-md-12">
						<div class="row">
							<div class="col">
								<section class="card">
									<div class="card-body">
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addTraitementModal"><i class="fa fa-plus"></i> ajouter un traitement</button> <br/> <br>
                                        <?php 
                                            if ($errors==1) {
                                                echo '
                                                <div class="alert alert-danger">
                                                <li>Erreur ! Une action a échoué. Vérifier les logs.</li>
                                                </div>
                                                ';
                                            }
                                            if ($errors==2) {
                                            echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Ce type de traitement a été activer avec succès.</li>
                                                </div>
                                                '; }
                                            if ($errors==3) {
                                                echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Suite à votre propore decision, ce type de traitement à été desactiver avec succès.</li>
                                                </div>
                                                '; }
                                            if ($errors==4) {
                                                echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Type de traitement supprimer avec succès.</li>
                                                </div>
                                                '; }
                                            if ($errors==5) {
                                                echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong><br>Traitement ajouté avec succès. Veuillez l\'activer.</li>
                                                </div>
                                                ';
                                            }
                                            if ($errors==6) {
                                                echo '
                                                <div class="alert alert-danger">
                                                <li>Ajout non effectué : champs invalides.</li>
                                                </div>
                                                ';
                                            }
                                            if ($errors==7) {
                                                echo '
                                                <div class="alert alert-warning">
                                                <li>Ce traitement existe déjà pour cette cellule.</li>
                                                </div>
                                                ';
                                            }
                                            if ($errors==8) {
                                                echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong><br>Traitement modifié avec succès.</li>
                                                </div>
                                                ';
                                            }
                                            if ($errors==9) {
                                                echo '
                                                <div class="alert alert-danger">
                                                <li>Modification non effectuée : champs invalides.</li>
                                                </div>
                                                ';
                                            }
                                            if ($errors==10) {
                                                echo '
                                                <div class="alert alert-warning">
                                                <li>Modification non effectuée : ce traitement existe déjà pour cette cellule.</li>
                                                </div>
                                                ';
                                            }
                                            if ($errors==11) {
                                                echo '
                                                <div class="alert alert-danger">
                                                <li>Traitement introuvable.</li>
                                                </div>
                                                ';
                                            }
                                        ?>
    									<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
													<th>ID</th>
													<th>TRAITEMENT</th>
													<th>TYPE</th>
                                                    <th>MONTANT STANDARD</th>
                                                    <th>PRIX ASSURANCE</th>
                                                    <th>CELULLE</th>
                                                    <th>ACTION</th>
												</tr>
											</thead>
											<tbody>
											<?php
                                                    $cols = ['id_type', 'nom_type', 'montant', 'status'];
                                                    if ($traitementsHasPrixAssurance) { $cols[] = 'prix_assurance'; }
                                                if ($traitementsHasOperation) { $cols[] = 'operation'; }
                                                if ($traitementsHasIdOrg) { $cols[] = 'id_organigramme'; }
                                                if ($traitementsHasModel) { $cols[] = 'model'; }
                                                $sql = 'SELECT ' . implode(',', $cols) . ' FROM traitements ORDER BY id_type';
                                                $reponse1 = $bdd->prepare($sql);
                                                $reponse1->execute();
                                                while ($donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC))
                                                    {
                                                        $status = (int) ($donnees1['status'] ?? 0);
                                                        $idTypeRow = (int) ($donnees1['id_type'] ?? 0);
                                                        $nomTypeRow = (string) ($donnees1['nom_type'] ?? '');
                                                        $montantRow = (float) ($donnees1['montant'] ?? 0);
                                                        $prixAssuranceRow = $traitementsHasPrixAssurance ? (float) ($donnees1['prix_assurance'] ?? 0) : 0.0;
                                                        $operationRow = $traitementsHasOperation ? (int) ($donnees1['operation'] ?? -1) : -1;
                                                        $idCell = 0;
                                                        if ($traitementsHasIdOrg && isset($donnees1['id_organigramme'])) {
                                                            $idCell = (int) $donnees1['id_organigramme'];
                                                        } elseif ($traitementsHasModel && isset($donnees1['model'])) {
                                                            $idCell = (int) $donnees1['model'];
                                                        }

                                                        if ($status === 3) { continue; }
                                                        if (status_cellule($idCell) !== 1) { continue; }

                                                        echo '<tr>';
                                                        echo '<td>' . h((string)$idTypeRow) . '</td>';
                                                        echo '<td>' . h($nomTypeRow) . '</td>';
                                                        echo '<td>' . h(operation_label($operationRow)) . '</td>';
                                                        echo '<td>' . h(number_format((float)$montantRow)) . ' '.$devise . '</td>';
                                                        echo '<td>' . h(number_format((float)$prixAssuranceRow)) . ' '.$devise . '</td>';
                                                        echo '<td>' . h(service($idCell)) . '</td>';
                                                        echo '<td>';

                                                        echo '<div class="d-flex gap-1 flex-wrap align-items-center">';

                                                        // Actions selon statut
                                                        if ($status === 0) {
                                                            echo '<form class="m-0" action="' . h($_SERVER['PHP_SELF']) . '" method="post">'
                                                                . '<button type="submit" name="activer" value="' . h((string)$idTypeRow) . '" class="btn btn-sm btn-warning"><i class="fa fa-unlock-alt"></i> activer</button>'
                                                                . '</form>'

                                                                . '<button type="button" class="btn btn-sm btn-info js-edit-traitement" data-bs-toggle="modal" data-bs-target="#editTraitementModal"'
                                                                . ' data-id_type="' . h((string)$idTypeRow) . '"'
                                                                . ' data-nom_type="' . h($nomTypeRow) . '"'
                                                                . ' data-montant="' . h((string)$montantRow) . '"'
                                                                . ' data-prix_assurance="' . h((string)$prixAssuranceRow) . '"'
                                                                . ' data-operation="' . h((string)$operationRow) . '"'
                                                                . ' data-id_organigramme="' . h((string)$idCell) . '"'
                                                                . '><i class="fa fa-edit"></i> modifier</button>'

                                                                . '<form class="m-0" action="' . h($_SERVER['PHP_SELF']) . '" method="post">'
                                                                . '<input type="hidden" name="supprimer" value="' . h((string)$idTypeRow) . '">'
                                                                . '<button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-times"></i> supprimer</button>'
                                                                . '</form>';
                                                        } elseif ($status === 1) {
                                                            echo '<form class="m-0" action="' . h($_SERVER['PHP_SELF']) . '" method="post">'
                                                                . '<input type="hidden" name="desactiver" value="' . h((string)$idTypeRow) . '">'
                                                                . '<button type="submit" class="btn btn-sm btn-success"><i class="fa fa-lock"></i> désactiver</button>'
                                                                . '</form>';
                                                        }

                                                        echo '</div>';

                                                        echo '</td>';
                                                        echo '</tr>';
                                                    }
                                            ?>
											</tbody>
										</table>

                                        <!-- Modal ajout traitement -->
                                        <div class="modal fade" id="addTraitementModal" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <form method="POST" action="<?php echo h($_SERVER['PHP_SELF']); ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Ajouter un traitement</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="add_traitement" value="1">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="nom_type">Nom du traitement</label>
                                                                        <input type="text" class="form-control" name="nom_type" id="nom_type" required>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="montant">Montant</label>
                                                                        <input type="number" step="1" min="0" class="form-control" name="montant" id="montant" required>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="prix_assurance">Prix assurance</label>
                                                                        <input type="number" step="1" min="0" class="form-control" name="prix_assurance" id="prix_assurance" required>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="operation">Type (opération)</label>
                                                                        <select class="form-control" name="operation" id="operation" required>
                                                                            <option value="1">Opérable</option>
                                                                            <option value="0">Consultation</option>
                                                                            <option value="2">Soins</option>
                                                                            <option value="3">Imagerie</option>
                                                                            <option value="4">Contrôle</option>
                                                                            <option value="6">Rapport Médical</option>
                                                                            <option value="7">Rapport Médical évacuation  s</option>
                                                                            <option value="5">Optique Réfraction</option>
                                                                            <option value="10">Vente Lunette</option>
                                                                            <option value="11">Vente Lentille</option>
                                                                            <option value="12">Vente Monture</option>
                                                                            <option value="-1">Non Assigné</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-12">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="id_organigramme">Cellule concernée</label>
                                                                        <select class="form-control populate" name="id_organigramme" id="id_organigramme" required>
                                                                            <?php echo $serviceOptionsHtmlClinique; ?>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                                                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Modal modification traitement (séparé, non imbriqué) -->
                                        <div class="modal fade" id="editTraitementModal" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <form method="POST" action="<?php echo h($_SERVER['PHP_SELF']); ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Modifier le traitement</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="edit_traitement_save" value="1">
                                                            <input type="hidden" name="id_type" id="edit_id_type" value="">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="edit_nom_type">Nom du traitement</label>
                                                                        <input type="text" class="form-control" name="nom_type" id="edit_nom_type" required>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="edit_montant">Montant</label>
                                                                        <input type="number" step="1" min="0" class="form-control" name="montant" id="edit_montant" required>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="edit_prix_assurance">Prix assurance</label>
                                                                        <input type="number" step="1" min="0" class="form-control" name="prix_assurance" id="edit_prix_assurance" required>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="edit_operation">Type (opération)</label>
                                                                        <select class="form-control" name="operation" id="edit_operation" required>
                                                                            <option value="1">Opérable</option>
                                                                            <option value="0">Consultation</option>
                                                                            <option value="2">Soins</option>
                                                                            <option value="3">Imagerie</option>
                                                                            <option value="4">Contrôle</option>
                                                                            <option value="6">Rapport Médical</option>
                                                                            <option value="7">Rapport Médical évacuation  s</option>
                                                                            <option value="5">Optique Réfraction</option>
                                                                            <option value="10">Vente Lunette</option>
                                                                            <option value="11">Vente Lentille</option>
                                                                            <option value="12">Vente Monture</option>
                                                                            <option value="-1">Non Assigné</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-12">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="edit_id_organigramme">Cellule concernée</label>
                                                                        <select class="form-control populate" name="id_organigramme" id="edit_id_organigramme" required>
                                                                            <?php echo $serviceOptionsHtmlAll; ?>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                                                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
									</div>
								</section>
							</div>
						</div>
				    </section>
			    </div>

            <script>
            (function () {
                function setValue(id, value) {
                    var el = document.getElementById(id);
                    if (!el) return;
                    el.value = (value === undefined || value === null) ? '' : value;
                }

                document.addEventListener('click', function (e) {
                    var btn = e.target.closest('.js-edit-traitement');
                    if (!btn) return;
                    setValue('edit_id_type', btn.getAttribute('data-id_type'));
                    setValue('edit_nom_type', btn.getAttribute('data-nom_type'));
                    setValue('edit_montant', btn.getAttribute('data-montant'));
                    setValue('edit_prix_assurance', btn.getAttribute('data-prix_assurance'));
                    setValue('edit_operation', btn.getAttribute('data-operation'));
                    setValue('edit_id_organigramme', btn.getAttribute('data-id_organigramme'));
                });
            })();
            </script>
            <?php include('../PUBLIC/footer.php');?>
		