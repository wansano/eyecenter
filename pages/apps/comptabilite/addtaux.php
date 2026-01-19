<?php
include('../PUBLIC/connect.php');
session_start();

function cleanInput($data): string
{
    $data = trim((string)$data);
    $data = stripslashes($data);
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function validateTaux(float $taux, int $tauxPour): array
{
    $errors = [];

    if ($taux < 0 || $taux > 100) {
        $errors[] = 'Le taux doit être compris entre 0 et 100.';
    }

    if (!in_array($tauxPour, [0, 1], true)) {
        $errors[] = "Type d'affectation invalide.";
    }

    return $errors;
}

function renderTauxForm(): void
{
    ?>
    <div id="addTauxErrors" class="alert alert-danger" style="display:none;"></div>
    <div id="addTauxSuccess" class="alert alert-success" style="display:none;"></div>

    <form id="addTauxForm" method="post" action="addtaux.php" novalidate>
        <div class="row form-group pb-3">
            <div class="col-md-4">
                <label class="col-form-label" for="taux">Taux (%)</label>
                <input type="number" step="0.01" min="0" max="100" class="form-control" name="taux" id="taux" required>
            </div>
            <div class="col-md-4">
                <label class="col-form-label" for="taux_pour">Taux affecté</label>
                <select class="form-control" name="taux_pour" id="taux_pour" required>
                    <option value="0">Clinique</option>
                    <option value="1">Boutique</option>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="activer_immediatement" name="activer_immediatement">
                    <label class="form-check-label" for="activer_immediatement">
                        Activer immédiatement
                    </label>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-default" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary" id="addTauxSubmitBtn">Enregistrer</button>
        </div>
    </form>
    <?php
}

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$isAjax = (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');

if ($isPost) {
    // Réactiver un taux supprimé
    if (isset($_POST['reactiver_id'])) {
        $idTaux = (int)($_POST['reactiver_id'] ?? 0);
        $activer = (int)($_POST['activer_immediatement'] ?? 0) === 1;
        if ($idTaux <= 0) {
            jsonResponse(['success' => false, 'errors' => ['Taux invalide.']], 422);
        }

        try {
            $bdd->beginTransaction();

            $stRow = $bdd->prepare('SELECT id_taux, taux_pour, status FROM taux WHERE id_taux = ? LIMIT 1');
            $stRow->execute([$idTaux]);
            $row = $stRow->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $bdd->rollBack();
                jsonResponse(['success' => false, 'errors' => ['Taux introuvable.']], 404);
            }
            if ((int)($row['status'] ?? 0) !== 3) {
                $bdd->rollBack();
                jsonResponse(['success' => false, 'errors' => ['Ce taux n\'est pas archivé.']], 409);
            }

            $tauxPour = (int)($row['taux_pour'] ?? 0);
            if ($activer) {
                $st = $bdd->prepare('UPDATE taux SET status = 0 WHERE taux_pour = ?');
                $st->execute([$tauxPour]);
            }

            $newStatus = $activer ? 1 : 0;
            $st = $bdd->prepare('UPDATE taux SET status = ? WHERE id_taux = ?');
            $st->execute([$newStatus, $idTaux]);

            $bdd->commit();
            jsonResponse(['success' => true, 'message' => 'Taux réactivé avec succès']);
        } catch (Throwable $e) {
            if ($bdd->inTransaction()) {
                $bdd->rollBack();
            }
            error_log('Erreur réactivation taux: ' . $e->getMessage());
            jsonResponse(['success' => false, 'errors' => ['Une erreur est survenue lors de la réactivation du taux']], 500);
        }
    }

    $tauxRaw = str_replace(',', '.', (string)($_POST['taux'] ?? ''));
    $taux = is_numeric($tauxRaw) ? (float)$tauxRaw : -1;
    $tauxPour = (int)($_POST['taux_pour'] ?? -1);
    $activer = (int)($_POST['activer_immediatement'] ?? 0) === 1;

    $errors = validateTaux($taux, $tauxPour);
    if (!empty($errors)) {
        jsonResponse(['success' => false, 'errors' => $errors], 422);
    }

    try {
        $bdd->beginTransaction();

        // Empêcher doublons et proposer réactivation si déjà supprimé
        $stDup = $bdd->prepare('SELECT id_taux, status FROM taux WHERE taux = ? AND taux_pour = ? ORDER BY id_taux DESC LIMIT 1');
        $stDup->execute([$taux, $tauxPour]);
        $dup = $stDup->fetch(PDO::FETCH_ASSOC);
        if ($dup) {
            $dupStatus = (int)($dup['status'] ?? 0);
            $dupId = (int)($dup['id_taux'] ?? 0);
            if ($dupStatus === 3 && $dupId > 0) {
                $bdd->rollBack();
                jsonResponse([
                    'success' => false,
                    'code' => 'DELETED_EXISTS',
                    'message' => 'Ce taux existe déjà mais il est archivé.',
                    'existing' => [
                        'id_taux' => $dupId,
                        'activer_immediatement' => $activer ? 1 : 0,
                    ],
                ], 409);
            }

            $bdd->rollBack();
            jsonResponse(['success' => false, 'errors' => ['Ce taux existe déjà.']], 409);
        }

        if ($activer) {
            // Désactiver les autres taux pour le même périmètre
            $st = $bdd->prepare('UPDATE taux SET status = 0 WHERE taux_pour = ?');
            $st->execute([$tauxPour]);
        }

        $status = $activer ? 1 : 0;
        $date = date('Y-m-d');

        $st = $bdd->prepare('INSERT INTO taux (date, taux, taux_pour, status) VALUES (?, ?, ?, ?)');
        $st->execute([$date, $taux, $tauxPour, $status]);

        $bdd->commit();
        jsonResponse(['success' => true, 'message' => 'Taux ajouté avec succès']);
    } catch (Throwable $e) {
        if ($bdd->inTransaction()) {
            $bdd->rollBack();
        }
        error_log('Erreur ajout taux: ' . $e->getMessage());
        jsonResponse(['success' => false, 'errors' => ['Une erreur est survenue lors de l\'ajout du taux']], 500);
    }
}

if ($isAjax) {
    renderTauxForm();
    exit;
}

include('../PUBLIC/header.php');
?>

<body>
<section class="body">
<?php require('../PUBLIC/navbarmenu.php'); ?>
<div class="inner-wrapper">
    <section role="main" class="content-body">
        <header class="page-header"><h2>Ajouter un taux de remise</h2></header>
        <div class="col-md-12">
            <section class="card">
                <div class="card-body">
                    <?php renderTauxForm(); ?>
                </div>
            </section>
        </div>
    </section>
</div>
<?php include('../PUBLIC/footer.php'); ?>
