<?php
session_start();

require_once(__DIR__ . '/fonction.php');
require_once(__DIR__ . '/../logistique/fonctions_logistique.php');

if (!function_exists('redirectWith')) {
	function redirectWith(array $params = [], ?string $overridePath = null): void {
		$requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
		$parts = @parse_url($requestUri);

		$path = $overridePath ?? (is_array($parts) ? (string)($parts['path'] ?? '') : '');
		if ($path === '') {
			$path = (string)($_SERVER['PHP_SELF'] ?? '');
		}

		$currentQuery = [];
		if (is_array($parts) && !empty($parts['query'])) {
			parse_str((string)$parts['query'], $currentQuery);
		}

		// Permet de supprimer une clé en passant null
		foreach ($params as $k => $v) {
			if ($v === null) {
				unset($currentQuery[$k]);
				unset($params[$k]);
			}
		}

		$merged = array_merge($currentQuery, $params);
		$queryString = http_build_query($merged);
		$target = $path . ($queryString !== '' ? ('?' . $queryString) : '');

		header('Location: ' . $target, true, 303);
		exit;
	}
}

function getCurrentModuleFromScriptName(): ?string {
	$script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
	if ($script === '') return null;
	if (preg_match('~/apps/([^/]+)/~', $script, $m)) {
		return strtolower((string)$m[1]);
	}
	return null;
}

function getUserResponsableId(PDO $bdd, int $userId): int {
	if ($userId <= 0) return 0;
	try {
		$st = $bdd->prepare('SELECT responsable FROM users WHERE id=? LIMIT 1');
		$st->execute([$userId]);
		return (int)($st->fetchColumn() ?: 0);
	} catch (Throwable $e) {
		error_log('[demandes] getUserResponsableId => ' . $e->getMessage());
		return 0;
	}
}

$currentUserId = (int)($_SESSION['auth'] ?? 0);
$userId = (int)($_GET['u'] ?? 0);
if ($userId <= 0) {
	$userId = $currentUserId;
}

