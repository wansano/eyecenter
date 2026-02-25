<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

if (!function_exists('appec_getAssuranceIdColumn')) {
    function appec_getAssuranceIdColumn(PDO $bdd): ?string
    {
        if (!function_exists('dbTableHasColumn')) return null;
        if (dbTableHasColumn($bdd, 'assurances', 'id_assurance')) return 'id_assurance';
        if (dbTableHasColumn($bdd, 'assurances', 'd_assurance')) return 'd_assurance';
        if (dbTableHasColumn($bdd, 'assurances', 'id')) return 'id';
        return null;
    }
}

function appec_getDevise(PDO $bdd): string
{
    $devise = 'GNF';
    try {
        $st = $bdd->query('SELECT devise FROM profil_entreprise LIMIT 1');
        if ($st) {
            $d = $st->fetchColumn();
            if ($d !== false && trim((string)$d) !== '') {
                $devise = trim((string)$d);
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
    return $devise;
}

function appec_parseMonthOrDefault($value): string
{
    $s = trim((string)$value);
    if (preg_match('/^\d{4}-\d{2}$/', $s)) return $s;
    return date('Y-m');
}

function appec_monthRange(string $ym): array
{
    $start = $ym . '-01';
    $end = date('Y-m-t', strtotime($start));
    return [$start, $end];
}

function appec_getAvailablePaymentAccounts(PDO $bdd, ?int $userId): array
{
    $comptes = [];
    try {
        // Ne pas proposer un compte déjà clôturé (preuve de caisse effectuée aujourd'hui pour ce compte)
        if ($userId) {
            $st = $bdd->prepare(
                'SELECT c.id_compte, c.types '
                . 'FROM comptes c '
                . 'WHERE c.status = ? '
                . 'AND NOT EXISTS ( '
                . '  SELECT 1 FROM preuvedecaisse p '
                . '  WHERE p.date_rapportement = ? AND p.id_user = ? AND p.compte = c.id_compte '
                . ') '
                . 'ORDER BY c.types'
            );
            $st->execute([1, date('Y-m-d'), $userId]);
        } else {
            $st = $bdd->prepare('SELECT id_compte, types FROM comptes WHERE defaut = 1 AND status = ? ORDER BY types');
            $st->execute([1]);
        }
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $comptes[] = ['id' => (int)$r['id_compte'], 'label' => (string)$r['types']];
        }
    } catch (Throwable $e) {
        $comptes = [];
    }
    return $comptes;
}

// Assurer l'existence des tables facturation
try {
    if (function_exists('appecEnsureAssuranceFacturationTables')) {
        appecEnsureAssuranceFacturationTables($bdd);
    }
    if (function_exists('appecEnsurePartAssurancesTable')) {
        appecEnsurePartAssurancesTable($bdd);
    }
} catch (Throwable $e) {
    // si création échoue, la page doit quand même s'afficher
}

$devise = appec_getDevise($bdd);

$currentUserId = isset($_SESSION['auth']) ? (int)$_SESSION['auth'] : null;
$comptesPaiement = appec_getAvailablePaymentAccounts($bdd, $currentUserId);

$selectedAssuranceId = isset($_GET['assurance_id']) ? (int)$_GET['assurance_id'] : 0;
$assuranceLocked = (isset($_GET['assurance_id']) && (int)$_GET['assurance_id'] > 0);
$currentYear = (int)date('Y');
$currentMonthNum = (int)date('n');
$yearMin = $currentYear - 1;
$yearMax = $currentYear;

// Mois (labels FR) pour le filtre
$monthLabels = [
    1 => 'Janvier',
    2 => 'Février',
    3 => 'Mars',
    4 => 'Avril',
    5 => 'Mai',
    6 => 'Juin',
    7 => 'Juillet',
    8 => 'Août',
    9 => 'Septembre',
    10 => 'Octobre',
    11 => 'Novembre',
    12 => 'Décembre',
];

// Support legacy: ?mois=YYYY-MM, et nouveau filtre: ?annee=YYYY&mois_num=MM
$selectedYear = isset($_GET['annee']) ? (int)$_GET['annee'] : 0;
$selectedMonthNum = isset($_GET['mois_num']) ? (int)$_GET['mois_num'] : 0;

$selectedMonthLegacy = appec_parseMonthOrDefault($_GET['mois'] ?? '');
if (($selectedYear <= 0 || $selectedMonthNum <= 0) && preg_match('/^(\d{4})-(\d{2})$/', $selectedMonthLegacy, $m)) {
    $selectedYear = (int)$m[1];
    $selectedMonthNum = (int)$m[2];
}

if ($selectedYear <= 0) $selectedYear = $currentYear;
if ($selectedMonthNum <= 0) $selectedMonthNum = $currentMonthNum;
if ($selectedMonthNum < 1) $selectedMonthNum = 1;
if ($selectedMonthNum > 12) $selectedMonthNum = 12;

// Sécurité: empêcher un mois futur dans l'année en cours (si on force l'URL)
if ($selectedYear === $currentYear && $selectedMonthNum > $currentMonthNum) {
    $selectedMonthNum = $currentMonthNum;
}

// Garder le format utilisé partout ailleurs (impressions, calculs)
$selectedMonth = sprintf('%04d-%02d', $selectedYear, $selectedMonthNum);
[$dateDebut, $dateFin] = appec_monthRange($selectedMonth);

// ===================== AJAX: Enregistrer règlement assureur =====================
if (isset($_POST['ajax_add_reglement'])) {
    header('Content-Type: application/json; charset=UTF-8');

    $assuranceId = isset($_POST['assurance_id']) ? (int)$_POST['assurance_id'] : 0;
    $mois = appec_parseMonthOrDefault($_POST['mois'] ?? '');
    [$pd, $pf] = appec_monthRange($mois);

    $montant = isset($_POST['montant']) ? (float)str_replace([' ', ','], ['', '.'], (string)$_POST['montant']) : 0.0;
    $montantInt = (float)round($montant, 0);
    $datePaiement = isset($_POST['date_paiement']) ? trim((string)$_POST['date_paiement']) : '';
    $compteId = isset($_POST['compte_id']) ? (int)$_POST['compte_id'] : 0;
    $reference = isset($_POST['reference']) ? trim((string)$_POST['reference']) : '';
    $commentaire = isset($_POST['commentaire']) ? trim((string)$_POST['commentaire']) : '';

    if ($assuranceId <= 0 || $montantInt <= 0 || $compteId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Paramètres invalides.']);
        exit;
    }

    try {
        if (function_exists('appecEnsureAssuranceFacturationTables')) {
            appecEnsureAssuranceFacturationTables($bdd);
        }
        if (function_exists('appecEnsurePartAssurancesTable')) {
            appecEnsurePartAssurancesTable($bdd);
        }

        // La facturation par assurance nécessite patients.assurance
        if (!function_exists('dbTableHasColumn') || !dbTableHasColumn($bdd, 'patients', 'assurance')) {
            echo json_encode(['success' => false, 'message' => 'Configuration DB: colonne patients.assurance introuvable.']);
            exit;
        }

        // Vérifier le compte et récupérer le libellé (mode)
        $compteLabel = '';
        $stCompte = $bdd->prepare('SELECT id_compte, types FROM comptes WHERE id_compte = ? LIMIT 1');
        $stCompte->execute([$compteId]);
        $rowCompte = $stCompte->fetch(PDO::FETCH_ASSOC);
        if (!$rowCompte) {
            echo json_encode(['success' => false, 'message' => 'Compte de paiement invalide.']);
            exit;
        }
        $compteLabel = (string)($rowCompte['types'] ?? '');

        // Calcul serveur du reste à payer pour la période
        $totCreanceLocal = 0.0;
        $totRegleLocal = 0.0;
        try {
            $st = $bdd->prepare(
                'SELECT COALESCE(SUM(pa.montant),0) '
                . 'FROM partAssurances pa '
                . 'INNER JOIN patients p ON p.id_patient = pa.patient '
                . 'WHERE p.assurance = ? AND pa.datepaiement BETWEEN ? AND ?'
            );
            $st->execute([$assuranceId, $pd, $pf]);
            $totCreanceLocal = (float)$st->fetchColumn();

            $st = $bdd->prepare(
                'SELECT COALESCE(SUM(montant),0) FROM assurance_reglements '
                . 'WHERE assurance_id = ? AND (periode_debut <=> ? AND periode_fin <=> ?)'
            );
            $st->execute([$assuranceId, $pd, $pf]);
            $totRegleLocal = (float)$st->fetchColumn();
        } catch (Throwable $e) {
            // ignore
        }

        $resteLocal = $totCreanceLocal - $totRegleLocal;
        if ($resteLocal < 0) $resteLocal = 0.0;
        if ($montantInt > $resteLocal) {
            echo json_encode(['success' => false, 'message' => 'Le montant ne doit pas dépasser le reste à payer.']);
            exit;
        }

        $hasCompteCol = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'assurance_reglements', 'compte_id') : false;
        $hasPreuveCol = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'assurance_reglements', 'preuve') : false;

        $dateForCheck = ($datePaiement !== '' ? ($datePaiement . ' 00:00:00') : date('Y-m-d 00:00:00'));
        $dateValue = ($datePaiement !== '' ? ($datePaiement . ' 00:00:00') : date('Y-m-d H:i:s'));

        $bdd->beginTransaction();
        try {
            // Anti-doublon
            $dupSqlBase = 'SELECT id FROM assurance_reglements '
                . 'WHERE assurance_id = ? AND montant = ? '
                . 'AND (periode_debut <=> ? AND periode_fin <=> ?) '
                . ($hasCompteCol ? 'AND (compte_id <=> ?) ' : '')
                . 'AND (reference <=> ?) '
                . 'AND DATE(date_paiement) = DATE(?) '
                . 'LIMIT 1';

            $dupParams = [$assuranceId, $montantInt, $pd, $pf];
            if ($hasCompteCol) $dupParams[] = $compteId;
            $dupParams[] = ($reference !== '' ? $reference : null);
            $dupParams[] = $dateForCheck;

            $existingId = 0;
            try {
                $stDup = $bdd->prepare($dupSqlBase . ' FOR UPDATE');
                $stDup->execute($dupParams);
                $existingId = (int)($stDup->fetchColumn() ?: 0);
            } catch (Throwable $lockErr) {
                // Certains schémas legacy (MyISAM) refusent FOR UPDATE
                $stDup = $bdd->prepare($dupSqlBase);
                $stDup->execute($dupParams);
                $existingId = (int)($stDup->fetchColumn() ?: 0);
            }
            if ($existingId > 0) {
                $bdd->rollBack();
                echo json_encode(['success' => false, 'message' => 'Ce paiement a déjà été enregistré.']);
                exit;
            }

            // Insert règlement
            $cols = ['assurance_id', 'date_paiement', 'montant', 'mode_paiement', 'reference', 'periode_debut', 'periode_fin', 'commentaire', 'caisse'];
            $ph = ['?', '?', '?', '?', '?', '?', '?', '?', '?'];
            $params = [
                $assuranceId,
                $dateValue,
                $montantInt,
                ($compteLabel !== '' ? $compteLabel : null),
                ($reference !== '' ? $reference : null),
                $pd,
                $pf,
                ($commentaire !== '' ? $commentaire : null),
                isset($_SESSION['auth']) ? (int)$_SESSION['auth'] : null,
            ];
            if ($hasCompteCol) {
                $cols[] = 'compte_id';
                $ph[] = '?';
                $params[] = $compteId;
            }
            if ($hasPreuveCol) {
                $cols[] = 'preuve';
                $ph[] = '?';
                $params[] = null;
            }

            $sqlIns = 'INSERT INTO assurance_reglements (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ')';
            $st = $bdd->prepare($sqlIns);
            $st->execute($params);
            $newReglementId = (int)$bdd->lastInsertId();

            // Upload preuve (optionnel)
            if ($hasPreuveCol && isset($_FILES['preuve']) && is_array($_FILES['preuve']) && (int)($_FILES['preuve']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $errUp = (int)($_FILES['preuve']['error'] ?? UPLOAD_ERR_OK);
                if ($errUp !== UPLOAD_ERR_OK) {
                    throw new Exception('Erreur upload preuve.');
                }
                $tmp = (string)($_FILES['preuve']['tmp_name'] ?? '');
                $orig = (string)($_FILES['preuve']['name'] ?? '');
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
                if (!in_array($ext, $allowed, true)) {
                    throw new Exception('Type de fichier non autorisé.');
                }
                // Dossier demandé: documents/piececomptable
                $destDirAbs = __DIR__ . '/../documents/piececomptable';
                if (!is_dir($destDirAbs)) {
                    if (!@mkdir($destDirAbs, 0775, true) && !is_dir($destDirAbs)) {
                        throw new Exception('Impossible de créer le dossier de destination.');
                    }
                }

                if (!is_uploaded_file($tmp)) {
                    throw new Exception('Fichier upload invalide (tmp introuvable).');
                }
                if (!is_writable($destDirAbs)) {
                    throw new Exception('Dossier de destination non inscriptible: documents/piececomptable');
                }
                $fname = 'reglement_' . $newReglementId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                $destAbs = $destDirAbs . '/' . $fname;
                if (!@move_uploaded_file($tmp, $destAbs)) {
                    throw new Exception('Impossible d\'enregistrer le fichier.');
                }
                // Chemin web relatif depuis /pages/apps/comptabilite/
                $destRel = '../documents/piececomptable/' . $fname;
                $stUp = $bdd->prepare('UPDATE assurance_reglements SET preuve = ? WHERE id = ?');
                $stUp->execute([$destRel, $newReglementId]);
            }

            // Mettre à jour la créance (credit) + débit de l'assureur (si colonnes)
            $assuranceIdCol = appec_getAssuranceIdColumn($bdd);
            $hasCredit = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'assurances', 'credit') : false;
            $hasDebit = function_exists('dbTableHasColumn') ? dbTableHasColumn($bdd, 'assurances', 'debit') : false;
            if ($assuranceIdCol && $hasCredit) {
                $up = $bdd->prepare('UPDATE assurances SET credit = GREATEST(COALESCE(credit,0) - ?, 0) WHERE ' . $assuranceIdCol . ' = ?');
                $up->execute([$montantInt, $assuranceId]);
            }
            if ($assuranceIdCol && $hasDebit) {
                $up = $bdd->prepare('UPDATE assurances SET debit = COALESCE(debit,0) + ? WHERE ' . $assuranceIdCol . ' = ?');
                $up->execute([$montantInt, $assuranceId]);
            }

            // Créditer le compte sélectionné
            $stUpdCompte = $bdd->prepare('UPDATE comptes SET debit = COALESCE(debit,0) + ? WHERE id_compte = ?');
            $stUpdCompte->execute([$montantInt, $compteId]);

            // Appliquer le règlement sur les lignes partAssurances (FIFO) : mise à jour montant_paye uniquement.
            $resteAPayer = $montantInt;
            $linesSqlBase = 'SELECT pa.id_part, pa.montant, pa.montant_paye '
                . 'FROM partAssurances pa '
                . 'INNER JOIN patients p ON p.id_patient = pa.patient '
                . 'WHERE p.assurance = ? AND pa.datepaiement BETWEEN ? AND ? AND (pa.montant - pa.montant_paye) > 0 '
                . 'ORDER BY pa.datepaiement ASC, pa.id_part ASC';

            try {
                $stLines = $bdd->prepare($linesSqlBase . ' FOR UPDATE');
                $stLines->execute([$assuranceId, $pd, $pf]);
            } catch (Throwable $lockErr) {
                $stLines = $bdd->prepare($linesSqlBase);
                $stLines->execute([$assuranceId, $pd, $pf]);
            }
            $lines = $stLines->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if (!empty($lines)) {
                $stUpLine = $bdd->prepare('UPDATE partAssurances SET montant_paye = ? WHERE id_part = ?');
                foreach ($lines as $ln) {
                    if ($resteAPayer <= 0) break;
                    $m = (float)($ln['montant'] ?? 0);
                    $mp = (float)($ln['montant_paye'] ?? 0);
                    $du = $m - $mp;
                    if ($du <= 0) continue;

                    $pay = ($resteAPayer >= $du) ? $du : $resteAPayer;
                    $newMp = $mp + $pay;
                    $stUpLine->execute([$newMp, (int)$ln['id_part']]);
                    $resteAPayer -= $pay;
                }
            }

            $bdd->commit();
        } catch (Throwable $txe) {
            $bdd->rollBack();
            throw $txe;
        }

        echo json_encode(['success' => true, 'reglement_id' => $newReglementId ?? null]);
        exit;
    } catch (Throwable $e) {
        error_log('[FACTURATION ASSURANCE ajax_add_reglement] ' . $e->getMessage());
        $msg = 'Erreur lors de l\'enregistrement.';
        // Aide au diagnostic: renvoyer le message SQL/exception pour les utilisateurs connectés
        if (isset($_SESSION['auth']) && (int)$_SESSION['auth'] > 0) {
            $msg = $e->getMessage();
        }
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
}

