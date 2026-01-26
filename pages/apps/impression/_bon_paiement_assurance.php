<?php
session_start();

require('../PDF/fpdf.php');
require('../PDF/html_table13.php');
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');

function appec_parseInt($v): int {
    return (int)(is_numeric($v) ? $v : 0);
}

$reglementId = appec_parseInt($_GET['reglement_id'] ?? 0);
if ($reglementId <= 0) {
    die('Paramètres invalides');
}

// Profil entreprise
$profil = $bdd->prepare('SELECT * FROM profil_entreprise LIMIT 1');
$profil->execute();
$dataProfil = $profil->fetch(PDO::FETCH_ASSOC) ?: [];
$devise = 'GNF';
try {
    if (!empty($dataProfil['devise'])) $devise = trim((string)$dataProfil['devise']);
} catch (Throwable $e) {
    $devise = 'GNF';
}

// Règlement
$reg = null;
try {
    $st = $bdd->prepare('SELECT * FROM assurance_reglements WHERE id = ? LIMIT 1');
    $st->execute([$reglementId]);
    $reg = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    $reg = null;
}

if (!$reg) {
    die('Règlement introuvable');
}

$assuranceId = (int)($reg['assurance_id'] ?? 0);
$assuranceNom = '';
try {
    // Détection colonne ID assurances
    $idCol = 'id_assurance';
    if (function_exists('dbTableHasColumn')) {
        if (dbTableHasColumn($bdd, 'assurances', 'id_assurance')) $idCol = 'id_assurance';
        elseif (dbTableHasColumn($bdd, 'assurances', 'd_assurance')) $idCol = 'd_assurance';
        elseif (dbTableHasColumn($bdd, 'assurances', 'id')) $idCol = 'id';
    }
    $stA = $bdd->prepare('SELECT assurance FROM assurances WHERE ' . $idCol . ' = ? LIMIT 1');
    $stA->execute([$assuranceId]);
    $assuranceNom = (string)($stA->fetchColumn() ?: '');
} catch (Throwable $e) {
    $assuranceNom = '';
}

$montant = (float)($reg['montant'] ?? 0);
$datePaiement = (string)($reg['date_paiement'] ?? '');
$modePaiement = (string)($reg['mode_paiement'] ?? '');
$reference = (string)($reg['reference'] ?? '');
$commentaire = (string)($reg['commentaire'] ?? '');
$pd = (string)($reg['periode_debut'] ?? '');
$pf = (string)($reg['periode_fin'] ?? '');

$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->AddFont('CenturyGothic', '', 'CenturyGothic.php');
$pdf->AddFont('CenturyGothic', 'B', 'CenturyGothic_bold.php');
$pdf->SetAutoPageBreak(true, 12);
setlocale(LC_CTYPE, 'fr_FR');

if (function_exists('genererEntete')) {
    genererEntete($pdf, $dataProfil);
}

$pdf->Ln(10);
$pdf->SetFont('CenturyGothic', 'B', 14);
$pdf->Cell(0, 8, pdf_text_compat('BON DE PAIEMENT N° ' . $reglementId), 0, 1, 'C');

$pdf->SetFont('CenturyGothic', '', 11);
$pdf->Ln(2);
$pdf->Cell(0, 6, pdf_text_compat('Entreprise : ' . ($assuranceNom !== '' ? $assuranceNom : ('#' . $assuranceId))), 0, 1);
if ($pd !== '' && $pf !== '') {
    $pdf->Cell(0, 6, pdf_text_compat('Période : du ' . $pd . ' au ' . $pf), 0, 1);
}
$pdf->Cell(0, 6, pdf_text_compat('Date paiement : ' . ($datePaiement !== '' ? $datePaiement : date('Y-m-d H:i:s'))), 0, 1);
$pdf->Ln(2);

// Détails en petit tableau
$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->SetFillColor(0, 102, 204);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(70, 10, pdf_text_compat('Libellé'), 1, 0, 'C', true);
$pdf->Cell(120, 10, pdf_text_compat('Valeur'), 1, 1, 'C', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(255, 255, 255);
$pdf->SetFont('CenturyGothic', '', 11);

$pdf->Cell(70, 8, pdf_text_compat('Montant'), 1, 0, 'L');
$pdf->Cell(120, 8, pdf_text_compat(number_format($montant, 0, ',', ' ') . ' ' . $devise), 1, 1, 'R');

$pdf->Cell(70, 8, pdf_text_compat('Mode'), 1, 0, 'L');
$pdf->Cell(120, 8, pdf_text_compat($modePaiement !== '' ? $modePaiement : '-'), 1, 1, 'L');

$pdf->Cell(70, 8, pdf_text_compat('Référence'), 1, 0, 'L');
$pdf->Cell(120, 8, pdf_text_compat($reference !== '' ? $reference : '-'), 1, 1, 'L');

$pdf->Cell(70, 8, pdf_text_compat('Commentaire'), 1, 0, 'L');
$pdf->MultiCell(120, 8, pdf_text_compat($commentaire !== '' ? $commentaire : '-'), 1, 'L');

$pdf->Ln(10);
$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->Cell(0, 6, pdf_text_compat('Signature et cachet'), 0, 1, 'R');
$pdf->Ln(10);
$pdf->SetFont('CenturyGothic', '', 10);
$pdf->Cell(0, 6, pdf_text_compat('Le comptable'), 0, 1, 'R');

$pdf->Output('BON_PAIEMENT_ASSURANCE_' . $reglementId . '.pdf', 'I');