// Ne pas permettre d'agir au nom d'un autre utilisateur via ?u=
if ($currentUserId > 0 && $userId !== $currentUserId) {
	$userId = $currentUserId;
}

		$currentModule = getCurrentModuleFromScriptName();
		$isComptaModule = ($currentModule === 'comptabilite');
		$isLogistiqueModule = ($currentModule === 'logistique');

		// Un utilisateur est "responsable de clinique" s'il a des subordonnés (users.responsable = son id)
		$isCliniqueResponsable = false;
		try {
			$st = $bdd->prepare('SELECT COUNT(*) FROM users WHERE responsable = ?');
			$st->execute([$currentUserId]);
			$isCliniqueResponsable = ((int)$st->fetchColumn() > 0);
		} catch (Throwable $e) {
			error_log('[demandes] isCliniqueResponsable => ' . $e->getMessage());
		}

		$view = (string)($_GET['view'] ?? 'mine'); // mine | to_validate | to_pay | to_treat
		if ($view === 'to_approve') {
			// Compat anciens liens: on supprime l'étape "à approuver".
			$view = 'to_validate';
		}
		if (!in_array($view, ['mine', 'to_validate', 'to_pay', 'to_treat'], true)) {
			$view = 'mine';
		}

		$filterType = (string)($_GET['type'] ?? 'all'); // all | depense | logistique
		if (!in_array($filterType, ['all', 'depense', 'logistique'], true)) {
			$filterType = 'all';
		}

		$filterStatus = trim((string)($_GET['statut'] ?? '')); // dépense: 0..4 ; logistique: en_attente/validee/refusee/traitee/annulee
		$allowedLogistiqueStatus = ['en_attente', 'validee', 'refusee', 'traitee', 'annulee'];
		$depenseStatusLabel = [
			0 => 'En cours',
			1 => 'Autorisé',
			2 => 'Non autorisé',
			3 => 'Annulée',
			4 => 'Payée',
		];

		$filterDepenseStatus = null;
		$filterLogistiqueStatus = null;
		if ($filterStatus !== '') {
			if (preg_match('/^\d+$/', $filterStatus) && isset($depenseStatusLabel[(int)$filterStatus])) {
				$filterDepenseStatus = (int)$filterStatus;
			}
			if (in_array($filterStatus, $allowedLogistiqueStatus, true)) {
				$filterLogistiqueStatus = $filterStatus;
			}
		}
		$dateStart = trim((string)($_GET['date_debut'] ?? ''));
		$dateEnd = trim((string)($_GET['date_fin'] ?? ''));

		$flashSuccess = null;
		$flashError = null;

		if (!empty($_GET['ok'])) {
			if ($_GET['ok'] === 'depense') {
				$flashSuccess = 'Demande de dépense enregistrée.';
			} elseif ($_GET['ok'] === 'logistique') {
				$flashSuccess = 'Demande logistique enregistrée.';
			} elseif ($_GET['ok'] === 'valide') {
				$flashSuccess = 'Action effectuée.';
			} elseif ($_GET['ok'] === 'annulee') {
				$flashSuccess = 'Demande annulée.';
			} elseif ($_GET['ok'] === 'supprimee') {
				$flashSuccess = 'Demande supprimée.';
			}
		}

		if (!empty($_GET['err'])) {
			if ($_GET['err'] === 'input') {
				$flashError = 'Veuillez renseigner correctement les champs.';
			} elseif ($_GET['err'] === 'forbidden') {
				$flashError = 'Action non autorisée.';
			} elseif ($_GET['err'] === 'noclinique') {
				$flashError = "Aucun responsable de clinique n'est défini pour votre compte. Veuillez contacter la technologie pour renseigner votre responsable.";
			} elseif ($_GET['err'] === 'stock') {
				$flashError = 'Stock insuffisant pour traiter la demande.';
			} elseif ($_GET['err'] === 'budget') {
				$flashError = 'Budget insuffisant ou budget invalide.';
			} else {
				$flashError = 'Une erreur est survenue.';
			}
		}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

		if ($action === 'create_depense') {
			$description = trim((string)($_POST['description'] ?? ''));
			$designations = $_POST['designation'] ?? [];
			$quantites = $_POST['qte'] ?? [];
			$prixUnitaires = $_POST['pu'] ?? [];

			if ($description === '' || $userId <= 0 || !is_array($designations)) {
				redirectWith(['err' => 'input']);
			}

			// Validateur unique: responsable de la clinique (users.responsable)
			$cliniqueId = getUserResponsableId($bdd, $userId);
			if ($cliniqueId <= 0) {
				redirectWith(['err' => 'noclinique']);
			}
			$validateur = $cliniqueId;

			$lines = [];
			$total = 0.0;
			$count = count($designations);
			for ($i = 0; $i < $count; $i++) {
				$designation = trim((string)($designations[$i] ?? ''));
				$qte = (int)($quantites[$i] ?? 0);
				$pu = (float)($prixUnitaires[$i] ?? 0);
				if ($designation === '' || $qte <= 0 || $pu <= 0) {
					continue;
				}
				$montant = $qte * $pu;
				$total += $montant;
				$lines[] = ['designation' => $designation, 'qte' => $qte, 'pu' => $pu, 'montant' => $montant];
			}
			if (empty($lines) || $total <= 0) {
				redirectWith(['err' => 'input']);
			}

			$bdd->beginTransaction();
			$cols = ['description', 'montant', 'id', 'validateur'];
			$vals = [$description, $total, $userId, $validateur];
			$hasIdSup = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'id_superieur') : true;
			$hasIdClin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'id_responsable_clinique') : true;
			if ($hasIdSup) {
				$cols[] = 'id_superieur';
				$vals[] = null;
			}
			if ($hasIdClin) {
				$cols[] = 'id_responsable_clinique';
				$vals[] = $cliniqueId;
			}
			$ph = implode(',', array_fill(0, count($cols), '?'));
			$st = $bdd->prepare('INSERT INTO depenses (' . implode(',', $cols) . ') VALUES(' . $ph . ')');
			$st->execute($vals);
			$idDepense = (int)$bdd->lastInsertId();

			$stL = $bdd->prepare('INSERT INTO depenses_lignes (id_depense, designation, quantite, prix_unitaire, montant_ligne) VALUES (?,?,?,?,?)');
			foreach ($lines as $ln) {
				$stL->execute([$idDepense, $ln['designation'], $ln['qte'], $ln['pu'], $ln['montant']]);
			}
			$bdd->commit();

			redirectWith(['ok' => 'depense']);
		}

		if ($action === 'create_logistique') {
			$commentaire = trim((string)($_POST['commentaire'] ?? ''));
			$articleIds = $_POST['id_article'] ?? [];
			$quantites = $_POST['quantite'] ?? [];

			if ($userId <= 0 || !is_array($articleIds) || !is_array($quantites)) {
				redirectWith(['err' => 'input']);
			}

			$cliniqueId = getUserResponsableId($bdd, $userId);
			if ($cliniqueId <= 0) {
				redirectWith(['err' => 'noclinique']);
			}
			$validateur = $cliniqueId;

			$lines = [];
			$count = count($articleIds);
			for ($i = 0; $i < $count; $i++) {
				$idArticle = (int)($articleIds[$i] ?? 0);
				$qte = (int)($quantites[$i] ?? 0);
				if ($idArticle <= 0 || $qte <= 0) {
					continue;
				}
				$lines[] = ['id_article' => $idArticle, 'qte' => $qte];
			}
			if (empty($lines)) {
				redirectWith(['err' => 'input']);
			}

			// Vérifier existence des articles
			$ph = implode(',', array_fill(0, count($lines), '?'));
			$args = array_map(fn($r) => (int)$r['id_article'], $lines);
			$stA = $bdd->prepare('SELECT id_article FROM log_articles WHERE actif=1 AND id_article IN (' . $ph . ')');
			$stA->execute($args);
			$found = array_map('intval', $stA->fetchAll(PDO::FETCH_COLUMN));
			foreach ($lines as $ln) {
				if (!in_array((int)$ln['id_article'], $found, true)) {
					redirectWith(['err' => 'input']);
				}
			}

			$bdd->beginTransaction();
			$cols = ['id_user', 'id_validateur', 'description'];
			$vals = [$userId, $validateur, ($commentaire !== '' ? $commentaire : 'Demande logistique')];
			$hasClin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'log_demandes', 'id_responsable_clinique') : true;
			if ($hasClin) {
				$cols[] = 'id_responsable_clinique';
				$vals[] = $cliniqueId;
			}
			$ph = implode(',', array_fill(0, count($cols), '?'));
			$st = $bdd->prepare('INSERT INTO log_demandes (' . implode(',', $cols) . ') VALUES (' . $ph . ')');
			$st->execute($vals);
			$idDemande = (int)$bdd->lastInsertId();

			$stL = $bdd->prepare('INSERT INTO log_demandes_lignes (id_demande, id_article, quantite, commentaire) VALUES (?,?,?,?)');
			foreach ($lines as $ln) {
				$stL->execute([$idDemande, (int)$ln['id_article'], (int)$ln['qte'], $commentaire !== '' ? $commentaire : null]);
			}

			$bdd->commit();
			redirectWith(['ok' => 'logistique']);
		}

		if ($action === 'update_depense') {
			$idDepense = (int)($_POST['id_depense'] ?? 0);
			$description = trim((string)($_POST['description'] ?? ''));
			$designations = $_POST['designation'] ?? [];
			$quantites = $_POST['qte'] ?? [];
			$prixUnitaires = $_POST['pu'] ?? [];
			if ($idDepense <= 0 || $userId <= 0 || $description === '' || !is_array($designations)) {
				redirectWith(['err' => 'input']);
			}

			$cliniqueId = getUserResponsableId($bdd, $userId);
			if ($cliniqueId <= 0) {
				redirectWith(['err' => 'noclinique']);
			}

			$lines = [];
			$total = 0.0;
			$count = count($designations);
			for ($i = 0; $i < $count; $i++) {
				$designation = trim((string)($designations[$i] ?? ''));
				$qte = (int)($quantites[$i] ?? 0);
				$pu = (float)($prixUnitaires[$i] ?? 0);
				if ($designation === '' || $qte <= 0 || $pu <= 0) {
					continue;
				}
				$montant = $qte * $pu;
				$total += $montant;
				$lines[] = ['designation' => $designation, 'qte' => $qte, 'pu' => $pu, 'montant' => $montant];
			}
			if (empty($lines) || $total <= 0) {
				redirectWith(['err' => 'input']);
			}

			$hasEtatClin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'etat_clinique') : false;
			$hasIdSup = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'id_superieur') : false;
			$hasIdClin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'id_responsable_clinique') : false;

			$bdd->beginTransaction();
			$sqlChk = 'SELECT id_depense FROM depenses WHERE id_depense=? AND id=? AND status=0';
			if ($hasEtatClin) {
				$sqlChk .= ' AND etat_clinique=0';
			}
			$stChk = $bdd->prepare($sqlChk);
			$stChk->execute([$idDepense, $userId]);
			if (!(int)$stChk->fetchColumn()) {
				$bdd->rollBack();
				redirectWith(['err' => 'forbidden']);
			}

			$set = ['description=?', 'montant=?', 'validateur=?'];
			$argsUp = [$description, $total, $cliniqueId];
			if ($hasIdSup) {
				$set[] = 'id_superieur=?';
				$argsUp[] = null;
			}
			if ($hasIdClin) {
				$set[] = 'id_responsable_clinique=?';
				$argsUp[] = $cliniqueId;
			}
			$argsUp[] = $idDepense;
			$argsUp[] = $userId;
			$st = $bdd->prepare('UPDATE depenses SET ' . implode(', ', $set) . ' WHERE id_depense=? AND id=?');
			$st->execute($argsUp);

			$bdd->prepare('DELETE FROM depenses_lignes WHERE id_depense=?')->execute([$idDepense]);
			$stL = $bdd->prepare('INSERT INTO depenses_lignes (id_depense, designation, quantite, prix_unitaire, montant_ligne) VALUES (?,?,?,?,?)');
			foreach ($lines as $ln) {
				$stL->execute([$idDepense, $ln['designation'], $ln['qte'], $ln['pu'], $ln['montant']]);
			}
			$bdd->commit();
			redirectWith(['ok' => 'valide']);
		}

		if ($action === 'update_logistique') {
			$idDemande = (int)($_POST['id_demande'] ?? 0);
			$commentaire = trim((string)($_POST['commentaire'] ?? ''));
			$articleIds = $_POST['id_article'] ?? [];
			$quantites = $_POST['quantite'] ?? [];
			if ($idDemande <= 0 || $userId <= 0 || !is_array($articleIds) || !is_array($quantites)) {
				redirectWith(['err' => 'input']);
			}

			$cliniqueId = getUserResponsableId($bdd, $userId);
			if ($cliniqueId <= 0) {
				redirectWith(['err' => 'noclinique']);
			}

			$lines = [];
			$count = count($articleIds);
			for ($i = 0; $i < $count; $i++) {
				$idArticle = (int)($articleIds[$i] ?? 0);
				$qte = (int)($quantites[$i] ?? 0);
				if ($idArticle <= 0 || $qte <= 0) continue;
				$lines[] = ['id_article' => $idArticle, 'qte' => $qte];
			}
			if (empty($lines)) {
				redirectWith(['err' => 'input']);
			}

			$ph = implode(',', array_fill(0, count($lines), '?'));
			$args = array_map(fn($r) => (int)$r['id_article'], $lines);
			$stA = $bdd->prepare('SELECT id_article FROM log_articles WHERE actif=1 AND id_article IN (' . $ph . ')');
			$stA->execute($args);
			$found = array_map('intval', $stA->fetchAll(PDO::FETCH_COLUMN));
			foreach ($lines as $ln) {
				if (!in_array((int)$ln['id_article'], $found, true)) {
					redirectWith(['err' => 'input']);
				}
			}

			$hasClin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'log_demandes', 'id_responsable_clinique') : false;
			$bdd->beginTransaction();
			$stChk = $bdd->prepare('SELECT id_demande FROM log_demandes WHERE id_demande=? AND id_user=? AND statut="en_attente"');
			$stChk->execute([$idDemande, $userId]);
			if (!(int)$stChk->fetchColumn()) {
				$bdd->rollBack();
				redirectWith(['err' => 'forbidden']);
			}

			$set = ['description=?', 'id_validateur=?'];
			$argsUp = [($commentaire !== '' ? $commentaire : 'Demande logistique'), $cliniqueId];
			if ($hasClin) {
				$set[] = 'id_responsable_clinique=?';
				$argsUp[] = $cliniqueId;
			}
			$argsUp[] = $idDemande;
			$argsUp[] = $userId;
			$st = $bdd->prepare('UPDATE log_demandes SET ' . implode(', ', $set) . ' WHERE id_demande=? AND id_user=?');
			$st->execute($argsUp);

			$bdd->prepare('DELETE FROM log_demandes_lignes WHERE id_demande=?')->execute([$idDemande]);
			$stL = $bdd->prepare('INSERT INTO log_demandes_lignes (id_demande, id_article, quantite, commentaire) VALUES (?,?,?,?)');
			foreach ($lines as $ln) {
				$stL->execute([$idDemande, (int)$ln['id_article'], (int)$ln['qte'], ($commentaire !== '' ? $commentaire : null)]);
			}
			$bdd->commit();
			redirectWith(['ok' => 'valide']);
		}

		if ($action === 'annuler_depense') {
			$idDepense = (int)($_POST['id_depense'] ?? 0);
			if ($idDepense <= 0 || $userId <= 0) {
				redirectWith(['err' => 'input']);
			}

			$hasEtatClin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'etat_clinique') : false;
			$sql = 'UPDATE depenses SET status=3 WHERE id_depense=? AND id=? AND status=0';
			if ($hasEtatClin) {
				$sql .= ' AND etat_clinique=0';
			}
			$st = $bdd->prepare($sql);
			$st->execute([$idDepense, $userId]);
			redirectWith(['ok' => 'annulee']);
		}

		if ($action === 'supprimer_depense') {
			$idDepense = (int)($_POST['id_depense'] ?? 0);
			if ($idDepense <= 0 || $userId <= 0) {
				redirectWith(['err' => 'input']);
			}

			$hasStatus = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'status') : true;
			$hasEtatClin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'etat_clinique') : false;
			if (!$hasStatus) {
				redirectWith(['err' => 'forbidden']);
			}

			try {
				$bdd->beginTransaction();
				$cols = ['status'];
				if ($hasEtatClin) {
					$cols[] = 'etat_clinique';
				}
				$st = $bdd->prepare('SELECT ' . implode(', ', $cols) . ' FROM depenses WHERE id_depense=? AND id=? FOR UPDATE');
				$st->execute([$idDepense, $userId]);
				$row = $st->fetch(PDO::FETCH_ASSOC);
				if (!$row) {
					$bdd->rollBack();
					redirectWith(['err' => 'forbidden']);
				}
				$status = (int)($row['status'] ?? 0);
				$etatClin = $hasEtatClin ? (int)($row['etat_clinique'] ?? 0) : 0;
				$canDelete = in_array($status, [2, 3], true) || ($hasEtatClin && $etatClin === 2);
				if (!$canDelete) {
					$bdd->rollBack();
					redirectWith(['err' => 'forbidden']);
				}

				$bdd->prepare('DELETE FROM depenses_lignes WHERE id_depense=?')->execute([$idDepense]);
				$bdd->prepare('DELETE FROM depenses WHERE id_depense=? AND id=?')->execute([$idDepense, $userId]);
				$bdd->commit();
				redirectWith(['ok' => 'supprimee']);
			} catch (Throwable $e) {
				if ($bdd->inTransaction()) {
					$bdd->rollBack();
				}
				error_log('[demandes] supprimer_depense => ' . $e->getMessage());
				redirectWith(['err' => 'forbidden']);
			}
		}

		// Validation unique: responsable de la clinique
		if ($action === 'valider_depense' || $action === 'refuser_depense') {
			$idDepense = (int)($_POST['id_depense'] ?? 0);
			$comment = trim((string)($_POST['commentaire_validation'] ?? ''));
			$signature = trim((string)($_POST['signature'] ?? ''));
			if ($idDepense <= 0 || $currentUserId <= 0) {
				redirectWith(['err' => 'input']);
			}

			$isApprove = ($action === 'valider_depense');
			$hasEtatClin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'etat_clinique') : false;
			$hasClinValidePar = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'clinique_valide_par') : false;
			$hasDateClin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'date_validation_clinique') : false;
			$hasComClin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'commentaire_validation_clinique') : false;
			$hasIdClin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'id_responsable_clinique') : false;
			$hasSigClin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'signature_clinique') : false;
			$hasDateFin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'datefin') : false;

			$set = ['status=' . (int)($isApprove ? 1 : 2)];
			if (!$isApprove && $hasDateFin) {
				$set[] = 'datefin=NOW()';
			}
			if ($hasEtatClin) {
				$set[] = 'etat_clinique=' . (int)($isApprove ? 1 : 2);
			}
			if ($hasClinValidePar) {
				$set[] = 'clinique_valide_par=' . (int)$currentUserId;
			}
			if ($hasDateClin) {
				$set[] = 'date_validation_clinique=NOW()';
			}
			if ($hasComClin) {
				$set[] = 'commentaire_validation_clinique=?';
			}
			if ($hasSigClin) {
				$set[] = 'signature_clinique=?';
			}

			$sql = 'UPDATE depenses SET ' . implode(', ', $set) . ' WHERE id_depense=? AND status=0';
			$args = [];
			if ($hasComClin) {
				$args[] = ($comment !== '' ? $comment : null);
			}
			if ($hasSigClin) {
				$args[] = ($signature !== '' ? $signature : null);
			}
			$args[] = $idDepense;
			if ($hasIdClin) {
				$sql .= ' AND id_responsable_clinique=?';
				$args[] = $currentUserId;
			} else {
				$sql .= ' AND validateur=?';
				$args[] = $currentUserId;
			}
			if ($hasEtatClin) {
				$sql .= ' AND etat_clinique=0';
			}
			$st = $bdd->prepare($sql);
			$st->execute($args);

			redirectWith(['ok' => 'valide']);
		}

		// Paiement par la comptabilité (par budget) + bon de paiement
		if ($action === 'payer_depense') {
			if (!$isComptaModule) {
				redirectWith(['err' => 'forbidden']);
			}
			$idDepense = (int)($_POST['id_depense'] ?? 0);
			$idBudget = (int)($_POST['id_budget'] ?? 0);
			$reference = trim((string)($_POST['reference_paiement'] ?? ''));
			$signature = trim((string)($_POST['signature'] ?? ''));
			if ($idDepense <= 0 || $idBudget <= 0 || $currentUserId <= 0) {
				redirectWith(['err' => 'input']);
			}

			$hasIdBudget = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'id_budget') : false;
			$hasEtatCompta = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'etat_compta') : false;
			$hasComptaPayePar = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'compta_paye_par') : false;
			$hasDatePaiement = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'date_paiement') : false;
			$hasRef = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'reference_paiement') : false;
			$hasPayeur = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'payeur') : false;
			$hasEtatClin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'etat_clinique') : false;
			$hasSigCompta = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'signature_compta') : false;

			$bdd->beginTransaction();
			try {
				// Verrouiller la dépense
				$sqlD = 'SELECT montant FROM depenses WHERE id_depense=? AND status=1';
				if ($hasEtatClin) {
					$sqlD .= ' AND etat_clinique=1';
				}
				if ($hasEtatCompta) {
					$sqlD .= ' AND etat_compta=0';
				}
				$sqlD .= ' FOR UPDATE';
				$stD = $bdd->prepare($sqlD);
				$stD->execute([$idDepense]);
				$montantDep = (float)($stD->fetchColumn() ?: 0);
				if ($montantDep <= 0) {
					$bdd->rollBack();
					redirectWith(['err' => 'forbidden']);
				}

				// Verrouiller le budget
				$stB = $bdd->prepare('SELECT * FROM budgets WHERE id_budget=? LIMIT 1 FOR UPDATE');
				$stB->execute([$idBudget]);
				$budget = $stB->fetch(PDO::FETCH_ASSOC) ?: null;
				if (!$budget) {
					$bdd->rollBack();
					redirectWith(['err' => 'budget']);
				}

				$montantInitial = (float)($budget['montant_initial'] ?? 0);
				$montantUtilise = 0.0;
				if (isset($budget['montant_utilise'])) {
					$montantUtilise = (float)$budget['montant_utilise'];
				} elseif (isset($budget['montant_utilisé'])) {
					$montantUtilise = (float)$budget['montant_utilisé'];
				}
				$restant = null;
				if (isset($budget['solde'])) {
					$restant = (float)$budget['solde'];
				} elseif (isset($budget['montant_restant'])) {
					$restant = (float)$budget['montant_restant'];
				}
				if ($restant === null) {
					$restant = $montantInitial - $montantUtilise;
				}
				if ($restant < $montantDep) {
					$bdd->rollBack();
					redirectWith(['err' => 'budget']);
				}

				// Mise à jour budget (essayer solde et/ou montant_utilise si colonnes présentes)
				try {
					if (isset($budget['montant_utilise'])) {
						$bdd->prepare('UPDATE budgets SET montant_utilise = montant_utilise + ? WHERE id_budget=?')->execute([$montantDep, $idBudget]);
					} elseif (isset($budget['montant_utilisé'])) {
						$bdd->prepare('UPDATE budgets SET `montant_utilisé` = `montant_utilisé` + ? WHERE id_budget=?')->execute([$montantDep, $idBudget]);
					}
					if (isset($budget['solde'])) {
						$bdd->prepare('UPDATE budgets SET solde = solde - ? WHERE id_budget=?')->execute([$montantDep, $idBudget]);
					} elseif (isset($budget['montant_restant'])) {
						$bdd->prepare('UPDATE budgets SET montant_restant = montant_restant - ? WHERE id_budget=?')->execute([$montantDep, $idBudget]);
					}
				} catch (Throwable $e) {
					// Si colonnes non présentes, on ne bloque pas le paiement.
				}

				$set = ['status=4'];
				if ($hasIdBudget) {
					$set[] = 'id_budget=' . (int)$idBudget;
				}
				if ($hasEtatCompta) {
					$set[] = 'etat_compta=1';
				}
				if ($hasComptaPayePar) {
					$set[] = 'compta_paye_par=' . (int)$currentUserId;
				}
				if ($hasDatePaiement) {
					$set[] = 'date_paiement=NOW()';
				}
				if ($hasRef) {
					$set[] = 'reference_paiement=?';
				}
				if ($hasPayeur) {
					$set[] = 'payeur=' . (int)$currentUserId;
				}
				if ($hasSigCompta) {
					$set[] = 'signature_compta=?';
				}

				$sql = 'UPDATE depenses SET ' . implode(', ', $set) . ' WHERE id_depense=? AND status=1';
				if ($hasEtatClin) {
					$sql .= ' AND etat_clinique=1';
				}
				if ($hasEtatCompta) {
					$sql .= ' AND etat_compta=0';
				}
				$args = [];
				if ($hasRef) {
					$args[] = ($reference !== '' ? $reference : null);
				}
				if ($hasSigCompta) {
					$args[] = ($signature !== '' ? $signature : null);
				}
				$args[] = $idDepense;
				$bdd->prepare($sql)->execute($args);

				$bdd->commit();
				redirectWith(['ok' => 'valide']);
			} catch (Throwable $e) {
				if ($bdd->inTransaction()) $bdd->rollBack();
				error_log('[demandes] payer_depense budget => ' . $e->getMessage());
				redirectWith(['err' => 'server']);
			}

			redirectWith(['ok' => 'valide']);
		}

		// Fin de processus: le demandeur confirme la réception
		if ($action === 'confirmer_reception_depense') {
			$idDepense = (int)($_POST['id_depense'] ?? 0);
			$signature = trim((string)($_POST['signature'] ?? ''));
			if ($idDepense <= 0 || $userId <= 0) {
				redirectWith(['err' => 'input']);
			}
			$hasEtatReception = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'etat_reception') : false;
			$hasDateReception = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'date_reception') : false;
			$hasEtatCompta = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'etat_compta') : false;
			$hasSigReception = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'signature_reception') : false;

			$set = [];
			if ($hasEtatReception) {
				$set[] = 'etat_reception=1';
			}
			if ($hasDateReception) {
				$set[] = 'date_reception=NOW()';
			}
			if ($hasSigReception) {
				$set[] = 'signature_reception=?';
			}
			if (empty($set)) {
				redirectWith(['err' => 'server']);
			}
			$sql = 'UPDATE depenses SET ' . implode(', ', $set) . ' WHERE id_depense=? AND id=? AND status=4';
			if ($hasEtatReception) {
				$sql .= ' AND etat_reception=0';
			}
			if ($hasEtatCompta) {
				$sql .= ' AND etat_compta=1';
			}
			$args = [];
			if ($hasSigReception) {
				$args[] = ($signature !== '' ? $signature : null);
			}
			$args[] = $idDepense;
			$args[] = $userId;
			$st = $bdd->prepare($sql);
			$st->execute($args);

			redirectWith(['ok' => 'valide']);
		}

		// Validation unique (responsable clinique) : logistique
		if ($action === 'valider_logistique' || $action === 'refuser_logistique') {
			$idDemande = (int)($_POST['id_demande'] ?? 0);
			$comment = trim((string)($_POST['commentaire_validation'] ?? ''));
			$signature = trim((string)($_POST['signature'] ?? ''));
			if ($idDemande <= 0 || $currentUserId <= 0) {
				redirectWith(['err' => 'input']);
			}
			$isApprove = ($action === 'valider_logistique');
			$newStatut = $isApprove ? 'validee' : 'refusee';
			$hasEtatClin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'log_demandes', 'etat_clinique') : false;
			$hasClinPar = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'log_demandes', 'clinique_valide_par') : false;
			$hasDateClin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'log_demandes', 'date_validation_clinique') : false;
			$hasSigClin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'log_demandes', 'signature_clinique') : false;
			$hasComClin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'log_demandes', 'commentaire_validation_clinique') : false;
			$hasIdClin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'log_demandes', 'id_responsable_clinique') : false;
			$hasValidePar = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'log_demandes', 'valide_par') : false;
			$hasDateVal = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'log_demandes', 'date_validation') : false;
			$hasComVal = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'log_demandes', 'commentaire_validation') : false;

			$set = ['statut=?'];
			if ($hasEtatClin) {
				$set[] = 'etat_clinique=' . (int)($isApprove ? 1 : 2);
			}
			if ($hasClinPar) {
				$set[] = 'clinique_valide_par=' . (int)$currentUserId;
			}
			if ($hasDateClin) {
				$set[] = 'date_validation_clinique=NOW()';
			}
			if ($hasComClin) {
				$set[] = 'commentaire_validation_clinique=?';
			}
			if ($hasSigClin) {
				$set[] = 'signature_clinique=?';
			}
			// Compat colonnes legacy
			if ($hasValidePar) {
				$set[] = 'valide_par=' . (int)$currentUserId;
			}
			if ($hasDateVal) {
				$set[] = 'date_validation=NOW()';
			}
			if ($hasComVal) {
				$set[] = 'commentaire_validation=?';
			}

			$sql = 'UPDATE log_demandes SET ' . implode(', ', $set) . ' WHERE id_demande=? AND statut="en_attente"';
			$args = [$newStatut];
			if ($hasComClin) {
				$args[] = ($comment !== '' ? $comment : null);
			}
			if ($hasSigClin) {
				$args[] = ($signature !== '' ? $signature : null);
			}
			if ($hasComVal) {
				$args[] = ($comment !== '' ? $comment : null);
			}
			$args[] = $idDemande;
			if ($hasIdClin) {
				$sql .= ' AND id_responsable_clinique=?';
				$args[] = $currentUserId;
			} else {
				$sql .= ' AND id_validateur=?';
				$args[] = $currentUserId;
			}
			$st = $bdd->prepare($sql);
			$st->execute($args);

			redirectWith(['ok' => 'valide']);
		}

		if ($action === 'supprimer_logistique') {
			$idDemande = (int)($_POST['id_demande'] ?? 0);
			if ($idDemande <= 0 || $userId <= 0) {
				redirectWith(['err' => 'input']);
			}

			$hasEtatClin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'log_demandes', 'etat_clinique') : false;
			try {
				$bdd->beginTransaction();
				$cols = ['statut'];
				if ($hasEtatClin) {
					$cols[] = 'etat_clinique';
				}
				$st = $bdd->prepare('SELECT ' . implode(', ', $cols) . ' FROM log_demandes WHERE id_demande=? AND id_user=? FOR UPDATE');
				$st->execute([$idDemande, $userId]);
				$row = $st->fetch(PDO::FETCH_ASSOC);
				if (!$row) {
					$bdd->rollBack();
					redirectWith(['err' => 'forbidden']);
				}
				$statut = (string)($row['statut'] ?? '');
				$etatClin = $hasEtatClin ? (int)($row['etat_clinique'] ?? 0) : 0;
				$canDelete = in_array($statut, ['refusee', 'annulee'], true) || ($hasEtatClin && $etatClin === 2);
				if (!$canDelete) {
					$bdd->rollBack();
					redirectWith(['err' => 'forbidden']);
				}

				$bdd->prepare('DELETE FROM log_demandes_lignes WHERE id_demande=?')->execute([$idDemande]);
				$bdd->prepare('DELETE FROM log_demandes WHERE id_demande=? AND id_user=?')->execute([$idDemande, $userId]);
				$bdd->commit();
				redirectWith(['ok' => 'supprimee']);
			} catch (Throwable $e) {
				if ($bdd->inTransaction()) {
					$bdd->rollBack();
				}
				error_log('[demandes] supprimer_logistique => ' . $e->getMessage());
				redirectWith(['err' => 'forbidden']);
			}
		}

		// Traitement par la logistique (sortie stock)
		if ($action === 'traiter_logistique') {
			if (!$isLogistiqueModule) {
				redirectWith(['err' => 'forbidden']);
			}
			$idDemande = (int)($_POST['id_demande'] ?? 0);
			$signature = trim((string)($_POST['signature'] ?? ''));
			if ($idDemande <= 0 || $currentUserId <= 0) {
				redirectWith(['err' => 'input']);
			}
			$hasEtatTrait = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'log_demandes', 'etat_traitement') : false;
			$hasEtatClin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'log_demandes', 'etat_clinique') : false;
			$hasSigTrait = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'log_demandes', 'signature_traitement') : false;
			$bdd->beginTransaction();
			try {
				$st = $bdd->prepare('SELECT id_demande FROM log_demandes WHERE id_demande=? AND statut="validee"' . ($hasEtatTrait ? ' AND etat_traitement=0' : '') . ($hasEtatClin ? ' AND etat_clinique=1' : ''));
				$st->execute([$idDemande]);
				if (!(int)$st->fetchColumn()) {
					$bdd->rollBack();
					redirectWith(['err' => 'forbidden']);
				}

				$stL = $bdd->prepare('SELECT id_article, quantite FROM log_demandes_lignes WHERE id_demande=?');
				$stL->execute([$idDemande]);
				$lines = $stL->fetchAll(PDO::FETCH_ASSOC);
				if (empty($lines)) {
					$bdd->rollBack();
					redirectWith(['err' => 'input']);
				}

				// Vérifier stock suffisant
				foreach ($lines as $ln) {
					$idA = (int)$ln['id_article'];
					$q = (int)$ln['quantite'];
					$stS = $bdd->prepare('SELECT stock_actuel FROM log_articles WHERE id_article=? AND actif=1');
					$stS->execute([$idA]);
					$stock = (int)($stS->fetchColumn() ?: 0);
					if ($q <= 0 || $stock < $q) {
						$bdd->rollBack();
						redirectWith(['err' => 'stock']);
					}
				}
				foreach ($lines as $ln) {
					enregistrerMouvementStock($bdd, (int)$ln['id_article'], 'sortie', 'demande', (string)$idDemande, (int)$ln['quantite']);
				}

				$set = ['statut="traitee"'];
				if ($hasEtatTrait) {
					$set[] = 'etat_traitement=1';
					$set[] = 'traite_par=' . (int)$currentUserId;
					$set[] = 'date_traitement=NOW()';
				}
				if ($hasSigTrait) {
					$set[] = 'signature_traitement=?';
				}
				$argsUp = [];
				if ($hasSigTrait) {
					$argsUp[] = ($signature !== '' ? $signature : null);
				}
				$argsUp[] = $idDemande;
				$up = $bdd->prepare('UPDATE log_demandes SET ' . implode(', ', $set) . ' WHERE id_demande=?');
				$up->execute($argsUp);

				$bdd->commit();
				redirectWith(['ok' => 'valide']);
			} catch (Throwable $e) {
				if ($bdd->inTransaction()) $bdd->rollBack();
				error_log('[demandes] traiter_logistique => ' . $e->getMessage());
				redirectWith(['err' => 'server']);
			}
		}

		if ($action === 'confirmer_reception_logistique') {
			$idDemande = (int)($_POST['id_demande'] ?? 0);
			$signature = trim((string)($_POST['signature'] ?? ''));
			if ($idDemande <= 0 || $userId <= 0) {
				redirectWith(['err' => 'input']);
			}
			$hasEtatReception = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'log_demandes', 'etat_reception') : false;
			$hasDateReception = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'log_demandes', 'date_reception') : false;
			$hasSigReception = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'log_demandes', 'signature_reception') : false;
			$set = [];
			if ($hasEtatReception) {
				$set[] = 'etat_reception=1';
			}
			if ($hasDateReception) {
				$set[] = 'date_reception=NOW()';
			}
			if ($hasSigReception) {
				$set[] = 'signature_reception=?';
			}
			if (empty($set)) {
				redirectWith(['err' => 'server']);
			}
			$sql = 'UPDATE log_demandes SET ' . implode(', ', $set) . ' WHERE id_demande=? AND id_user=? AND statut="traitee"';
			if ($hasEtatReception) {
				$sql .= ' AND etat_reception=0';
			}
			$st = $bdd->prepare($sql);
			$args = [];
			if ($hasSigReception) {
				$args[] = ($signature !== '' ? $signature : null);
			}
			$args[] = $idDemande;
			$args[] = $userId;
			$st->execute($args);

			redirectWith(['ok' => 'valide']);
		}

		redirectWith(['err' => 'input']);
}

