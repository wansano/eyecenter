<?php
require('../PDF/fpdf.php');
require('../PDF/html_table13.php');
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');

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

$assuranceId = isset($_GET['assurance_id']) ? (int)$_GET['assurance_id'] : 0;
$mois = appec_parseMonthOrDefault($_GET['mois'] ?? '');
[$dateDebut, $dateFin] = appec_monthRange($mois);

if ($assuranceId <= 0) {
    die('Paramètres invalides');
}

try {
    if (function_exists('appecEnsureAssuranceFacturationTables')) {
        appecEnsureAssuranceFacturationTables($bdd);
    }
    if (function_exists('appecEnsurePartAssurancesTable')) {
        appecEnsurePartAssurancesTable($bdd);
    }
} catch (Throwable $e) {
    // ignore
}

// La facturation par assurance nécessite patients.assurance
if (!function_exists('dbTableHasColumn') || !dbTableHasColumn($bdd, 'patients', 'assurance')) {
    die('Configuration DB: colonne patients.assurance introuvable.');
}

// Profil entreprise
$profil = $bdd->prepare('SELECT * FROM profil_entreprise');
$profil->execute();
$dataProfil = $profil->fetch(PDO::FETCH_ASSOC) ?: [];

// Nom assurance
$assuranceNom = '';
try {
    $idCol = null;
    if (function_exists('dbTableHasColumn')) {
        if (dbTableHasColumn($bdd, 'assurances', 'id_assurance')) $idCol = 'id_assurance';
        elseif (dbTableHasColumn($bdd, 'assurances', 'd_assurance')) $idCol = 'd_assurance';
        elseif (dbTableHasColumn($bdd, 'assurances', 'id')) $idCol = 'id';
    }
    $idCol = $idCol ?: 'id_assurance';
    $stA = $bdd->prepare('SELECT assurance FROM assurances WHERE ' . $idCol . ' = ? LIMIT 1');
    $stA->execute([$assuranceId]);
    $assuranceNom = (string)($stA->fetchColumn() ?: '');
} catch (Throwable $e) {
    $assuranceNom = '';
}

