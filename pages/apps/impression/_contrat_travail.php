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

function getEmployesColumnMap(PDO $bdd): array
{
	$fields = [];
	try {
		$stmt = $bdd->query('SHOW COLUMNS FROM employes');
		$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
		foreach ($rows as $r) {
			$f = (string)($r['Field'] ?? '');
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
		'sexe' => isset($fields['sexe']) ? 'sexe' : null,
		'nationalite' => isset($fields['nationalite']) ? 'nationalite' : null,
		'lieu_naissance' => isset($fields['lieuNaissance']) ? 'lieuNaissance' : (isset($fields['lieu_naissance']) ? 'lieu_naissance' : null),
		'nin' => isset($fields['nin']) ? 'nin' : (isset($fields['nni']) ? 'nni' : (isset($fields['NNI']) ? 'NNI' : null)),
		'expiration_nin' => isset($fields['expirationNin']) ? 'expirationNin' : (isset($fields['expiration_nin']) ? 'expiration_nin' : null),
		'engagement' => isset($fields['engagement']) ? 'engagement' : null,
		'type_contrat' => isset($fields['typeContrat']) ? 'typeContrat' : (isset($fields['type_contrat']) ? 'type_contrat' : null),
		'prime_transport' => isset($fields['PrimeTransport']) ? 'PrimeTransport' : (isset($fields['prime_transport']) ? 'prime_transport' : null),
		'prime_logement' => isset($fields['PrimeLogement']) ? 'PrimeLogement' : (isset($fields['prime_logement']) ? 'prime_logement' : null),
		'prime_vie' => isset($fields['PrimeVie']) ? 'PrimeVie' : (isset($fields['prime_vie']) ? 'prime_vie' : null),
	];
}

function getProfilEntrepriseColumnMap(PDO $bdd): array
{
	$fields = [];
	try {
		$stmt = $bdd->query('SHOW COLUMNS FROM profil_entreprise');
		$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
		foreach ($rows as $r) {
			$f = (string)($r['Field'] ?? '');
			if ($f !== '') {
				$fields[$f] = true;
			}
		}
	} catch (Throwable $e) {
		$fields = [];
	}

	$rccmCol = null;
	foreach (['rccm', 'RCCM', 'rccm_entreprise', 'rc_cm'] as $k) {
		if (isset($fields[$k])) {
			$rccmCol = $k;
			break;
		}
	}

	return [
		'rccm' => $rccmCol,
	];
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

function frWordsBelowHundred(int $n): string
{
	$units = [
		0 => 'zéro', 1 => 'un', 2 => 'deux', 3 => 'trois', 4 => 'quatre', 5 => 'cinq', 6 => 'six', 7 => 'sept', 8 => 'huit', 9 => 'neuf',
		10 => 'dix', 11 => 'onze', 12 => 'douze', 13 => 'treize', 14 => 'quatorze', 15 => 'quinze', 16 => 'seize',
	];
	if ($n <= 16) return $units[$n];
	if ($n < 20) return 'dix-' . $units[$n - 10];

	$ten = intdiv($n, 10);
	$u = $n % 10;

	if ($ten === 7) {
		return frWordsBelowHundred(60 + ($n - 70));
	}
	if ($ten === 9) {
		return frWordsBelowHundred(80 + ($n - 90));
	}

	$tens = [
		2 => 'vingt',
		3 => 'trente',
		4 => 'quarante',
		5 => 'cinquante',
		6 => 'soixante',
		8 => 'quatre-vingt',
	];

	$tenWord = $tens[$ten] ?? '';
	if ($ten === 8 && $u === 0) {
		return 'quatre-vingts';
	}
	if ($u === 0) {
		return $tenWord;
	}
	if ($u === 1 && ($ten === 2 || $ten === 3 || $ten === 4 || $ten === 5 || $ten === 6)) {
		return $tenWord . '-et-un';
	}
	return $tenWord . '-' . $units[$u];
}

function frWordsBelowThousand(int $n): string
{
	if ($n < 100) return frWordsBelowHundred($n);
	$hundreds = intdiv($n, 100);
	$rest = $n % 100;
	$base = ($hundreds === 1) ? 'cent' : (frWordsBelowHundred($hundreds) . ' cent');
	if ($rest === 0 && $hundreds > 1) {
		return $base . 's';
	}
	if ($rest === 0) return $base;
	return $base . ' ' . frWordsBelowHundred($rest);
}

function frWordsInt(int $n): string
{
	if ($n < 0) return 'moins ' . frWordsInt(-$n);
	if ($n < 1000) return frWordsBelowThousand($n);

	$parts = [];
	$scales = [
		1000000000 => 'milliard',
		1000000 => 'million',
		1000 => 'mille',
	];

	$remaining = $n;
	foreach ($scales as $value => $label) {
		if ($remaining < $value) continue;
		$qty = intdiv($remaining, $value);
		$remaining = $remaining % $value;

		if ($value === 1000) {
			if ($qty === 1) {
				$parts[] = 'mille';
			} else {
				$parts[] = frWordsBelowThousand($qty) . ' mille';
			}
		} else {
			$word = frWordsInt($qty) . ' ' . $label;
			if ($qty > 1) $word .= 's';
			$parts[] = $word;
		}
	}

	if ($remaining > 0) {
		$parts[] = frWordsBelowThousand($remaining);
	}

	return implode(' ', $parts);
}

function moneyLine(?float $amount, string $currency): string
{
	if ($amount === null) return '';
	$amt = (float)$amount;
	$intAmt = (int)round($amt);
	$formatted = number_format($intAmt, 0, ',', ' ');
	$words = frWordsInt(max(0, $intAmt));
	if (function_exists('mb_convert_case')) {
		$words = (string)mb_convert_case($words, MB_CASE_TITLE, 'UTF-8');
	} else {
		$words = ucfirst($words);
	}

	if (strtoupper($currency) === 'GNF') {
		return $formatted . ' ' . $currency . ' (' . $words . ' de franc guinéen)';
	}
	return $formatted . ' ' . $currency . ' (' . $words . ')';
}

$userId = (int)($_SESSION['auth'] ?? 0);
if ($userId <= 0) {
	http_response_code(403);
	die('Accès refusé');
}

$idEmploye = isset($_GET['id_employe']) ? (int)$_GET['id_employe'] : 0;
if ($idEmploye <= 0) {
	http_response_code(400);
	die('Paramètre id_employe manquant.');
}

if (!tableExists($bdd, 'employes')) {
	http_response_code(500);
	die('Table employes introuvable.');
}

$empCols = getEmployesColumnMap($bdd);

$profilCols = getProfilEntrepriseColumnMap($bdd);

// Profil entreprise
$profil = null;
try {
	$stProfil = $bdd->query('SELECT * FROM profil_entreprise LIMIT 1');
	$row = $stProfil ? $stProfil->fetch(PDO::FETCH_ASSOC) : false;
	$profil = $row ? $row : null;
} catch (Throwable $e) {
	$profil = null;
}
if (is_array($profil)) {
	foreach (['denomination', 'sigle', 'adresse', 'phone', 'email', 'responsable', 'arrete', 'exploitation', 'devise'] as $k) {
		if (!array_key_exists($k, $profil)) $profil[$k] = '';
	}
}

// Employé
$emp = null;
try {
	$nameCol = $empCols['name'];
	$salaryCol = $empCols['salary'];
	$select = [
		'e.id_employe',
		"e.`$nameCol` AS nom_employe",
		'e.date_naissance',
		'e.adresse',
		'e.telephone',
		'e.email',
		'e.date_embauche',
		'e.poste',
		'e.service',
		'e.status',
		"e.`$salaryCol` AS salaire_base",
	];
	if ($empCols['sexe'] !== null) $select[] = 'e.`' . $empCols['sexe'] . '` AS sexe';
	if ($empCols['nationalite'] !== null) $select[] = 'e.`' . $empCols['nationalite'] . '` AS nationalite';
	if ($empCols['lieu_naissance'] !== null) $select[] = 'e.`' . $empCols['lieu_naissance'] . '` AS lieu_naissance';
	if ($empCols['nin'] !== null) $select[] = 'e.`' . $empCols['nin'] . '` AS nin';
	if ($empCols['expiration_nin'] !== null) $select[] = 'e.`' . $empCols['expiration_nin'] . '` AS expiration_nin';
	if ($empCols['engagement'] !== null) $select[] = 'e.`' . $empCols['engagement'] . '` AS engagement';
	if ($empCols['type_contrat'] !== null) $select[] = 'e.`' . $empCols['type_contrat'] . '` AS type_contrat';
	if ($empCols['prime_transport'] !== null) $select[] = 'e.`' . $empCols['prime_transport'] . '` AS prime_transport';
	if ($empCols['prime_logement'] !== null) $select[] = 'e.`' . $empCols['prime_logement'] . '` AS prime_logement';
	if ($empCols['prime_vie'] !== null) $select[] = 'e.`' . $empCols['prime_vie'] . '` AS prime_vie';

	$sql = 'SELECT ' . implode(', ', $select) . ' FROM employes e WHERE e.id_employe = ? LIMIT 1';
	$st = $bdd->prepare($sql);
	$st->execute([$idEmploye]);
	$row = $st->fetch(PDO::FETCH_ASSOC);
	$emp = $row ?: null;
} catch (Throwable $e) {
	error_log('[impression/_contrat_travail] ' . $e->getMessage());
	$emp = null;
}

if (!is_array($emp)) {
	http_response_code(404);
	die('Employé introuvable.');
}

if ((int)($emp['status'] ?? 0) !== 1) {
	http_response_code(403);
	die('Contrat indisponible pour un employé inactif.');
}

$currency = (string)($profil['devise'] ?? 'GNF');
if (trim($currency) === '') $currency = 'GNF';

$nomEmploye = trim((string)($emp['nom_employe'] ?? ''));
$poste = trim((string)($emp['poste'] ?? ''));
$adresseEmp = trim((string)($emp['adresse'] ?? ''));

$sexeRaw = trim((string)($emp['sexe'] ?? ''));
$sexeLower = strtolower($sexeRaw);
$civilite = 'Madame/Monsieur';
$employeLabel = "l’employée";
$neeLabel = 'Né';
if ($sexeRaw !== '') {
	// Mapping demandé: 1 => Monsieur / employé ; 0 => Madame / employée
	if (is_numeric($sexeRaw)) {
		$sexeInt = (int)$sexeRaw;
		if ($sexeInt === 1) {
			$civilite = 'Monsieur';
			$employeLabel = "l’employé";
			$neeLabel = 'Né';
		} elseif ($sexeInt === 0) {
			$civilite = 'Madame';
			$employeLabel = "l’employée";
			$neeLabel = 'Née';
		}
	} elseif (in_array($sexeLower, ['f', 'feminin', 'féminin', 'femme', 'female'], true)) {
		$civilite = 'Madame';
		$employeLabel = "l’employée";
		$neeLabel = 'Née';
	} elseif (in_array($sexeLower, ['m', 'masculin', 'homme', 'male'], true)) {
		$civilite = 'Monsieur';
		$employeLabel = "l’employé";
		$neeLabel = 'Né';
	}
}

$dtStart = null;
try {
	$raw = trim((string)($emp['date_embauche'] ?? ''));
	if ($raw !== '') {
		$dtStart = new DateTime($raw);
	}
} catch (Throwable $e) {
	$dtStart = null;
}
if (!$dtStart) {
	$dtStart = new DateTime();
}

// Type de contrat: 1 = CDD, 0 = CDI (fallback: CDD)
$typeContratRaw = isset($emp['type_contrat']) ? trim((string)$emp['type_contrat']) : '';
$typeContrat = 1;
if ($typeContratRaw !== '') {
	if ($typeContratRaw === '0' || $typeContratRaw === '1' || is_numeric($typeContratRaw)) {
		$typeContrat = ((int)$typeContratRaw === 0) ? 0 : 1;
	}
}

// Engagement en jours (période de 3 mois = 90 jours par défaut)
$engagementDays = (int)($emp['engagement'] ?? 0);
$periodeJours = $engagementDays > 0 ? $engagementDays : 90;

// Date de fin uniquement pour CDD
$dtEnd = null;
if ($typeContrat === 1) {
	$dtEnd = (clone $dtStart);
	$dtEnd->modify('+' . $periodeJours . ' days');
	$dtEnd->modify('-1 day');
}

$dtToday = new DateTime();

$companyName = trim((string)($profil['denomination'] ?? ''));
if ($companyName === '') $companyName = '________________________';
$companyAddr = trim((string)($profil['adresse'] ?? ''));
$companyArrete = trim((string)($profil['arrete'] ?? ''));
$companyDG = trim((string)($profil['responsable'] ?? ''));
if ($companyDG === '') $companyDG = '________________________';

$companyRccm = '';
if (is_array($profil) && !empty($profilCols['rccm'])) {
	$companyRccm = trim((string)($profil[$profilCols['rccm']] ?? ''));
}
if ($companyRccm === '') $companyRccm = '____________________________';

$salaireBase = isset($emp['salaire_base']) && $emp['salaire_base'] !== '' ? (float)$emp['salaire_base'] : null;
$primeTransport = isset($emp['prime_transport']) && $emp['prime_transport'] !== '' ? (float)$emp['prime_transport'] : null;
$primeVie = isset($emp['prime_vie']) && $emp['prime_vie'] !== '' ? (float)$emp['prime_vie'] : null;
$primeLogement = isset($emp['prime_logement']) && $emp['prime_logement'] !== '' ? (float)$emp['prime_logement'] : null;

function writePara(PDF $pdf, string $text, float $lh = 6.0, string $align = 'J'): void
{
	$text = trim($text);
	if ($text === '') {
		$pdf->Ln($lh);
		return;
	}
	$pdf->MultiCell(0, $lh, pdf_text_compat($text), 0, $align);
	$pdf->Ln(0.5);
}

function writeBoldPara(PDF $pdf, string $text, float $lh = 6.0, string $align = 'J'): void
{
	$text = trim($text);
	if ($text === '') {
		$pdf->Ln($lh);
		return;
	}
	$pdf->SetFont('CenturyGothic', 'B', 11);
	$pdf->MultiCell(0, $lh, pdf_text_compat($text), 0, $align);
	$pdf->Ln(0.5);
	$pdf->SetFont('CenturyGothic', '', 11);
}

function writeLabelAmountBold(PDF $pdf, string $label, string $amount, float $lh = 6.0): void
{
	$pdf->SetFont('CenturyGothic', '', 11);
	$pdf->Write($lh, pdf_text_compat($label));
	$pdf->SetFont('CenturyGothic', 'B', 11);
	$pdf->Write($lh, pdf_text_compat($amount));
	$pdf->SetFont('CenturyGothic', '', 11);
	$pdf->Ln($lh);
	$pdf->Ln(0.5);
}

function writeIndentedLabelAmountBold(PDF $pdf, float $indentMm, string $label, string $amount, float $lh = 6.0): void
{
	$leftMargin = 10.0;
	$startX = $leftMargin + $indentMm;
	$pdf->SetX($startX);
	$pdf->SetFont('CenturyGothic', '', 11);
	$pdf->Write($lh, pdf_text_compat($label));
	$pdf->SetFont('CenturyGothic', 'B', 11);
	$pdf->Write($lh, pdf_text_compat($amount));
	$pdf->SetFont('CenturyGothic', '', 11);
	$pdf->Ln($lh);
	$pdf->Ln(0.5);
}

function writeIndentedPara(PDF $pdf, float $indentMm, string $text, float $lh = 6.0, string $align = 'J'): void
{
	$text = trim($text);
	if ($text === '') {
		$pdf->Ln($lh);
		return;
	}
	$leftMargin = 10.0;
	$rightMargin = 10.0;
	$startX = $leftMargin + $indentMm;
	$pdf->SetX($startX);
	$w = $pdf->GetPageWidth() - $rightMargin - $startX;
	$pdf->MultiCell($w, $lh, pdf_text_compat($text), 0, $align);
	$pdf->Ln(0.5);
}

function buildMatriculeEmploye(int $idEmploye, ?string $dateEmbauche): string
{
	$annee = (int)date('Y');
	if ($dateEmbauche) {
		$ts = strtotime($dateEmbauche);
		if ($ts !== false && $ts > 0) {
			$annee = (int)date('Y', $ts);
		}
	}
	return 'E' . $annee . 'C' . $idEmploye;
}

function code39Patterns(): array
{
	return [
		'0' => 'nnnwwnwnn',
		'1' => 'wnnwnnnnw',
		'2' => 'nnwwnnnnw',
		'3' => 'wnwwnnnnn',
		'4' => 'nnnwwnnnw',
		'5' => 'wnnwwnnnn',
		'6' => 'nnwwwnnnn',
		'7' => 'nnnwnnwnw',
		'8' => 'wnnwnnwnn',
		'9' => 'nnwwnnwnn',
		'A' => 'wnnnnwnnw',
		'B' => 'nnwnnwnnw',
		'C' => 'wnwnnwnnn',
		'D' => 'nnnnwwnnw',
		'E' => 'wnnnwwnnn',
		'F' => 'nnwnwwnnn',
		'G' => 'nnnnnwwnw',
		'H' => 'wnnnnwwnn',
		'I' => 'nnwnnwwnn',
		'J' => 'nnnnwwwnn',
		'K' => 'wnnnnnnww',
		'L' => 'nnwnnnnww',
		'M' => 'wnwnnnnwn',
		'N' => 'nnnnwnnww',
		'O' => 'wnnnwnnwn',
		'P' => 'nnwnwnnwn',
		'Q' => 'nnnnnnwww',
		'R' => 'wnnnnnwwn',
		'S' => 'nnwnnnwwn',
		'T' => 'nnnnwnwwn',
		'U' => 'wwnnnnnnw',
		'V' => 'nwwnnnnnw',
		'W' => 'wwwnnnnnn',
		'X' => 'nwnnwnnnw',
		'Y' => 'wwnnwnnnn',
		'Z' => 'nwwnwnnnn',
		'-' => 'nwnnnnwnw',
		'.' => 'wwnnnnwnn',
		' ' => 'nwwnnnwnn',
		'$' => 'nwnwnwnnn',
		'/' => 'nwnwnnnwn',
		'+' => 'nwnnnwnwn',
		'%' => 'nnnwnwnwn',
		'*' => 'nwnnwnwnn',
	];
}

function code39Encode(string $raw): string
{
	$raw = strtoupper(trim($raw));
	$raw = preg_replace('/[^0-9A-Z\-\. \$\/\+\%]/', '', $raw) ?? '';
	if ($raw === '') {
		$raw = '0';
	}
	return '*' . $raw . '*';
}

function code39WidthMm(string $encoded, float $narrow, float $wide, float $gap): float
{
	$patterns = code39Patterns();
	$w = 0.0;
	$len = strlen($encoded);
	for ($i = 0; $i < $len; $i++) {
		$ch = $encoded[$i];
		if (!isset($patterns[$ch])) continue;
		$pat = $patterns[$ch];
		for ($j = 0; $j < 9; $j++) {
			$w += ($pat[$j] === 'w') ? $wide : $narrow;
		}
		if ($i < $len - 1) {
			$w += $gap;
		}
	}
	return $w;
}

function drawCode39Pdf(PDF $pdf, float $x, float $y, float $height, string $raw, float $narrow, float $wide, float $gap): void
{
	$patterns = code39Patterns();
	$encoded = code39Encode($raw);
	$pdf->SetFillColor(0);
	$cursorX = $x;
	$len = strlen($encoded);
	for ($i = 0; $i < $len; $i++) {
		$ch = $encoded[$i];
		if (!isset($patterns[$ch])) continue;
		$pat = $patterns[$ch];
		for ($j = 0; $j < 9; $j++) {
			$w = ($pat[$j] === 'w') ? $wide : $narrow;
			$isBar = ($j % 2 === 0);
			if ($isBar) {
				$pdf->Rect($cursorX, $y, $w, $height, 'F');
			}
			$cursorX += $w;
		}
		if ($i < $len - 1) {
			$cursorX += $gap;
		}
	}
}

class ContratTravailPDF extends PDF
{
	public function getLeftMargin(): float
	{
		return (float)$this->lMargin;
	}

	public function getRightMargin(): float
	{
		return (float)$this->rMargin;
	}

	function Footer()
	{
		$this->SetY(-12);
		$this->SetFont('CenturyGothic', '', 8);
		$this->Cell(0, 5, pdf_text_compat('Page ' . $this->PageNo() . '/{nb}'), 0, 0, 'C');
	}
}

$pdf = new ContratTravailPDF('P', 'mm', 'A4');
$pdf->SetMargins(10, 10, 10);
$pdf->AliasNbPages();
$pdf->AddFont('CenturyGothic', '', 'CenturyGothic.php');
$pdf->AddFont('CenturyGothic', 'B', 'CenturyGothic_bold.php');
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 14);

if (is_array($profil)) {
	genererEntete($pdf, $profil);
}

// Titre
$pdf->SetFont('CenturyGothic', 'B', 14);
$pdf->Cell(0, 8, pdf_text_compat($typeContrat === 0 ? 'CONTRAT DE TRAVAIL A DUREE INDETERMINEE' : 'CONTRAT DE TRAVAIL A DUREE DETERMINEE'), 0, 1, 'C');
$pdf->Ln(4);

$pdf->SetFont('CenturyGothic', '', 11);

writePara($pdf, 'Entre les soussignés :');

$pdf->Ln(2);

$line1 = 'La ' . $companyName;
$line2 = 'service spécialisé en ophtalmologie, créé par l’arrêté ' . ($companyArrete !== '' ? $companyArrete : '________________') . ', immatriculée sous le n° RCCM/' . $companyRccm . ' sise à ' . ($companyAddr !== '' ? $companyAddr : '____________________________') . ' représenté par ' . $companyDG . ', agissant en qualité de Directeur Général, dénommé ci-après l’employeur.';

writePara($pdf, $line1 . ' ' . $line2);

$pdf->Ln(2);

writeBoldPara($pdf, 'D’une part,');

$pdf->Ln(2);

writePara($pdf, 'Et :');

$nomBloc = trim($civilite . ' ' . $nomEmploye);
writePara($pdf, $nomBloc . ',');

$birthDateTxt = '________________';
try {
	$raw = trim((string)($emp['date_naissance'] ?? ''));
	if ($raw !== '') {
		$dtN = new DateTime($raw);
		$birthDateTxt = fmtDateFrLong($dtN);
	}
} catch (Throwable $e) {
	$birthDateTxt = '________________';
}

$lieuNaissance = trim((string)($emp['lieu_naissance'] ?? ''));
if ($lieuNaissance === '') $lieuNaissance = '________________';

writePara($pdf, $neeLabel . ' le ' . $birthDateTxt . ' à ' . $lieuNaissance . ',');
$nationalite = trim((string)($emp['nationalite'] ?? ''));
if ($nationalite === '') {
	$nationalite = 'Guinéenne';
}
writePara($pdf, 'Nationalité : ' . $nationalite . ',');

$nin = trim((string)($emp['nin'] ?? ''));
if ($nin === '') $nin = '______________________________';

$ninExpTxt = '________________';
try {
	$raw = trim((string)($emp['expiration_nin'] ?? ''));
	if ($raw !== '') {
		$dtExp = new DateTime($raw);
		$ninExpTxt = fmtDateFrLong($dtExp);
	}
} catch (Throwable $e) {
	$ninExpTxt = '________________';
}

writePara($pdf, 'Pièce d’identité nationale n° ' . $nin . ' qui expire le ' . $ninExpTxt . ',');
writePara($pdf, 'Domicilié à ' . ($adresseEmp !== '' ? $adresseEmp : '____________________________') . ',');
writePara($pdf, 'Dénommé ci-après « ' . $employeLabel . ' ».');

$pdf->Ln(2);

writePara($pdf, 'Qui déclare être libre de tout engagement et donne libre consentement du présent contrat de travail.');

$pdf->Ln(2);

writeBoldPara($pdf, 'D’autre part,');

$pdf->Ln(2);

$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->Cell(0, 8, pdf_text_compat('IL A ETE CONVENU CE QUI SUIT'), 0, 1, 'C');

$pdf->Ln(2);

$pdf->SetFont('CenturyGothic', '', 11);
writePara($pdf, 'Le contrat est régi par la législation suivante :');
writePara($pdf, 'Loi portant sur le Code du travail en République de Guinée institué par l’ordonnance N°003/PRG/SGG/88 du 28 janvier 1988 et de la convention collective nationale interprofessionnelle.');

$pdf->Ln(2);

$pdf->SetFont('CenturyGothic', 'B', 11);
writePara($pdf, 'Article 1er : Attributions et fonctions', 6, 'L');
$pdf->SetFont('CenturyGothic', '', 11);

$pdf->Ln(2);

$qualif = $poste !== '' ? $poste : '________________';
$engageVerb = ($neeLabel === 'Née') ? 'est engagée' : 'est engagé';
writePara($pdf, $civilite . ' ' . $nomEmploye . ' ' . $engageVerb . ' par la ' . $companyName . ', en qualité de ' . $qualif . ', pour effectuer toutes tâches pouvant lui être confiées en rapport avec sa qualification.');
writePara($pdf, $employeLabel . ' s’engage à s’acquitter en toutes circonstances avec soin et fidélité des travaux qui lui seront confiés par son employeur ou son représentant.');

$pdf->Ln(2);

$pdf->SetFont('CenturyGothic', 'B', 11);
writePara($pdf, 'Article 2 : Durée du contrat et Période d’essaie', 6, 'L');
$pdf->SetFont('CenturyGothic', '', 11);

$pdf->Ln(2);

if ($typeContrat === 1) {
	writePara($pdf, 'En application de l’article 122.4 du code du travail, la durée du présent contrat est définie sur une période de ' . $periodeJours . ' jour(s), il prend effet à compter du ' . fmtDateFrLong($dtStart) . ' et prend fin le ' . ($dtEnd ? fmtDateFrLong($dtEnd) : '________________') . '.');
} else {
	writePara($pdf, 'Le présent contrat est à durée indéterminée et prend effet à compter du ' . fmtDateFrLong($dtStart) . '.');
}
writePara($pdf, 'Durant cette période, le contrat de travail pourra être résilié par chacune des parties moyennant un préavis de 30 jours.');

$pdf->Ln(2);

$pdf->SetFont('CenturyGothic', 'B', 11);
writePara($pdf, 'Article 3 : Horaires', 6, 'L');
$pdf->SetFont('CenturyGothic', '', 11);

$pdf->Ln(2);

writePara($pdf, $employeLabel . ' exercera ses fonctions selon les horaires en vigueur dans l’entreprise qui est conclus de 08h30 à 16h30 incluant une heure (1h) de pause qui sera prise entre 12h et 14h.');

$pdf->Ln(2);

$pdf->SetFont('CenturyGothic', 'B', 11);
writePara($pdf, 'Article 4 : Clause de Mobilité', 6, 'L');
$pdf->SetFont('CenturyGothic', '', 11);

$pdf->Ln(2);

writePara($pdf, $employeLabel . ' exercera ses fonctions au siège principal de l’entreprise basé à Conakry.');
writePara($pdf, 'Toutefois, compte tenu de la nature de ses activités, la Direction se réserve la possibilité de le muter dans tout autre site de la clinique en République de Guinée.');

$pdf->Ln(2);

$pdf->SetFont('CenturyGothic', 'B', 11);
writePara($pdf, 'Article 5 : Rémunération', 6, 'L');
$pdf->SetFont('CenturyGothic', '', 11);

$pdf->Ln(2);

$salLine = $salaireBase !== null ? moneyLine($salaireBase, $currency) : '____________________________';
writePara($pdf, 'En rémunération de ses services, ' . $employeLabel . ' percevra un salaire de base mensuel de :');
writeBoldPara($pdf, '' . $salLine . '.');
writePara($pdf, 'Auquel pourra s’ajouter les primes ci-dessous :');

$pt = $primeTransport !== null ? (number_format((int)round($primeTransport), 0, ',', ' ') . ' ' . $currency) : '________________';
$pv = $primeVie !== null ? (number_format((int)round($primeVie), 0, ',', ' ') . ' ' . $currency) : '________________';
$pl = $primeLogement !== null ? (number_format((int)round($primeLogement), 0, ',', ' ') . ' ' . $currency) : '________________';

$indent = 8.0;
writeIndentedLabelAmountBold($pdf, $indent, 'a)  Indemnité de transport : ', $pt);
writeIndentedLabelAmountBold($pdf, $indent, 'b)  Prime de cherté de vie : ', $pv);
writeIndentedLabelAmountBold($pdf, $indent, 'c)  Prime de Logement : ', $pl);
writeIndentedPara($pdf, $indent, 'd)  Prime de rendement et qualité : (En fonction du prorata retenu par la direction)');

$pdf->Ln(2);

$pdf->SetFont('CenturyGothic', 'B', 11);
writePara($pdf, 'Article 6 : Congés payés', 6, 'L');
$pdf->SetFont('CenturyGothic', '', 11);

$pdf->Ln(2);

writePara($pdf, $employeLabel . ' aura droit à deux jours et demi ouvrables de congés payés par mois de service. L’ordre des départs en congé est établi par l’employeur en fonction des nécessités de services et autant que possible, en tenant compte des préférences des salariés.');

$pdf->Ln(2);

$pdf->SetFont('CenturyGothic', 'B', 11);
writePara($pdf, 'Article 7 : Sécurité Sociale', 6, 'L');
$pdf->SetFont('CenturyGothic', '', 11);

$pdf->Ln(2);

writePara($pdf, $employeLabel . ' sera immatriculée à la Caisse Nationale de la Sécurité Sociale conformément à la loi, il s’engage à respecter les normes d’hygiène et de sécurité sur les lieux de travail.');

$pdf->Ln(2);

$pdf->SetFont('CenturyGothic', 'B', 11);
writePara($pdf, 'Article 8 : Rupture du contrat', 6, 'L');
$pdf->SetFont('CenturyGothic', '', 11);

$pdf->Ln(2);

writePara($pdf, 'La rupture du contrat résulte soit de la démission de ' . $employeLabel . ', soit du licenciement par l’employeur dans les conditions prévues par le Code du travail et par la Convention Collective Générale.');
writePara($pdf, 'Le contrat peut-être, notamment, rompu pour faute lourde.');
writePara($pdf, 'En référence aux usages et à la jurisprudence, seront notamment considérés comme faute lourde de ' . $employeLabel . ' :');

$pdf->Ln(2);

writeIndentedPara($pdf, $indent,'-  L’insubordination, l’abandon de poste, les retards au travail, l’inconduite notoire ;');
writeIndentedPara($pdf, $indent,'-  Les irrégularités dans l’établissement des documents prescrits, les faux en écriture;');
writeIndentedPara($pdf, $indent,'-  Les déficits de gestion injustifiés;');
writeIndentedPara($pdf, $indent,'-  Les prélèvements personnels excédant le solde créditeur de ' . $employeLabel . ' dans les livres;');
writeIndentedPara($pdf, $indent,'-  Les vols ou détournements de biens dans un intérêt autre que celui de la clinique ;');
writeIndentedPara($pdf, $indent,'-  Les abus de biens sociaux ;');
writeIndentedPara($pdf, $indent,'-  Le non-respect d’obligations orales ou écrites dans le règlement de la clinique ;');
writeIndentedPara($pdf, $indent,'-  L’intempérance, les rixes ou brutalité dans le service.');

$pdf->Ln(2);

$pdf->SetFont('CenturyGothic', 'B', 11);
writePara($pdf, 'Article 9 : Obligation de discrétion', 6, 'L');
$pdf->SetFont('CenturyGothic', '', 11);

$pdf->Ln(2);

writePara($pdf, 'Dans l’exécution du présent contrat, ' . $employeLabel . ' est tenue, en plus d’une obligation de réserve générale et de secret professionnel, à une discrétion absolue sur tous les faits qu’il peut apprendre en raison de ses fonctions ou de son appartenance à la clinique, et qui concernent tant sa gestion, son fonctionnement que sa situation.');
writePara($pdf, 'La présente obligation demeure en vigueur même après l’expiration ou la résiliation du contrat.');
$pdf->Ln(2);
$pdf->SetFont('CenturyGothic', 'B', 11);
writePara($pdf, 'Article 10 : Cas de litiges', 6, 'L');
$pdf->SetFont('CenturyGothic', '', 11);

$pdf->Ln(2);

writePara($pdf, 'En cas de litige, les parties s’engagent à régler les différents nés de l’exécution ou de l’interprétation du présent contrat à l’amiable.');
writePara($pdf, 'A défaut, le litige sera porté devant les tribunaux compétents de Conakry.');
$pdf->Ln(2);

$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->Cell(0, 8, pdf_text_compat('Fait à Conakry en deux (2) exemplaires, le ' . fmtDateFrLong($dtToday) . '.'), 0, 1, 'C');
$pdf->SetFont('CenturyGothic', '', 11);
$pdf->Ln(6);

$colW = ($pdf->GetPageWidth() - 20) / 2;
$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->Cell($colW, 6, pdf_text_compat('Pour l’employeur'), 0, 0, 'L');
$pdf->Cell($colW, 6, pdf_text_compat('Pour ' . $employeLabel), 0, 1, 'R');

$pdf->SetFont('CenturyGothic', '', 11);
$pdf->Ln(32);

$pdf->Cell($colW, 6, pdf_text_compat($companyDG), 0, 0, 'L');
$pdf->Cell($colW, 6, pdf_text_compat($nomEmploye), 0, 1, 'R');

// Code-barres (Code39) sur la dernière page, basé sur le matricule
$matriculeEmploye = buildMatriculeEmploye($idEmploye, isset($emp['date_embauche']) ? (string)$emp['date_embauche'] : null);
$barcodeValue = $matriculeEmploye;

// Zone disponible (au-dessus du footer)
$pageW = $pdf->GetPageWidth();
$leftMargin = $pdf->getLeftMargin();
$rightMargin = $pdf->getRightMargin();
$maxW = $pageW - $leftMargin - $rightMargin;
$targetMaxW = $maxW * 0.70; // réduire la largeur visuelle du code-barres (70% de la zone dispo)
$barH = 14.0;
$barY = $pdf->GetPageHeight() - 12.0 - 4.0 - $barH - 6.0; // footer(12) + marge
$encoded = code39Encode($barcodeValue);

// Si on est trop bas sur la page, on force une dernière page propre pour le code-barres
if ($pdf->GetY() > ($barY - 8.0)) {
	$pdf->AddPage();
	$pageW = $pdf->GetPageWidth();
	$leftMargin = $pdf->getLeftMargin();
	$rightMargin = $pdf->getRightMargin();
	$maxW = $pageW - $leftMargin - $rightMargin;
	$targetMaxW = $maxW * 0.70; // réduire la largeur visuelle du code-barres (70% de la zone dispo)
	$barY = $pdf->GetPageHeight() - 12.0 - 4.0 - $barH - 6.0;
}

// Choisir la plus grande combinaison qui tient
$candidates = [
	[0.35, 1.05, 0.20],
	[0.30, 0.90, 0.18],
	[0.25, 0.75, 0.16],
	[0.22, 0.65, 0.14],
	[0.20, 0.60, 0.12],
];
$narrow = 0.25;
$wide = 0.75;
$gap = 0.16;
foreach ($candidates as $c) {
	$wTest = code39WidthMm($encoded, $c[0], $c[1], $c[2]);
	if ($wTest <= $targetMaxW) {
		$narrow = $c[0];
		$wide = $c[1];
		$gap = $c[2];
		break;
	}
}
$barcodeW = code39WidthMm($encoded, $narrow, $wide, $gap);
$barX = $leftMargin + max(0.0, ($maxW - $barcodeW) / 2);

drawCode39Pdf($pdf, $barX, $barY, $barH, $barcodeValue, $narrow, $wide, $gap);
$pdf->SetFont('CenturyGothic', '', 9);
$pdf->SetXY($leftMargin, $barY + $barH + 3.0);
//$pdf->Cell($maxW, 5, pdf_text_compat('Matricule : ' . $matriculeEmploye), 0, 0, 'C');

$pdf->Output('CONTRAT_TRAVAIL_' . $matriculeEmploye . '.pdf', 'I');