$hasDepensesStatus = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'status') : true;
$hasDepensesDateDebut = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'datedebut') : false;
$hasDepensesDateFin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'datefin') : false;
$hasDepensesIdClinique = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'id_responsable_clinique') : false;
$hasDepensesEtatSup = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'etat_superieur') : false;
$hasDepensesEtatClin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'etat_clinique') : false;
$hasDepensesEtatCompta = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'etat_compta') : false;
$hasDepensesEtatReception = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'etat_reception') : false;
$hasDepensesDatePaiement = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'date_paiement') : false;
$hasDepensesRefPaiement = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'reference_paiement') : false;
$hasDepensesSupValidePar = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'superieur_valide_par') : false;
$hasDepensesClinValidePar = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'clinique_valide_par') : false;
$hasDepensesComptaPayePar = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'depenses', 'compta_paye_par') : false;

$depenses = [];
$depensesLinesByDepense = [];
try {
	$where = [];
	$args = [];
	if ($view === 'to_validate') {
		if ($hasDepensesIdClinique) {
			$where[] = 'id_responsable_clinique = ?';
			$args[] = $currentUserId;
		} else {
			$where[] = 'validateur = ?';
			$args[] = $currentUserId;
		}
		if ($hasDepensesStatus) {
			$where[] = 'status = 0';
		}
		if ($hasDepensesEtatClin) {
			$where[] = 'etat_clinique = 0';
		}
	} elseif ($view === 'to_pay') {
		if (!$isComptaModule) {
			$where[] = '1=0';
		} else {
			if ($hasDepensesStatus) {
				$where[] = 'status = 1';
			}
			if ($hasDepensesEtatClin) {
				$where[] = 'etat_clinique = 1';
			}
			if ($hasDepensesEtatCompta) {
				$where[] = 'etat_compta = 0';
			}
		}
	} else {
		$where[] = 'id = ?';
		$args[] = $userId;
	}

	if ($filterType !== 'all' && $filterType !== 'depense') {
		// ne rien charger si on filtre uniquement logistique
		$where[] = '1=0';
	}

	if ($filterDepenseStatus !== null && $hasDepensesStatus) {
		$where[] = 'status = ?';
		$args[] = (int)$filterDepenseStatus;
	}

	if ($hasDepensesDateDebut && $dateStart !== '') {
		$where[] = 'DATE(datedebut) >= ?';
		$args[] = $dateStart;
	}
	if ($hasDepensesDateDebut && $dateEnd !== '') {
		$where[] = 'DATE(datedebut) <= ?';
		$args[] = $dateEnd;
	}

	$cols = 'id_depense, description, montant, id, validateur';
	if ($hasDepensesStatus) {
		$cols .= ', status, payeur';
	}
	if ($hasDepensesIdClinique) {
		$cols .= ', id_responsable_clinique';
	}
	if ($hasDepensesEtatSup) {
		$cols .= ', etat_superieur, date_validation_superieur, commentaire_validation_superieur';
		if ($hasDepensesSupValidePar) {
			$cols .= ', superieur_valide_par';
		}
	}
	if ($hasDepensesEtatClin) {
		$cols .= ', etat_clinique, date_validation_clinique, commentaire_validation_clinique';
		if ($hasDepensesClinValidePar) {
			$cols .= ', clinique_valide_par';
		}
	}
	if ($hasDepensesEtatCompta) {
		$cols .= ', etat_compta';
		if ($hasDepensesComptaPayePar) {
			$cols .= ', compta_paye_par';
		}
	}
	if ($hasDepensesEtatReception) {
		$cols .= ', etat_reception';
	}
	if ($hasDepensesDatePaiement) {
		$cols .= ', date_paiement';
	}
	if ($hasDepensesRefPaiement) {
		$cols .= ', reference_paiement';
	}
	if ($hasDepensesDateDebut) {
		$cols .= ', datedebut';
	}
	if ($hasDepensesDateFin) {
		$cols .= ', datefin';
	}
	$sql = 'SELECT ' . $cols . ' FROM depenses';
	if (!empty($where)) {
		$sql .= ' WHERE ' . implode(' AND ', $where);
	}
	$sql .= ' ORDER BY id_depense DESC';

	$st = $bdd->prepare($sql);
	$st->execute($args);
	$depenses = $st->fetchAll(PDO::FETCH_ASSOC);

	$depenseIds = array_map(fn($r) => (int)$r['id_depense'], $depenses);
	if (!empty($depenseIds)) {
		$ph = implode(',', array_fill(0, count($depenseIds), '?'));
		$stL = $bdd->prepare('SELECT * FROM depenses_lignes WHERE id_depense IN (' . $ph . ') ORDER BY id_ligne');
		$stL->execute($depenseIds);
		foreach ($stL->fetchAll(PDO::FETCH_ASSOC) as $ln) {
			$idDep = (int)$ln['id_depense'];
			if (!isset($depensesLinesByDepense[$idDep])) {
				$depensesLinesByDepense[$idDep] = [];
			}
			$depensesLinesByDepense[$idDep][] = $ln;
		}
	}
} catch (Throwable $e) {
	error_log('[demandes.php] depenses read: ' . $e->getMessage());
}

