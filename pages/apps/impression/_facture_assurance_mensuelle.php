<?php
// Génération PDF: éviter tout output parasite (warnings/echo) avant FPDF->Output()
ob_start();
if (function_exists('ini_set')) {
    @ini_set('display_errors', '0');
    @ini_set('log_errors', '1');
}
if (function_exists('session_status') && session_status() === PHP_SESSION_NONE) {
    @session_start();
} elseif (!function_exists('session_status')) {
    @session_start();
}

require('../PDF/fpdf.php');
require('../PDF/html_table13.php');
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');

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

// Profil entreprise
$profil = $bdd->prepare('SELECT * FROM profil_entreprise');
$profil->execute();
$dataProfil = $profil->fetch(PDO::FETCH_ASSOC) ?: [];

$devise = 'GNF';
try {
    if (!empty($dataProfil['devise'])) {
        $devise = trim((string)$dataProfil['devise']);
        $denomination = trim((string)$dataProfil['denomination']);
    }
} catch (Throwable $e) {
    $devise = 'GNF';
}

// Assurance
$assuranceNom = '';
try {
    $idCol = appec_getAssuranceIdColumn($bdd) ?: 'id_assurance';
    $stA = $bdd->prepare('SELECT assurance FROM assurances WHERE ' . $idCol . ' = ? LIMIT 1');
    $stA->execute([$assuranceId]);
    $assuranceNom = (string)($stA->fetchColumn() ?: '');
} catch (Throwable $e) {
    $assuranceNom = '';
}

// La facturation par assurance nécessite patients.assurance
if (!function_exists('dbTableHasColumn') || !dbTableHasColumn($bdd, 'patients', 'assurance')) {
    die('Configuration DB: colonne patients.assurance introuvable.');
}

// Totaux
$totCreance = 0.0;
$totRegle = 0.0;
$reste = 0.0;

try {
    $st = $bdd->prepare(
        'SELECT COALESCE(SUM(pa.montant),0) '
        . 'FROM partAssurances pa '
        . 'INNER JOIN patients p ON p.id_patient = pa.patient '
        . 'WHERE p.assurance = ? AND pa.datepaiement BETWEEN ? AND ?'
    );
    $st->execute([$assuranceId, $dateDebut, $dateFin]);
    $totCreance = (float)$st->fetchColumn();

    $st = $bdd->prepare(
        'SELECT COALESCE(SUM(pa.montant_paye),0) '
        . 'FROM partAssurances pa '
        . 'INNER JOIN patients p ON p.id_patient = pa.patient '
        . 'WHERE p.assurance = ? AND pa.datepaiement BETWEEN ? AND ?'
    );
    $st->execute([$assuranceId, $dateDebut, $dateFin]);
    $totRegle = (float)$st->fetchColumn();

    $reste = $totCreance - $totRegle;
    if ($reste < 0) $reste = 0.0;
} catch (Throwable $e) {
    // ignore
}

