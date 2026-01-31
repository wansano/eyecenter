<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

// Devise de l'entreprise (fallback)
if (!isset($devise) || trim((string)$devise) === '') {
    $devise = 'GNF';
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function bulletin_table_exists(PDO $bdd, string $table): bool
{
    try {
        $st = $bdd->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);
        return (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        error_log('[bulletinsalaire] tableExists ' . $table . ': ' . $e->getMessage());
        return false;
    }
}

function bulletin_get_employes_column_map(PDO $bdd): array
{
    $fields = [];
    try {
        $stmt = $bdd->query('SHOW COLUMNS FROM employes');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $r) {
            $f = (string) ($r['Field'] ?? '');
            if ($f !== '') {
                $fields[$f] = true;
            }
        }
    } catch (Throwable $e) {
        $fields = [];
    }

    $nameCol = isset($fields['nomEmploye']) ? 'nomEmploye' : (isset($fields['nom_employe']) ? 'nom_employe' : 'nomEmploye');
    $salaryCol = isset($fields['salaireBase']) ? 'salaireBase' : (isset($fields['salaire']) ? 'salaire' : 'salaireBase');

    return [
        'name' => $nameCol,
        'salary' => $salaryCol,
        'prime_transport' => isset($fields['PrimeTransport']) ? 'PrimeTransport' : null,
        'prime_logement' => isset($fields['PrimeLogement']) ? 'PrimeLogement' : null,
        'prime_vie' => isset($fields['PrimeVie']) ? 'PrimeVie' : null,
    ];
}

function bulletin_to_float($value): float
{
    if ($value === null) return 0.0;
    if (is_int($value) || is_float($value)) return (float) $value;
    $s = trim((string) $value);
    if ($s === '') return 0.0;
    $s = str_replace(["\xC2\xA0", ' '], '', $s);
    $s = str_replace(',', '.', $s);
    if (substr_count($s, '.') > 1) {
        $s = str_replace('.', '', $s);
    }
    return is_numeric($s) ? (float) $s : 0.0;
}

function bulletin_fmt_money(float $n, string $devise = ''): string
{
    $out = number_format($n, (abs($n - round($n)) > 0 ? 2 : 0), ',', ' ');
    return trim($out . ($devise !== '' ? (' ' . $devise) : ''));
}

function bulletin_get_paid_employe_ids_for_period(PDO $bdd, string $periodeMonth): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $periodeMonth)) {
        return [];
    }

    try {
        $periode = $periodeMonth . '-01';
        $st = $bdd->prepare('SELECT DISTINCT id_employe FROM bulletins_salaire WHERE periode = ? AND paye = 1');
        $st->execute([$periode]);
        $ids = [];
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) ($r['id_employe'] ?? 0);
            if ($id > 0) $ids[] = $id;
        }
        return $ids;
    } catch (Throwable $e) {
        error_log('[bulletinsalaire] paid_employes: ' . $e->getMessage());
        return [];
    }
}

$alert = null;
$error = null;

// Période (filtre année/mois comme facturation assurance)
$currentYear = (int)date('Y');
$currentMonthNum = (int)date('n');
$yearMin = 2025;
$yearMax = $currentYear;

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

$selectedYear = isset($_GET['annee']) ? (int)$_GET['annee'] : 0;
$selectedMonthNum = isset($_GET['mois_num']) ? (int)$_GET['mois_num'] : 0;

