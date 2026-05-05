<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

date_default_timezone_set('Africa/Abidjan');

$errors = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function is_valid_date($value): bool {
    if (!is_string($value)) return false;
    return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
}

function mp_int($v): int {
    if ($v === null) return 0;
    if (is_string($v)) {
        $v = str_replace([' ', ','], ['', '.'], trim($v));
    }
    if ($v === '' || $v === false) return 0;
    return (int)round((float)$v);
}

function mp_expected_total(array $p): int {
    $b0 = mp_int($p['b0'] ?? 0);
    $b1 = mp_int($p['b1'] ?? 0);
    $b2 = mp_int($p['b2'] ?? 0);
    $b5 = mp_int($p['b5'] ?? 0);
    $b10 = mp_int($p['b10'] ?? 0);
    $b20 = mp_int($p['b20'] ?? 0);
    return ($b0 * 500) + ($b1 * 1000) + ($b2 * 2000) + ($b5 * 5000) + ($b10 * 10000) + ($b20 * 20000);
}

function mp_entree_paiements(PDO $bdd, int $compteId, string $dateRapportement, ?int $userId = null): int {
    static $cache = [];
    $compteId = (int)$compteId;
    $dateKey = substr((string)$dateRapportement, 0, 10);
    $userKey = $userId !== null ? (int)$userId : 0;
    $cacheKey = $dateKey . '|' . $compteId . '|' . $userKey;
    if (isset($cache[$cacheKey])) return (int)$cache[$cacheKey];

    try {
        // Couvrir toute la journée (datepaiement peut être DATE ou DATETIME selon les environnements).
        $debut = $dateKey . ' 00:00:00';
        $fin = $dateKey . ' 23:59:59';

        $val = mp_int(getEntreePaiements($compteId, $debut, $fin, $bdd, $userId !== null ? (int)$userId : null));
        $cache[$cacheKey] = $val;
        return $val;
    } catch (Throwable $e) {
        error_log('[listepreuvecaisse mp_entree_paiements] ' . $e->getMessage());
        $cache[$cacheKey] = 0;
        return 0;
    }
}

// ===================== Chargement listes (caissiers, comptes) =====================
$caissiers = [];
try {
    // Schéma récent (type texte)
    $st = $bdd->prepare("SELECT id, pseudo FROM users WHERE status = 1 AND type IN (8) ORDER BY pseudo");
    $st->execute();
    $caissiers = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $caissiers = [];
}

if (empty($caissiers)) {
    try {
        // Fallback: tous les utilisateurs ayant au moins une preuve
        $st = $bdd->prepare('SELECT DISTINCT u.id, u.pseudo FROM preuvedecaisse p JOIN users u ON u.id = p.id_user ORDER BY u.pseudo');
        $st->execute();
        $caissiers = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $caissiers = [];
    }
}

$comptes = [];
try {
    $st = $bdd->prepare('SELECT id_compte, nom_compte FROM comptes WHERE defaut = 1 AND (compte_pour = 1 OR compte_pour = 2) AND status = 1 ORDER BY nom_compte');
    $st->execute();
    $comptes = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $comptes = [];
}