$logDemandes = [];
$logLinesByDemande = [];
try {
	$where = [];
	$args = [];
	$hasLogIdClinique = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'log_demandes', 'id_responsable_clinique') : false;
	$hasLogEtatClin = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'log_demandes', 'etat_clinique') : false;
	$hasLogEtatTrait = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'log_demandes', 'etat_traitement') : false;
	$hasLogEtatReception = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'log_demandes', 'etat_reception') : false;

	if ($view === 'to_validate') {
		if ($hasLogIdClinique) {
			$where[] = 'd.id_responsable_clinique = ?';
			$args[] = $currentUserId;
		} else {
			$where[] = 'd.id_validateur = ?';
			$args[] = $currentUserId;
		}
		$where[] = 'd.statut = "en_attente"';
	} elseif ($view === 'to_treat') {
		if (!$isLogistiqueModule) {
			$where[] = '1=0';
		} else {
			$where[] = 'd.statut = "validee"';
			if ($hasLogEtatClin) $where[] = 'd.etat_clinique = 1';
			if ($hasLogEtatTrait) {
				$where[] = 'd.etat_traitement = 0';
			}
		}
	} else {
		$where[] = 'd.id_user = ?';
		$args[] = $userId;
	}

	if ($filterType !== 'all' && $filterType !== 'logistique') {
		$where[] = '1=0';
	}
	if ($filterLogistiqueStatus !== null) {
		$where[] = 'd.statut = ?';
		$args[] = $filterLogistiqueStatus;
	}
	if ($dateStart !== '') {
		$where[] = 'DATE(d.date_creation) >= ?';
		$args[] = $dateStart;
	}
	if ($dateEnd !== '') {
		$where[] = 'DATE(d.date_creation) <= ?';
		$args[] = $dateEnd;
	}

	$sql = 'SELECT d.* FROM log_demandes d';
	if (!empty($where)) {
		$sql .= ' WHERE ' . implode(' AND ', $where);
	}
	$sql .= ' ORDER BY d.id_demande DESC';
	$st = $bdd->prepare($sql);
	$st->execute($args);
	$logDemandes = $st->fetchAll(PDO::FETCH_ASSOC);

	$ids = array_map(fn($r) => (int)$r['id_demande'], $logDemandes);
	if (!empty($ids)) {
		$ph = implode(',', array_fill(0, count($ids), '?'));
		$stL = $bdd->prepare(
			'SELECT l.*, a.nom AS article\n             FROM log_demandes_lignes l\n             JOIN log_articles a ON a.id_article = l.id_article\n             WHERE l.id_demande IN (' . $ph . ')\n             ORDER BY l.id_ligne'
		);
		$stL->execute($ids);
		foreach ($stL->fetchAll(PDO::FETCH_ASSOC) as $ln) {
			$idD = (int)$ln['id_demande'];
			if (!isset($logLinesByDemande[$idD])) {
				$logLinesByDemande[$idD] = [];
			}
			$logLinesByDemande[$idD][] = $ln;
		}
	}
} catch (Throwable $e) {
	error_log('[demandes.php] log_demandes read: ' . $e->getMessage());
}