// Lignes (regroupées par traitement)
$lignes = [];
try {
    $st = $bdd->prepare(
        'SELECT pa.types AS id_type, COUNT(*) AS nb, COALESCE(SUM(pa.montant),0) AS total '
        . 'FROM partAssurances pa '
        . 'INNER JOIN patients p ON p.id_patient = pa.patient '
        . 'WHERE p.assurance = ? AND pa.datepaiement BETWEEN ? AND ? '
        . 'GROUP BY pa.types '
        . 'ORDER BY pa.types ASC'
    );
    $st->execute([$assuranceId, $dateDebut, $dateFin]);
    $lignes = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $lignes = [];
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->AddFont('CenturyGothic', '', 'CenturyGothic.php');
$pdf->AddFont('CenturyGothic', 'B', 'CenturyGothic_bold.php');
$pdf->SetAutoPageBreak(true, 12);
setlocale(LC_CTYPE, 'fr_FR');

// En-tête clinique
if (function_exists('genererEntete')) {
    genererEntete($pdf, $dataProfil);
}

$pdf->Ln(2);
$pdf->SetFont('CenturyGothic', 'B', 14);
$pdf->Cell(0, 8, pdf_text_compat('FACTURE N° ' . $mois), 0, 1, 'C');

$pdf->SetFont('CenturyGothic', '', 11);
$pdf->Ln(2);
$pdf->Cell(0, 6, pdf_text_compat('Entreprise : ' . ($assuranceNom !== '' ? $assuranceNom : ('#' . $assuranceId))), 0, 1);
$pdf->Cell(0, 6, pdf_text_compat('Période : du ' . $dateDebut . ' au ' . $dateFin), 0, 1);
$pdf->Cell(0, 6, pdf_text_compat('Date d\'impression : ' . date('Y-m-d H:i')), 0, 1);

$pdf->Ln(4);

// Tableau style "rapport interrogation" (FPDF direct)
$pdf->Ln(1);
$pdf->SetFont('CenturyGothic', 'B', 11);

// Largeur utile A4 ~ 190mm
$wTraitement = 110;
$wNb = 30;
$wTotal = 50;

$renderHeader = function () use ($pdf, $wTraitement, $wNb, $wTotal) {
    $pdf->SetFillColor(0, 102, 204);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('CenturyGothic', 'B', 11);
    $pdf->Cell($wTraitement, 10, pdf_text_compat('Traitement'), 1, 0, 'C', true);
    $pdf->Cell($wNb, 10, pdf_text_compat('Nombre'), 1, 0, 'C', true);
    $pdf->Cell($wTotal, 10, pdf_text_compat('Montant total'), 1, 1, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetFont('CenturyGothic', '', 11);
};

$renderHeader();

if (empty($lignes)) {
    $pdf->Cell($wTraitement + $wNb + $wTotal, 8, pdf_text_compat('Aucune donnée pour cette période.'), 1, 1, 'C');
} else {
    foreach ($lignes as $l) {
        // Gestion simple du saut de page + répétition de l'entête
        if ($pdf->GetY() > 270) {
            $pdf->AddPage();
            if (function_exists('genererEntete')) {
                genererEntete($pdf, $dataProfil);
            }
            $pdf->Ln(20);
            $pdf->SetFont('CenturyGothic', 'B', 14);
            $pdf->Cell(0, 8, pdf_text_compat('FACTURE N° ' . $mois), 0, 1, 'C');
            $pdf->SetFont('CenturyGothic', '', 11);
            $pdf->Ln(2);
            $pdf->Cell(0, 6, pdf_text_compat('Entreprise : ' . ($assuranceNom !== '' ? $assuranceNom : ('#' . $assuranceId))), 0, 1);
            $pdf->Cell(0, 6, pdf_text_compat('Période : du ' . $dateDebut . ' au ' . $dateFin), 0, 1);
            $pdf->Ln(4);
            $renderHeader();
        }

        $idType = (int)($l['id_type'] ?? 0);
        $nb = (int)($l['nb'] ?? 0);
        $total = (float)($l['total'] ?? 0);

        $nomTraitement = (string)$idType;
        if (function_exists('model')) {
            try {
                $nomTraitement = (string)model($idType);
            } catch (Throwable $e) {
                $nomTraitement = (string)$idType;
            }
        }

        $pdf->Cell($wTraitement, 8, pdf_text_compat($nomTraitement), 1, 0, 'L');
        $pdf->Cell($wNb, 8, pdf_text_compat((string)$nb), 1, 0, 'C');
        $pdf->Cell($wTotal, 8, pdf_text_compat(number_format($total, 0, ',', ' ') . ' ' . $devise), 1, 1, 'R');
    }
}

// Lignes récapitulatives (dans le tableau)
$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->Cell($wTraitement + $wNb, 8, pdf_text_compat('Montant de la facture'), 1, 0, 'R');
$pdf->Cell($wTotal, 8, pdf_text_compat(number_format($totCreance, 0, ',', ' ') . ' ' . $devise), 1, 1, 'R');

$pdf->Cell($wTraitement + $wNb, 8, pdf_text_compat('Montant payé'), 1, 0, 'R');
$pdf->Cell($wTotal, 8, pdf_text_compat(number_format($totRegle, 0, ',', ' ') . ' ' . $devise), 1, 1, 'R');

// Ligne Total global à payer
$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->Cell($wTraitement + $wNb, 8, pdf_text_compat('Montant à payer'), 1, 0, 'R');
$pdf->Cell($wTotal, 8, pdf_text_compat(number_format($reste, 0, ',', ' ') . ' ' . $devise), 1, 1, 'R');


// Conditions de paiement + signature comptable
$pdf->Ln(8);
$leftW = 100;
$rightW = 90;
$marginX = 10;

$y = $pdf->GetY();
$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->SetXY($marginX, $y);
$pdf->Cell($leftW, 6, pdf_text_compat('Conditions de paiement :'), 0, 0, 'L');
$pdf->SetFont('CenturyGothic', '', 11);
$pdf->SetXY($marginX + $leftW, $y);
$pdf->Cell($rightW, 6, pdf_text_compat('La trésorerie'), 0, 1, 'R');

$lineH = 7;
$box = 4;
$optY = $pdf->GetY();

$drawOption = function (string $label) use ($pdf, $marginX, $lineH) {
    $x = $marginX;
    $y = $pdf->GetY();
    $pdf->SetXY($x, $y);
    $pdf->Cell(0, $lineH, pdf_text_compat($label), 0, 1, 'L');
};

$pdf->SetY($optY);
$drawOption('Espèce, Chèque, Virement.');
$drawOption('Les chèques sont à l\'ordre de : ' . ($denomination ?? '__________'));
// Zone signature (alignée à droite)
$signX = $marginX + $leftW;
$signTopY = $optY;
$pdf->SetXY($signX, $signTopY + 6);
$pdf->Cell($rightW, 7, '', 0, 1, 'R');
$pdf->SetXY($signX, $signTopY + 10);
$pdf->Cell($rightW, 7, '', 0, 1, 'R');
$pdf->SetXY($signX, $signTopY + 14);
$pdf->SetFont('CenturyGothic', '', 10);
$authId = (isset($_SESSION) && isset($_SESSION['auth'])) ? (int)$_SESSION['auth'] : 0;
$traitantNom = '';
if ($authId > 0 && function_exists('traitant')) {
    try {
        $traitantNom = (string)traitant($authId);
    } catch (Throwable $e) {
        $traitantNom = '';
    }
}
$pdf->Cell($rightW, 7, pdf_text_compat($traitantNom), 0, 1, 'R');

// Repositionner le curseur sous le bloc
$endY = max($pdf->GetY(), $signTopY + 21);
$pdf->SetY($endY + 2);

// Nettoyer tout output (BOM, warnings, echo) avant l'envoi du PDF
while (ob_get_level() > 0) {
    @ob_end_clean();
}

$pdf->Output();
