<?php
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

header('Content-Type: text/html; charset=UTF-8');

$compte = filter_input(INPUT_GET, 'compte', FILTER_VALIDATE_INT);
$debut = isset($_GET['debut']) ? (string)$_GET['debut'] : '';
$fin = isset($_GET['fin']) ? (string)$_GET['fin'] : '';

function is_date_ymd($s) {
    return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
}

if (!$debut || !$fin || !is_date_ymd($debut) || !is_date_ymd($fin) || $debut > $fin) {
    echo '<div class="alert alert-danger">Période invalide.</div>';
    exit;
}

$paiementsAmountCol = 'montant';
try {
    $stCols = $bdd->query('SHOW COLUMNS FROM paiements');
    if ($stCols) {
        while ($c = $stCols->fetch(PDO::FETCH_ASSOC)) {
            if (($c['Field'] ?? '') === 'montant_paye') {
                $paiementsAmountCol = 'montant_paye';
                break;
            }
        }
    }
} catch (Throwable $e) {
    $paiementsAmountCol = 'montant';
}

$sql = 'SELECT p.id_affectation, p.datepaiement, p.compte, p.`' . $paiementsAmountCol . '` AS montant_remb, a.id_patient, a.type
        FROM paiements p
        LEFT JOIN affectations a ON a.id_affectation = p.id_affectation
        WHERE p.remboursement = 1 AND p.datepaiement BETWEEN :debut AND :fin';
$params = [':debut' => $debut, ':fin' => $fin];

if ($compte !== null && (int)$compte !== 0) {
    $sql .= ' AND p.compte = :compte';
    $params[':compte'] = (int)$compte;
}

$sql .= ' ORDER BY p.datepaiement DESC, p.id_affectation DESC';

try {
    $stmt = $bdd->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo '<div class="alert alert-danger">Erreur lors du chargement.</div>';
    exit;
}

$deviseLocal = isset($devise) ? $devise : 'GNF';

if (!$rows) {
    echo '<div class="alert alert-info mb-0">Aucun remboursement sur cette période.</div>';
    exit;
}

echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-striped mb-0">';
echo '<thead><tr>';
echo '<th>Date</th>';
echo '<th>Compte</th>';
echo '<th>N° Paiement</th>';
echo '<th>Dossier</th>';
echo '<th>Patient</th>';
echo '<th>Examen</th>';
echo '<th>Montant remboursé</th>';
echo '<th>Action</th>';
echo '</tr></thead><tbody>';

foreach ($rows as $r) {
    $idAffectation = (int)($r['id_affectation'] ?? 0);
    $idPatient = isset($r['id_patient']) ? (int)$r['id_patient'] : 0;
    $type = isset($r['type']) ? (string)$r['type'] : '';
    $datePaiement = isset($r['datepaiement']) ? (string)$r['datepaiement'] : '';
    $idCompte = isset($r['compte']) ? (int)$r['compte'] : 0;
    $montantRemb = isset($r['montant_remb']) ? (float)$r['montant_remb'] : 0.0;

    $numero = '';
    if (function_exists('getNumeroPaiement') && $idAffectation > 0) {
        try { $numero = (string)getNumeroPaiement($bdd, $idAffectation); } catch (Throwable $e) { $numero = ''; }
    }

    $nomCompte = $idCompte > 0 && function_exists('compte') ? (string)compte($idCompte) : '';
    $patientNom = ($idPatient > 0 && function_exists('nom_patient')) ? (string)nom_patient($idPatient) : '';
    $examenNom = ($type !== '' && function_exists('model')) ? (string)model($type) : '';

    echo '<tr>';
    echo '<td>' . htmlspecialchars($datePaiement) . '</td>';
    echo '<td>' . htmlspecialchars($nomCompte) . '</td>';
    echo '<td>' . htmlspecialchars($numero) . '</td>';
    echo '<td>' . htmlspecialchars((string)$idPatient) . '</td>';
    echo '<td>' . htmlspecialchars($patientNom) . '</td>';
    echo '<td>' . htmlspecialchars($examenNom) . '</td>';
    echo '<td>' . number_format($montantRemb, 0, '', ' ') . ' ' . htmlspecialchars($deviseLocal) . '</td>';
    echo '<td>';
    if ($idAffectation > 0) {
        echo '<a class="btn btn-sm btn-secondary" target="_blank" href="imprimer_remboursement.php?affectation=' . urlencode((string)$idAffectation) . '"><i class="fa fa-file-pdf-o"></i> Reçu</a>';
    }
    echo '</td>';
    echo '</tr>';
}

echo '</tbody></table></div>';