// Responsable clinique (validateur unique)
$cliniqueIdForUser = getUserResponsableId($bdd, $userId);
$cliniquePseudoForUser = '';
try {
	if ($cliniqueIdForUser > 0) {
		$st = $bdd->prepare('SELECT pseudo FROM users WHERE id=? LIMIT 1');
		$st->execute([$cliniqueIdForUser]);
		$cliniquePseudoForUser = (string)($st->fetchColumn() ?: '');
	}
} catch (Throwable $e) {
	error_log('[demandes.php] clinique read: ' . $e->getMessage());
}

$articles = [];
try {
    $st = $bdd->query('SELECT id_article, nom, stock_actuel, unite FROM log_articles WHERE actif=1 ORDER BY nom');
    $articles = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[demandes.php] articles read: ' . $e->getMessage());
}

$budgets = [];
if ($isComptaModule) {
	try {
		$sqlB = 'SELECT * FROM budgets WHERE status=1';
		if (function_exists('dbTableHasColumn') && dbTableHasColumn($bdd, 'budgets', 'date_debut')) {
			$sqlB .= ' AND (date_debut IS NULL OR date_debut <= CURDATE())';
		}
		if (function_exists('dbTableHasColumn') && dbTableHasColumn($bdd, 'budgets', 'date_fin')) {
			$sqlB .= ' AND (date_fin IS NULL OR date_fin >= CURDATE())';
		}
		$sqlB .= ' ORDER BY nom_budget';
		$st = $bdd->query($sqlB);
		$budgets = $st->fetchAll(PDO::FETCH_ASSOC);
	} catch (Throwable $e) {
		error_log('[demandes.php] budgets read: ' . $e->getMessage());
	}
}

$userPseudoById = [];
try {
	$ids = [];
	foreach ($depenses as $d) {
		foreach (['id', 'validateur', 'id_responsable_clinique', 'superieur_valide_par', 'clinique_valide_par', 'compta_paye_par'] as $k) {
			if (isset($d[$k])) {
				$ids[] = (int)$d[$k];
			}
		}
	}
	foreach ($logDemandes as $l) {
		foreach (['id_user', 'id_validateur', 'id_responsable_clinique', 'valide_par', 'clinique_valide_par', 'traite_par'] as $k) {
			if (isset($l[$k])) {
				$ids[] = (int)$l[$k];
			}
		}
	}
	$ids = array_values(array_unique(array_filter($ids, fn($x) => (int)$x > 0)));
	if (!empty($ids)) {
		$ph = implode(',', array_fill(0, count($ids), '?'));
		$stU = $bdd->prepare('SELECT id, pseudo FROM users WHERE id IN (' . $ph . ')');
		$stU->execute($ids);
		foreach ($stU->fetchAll(PDO::FETCH_ASSOC) as $u) {
			$userPseudoById[(int)$u['id']] = (string)($u['pseudo'] ?? '');
		}
	}
} catch (Throwable $e) {
	error_log('[demandes.php] users pseudo map => ' . $e->getMessage());
}

function pseudoFromMap(array $map, int $id): string {
	if ($id <= 0) return '';
	$p = (string)($map[$id] ?? '');
	return $p !== '' ? $p : ('#' . $id);
}

function budgetRestantRow(array $b): float {
	if (isset($b['solde'])) return (float)$b['solde'];
	if (isset($b['montant_restant'])) return (float)$b['montant_restant'];
	$initial = (float)($b['montant_initial'] ?? 0);
	$utilise = 0.0;
	if (isset($b['montant_utilise'])) $utilise = (float)$b['montant_utilise'];
	elseif (isset($b['montant_utilisé'])) $utilise = (float)$b['montant_utilisé'];
	return $initial - $utilise;
}

function libelleStatusDepenseRow(array $d): string {
	$status = isset($d['status']) ? (int)$d['status'] : null;
	$etatClin = isset($d['etat_clinique']) ? (int)$d['etat_clinique'] : null;
	$etatCompta = isset($d['etat_compta']) ? (int)$d['etat_compta'] : null;
	$etatReception = isset($d['etat_reception']) ? (int)$d['etat_reception'] : null;

	if ($status === 3) return 'Annulée';
	if ($status === 2) return 'Refusée';
	if ($etatReception === 1) return 'Réception confirmée';
	if ($status === 4 || $etatCompta === 1) return 'Payée en attente réception';
	if ($status === 1 || $etatClin === 1) return 'Validée en attente paiement';
	return 'En attente validation clinique';
}

function libelleStatusLogistiqueRow(array $l): string {
	$statut = (string)($l['statut'] ?? '');
	$etatClin = isset($l['etat_clinique']) ? (int)$l['etat_clinique'] : null;
	$etatTrait = isset($l['etat_traitement']) ? (int)$l['etat_traitement'] : null;
	$etatReception = isset($l['etat_reception']) ? (int)$l['etat_reception'] : null;

	if ($statut === 'annulee') return 'Annulée';
	if ($statut === 'refusee' || $etatClin === 2) return 'Refusée';
	if ($etatReception === 1) return 'Réception confirmée';
	if ($statut === 'traitee' || $etatTrait === 1) return 'Traitée en attente réception';
	if ($etatClin === 1) return 'Validée clinique en attente de traitement';
	if ($statut === 'validee') return 'Validée en attente de traitement';
	return 'En attente validation clinique';
}

