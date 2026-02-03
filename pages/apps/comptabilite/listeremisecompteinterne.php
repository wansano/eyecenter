<?php
require_once(__DIR__ . '/../public/connect.php');
require_once(__DIR__ . '/../public/fonction.php');
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

function h($value): string {
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function computeSolde(float $debit, float $credit): float {
	return $debit - $credit;
}

function buildPreuveUrl(string $preuvePath): ?string {
	$preuvePath = trim($preuvePath);
	if ($preuvePath === '') {
		return null;
	}
	$clean = ltrim($preuvePath, '/');
	// Si un chemin complet a été stocké (ex: pages/apps/comptabilite/uploads/remise/xxx)
	if (str_contains($clean, 'pages/apps/comptabilite/')) {
		$pos = strpos($clean, 'pages/apps/comptabilite/');
		if ($pos !== false) {
			$clean = substr($clean, $pos + strlen('pages/apps/comptabilite/'));
		}
	}
	// Si un chemin complet sous public a été stocké (ex: pages/apps/public/uploads/remises/xxx)
	if (str_contains($clean, 'pages/apps/public/')) {
		$pos = strpos($clean, 'pages/apps/public/');
		if ($pos !== false) {
			$clean = substr($clean, $pos + strlen('pages/apps/public/'));
			$clean = '../public/' . ltrim($clean, '/');
			return $clean;
		}
	}
	// Nouveau stockage: dans le module comptabilité
	if (str_starts_with($clean, 'uploads/remise/')) {
		return './' . $clean;
	}
	// Ancien stockage: sous public/
	if (str_starts_with($clean, 'uploads/remises/')) {
		return '../public/' . $clean;
	}
	// Par défaut, on tente tel quel (chemin relatif)
	return $clean;
}

function remiseTableHasColumn(PDO $bdd, string $column): bool {
	try {
		$st = $bdd->prepare("SHOW COLUMNS FROM remise_de_compte LIKE ?");
		$st->execute([$column]);
		return (bool)$st->fetch(PDO::FETCH_ASSOC);
	} catch (Throwable $e) {
		return false;
	}
}

function ensureRemisePreuveColumn(PDO $bdd): void {
	if (remiseTableHasColumn($bdd, 'preuve')) {
		return;
	}
	try {
		// Peut échouer si l'utilisateur DB n'a pas les droits ALTER; dans ce cas on garde un comportement dégradé.
		$bdd->exec("ALTER TABLE remise_de_compte ADD COLUMN preuve VARCHAR(255) NULL");
	} catch (Throwable $e) {
		// pas de throw volontaire
		error_log('[listeremisecompteinterne] ensure preuve column => ' . $e->getMessage());
	}
}

$allowedTypeRemise = ['approvisionnement', 'paiement'];

// Le mode de paiement dépend des types disponibles dans la table comptes
$allowedModePaiement = [];
try {
	$st = $bdd->query("SELECT DISTINCT types FROM comptes WHERE types IS NOT NULL AND types <> '' ORDER BY types");
	$allowedModePaiement = $st->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
	error_log('[listeremisecompteinterne] mode_paiement types => ' . $e->getMessage());
}
if (!$allowedModePaiement) {
	// fallback si la table ne contient pas de types exploitable
	$allowedModePaiement = ['Espèce', 'Chèque'];
}

// PRG: messages
$flashSuccess = null;
$flashError = null;
if (!empty($_GET['ok']) && $_GET['ok'] === '1') {
	$flashSuccess = 'Remise de compte effectuée avec succès.';
}
if (!empty($_GET['err'])) {
	if ($_GET['err'] === 'exists') {
		$flashError = 'La remise a déjà été effectuée (référence déjà utilisée).';
	} elseif ($_GET['err'] === 'solde') {
		$flashError = "Remise non effectuée : le montant saisi est supérieur au solde du compte débité.";
	} elseif ($_GET['err'] === 'not_allowed') {
		$flashError = 'Modification impossible : une remise est modifiable uniquement dans les 24h qui suivent sa création.';
	} elseif ($_GET['err'] === 'input') {
		$flashError = 'Veuillez renseigner correctement tous les champs.';
	} elseif ($_GET['err'] === 'upload') {
		$flashError = "Erreur d'upload : dossier non accessible ou fichier invalide.";
		if (!empty($_GET['code'])) {
			$flashError .= ' (code: ' . h($_GET['code']) . ')';
		}
	} elseif ($_GET['err'] === 'server') {
		$flashError = 'Erreur serveur. Veuillez réessayer ou consulter les logs.';
		if (!empty($_GET['code'])) {
			$flashError .= ' (code: ' . h($_GET['code']) . ')';
		}
	} else {
		$flashError = 'Une erreur est survenue.';
	}
}

// Ajout d'une remise (inspiré de oldFile/addremiseaccountin.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_remise'])) {
	$idRemise = (int)($_POST['id_remise'] ?? 0);
	$idEmploye = (int)($_POST['id_employe'] ?? 0);
	$dateRemise = trim((string)($_POST['date_remise'] ?? ''));
	$montant = (float)($_POST['montant'] ?? 0);
	$typeRemise = (string)($_POST['type_remise'] ?? '');
	$modePaiement = (string)($_POST['mode_paiement'] ?? '');
	$reference = trim((string)($_POST['reference'] ?? ''));
	$idCompteCredite = (int)($_POST['id_compte'] ?? 0);
	$idCompteDebite = (int)($_POST['id_compte2'] ?? 0);
	$notes = trim((string)($_POST['notes'] ?? ''));
	$preuveCurrent = trim((string)($_POST['preuve_current'] ?? ''));

	$preuvePath = null;
	if (isset($_FILES['preuve']) && is_array($_FILES['preuve']) && ($_FILES['preuve']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
		if (($_FILES['preuve']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
			$errCode = bin2hex(random_bytes(4));
			error_log('[listeremisecompteinterne] upload(modif) code=' . $errCode . ' error=' . (int)($_FILES['preuve']['error'] ?? -1));
			header('Location: listeremisecompteinterne.php?err=upload&code=' . rawurlencode($errCode));
			exit;
		}
		$maxBytes = 10 * 1024 * 1024;
		if ((int)($_FILES['preuve']['size'] ?? 0) <= 0 || (int)($_FILES['preuve']['size'] ?? 0) > $maxBytes) {
			$errCode = bin2hex(random_bytes(4));
			error_log('[listeremisecompteinterne] upload(modif) code=' . $errCode . ' invalid size=' . (int)($_FILES['preuve']['size'] ?? 0));
			header('Location: listeremisecompteinterne.php?err=upload&code=' . rawurlencode($errCode));
			exit;
		}
		$originalName = (string)($_FILES['preuve']['name'] ?? '');
		$extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
		$allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
		if (!in_array($extension, $allowedExt, true)) {
			$errCode = bin2hex(random_bytes(4));
			error_log('[listeremisecompteinterne] upload(modif) code=' . $errCode . ' invalid ext=' . $extension);
			header('Location: listeremisecompteinterne.php?err=upload&code=' . rawurlencode($errCode));
			exit;
		}
		$uploadDir = __DIR__ . '/uploads/remise';
		if (!is_dir($uploadDir)) {
			@mkdir($uploadDir, 0777, true);
		}
		if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
			$errCode = bin2hex(random_bytes(4));
			error_log('[listeremisecompteinterne] upload(modif) code=' . $errCode . ' uploadDir not writable: ' . $uploadDir);
			header('Location: listeremisecompteinterne.php?err=upload&code=' . rawurlencode($errCode));
			exit;
		}
		$safeRef = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $reference);
		$filename = 'remise_' . date('Ymd_His') . '_' . $safeRef . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
		$target = $uploadDir . DIRECTORY_SEPARATOR . $filename;
		if (!@move_uploaded_file((string)($_FILES['preuve']['tmp_name'] ?? ''), $target)) {
			$errCode = bin2hex(random_bytes(4));
			error_log('[listeremisecompteinterne] upload(modif) code=' . $errCode . ' move_uploaded_file failed target=' . $target);
			header('Location: listeremisecompteinterne.php?err=upload&code=' . rawurlencode($errCode));
			exit;
		}
		$preuvePath = 'uploads/remise/' . $filename;
	}

	$validDate = false;
	if ($dateRemise !== '') {
		$dt = DateTime::createFromFormat('Y-m-d', $dateRemise);
		$validDate = ($dt instanceof DateTime) && ($dt->format('Y-m-d') === $dateRemise);
	}

	if ($idRemise <= 0 || $idEmploye <= 0 || !$validDate || $montant <= 0 || $reference === '' || $idCompteCredite <= 0 || $idCompteDebite <= 0 || $idCompteCredite === $idCompteDebite) {
		header('Location: listeremisecompteinterne.php?err=input');
		exit;
	}
	if (mb_strlen($reference) > 50) {
		header('Location: listeremisecompteinterne.php?err=input');
		exit;
	}
	if (!in_array($typeRemise, $allowedTypeRemise, true) || !in_array($modePaiement, $allowedModePaiement, true)) {
		header('Location: listeremisecompteinterne.php?err=input');
		exit;
	}

	try {
		$bdd->beginTransaction();

		$st = $bdd->prepare('SELECT * FROM remise_de_compte WHERE id_remise = ? FOR UPDATE');
		$st->execute([$idRemise]);
		$old = $st->fetch(PDO::FETCH_ASSOC);
		if (!$old) {
			$bdd->rollBack();
			header('Location: listeremisecompteinterne.php?err=input');
			exit;
		}

		// Autoriser la modification uniquement le jour de la remise (sécurité serveur)
		$now = new DateTimeImmutable('now');
		$oldBaseRaw = trim((string)($old['date_creation'] ?? ''));
		if ($oldBaseRaw === '') {
			$oldBaseRaw = trim((string)($old['date_remise'] ?? ''));
		}
		try {
			$oldDt = new DateTimeImmutable($oldBaseRaw !== '' ? $oldBaseRaw : '');
			$deadline = $oldDt->modify('+24 hours');
			$canEdit = ($deadline instanceof DateTimeImmutable) && ($now <= $deadline);
		} catch (Throwable $e) {
			$canEdit = false;
		}
		if (!$canEdit) {
			$bdd->rollBack();
			header('Location: listeremisecompteinterne.php?err=not_allowed');
			exit;
		}

		if ((string)($old['reference'] ?? '') !== $reference) {
			$st = $bdd->prepare('SELECT COUNT(*) FROM remise_de_compte WHERE reference = ? AND id_remise <> ?');
			$st->execute([$reference, $idRemise]);
			if ((int)$st->fetchColumn() > 0) {
				$bdd->rollBack();
				header('Location: listeremisecompteinterne.php?err=exists');
				exit;
			}
		}

		$oldMontant = (float)($old['montant'] ?? 0);
		$oldCred = (int)($old['id_compte'] ?? 0);
		$oldDeb = (int)($old['id_compte2'] ?? 0);

		$accountIds = array_values(array_unique(array_filter([$oldCred, $oldDeb, $idCompteCredite, $idCompteDebite])));
		$accounts = [];
		$stAcc = $bdd->prepare('SELECT id_compte, debit, credit FROM comptes WHERE id_compte = ? FOR UPDATE');
		foreach ($accountIds as $aid) {
			$stAcc->execute([$aid]);
			$row = $stAcc->fetch(PDO::FETCH_ASSOC);
			if (!$row) {
				$bdd->rollBack();
				header('Location: listeremisecompteinterne.php?err=input');
				exit;
			}
			$accounts[(int)$aid] = [
				'debit' => (float)($row['debit'] ?? 0),
				'credit' => (float)($row['credit'] ?? 0),
			];
		}

		// Annule l'ancienne remise
		if ($oldMontant > 0 && $oldCred > 0 && $oldDeb > 0) {
			$accounts[$oldDeb]['debit'] += $oldMontant;
			$accounts[$oldDeb]['credit'] -= $oldMontant;
			$accounts[$oldCred]['debit'] -= $oldMontant;
			if ($accounts[$oldDeb]['credit'] < 0 || $accounts[$oldCred]['debit'] < 0) {
				$bdd->rollBack();
				header('Location: listeremisecompteinterne.php?err=server');
				exit;
			}
		}

		// Applique la nouvelle remise
		if ($montant > $accounts[$idCompteDebite]['debit']) {
			$bdd->rollBack();
			header('Location: listeremisecompteinterne.php?err=solde');
			exit;
		}
		$accounts[$idCompteDebite]['debit'] -= $montant;
		$accounts[$idCompteDebite]['credit'] += $montant;
		$accounts[$idCompteCredite]['debit'] += $montant;

		$stUpd = $bdd->prepare('UPDATE comptes SET debit = ?, credit = ?, solde = ?, date_update = CURRENT_TIMESTAMP() WHERE id_compte = ?');
		foreach ($accounts as $aid => $vals) {
			$debitNew = (float)$vals['debit'];
			$creditNew = (float)$vals['credit'];
			$stUpd->execute([$debitNew, $creditNew, computeSolde($debitNew, $creditNew), (int)$aid]);
		}

		$finalPreuve = ($preuvePath !== null) ? $preuvePath : ($preuveCurrent !== '' ? $preuveCurrent : null);
		try {
			$st = $bdd->prepare('UPDATE remise_de_compte SET id_employe=?, date_remise=?, montant=?, type_remise=?, mode_paiement=?, reference=?, id_compte=?, id_compte2=?, notes=?, preuve=? WHERE id_remise=?');
			$st->execute([$idEmploye, $dateRemise, $montant, $typeRemise, $modePaiement, $reference, $idCompteCredite, $idCompteDebite, $notes, $finalPreuve, $idRemise]);
		} catch (Throwable $e) {
			// Fallback si la colonne preuve n'existe pas
			$st = $bdd->prepare('UPDATE remise_de_compte SET id_employe=?, date_remise=?, montant=?, type_remise=?, mode_paiement=?, reference=?, id_compte=?, id_compte2=?, notes=? WHERE id_remise=?');
			$st->execute([$idEmploye, $dateRemise, $montant, $typeRemise, $modePaiement, $reference, $idCompteCredite, $idCompteDebite, $notes, $idRemise]);
		}

		$bdd->commit();
		header('Location: listeremisecompteinterne.php?ok=1');
		exit;
	} catch (Throwable $e) {
		if ($bdd->inTransaction()) {
			$bdd->rollBack();
		}
		$errCode = bin2hex(random_bytes(4));
		error_log('[listeremisecompteinterne] modif_remise code=' . $errCode . ' => ' . $e->getMessage());
		header('Location: listeremisecompteinterne.php?err=server&code=' . rawurlencode($errCode));
		exit;
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_remise'])) {
	$idEmploye = (int)($_POST['id_employe'] ?? 0);
	$dateRemise = trim((string)($_POST['date_remise'] ?? ''));
	$montant = (float)($_POST['montant'] ?? 0);
	$typeRemise = (string)($_POST['type_remise'] ?? '');
	$modePaiement = (string)($_POST['mode_paiement'] ?? '');
	$reference = trim((string)($_POST['reference'] ?? ''));
	$idCompteCredite = (int)($_POST['id_compte'] ?? 0);
	$idCompteDebite = (int)($_POST['id_compte2'] ?? 0);
	$notes = trim((string)($_POST['notes'] ?? ''));

	$preuvePath = null; // ex: uploads/remises/xxx.pdf
	if (isset($_FILES['preuve']) && is_array($_FILES['preuve']) && ($_FILES['preuve']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
		if (($_FILES['preuve']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
			$errCode = bin2hex(random_bytes(4));
			error_log('[listeremisecompteinterne] upload code=' . $errCode . ' error=' . (int)($_FILES['preuve']['error'] ?? -1));
			header('Location: listeremisecompteinterne.php?err=upload&code=' . rawurlencode($errCode));
			exit;
		}
		$maxBytes = 10 * 1024 * 1024;
		if ((int)($_FILES['preuve']['size'] ?? 0) <= 0 || (int)($_FILES['preuve']['size'] ?? 0) > $maxBytes) {
			$errCode = bin2hex(random_bytes(4));
			error_log('[listeremisecompteinterne] upload code=' . $errCode . ' invalid size=' . (int)($_FILES['preuve']['size'] ?? 0));
			header('Location: listeremisecompteinterne.php?err=upload&code=' . rawurlencode($errCode));
			exit;
		}
		$originalName = (string)($_FILES['preuve']['name'] ?? '');
		$extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
		$allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
		if (!in_array($extension, $allowedExt, true)) {
			$errCode = bin2hex(random_bytes(4));
			error_log('[listeremisecompteinterne] upload code=' . $errCode . ' invalid ext=' . $extension);
			header('Location: listeremisecompteinterne.php?err=upload&code=' . rawurlencode($errCode));
			exit;
		}

		// Dossier demandé: pages/apps/comptabilite/uploads/remise
		$uploadDir = __DIR__ . '/uploads/remise';
		if (!is_dir($uploadDir)) {
			@mkdir($uploadDir, 0777, true);
		}
		if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
			$errCode = bin2hex(random_bytes(4));
			error_log('[listeremisecompteinterne] upload code=' . $errCode . ' uploadDir not writable: ' . $uploadDir);
			header('Location: listeremisecompteinterne.php?err=upload&code=' . rawurlencode($errCode));
			exit;
		}
		$safeRef = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $reference);
		$filename = 'remise_' . date('Ymd_His') . '_' . $safeRef . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
		$target = $uploadDir . DIRECTORY_SEPARATOR . $filename;
		if (!@move_uploaded_file((string)($_FILES['preuve']['tmp_name'] ?? ''), $target)) {
			$errCode = bin2hex(random_bytes(4));
			error_log('[listeremisecompteinterne] upload code=' . $errCode . ' move_uploaded_file failed target=' . $target);
			header('Location: listeremisecompteinterne.php?err=upload&code=' . rawurlencode($errCode));
			exit;
		}
		$preuvePath = 'uploads/remise/' . $filename;
	}

	if ($preuvePath !== null) {
		ensureRemisePreuveColumn($bdd);
	}

	$validDate = false;
	if ($dateRemise !== '') {
		$dt = DateTime::createFromFormat('Y-m-d', $dateRemise);
		$validDate = ($dt instanceof DateTime) && ($dt->format('Y-m-d') === $dateRemise);
	}

	if ($idEmploye <= 0 || !$validDate || $montant <= 0 || $reference === '' || $idCompteCredite <= 0 || $idCompteDebite <= 0 || $idCompteCredite === $idCompteDebite) {
		header('Location: listeremisecompteinterne.php?err=input');
		exit;
	}
	if (mb_strlen($reference) > 50) {
		header('Location: listeremisecompteinterne.php?err=input');
		exit;
	}
	if (!in_array($typeRemise, $allowedTypeRemise, true) || !in_array($modePaiement, $allowedModePaiement, true)) {
		header('Location: listeremisecompteinterne.php?err=input');
		exit;
	}

	try {
		$st = $bdd->prepare('SELECT COUNT(*) FROM remise_de_compte WHERE reference = ?');
		$st->execute([$reference]);
		if ((int)$st->fetchColumn() > 0) {
			header('Location: listeremisecompteinterne.php?err=exists');
			exit;
		}

		$bdd->beginTransaction();

		$st = $bdd->prepare('SELECT debit, credit FROM comptes WHERE id_compte = ? FOR UPDATE');
		$st->execute([$idCompteCredite]);
		$compteCredite = $st->fetch(PDO::FETCH_ASSOC);

		$st = $bdd->prepare('SELECT debit, credit FROM comptes WHERE id_compte = ? FOR UPDATE');
		$st->execute([$idCompteDebite]);
		$compteDebite = $st->fetch(PDO::FETCH_ASSOC);

		if (!$compteCredite || !$compteDebite) {
			$bdd->rollBack();
			header('Location: listeremisecompteinterne.php?err=input');
			exit;
		}

		$soldeDebite = (float)$compteDebite['debit'];
		$creditDebite = (float)$compteDebite['credit'];
		$soldeCredite = (float)$compteCredite['debit'];
		$creditCredite = (float)$compteCredite['credit'];

		if ($montant > $soldeDebite) {
			$bdd->rollBack();
			header('Location: listeremisecompteinterne.php?err=solde');
			exit;
		}

		// Même logique que le fichier old: on diminue le débit (solde) du compte débité, et on incrémente son crédit.
		$nouveauDebitCompteDebite = $soldeDebite - $montant;
		$nouveauCreditCompteDebite = $creditDebite + $montant;
		$st = $bdd->prepare('UPDATE comptes SET debit = ?, credit = ?, solde = ?, date_update = CURRENT_TIMESTAMP() WHERE id_compte = ?');
		$st->execute([$nouveauDebitCompteDebite, $nouveauCreditCompteDebite, computeSolde($nouveauDebitCompteDebite, $nouveauCreditCompteDebite), $idCompteDebite]);

		// Sur le compte crédité, on incrémente le débit (solde)
		$nouveauDebitCompteCredite = $soldeCredite + $montant;
		$st = $bdd->prepare('UPDATE comptes SET debit = ?, solde = ?, date_update = CURRENT_TIMESTAMP() WHERE id_compte = ?');
		$st->execute([$nouveauDebitCompteCredite, computeSolde($nouveauDebitCompteCredite, $creditCredite), $idCompteCredite]);

		// Insert: on tente avec preuve, puis fallback sans la colonne si nécessaire
		try {
			$st = $bdd->prepare('INSERT INTO remise_de_compte (id_employe, date_remise, montant, type_remise, mode_paiement, reference, id_compte, id_compte2, notes, preuve) VALUES(?,?,?,?,?,?,?,?,?,?)');
			$st->execute([$idEmploye, $dateRemise, $montant, $typeRemise, $modePaiement, $reference, $idCompteCredite, $idCompteDebite, $notes, $preuvePath]);
		} catch (Throwable $e) {
			$st = $bdd->prepare('INSERT INTO remise_de_compte (id_employe, date_remise, montant, type_remise, mode_paiement, reference, id_compte, id_compte2, notes) VALUES(?,?,?,?,?,?,?,?,?)');
			$st->execute([$idEmploye, $dateRemise, $montant, $typeRemise, $modePaiement, $reference, $idCompteCredite, $idCompteDebite, $notes]);
		}

		$bdd->commit();
		header('Location: listeremisecompteinterne.php?ok=1');
		exit;
	} catch (Throwable $e) {
		if ($bdd->inTransaction()) {
			$bdd->rollBack();
		}
		$errCode = bin2hex(random_bytes(4));
		error_log('[listeremisecompteinterne] ajout_remise code=' . $errCode . ' => ' . $e->getMessage());
		header('Location: listeremisecompteinterne.php?err=server&code=' . rawurlencode($errCode));
		exit;
	}
}

$remises = [];
try {
	$sql = "
		SELECT r.*, e.nomEmploye AS nom_employe,
			cdeb.nom_compte AS compte_debite_nom,
			ccre.nom_compte AS compte_credite_nom
		FROM remise_de_compte r
		LEFT JOIN employes e ON e.id_employe = r.id_employe
		LEFT JOIN comptes cdeb ON cdeb.id_compte = r.id_compte2
		LEFT JOIN comptes ccre ON ccre.id_compte = r.id_compte
		WHERE r.id_employe IS NOT NULL AND r.id_employe <> 0
		ORDER BY r.id_remise DESC
	";
	$st = $bdd->prepare($sql);
	$st->execute();
	$remises = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	error_log('[listeremisecompteinterne] list => ' . $e->getMessage());
}

$employes = [];
$comptes = [];
try {
	$st = $bdd->prepare('SELECT id_employe, nomEmploye, nomEmploye AS employe_nom FROM employes WHERE status = ? ORDER BY nomEmploye');
	$st->execute([1]);
	$employes = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	error_log('[listeremisecompteinterne] employes => ' . $e->getMessage());
}
try {
	$st = $bdd->prepare('SELECT id_compte, nom_compte, types FROM comptes ORDER BY nom_compte');
	$st->execute();
	$comptes = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	error_log('[listeremisecompteinterne] comptes => ' . $e->getMessage());
}

include(__DIR__ . '/../public/header.php');
?>

	<body>
		<section class="body">

			<?php require(__DIR__ . '/../public/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Liste des remises de comptes</h2>
					</header>

					<!-- start: page -->
					<div class="col-md-12">
						<div class="row">
							<div class="col">
								<section class="card">
									<div class="card-body">
										<button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modalAjouterRemise">
										Ajouter une remise
										</button>
										<?php if ($flashSuccess): ?>
											<div class="alert alert-success mb-3"><?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
										<?php endif; ?>
										<?php if ($flashError): ?>
											<div class="alert alert-danger mb-3"><?php echo htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?></div>
										<?php endif; ?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">

											<thead>
												<tr>
                                                    <th>DATE REMISE</th>
													<th>EMPLOYE</th>
													<th>MONTANT</th>
                                                    <th>TYPE REMISE</th>
                                                    <th>MODE PAIEMENT</th>
													<th>REFERENCE</th>
													<th>COMPTE DEBITE</th>
                                                    <th>COMPTE CREDITE</th>
													<th>PREUVE</th>
													<th>ACTION</th>
												</tr>
											</thead>
											<tbody>
											<?php $nowTs = time(); ?>
											<?php foreach ($remises as $r): ?>
												<tr>
													<td><?php echo htmlspecialchars((string)($r['date_remise'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
													<td><?php echo htmlspecialchars((string)($r['nom_employe'] ?? $r['nomEmploye'] ?? par((int)($r['id_employe'] ?? 0))), ENT_QUOTES, 'UTF-8'); ?></td>
													<td><?php echo number_format((float)($r['montant'] ?? 0), 0, ',', ' ') . ' GNF'; ?></td>
													<td><?php echo htmlspecialchars((string)($r['type_remise'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
													<td><?php echo htmlspecialchars((string)($r['mode_paiement'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
													<td><?php echo htmlspecialchars((string)($r['reference'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
													<td><?php echo htmlspecialchars((string)($r['compte_debite_nom'] ?? type_paiement((int)($r['id_compte2'] ?? 0))), ENT_QUOTES, 'UTF-8'); ?></td>
													<td><?php echo htmlspecialchars((string)($r['compte_credite_nom'] ?? type_paiement((int)($r['id_compte'] ?? 0))), ENT_QUOTES, 'UTF-8'); ?></td>
													<td>
														<?php
															$preuve = (string)($r['preuve'] ?? '');
															$url = buildPreuveUrl($preuve);
															if ($url !== null) {
																echo '<button type="button" class="btn btn-sm btn-outline-secondary js-open-preuve" data-bs-toggle="modal" data-bs-target="#modalPreuve" data-url="' . h($url) . '"><i class="fa fa-paperclip"></i> Voir</button>';
															} else {
																echo '<span class="text-muted">-</span>';
															}
														?>
													</td>
													<td>
														<?php
															$remiseDateRaw = trim((string)($r['date_creation'] ?? ''));
															if ($remiseDateRaw === '') {
																$remiseDateRaw = trim((string)($r['date_remise'] ?? ''));
															}
															$canEdit = false;
															if ($remiseDateRaw !== '') {
																try {
																	$dt = new DateTimeImmutable($remiseDateRaw);
																	$deadline = $dt->modify('+24 hours');
																	$canEdit = ($deadline instanceof DateTimeImmutable) && ($nowTs <= $deadline->getTimestamp());
																} catch (Throwable $e) {
																	$canEdit = false;
																}
															}
															$bordUrl = '../impression/imprimer_borderau.php?id=' . (int)($r['id_remise'] ?? 0);
														?>
														<button type="button" class="btn btn-sm btn-info js-open-bordereau" data-bs-toggle="modal" data-bs-target="#modalBordereau" data-url="<?php echo h($bordUrl); ?>"><i class="fa fa-print"></i> bordereau</button>
														<?php if ($canEdit): ?>
															<button
																type="button"
																class="btn btn-sm btn-warning js-edit-remise"
																data-bs-toggle="modal"
																data-bs-target="#modalModifierRemise"
																data-id="<?php echo (int)($r['id_remise'] ?? 0); ?>"
																data-id-employe="<?php echo (int)($r['id_employe'] ?? 0); ?>"
																data-date="<?php echo h($r['date_remise'] ?? ''); ?>"
																data-montant="<?php echo h($r['montant'] ?? ''); ?>"
																data-type="<?php echo h($r['type_remise'] ?? ''); ?>"
																data-mode="<?php echo h($r['mode_paiement'] ?? ''); ?>"
																data-reference="<?php echo h($r['reference'] ?? ''); ?>"
																data-compte-credite="<?php echo (int)($r['id_compte'] ?? 0); ?>"
																data-compte-debite="<?php echo (int)($r['id_compte2'] ?? 0); ?>"
																data-notes="<?php echo h($r['notes'] ?? ''); ?>"
																data-preuve="<?php echo h($r['preuve'] ?? ''); ?>"
																data-preuve-url="<?php echo h((string)($url ?? '')); ?>"
															><i class="fa fa-edit"></i> modifier</button>
														<?php else: ?>
														<button type="button" class="btn btn-sm btn-warning" disabled title="Modification possible uniquement dans les 24h qui suivent la création"><i class="fa fa-edit"></i> modifier</button>
														<?php endif; ?>
													</td>
												</tr>
											<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								</section>
							</div>
						</div>
					</div>

			    </div>

			<!-- Modal Ajouter une remise -->
			<div class="modal fade" id="modalAjouterRemise" tabindex="-1" aria-hidden="true">
				<div class="modal-dialog modal-lg modal-dialog-scrollable">
					<div class="modal-content">
						<form method="POST" action="listeremisecompteinterne.php" enctype="multipart/form-data">
							<input type="hidden" name="ajouter_remise" value="1" />
							<div class="modal-header">
								<h5 class="modal-title">Ajout d'une remise</h5>
								<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
							</div>
							<div class="modal-body">
								<div class="row g-3">
									<div class="col-md-6">
										<label class="form-label">Remis par l'employé</label>
												<select class="form-control" name="id_employe" id="remise_id_employe" required>
											<option value="">-------- Choisir le remettant --------</option>
													<?php foreach ($employes as $e): ?>
														<option value="<?php echo (int)($e['id_employe'] ?? 0); ?>"><?php echo h($e['nomEmploye'] ?? $e['employe_nom'] ?? ''); ?></option>
													<?php endforeach; ?>
										</select>
									</div>
									<div class="col-md-3">
										<label class="form-label">Date de remise</label>
										<input type="date" class="form-control" name="date_remise" required>
									</div>
									<div class="col-md-3">
										<label class="form-label">Montant</label>
										<input type="number" class="form-control" name="montant" min="1" step="1" required>
									</div>
									<div class="col-md-4">
										<label class="form-label">Type de remise</label>
										<select class="form-control" name="type_remise" required>
											<?php foreach ($allowedTypeRemise as $t): ?>
												<option value="<?php echo htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(ucfirst($t), ENT_QUOTES, 'UTF-8'); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="col-md-4">
										<label class="form-label">Mode de paiement</label>
										<select class="form-control" name="mode_paiement" id="remise_mode_paiement" required>
											<option value="">-- Choisir --</option>
											<?php foreach ($allowedModePaiement as $m): ?>
												<option value="<?php echo h($m); ?>"><?php echo h($m); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="col-md-4">
										<label class="form-label">Référence</label>
										<input type="text" class="form-control" name="reference" required>
									</div>
									<div class="col-md-6">
										<label class="form-label">Compte débité</label>
											<select class="form-control" name="id_compte2" id="remise_id_compte2" required>
											<option value="">-- Choisir --</option>
											<?php foreach ($comptes as $c): ?>
												<option value="<?php echo (int)$c['id_compte']; ?>" data-type="<?php echo h($c['types'] ?? ''); ?>"><?php echo htmlspecialchars((string)$c['nom_compte'], ENT_QUOTES, 'UTF-8'); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="col-md-6">
										<label class="form-label">Compte crédité</label>
											<select class="form-control" name="id_compte" id="remise_id_compte" required>
											<option value="">-- Choisir --</option>
											<?php foreach ($comptes as $c): ?>
												<option value="<?php echo (int)$c['id_compte']; ?>" data-type="<?php echo h($c['types'] ?? ''); ?>"><?php echo htmlspecialchars((string)$c['nom_compte'], ENT_QUOTES, 'UTF-8'); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="col-md-12">
										<label class="form-label">Preuve (optionnel)</label>
										<input type="file" class="form-control" name="preuve" accept=".pdf,.jpg,.jpeg,.png" />
										<small class="text-muted">Formats acceptés: PDF, JPG, PNG (max 10 Mo)</small>
									</div>
									<div class="col-12">
										<label class="form-label">Notes</label>
										<textarea class="form-control" name="notes" rows="4"></textarea>
									</div>
								</div>
							</div>
							<div class="modal-footer">
								<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
								<button type="submit" class="btn btn-primary">Enregistrer</button>
							</div>
						</form>
					</div>
				</div>
			</div>

			<!-- Modal Modifier une remise -->
			<div class="modal fade" id="modalModifierRemise" tabindex="-1" aria-hidden="true">
				<div class="modal-dialog modal-lg modal-dialog-scrollable">
					<div class="modal-content">
						<form method="POST" action="listeremisecompteinterne.php" enctype="multipart/form-data">
							<input type="hidden" name="modifier_remise" value="1" />
							<input type="hidden" name="id_remise" id="edit_id_remise" value="" />
							<input type="hidden" name="preuve_current" id="edit_preuve_current" value="" />
							<div class="modal-header">
								<h5 class="modal-title">Modifier une remise</h5>
								<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
							</div>
							<div class="modal-body">
								<div class="row g-3">
										<div class="col-md-6">
											<label class="form-label">Remis par l'employé</label>
											<select class="form-control" name="id_employe" id="edit_id_employe" required>
												<option value="">-- Choisir --</option>
												<?php foreach ($employes as $e): $eid = (int)($e['id_employe'] ?? 0); ?>
													<option value="<?php echo $eid; ?>"><?php echo h($e['nomEmploye'] ?? $e['employe_nom'] ?? ''); ?></option>
												<?php endforeach; ?>
											</select>
										</div>
										<div class="col-md-3">
											<label class="form-label">Date de remise</label>
											<input type="date" class="form-control" name="date_remise" id="edit_date_remise" value="" required>
										</div>
										<div class="col-md-3">
											<label class="form-label">Montant</label>
											<input type="number" class="form-control" name="montant" id="edit_montant" min="1" step="1" value="" required>
										</div>
										<div class="col-md-4">
											<label class="form-label">Type de remise</label>
											<select class="form-control" name="type_remise" id="edit_type_remise" required>
												<?php foreach ($allowedTypeRemise as $t): ?>
													<option value="<?php echo h($t); ?>"><?php echo h(ucfirst($t)); ?></option>
												<?php endforeach; ?>
											</select>
										</div>
										<div class="col-md-4">
											<label class="form-label">Mode de paiement</label>
											<select class="form-control" name="mode_paiement" id="edit_mode_paiement" required>
												<option value="">-- Choisir --</option>
												<?php foreach ($allowedModePaiement as $m): ?>
													<option value="<?php echo h($m); ?>"><?php echo h($m); ?></option>
												<?php endforeach; ?>
											</select>
										</div>
										<div class="col-md-4">
											<label class="form-label">Référence</label>
											<input type="text" class="form-control" name="reference" id="edit_reference" value="" required>
										</div>
										<div class="col-md-6">
											<label class="form-label">Compte débité</label>
											<select class="form-control" name="id_compte2" id="edit_id_compte2" required>
												<option value="">-- Choisir --</option>
												<?php foreach ($comptes as $c): $cid = (int)($c['id_compte'] ?? 0); ?>
													<option value="<?php echo $cid; ?>" data-type="<?php echo h($c['types'] ?? ''); ?>"><?php echo h($c['nom_compte'] ?? ''); ?></option>
												<?php endforeach; ?>
											</select>
										</div>
										<div class="col-md-6">
											<label class="form-label">Compte crédité</label>
											<select class="form-control" name="id_compte" id="edit_id_compte" required>
												<option value="">-- Choisir --</option>
												<?php foreach ($comptes as $c): $cid = (int)($c['id_compte'] ?? 0); ?>
													<option value="<?php echo $cid; ?>" data-type="<?php echo h($c['types'] ?? ''); ?>"><?php echo h($c['nom_compte'] ?? ''); ?></option>
												<?php endforeach; ?>
											</select>
										</div>
										<div class="col-md-12">
											<label class="form-label">Preuve (optionnel)</label>
											<input type="file" class="form-control" name="preuve" accept=".pdf,.jpg,.jpeg,.png" />
											<div id="edit_preuve_current_wrap" class="mt-1"></div>
										</div>
										<div class="col-12">
											<label class="form-label">Notes</label>
											<textarea class="form-control" name="notes" id="edit_notes" rows="4"></textarea>
										</div>
								</div>
							</div>
							<div class="modal-footer">
								<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
								<button type="submit" class="btn btn-warning">Enregistrer</button>
							</div>
						</form>
					</div>
				</div>
			</div>

			<!-- Modal Preuve -->
			<div class="modal fade" id="modalPreuve" tabindex="-1" aria-hidden="true">
				<div class="modal-dialog modal-xl modal-dialog-scrollable">
					<div class="modal-content">
						<div class="modal-header">
							<h5 class="modal-title">Preuve</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body">
							<div id="preuveViewer"></div>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Fermer</button>
						</div>
					</div>
				</div>
			</div>

			<!-- Modal Bordereau (PDF) -->
			<div class="modal fade" id="modalBordereau" tabindex="-1" aria-hidden="true">
				<div class="modal-dialog modal-xl modal-dialog-scrollable">
					<div class="modal-content">
						<div class="modal-header">
							<h5 class="modal-title">Bordereau de remise</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body">
							<div class="ratio ratio-16x9">
								<iframe id="bordereauFrame" src="about:blank" style="width:100%;height:100%;" frameborder="0"></iframe>
							</div>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
							<button type="button" class="btn btn-primary" id="btnPrintBordereau"><i class="fa fa-print"></i> Imprimer</button>
						</div>
					</div>
				</div>
			</div>

			<script>
				(function () {
					function filterDebitedByMode(modeId, debitedId) {
						var mode = document.getElementById(modeId);
						var debited = document.getElementById(debitedId);
						if (!mode || !debited) return;

						var selectedMode = mode.value;
						var hasVisible = false;
						Array.prototype.forEach.call(debited.options, function (opt) {
							if (!opt.value) {
								opt.hidden = false;
								opt.disabled = false;
								return;
							}
							var t = (opt.getAttribute('data-type') || '').trim();
							var ok = !selectedMode || t === selectedMode;
							opt.hidden = !ok;
							opt.disabled = !ok;
							if (ok) hasVisible = true;
						});
						if (!hasVisible && selectedMode) {
							// aucun compte pour ce mode: on réactive tout (comportement tolérant)
							Array.prototype.forEach.call(debited.options, function (opt) {
								opt.hidden = false;
								opt.disabled = false;
							});
						}
						if (debited.value) {
							var current = debited.options[debited.selectedIndex];
							if (current && (current.hidden || current.disabled)) {
								debited.value = '';
							}
						}
					}

					function syncAccountSelects(creditedId, debitedId) {
						var credited = document.getElementById(creditedId);
						var debited = document.getElementById(debitedId);
						if (!credited || !debited) return;

						var creditedVal = credited.value;
						var debitedVal = debited.value;

						// Réactive uniquement ce qui est visible (le filtre peut déjà en masquer)
						Array.prototype.forEach.call(debited.options, function (opt) {
							if (!opt.hidden) opt.disabled = false;
						});
						Array.prototype.forEach.call(credited.options, function (opt) {
							if (!opt.hidden) opt.disabled = false;
						});

						// Désactive l'option identique dans l'autre select
						if (creditedVal) {
							Array.prototype.forEach.call(debited.options, function (opt) {
								if (opt.value === creditedVal) opt.disabled = true;
							});
						}
						if (debitedVal) {
							Array.prototype.forEach.call(credited.options, function (opt) {
								if (opt.value === debitedVal) opt.disabled = true;
							});
						}

						// Si la sélection actuelle devient invalide, on la vide
						if (credited.value && credited.value === debited.value) {
							debited.value = '';
						}
					}

					document.addEventListener('change', function (e) {
						if (!e.target) return;
						if (e.target.id === 'remise_mode_paiement') {
							filterDebitedByMode('remise_mode_paiement', 'remise_id_compte2');
							syncAccountSelects('remise_id_compte', 'remise_id_compte2');
							return;
						}
						if (e.target.id === 'edit_mode_paiement') {
							filterDebitedByMode('edit_mode_paiement', 'edit_id_compte2');
							syncAccountSelects('edit_id_compte', 'edit_id_compte2');
							return;
						}
						if (e.target.id === 'remise_id_compte' || e.target.id === 'remise_id_compte2') {
							syncAccountSelects('remise_id_compte', 'remise_id_compte2');
						}
						if (e.target.id === 'edit_id_compte' || e.target.id === 'edit_id_compte2') {
							syncAccountSelects('edit_id_compte', 'edit_id_compte2');
						}
					});
					// initial
					filterDebitedByMode('remise_mode_paiement', 'remise_id_compte2');
					syncAccountSelects('remise_id_compte', 'remise_id_compte2');
					filterDebitedByMode('edit_mode_paiement', 'edit_id_compte2');
					syncAccountSelects('edit_id_compte', 'edit_id_compte2');

					// Preuve modal
					var preuveModal = document.getElementById('modalPreuve');
					if (preuveModal) {
						preuveModal.addEventListener('show.bs.modal', function (event) {
							var btn = event.relatedTarget;
							var url = btn && btn.getAttribute ? btn.getAttribute('data-url') : '';
							var container = document.getElementById('preuveViewer');
							if (!container) return;
							container.innerHTML = '';
							if (!url) {
								container.innerHTML = '<div class="alert alert-warning mb-0">Aucune preuve.</div>';
								return;
							}
							var lower = url.toLowerCase();
							if (lower.endsWith('.pdf')) {
								container.innerHTML = '<div class="ratio ratio-16x9"><iframe src="' + url.replace(/"/g, '%22') + '" style="width:100%;height:100%;" frameborder="0"></iframe></div>';
							} else {
								container.innerHTML = 
									'<img src="' + url.replace(/"/g, '%22') + '" style="max-width:100%;height:auto;" />';
							}
						});
					}

					// Bordereau modal (PDF)
					var bordModal = document.getElementById('modalBordereau');
					if (bordModal) {
						bordModal.addEventListener('show.bs.modal', function (event) {
							var btn = event.relatedTarget;
							var url = btn && btn.getAttribute ? btn.getAttribute('data-url') : '';
							var frame = document.getElementById('bordereauFrame');
							if (!frame) return;
							frame.src = url ? url : 'about:blank';
						});
						bordModal.addEventListener('hidden.bs.modal', function () {
							var frame = document.getElementById('bordereauFrame');
							if (frame) frame.src = 'about:blank';
						});
					}
					var btnPrintBord = document.getElementById('btnPrintBordereau');
					if (btnPrintBord) {
						btnPrintBord.addEventListener('click', function () {
							var frame = document.getElementById('bordereauFrame');
							if (!frame || !frame.src || frame.src === 'about:blank') return;
							try {
								if (frame.contentWindow) {
									frame.contentWindow.focus();
									frame.contentWindow.print();
									return;
								}
							} catch (e) {
								// fallback: ouverture nouvel onglet
							}
							window.open(frame.src, '_blank');
						});
					}

					// Pré-remplissage du modal de modification via data-*
					var editModal = document.getElementById('modalModifierRemise');
					if (editModal) {
						editModal.addEventListener('show.bs.modal', function (event) {
							var btn = event.relatedTarget;
							if (!btn || !btn.getAttribute) return;
							var get = function (name) {
								return (btn.getAttribute('data-' + name) || '').toString();
							};

							var idRemise = get('id');
							var preuve = get('preuve');
							var preuveUrl = get('preuve-url');

							var el;
							el = document.getElementById('edit_id_remise'); if (el) el.value = idRemise;
							el = document.getElementById('edit_preuve_current'); if (el) el.value = preuve;

							el = document.getElementById('edit_id_employe'); if (el) el.value = get('id-employe');
							el = document.getElementById('edit_date_remise'); if (el) el.value = get('date');
							el = document.getElementById('edit_montant'); if (el) el.value = get('montant');
							el = document.getElementById('edit_type_remise'); if (el) el.value = get('type');
							el = document.getElementById('edit_mode_paiement'); if (el) el.value = get('mode');
							el = document.getElementById('edit_reference'); if (el) el.value = get('reference');
							el = document.getElementById('edit_id_compte'); if (el) el.value = get('compte-credite');
							el = document.getElementById('edit_id_compte2'); if (el) el.value = get('compte-debite');
							el = document.getElementById('edit_notes'); if (el) el.value = get('notes');

							// Affichage preuve actuelle dans le modal
							var wrap = document.getElementById('edit_preuve_current_wrap');
							if (wrap) {
								if (preuveUrl) {
									wrap.innerHTML = '<small class="text-muted">Preuve actuelle: <button type="button" class="btn btn-link p-0 align-baseline" data-bs-toggle="modal" data-bs-target="#modalPreuve" data-url="' + preuveUrl.replace(/"/g, '%22') + '">Voir</button></small>';
								} else {
									wrap.innerHTML = '<small class="text-muted">Aucune preuve.</small>';
								}
							}

							filterDebitedByMode('edit_mode_paiement', 'edit_id_compte2');
							syncAccountSelects('edit_id_compte', 'edit_id_compte2');
						});
					}
				})();
			</script>

			<?php include(__DIR__ . '/../public/footer.php'); ?>
