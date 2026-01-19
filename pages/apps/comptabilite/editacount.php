<?php
include('../PUBLIC/connect.php');
session_start();

function cleanInput($data)
{
    $data = trim((string) $data);
    $data = stripslashes($data);
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function validateAccountData(array $data): array
{
    $errors = [];

    if ($data['nom_compte'] === '') {
        $errors[] = "Le nom du compte est requis";
    }

    if ($data['code'] === '') {
        $errors[] = "Le code du compte est requis";
    }

    if (!in_array($data['types'], ['Espèce', 'Chèque', 'Mobile'], true)) {
        $errors[] = "Type de règlement invalide";
    }

    if ($data['taux'] === '' || !is_numeric($data['taux']) || (float) $data['taux'] < 0) {
        $errors[] = "Le taux doit être un nombre positif";
    }

    if (!in_array($data['defaut'], ['0', '1'], true)) {
        $errors[] = "Valeur de confidentialité invalide";
    }

    if (!in_array($data['pour'], ['1', '2'], true)) {
        $errors[] = "Valeur 'pour' invalide";
    }

    return $errors;
}

function jsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

if ($isPost) {
    $idCompte = (int)($_POST['id_compte'] ?? 0);
    if ($idCompte <= 0) {
        jsonResponse(['success' => false, 'errors' => ['Compte invalide.']], 422);
    }

    $formData = [
        'nom_compte' => cleanInput($_POST['nom_compte'] ?? ''),
        'types' => cleanInput($_POST['types'] ?? ''),
        'code' => cleanInput($_POST['code'] ?? ''),
        'taux' => cleanInput($_POST['taux'] ?? '0'),
        'defaut' => cleanInput($_POST['defaut'] ?? ''),
        'pour' => cleanInput($_POST['pour'] ?? ''),
    ];

    $errors = validateAccountData($formData);
    if (!empty($errors)) {
        jsonResponse(['success' => false, 'errors' => $errors], 422);
    }

    try {
        $bdd->beginTransaction();

        $stExists = $bdd->prepare('SELECT COUNT(*) FROM comptes WHERE (nom_compte = ? OR code = ?) AND id_compte <> ?');
        $stExists->execute([$formData['nom_compte'], $formData['code'], $idCompte]);
        if ((int)$stExists->fetchColumn() > 0) {
            $bdd->rollBack();
            jsonResponse(['success' => false, 'errors' => ["Ce compte ou ce code existe déjà dans le système"]], 409);
        }

        // NB: ne pas modifier les montants (debit/credit/solde) depuis ce formulaire
        $st = $bdd->prepare('UPDATE comptes SET nom_compte = ?, types = ?, code = ?, taux = ?, defaut = ?, compte_pour = ? WHERE id_compte = ?');
        $st->execute([
            $formData['nom_compte'],
            $formData['types'],
            $formData['code'],
            $formData['taux'],
            $formData['defaut'],
            $formData['pour'],
            $idCompte,
        ]);

        $bdd->commit();

        jsonResponse(['success' => true, 'message' => 'Compte mis à jour avec succès']);
    } catch (Throwable $e) {
        if ($bdd->inTransaction()) {
            $bdd->rollBack();
        }
        error_log("Erreur lors de la mise à jour du compte (modal) : " . $e->getMessage());
        jsonResponse(['success' => false, 'errors' => ["Une erreur est survenue lors de la mise à jour du compte"]], 500);
    }
}

// GET: rendu HTML (contenu de modal)
$idCompte = (int)($_GET['id_compte'] ?? 0);
$codeLookup = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
$compte = null;
if ($idCompte > 0 || $codeLookup !== '') {
    try {
        if ($idCompte > 0) {
            $st = $bdd->prepare('SELECT id_compte, nom_compte, types, code, debit, credit, solde, taux, defaut, compte_pour FROM comptes WHERE id_compte = ? LIMIT 1');
            $st->execute([$idCompte]);
        } else {
            $st = $bdd->prepare('SELECT id_compte, nom_compte, types, code, debit, credit, solde, taux, defaut, compte_pour FROM comptes WHERE code = ? LIMIT 1');
            $st->execute([$codeLookup]);
        }
        $compte = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        error_log("Erreur chargement compte (edit modal): " . $e->getMessage());
        $compte = null;
    }
}

if (!$compte) {
    ?>
    <div class="alert alert-danger">Compte introuvable.</div>
    <?php
    exit;
}

$nom = htmlspecialchars((string)($compte['nom_compte'] ?? ''), ENT_QUOTES, 'UTF-8');
$types = (string)($compte['types'] ?? 'Espèce');
$code = htmlspecialchars((string)($compte['code'] ?? ''), ENT_QUOTES, 'UTF-8');
$debit = htmlspecialchars((string)($compte['debit'] ?? '0'), ENT_QUOTES, 'UTF-8');
$credit = htmlspecialchars((string)($compte['credit'] ?? '0'), ENT_QUOTES, 'UTF-8');
$solde = htmlspecialchars((string)($compte['solde'] ?? '0'), ENT_QUOTES, 'UTF-8');
$taux = htmlspecialchars((string)($compte['taux'] ?? '0'), ENT_QUOTES, 'UTF-8');
$defaut = (string)($compte['defaut'] ?? '1');
$pour = (string)($compte['compte_pour'] ?? '1');
?>

<div id="addCompteErrors" class="alert alert-danger" style="display:none;"></div>

<form id="editCompteForm" method="post" action="editacount.php" novalidate>
    <input type="hidden" name="id_compte" value="<?php echo (int)$compte['id_compte']; ?>">

    <div class="row form-group pb-3">
        <div class="col-md-4">
            <label class="col-form-label" for="nom_compte">Nom du compte</label>
            <input type="text" name="nom_compte" id="nom_compte" class="form-control" value="<?php echo $nom; ?>" required>
        </div>
        <div class="col-md-2">
            <label class="col-form-label" for="types">Type</label>
            <select class="form-control populate" name="types" id="types" required>
                <option value="Espèce" <?php echo ($types === 'Espèce') ? 'selected' : ''; ?>>Espèce</option>
                <option value="Chèque" <?php echo ($types === 'Chèque') ? 'selected' : ''; ?>>Chèque</option>
                <option value="Mobile" <?php echo ($types === 'Mobile') ? 'selected' : ''; ?>>Mobile</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="col-form-label" for="code">Code compte</label>
            <input type="text" class="form-control" name="code" id="code" value="<?php echo $code; ?>" required>
        </div>
    </div>

    <div class="row form-group pb-3">
        <div class="col-md-4">
            <label class="col-form-label">Débit</label>
            <input type="text" class="form-control" value="<?php echo $debit; ?>" disabled>
        </div>
        <div class="col-md-4">
            <label class="col-form-label">Crédit</label>
            <input type="text" class="form-control" value="<?php echo $credit; ?>" disabled>
        </div>
        <div class="col-md-4">
            <label class="col-form-label">Solde</label>
            <input type="text" class="form-control" value="<?php echo $solde; ?>" disabled>
        </div>
    </div>

    <div class="row form-group pb-3">
        <div class="col-md-3">
            <label class="col-form-label" for="defaut">Confidentialité</label>
            <select class="form-control populate" name="defaut" id="defaut" required>
                <option value="1" <?php echo ($defaut === '1') ? 'selected' : ''; ?>>Public</option>
                <option value="0" <?php echo ($defaut === '0') ? 'selected' : ''; ?>>Privé</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="col-form-label" for="taux">Taux si Paiement Marchand sinon 0</label>
            <input type="number" step="0.1" class="form-control" name="taux" id="taux" value="<?php echo $taux; ?>" placeholder="exemple : 1.0 sans le %" required>
        </div>
        <div class="col-md-3">
            <label class="col-form-label" for="pour">Pour</label>
            <select class="form-control populate" name="pour" id="pour" required>
                <option value="1" <?php echo ($pour === '1') ? 'selected' : ''; ?>>Clinique</option>
                <option value="2" <?php echo ($pour === '2') ? 'selected' : ''; ?>>Boutique</option>
            </select>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-default" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-primary" id="addCompteSubmitBtn">Enregistrer</button>
    </div>
</form>