include(__DIR__ . '/header.php');
?>
<body>
	<section class="body">

		<?php include(__DIR__ . '/navbarmenu.php'); ?>

		<div class="inner-wrapper">
			<section role="main" class="content-body">
				<header class="page-header">
					<h2>Mes demandes</h2>
				</header>

				<div class="row">
					<div class="col-md-12">
						<section class="card">
							<div class="card-body">
								<div class="d-flex gap-2 mb-3">
									<a class="btn btn-sm <?= ($view === 'mine') ? 'btn-success' : 'btn-default' ?>" href="demandes.php?u=<?= (int)$userId ?>&view=mine">Mes demandes</a>
									<?php if ($isCliniqueResponsable): ?>
										<a class="btn btn-sm <?= ($view === 'to_validate') ? 'btn-primary' : 'btn-default' ?>" href="demandes.php?u=<?= (int)$userId ?>&view=to_validate">À valider</a>
									<?php endif; ?>
									<?php if ($isComptaModule): ?>
										<a class="btn btn-sm <?= ($view === 'to_pay') ? 'btn-primary' : 'btn-default' ?>" href="demandes.php?u=<?= (int)$userId ?>&view=to_pay&type=depense">À payer</a>
									<?php endif; ?>
									<?php if ($isLogistiqueModule): ?>
										<a class="btn btn-sm <?= ($view === 'to_treat') ? 'btn-primary' : 'btn-default' ?>" href="demandes.php?u=<?= (int)$userId ?>&view=to_treat&type=logistique">À traiter</a>
									<?php endif; ?>
								</div>
									<?php if ($view === 'mine' && !$isComptaModule): ?>
										<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalDepense">Faire une demande de dépense</button>
										<button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#modalLogistique">Faire une demande logistique</button>
									<?php endif; ?>
								<br> 
								<br>
								<form method="get" class="row g-2 align-items-end mb-3" id="filter-form">
									<input type="hidden" name="u" value="<?= (int)$userId ?>">
									<input type="hidden" name="view" value="<?= htmlspecialchars($view, ENT_QUOTES, 'UTF-8') ?>">
									<div class="col-md-3">
										<label class="form-label">Type</label>
										<select name="type" class="form-control" id="filter-type">
											<option value="all" <?= ($filterType==='all'?'selected':'') ?>>Tous</option>
											<option value="depense" <?= ($filterType==='depense'?'selected':'') ?>>Dépense</option>
											<option value="logistique" <?= ($filterType==='logistique'?'selected':'') ?>>Logistique</option>
										</select>
									</div>
									<div class="col-md-3">
										<label class="form-label">Statut</label>
										<select name="statut" class="form-control" id="filter-statut">
											<option value="" <?= ($filterStatus===''?'selected':'') ?>>Tous</option>
											<optgroup label="Dépense">
												<option value="0" data-kind="depense" <?= ($filterStatus==='0'?'selected':'') ?>>En cours</option>
												<option value="1" data-kind="depense" <?= ($filterStatus==='1'?'selected':'') ?>>Autorisé</option>
												<option value="2" data-kind="depense" <?= ($filterStatus==='2'?'selected':'') ?>>Non autorisé</option>
												<option value="3" data-kind="depense" <?= ($filterStatus==='3'?'selected':'') ?>>Annulée</option>
												<option value="4" data-kind="depense" <?= ($filterStatus==='4'?'selected':'') ?>>Payée</option>
											</optgroup>
											<optgroup label="Logistique">
												<option value="en_attente" data-kind="logistique" <?= ($filterStatus==='en_attente'?'selected':'') ?>>En attente</option>
												<option value="validee" data-kind="logistique" <?= ($filterStatus==='validee'?'selected':'') ?>>Validée</option>
												<option value="refusee" data-kind="logistique" <?= ($filterStatus==='refusee'?'selected':'') ?>>Refusée</option>
												<option value="traitee" data-kind="logistique" <?= ($filterStatus==='traitee'?'selected':'') ?>>Traitée</option>
												<option value="annulee" data-kind="logistique" <?= ($filterStatus==='annulee'?'selected':'') ?>>Annulée</option>
											</optgroup>
										</select>
									</div>
									<div class="col-md-2">
										<label class="form-label">Du</label>
										<input type="date" name="date_debut" class="form-control" value="<?= htmlspecialchars($dateStart, ENT_QUOTES, 'UTF-8') ?>">
									</div>
									<div class="col-md-2">
										<label class="form-label">Au</label>
										<input type="date" name="date_fin" class="form-control" value="<?= htmlspecialchars($dateEnd, ENT_QUOTES, 'UTF-8') ?>">
									</div>
									<div class="col-md-2">
										<button class="btn btn-primary w-100" type="submit">Filtrer</button>
									</div>
								</form>
								<hr>
								<br>
								<?php if ($flashSuccess): ?>
									<div class="alert alert-success"><strong>Succès</strong><br><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
								<?php endif; ?>
								<?php if ($flashError): ?>
									<div class="alert alert-danger"><strong>Erreur</strong><br><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
								<?php endif; ?>

								<table class="table table-bordered table-striped mb-0" id="datatable-default">
									<thead>
										<tr>
											<th>TYPE</th>
											<th>DESCRIPTION</th>
											<th>MONTANT</th>
											<th>ARTICLES</th>
											<th>DATE</th>
											<th>STATUT</th>
											<th>ACTIONS</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($depenses as $d): ?>
											<?php
												$status = $hasDepensesStatus ? (int)($d['status'] ?? 0) : null;
												$dateLabel = '';
												if ($hasDepensesDateDebut) {
													$dateLabel = (string)($d['datedebut'] ?? '');
												}
												if ($dateLabel === '') {
													$dateLabel = '—';
												}
											?>
											<tr>
												<td>Dépense</td>
												<td><?= htmlspecialchars((string)($d['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
												<td><?= number_format((float)($d['montant'] ?? 0), 0, ',', ' ') ?></td>
												<td>
													<?php
													$lines = $depensesLinesByDepense[(int)$d['id_depense']] ?? [];
													if (empty($lines)) {
														echo '—';
													} else {
														$chunks = [];
														foreach ($lines as $ln) {
															$chunks[] = htmlspecialchars((string)$ln['designation'], ENT_QUOTES, 'UTF-8') . ' x' . (int)$ln['quantite'];
														}
														echo implode('<br>', $chunks);
													}
													?>
												</td>
												<td><?= htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8') ?></td>
												<td>
													<?= htmlspecialchars(libelleStatusDepenseRow($d), ENT_QUOTES, 'UTF-8') ?>
													<?php
														$meta = [];
														if ($hasDepensesClinValidePar && (int)($d['etat_clinique'] ?? 0) === 1) {
															$meta[] = 'Validé : ' . htmlspecialchars(pseudoFromMap($userPseudoById, (int)($d['clinique_valide_par'] ?? 0)), ENT_QUOTES, 'UTF-8');
														}
														if ($hasDepensesComptaPayePar && ((int)($d['status'] ?? 0) === 4 || (int)($d['etat_compta'] ?? 0) === 1)) {
															$meta[] = 'Payé : ' . htmlspecialchars(pseudoFromMap($userPseudoById, (int)($d['compta_paye_par'] ?? 0)), ENT_QUOTES, 'UTF-8');
														}
														if (!empty($meta)) {
															echo '<div class="text-muted small" style="margin-top:2px">' . implode(' | ', $meta) . '</div>';
														}
													?>
												</td>
												<td>
													<?php
													$depId = (int)$d['id_depense'];
													$linesEdit = $depensesLinesByDepense[$depId] ?? [];
													$linesPayload = [];
													foreach ($linesEdit as $ln) {
														$linesPayload[] = [
															'designation' => (string)($ln['designation'] ?? ''),
															'qte' => (int)($ln['quantite'] ?? 0),
															'pu' => (float)($ln['prix_unitaire'] ?? 0),
														];
													}
														$canEdit = ($view === 'mine' && $hasDepensesStatus && (int)($d['status'] ?? 0) === 0 && (!$hasDepensesEtatClin || (int)($d['etat_clinique'] ?? 0) === 0));
													$canCancel = $canEdit;
														$canDelete = ($view === 'mine' && $hasDepensesStatus && (in_array((int)($d['status'] ?? 0), [2, 3], true) || ($hasDepensesEtatClin && (int)($d['etat_clinique'] ?? 0) === 2)));
														$canPrint = !($hasDepensesStatus && (in_array((int)($d['status'] ?? 0), [2, 3], true) || ($hasDepensesEtatClin && (int)($d['etat_clinique'] ?? 0) === 2)));
													$canConfirmReception = ($view === 'mine' && $hasDepensesStatus && (int)($d['status'] ?? 0) === 4 && $hasDepensesEtatReception && (int)($d['etat_reception'] ?? 0) === 0);
													$canValidateClin = ($view === 'to_validate' && $hasDepensesStatus && (int)($d['status'] ?? 0) === 0);
													$canPay = ($view === 'to_pay' && $hasDepensesStatus && (int)($d['status'] ?? 0) === 1);
													?>

															<?php if ($canPrint): ?>
																<button type="button" class="btn btn-sm btn-default js-open-print"
																	data-title="Impression demande de dépense"
																	data-url="../public/imprimer_demande_depense.php?id_depense=<?= $depId ?>">
																	Imprimer
																</button>
																<?php if ((int)($d['status'] ?? 0) === 4 || (int)($d['etat_compta'] ?? 0) === 1): ?>
																	<button type="button" class="btn btn-sm btn-default js-open-print"
																		data-title="Bon de paiement"
																		data-url="../public/imprimer_bon_paiement_depense.php?id_depense=<?= $depId ?>">
																		Bon paiement
																	</button>
																<?php endif; ?>
															<?php endif; ?>

													<?php if ($canEdit): ?>
														<button type="button" class="btn btn-sm btn-warning btn-edit-depense" data-bs-toggle="modal" data-bs-target="#modalEditDepense"
															data-id="<?= $depId ?>"
															data-description="<?= htmlspecialchars((string)($d['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
															data-lines="<?= htmlspecialchars(json_encode($linesPayload), ENT_QUOTES, 'UTF-8') ?>"
														>Modifier</button>
													<?php endif; ?>

													<?php if ($canCancel): ?>
														<form method="post" action="demandes.php?u=<?= (int)$userId ?>" style="display:inline">
															<input type="hidden" name="action" value="annuler_depense">
															<input type="hidden" name="id_depense" value="<?= $depId ?>">
															<button type="submit" class="btn btn-sm btn-default">Annuler</button>
														</form>
													<?php endif; ?>

														<?php if ($canDelete): ?>
															<form method="post" action="demandes.php?u=<?= (int)$userId ?>" style="display:inline" onsubmit="return confirm('Supprimer cette demande ?');">
																<input type="hidden" name="action" value="supprimer_depense">
																<input type="hidden" name="id_depense" value="<?= $depId ?>">
																<button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
															</form>
														<?php endif; ?>

													<?php if ($canValidateClin): ?>
														<form method="post" action="demandes.php?u=<?= (int)$userId ?>&view=to_validate" class="d-inline js-comment-form">
															<input type="hidden" name="action" value="valider_depense">
															<input type="hidden" name="id_depense" value="<?= $depId ?>">
															<input type="hidden" name="commentaire_validation" value="">
															<input type="hidden" name="signature" value="">
															<button type="submit" class="btn btn-sm btn-success">Valider</button>
														</form>
														<form method="post" action="demandes.php?u=<?= (int)$userId ?>&view=to_validate" class="d-inline js-comment-form">
															<input type="hidden" name="action" value="refuser_depense">
															<input type="hidden" name="id_depense" value="<?= $depId ?>">
															<input type="hidden" name="commentaire_validation" value="">
															<input type="hidden" name="signature" value="">
															<button type="submit" class="btn btn-sm btn-danger">Refuser</button>
														</form>
													<?php endif; ?>

													<?php if ($canPay): ?>
														<form method="post" action="demandes.php?u=<?= (int)$userId ?>&view=to_pay&type=depense" class="d-inline js-sign-form">
															<input type="hidden" name="action" value="payer_depense">
															<input type="hidden" name="id_depense" value="<?= $depId ?>">
															<input type="hidden" name="signature" value="">
															<select name="id_budget" class="form-control form-control-sm d-inline" style="width:220px;display:inline-block" required>
																<option value="">— Budget —</option>
																<?php foreach ($budgets as $b): ?>
																	<?php
																		$bid = (int)($b['id_budget'] ?? 0);
																		$bn = (string)($b['nom_budget'] ?? ('Budget #' . $bid));
																		$br = budgetRestantRow($b);
																	?>
																	<option value="<?= $bid ?>"><?= htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') ?> (reste: <?= number_format($br, 0, ',', ' ') ?>)</option>
																<?php endforeach; ?>
															</select>
															<input type="text" name="reference_paiement" class="form-control form-control-sm d-inline" style="width:140px;display:inline-block" placeholder="Référence">
															<button type="submit" class="btn btn-sm btn-primary">Payer</button>
														</form>
													<?php endif; ?>

													<?php if ($canConfirmReception): ?>
														<form method="post" action="demandes.php?u=<?= (int)$userId ?>" class="d-inline js-sign-form">
															<input type="hidden" name="action" value="confirmer_reception_depense">
															<input type="hidden" name="id_depense" value="<?= $depId ?>">
															<input type="hidden" name="signature" value="">
															<button type="submit" class="btn btn-sm btn-success">Réception</button>
														</form>
													<?php endif; ?>
												</td>
												</td>
											</tr>
										<?php endforeach; ?>

										<?php foreach ($logDemandes as $l): ?>
											<tr>
												<td>Logistique</td>
												<td><?= htmlspecialchars((string)($l['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
												<td>—</td>
												<td>
													<?php
													$lines = $logLinesByDemande[(int)$l['id_demande']] ?? [];
													if (empty($lines)) {
														echo '—';
													} else {
														$chunks = [];
														foreach ($lines as $ln) {
															$chunks[] = htmlspecialchars((string)$ln['article'], ENT_QUOTES, 'UTF-8') . ' x' . (int)$ln['quantite'];
														}
														echo implode('<br>', $chunks);
													}
													?>
												</td>
												<td><?= htmlspecialchars((string)($l['date_creation'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
												<td>
													<?= htmlspecialchars(libelleStatusLogistiqueRow($l), ENT_QUOTES, 'UTF-8') ?>
													<?php
															$meta = [];
															$cid = (int)($l['clinique_valide_par'] ?? 0);
															if ($cid <= 0) {
																$cid = (int)($l['valide_par'] ?? 0);
															}
															if ($cid > 0) {
																$meta[] = 'Validé : ' . htmlspecialchars(pseudoFromMap($userPseudoById, $cid), ENT_QUOTES, 'UTF-8');
															}
														if ((int)($l['traite_par'] ?? 0) > 0) {
															$meta[] = 'Traité : ' . htmlspecialchars(pseudoFromMap($userPseudoById, (int)($l['traite_par'] ?? 0)), ENT_QUOTES, 'UTF-8');
														}
														if (!empty($meta)) {
															echo '<div class="text-muted small" style="margin-top:2px">' . implode(' | ', $meta) . '</div>';
														}
													?>
												</td>
												<td>
													<?php
													$demId = (int)$l['id_demande'];
													$statut = (string)($l['statut'] ?? '');
													$linesEdit = $logLinesByDemande[$demId] ?? [];
													$linesPayload = [];
													foreach ($linesEdit as $ln) {
														$linesPayload[] = [
															'id_article' => (int)($ln['id_article'] ?? 0),
															'quantite' => (int)($ln['quantite'] ?? 0),
														];
													}
													$canEdit = ($view === 'mine' && $statut === 'en_attente');
													$canCancel = $canEdit;
														$canDelete = ($view === 'mine' && (in_array($statut, ['refusee', 'annulee'], true) || (int)($l['etat_clinique'] ?? 0) === 2));
														$canPrint = !(in_array($statut, ['refusee', 'annulee'], true) || (int)($l['etat_clinique'] ?? 0) === 2);
													$canValidateClin = ($view === 'to_validate' && $statut === 'en_attente');
													$canTreat = ($view === 'to_treat' && $statut === 'validee');
													$canConfirmReception = ($view === 'mine' && $statut === 'traitee' && (!isset($l['etat_reception']) || (int)$l['etat_reception'] === 0));
													?>

															<?php if ($canPrint): ?>
																<button type="button" class="btn btn-sm btn-default js-open-print"
																	data-title="Impression demande logistique"
																	data-url="../public/imprimer_demande_logistique.php?id_demande=<?= $demId ?>">
																	Imprimer
																</button>
															<?php endif; ?>

														<?php if ($statut === 'traitee' || (int)($l['etat_traitement'] ?? 0) === 1): ?>
															<button type="button" class="btn btn-sm btn-default js-open-print"
																data-title="Bon de sortie"
																data-url="../public/imprimer_bon_sortie_logistique.php?id_demande=<?= $demId ?>">
																Bon sortie
															</button>
														<?php endif; ?>

													<?php if ($canEdit): ?>
														<button type="button" class="btn btn-sm btn-warning btn-edit-logistique" data-bs-toggle="modal" data-bs-target="#modalEditLogistique"
															data-id="<?= $demId ?>"
															data-commentaire="<?= htmlspecialchars((string)($l['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
															data-lines="<?= htmlspecialchars(json_encode($linesPayload), ENT_QUOTES, 'UTF-8') ?>"
														>Modifier</button>
													<?php endif; ?>

													<?php if ($canCancel): ?>
														<form method="post" action="demandes.php?u=<?= (int)$userId ?>" style="display:inline">
															<input type="hidden" name="action" value="annuler_logistique">
															<input type="hidden" name="id_demande" value="<?= $demId ?>">
															<button type="submit" class="btn btn-sm btn-default">Annuler</button>
														</form>
													<?php endif; ?>

														<?php if ($canDelete): ?>
															<form method="post" action="demandes.php?u=<?= (int)$userId ?>" style="display:inline" onsubmit="return confirm('Supprimer cette demande ?');">
																<input type="hidden" name="action" value="supprimer_logistique">
																<input type="hidden" name="id_demande" value="<?= $demId ?>">
																<button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
															</form>
														<?php endif; ?>

													<?php if ($canValidateClin): ?>
														<form method="post" action="demandes.php?u=<?= (int)$userId ?>&view=to_validate" class="d-inline js-comment-form">
															<input type="hidden" name="action" value="valider_logistique">
															<input type="hidden" name="id_demande" value="<?= $demId ?>">
															<input type="hidden" name="commentaire_validation" value="">
															<input type="hidden" name="signature" value="">
															<button type="submit" class="btn btn-sm btn-success">Valider</button>
														</form>
														<form method="post" action="demandes.php?u=<?= (int)$userId ?>&view=to_validate" class="d-inline js-comment-form">
															<input type="hidden" name="action" value="refuser_logistique">
															<input type="hidden" name="id_demande" value="<?= $demId ?>">
															<input type="hidden" name="commentaire_validation" value="">
															<input type="hidden" name="signature" value="">
															<button type="submit" class="btn btn-sm btn-danger">Refuser</button>
														</form>
													<?php endif; ?>

													<?php if ($canTreat): ?>
														<form method="post" action="demandes.php?u=<?= (int)$userId ?>&view=to_treat&type=logistique" class="d-inline js-sign-form">
															<input type="hidden" name="action" value="traiter_logistique">
															<input type="hidden" name="id_demande" value="<?= $demId ?>">
															<input type="hidden" name="signature" value="">
															<button type="submit" class="btn btn-sm btn-primary">Traiter</button>
														</form>
													<?php endif; ?>

													<?php if ($canConfirmReception): ?>
														<form method="post" action="demandes.php?u=<?= (int)$userId ?>" class="d-inline js-sign-form">
															<input type="hidden" name="action" value="confirmer_reception_logistique">
															<input type="hidden" name="id_demande" value="<?= $demId ?>">
															<input type="hidden" name="signature" value="">
															<button type="submit" class="btn btn-sm btn-success">Réception</button>
														</form>
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

				<script>
				(function() {
					var typeEl = document.getElementById('filter-type');
					var statutEl = document.getElementById('filter-statut');
					if (!typeEl || !statutEl) return;

					function applyStatusFilterUI() {
						var t = typeEl.value;
						var opts = statutEl.querySelectorAll('option[data-kind]');
						opts.forEach(function(opt) {
							var kind = opt.getAttribute('data-kind');
							var shouldEnable = (t === 'all') || (t === kind);
							opt.disabled = !shouldEnable;
							opt.hidden = !shouldEnable;
						});

						// Si la valeur courante n'est plus compatible, remettre à "Tous"
						var selected = statutEl.options[statutEl.selectedIndex];
						if (selected && selected.disabled) {
							statutEl.value = '';
						}
					}

					typeEl.addEventListener('change', applyStatusFilterUI);
					applyStatusFilterUI();
				})();
				</script>

				<!-- Modal Dépense (multi-articles) -->
				<div class="modal fade" id="modalDepense" tabindex="-1" aria-hidden="true">
					<div class="modal-dialog modal-lg">
						<div class="modal-content">
							<form method="post" action="demandes.php?u=<?= (int)$userId ?>">
								<input type="hidden" name="action" value="create_depense">
								<div class="modal-header">
									<h5 class="modal-title">Faire une demande de depense</h5>
									<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
								</div>
								<div class="modal-body">
									<div class="row">
										<div class="col-md-12 mb-3">
											<label class="form-label">Objectif de la demande</label>
											<textarea name="description" class="form-control" rows="3" required></textarea>
										</div>
										<div class="col-md-12 mb-3">
											<label class="form-label">Articles demandés</label>
											<div id="depense-lines"></div>
											<button type="button" class="btn btn-sm btn-default" id="btn-add-depense-line">Ajouter une ligne</button>
											<small class="text-muted d-block mt-1">Le montant total est calculé automatiquement (qte × prix unitaire).</small>
										</div>
										<div class="col-md-12 mb-3">
															<label class="form-label">Validateur (responsable de la clinique)</label>
															<input type="text" class="form-control" value="<?= htmlspecialchars(($cliniquePseudoForUser !== '' ? $cliniquePseudoForUser : '—'), ENT_QUOTES, 'UTF-8') ?>" readonly>
															<small class="text-muted d-block mt-1">Après validation, la demande part vers la comptabilité (dépense) ou la logistique (sortie stock).</small>
										</div>
									</div>
								</div>
								<div class="modal-footer">
									<button type="button" class="btn btn-default" data-bs-dismiss="modal">Fermer</button>
									<button type="submit" class="btn btn-primary">Enregistrer</button>
								</div>
							</form>
						</div>
					</div>
				</div>

				<!-- Modal Logistique (multi-articles + validateur) -->
				<div class="modal fade" id="modalLogistique" tabindex="-1" aria-hidden="true">
					<div class="modal-dialog modal-lg">
						<div class="modal-content">
							<form method="post" action="demandes.php?u=<?= (int)$userId ?>">
								<input type="hidden" name="action" value="create_logistique">
								<div class="modal-header">
									<h5 class="modal-title">Faire une demande logistique</h5>
									<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
								</div>
								<div class="modal-body">
									<div class="row">
										<div class="col-md-12 mb-3">
											<label class="form-label">Articles demandés</label>
											<div id="logistique-lines"></div>
											<button type="button" class="btn btn-sm btn-default" id="btn-add-log-line">Ajouter une ligne</button>
										</div>
										<div class="col-md-12 mb-3">
											<label class="form-label">Motif / commentaire</label>
											<textarea name="commentaire" class="form-control" rows="3"></textarea>
										</div>
										<div class="col-md-12 mb-3">
															<label class="form-label">Validateur (responsable de la clinique)</label>
															<input type="text" class="form-control" value="<?= htmlspecialchars(($cliniquePseudoForUser !== '' ? $cliniquePseudoForUser : '—'), ENT_QUOTES, 'UTF-8') ?>" readonly>
															<small class="text-muted d-block mt-1">Après validation, la demande part vers la logistique pour la sortie stock.</small>
										</div>
									</div>
								</div>
								<div class="modal-footer">
									<button type="button" class="btn btn-default" data-bs-dismiss="modal">Fermer</button>
									<button type="submit" class="btn btn-info">Enregistrer</button>
								</div>
							</form>
						</div>
					</div>
				</div>

				<!-- Modal Édition Dépense -->
				<div class="modal fade" id="modalEditDepense" tabindex="-1" aria-hidden="true">
					<div class="modal-dialog modal-lg">
						<div class="modal-content">
							<form method="post" action="demandes.php?u=<?= (int)$userId ?>">
								<input type="hidden" name="action" value="update_depense">
								<input type="hidden" name="id_depense" id="edit-depense-id" value="">
								<div class="modal-header">
									<h5 class="modal-title">Modifier une demande de dépense</h5>
									<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
								</div>
								<div class="modal-body">
									<div class="row">
										<div class="col-md-12 mb-3">
											<label class="form-label">Objectif de la demande</label>
											<textarea name="description" class="form-control" id="edit-depense-description" rows="3" required></textarea>
										</div>
										<div class="col-md-12 mb-3">
											<label class="form-label">Articles demandés</label>
											<div id="depense-lines-edit"></div>
											<button type="button" class="btn btn-sm btn-default" id="btn-add-depense-line-edit">Ajouter une ligne</button>
										</div>
										<div class="col-md-12 mb-3">
															<label class="form-label">Validateur (responsable de la clinique)</label>
															<input type="text" class="form-control" value="<?= htmlspecialchars(($cliniquePseudoForUser !== '' ? $cliniquePseudoForUser : '—'), ENT_QUOTES, 'UTF-8') ?>" readonly>
										</div>
									</div>
								</div>
								<div class="modal-footer">
									<button type="button" class="btn btn-default" data-bs-dismiss="modal">Fermer</button>
									<button type="submit" class="btn btn-warning">Enregistrer</button>
								</div>
							</form>
						</div>
					</div>
				</div>

				<!-- Modal Édition Logistique -->
				<div class="modal fade" id="modalEditLogistique" tabindex="-1" aria-hidden="true">
					<div class="modal-dialog modal-lg">
						<div class="modal-content">
							<form method="post" action="demandes.php?u=<?= (int)$userId ?>">
								<input type="hidden" name="action" value="update_logistique">
								<input type="hidden" name="id_demande" id="edit-logistique-id" value="">
								<div class="modal-header">
									<h5 class="modal-title">Modifier une demande logistique</h5>
									<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
								</div>
								<div class="modal-body">
									<div class="row">
										<div class="col-md-12 mb-3">
											<label class="form-label">Articles demandés</label>
											<div id="logistique-lines-edit"></div>
											<button type="button" class="btn btn-sm btn-default" id="btn-add-log-line-edit">Ajouter une ligne</button>
										</div>
										<div class="col-md-12 mb-3">
											<label class="form-label">Motif / commentaire</label>
											<textarea name="commentaire" class="form-control" id="edit-logistique-commentaire" rows="3"></textarea>
										</div>
										<div class="col-md-12 mb-3">
															<label class="form-label">Validateur (responsable de la clinique)</label>
															<input type="text" class="form-control" value="<?= htmlspecialchars(($cliniquePseudoForUser !== '' ? $cliniquePseudoForUser : '—'), ENT_QUOTES, 'UTF-8') ?>" readonly>
										</div>
									</div>
								</div>
								<div class="modal-footer">
									<button type="button" class="btn btn-default" data-bs-dismiss="modal">Fermer</button>
									<button type="submit" class="btn btn-warning">Enregistrer</button>
								</div>
							</form>
						</div>
					</div>
				</div>

				<!-- Modal Impression (aperçu PDF + impression) -->
				<div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
					<div class="modal-dialog modal-xl modal-dialog-scrollable">
						<div class="modal-content">
							<div class="modal-header">
								<h5 class="modal-title" id="printModalTitle">Impression</h5>
								<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
							</div>
							<div class="modal-body p-0" style="height:80vh;">
								<iframe id="printFrame" src="about:blank" style="width:100%; height:100%;" frameborder="0"></iframe>
							</div>
							<div class="modal-footer">
								<button type="button" id="printBtn" class="btn btn-primary">Imprimer</button>
								<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
							</div>
						</div>
					</div>
				</div>

				<script>
				(function() {
					// Impression (modal + iframe) — PDF via wrappers imprimer_*.php
					var printModalEl = document.getElementById('printModal');
					var printFrameEl = document.getElementById('printFrame');
					var printBtnEl = document.getElementById('printBtn');
					var printTitleEl = document.getElementById('printModalTitle');
					var printOpenEl = document.getElementById('printOpen');

					function withAutoPrintDisabled(url) {
						if (!url) return url;
						return url + (url.indexOf('?') >= 0 ? '&' : '?') + 'autoprint=0';
					}

					function openPrintModal(url, titleText) {
						if (!url) return;
						if (printTitleEl) printTitleEl.textContent = titleText || 'Impression';
						if (printOpenEl) printOpenEl.href = url;
						if (printFrameEl) printFrameEl.src = withAutoPrintDisabled(url);
						if (window.bootstrap && window.bootstrap.Modal && printModalEl) {
							var instance = window.bootstrap.Modal.getInstance(printModalEl) || new window.bootstrap.Modal(printModalEl);
							instance.show();
							return;
						}
						if (window.jQuery && printModalEl && typeof jQuery(printModalEl).modal === 'function') {
							jQuery(printModalEl).modal('show');
							return;
						}
						// Fallback : ouvrir dans un nouvel onglet
						window.open(url, '_blank', 'noopener');
					}

					window.openPrintModal = openPrintModal;

					document.querySelectorAll('.js-open-print').forEach(function(btn) {
						btn.addEventListener('click', function() {
							openPrintModal(btn.getAttribute('data-url') || '', btn.getAttribute('data-title') || 'Impression');
						});
					});

					if (printBtnEl) {
						printBtnEl.addEventListener('click', function() {
							try {
								var win = printFrameEl && printFrameEl.contentWindow ? printFrameEl.contentWindow : null;
								if (win && typeof win.printPdf === 'function') {
									win.printPdf();
									return;
								}
								if (win && typeof win.print === 'function') {
									win.print();
								}
							} catch (e) {
								// noop
							}
						});
					}

					if (printModalEl) {
						printModalEl.addEventListener('hidden.bs.modal', function() {
							if (printFrameEl) printFrameEl.src = 'about:blank';
							if (printOpenEl) printOpenEl.href = 'about:blank';
						});
						if (window.jQuery && typeof jQuery(printModalEl).on === 'function') {
							jQuery(printModalEl).on('hidden.bs.modal', function() {
								if (printFrameEl) printFrameEl.src = 'about:blank';
								if (printOpenEl) printOpenEl.href = 'about:blank';
							});
						}
					}

					// Commentaires (optionnels) pour validations/refus
					function ensureSignature(form) {
						var sig = form.querySelector('input[name="signature"]');
						if (!sig) return true;
						if (sig.value && String(sig.value).trim() !== '') return true;
						var s = window.prompt('Signature (Nom et prénom) :', sig.value || '');
						if (s === null) return false;
						sig.value = String(s).trim();
						if (sig.value === '') {
							window.alert('Signature requise.');
							return false;
						}
						return true;
					}

					// Validations/approbations : commentaire (optionnel) + signature (requise)
					document.querySelectorAll('form.js-comment-form').forEach(function(form) {
						form.addEventListener('submit', function(e) {
							var input = form.querySelector('input[name="commentaire_validation"]');
							if (input) {
								var c = window.prompt('Commentaire (optionnel) :', input.value || '');
								if (c !== null) input.value = c;
							}
							if (!ensureSignature(form)) {
								e.preventDefault();
								e.stopPropagation();
							}
						});
					});

					// Paiement / traitement / réception : signature (requise)
					document.querySelectorAll('form.js-sign-form').forEach(function(form) {
						form.addEventListener('submit', function(e) {
							if (!ensureSignature(form)) {
								e.preventDefault();
								e.stopPropagation();
							}
						});
					});

					function escapeHtml(s) {
						return String(s).replace(/[&<>"']/g, function(c) {
							return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[c];
						});
					}

					function depenseLineRow(values) {
						values = values || {};
						var designation = values.designation || '';
						var qte = (values.qte != null ? values.qte : 1);
						var pu = (values.pu != null ? values.pu : '');
						return `
						<div class="row g-2 align-items-end mb-2 depense-line">
							<div class="col-md-6">
								<label class="form-label">Désignation</label>
								<input type="text" name="designation[]" class="form-control" value="${escapeHtml(designation)}" required>
							</div>
							<div class="col-md-2">
								<label class="form-label">Qte</label>
								<input type="number" name="qte[]" class="form-control" min="1" step="1" value="${escapeHtml(qte)}" required>
							</div>
							<div class="col-md-3">
								<label class="form-label">Prix unitaire</label>
								<input type="number" name="pu[]" class="form-control" min="0" step="0.01" value="${escapeHtml(pu)}" required>
							</div>
							<div class="col-md-1">
								<button type="button" class="btn btn-sm btn-danger btn-remove-line">X</button>
							</div>
						</div>`;
					}

					function logLineRow(optionsHtml) {
						return `
						<div class="row g-2 align-items-end mb-2 log-line">
							<div class="col-md-8">
								<label class="form-label">Article</label>
								<select name="id_article[]" class="form-control" required>${optionsHtml}</select>
							</div>
							<div class="col-md-3">
								<label class="form-label">Quantité</label>
								<input type="number" name="quantite[]" class="form-control" min="1" step="1" value="1" required>
							</div>
							<div class="col-md-1">
								<button type="button" class="btn btn-sm btn-danger btn-remove-line">X</button>
							</div>
						</div>`;
					}

					var depenseContainer = document.getElementById('depense-lines');
					var addDepenseBtn = document.getElementById('btn-add-depense-line');
					if (depenseContainer && addDepenseBtn) {
						addDepenseBtn.addEventListener('click', function() {
							depenseContainer.insertAdjacentHTML('beforeend', depenseLineRow());
						});
						depenseContainer.addEventListener('click', function(e) {
							if (e.target && e.target.classList.contains('btn-remove-line')) {
								e.target.closest('.depense-line').remove();
							}
						});
						// 1 ligne par défaut
						addDepenseBtn.click();
					}

					// Édition dépense
					var depenseEditContainer = document.getElementById('depense-lines-edit');
					var addDepenseEditBtn = document.getElementById('btn-add-depense-line-edit');
					if (depenseEditContainer && addDepenseEditBtn) {
						addDepenseEditBtn.addEventListener('click', function() {
							depenseEditContainer.insertAdjacentHTML('beforeend', depenseLineRow());
						});
						depenseEditContainer.addEventListener('click', function(e) {
							if (e.target && e.target.classList.contains('btn-remove-line')) {
								e.target.closest('.depense-line').remove();
							}
						});
					}

					var logContainer = document.getElementById('logistique-lines');
					var addLogBtn = document.getElementById('btn-add-log-line');
					var logEditContainer = document.getElementById('logistique-lines-edit');
					var addLogEditBtn = document.getElementById('btn-add-log-line-edit');
					if (logContainer && addLogBtn) {
						var options = '<option value="">—</option>';
						<?php foreach ($articles as $a): ?>
						options += '<option value="<?= (int)$a['id_article'] ?>"><?= htmlspecialchars((string)($a['nom'] ?? ''), ENT_QUOTES, 'UTF-8') ?> (Stock: <?= (int)($a['stock_actuel'] ?? 0) ?> <?= htmlspecialchars((string)($a['unite'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</option>';
						<?php endforeach; ?>
						addLogBtn.addEventListener('click', function() {
							logContainer.insertAdjacentHTML('beforeend', logLineRow(options));
						});
						logContainer.addEventListener('click', function(e) {
							if (e.target && e.target.classList.contains('btn-remove-line')) {
								e.target.closest('.log-line').remove();
							}
						});
						addLogBtn.click();
					}
					if (logEditContainer && addLogEditBtn) {
						var optionsEdit = '<option value="">—</option>';
						<?php foreach ($articles as $a): ?>
						optionsEdit += '<option value="<?= (int)$a['id_article'] ?>"><?= htmlspecialchars((string)($a['nom'] ?? ''), ENT_QUOTES, 'UTF-8') ?> (Stock: <?= (int)($a['stock_actuel'] ?? 0) ?> <?= htmlspecialchars((string)($a['unite'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</option>';
						<?php endforeach; ?>
						addLogEditBtn.addEventListener('click', function() {
							logEditContainer.insertAdjacentHTML('beforeend', logLineRow(optionsEdit));
						});
						logEditContainer.addEventListener('click', function(e) {
							if (e.target && e.target.classList.contains('btn-remove-line')) {
								e.target.closest('.log-line').remove();
							}
						});
					}

					// Pré-remplissage modals édition
					document.querySelectorAll('.btn-edit-depense').forEach(function(btn) {
						btn.addEventListener('click', function() {
							var id = btn.getAttribute('data-id') || '';
							var desc = btn.getAttribute('data-description') || '';
							var lines = [];
							try { lines = JSON.parse(btn.getAttribute('data-lines') || '[]'); } catch (e) { lines = []; }

							var idEl = document.getElementById('edit-depense-id');
							var descEl = document.getElementById('edit-depense-description');
							if (idEl) idEl.value = id;
							if (descEl) descEl.value = desc;
							if (depenseEditContainer) depenseEditContainer.innerHTML = '';
							if (depenseEditContainer) {
								if (!lines.length) {
									depenseEditContainer.insertAdjacentHTML('beforeend', depenseLineRow());
								} else {
									lines.forEach(function(l) {
										depenseEditContainer.insertAdjacentHTML('beforeend', depenseLineRow({designation: l.designation, qte: l.qte, pu: l.pu}));
									});
								}
							}
						});
					});

					document.querySelectorAll('.btn-edit-logistique').forEach(function(btn) {
						btn.addEventListener('click', function() {
							var id = btn.getAttribute('data-id') || '';
							var comm = btn.getAttribute('data-commentaire') || '';
							var lines = [];
							try { lines = JSON.parse(btn.getAttribute('data-lines') || '[]'); } catch (e) { lines = []; }

							var idEl = document.getElementById('edit-logistique-id');
							var commEl = document.getElementById('edit-logistique-commentaire');
							if (idEl) idEl.value = id;
							if (commEl) commEl.value = comm;
							if (logEditContainer) logEditContainer.innerHTML = '';
							if (logEditContainer) {
								if (!lines.length) {
									logEditContainer.insertAdjacentHTML('beforeend', logLineRow(optionsEdit || '<option value="">—</option>'));
								} else {
									lines.forEach(function(l) {
										logEditContainer.insertAdjacentHTML('beforeend', logLineRow(optionsEdit || '<option value="">—</option>'));
										var row = logEditContainer.querySelector('.log-line:last-child');
										if (row) {
											var sel = row.querySelector('select[name="id_article[]"]');
											var inp = row.querySelector('input[name="quantite[]"]');
											if (sel) sel.value = String(l.id_article || '');
											if (inp) inp.value = String(l.quantite || 1);
										}
									});
								}
							}
						});
					});
				})();
				</script>

			</section>
		</div>
	</section>
	<?php include(__DIR__ . '/footer.php'); ?>
