<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
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

function validateAssurance(array $data): array
{
	$errors = [];

	if ($data['assurance'] === '') {
		$errors[] = "Le nom de l'assureur est requis.";
	}

	if ($data['contrat'] === '') {
		$errors[] = "Le N° contrat est requis.";
	}

	if ($data['email'] === '') {
		$errors[] = "Le courriel est requis.";
	} elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
		$errors[] = "Adresse e-mail invalide.";
	}

	return $errors;
}

function renderAssuranceForm(): void
{
	?>
	<div id="addAssuranceErrors" class="alert alert-danger" style="display:none;"></div>
	<div id="addAssuranceSuccess" class="alert alert-success" style="display:none;"></div>

	<form id="addAssuranceForm" method="post" action="addassurance.php" novalidate>
		<div class="row form-group pb-3">
			<div class="col-md-6">
				<label class="col-form-label" for="assurance">Assureur</label>
				<input type="text" class="form-control" id="assurance" name="assurance" required>
			</div>
			<div class="col-md-6">
				<label class="col-form-label" for="contrat">N° Contrat</label>
				<input type="text" class="form-control" id="contrat" name="contrat" required>
			</div>
		</div>

		<div class="row form-group pb-3">
			<div class="col-md-4">
				<label class="col-form-label" for="type_assurance">Type</label>
                <select class="form-control" name="type_assurance" id="type_assurance" onchange="document.getElementById('type_assurance').value=this.value;">
                    <option value="">-- Sélectionner un type --</option>
                    <option value="Mutuelle">Mutuelle</option>
                    <option value="Entreprise">Entreprise</option>
                    <option value="Gouvernement">Gouvernement</option>
                </select>
			</div>
			<div class="col-md-3">
				<label class="col-form-label" for="telephone">Téléphone</label>
				<input type="text" class="form-control" id="telephone" name="telephone">
			</div>
			<div class="col-md-5">
				<label class="col-form-label" for="email">Courriel</label>
				<input type="email" class="form-control" id="email" name="email" required>
			</div>
		</div>

		<div class="row form-group pb-3">
			<div class="col-md-8">
				<label class="col-form-label" for="adresse">Adresse</label>
				<input type="text" class="form-control" id="adresse" name="adresse">
			</div>
			<div class="col-md-4 d-flex align-items-end">
				<div class="form-check">
					<input class="form-check-input" type="checkbox" value="1" id="activer_immediatement" name="activer_immediatement" checked>
					<label class="form-check-label" for="activer_immediatement">Activer immédiatement</label>
				</div>
			</div>
		</div>

		<div class="d-flex justify-content-end gap-2">
			<button type="button" class="btn btn-default" data-bs-dismiss="modal">Annuler</button>
			<button type="submit" class="btn btn-primary" id="addAssuranceSubmitBtn">Enregistrer</button>
		</div>
	</form>
	<?php
}

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$isAjax = (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');

if ($isPost) {
	$assurance = cleanInput($_POST['assurance'] ?? '');
	$contrat = cleanInput($_POST['contrat'] ?? '');
	$typeAssurance = cleanInput($_POST['type_assurance'] ?? '');
	$telephone = cleanInput($_POST['telephone'] ?? '');
	$email = cleanInput($_POST['email'] ?? '');
	$adresse = cleanInput($_POST['adresse'] ?? '');
	$activer = (int)($_POST['activer_immediatement'] ?? 0) === 1;

	$data = [
		'assurance' => $assurance,
		'contrat' => $contrat,
		'type_assurance' => $typeAssurance,
		'telephone' => $telephone,
		'email' => $email,
		'adresse' => $adresse,
	];

	$errors = validateAssurance($data);
	if (!empty($errors)) {
		jsonResponse(['success' => false, 'errors' => $errors], 422);
	}

	try {
		$bdd->beginTransaction();

		// Empêcher doublon par nom (hors supprimés)
		$stDup = $bdd->prepare('SELECT status FROM assurances WHERE assurance = ? ORDER BY date_creation DESC LIMIT 1');
		$stDup->execute([$assurance]);
		$dup = $stDup->fetch(PDO::FETCH_ASSOC);
		if ($dup && (int)($dup['status'] ?? 0) !== 3) {
			$bdd->rollBack();
			jsonResponse(['success' => false, 'errors' => ["Cet assureur existe déjà."]], 409);
		}

		$status = $activer ? 1 : 0;

		// solde est GENERATED, date_creation auto => ne pas les renseigner
		$st = $bdd->prepare('INSERT INTO assurances (assurance, contrat, adresse, telephone, email, type_assurance, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
		$st->execute([
			$assurance,
			$contrat,
			$adresse !== '' ? $adresse : null,
			$telephone !== '' ? $telephone : null,
			$email,
			$typeAssurance !== '' ? $typeAssurance : null,
			$status,
		]);

		$bdd->commit();
		jsonResponse(['success' => true, 'message' => 'Assureur ajouté avec succès']);
	} catch (Throwable $e) {
		if ($bdd->inTransaction()) {
			$bdd->rollBack();
		}
		error_log('Erreur ajout assurance: ' . $e->getMessage());
		jsonResponse(['success' => false, 'errors' => ["Une erreur est survenue lors de l'ajout de l'assureur"]], 500);
	}
}

if ($isAjax) {
	renderAssuranceForm();
	exit;
}

include('../PUBLIC/header.php');
?>

<body>
<section class="body">
<?php require('../PUBLIC/navbarmenu.php'); ?>
<div class="inner-wrapper">
	<section role="main" class="content-body">
		<header class="page-header"><h2>Ajouter un assureur</h2></header>
		<div class="col-md-12">
			<section class="card">
				<div class="card-body">
					<?php renderAssuranceForm(); ?>
				</div>
			</section>
		</div>
	</section>
</div>
<?php include('../PUBLIC/footer.php'); ?>

