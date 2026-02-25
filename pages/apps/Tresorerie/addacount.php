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

    if ($data['disponibilite'] === '' || !is_numeric($data['disponibilite'])) {
        $errors[] = "La disponibilité doit être un nombre";
    }

    if ($data['taux'] === '' || !is_numeric($data['taux']) || (float) $data['taux'] < 0) {
        $errors[] = "Le taux doit être un nombre positif";
    }

    if ($data['types'] !== 'Mobile' && (float) $data['taux'] != 0.0) {
        // On reste permissif, mais on évite les surprises côté saisie
        // (le libellé indique 0 si non marchand)
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
    $formData = [
        'nom_compte' => cleanInput($_POST['nom_compte'] ?? ''),
        'types' => cleanInput($_POST['types'] ?? ''),
        'code' => cleanInput($_POST['code'] ?? ''),
        'disponibilite' => cleanInput($_POST['disponibilite'] ?? ''),
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

        $req1 = $bdd->prepare('SELECT COUNT(*) FROM comptes WHERE nom_compte = ? OR code = ?');
        $req1->execute([$formData['nom_compte'], $formData['code']]);
        $compte_existe = (int) $req1->fetchColumn() > 0;

        if ($compte_existe) {
            $bdd->rollBack();
            jsonResponse([
                'success' => false,
                'errors' => ["Ce compte ou ce code existe déjà dans le système"],
            ], 409);
        }

        $req = $bdd->prepare('INSERT INTO comptes (nom_compte, types, code, debit, taux, defaut, compte_pour) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $req->execute([
            $formData['nom_compte'],
            $formData['types'],
            $formData['code'],
            $formData['disponibilite'],
            $formData['taux'],
            $formData['defaut'],
            $formData['pour'],
        ]);

        $bdd->commit();

        jsonResponse([
            'success' => true,
            'message' => 'Compte ajouté avec succès',
        ]);
    } catch (Exception $e) {
        if ($bdd->inTransaction()) {
            $bdd->rollBack();
        }
        error_log("Erreur lors de l'ajout du compte (modal) : " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'errors' => ["Une erreur est survenue lors de l'ajout du compte"],
        ], 500);
    }
}

// GET: rendu HTML (contenu de modal)
?>
<div id="addCompteErrors" class="alert alert-danger" style="display:none;"></div>

<form id="addCompteForm" method="post" action="addacount.php" novalidate>
    <div class="row form-group pb-3">
        <div class="col-md-4">
            <label class="col-form-label" for="nom_compte">Nom du compte</label>
            <input type="text" name="nom_compte" id="nom_compte" class="form-control" required>
        </div>
        <div class="col-md-2">
            <label class="col-form-label" for="types">Type</label>
            <select class="form-control populate" name="types" id="types" required>
                <option value="Espèce">Espèce</option>
                <option value="Chèque">Chèque</option>
                <option value="Mobile">Mobile</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="col-form-label" for="code">Code compte</label>
            <input type="text" class="form-control" name="code" id="code" required>
        </div>
        <div class="col-md-4">
            <label class="col-form-label" for="disponibilite">Disponibilité actuelle</label>
            <input type="number" class="form-control" name="disponibilite" id="disponibilite" value="0" required>
        </div>
    </div>

    <div class="row form-group pb-3">
        <div class="col-md-3">
            <label class="col-form-label" for="defaut">Confidentialité</label>
            <select class="form-control populate" name="defaut" id="defaut" required>
                <option value="1">Public</option>
                <option value="0">Privé</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="col-form-label" for="taux">Taux si Paiement Marchand sinon 0</label>
            <input type="number" step="0.1" class="form-control" name="taux" id="taux" value="0" placeholder="exemple : 1.0 sans le %" required>
        </div>
        <div class="col-md-3">
            <label class="col-form-label" for="pour">Pour</label>
            <select class="form-control populate" name="pour" id="pour" required>
                <option value="1">Clinique</option>
                <option value="2">Boutique</option>
            </select>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-default" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-primary" id="addCompteSubmitBtn">Ajouter</button>
    </div>
</form>