// ===================== POST: ajouter une preuve (admin) =====================
if (isset($_POST['add_preuve_admin'])) {
    $idUser = isset($_POST['id_user']) ? (int)$_POST['id_user'] : 0;
    $dateRapportement = isset($_POST['date_rapportement']) ? trim((string)$_POST['date_rapportement']) : '';
    $compte = isset($_POST['compte']) ? (int)$_POST['compte'] : 0;

    $payload = [
        'b0' => $_POST['b0'] ?? 0,
        'b1' => $_POST['b1'] ?? 0,
        'b2' => $_POST['b2'] ?? 0,
        'b5' => $_POST['b5'] ?? 0,
        'b10' => $_POST['b10'] ?? 0,
        'b20' => $_POST['b20'] ?? 0,
    ];
    $montantLettre = trim((string)($_POST['montant_lettre'] ?? ''));

    if ($idUser <= 0 || !is_valid_date($dateRapportement) || $compte <= 0 || $montantLettre === '') {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=1');
        exit;
    }

    // Normaliser billets
    foreach (array_keys($payload) as $k) {
        $payload[$k] = mp_int($payload[$k]);
        if ($payload[$k] < 0) $payload[$k] = 0;
    }

    $montant = mp_expected_total($payload);

    try {
        $bdd->beginTransaction();

        $st = $bdd->prepare('SELECT COUNT(*) FROM preuvedecaisse WHERE date_rapportement = ? AND id_user = ? AND compte = ?');
        $st->execute([$dateRapportement, $idUser, $compte]);
        if ((int)$st->fetchColumn() > 0) {
            $bdd->rollBack();
            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=3');
            exit;
        }

        $st = $bdd->prepare('INSERT INTO preuvedecaisse (date_rapportement,compte, montant, b0, b1, b2, b5, b10, b20, montant_lettre, id_user) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $st->execute([
            $dateRapportement,
            $compte,
            $montant,
            $payload['b0'],
            $payload['b1'],
            $payload['b2'],
            $payload['b5'],
            $payload['b10'],
            $payload['b20'],
            $montantLettre,
            $idUser,
        ]);

        $bdd->commit();
        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=2');
        exit;
    } catch (Throwable $e) {
        if ($bdd->inTransaction()) $bdd->rollBack();
        error_log('[listepreuvecaisse add_preuve_admin] ' . $e->getMessage());
        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=4');
        exit;
    }
}

// ===================== GET: filtres liste =====================
$filterUser = isset($_GET['caissier']) ? (int)$_GET['caissier'] : 0;
$dateDebut = isset($_GET['datedebut']) ? trim((string)$_GET['datedebut']) : '';
$dateFin = isset($_GET['datefin']) ? trim((string)$_GET['datefin']) : '';

$dateDebut = is_valid_date($dateDebut) ? $dateDebut : '';
$dateFin = is_valid_date($dateFin) ? $dateFin : '';

$today = date('Y-m-d');

// Bloquer les dates futures (sécurité serveur)
if ($dateDebut !== '' && $dateDebut > $today) {
    $dateDebut = '';
}
if ($dateFin !== '' && $dateFin > $today) {
    $dateFin = '';
}

// Optionnel: si la plage est inversée, on corrige
if ($dateDebut !== '' && $dateFin !== '' && $dateDebut > $dateFin) {
    $tmp = $dateDebut;
    $dateDebut = $dateFin;
    $dateFin = $tmp;
}

$where = [];
$params = [];

if ($filterUser > 0) {
    $where[] = 'p.id_user = ?';
    $params[] = $filterUser;
}

if ($dateDebut !== '' && $dateFin !== '') {
    $where[] = 'DATE(p.date_rapportement) BETWEEN ? AND ?';
    $params[] = $dateDebut;
    $params[] = $dateFin;
} elseif ($dateDebut !== '') {
    $where[] = 'DATE(p.date_rapportement) >= ?';
    $params[] = $dateDebut;
} elseif ($dateFin !== '') {
    $where[] = 'DATE(p.date_rapportement) <= ?';
    $params[] = $dateFin;
}

$sql = 'SELECT p.id_preuve, p.date_rapportement, p.compte, p.montant, p.id_user, '
    . 'p.b0, p.b1, p.b2, p.b5, p.b10, p.b20, '
    . 'u.pseudo AS caissier_pseudo, c.nom_compte '
     . 'FROM preuvedecaisse p '
     . 'LEFT JOIN users u ON u.id = p.id_user '
     . 'LEFT JOIN comptes c ON c.id_compte = p.compte ';

if (!empty($where)) {
    $sql .= 'WHERE ' . implode(' AND ', $where) . ' ';
}

$sql .= 'ORDER BY p.date_rapportement DESC, p.id_preuve DESC ';

// Sécurité: si aucun filtre, limiter la liste
$limit = 200;
if (empty($where)) {
    $sql .= 'LIMIT ' . (int)$limit;
}

$rows = [];
try {
    $st = $bdd->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[listepreuvecaisse fetch] ' . $e->getMessage());
    $rows = [];
    $errors = 5;
}

$exportParams = [
    'export' => 'excel',
    'caissier' => $filterUser > 0 ? (string)$filterUser : '0',
];
if ($dateDebut !== '') $exportParams['datedebut'] = $dateDebut;
if ($dateFin !== '') $exportParams['datefin'] = $dateFin;
$excelExportUrl = $_SERVER['PHP_SELF'] . '?' . http_build_query($exportParams);