$rows = [];
try {
    $carteCol = null;
    $tauxCol = null;
    if (function_exists('dbTableHasColumn')) {
        if (dbTableHasColumn($bdd, 'patients', 'carteAdhesion')) $carteCol = 'carteAdhesion';
        elseif (dbTableHasColumn($bdd, 'patients', 'carte_adhesion')) $carteCol = 'carte_adhesion';

        if (dbTableHasColumn($bdd, 'patients', 'tauxPrisecharge')) $tauxCol = 'tauxPrisecharge';
        elseif (dbTableHasColumn($bdd, 'patients', 'TauxPrisecharge')) $tauxCol = 'TauxPrisecharge';
        elseif (dbTableHasColumn($bdd, 'patients', 'taux_prisecharge')) $tauxCol = 'taux_prisecharge';
    }

    $carteSelect = $carteCol ? ('COALESCE(MAX(p.' . $carteCol . '), \'\') AS carte_adhesion') : "'' AS carte_adhesion";
    $tauxSelect = $tauxCol ? ('COALESCE(MAX(p.' . $tauxCol . '), 0) AS taux_prisecharge') : '0 AS taux_prisecharge';

    $st = $bdd->prepare(
        'SELECT pa.patient AS patient_id, COALESCE(p.nom_patient, \'\') AS nom_patient, '
        . '       ' . $carteSelect . ', '
        . '       ' . $tauxSelect . ', '
        . '       COUNT(*) AS nb_passages, COALESCE(SUM(pa.montant),0) AS total_assurance '
        . 'FROM partAssurances pa '
        . 'INNER JOIN patients p ON p.id_patient = pa.patient '
        . 'WHERE p.assurance = ? AND pa.datepaiement BETWEEN ? AND ? '
        . 'GROUP BY pa.patient, p.nom_patient '
        . 'ORDER BY p.nom_patient ASC'
    );
    $st->execute([$assuranceId, $dateDebut, $dateFin]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $rows = [];
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->AddFont('CenturyGothic', '', 'CenturyGothic.php');
$pdf->AddFont('CenturyGothic', 'B', 'CenturyGothic_bold.php');
$pdf->SetAutoPageBreak(true, 12);
setlocale(LC_CTYPE, 'fr_FR');

if (function_exists('genererEntete')) {
    genererEntete($pdf, $dataProfil);
}

$pdf->Ln(2);
$pdf->SetFont('CenturyGothic', 'B', 14);
$pdf->Cell(0, 8, pdf_text_compat('RAPPORT PATIENTS - ' . $mois), 0, 1, 'C');

$pdf->SetFont('CenturyGothic', '', 11);
$pdf->Ln(2);
$pdf->Cell(0, 6, pdf_text_compat('Entreprise : ' . ($assuranceNom !== '' ? $assuranceNom : ('#' . $assuranceId))), 0, 1);
$pdf->Cell(0, 6, pdf_text_compat('Période : du ' . $dateDebut . ' au ' . $dateFin), 0, 1);

$pdf->Ln(4);

// Tableau style "rapport interrogation" (FPDF direct)
$pdf->Ln(1);
$pdf->SetFont('CenturyGothic', 'B', 11);

// Largeur utile A4 ~ 190mm
$wPatient = 75;
$wCarte = 40;
$wCouv = 25;
$wNb = 20;
$wTot = 30;

$pdf->SetFillColor(0, 102, 204);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell($wPatient, 10, pdf_text_compat('Patient'), 1, 0, 'C', true);
$pdf->Cell($wCarte, 10, pdf_text_compat('N° carte'), 1, 0, 'C', true);
$pdf->Cell($wCouv, 10, pdf_text_compat('Couverture'), 1, 0, 'C', true);
$pdf->Cell($wNb, 10, pdf_text_compat('Passages'), 1, 0, 'C', true);
$pdf->Cell($wTot, 10, pdf_text_compat('Montant'), 1, 1, 'C', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(255, 255, 255);
$pdf->SetFont('CenturyGothic', '', 11);

if (empty($rows)) {
    $pdf->Cell($wPatient + $wCarte + $wCouv + $wNb + $wTot, 8, pdf_text_compat('Aucune donnée pour cette période.'), 1, 1, 'C');
} else {
    foreach ($rows as $r) {
        if ($pdf->GetY() > 270) {
            $pdf->AddPage();
            if (function_exists('genererEntete')) {
                genererEntete($pdf, $dataProfil);
            }
            $pdf->Ln(20);
            $pdf->SetFont('CenturyGothic', 'B', 14);
            $pdf->Cell(0, 8, pdf_text_compat('RAPPORT PATIENTS - ' . $mois), 0, 1, 'C');
            $pdf->SetFont('CenturyGothic', '', 11);
            $pdf->Ln(2);
            $pdf->Cell(0, 6, pdf_text_compat('Assurance : ' . ($assuranceNom !== '' ? $assuranceNom : ('#' . $assuranceId))), 0, 1);
            $pdf->Cell(0, 6, pdf_text_compat('Période : du ' . $dateDebut . ' au ' . $dateFin), 0, 1);
            $pdf->Ln(4);

            $pdf->SetFont('CenturyGothic', 'B', 11);
            $pdf->SetFillColor(0, 102, 204);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell($wPatient, 10, pdf_text_compat('Patient'), 1, 0, 'C', true);
            $pdf->Cell($wCarte, 10, pdf_text_compat('N° carte'), 1, 0, 'C', true);
            $pdf->Cell($wCouv, 10, pdf_text_compat('Couverture'), 1, 0, 'C', true);
            $pdf->Cell($wNb, 10, pdf_text_compat('Passages'), 1, 0, 'C', true);
            $pdf->Cell($wTot, 10, pdf_text_compat('Total part assurance'), 1, 1, 'C', true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetFont('CenturyGothic', '', 11);
        }

        $patient = (string)($r['nom_patient'] ?? '');
        $carte = (string)($r['carte_adhesion'] ?? '');
        $tauxVal = (float)($r['taux_prisecharge'] ?? 0);
        if ($tauxVal < 0) $tauxVal = 0;
        if ($tauxVal > 100) $tauxVal = 100;
        $nb = (int)($r['nb_passages'] ?? 0);
        $tot = (float)($r['total_assurance'] ?? 0);

        $pdf->Cell($wPatient, 8, pdf_text_compat($patient), 1, 0, 'L');
        $pdf->Cell($wCarte, 8, pdf_text_compat($carte), 1, 0, 'L');
        $pdf->Cell($wCouv, 8, pdf_text_compat(number_format($tauxVal, 0, ',', ' ') . '%'), 1, 0, 'C');
        $pdf->Cell($wNb, 8, pdf_text_compat(number_format($nb, 0, ',', ' ')), 1, 0, 'C');
        $pdf->Cell($wTot, 8, pdf_text_compat(number_format($tot, 0, ',', ' ')), 1, 1, 'R');
    }
}

$pdf->Output();