// Support legacy: ?periode=YYYY-MM
$periodeLegacy = (string)($_GET['periode'] ?? '');
if (($selectedYear <= 0 || $selectedMonthNum <= 0) && preg_match('/^(\d{4})-(\d{2})$/', $periodeLegacy, $m)) {
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
if ($selectedYear > $currentYear) {
    $selectedYear = $currentYear;
    $selectedMonthNum = $currentMonthNum;
}

$periodeMonth = sprintf('%04d-%02d', $selectedYear, $selectedMonthNum);
$periode = $periodeMonth . '-01';

if (!bulletin_table_exists($bdd, 'bulletins_salaire')) {
    $error = 'La table bulletins_salaire est introuvable. Exécutez db/bulletins_salaire.sql.';
}

// Endpoint AJAX: liste des employés déjà payés pour une période (YYYY-MM)
if (!$error && isset($_GET['ajax']) && (string)$_GET['ajax'] === 'paid_employes') {
    header('Content-Type: application/json; charset=utf-8');
    $periodeMonthAjax = (string)($_GET['periode_month'] ?? '');
    echo json_encode([
        'ok' => true,
        'paid_employe_ids' => bulletin_get_paid_employe_ids_for_period($bdd, $periodeMonthAjax),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Devise (si profil entreprise existe)
$clinique = getSingleRow($bdd, 'profil_entreprise') ?: [];
if (isset($clinique['devise']) && trim((string) $clinique['devise']) !== '') {
    $devise = (string) $clinique['devise'];
}

// Traitement POST (création/modification/paiement)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'save_bulletin') {
        $idBulletin = (int) ($_POST['id_bulletin'] ?? 0);
        $idEmploye = (int) ($_POST['id_employe'] ?? ($_POST['id_employe_hidden'] ?? 0));
        $periodeMonthPost = (string) ($_POST['periode_month'] ?? $periodeMonth);
        if (!preg_match('/^\d{4}-\d{2}$/', $periodeMonthPost)) {
            $periodeMonthPost = $periodeMonth;
        }
        $periodePost = $periodeMonthPost . '-01';

        if ($idEmploye <= 0) {
            $error = 'Veuillez sélectionner un employé.';
        } else {
            $salaireBase = bulletin_to_float($_POST['salaire_base'] ?? null);
            $primeTransport = bulletin_to_float($_POST['prime_transport'] ?? null);
            $primeLogement = bulletin_to_float($_POST['prime_logement'] ?? null);
            $primeVie = bulletin_to_float($_POST['prime_vie'] ?? null);
            $heuresSup = bulletin_to_float($_POST['heures_sup'] ?? null);
            $autresPrimes = bulletin_to_float($_POST['autres_primes'] ?? null);
            $rts = bulletin_to_float($_POST['rts'] ?? null);
            // Autres retenues supprimées (cohérence avec le PDF)
            $autresRetenues = 0.0;

            $modeReglement = trim((string) ($_POST['mode_reglement'] ?? ''));
            $datePaiement = trim((string) ($_POST['date_paiement'] ?? ''));
            $paye = (int) ($_POST['paye'] ?? 0) === 1 ? 1 : 0;
            if ($paye === 1 && $datePaiement === '') {
                $datePaiement = date('Y-m-d');
            }
            if ($paye === 0) {
                // On laisse la date si déjà payée (édition), sinon on vide
                if ($idBulletin <= 0) {
                    $datePaiement = '';
                }
            }

            $totalBrut = $salaireBase + $primeTransport + $primeLogement + $primeVie + $heuresSup + $autresPrimes;
            $net = $totalBrut - $rts;

            try {
                // Si pas d'id, on upsert par (id_employe, periode)
                if ($idBulletin <= 0) {
                    $stFind = $bdd->prepare('SELECT id_bulletin, numero FROM bulletins_salaire WHERE id_employe = ? AND periode = ? LIMIT 1');
                    $stFind->execute([$idEmploye, $periodePost]);
                    $existing = $stFind->fetch(PDO::FETCH_ASSOC);
                    if ($existing) {
                        $idBulletin = (int) ($existing['id_bulletin'] ?? 0);
                    }
                }

                if ($idBulletin > 0) {
                    $sqlUp = 'UPDATE bulletins_salaire
                              SET id_employe = ?,
                                  periode = ?,
                                  mode_reglement = ?,
                                  date_paiement = ?,
                                  devise = ?,
                                  salaire_base = ?,
                                  prime_transport = ?,
                                  prime_logement = ?,
                                  prime_vie = ?,
                                  heures_sup = ?,
                                  autres_primes = ?,
                                  rts = ?,
                                  autres_retenues = ?,
                                  total_brut = ?,
                                  net_a_payer = ?,
                                  paye = ?
                              WHERE id_bulletin = ?';
                    $stUp = $bdd->prepare($sqlUp);
                    $stUp->execute([
                        $idEmploye,
                        $periodePost,
                        ($modeReglement !== '' ? $modeReglement : null),
                        ($datePaiement !== '' ? $datePaiement : null),
                        $devise,
                        $salaireBase,
                        $primeTransport,
                        $primeLogement,
                        $primeVie,
                        $heuresSup,
                        $autresPrimes,
                        $rts,
                        $autresRetenues,
                        $totalBrut,
                        $net,
                        $paye,
                        $idBulletin,
                    ]);
                } else {
                    $numero = 'BS-' . str_replace('-', '', $periodeMonthPost) . '-' . $idEmploye;
                    $sqlIn = 'INSERT INTO bulletins_salaire
                                (id_employe, periode, numero, mode_reglement, date_paiement, devise,
                                 salaire_base, prime_transport, prime_logement, prime_vie, heures_sup, autres_primes,
                                 rts, autres_retenues, total_brut, net_a_payer, paye)
                              VALUES
                                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                    $stIn = $bdd->prepare($sqlIn);
                    $stIn->execute([
                        $idEmploye,
                        $periodePost,
                        $numero,
                        ($modeReglement !== '' ? $modeReglement : null),
                        ($datePaiement !== '' ? $datePaiement : null),
                        $devise,
                        $salaireBase,
                        $primeTransport,
                        $primeLogement,
                        $primeVie,
                        $heuresSup,
                        $autresPrimes,
                        $rts,
                        $autresRetenues,
                        $totalBrut,
                        $net,
                        $paye,
                    ]);
                }

                if (preg_match('/^(\d{4})-(\d{2})$/', (string)$periodeMonthPost, $mm)) {
                    $y = (int)$mm[1];
                    $mn = (int)$mm[2];
                    header('Location: bulletinsalaire.php?annee=' . urlencode((string)$y) . '&mois_num=' . urlencode((string)$mn) . '&ok=1');
                } else {
                    header('Location: bulletinsalaire.php?periode=' . urlencode($periodeMonthPost) . '&ok=1');
                }
                exit;
            } catch (PDOException $e) {
                error_log('[bulletinsalaire] save: ' . $e->getMessage());
                $error = 'Une erreur est survenue lors de l\'enregistrement du bulletin.';
            }
        }
    }
}

if (isset($_GET['ok'])) {
    $alert = ['type' => 'success', 'message' => 'Opération effectuée avec succès.'];
}

$colMap = bulletin_get_employes_column_map($bdd);
$nameCol = $colMap['name'];
$salaryCol = $colMap['salary'];

// Liste employés pour le modal
$employes = [];
if (!$error) {
    try {
        $select = 'SELECT id_employe,
                          `' . $nameCol . '` AS employe_nom,
                          `' . $salaryCol . '` AS salaire_base';
        if ($colMap['prime_transport']) {
            $select .= ', `' . $colMap['prime_transport'] . '` AS prime_transport';
        } else {
            $select .= ', 0 AS prime_transport';
        }
        if ($colMap['prime_logement']) {
            $select .= ', `' . $colMap['prime_logement'] . '` AS prime_logement';
        } else {
            $select .= ', 0 AS prime_logement';
        }
        if ($colMap['prime_vie']) {
            $select .= ', `' . $colMap['prime_vie'] . '` AS prime_vie';
        } else {
            $select .= ', 0 AS prime_vie';
        }
        $select .= ' FROM employes ORDER BY `' . $nameCol . '` ASC';

        $stEmp = $bdd->query($select);
        $employes = $stEmp ? $stEmp->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        error_log('[bulletinsalaire] employes: ' . $e->getMessage());
        $employes = [];
    }
}

$paidEmployeIdsCurrentPeriod = [];
if (!$error) {
    $paidEmployeIdsCurrentPeriod = bulletin_get_paid_employe_ids_for_period($bdd, $periodeMonth);
}

// Liste bulletins pour la période
$bulletins = [];
if (!$error) {
    try {
        $sqlB = 'SELECT b.*, e.`' . $nameCol . '` AS employe_nom
                 FROM bulletins_salaire b
                 JOIN employes e ON e.id_employe = b.id_employe
                 WHERE b.periode = ?
                 ORDER BY e.`' . $nameCol . '` ASC';
        $stB = $bdd->prepare($sqlB);
        $stB->execute([$periode]);
        $bulletins = $stB->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('[bulletinsalaire] list: ' . $e->getMessage());
        $error = 'Une erreur est survenue lors de la récupération des bulletins.';
    }
}

include('../PUBLIC/header.php');
?>

<body>
    <section class="body">
        <?php require('../PUBLIC/navbarmenu.php'); ?>

        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Bulletins de salaire</h2>
                </header>

                <div class="col-md-12">
                    <?php if ($alert): ?>
                        <div class="alert alert-<?php echo h($alert['type']); ?>">
                            <?php echo h($alert['message']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo h($error); ?></div>
                    <?php endif; ?>

                    <section class="card">
                        <div class="card-body">
                            <form method="get" class="row g-2 align-items-end mb-3">
                                <div class="col-sm-2">
                                    <label class="form-label">Année</label>
                                    <select name="annee" class="form-control" id="filterYear" required>
                                        <?php for ($y = (int)$yearMin; $y <= (int)$yearMax; $y++): ?>
                                            <option value="<?php echo (int)$y; ?>" <?php echo ((int)$y === (int)$selectedYear) ? 'selected' : ''; ?>>
                                                <?php echo (int)$y; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-sm-2">
                                    <label class="form-label">Mois</label>
                                    <select name="mois_num" class="form-control" id="filterMonth" required>
                                        <?php foreach ($monthLabels as $mNum => $mLabel): ?>
                                            <option value="<?php echo (int)$mNum; ?>" <?php echo ((int)$mNum === (int)$selectedMonthNum) ? 'selected' : ''; ?>>
                                                <?php echo h($mLabel); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-sm-3">
                                    <button type="submit" class="btn btn-primary">Afficher</button>
                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalBulletin" onclick="openCreateBulletin()">Nouveau bulletin</button>
                                    <button type="button" class="btn btn-default" data-bs-toggle="modal" data-bs-target="#modalEtatSalaires" onclick="openEtatSalairesFromPageFilter()">Etat des salaires</button>
                                </div>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped mb-0" id="datatable-default">
                                    <thead>
                                        <tr>
                                            <th>N°</th>
                                            <th>Employé</th>
                                            <th>Total brut</th>
                                            <th>Net à payer</th>
                                            <th>Statut</th>
                                            <th>Date paiement</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($bulletins)): ?>
                                            <tr>
                                                <td colspan="7">Aucun bulletin trouvé pour ce mois.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($bulletins as $b): ?>
                                                <?php
                                                    $isPaid = (int)($b['paye'] ?? 0) === 1;
                                                    $badge = $isPaid ? 'success' : 'secondary';
                                                    $label = $isPaid ? 'Payé' : 'Non payé';
                                                    $totalBrut = bulletin_to_float($b['total_brut'] ?? 0);
                                                    $net = bulletin_to_float($b['net_a_payer'] ?? 0);
                                                ?>
                                                <tr>
                                                    <td><?php echo h($b['numero'] ?? ('BS-' . ($b['id_bulletin'] ?? ''))); ?></td>
                                                    <td><?php echo h($b['employe_nom'] ?? ''); ?></td>
                                                    <td><?php echo h(bulletin_fmt_money($totalBrut, $devise)); ?></td>
                                                    <td><?php echo h(bulletin_fmt_money($net, $devise)); ?></td>
                                                    <td><span class="badge bg-<?php echo h($badge); ?>"><?php echo h($label); ?></span></td>
                                                    <td><?php echo h($b['date_paiement'] ?? '—'); ?></td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalBulletin"
                                                            data-bulletin='<?php echo h(json_encode($b, JSON_UNESCAPED_UNICODE)); ?>'
                                                            onclick="openEditBulletin(this)">Modifier</button>
                                                        <?php if ($isPaid): ?>
                                                            <button type="button" class="btn btn-sm btn-default" data-bs-toggle="modal" data-bs-target="#modalPrintBulletin" onclick="openPrintBulletin(<?php echo (int)($b['id_bulletin'] ?? 0); ?>)">Imprimer</button>
                                                        <?php endif; ?>
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

                <!-- Modal impression bulletin (PDF) -->
                <div class="modal fade" id="modalPrintBulletin" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Impression du bulletin</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body" style="min-height:70vh;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                </div>
                                <iframe id="printBulletinFrame" title="Bulletin de salaire" style="width:100%; height:65vh; border:1px solid #e5e5e5;"></iframe>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-bs-dismiss="modal">Fermer</button>
                                <button type="button" class="btn btn-primary" id="btnPrintBulletin"><i class="fa fa-print"></i> Imprimer</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal état des salaires (PDF) -->
                <div class="modal fade" id="modalEtatSalaires" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Etat des salaires</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body" style="min-height:70vh;">
                                <div class="row g-2 align-items-end mb-2">
                                    <div class="col-sm-3">
                                        <label class="form-label">Année</label>
                                        <select class="form-control" id="etatYear" required>
                                            <?php for ($y = (int)$yearMin; $y <= (int)$yearMax; $y++): ?>
                                                <option value="<?php echo (int)$y; ?>" <?php echo ((int)$y === (int)$selectedYear) ? 'selected' : ''; ?>>
                                                    <?php echo (int)$y; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-sm-3">
                                        <label class="form-label">Mois</label>
                                        <select class="form-control" id="etatMonth" required>
                                            <?php foreach ($monthLabels as $mNum => $mLabel): ?>
                                                <option value="<?php echo (int)$mNum; ?>" <?php echo ((int)$mNum === (int)$selectedMonthNum) ? 'selected' : ''; ?>>
                                                    <?php echo h($mLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-sm-6 text-end">
                                        <button type="button" class="btn btn-primary" onclick="refreshEtatSalaires()">Afficher</button>
                                        <button type="button" class="btn btn-primary" id="btnPrintEtatSalaires"><i class="fa fa-print"></i> Imprimer</button>
                                    </div>
                                </div>

                                <iframe id="etatSalairesFrame" title="Etat des salaires" style="width:100%; height:62vh; border:1px solid #e5e5e5;"></iframe>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-bs-dismiss="modal">Fermer</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal bulletin -->
                <div class="modal fade" id="modalBulletin" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <form method="post" id="bulletinForm">
                                <input type="hidden" name="action" value="save_bulletin">
                                <input type="hidden" name="id_bulletin" id="id_bulletin" value="">
                                    <input type="hidden" name="id_employe_hidden" id="id_employe_hidden" value="">

                                <div class="modal-header">
                                    <h5 class="modal-title" id="bulletinModalTitle">Nouveau bulletin</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Période</label>
                                            <input type="hidden" name="periode_month" id="periode_month" value="<?php echo h($periodeMonth); ?>">
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <select class="form-control" id="modalYear" required>
                                                        <?php for ($y = (int)$yearMin; $y <= (int)$yearMax; $y++): ?>
                                                            <option value="<?php echo (int)$y; ?>" <?php echo ((int)$y === (int)$selectedYear) ? 'selected' : ''; ?>>
                                                                <?php echo (int)$y; ?>
                                                            </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                                <div class="col-6">
                                                    <select class="form-control" id="modalMonth" required>
                                                        <?php foreach ($monthLabels as $mNum => $mLabel): ?>
                                                            <option value="<?php echo (int)$mNum; ?>" <?php echo ((int)$mNum === (int)$selectedMonthNum) ? 'selected' : ''; ?>>
                                                                <?php echo h($mLabel); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label">Employé</label>
                                            <select class="form-select" name="id_employe" id="id_employe" required onchange="fillFromEmploye()">
                                                <option value="">— Sélectionner —</option>
                                                <?php foreach ($employes as $e): ?>
                                                    <?php $isPaidThisPeriod = in_array((int)($e['id_employe'] ?? 0), $paidEmployeIdsCurrentPeriod, true); ?>
                                                    <option
                                                        value="<?php echo h($e['id_employe'] ?? ''); ?>"
                                                        data-salaire="<?php echo h($e['salaire_base'] ?? 0); ?>"
                                                        data-pt="<?php echo h($e['prime_transport'] ?? 0); ?>"
                                                        data-pl="<?php echo h($e['prime_logement'] ?? 0); ?>"
                                                        data-pv="<?php echo h($e['prime_vie'] ?? 0); ?>"
                                                        data-paid="<?php echo $isPaidThisPeriod ? '1' : '0'; ?>">
                                                        <?php echo h($e['employe_nom'] ?? ''); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Salaire de base</label>
                                            <input type="text" class="form-control" name="salaire_base" id="salaire_base" value="0">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Prime transport</label>
                                            <input type="text" class="form-control" name="prime_transport" id="prime_transport" value="0">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Prime logement</label>
                                            <input type="text" class="form-control" name="prime_logement" id="prime_logement" value="0">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Prime vie</label>
                                            <input type="text" class="form-control" name="prime_vie" id="prime_vie" value="0">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Heures sup</label>
                                            <input type="text" class="form-control" name="heures_sup" id="heures_sup" value="0">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Autres primes</label>
                                            <input type="text" class="form-control" name="autres_primes" id="autres_primes" value="0">
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">RTS</label>
                                            <input type="text" class="form-control" name="rts" id="rts" value="0">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Mode de règlement</label>
                                            <select class="form-select" name="mode_reglement" id="mode_reglement">
                                                <option value="">—</option>
                                                <option value="Espèces">Espèces</option>
                                                <option value="Chèque">Chèque</option>
                                                <option value="Virement">Virement</option>
                                                <option value="Mobile Money">Mobile Money</option>
                                            </select>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Date paiement</label>
                                            <input type="date" class="form-control" name="date_paiement" id="date_paiement" value="">
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="paye" id="paye" value="1">
                                                <label class="form-check-label" for="paye">Marquer comme payé</label>
                                            </div>
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

            </section>
        </div>
    </section>

    <?php include('../PUBLIC/footer.php'); ?>

    <script>
        var bulletinIsCreateMode = false;

        function bulletinPad2(n) {
            n = parseInt(n || 0, 10);
            return (n < 10 ? '0' : '') + String(n);
        }

        function bulletinSetModalPeriodeMonthFromSelects() {
            var yearEl = document.getElementById('modalYear');
            var monthEl = document.getElementById('modalMonth');
            var hiddenEl = document.getElementById('periode_month');
            if (!yearEl || !monthEl || !hiddenEl) return;

            var y = parseInt(yearEl.value || 0, 10);
            var m = parseInt(monthEl.value || 0, 10);
            if (!y || !m) return;

            // Sécurité: empêcher mois futur dans l'année en cours
            var cy = <?php echo (int)$currentYear; ?>;
            var cm = <?php echo (int)$currentMonthNum; ?>;
            if (y === cy && m > cm) {
                m = cm;
                monthEl.value = String(cm);
            }
            if (y > cy) {
                y = cy;
                m = cm;
                yearEl.value = String(cy);
                monthEl.value = String(cm);
            }

            hiddenEl.value = String(y) + '-' + bulletinPad2(m);
        }

        function bulletinUpdateModalMonthOptions() {
            var yearEl = document.getElementById('modalYear');
            var monthEl = document.getElementById('modalMonth');
            if (!yearEl || !monthEl) return;

            var cy = <?php echo (int)$currentYear; ?>;
            var cm = <?php echo (int)$currentMonthNum; ?>;
            var y = Number(yearEl.value || 0);

            var maxAllowed = 12;
            if (y === cy) {
                maxAllowed = Number(cm || 12);
            } else if (y > cy) {
                maxAllowed = 0;
            }

            var hasAllowed = false;
            Array.prototype.forEach.call(monthEl.options, function(opt){
                var m = Number(opt.value || 0);
                var allowed = (maxAllowed > 0) ? (m >= 1 && m <= maxAllowed) : false;
                opt.disabled = !allowed;
                if (allowed) hasAllowed = true;
            });

            monthEl.disabled = !hasAllowed;

            // Corriger la sélection si elle pointe sur un mois désormais interdit
            var curM = Number(monthEl.value || 0);
            if (!hasAllowed) {
                monthEl.value = '';
                return;
            }
            if (!(curM >= 1 && curM <= maxAllowed)) {
                monthEl.value = String(maxAllowed);
            }
        }

        function bulletinSetModalSelectsFromPeriodeMonth(periodeMonth) {
            var yearEl = document.getElementById('modalYear');
            var monthEl = document.getElementById('modalMonth');
            if (!yearEl || !monthEl) return;
            var v = String(periodeMonth || '');
            if (!/^\d{4}-\d{2}$/.test(v)) return;
            yearEl.value = v.slice(0, 4);
            monthEl.value = String(parseInt(v.slice(5, 7), 10));
        }

        function bulletinSetOptionVisibilityForPaid(paidIds) {
            var sel = document.getElementById('id_employe');
            if (!sel) return;

            var paidSet = {};
            (paidIds || []).forEach(function(id){ paidSet[String(id)] = true; });

            Array.prototype.forEach.call(sel.options, function(opt){
                if (!opt || !opt.value) return; // placeholder
                var id = String(opt.value);
                var isPaid = !!paidSet[id];

                // En mode création: cacher les employés déjà payés pour la période
                if (bulletinIsCreateMode && isPaid) {
                    opt.hidden = true;
                    opt.disabled = true;
                } else {
                    opt.hidden = false;
                    opt.disabled = false;
                }
            });

            // Si l'option sélectionnée vient d'être cachée, reset
            if (sel.value && sel.selectedOptions && sel.selectedOptions.length) {
                var cur = sel.selectedOptions[0];
                if (cur && (cur.hidden || cur.disabled)) {
                    sel.value = '';
                    document.getElementById('id_employe_hidden').value = '';
                }
            }
        }

        function bulletinRefreshPaidEmployesForPeriod() {
            if (!bulletinIsCreateMode) {
                bulletinSetOptionVisibilityForPaid([]);
                return;
            }

            var periodeEl = document.getElementById('periode_month');
            var periodeMonth = (periodeEl && periodeEl.value) ? String(periodeEl.value) : '';
            if (!periodeMonth) {
                bulletinSetOptionVisibilityForPaid([]);
                return;
            }

            var url = 'bulletinsalaire.php?ajax=paid_employes&periode_month=' + encodeURIComponent(periodeMonth);
            fetch(url, { credentials: 'same-origin' })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (!data || data.ok !== true) {
                        bulletinSetOptionVisibilityForPaid([]);
                        return;
                    }
                    bulletinSetOptionVisibilityForPaid(data.paid_employe_ids || []);
                })
                .catch(function(){ bulletinSetOptionVisibilityForPaid([]); });
        }

        function openPrintBulletin(idBulletin) {
            const id = parseInt(idBulletin || 0, 10);
            const url = '../impression/_bulletin_salaire.php?id=' + encodeURIComponent(id);
            const frame = document.getElementById('printBulletinFrame');
            const link = document.getElementById('printBulletinLink');
            if (link) link.href = url;
            if (frame) frame.src = url;
        }

        function etatUpdateMonthOptions(){
            var yearSel = document.getElementById('etatYear');
            var monthSel = document.getElementById('etatMonth');
            if (!yearSel || !monthSel) return;

            var currentYear = <?php echo (int)$currentYear; ?>;
            var currentMonthNum = <?php echo (int)$currentMonthNum; ?>;

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

            var curM = Number(monthSel.value || 0);
            if (!hasAllowed) {
                monthSel.value = '';
                return;
            }
            if (!(curM >= 1 && curM <= maxAllowed)) {
                monthSel.value = String(maxAllowed);
            }
        }

        function refreshEtatSalaires(){
            var yearSel = document.getElementById('etatYear');
            var monthSel = document.getElementById('etatMonth');
            var frame = document.getElementById('etatSalairesFrame');
            if (!yearSel || !monthSel || !frame) return;

            etatUpdateMonthOptions();

            var y = parseInt(yearSel.value || 0, 10);
            var m = parseInt(monthSel.value || 0, 10);
            if (!y || !m) return;

            var url = '../impression/_etat_salaires.php?annee=' + encodeURIComponent(String(y)) + '&mois_num=' + encodeURIComponent(String(m));
            frame.src = url;
        }

        function openEtatSalairesFromPageFilter(){
            var fy = document.getElementById('filterYear');
            var fm = document.getElementById('filterMonth');
            var yearSel = document.getElementById('etatYear');
            var monthSel = document.getElementById('etatMonth');
            if (fy && yearSel) yearSel.value = fy.value;
            if (fm && monthSel) monthSel.value = fm.value;
            refreshEtatSalaires();
        }

        document.addEventListener('DOMContentLoaded', function () {
            const modalEl = document.getElementById('modalPrintBulletin');
            if (!modalEl) return;
            modalEl.addEventListener('hidden.bs.modal', function () {
                const frame = document.getElementById('printBulletinFrame');
                if (frame) frame.src = '';
                const link = document.getElementById('printBulletinLink');
                if (link) link.href = '#';
            });

            const btnPrint = document.getElementById('btnPrintBulletin');
            if (btnPrint) {
                btnPrint.addEventListener('click', function () {
                    const frame = document.getElementById('printBulletinFrame');
                    if (!frame) return;
                    try {
                        if (frame.contentWindow) {
                            frame.contentWindow.focus();
                            frame.contentWindow.print();
                        }
                    } catch (e) {
                        // Si le navigateur bloque l'accès, on ne fait rien
                    }
                });
            }

            // Etat des salaires: initialisation + impression
            var etatModalEl = document.getElementById('modalEtatSalaires');
            if (etatModalEl) {
                etatModalEl.addEventListener('shown.bs.modal', function(){
                    etatUpdateMonthOptions();
                    var frame = document.getElementById('etatSalairesFrame');
                    if (frame && !frame.src) {
                        openEtatSalairesFromPageFilter();
                    }
                });
                etatModalEl.addEventListener('hidden.bs.modal', function(){
                    var frame = document.getElementById('etatSalairesFrame');
                    if (frame) frame.src = '';
                });
            }

            var etatYear = document.getElementById('etatYear');
            if (etatYear) etatYear.addEventListener('change', function(){ etatUpdateMonthOptions(); });

            var btnPrintEtat = document.getElementById('btnPrintEtatSalaires');
            if (btnPrintEtat) {
                btnPrintEtat.addEventListener('click', function(){
                    var frame = document.getElementById('etatSalairesFrame');
                    if (!frame) return;
                    try {
                        if (frame.contentWindow) {
                            frame.contentWindow.focus();
                            frame.contentWindow.print();
                        }
                    } catch (e) {
                    }
                });
            }
        });

        function openCreateBulletin() {
            bulletinIsCreateMode = true;
            document.getElementById('bulletinModalTitle').textContent = 'Nouveau bulletin';
            document.getElementById('id_bulletin').value = '';

            // Période par défaut = période sélectionnée dans le filtre
            bulletinSetModalSelectsFromPeriodeMonth(<?php echo json_encode($periodeMonth); ?>);
            bulletinUpdateModalMonthOptions();
            bulletinSetModalPeriodeMonthFromSelects();

            document.getElementById('id_employe').disabled = false;
            document.getElementById('id_employe').value = '';
            document.getElementById('id_employe_hidden').value = '';
            document.getElementById('salaire_base').value = '0';
            document.getElementById('prime_transport').value = '0';
            document.getElementById('prime_logement').value = '0';
            document.getElementById('prime_vie').value = '0';
            document.getElementById('heures_sup').value = '0';
            document.getElementById('autres_primes').value = '0';
            document.getElementById('rts').value = '0';
            document.getElementById('mode_reglement').value = '';
            document.getElementById('date_paiement').value = '';
            document.getElementById('paye').checked = false;

            bulletinRefreshPaidEmployesForPeriod();
        }

        function openEditBulletin(btn) {
            bulletinIsCreateMode = false;
            const raw = btn.getAttribute('data-bulletin') || '{}';
            let b = {};
            try { b = JSON.parse(raw); } catch (e) { b = {}; }

            // En mode édition, on ré-affiche tous les employés
            bulletinSetOptionVisibilityForPaid([]);

            document.getElementById('bulletinModalTitle').textContent = 'Modifier bulletin';
            document.getElementById('id_bulletin').value = b.id_bulletin || '';
            if (b.periode) {
                var pm = String(b.periode).slice(0, 7);
                bulletinSetModalSelectsFromPeriodeMonth(pm);
                bulletinUpdateModalMonthOptions();
                document.getElementById('periode_month').value = pm;
            }
            document.getElementById('id_employe').value = b.id_employe || '';
            document.getElementById('id_employe_hidden').value = b.id_employe || '';
            document.getElementById('id_employe').disabled = true;

            document.getElementById('salaire_base').value = b.salaire_base ?? '0';
            document.getElementById('prime_transport').value = b.prime_transport ?? '0';
            document.getElementById('prime_logement').value = b.prime_logement ?? '0';
            document.getElementById('prime_vie').value = b.prime_vie ?? '0';
            document.getElementById('heures_sup').value = b.heures_sup ?? '0';
            document.getElementById('autres_primes').value = b.autres_primes ?? '0';
            document.getElementById('rts').value = b.rts ?? '0';
            document.getElementById('mode_reglement').value = b.mode_reglement ?? '';
            document.getElementById('date_paiement').value = b.date_paiement ?? '';
            document.getElementById('paye').checked = String(b.paye || '0') === '1';
        }

        document.addEventListener('DOMContentLoaded', function(){
            var yearEl = document.getElementById('modalYear');
            var monthEl = document.getElementById('modalMonth');
            if (yearEl) {
                yearEl.addEventListener('change', function(){
                    bulletinUpdateModalMonthOptions();
                    bulletinSetModalPeriodeMonthFromSelects();
                    bulletinRefreshPaidEmployesForPeriod();
                });
            }
            if (monthEl) {
                monthEl.addEventListener('change', function(){
                    bulletinSetModalPeriodeMonthFromSelects();
                    bulletinRefreshPaidEmployesForPeriod();
                });
            }

            // Initialiser l'état des mois (utile si le modal est ouvert sans passer par openCreateBulletin)
            bulletinUpdateModalMonthOptions();
        });

        function fillFromEmploye() {
            const sel = document.getElementById('id_employe');
            const opt = sel.options[sel.selectedIndex];
            if (!opt || !opt.value) return;
            document.getElementById('id_employe_hidden').value = opt.value;
            document.getElementById('salaire_base').value = opt.getAttribute('data-salaire') || '0';
            document.getElementById('prime_transport').value = opt.getAttribute('data-pt') || '0';
            document.getElementById('prime_logement').value = opt.getAttribute('data-pl') || '0';
            document.getElementById('prime_vie').value = opt.getAttribute('data-pv') || '0';
        }

        (function(){
            // Filtre Année/Mois: désactiver les mois futurs pour l'année en cours
            var currentYear = <?php echo (int)$currentYear; ?>;
            var currentMonthNum = <?php echo (int)$currentMonthNum; ?>;

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
            if (yearSelInit) yearSelInit.addEventListener('change', appecUpdateMonthOptions);
            appecUpdateMonthOptions();
        })();
    </script>
</body>
