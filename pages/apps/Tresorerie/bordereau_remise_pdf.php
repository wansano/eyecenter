<?php
session_start();

// Endpoint déplacé vers /impression
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
header('Location: ../impression/imprimer_borderau.php?id=' . $id);
exit;

require_once(__DIR__ . '/../PDF/fpdf.php');
require_once(__DIR__ . '/../PDF/html_table13.php');
require_once(__DIR__ . '/../public/connect.php');
require_once(__DIR__ . '/../public/fonction.php');

function appec_int($v): int {
	return (int)(is_numeric($v) ? $v : 0);
}

function appec_fmt_date(string $dt): string {
	$dt = trim($dt);
	if ($dt === '') return '';
	try {
		return (new DateTime($dt))->format('d/m/Y');
	} catch (Throwable $e) {
		return $dt;
	}
}

$userId = (int)($_SESSION['auth'] ?? 0);
if ($userId <= 0) {
	die('Accès refusé');
}

$idRemise = appec_int($_GET['id'] ?? 0);
if ($idRemise <= 0) {
	die('Paramètres invalides');
}

$employeeNameCol = 'nomEmploye';
try {
	$cols = $bdd->query('SHOW COLUMNS FROM employes')->fetchAll(PDO::FETCH_ASSOC);
	$fields = array_map(static fn($r) => (string)($r['Field'] ?? ''), $cols);
	if (in_array('nomEmploye', $fields, true)) {
		$employeeNameCol = 'nomEmploye';
	} elseif (in_array('nom_employe', $fields, true)) {
		$employeeNameCol = 'nom_employe';
	}
} catch (Throwable $e) {
	error_log('[bordereau_remise_pdf] SHOW COLUMNS employes => ' . $e->getMessage());
}

// Profil entreprise (pour l'entête)

try {
	$profil = $bdd->prepare('SELECT * FROM profil_entreprise LIMIT 1');
	$profil->execute();
	$dataProfil = $profil->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
	error_log('[bordereau_remise_pdf] profil_entreprise => ' . $e->getMessage());
	$dataProfil = [];
}

$dataProfil = $dataProfil ?: [
	'denomination' => '',
	'adresse' => '',
	'phone' => '',
	'email' => '',
	'arrete' => '',
	'exploitation' => '',
	'devise' => 'GNF',
];


try {
	$sql = "
		SELECT r.*, e.`{$employeeNameCol}` AS nom_employe,
			cdeb.nom_compte AS compte_debite_nom,
			ccre.nom_compte AS compte_credite_nom
		FROM remise_de_compte r
		LEFT JOIN employes e ON e.id_employe = r.id_employe
		LEFT JOIN comptes cdeb ON cdeb.id_compte = r.id_compte2
		LEFT JOIN comptes ccre ON ccre.id_compte = r.id_compte
		WHERE r.id_remise = ?
		LIMIT 1
	";
	$st = $bdd->prepare($sql);
	$st->execute([$idRemise]);
	$r = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	error_log('[bordereau_remise_pdf] load remise => ' . $e->getMessage());
	$r = null;
}
if (!$r) {
	die('Remise introuvable');
}

$devise = !empty($dataProfil['devise']) ? trim((string)$dataProfil['devise']) : 'GNF';

$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 12);

// Fonts charte (utilisées ailleurs dans l'app)
$pdf->AddFont('CenturyGothic', '', 'CenturyGothic.php');
$pdf->AddFont('CenturyGothic', 'B', 'CenturyGothic_bold.php');

setlocale(LC_CTYPE, 'fr_FR');

if (function_exists('genererEntete')) {
	genererEntete($pdf, $dataProfil);
}

$pdf->Ln(4);
$pdf->SetFont('CenturyGothic', 'B', 14);
$pdf->Cell(0, 8, pdf_text('BORDEREAU DE REMISE'), 0, 1, 'C');

$pdf->SetFont('CenturyGothic', '', 11);
$pdf->Ln(1);

$montant = (float)($r['montant'] ?? 0);

// Bloc infos
$pdf->SetFillColor(245, 245, 245);
$pdf->SetDrawColor(220, 220, 220);

$pdf->Cell(40, 8, pdf_text('N° Remise'), 1, 0, 'L', true);
$pdf->Cell(55, 8, pdf_text((string)$idRemise), 1, 0, 'L');
$pdf->Cell(40, 8, pdf_text('Date'), 1, 0, 'L', true);
$pdf->Cell(55, 8, pdf_text(appec_fmt_date((string)($r['date_remise'] ?? ''))), 1, 1, 'L');

$pdf->Cell(40, 8, pdf_text('Employé'), 1, 0, 'L', true);
$pdf->Cell(150, 8, pdf_text((string)($r['nom_employe'] ?? '')), 1, 1, 'L');

$pdf->Cell(40, 8, pdf_text('Type remise'), 1, 0, 'L', true);
$pdf->Cell(55, 8, pdf_text((string)($r['type_remise'] ?? '')), 1, 0, 'L');
$pdf->Cell(40, 8, pdf_text('Mode paiement'), 1, 0, 'L', true);
$pdf->Cell(55, 8, pdf_text((string)($r['mode_paiement'] ?? '')), 1, 1, 'L');

$pdf->Cell(40, 8, pdf_text('Référence'), 1, 0, 'L', true);
$pdf->Cell(150, 8, pdf_text((string)($r['reference'] ?? '')), 1, 1, 'L');

$pdf->Cell(40, 8, pdf_text('Compte débité'), 1, 0, 'L', true);
$pdf->Cell(150, 8, pdf_text((string)($r['compte_debite_nom'] ?? '')), 1, 1, 'L');

$pdf->Cell(40, 8, pdf_text('Compte crédité'), 1, 0, 'L', true);
$pdf->Cell(150, 8, pdf_text((string)($r['compte_credite_nom'] ?? '')), 1, 1, 'L');

$pdf->Cell(40, 8, pdf_text('Montant'), 1, 0, 'L', true);
$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->Cell(150, 8, pdf_text(number_format($montant, 0, ',', ' ') . ' ' . $devise), 1, 1, 'L');
$pdf->SetFont('CenturyGothic', '', 11);

$pdf->Ln(4);
$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->Cell(0, 7, pdf_text('Notes'), 0, 1, 'L');
$pdf->SetFont('CenturyGothic', '', 11);
$pdf->MultiCell(0, 6, pdf_text((string)($r['notes'] ?? '')), 1, 'L');

$pdf->Ln(10);
$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->Cell(95, 6, pdf_text('Signature du remettant'), 0, 0, 'L');
$pdf->Cell(95, 6, pdf_text('Signature / Visa comptabilité'), 0, 1, 'R');
$pdf->Ln(22);
$pdf->SetFont('CenturyGothic', '', 10);
$pdf->Cell(95, 6, pdf_text((string)($r['nom_employe'] ?? '')), 0, 0, 'L');
$pdf->Cell(95, 6, pdf_text(''), 0, 1, 'R');

$pdf->Output('BORDEREAU_REMISE_' . $idRemise . '.pdf', 'I');