if (isset($_GET['export']) && (string)$_GET['export'] === 'excel') {
    $filename = 'preuves_caisse_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    if (!$output) die('Erreur création sortie');
    
    // En-têtes
    $headers = ['ID', 'DATE', 'CAISSIER', 'COMPTE', 'MONTANT DECLARE', 'TOTAL BILLETS', 'TOTAL ENTREES', 'CONFORMITE'];
    fputcsv($output, $headers, ';', '"');
    
    // Données
    foreach ($rows as $r) {
        $idPreuve = (int)($r['id_preuve'] ?? 0);
        $dateRap = (string)($r['date_rapportement'] ?? '');
        $pseudo = (string)($r['caissier_pseudo'] ?? '');
        $compteLabel = (string)($r['nom_compte'] ?? '');
        $montant = mp_int($r['montant'] ?? 0);
        $expected = mp_expected_total($r);
        $entree = mp_entree_paiements($bdd, (int)($r['compte'] ?? 0), $dateRap, isset($r['id_user']) ? (int)$r['id_user'] : null);
        $conforme = ($montant === $expected) && ($montant === $entree);
        
        fputcsv($output, [
            $idPreuve,
            $dateRap,
            $pseudo,
            $compteLabel,
            $montant,
            $expected,
            $entree,
            $conforme ? 'Oui' : 'Non',
        ], ';', '"');
    }
    
    fclose($output);
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
                <h2>Liste des preuves de caisse</h2>
            </header>

            <div class="col-md-12">
                <section class="card">
                    <div class="card-body">

                        <div id="lpAlert" class="alert d-none" role="alert"></div>

                        <div class="d-flex gap-2 flex-wrap mb-3">
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addPreuveModal">
                                <i class="fa fa-plus"></i> Ajouter une preuve de caisse
                            </button>
                            <a href="<?php echo h($excelExportUrl); ?>" class="btn btn-sm btn-success">
                                <i class="fa fa-file-excel-o"></i> Exporter Excel
                            </a>
                        </div>

                        <?php if ($errors === 1): ?>
                            <div class="alert alert-danger"><li>Champs invalides. Vérifiez caissier, date, compte et montant en lettres.</li></div>
                        <?php elseif ($errors === 2): ?>
                            <div class="alert alert-success"><li>Preuve ajoutée avec succès.</li></div>
                        <?php elseif ($errors === 3): ?>
                            <div class="alert alert-warning"><li>Une preuve existe déjà pour ce caissier, ce compte et cette date.</li></div>
                        <?php elseif ($errors === 4): ?>
                            <div class="alert alert-danger"><li>Erreur lors de l'ajout. Vérifiez les logs.</li></div>
                        <?php elseif ($errors === 5): ?>
                            <div class="alert alert-danger"><li>Erreur lors du chargement de la liste. Vérifiez les logs.</li></div>
                        <?php endif; ?>

                        <form method="get" class="row g-3 align-items-end mb-3">
                            <div class="col-md-4">
                                <label class="col-form-label" for="caissier">Caissier</label>
                                <select class="form-control" name="caissier" id="caissier">
                                    <option value="0">Tous</option>
                                    <?php foreach ($caissiers as $c):
                                        $cid = (int)($c['id'] ?? 0);
                                        $pseudo = (string)($c['pseudo'] ?? '');
                                        if ($cid <= 0) continue;
                                        $sel = ($filterUser === $cid) ? ' selected' : '';
                                        echo '<option value="' . h((string)$cid) . '"' . $sel . '>' . h($pseudo) . '</option>';
                                    endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="col-form-label" for="datedebut">Date début</label>
                                <input type="date" class="form-control" name="datedebut" id="datedebut" value="<?php echo h($dateDebut); ?>" max="<?php echo h($today); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="col-form-label" for="datefin">Date fin</label>
                                <input type="date" class="form-control" name="datefin" id="datefin" value="<?php echo h($dateFin); ?>" max="<?php echo h($today); ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">Afficher</button>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0" id="datatable-default">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>DATE</th>
                                        <th>CAISSIER</th>
                                        <th>COMPTE</th>
                                        <th>MONTANT</th>
                                        <th>ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($rows as $r):
                                    $idPreuve = (int)($r['id_preuve'] ?? 0);
                                    $dateRap = (string)($r['date_rapportement'] ?? '');
                                    $pseudo = (string)($r['caissier_pseudo'] ?? '');
                                    $compteLabel = (string)($r['nom_compte'] ?? '');
                                    $montant = mp_int($r['montant'] ?? 0);
                                    $expected = mp_expected_total($r);
                                    $entree = mp_entree_paiements($bdd, (int)($r['compte'] ?? 0), $dateRap, isset($r['id_user']) ? (int)$r['id_user'] : null);
                                    $conforme = ($montant === $expected) && ($montant === $entree);
                                    ?>
                                    <tr>
                                        <td><?php echo h((string)$idPreuve); ?></td>
                                        <td><?php echo h($dateRap); ?></td>
                                        <td><?php echo h($pseudo); ?></td>
                                        <td><?php echo h($compteLabel); ?></td>
                                        <td><?php echo h(number_format((float)$montant)); ?></td>
                                        <td>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <button type="button" class="btn btn-sm btn-default js-open-rapport" data-id_preuve="<?php echo h((string)$idPreuve); ?>">
                                                    <i class="fa fa-file-pdf-o"></i> PDF
                                                </button>
                                                <?php if (!$conforme): ?>
                                                    <button type="button" class="btn btn-sm btn-info js-edit-proof" data-id_preuve="<?php echo h((string)$idPreuve); ?>">
                                                        <i class="fa fa-edit"></i> Modifier
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </section>
            </div>

        </section>
    </div>

    <?php include('../PUBLIC/footer.php'); ?>
</section>

<!-- Modal aperçu rapport de caisse (PDF) -->
<div class="modal fade" id="rapportPdfModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rapport de caisse (PDF)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="height: 80vh;">
                <iframe id="rapportPdfFrame" src="" style="width:100%; height:100%;" frameborder="0"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary" onclick="printRapportPdf()"><i class="fa fa-print"></i> Imprimer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal ajout preuve admin -->
<div class="modal fade" id="addPreuveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" action="<?php echo h($_SERVER['PHP_SELF']); ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter une preuve de caisse</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="add_preuve_admin" value="1">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group pb-3">
                                <label class="col-form-label" for="ap_id_user">Caissier</label>
                                <select class="form-control" name="id_user" id="ap_id_user" required>
                                    <option value="">----- Choisir -----</option>
                                    <?php foreach ($caissiers as $c):
                                        $cid = (int)($c['id'] ?? 0);
                                        $pseudo = (string)($c['pseudo'] ?? '');
                                        if ($cid <= 0) continue;
                                        echo '<option value="' . h((string)$cid) . '">' . h($pseudo) . '</option>';
                                    endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group pb-3">
                                <label class="col-form-label" for="ap_date">Date</label>
                                <input type="date" class="form-control" name="date_rapportement" id="ap_date" value="<?php echo h(date('Y-m-d')); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group pb-3">
                                <label class="col-form-label" for="ap_compte">Compte</label>
                                <select class="form-control" name="compte" id="ap_compte" required>
                                    <option value="">----- Choisir -----</option>
                                    <?php foreach ($comptes as $cp):
                                        $idc = (int)($cp['id_compte'] ?? 0);
                                        $lbl = (string)($cp['nom_compte'] ?? '');
                                        if ($idc <= 0) continue;
                                        echo '<option value="' . h((string)$idc) . '">' . h($lbl) . '</option>';
                                    endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group pb-3">
                                <label class="col-form-label" for="ap_montant">Montant total</label>
                                <input type="text" class="form-control" id="ap_montant" value="0" readonly>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group pb-3">
                                <label class="col-form-label" for="ap_b0">Billets 500</label>
                                <input type="number" class="form-control ap-billet" name="b0" id="ap_b0" min="0" value="0" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group pb-3">
                                <label class="col-form-label" for="ap_b1">Billets 1 000</label>
                                <input type="number" class="form-control ap-billet" name="b1" id="ap_b1" min="0" value="0" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group pb-3">
                                <label class="col-form-label" for="ap_b2">Billets 2 000</label>
                                <input type="number" class="form-control ap-billet" name="b2" id="ap_b2" min="0" value="0" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group pb-3">
                                <label class="col-form-label" for="ap_b5">Billets 5 000</label>
                                <input type="number" class="form-control ap-billet" name="b5" id="ap_b5" min="0" value="0" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group pb-3">
                                <label class="col-form-label" for="ap_b10">Billets 10 000</label>
                                <input type="number" class="form-control ap-billet" name="b10" id="ap_b10" min="0" value="0" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group pb-3">
                                <label class="col-form-label" for="ap_b20">Billets 20 000</label>
                                <input type="number" class="form-control ap-billet" name="b20" id="ap_b20" min="0" value="0" required>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group pb-3">
                                <label class="col-form-label" for="ap_montant_lettre">Montant en lettres</label>
                                <input type="text" class="form-control" name="montant_lettre" id="ap_montant_lettre" required>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        Le montant est calculé automatiquement à partir des billets.
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Utilitaires: conversion montant -> lettres (FR)
// Note: on reste volontairement simple (entiers, style français standard).
window.mpNumberToFrenchWords = function (n) {
    n = Number(n);
    if (!isFinite(n) || n < 0) return '';
    n = Math.floor(n);
    if (n === 0) return 'zéro';

    function unit(x) {
        return ['','un','deux','trois','quatre','cinq','six','sept','huit','neuf'][x] || '';
    }
    function teen(x) {
        return ['dix','onze','douze','treize','quatorze','quinze','seize','dix-sept','dix-huit','dix-neuf'][x - 10] || '';
    }
    function tens(x) {
        if (x < 10) return unit(x);
        if (x < 20) return teen(x);

        var t = Math.floor(x / 10);
        var u = x % 10;

        if (t === 7) {
            // 70-79 = soixante + 10..19
            return 'soixante' + (u === 1 ? '-et-' : '-') + teen(10 + u);
        }
        if (t === 9) {
            // 90-99 = quatre-vingt + 10..19
            return 'quatre-vingt-' + teen(10 + u);
        }

        var base = {
            2: 'vingt',
            3: 'trente',
            4: 'quarante',
            5: 'cinquante',
            6: 'soixante',
            8: 'quatre-vingt'
        }[t] || '';

        if (t === 8 && u === 0) return 'quatre-vingts';
        if (u === 0) return base;
        if (u === 1 && (t === 2 || t === 3 || t === 4 || t === 5 || t === 6)) return base + '-et-un';
        return base + '-' + unit(u);
    }
    function belowThousand(x) {
        var h = Math.floor(x / 100);
        var r = x % 100;
        var out = [];

        if (h > 0) {
            if (h === 1) out.push('cent');
            else out.push(unit(h) + ' cent');
            if (r === 0 && h > 1) out[out.length - 1] += 's';
        }
        if (r > 0) out.push(tens(r));
        return out.join(' ');
    }

    function chunk(x, scaleWord, pluralizeScale) {
        if (x === 0) return '';
        var words = belowThousand(x);
        if (!scaleWord) return words;
        if (x === 1 && scaleWord === 'mille') return 'mille';
        var s = scaleWord;
        if (pluralizeScale && x > 1) s += 's';
        return words + ' ' + s;
    }

    var billions = Math.floor(n / 1000000000);
    var millions = Math.floor((n % 1000000000) / 1000000);
    var thousands = Math.floor((n % 1000000) / 1000);
    var rest = n % 1000;

    var parts = [];
    if (billions) parts.push(chunk(billions, 'milliard', true));
    if (millions) parts.push(chunk(millions, 'million', true));
    if (thousands) parts.push(chunk(thousands, 'mille', false));
    if (rest) parts.push(chunk(rest, '', false));

    return parts.join(' ').replace(/\s+/g, ' ').trim();
};

window.mpAutofillMontantLettre = function (inputEl, montant) {
    if (!inputEl) return;
    var isManual = String(inputEl.dataset.manual || '') === '1';
    if (isManual) return;

    var words = window.mpNumberToFrenchWords(montant);
    if (!words) return;

    function capitalizeToken(token) {
        if (!token) return token;
        var lower = String(token).toLowerCase();
        var re = /[A-Za-zÀ-ÖØ-öø-ÿ]/;
        var idx = lower.search(re);
        if (idx < 0) return token;
        return lower.slice(0, idx) + lower.charAt(idx).toUpperCase() + lower.slice(idx + 1);
    }

    function titleCaseWords(str) {
        return String(str)
            .split(/\s+/)
            .filter(function (w) { return w !== ''; })
            .map(function (w) {
                // Gère les mots composés (ex: "quatre-vingt-deux")
                return w.split('-').map(capitalizeToken).join('-');
            })
            .join(' ');
    }

    // Devise (par défaut: GNF)
    var currency = ' GNF';
    inputEl.value = titleCaseWords(words) + currency;
    inputEl.dataset.auto = '1';
};
</script>

<script>
(function () {
    function toInt(v) {
        var n = parseInt(v, 10);
        return isNaN(n) || n < 0 ? 0 : n;
    }

    function getValue(id) {
        var el = document.getElementById(id);
        return el ? el.value : '';
    }

    function formatNumber(n) {
        try {
            return new Intl.NumberFormat('fr-FR').format(n);
        } catch (e) {
            return String(n);
        }
    }

    function calcTotal() {
        var b0 = toInt(getValue('ap_b0'));
        var b1 = toInt(getValue('ap_b1'));
        var b2 = toInt(getValue('ap_b2'));
        var b5 = toInt(getValue('ap_b5'));
        var b10 = toInt(getValue('ap_b10'));
        var b20 = toInt(getValue('ap_b20'));

        var total = (b0 * 500) + (b1 * 1000) + (b2 * 2000) + (b5 * 5000) + (b10 * 10000) + (b20 * 20000);
        var out = document.getElementById('ap_montant');
        if (out) out.value = formatNumber(total);

        var lettre = document.getElementById('ap_montant_lettre');
        if (lettre) window.mpAutofillMontantLettre(lettre, total);
    }

    document.addEventListener('input', function (e) {
        if (!e.target) return;
        if (e.target.classList && e.target.classList.contains('ap-billet')) {
            calcTotal();
        }
    });

    // Recalcul à l'ouverture
    var modal = document.getElementById('addPreuveModal');
    if (modal) {
        modal.addEventListener('shown.bs.modal', function () {
            calcTotal();
        });
    }

    // Si l'utilisateur modifie le champ “lettre”, on arrête l'auto
    var lettreInput = document.getElementById('ap_montant_lettre');
    if (lettreInput) {
        lettreInput.addEventListener('input', function () {
            lettreInput.dataset.manual = '1';
        });
        lettreInput.addEventListener('blur', function () {
            // si vide, on repasse en auto
            if (!lettreInput.value || String(lettreInput.value).trim() === '') {
                lettreInput.dataset.manual = '0';
                calcTotal();
            }
        });
    }
})();
</script>

<script>
(function () {
    var modalEl = document.getElementById('rapportPdfModal');
    var frameEl = document.getElementById('rapportPdfFrame');
    var currentId = 0;

    function buildUrl(id) {
        // Charger un wrapper HTML pour imprimer de façon fiable (pas de nouvel onglet)
        return 'imprimer_rapport_caisse.php?id=' + encodeURIComponent(String(id)) + '&autoprint=0&t=' + Date.now();
    }

    window.printRapportPdf = function () {
        try {
            if (!frameEl || !frameEl.contentWindow) return;
            if (typeof frameEl.contentWindow.printPdf === 'function') {
                frameEl.contentWindow.printPdf();
                return;
            }
            if (typeof frameEl.contentWindow.print === 'function') {
                frameEl.contentWindow.focus && frameEl.contentWindow.focus();
                frameEl.contentWindow.print();
            }
        } catch (e) {
            // noop
        }
    };

    function openModal() {
        if (!modalEl || !window.bootstrap) return;
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('.js-open-rapport') : null;
        if (!btn) return;
        var id = parseInt(btn.getAttribute('data-id_preuve') || '0', 10);
        if (!id || id <= 0) return;
        currentId = id;
        if (frameEl) frameEl.src = buildUrl(id);
        openModal();
    });

    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', function () {
            currentId = 0;
            if (frameEl) frameEl.src = '';
        });
    }
})();
</script>

