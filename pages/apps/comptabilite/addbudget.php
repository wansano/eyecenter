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

    if ($data['montant_initial'] === '' || !is_numeric($data['montant_initial']) || (float)$data['montant_initial'] < 0) {
        $errors[] = "Le montant initial doit être un nombre positif";
    }

    return $errors;
}

function fetchActiveUsers(PDO $bdd): array
{
    try {
        $st = $bdd->prepare('SELECT id, pseudo FROM users WHERE status = 1 ORDER BY pseudo');
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        // Fallback si la colonne status n'existe pas ou autre contrainte
        try {
            $st = $bdd->prepare('SELECT id, pseudo FROM users ORDER BY pseudo');
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e2) {
            error_log('Erreur chargement users pour budget: ' . $e2->getMessage());
            return [];
        }
    }
}

function renderBudgetForm(array $values = []): void
{
    global $bdd;

    $nom = htmlspecialchars((string)($values['nom_budget'] ?? ''), ENT_QUOTES, 'UTF-8');
    $type = (string)($values['type_budget'] ?? 'opérationnel');
    $dateDebut = htmlspecialchars((string)($values['date_debut'] ?? ''), ENT_QUOTES, 'UTF-8');
    $dateFin = htmlspecialchars((string)($values['date_fin'] ?? ''), ENT_QUOTES, 'UTF-8');
    $montant = htmlspecialchars((string)($values['montant_initial'] ?? '0'), ENT_QUOTES, 'UTF-8');
    $responsable = (string)($values['responsable'] ?? '');
    $notes = htmlspecialchars((string)($values['notes'] ?? ''), ENT_QUOTES, 'UTF-8');

    $users = fetchActiveUsers($bdd);
    $responsableId = is_numeric($responsable) ? (int)$responsable : 0;

    ?>
    <div id="addBudgetErrors" class="alert alert-danger" style="display:none;"></div>
    <div id="addBudgetSuccess" class="alert alert-success" style="display:none;"></div>

    <form id="addBudgetForm" method="post" action="addbudget.php" novalidate>
        <input type="hidden" name="ajouter" value="1">

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
                    <option value="fonctionnement" <?php echo ($type === 'fonctionnement' || $type === 'fonctionnement') ? 'selected' : ''; ?>>Fonctionnement</option>
                    <option value="opérationnel" <?php echo ($type === 'opérationnel') ? 'selected' : ''; ?>>Opérationnel</option>
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
                <input type="number" min="0" step="1" class="form-control" name="montant_initial" id="montant_initial" value="<?php echo $montant; ?>" required>
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
            <button type="submit" class="btn btn-primary" id="addBudgetSubmitBtn">Ajouter le budget</button>
        </div>
    </form>
    <?php
}

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$isAjax = (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');

if ($isPost) {
    $responsableIdPost = (int)($_POST['responsable'] ?? 0);

    $formData = [
        'nom_budget' => cleanInput($_POST['nom_budget'] ?? ''),
        'type_budget' => cleanInput($_POST['type_budget'] ?? ''),
        'date_debut' => cleanInput($_POST['date_debut'] ?? ''),
        'date_fin' => cleanInput($_POST['date_fin'] ?? ''),
        'montant_initial' => cleanInput($_POST['montant_initial'] ?? ''),
        'responsable' => ($responsableIdPost > 0) ? (string)$responsableIdPost : '',
        'notes' => cleanInput($_POST['notes'] ?? ''),
    ];

    $errors = validateBudget($formData);
    if (!empty($errors)) {
        jsonResponse(['success' => false, 'errors' => $errors], 422);
    }

    try {
        $bdd->beginTransaction();

        $stExists = $bdd->prepare('SELECT COUNT(*) FROM budgets WHERE nom_budget = ?');
        $stExists->execute([$formData['nom_budget']]);
        if ((int)$stExists->fetchColumn() > 0) {
            $bdd->rollBack();
            jsonResponse(['success' => false, 'errors' => ["Ce budget existe déjà dans le système."]], 409);
        }

        $st = $bdd->prepare('INSERT INTO budgets (nom_budget, type_budget, date_debut, date_fin, montant_initial, responsable, notes) VALUES (?,?,?,?,?,?,?)');
        $st->execute([
            $formData['nom_budget'],
            $formData['type_budget'],
            $formData['date_debut'],
            $formData['date_fin'],
            $formData['montant_initial'],
            $formData['responsable'],
            $formData['notes'],
        ]);

        $bdd->commit();
        jsonResponse(['success' => true, 'message' => 'Budget ajouté avec succès']);
    } catch (Throwable $e) {
        if ($bdd->inTransaction()) {
            $bdd->rollBack();
        }
        error_log("Erreur ajout budget (modal) : " . $e->getMessage());
        jsonResponse(['success' => false, 'errors' => ['Une erreur est survenue lors de la création du budget']], 500);
    }
}

// GET
if ($isAjax) {
    renderBudgetForm();
    exit;
}

include('../PUBLIC/header.php');
?>

<body>
<section class="body">
    <?php require('../PUBLIC/navbarmenu.php'); ?>

    <div class="inner-wrapper">
        <section role="main" class="content-body">
            <header class="page-header">
                <h2>Ajouter un budget</h2>
            </header>

            <div class="col-md-12">
                <div class="row">
                    <div class="col">
                        <section class="card">
                            <div class="card-body">
                                <?php renderBudgetForm(); ?>
                            </div>
                        </section>
                    </div>
                </div>
            </div>

        </section>
    </div>

    <?php include('../PUBLIC/footer.php'); ?>