// ===================== Chargement assurances =====================
$assurances = [];
try {
    $idCol = appec_getAssuranceIdColumn($bdd) ?: 'id_assurance';
    $st = $bdd->prepare('SELECT ' . $idCol . ' AS id, assurance, status FROM assurances WHERE status <> 3 ORDER BY assurance');
    $st->execute();
    $assurances = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $assurances = [];
}

// ===================== Totaux & listes pour l'assureur sélectionné =====================
$totCreance = 0.0;
$totRegle = 0.0;
$reste = 0.0;
$passages = [];
$reglements = [];

if ($selectedAssuranceId > 0) {
    $startDt = $dateDebut . ' 00:00:00';
    $endDt = $dateFin . ' 23:59:59';

    try {
        if (function_exists('appecEnsurePartAssurancesTable')) {
            appecEnsurePartAssurancesTable($bdd);
        }

        // Total créances / réglé depuis partAssurances
        $st = $bdd->prepare(
            'SELECT COALESCE(SUM(pa.montant),0) '
            . 'FROM partAssurances pa '
            . 'INNER JOIN patients p ON p.id_patient = pa.patient '
            . 'WHERE p.assurance = ? AND pa.datepaiement BETWEEN ? AND ?'
        );
        $st->execute([$selectedAssuranceId, $dateDebut, $dateFin]);
        $totCreance = (float)$st->fetchColumn();

        $st = $bdd->prepare(
            'SELECT COALESCE(SUM(pa.montant_paye),0) '
            . 'FROM partAssurances pa '
            . 'INNER JOIN patients p ON p.id_patient = pa.patient '
            . 'WHERE p.assurance = ? AND pa.datepaiement BETWEEN ? AND ?'
        );
        $st->execute([$selectedAssuranceId, $dateDebut, $dateFin]);
        $totRegle = (float)$st->fetchColumn();

        // Total règlements (ceux affectés à la période; sinon fallback sur date_paiement)
        $st = $bdd->prepare(
            'SELECT COALESCE(SUM(montant),0) '
            . 'FROM assurance_reglements '
            . 'WHERE assurance_id = ? AND ( '
            . '  (periode_debut IS NOT NULL AND periode_fin IS NOT NULL AND periode_debut <= ? AND periode_fin >= ?) '
            . '  OR (periode_debut IS NULL AND periode_fin IS NULL AND date_paiement BETWEEN ? AND ?) '
            . ')'
        );
        $st->execute([$selectedAssuranceId, $dateFin, $dateDebut, $startDt, $endDt]);
        $totRegle = (float)$st->fetchColumn();

        $reste = $totCreance - $totRegle;
        if ($reste < 0) $reste = 0.0;

        // Liste passages
        $st = $bdd->prepare(
            'SELECT pa.id_part, pa.datepaiement, pa.types, pa.montant, pa.montant_paye, pa.solde, '
            . '       p.nom_patient, pay.code AS code_paiement '
            . 'FROM partAssurances pa '
            . 'INNER JOIN patients p ON p.id_patient = pa.patient '
            . 'LEFT JOIN paiements pay ON pay.id_paiement = pa.id_paiement '
            . 'WHERE p.assurance = ? AND pa.datepaiement BETWEEN ? AND ? '
            . 'ORDER BY pa.datepaiement ASC, pa.id_part ASC'
        );
        $st->execute([$selectedAssuranceId, $dateDebut, $dateFin]);
        $passages = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Liste règlements
        $regSelect = 'id, date_paiement, montant, mode_paiement, reference, commentaire';
        if (function_exists('dbTableHasColumn') && dbTableHasColumn($bdd, 'assurance_reglements', 'preuve')) {
            $regSelect .= ', preuve';
        }
        if (function_exists('dbTableHasColumn') && dbTableHasColumn($bdd, 'assurance_reglements', 'compte_id')) {
            $regSelect .= ', compte_id';
        }

        $st = $bdd->prepare(
            'SELECT ' . $regSelect . ' '
            . 'FROM assurance_reglements '
            . 'WHERE assurance_id = ? AND ( '
            . '  (periode_debut IS NOT NULL AND periode_fin IS NOT NULL AND periode_debut <= ? AND periode_fin >= ?) '
            . '  OR (periode_debut IS NULL AND periode_fin IS NULL AND date_paiement BETWEEN ? AND ?) '
            . ') '
            . 'ORDER BY date_paiement ASC, id ASC'
        );
        $st->execute([$selectedAssuranceId, $dateFin, $dateDebut, $startDt, $endDt]);
        $reglements = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('[FACTURATION ASSURANCE] ' . $e->getMessage());
    }
}

