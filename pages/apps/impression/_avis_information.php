<?php
session_start();

require_once(__DIR__ . '/../PDF/fpdf.php');
require_once(__DIR__ . '/../PDF/font/CenturyGothic.php');
require_once(__DIR__ . '/../PDF/html_table13.php');
require_once(__DIR__ . '/../public/connect.php');
require_once(__DIR__ . '/../public/fonction.php');

function tableExists(PDO $bdd, string $table): bool
{
	try {
		$st = $bdd->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
		$st->execute([$table]);
		return (bool)$st->fetchColumn();
	} catch (Throwable $e) {
		return false;
	}
}

function fmtDateFrLong(DateTimeInterface $d): string
{
	$months = [
		1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
		7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
	];
	$m = (int)$d->format('n');
	return (int)$d->format('d') . ' ' . ($months[$m] ?? $d->format('m')) . ' ' . $d->format('Y');
}

function avisHtmlToTextForPdf(string $html): string
{
	$html = trim($html);
	if ($html === '') return '';

	// Retire scripts/styles
	$html = preg_replace('#<\s*(script|style)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html) ?? '';

	// Sauts de ligne HTML -> texte
	$html = str_ireplace(["<br>", "<br/>", "<br />"], "\n", $html);
	$html = preg_replace('#</\s*p\s*>#i', "\n\n", $html) ?? $html;
	$html = preg_replace('#</\s*div\s*>#i', "\n", $html) ?? $html;
	$html = preg_replace('#<\s*li\b[^>]*>#i', "\n• ", $html) ?? $html;
	$html = preg_replace('#</\s*li\s*>#i', "", $html) ?? $html;

	$text = strip_tags($html);
	$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
	$text = preg_replace("/[ \t]+/", " ", $text) ?? $text;
	$text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

	return trim($text);
}

$userId = (int)($_SESSION['auth'] ?? 0);
if ($userId <= 0) {
	http_response_code(403);
	die('Accès refusé');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
	http_response_code(400);
	die('Paramètre manquant.');
}

if (!tableExists($bdd, 'avis_information')) {
	http_response_code(500);
	die('La table avis_information est introuvable. Exécutez db/avis_information.sql.');
}

// Profil entreprise (pour l'entête)
$profil = [];
try {
	$stProfil = $bdd->query('SELECT * FROM profil_entreprise LIMIT 1');
	$profil = $stProfil ? ($stProfil->fetch(PDO::FETCH_ASSOC) ?: []) : [];
} catch (Throwable $e) {
	$profil = [];
}
foreach (['denomination', 'adresse', 'phone', 'email', 'arrete', 'exploitation'] as $k) {
	if (!array_key_exists($k, $profil)) $profil[$k] = '';
}

$avis = null;
try {
	$st = $bdd->prepare('SELECT * FROM avis_information WHERE id_avis = ? LIMIT 1');
	$st->execute([$id]);
	$avis = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$avis = null;
}

if (!$avis) {
	http_response_code(404);
	die('Avis introuvable.');
}

$objet = trim((string)($avis['objet'] ?? ''));
$contenu = (string)($avis['contenu'] ?? '');

$createdAtRaw = trim((string)($avis['created_at'] ?? ''));
$dtRef = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $createdAtRaw)
	?: DateTimeImmutable::createFromFormat('Y-m-d', $createdAtRaw)
	?: new DateTimeImmutable();

$reference = 'AI-' . $dtRef->format('Ymd') . '-' . $id;
$lieu = 'Conakry';

// Si le contenu est du texte brut, on l'échappe et on convertit les retours ligne.
$contenuTrim = trim($contenu);
if ($contenuTrim !== '' && strip_tags($contenuTrim) === $contenuTrim) {
	$contenuTrim = nl2br(htmlspecialchars($contenuTrim, ENT_QUOTES, 'UTF-8'));
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->AddFont('CenturyGothic', '', 'CenturyGothic.php');
$pdf->AddFont('CenturyGothic', 'B', 'CenturyGothic_bold.php');
$pdf->SetAutoPageBreak(true, 12);

if (!empty($profil)) {
	genererEntete($pdf, $profil);
}

// Référence
$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->Cell(0, 6, pdf_text_compat('Réf : ' . $reference), 0, 1, 'L');
$pdf->Ln(8);

// Titre
$pdf->SetFont('CenturyGothic', 'B', 20);
$pdf->Cell(0, 10, pdf_text_compat("AVIS D'INFORMATION"), 0, 1, 'C');
$pdf->Ln(8);

// Objet ("Objet :" uniquement en gras)
$pdf->SetFont('CenturyGothic', 'B', 12);
$x = $pdf->GetX();
$y = $pdf->GetY();
$pdf->Cell(16, 7, pdf_text_compat('Objet :'), 0, 0, 'L');
$pdf->SetFont('CenturyGothic', '', 12);
$pdf->SetXY($x + 16, $y);
$pdf->MultiCell(0, 7, pdf_text_compat($objet), 0, 'L');
$pdf->Ln(6);

// Corps
$pdf->SetFont('CenturyGothic', '', 11);
if ($contenuTrim !== '') {
	$contenuText = avisHtmlToTextForPdf($contenuTrim);
	if ($contenuText === '') {
		$pdf->MultiCell(0, 7, pdf_text_compat('—'), 0, 'L');
	} else {
		$lineHeight = 3; // interligne 1
		$pdf->MultiCell(0, $lineHeight, pdf_text_compat($contenuText), 0, 'J');
	}
} else {
	$pdf->MultiCell(0, 7, pdf_text_compat('—'), 0, 'L');
}

// Signature
$pdf->Ln(18);
$pdf->SetFont('CenturyGothic', 'B', 12);
$pdf->Cell(0, 7, pdf_text_compat($lieu . ', le ' . fmtDateFrLong($dtRef)), 0, 1, 'C');

$pdf->Ln(22);
$pdf->SetFont('CenturyGothic', '', 12);
//$pdf->Cell(0, 7, pdf_text_compat('Signature et cachet'), 0, 1, 'C');

$pdf->Output('AVIS_INFORMATION_' . $id . '.pdf', 'I');