<!-- Modal modification preuve (uniquement si non conforme) -->
<div class="modal fade" id="editPreuveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="epForm" onsubmit="return false;">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier la preuve de caisse</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="epAlert" class="alert d-none" role="alert"></div>

                    <input type="hidden" id="ep_id_preuve" value="">

                    <div class="table-responsive mb-3">
                        <table class="table table-bordered mb-0">
                            <tbody>
                                <tr><th style="width: 35%">ID preuve</th><td id="ep_id">—</td></tr>
                                <tr><th>Date</th><td id="ep_date">—</td></tr>
                                <tr><th>Compte</th><td id="ep_compte">—</td></tr>
                                <tr><th>Caissier</th><td id="ep_caissier">—</td></tr>
                                <tr><th>Total billets calculé</th><td id="ep_expected">—</td></tr>
                                <tr><th>Total entrées du jour</th><td id="ep_entree">—</td></tr>
                                <tr><th>Statut</th><td id="ep_status">—</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group pb-3">
                                <label class="col-form-label" for="ep_montant">Montant déclaré</label>
                                <input type="text" class="form-control" id="ep_montant" value="0" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group pb-3">
                                <label class="col-form-label" for="ep_b0">Billets 500</label>
                                <input type="number" class="form-control ep-billet" id="ep_b0" min="0" value="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group pb-3">
                                <label class="col-form-label" for="ep_b1">Billets 1 000</label>
                                <input type="number" class="form-control ep-billet" id="ep_b1" min="0" value="0" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group pb-3">
                                <label class="col-form-label" for="ep_b2">Billets 2 000</label>
                                <input type="number" class="form-control ep-billet" id="ep_b2" min="0" value="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group pb-3">
                                <label class="col-form-label" for="ep_b5">Billets 5 000</label>
                                <input type="number" class="form-control ep-billet" id="ep_b5" min="0" value="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group pb-3">
                                <label class="col-form-label" for="ep_b10">Billets 10 000</label>
                                <input type="number" class="form-control ep-billet" id="ep_b10" min="0" value="0" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group pb-3">
                                <label class="col-form-label" for="ep_b20">Billets 20 000</label>
                                <input type="number" class="form-control ep-billet" id="ep_b20" min="0" value="0" required>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group pb-3">
                                <label class="col-form-label" for="ep_montant_lettre">Montant en lettres</label>
                                <input type="text" class="form-control" id="ep_montant_lettre" required="" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">Le montant est recalculé automatiquement à partir des billets.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
                    <button type="button" class="btn btn-primary" id="epSaveBtn">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    function qs(id) { return document.getElementById(id); }
    function toInt(v) {
        var n = parseInt(v, 10);
        return isNaN(n) || n < 0 ? 0 : n;
    }

    function val(id) {
        var el = qs(id);
        return el ? el.value : '';
    }
    function formatNumber(n) {
        try { return new Intl.NumberFormat('fr-FR').format(n); } catch (e) { return String(n); }
    }
    function setAlert(el, message, type) {
        if (!el) return;
        el.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info');
        el.classList.add('alert-' + (type || 'info'));
        el.textContent = message || '';
    }
    function clearAlert(el) {
        if (!el) return;
        el.classList.add('d-none');
        el.textContent = '';
    }

    function calcMontantEdit() {
        var total = (toInt(val('ep_b0')) * 500)
            + (toInt(val('ep_b1')) * 1000)
            + (toInt(val('ep_b2')) * 2000)
            + (toInt(val('ep_b5')) * 5000)
            + (toInt(val('ep_b10')) * 10000)
            + (toInt(val('ep_b20')) * 20000);
        var out = qs('ep_montant');
        if (out) out.value = formatNumber(total);

        var lettre = qs('ep_montant_lettre');
        if (lettre) window.mpAutofillMontantLettre(lettre, total);
        return total;
    }

    function fillText(id, value) {
        var el = qs(id);
        if (!el) return;
        el.textContent = (value === undefined || value === null || value === '') ? '—' : String(value);
    }

    function openEditModal() {
        var modalEl = qs('editPreuveModal');
        if (!modalEl || !window.bootstrap) return;
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    async function fetchProof(idPreuve) {
        var url = 'modificationpreuvecaisse.php?ajax_preuve=1&id_preuve=' + encodeURIComponent(idPreuve);
        var res = await fetch(url, { credentials: 'same-origin' });
        return await res.json();
    }

    async function saveProof() {
        var idPreuve = toInt(val('ep_id_preuve'));
        if (idPreuve <= 0) return;

        var montant = calcMontantEdit();
        var payload = new URLSearchParams();
        payload.set('ajax_update', '1');
        payload.set('id_preuve', String(idPreuve));
        payload.set('montant', String(montant));
        payload.set('b0', String(toInt(val('ep_b0'))));
        payload.set('b1', String(toInt(val('ep_b1'))));
        payload.set('b2', String(toInt(val('ep_b2'))));
        payload.set('b5', String(toInt(val('ep_b5'))));
        payload.set('b10', String(toInt(val('ep_b10'))));
        payload.set('b20', String(toInt(val('ep_b20'))));
        payload.set('montant_lettre', String(val('ep_montant_lettre') || ''));

        clearAlert(qs('epAlert'));
        var btn = qs('epSaveBtn');
        if (btn) btn.disabled = true;
        try {
            var res = await fetch('modificationpreuvecaisse.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload.toString(),
                credentials: 'same-origin'
            });
            var json = await res.json();
            if (!json || !json.success) {
                setAlert(qs('epAlert'), (json && json.message) ? json.message : 'Erreur lors de la modification.', 'danger');
                return;
            }

            setAlert(qs('epAlert'), json.message || 'Modification enregistrée.', 'success');

            // Refresh simple: recharger la page pour mettre à jour la liste
            setTimeout(function () { window.location.reload(); }, 900);
        } catch (e) {
            setAlert(qs('epAlert'), 'Erreur réseau lors de la modification.', 'danger');
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    document.addEventListener('input', function (e) {
        if (!e.target) return;
        if (e.target.classList && e.target.classList.contains('ep-billet')) {
            calcMontantEdit();
        }
    });

    // Si l'utilisateur modifie le champ “lettre”, on arrête l'auto
    var epLettre = qs('ep_montant_lettre');
    if (epLettre) {
        epLettre.addEventListener('input', function () {
            epLettre.dataset.manual = '1';
        });
        epLettre.addEventListener('blur', function () {
            if (!epLettre.value || String(epLettre.value).trim() === '') {
                epLettre.dataset.manual = '0';
                calcMontantEdit();
            }
        });
    }

    var saveBtn = qs('epSaveBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function () { saveProof(); });
    }

    document.addEventListener('click', async function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('.js-edit-proof') : null;
        if (!btn) return;

        clearAlert(qs('lpAlert'));
        var id = toInt(btn.getAttribute('data-id_preuve'));
        if (id <= 0) return;

        btn.disabled = true;
        try {
            var data = await fetchProof(id);
            if (!data || !data.success || !data.proof) {
                setAlert(qs('lpAlert'), (data && data.message) ? data.message : 'Impossible de charger la preuve.', 'danger');
                return;
            }

            var p = data.proof;
            if (p.conforme) {
                setAlert(qs('lpAlert'), "Preuve conforme : aucune modification n'est proposée.", 'info');
                return;
            }

            // Pré-remplir le modal
            clearAlert(qs('epAlert'));
            qs('ep_id_preuve').value = String(p.id_preuve || id);
            fillText('ep_id', p.id_preuve);
            fillText('ep_date', p.date_rapportement);
            fillText('ep_compte', p.compte_label);
            fillText('ep_caissier', (p.caissier_pseudo ? (p.caissier_pseudo + ' (ID ' + p.id_user + ')') : ('ID ' + p.id_user)));
            fillText('ep_expected', formatNumber(toInt(p.expected_total)));
            fillText('ep_entree', formatNumber(toInt(p.entree_total)));
            fillText('ep_status', p.conforme ? 'Conforme' : 'Non conforme');

            qs('ep_b0').value = String(toInt(p.b0));
            qs('ep_b1').value = String(toInt(p.b1));
            qs('ep_b2').value = String(toInt(p.b2));
            qs('ep_b5').value = String(toInt(p.b5));
            qs('ep_b10').value = String(toInt(p.b10));
            qs('ep_b20').value = String(toInt(p.b20));
            qs('ep_montant_lettre').value = String(p.montant_lettre || '');

            // si le backend n'a pas fourni de lettre, on laisse l'auto remplir
            var lettre = qs('ep_montant_lettre');
            if (lettre) {
                lettre.dataset.manual = (lettre.value && String(lettre.value).trim() !== '') ? '1' : '0';
            }

            calcMontantEdit();
            openEditModal();
        } catch (err) {
            setAlert(qs('lpAlert'), 'Erreur lors du chargement de la preuve.', 'danger');
        } finally {
            btn.disabled = false;
        }
    });
})();
</script>