require('../PUBLIC/header.php');
?>

<body>
<section class="body">

    <?php require('../PUBLIC/navbarmenu.php'); ?>

    <div class="inner-wrapper">
        <section role="main" class="content-body">
            <header class="page-header">
                <h2>Facturation mensuelle des assurances</h2>
            </header>

            <div class="row">
                <div class="col-lg-12">
                    <section class="card">
                        <header class="card-header">
                            <h2 class="card-title">Filtres</h2>
                        </header>
                        <div class="card-body">
                            <form method="get" class="row g-3 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label">Assurance</label>
                                    <select name="assurance_id" class="form-control" <?= $assuranceLocked ? 'disabled' : 'required' ?>>
                                        <option value="">-- Choisir --</option>
                                        <?php foreach ($assurances as $a): ?>
                                            <option value="<?= (int)$a['id'] ?>" <?= ((int)$a['id'] === (int)$selectedAssuranceId) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars((string)$a['assurance'], ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($assuranceLocked): ?>
                                        <input type="hidden" name="assurance_id" value="<?= (int)$selectedAssuranceId ?>">
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Année</label>
                                    <select name="annee" class="form-control" id="filterYear" required>
                                        <?php for ($y = (int)$yearMin; $y <= (int)$yearMax; $y++): ?>
                                            <option value="<?= (int)$y ?>" <?= ((int)$y === (int)$selectedYear) ? 'selected' : '' ?>>
                                                <?= (int)$y ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Mois</label>
                                    <select name="mois_num" class="form-control" id="filterMonth" required>
                                        <?php foreach ($monthLabels as $mNum => $mLabel): ?>
                                            <option value="<?= (int)$mNum ?>" <?= ((int)$mNum === (int)$selectedMonthNum) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($mLabel, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary w-100">Afficher</button>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
            </div>

            <?php if ($selectedAssuranceId > 0): ?>
                <div class="row">
                    <div class="col-lg-12">
                        <section class="card">
                            <header class="card-header d-flex justify-content-between align-items-center">
                                <h2 class="card-title">Situation</h2>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-default" id="btnPrintFacture">Imprimer facture</button>
                                    <button type="button" class="btn btn-default" id="btnPrintRapport">Rapport patients</button>
                                    <button type="button" class="btn btn-success" id="btnAddReglement">Enregistrer paiement assureur</button>
                                </div>
                            </header>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3"><strong>Début :</strong> <?= htmlspecialchars($dateDebut, ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="col-md-3"><strong>Fin :</strong> <?= htmlspecialchars($dateFin, ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="col-md-2"><strong>Créance :</strong> <?= number_format($totCreance, 0, ',', ' ') ?> <?= htmlspecialchars($devise, ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="col-md-2"><strong>Réglé :</strong> <?= number_format($totRegle, 0, ',', ' ') ?> <?= htmlspecialchars($devise, ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="col-md-2"><strong>Reste :</strong> <?= number_format($reste, 0, ',', ' ') ?> <?= htmlspecialchars($devise, ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <section class="card">
                            <header class="card-header">
                                <h2 class="card-title">Historique paiements</h2>
                            </header>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped mb-0">
                                        <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Montant</th>
                                            <th>Mode</th>
                                            <th>Référence</th>
                                            <th>Preuve</th>
                                            <th>Commentaire</th>
                                            <th>Actions</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (empty($reglements)): ?>
                                            <tr><td colspan="7" class="text-center">Aucun règlement pour cette période.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($reglements as $r): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars((string)$r['date_paiement'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-end"><?= number_format((float)$r['montant'], 0, ',', ' ') ?> <?= htmlspecialchars($devise, ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= htmlspecialchars((string)($r['mode_paiement'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= htmlspecialchars((string)($r['reference'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td>
                                                        <?php if (!empty($r['preuve'])): ?>
                                                            <button type="button" class="btn btn-default btn-sm btnVoirPreuve" data-url="<?= htmlspecialchars((string)$r['preuve'], ENT_QUOTES, 'UTF-8') ?>">Voir</button>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars((string)($r['commentaire'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td>
                                                        <button type="button" class="btn btn-default btn-sm btnPrintBon" data-id="<?= (int)$r['id'] ?>">Bon</button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-12">
                    <section class="card">
                        <header class="card-header">
                            <h2 class="card-title">Historique prestation</h2>
                        </header>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped mb-0">
                                    <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Patient</th>
                                        <th>Code</th>
                                        <th>Montant</th>
                                        <th>Montant payé</th>
                                        <th>Solde</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($passages)): ?>
                                        <tr><td colspan="6" class="text-center">Aucune donnée pour cette période.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($passages as $p): ?>
                                            <tr>
                                                <td><?= htmlspecialchars((string)$p['datepaiement'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars((string)($p['nom_patient'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars((string)($p['code_paiement'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-end"><?= number_format((float)$p['montant'], 0, ',', ' ') ?> <?= htmlspecialchars($devise, ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-end"><?= number_format((float)$p['montant_paye'], 0, ',', ' ') ?> <?= htmlspecialchars($devise, ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-end"><?= number_format((float)$p['solde'], 0, ',', ' ') ?> <?= htmlspecialchars($devise, ENT_QUOTES, 'UTF-8') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

        </section>
    </div>

</section>

<!-- Modal impression (iframe) -->
<div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl" style="max-width: 50vw;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="printModalTitle">Impression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="height: 80vh;">
                <iframe id="printModalFrame" title="Aperçu impression" src="about:blank" style="width:100%; height:100%; border:0;"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary" id="btnPrintModal"><i class="fa fa-print"></i> Imprimer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal règlement -->
<div class="modal fade" id="reglementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="max-width: 50vw;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Enregistrer un paiement assureur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <strong>Reste à payer :</strong>
                    <span id="resteAPayerText"><?= number_format((float)$reste, 0, ',', ' ') ?> <?= htmlspecialchars($devise, ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <form id="reglementForm" enctype="multipart/form-data">
                    <input type="hidden" name="ajax_add_reglement" value="1">
                    <input type="hidden" name="assurance_id" value="<?= (int)$selectedAssuranceId ?>">
                    <input type="hidden" name="mois" value="<?= htmlspecialchars($selectedMonth, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Date paiement</label>
                            <input type="date" class="form-control" name="date_paiement" value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Montant (<?= htmlspecialchars($devise, ENT_QUOTES, 'UTF-8') ?>)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="montant" id="reglementMontant" required>
                            <small class="text-muted">Ne doit pas dépasser le reste à payer.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Mode de paiement</label>
                            <select class="form-control" name="compte_id" required>
                                <option value="">-- Choisir --</option>
                                <?php foreach ($comptesPaiement as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars((string)$c['label'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Référence</label>
                            <input type="text" class="form-control" name="reference">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Document de preuve (optionnel)</label>
                            <input type="file" class="form-control" name="preuve" accept=".pdf,.jpg,.jpeg,.png">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Commentaire</label>
                            <textarea class="form-control" name="commentaire" rows="2"></textarea>
                        </div>
                    </div>
                </form>
                <div class="alert alert-danger d-none" id="reglementError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-success" id="btnSaveReglement">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    // Filtre Année/Mois: désactiver les mois futurs pour l'année en cours
    var currentYear = <?= (int)$currentYear ?>;
    var currentMonthNum = <?= (int)$currentMonthNum ?>;

    function appecUpdateMonthOptions(){
        var yearSel = document.getElementById('filterYear');
        var monthSel = document.getElementById('filterMonth');
        if (!yearSel || !monthSel) return;

        var y = Number(yearSel.value || 0);
        var maxAllowed = 12;
        if (y === currentYear) {
            maxAllowed = Number(currentMonthNum || 12);
        } else if (y > currentYear) {
            maxAllowed = 0;
        }

        var hasAllowed = false;
        Array.prototype.forEach.call(monthSel.options, function(opt){
            var m = Number(opt.value || 0);
            var allowed = (maxAllowed > 0) ? (m >= 1 && m <= maxAllowed) : false;
            opt.disabled = !allowed;
            if (allowed) hasAllowed = true;
        });

        monthSel.disabled = !hasAllowed;

        // Corriger la sélection si elle pointe sur un mois désormais interdit
        var curM = Number(monthSel.value || 0);
        if (!hasAllowed) {
            monthSel.value = '';
            return;
        }
        if (!(curM >= 1 && curM <= maxAllowed)) {
            monthSel.value = String(maxAllowed);
        }
    }

    var yearSelInit = document.getElementById('filterYear');
    if (yearSelInit) {
        yearSelInit.addEventListener('change', appecUpdateMonthOptions);
    }
    appecUpdateMonthOptions();

    // Impression modal helper
    if (typeof window.openPrintModal !== 'function') {
        window.openPrintModal = function(url, titleText){
            var modalEl = document.getElementById('printModal');
            var frame = document.getElementById('printModalFrame');
            var titleEl = document.getElementById('printModalTitle');
            if (!modalEl || !frame) return;
            if (titleEl) titleEl.textContent = titleText || 'Impression';
            frame.src = url;
            if (window.bootstrap && window.bootstrap.Modal) {
                var inst = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
                inst.show();
            } else if (window.jQuery && typeof jQuery(modalEl).modal === 'function') {
                jQuery(modalEl).modal('show');
            }
        };
    }

    var btnPrint = document.getElementById('btnPrintModal');
    if (btnPrint) {
        btnPrint.addEventListener('click', function(){
            try {
                var frame = document.getElementById('printModalFrame');
                if (frame && frame.contentWindow) {
                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                }
            } catch (e) {}
        });
    }

    var assuranceId = <?= (int)$selectedAssuranceId ?>;
    var mois = <?= json_encode($selectedMonth) ?>;
    var resteAPayer = <?= json_encode((float)$reste) ?>;
    var devise = <?= json_encode($devise) ?>;

    var btnFact = document.getElementById('btnPrintFacture');
    if (btnFact) {
        btnFact.addEventListener('click', function(){
            if (!assuranceId) return;
            var url = '../impression/_facture_assurance_mensuelle.php?assurance_id=' + encodeURIComponent(String(assuranceId)) + '&mois=' + encodeURIComponent(String(mois));
            window.openPrintModal(url, 'Facture assurance');
        });
    }

    var btnRap = document.getElementById('btnPrintRapport');
    if (btnRap) {
        btnRap.addEventListener('click', function(){
            if (!assuranceId) return;
            var url = '../impression/_rapport_patients_assurance.php?assurance_id=' + encodeURIComponent(String(assuranceId)) + '&mois=' + encodeURIComponent(String(mois));
            window.openPrintModal(url, 'Rapport patients');
        });
    }

    // Modal règlement
    var reglementModalEl = document.getElementById('reglementModal');
    var btnAdd = document.getElementById('btnAddReglement');
    if (btnAdd && reglementModalEl) {
        btnAdd.addEventListener('click', function(){
            var err = document.getElementById('reglementError');
            if (err) { err.classList.add('d-none'); err.textContent = ''; }

            var resteText = document.getElementById('resteAPayerText');
            if (resteText) {
                try {
                    resteText.textContent = (Number(resteAPayer || 0)).toLocaleString('fr-FR') + ' ' + String(devise || '');
                } catch (e) {}
            }

            var montantInput = document.getElementById('reglementMontant');
            if (montantInput) {
                var max = Number(resteAPayer || 0);
                montantInput.max = String(max > 0 ? max : 0);
                montantInput.value = (max > 0) ? String(max) : '';
            }

            if (window.bootstrap && window.bootstrap.Modal) {
                var inst = window.bootstrap.Modal.getInstance(reglementModalEl) || new window.bootstrap.Modal(reglementModalEl);
                inst.show();
            } else if (window.jQuery && typeof jQuery(reglementModalEl).modal === 'function') {
                jQuery(reglementModalEl).modal('show');
            }
        });
    }

    var montantInputGlobal = document.getElementById('reglementMontant');
    if (montantInputGlobal) {
        montantInputGlobal.addEventListener('input', function(){
            var max = Number(montantInputGlobal.max || resteAPayer || 0);
            var val = Number(String(montantInputGlobal.value || '0').replace(',', '.'));
            if (max > 0 && val > max) {
                montantInputGlobal.value = String(max);
            }
        });
    }

    var btnSave = document.getElementById('btnSaveReglement');
    if (btnSave) {
        btnSave.addEventListener('click', async function(){
            var form = document.getElementById('reglementForm');
            var err = document.getElementById('reglementError');
            if (!form) return;
            if (err) { err.classList.add('d-none'); err.textContent = ''; }

            if (!resteAPayer || Number(resteAPayer) <= 0) {
                if (err) { err.textContent = 'Aucun reste à payer pour cette période.'; err.classList.remove('d-none'); }
                return;
            }

            var montantInput = document.getElementById('reglementMontant');
            var montantVal = montantInput ? Number(String(montantInput.value || '0').replace(',', '.')) : 0;
            if (!(montantVal > 0)) {
                if (err) { err.textContent = 'Veuillez saisir un montant valide.'; err.classList.remove('d-none'); }
                return;
            }
            if (montantVal > Number(resteAPayer || 0)) {
                if (err) { err.textContent = 'Le montant ne doit pas dépasser le reste à payer.'; err.classList.remove('d-none'); }
                return;
            }

            var fd = new FormData(form);
            try {
                btnSave.disabled = true;
                var res = await fetch(window.location.pathname + window.location.search, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                var data = await res.json();
                if (!data || !data.success) {
                    var msg = (data && data.message) ? data.message : 'Erreur.';
                    if (err) { err.textContent = msg; err.classList.remove('d-none'); }
                    btnSave.disabled = false;
                    return;
                }
                window.location.reload();
            } catch (e) {
                if (err) { err.textContent = 'Erreur réseau.'; err.classList.remove('d-none'); }
                btnSave.disabled = false;
            }
        });
    }

    // Actions règlements
    document.querySelectorAll('.btnPrintBon').forEach(function(btn){
        btn.addEventListener('click', function(){
            var id = btn.getAttribute('data-id');
            if (!id) return;
            var url = '../impression/_bon_paiement_assurance.php?reglement_id=' + encodeURIComponent(String(id));
            window.openPrintModal(url, 'Bon de paiement');
        });
    });

    document.querySelectorAll('.btnVoirPreuve').forEach(function(btn){
        btn.addEventListener('click', function(){
            var url = btn.getAttribute('data-url');
            if (!url) return;
            window.openPrintModal(url, 'Document de preuve');
        });
    });
})();
</script>

<?php require('../PUBLIC/footer.php'); ?>
</body>
</html>
