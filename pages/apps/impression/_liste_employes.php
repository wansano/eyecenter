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

function getEmployeNameColumn(PDO $bdd): string
{
	try {
		$st = $bdd->query('SHOW COLUMNS FROM employes');
		$cols = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
		$has = [];
		foreach ($cols as $c) {
			$f = (string)($c['Field'] ?? '');
			if ($f !== '') $has[$f] = true;
		}
		if (isset($has['nomEmploye'])) return 'nomEmploye';
		if (isset($has['nom_employe'])) return 'nom_employe';
	} catch (Throwable $e) {
		// ignore
	}
	return 'nomEmploye';
}

$userId = (int)($_SESSION['auth'] ?? 0);
if ($userId <= 0) {
	http_response_code(403);
	die('Accès refusé');
}

if (!tableExists($bdd, 'employes')) {
	http_response_code(500);
	die('Table employes introuvable.');
}

$nameCol = getEmployeNameColumn($bdd);

// Filtres (optionnels)
$filterYear = isset($_GET['annee_emploi']) ? (int)$_GET['annee_emploi'] : 0;
$filterStatus = isset($_GET['statut']) ? trim((string)$_GET['statut']) : '';
$filterService = isset($_GET['service']) ? (int)$_GET['service'] : 0;

$where = [];
$params = [];

// Afficher uniquement les employés actifs
$where[] = 'e.status = ?';
$params[] = 1;
if ($filterYear > 0) {
	$where[] = 'YEAR(e.date_embauche) = ?';
	$params[] = $filterYear;
}
if ($filterService > 0) {
	// Compat: ancien stockage (libellé) + nouveau (ID)
	$where[] = '(e.service = ? OR oName.id_org = ?)';
	$params[] = (string)$filterService;
	$params[] = (int)$filterService;
}

$sql = 'SELECT e.id_employe,
		e.`' . $nameCol . '` AS nom,
		e.telephone,
		e.email,
		e.poste,
		COALESCE(oId.celulle, oName.celulle, e.service) AS service,
		COALESCE(oId.id_organigramme, oName.id_org, 0) AS service_id,
		e.date_embauche,
		e.status
	FROM employes e
	LEFT JOIN organigramme oId ON oId.id_organigramme = e.service
	LEFT JOIN (
		SELECT celulle, MIN(id_organigramme) AS id_org
		FROM organigramme
		GROUP BY celulle
	) oName ON oName.celulle = e.service';
if (!empty($where)) {
	$sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY (e.date_embauche IS NULL) ASC, e.date_embauche ASC, e.id_employe ASC';

$rows = [];
try {
	$st = $bdd->prepare($sql);
	$st->execute($params);
	$rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	error_log('[impression/_liste_employes] ' . $e->getMessage());
	$rows = [];
}

// Profil entreprise (pour l'entête)
$profil = null;
try {
	$stProfil = $bdd->query('SELECT * FROM profil_entreprise LIMIT 1');
	$row = $stProfil ? $stProfil->fetch(PDO::FETCH_ASSOC) : false;
	$profil = $row ? $row : null;
} catch (Throwable $e) {
	$profil = null;
}
if (is_array($profil)) {
	foreach (['denomination', 'adresse', 'phone', 'email', 'arrete', 'exploitation'] as $k) {
		if (!array_key_exists($k, $profil)) $profil[$k] = '';
	}
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->AddFont('CenturyGothic', '', 'CenturyGothic.php');
$pdf->AddFont('CenturyGothic', 'B', 'CenturyGothic_bold.php');
$pdf->SetAutoPageBreak(true, 12);

if (is_array($profil)) {
	genererEntete($pdf, $profil);
}

$pdf->SetFont('CenturyGothic', 'B', 16);
$pdf->Cell(0, 8, pdf_text_compat('LISTE DES EMPLOYÉS'), 0, 1, 'C');
$pdf->Ln(2);

// En-tête tableau
$widths = [
	'MATRICULE' => 22,
	'NOM' => 45,
	'CONTACT' => 22,
	'POSTE' => 45,
	'SERVICE' => 43,
	'EMBAUCHE' => 20,
];

$pdf->SetFillColor(235, 235, 235);
$pdf->SetFont('CenturyGothic', 'B', 10);
foreach ($widths as $label => $w) {
	$pdf->Cell($w, 7, pdf_text_compat($label), 1, 0, 'C', true);
}
$pdf->Ln();

$pdf->SetFont('CenturyGothic', '', 9);
$lineH = 6;
foreach ($rows as $r) {
	$stat = ((int)($r['status'] ?? 0) === 1) ? 'Actif' : 'Inactif';
	$tsEmb = null;
	try {
		$rawEmb = (string)($r['date_embauche'] ?? '');
		$t = $rawEmb !== '' ? strtotime($rawEmb) : false;
		$tsEmb = ($t !== false) ? $t : null;
	} catch (Throwable $e) {
		$tsEmb = null;
	}
	$anneeMatricule = $tsEmb ? date('Y', $tsEmb) : date('Y');
	$matricule = 'E' . $anneeMatricule . 'C' . (string)($r['id_employe'] ?? '');
	$serviceLabel = '';
	$serviceId = (int)($r['service_id'] ?? 0);
	if ($serviceId > 0) {
		$serviceLabel = (string)service($serviceId);
	}
	if (trim($serviceLabel) === '') {
		$serviceLabel = (string)($r['service'] ?? '');
	}
	$values = [
		$matricule,
		(string)($r['nom'] ?? ''),
		(string)($r['telephone'] ?? ''),
		(string)($r['poste'] ?? ''),
		(string)$serviceLabel,
		(string)($r['date_embauche'] ?? ''),
	];

	// Calcul hauteur ligne (pour les champs multi-lignes potentiels)
	$maxLines = 1;
	$idx = 0;
	foreach ($widths as $label => $w) {
		$text = pdf_text_compat($values[$idx] ?? '');
		$nb = max(1, (int)ceil($pdf->GetStringWidth($text) / max(1, ($w - 2))));
		if ($nb > $maxLines) $maxLines = $nb;
		$idx++;
	}
	$rowH = $lineH * $maxLines;

	if ($pdf->GetY() + $rowH > ($pdf->GetPageHeight() - 12)) {
		$pdf->AddPage();
		$pdf->SetFillColor(235, 235, 235);
		$pdf->SetFont('CenturyGothic', 'B', 10);
		foreach ($widths as $label => $w) {
			$pdf->Cell($w, 7, pdf_text_compat($label), 1, 0, 'C', true);
		}
		$pdf->Ln();
		$pdf->SetFont('CenturyGothic', '', 9);
	}

	$x0 = $pdf->GetX();
	$y0 = $pdf->GetY();
	$idx = 0;
	foreach ($widths as $label => $w) {
		$pdf->SetXY($x0, $y0);
		$align = ($label === 'MATRICULE' || $label === 'EMBAUCHE') ? 'C' : 'L';
		$pdf->MultiCell($w, $lineH, pdf_text_compat($values[$idx] ?? ''), 1, $align);
		$x0 += $w;
		$idx++;
	}
	$pdf->SetXY($pdf->GetX(), $y0 + $rowH);
}
$pdf->Ln(4);
$pdf->SetFont('CenturyGothic', '', 10);
$pdf->Cell(0, 6, pdf_text_compat('Imprimé le ' . date('d/m/Y H:i')), 0, 1, 'R');

$pdf->Output('LISTE_EMPLOYES.pdf', 'I');
