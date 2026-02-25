<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

function cleanInput($data)
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

function fetchActiveUsers(PDO $bdd): array
{
    try {
        $st = $bdd->prepare('SELECT id, pseudo FROM users WHERE status = 1 ORDER BY pseudo');
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        try {
            $st = $bdd->prepare('SELECT id, pseudo FROM users ORDER BY pseudo');
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e2) {
            error_log('Erreur chargement users pour edit budget: ' . $e2->getMessage());
            return [];
        }
    }
}

function validateBudget(array $data): array
{
    $errors = [];

    if ($data['nom_budget'] === '') {
        $errors[] = "L'intitulé du budget est requis";
    }

    if (!in_array($data['type_budget'], ['fonctionnement', 'opérationnel', 'operationnel', 'capital', 'autre'], true)) {
        $errors[] = "Type de budget invalide";
    }

    if ($data['date_debut'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date_debut'])) {
        $errors[] = "Date début invalide";
    }

    if ($data['date_fin'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date_fin'])) {
        $errors[] = "Date fin invalide";
    }

    if ($data['date_debut'] !== '' && $data['date_fin'] !== '' && $data['date_fin'] < $data['date_debut']) {
        $errors[] = "La date de fin doit être supérieure ou égale à la date de début";
    }

    return $errors;
}

function renderEditBudgetForm(array $budget, array $users): void
{
    $idBudget = (int)($budget['id_budget'] ?? 0);
    $nom = htmlspecialchars((string)($budget['nom_budget'] ?? ''), ENT_QUOTES, 'UTF-8');
    $type = (string)($budget['type_budget'] ?? 'opérationnel');
    $dateDebut = htmlspecialchars((string)($budget['date_debut'] ?? ''), ENT_QUOTES, 'UTF-8');
    $dateFin = htmlspecialchars((string)($budget['date_fin'] ?? ''), ENT_QUOTES, 'UTF-8');
    $montantInitial = htmlspecialchars((string)($budget['montant_initial'] ?? '0'), ENT_QUOTES, 'UTF-8');
    $notes = htmlspecialchars((string)($budget['notes'] ?? ''), ENT_QUOTES, 'UTF-8');

    $respRaw = (string)($budget['responsable'] ?? '');
    $responsableId = ctype_digit($respRaw) ? (int)$respRaw : 0;

    ?>
    <div id="editBudgetErrors" class="alert alert-danger" style="display:none;"></div>
    <div id="editBudgetSuccess" class="alert alert-success" style="display:none;"></div>

    <form id="editBudgetForm" method="post" action="editbudget.php" novalidate>
        <input type="hidden" name="id_budget" value="<?php echo $idBudget; ?>">

        <div class="row form-group pb-3">
            <div class="col-md-6">
                <label class="col-form-label" for="nom_budget">Intitulé du budget</label>
                <input type="text" name="nom_budget" id="nom_budget" class="form-control" value="<?php echo $nom; ?>" required>
            </div>
        </div>

        <div class="row form-group pb-3">
            <div class="col-md-3">
                <label class="col-form-label" for="type_budget">Type de budget</label>
                <select class="form-control populate" name="type_budget" id="type_budget" required>
                    <option value="fonctionnement" <?php echo ($type === 'fonctionnement') ? 'selected' : ''; ?>>Fonctionnement</option>
                    <option value="opérationnel" <?php echo ($type === 'opérationnel' || $type === 'operationnel') ? 'selected' : ''; ?>>Opérationnel</option>
                    <option value="capital" <?php echo ($type === 'capital') ? 'selected' : ''; ?>>Capital</option>
                    <option value="autre" <?php echo ($type === 'autre') ? 'selected' : ''; ?>>Autres</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="col-form-label" for="date_debut">Date début</label>
                <input type="date" name="date_debut" id="date_debut" class="form-control" value="<?php echo $dateDebut; ?>" required>
            </div>
            <div class="col-md-3">
                <label class="col-form-label" for="date_fin">Date fin</label>
                <input type="date" name="date_fin" id="date_fin" class="form-control" value="<?php echo $dateFin; ?>" required>
            </div>
            <div class="col-md-3">
                <label class="col-form-label" for="montant_initial">Montant initial</label>
                <input type="number" class="form-control" id="montant_initial" value="<?php echo $montantInitial; ?>" disabled>
                <small class="text-muted">Le montant initial n'est pas modifiable.</small>
            </div>
        </div>

        <div class="row form-group pb-3">
            <div class="col-md-6">
                <label class="col-form-label" for="responsable">Responsable du budget</label>
                <select class="form-control populate" name="responsable" id="responsable">
                    <option value="">-- Choisir un utilisateur --</option>
                    <?php foreach ($users as $u):
                        $uid = (int)($u['id'] ?? 0);
                        $pseudo = htmlspecialchars((string)($u['pseudo'] ?? ''), ENT_QUOTES, 'UTF-8');
                        if ($uid <= 0 || $pseudo === '') continue;
                        ?>
                        <option value="<?php echo $uid; ?>" <?php echo ($responsableId === $uid) ? 'selected' : ''; ?>><?php echo $pseudo; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="row form-group pb-3">
            <div class="col-md-12">
                <label class="col-form-label" for="notes">Notes</label>
                <textarea class="form-control" name="notes" id="notes" rows="5" placeholder="Notes sur le budget"><?php echo $notes; ?></textarea>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-default" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary" id="editBudgetSubmitBtn">Enregistrer</button>
        </div>
    </form>
    <?php
}

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$isAjax = (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');

if ($isPost) {
    $idBudget = (int)($_POST['id_budget'] ?? 0);
    if ($idBudget <= 0) {
        jsonResponse(['success' => false, 'errors' => ['Budget invalide.']], 422);
    }

    $responsableIdPost = (int)($_POST['responsable'] ?? 0);

    $formData = [
        'nom_budget' => cleanInput($_POST['nom_budget'] ?? ''),
        'type_budget' => cleanInput($_POST['type_budget'] ?? ''),
        'date_debut' => cleanInput($_POST['date_debut'] ?? ''),
        'date_fin' => cleanInput($_POST['date_fin'] ?? ''),
        'responsable' => ($responsableIdPost > 0) ? (string)$responsableIdPost : '',
        'notes' => cleanInput($_POST['notes'] ?? ''),
    ];

    $errors = validateBudget($formData);
    if (!empty($errors)) {
        jsonResponse(['success' => false, 'errors' => $errors], 422);
    }

    try {
        $bdd->beginTransaction();

        // Vérifier l'existence
        $stBudget = $bdd->prepare('SELECT id_budget FROM budgets WHERE id_budget = ? LIMIT 1');
        $stBudget->execute([$idBudget]);
        if (!$stBudget->fetchColumn()) {
            $bdd->rollBack();
            jsonResponse(['success' => false, 'errors' => ['Budget introuvable.']], 404);
        }

        // Unicité nom (hors budget courant)
        $stExists = $bdd->prepare('SELECT COUNT(*) FROM budgets WHERE nom_budget = ? AND id_budget <> ?');
        $stExists->execute([$formData['nom_budget'], $idBudget]);
        if ((int)$stExists->fetchColumn() > 0) {
            $bdd->rollBack();
            jsonResponse(['success' => false, 'errors' => ["Ce budget existe déjà dans le système."]], 409);
        }

        $st = $bdd->prepare('UPDATE budgets SET nom_budget = ?, type_budget = ?, date_debut = ?, date_fin = ?, responsable = ?, notes = ? WHERE id_budget = ?');
        $st->execute([
            $formData['nom_budget'],
            $formData['type_budget'],
            $formData['date_debut'],
            $formData['date_fin'],
            $formData['responsable'],
            $formData['notes'],
            $idBudget,
        ]);

        $bdd->commit();
        jsonResponse(['success' => true, 'message' => 'Budget mis à jour avec succès']);
    } catch (Throwable $e) {
        if ($bdd->inTransaction()) {
            $bdd->rollBack();
        }
        error_log('Erreur modification budget: ' . $e->getMessage());
        jsonResponse(['success' => false, 'errors' => ['Une erreur est survenue lors de la modification du budget']], 500);
    }
}

// GET (contenu modal)
$idBudget = isset($_GET['id_budget']) ? (int)$_GET['id_budget'] : 0;
if ($idBudget <= 0) {
    ?>
    <div class="alert alert-danger">Budget introuvable.</div>
    <?php
    exit;
}

try {
    $st = $bdd->prepare('SELECT * FROM budgets WHERE id_budget = ? LIMIT 1');
    $st->execute([$idBudget]);
    $budget = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    error_log('Erreur chargement budget: ' . $e->getMessage());
    $budget = null;
}

if (!$budget) {
    ?>
    <div class="alert alert-danger">Budget introuvable.</div>
    <?php
    exit;
}

$users = fetchActiveUsers($bdd);

if ($isAjax) {
    renderEditBudgetForm($budget, $users);
    exit;
}

include('../PUBLIC/header.php');
?>

<body>
<section class="body">
    <?php require('../PUBLIC/navbarmenu.php'); ?>
    <div class="inner-wrapper">
        <section role="main" class="content-body">
            <header class="page-header"><h2>Modifier un budget</h2></header>
            <div class="col-md-12">
                <section class="card">
                    <div class="card-body">
                        <?php renderEditBudgetForm($budget, $users); ?>
                    </div>
                </section>
            </div>
        </section>
    </div>
    <?php include('../PUBLIC/footer.php'); ?>
