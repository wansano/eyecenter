<?php
session_start();

require_once(__DIR__ . '/../public/connect.php');
require_once(__DIR__ . '/../public/fonction.php');

function outputErrorPng(string $message): void
{
	$w = 900;
	$h = 320;
	$im = imagecreatetruecolor($w, $h);
	$bg = imagecolorallocate($im, 255, 255, 255);
	$txt = imagecolorallocate($im, 30, 30, 30);
	imagefilledrectangle($im, 0, 0, $w - 1, $h - 1, $bg);
	imagestring($im, 5, 20, 20, 'Erreur badge', $txt);
	imagestring($im, 3, 20, 60, substr($message, 0, 200), $txt);
	header('Content-Type: image/png');
	header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
	imagepng($im);
	imagedestroy($im);
	exit;
}

function toUtf8(string $text): string
{
	$text = (string)$text;
	if ($text === '') return '';
	// Si déjà UTF-8 valide
	if (@preg_match('//u', $text) === 1) return $text;

	// Tentatives de conversion (souvent DB en Windows-1252/ISO-8859-1)
	if (function_exists('iconv')) {
		$converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $text);
		if (is_string($converted) && $converted !== '') return $converted;
		$converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $text);
		if (is_string($converted) && $converted !== '') return $converted;
	}

	if (function_exists('utf8_encode')) {
		return @utf8_encode($text);
	}

	return $text;
}

function toUpperUtf8(string $text): string
{
	$text = toUtf8($text);
	if ($text === '') return '';

	if (function_exists('mb_strtoupper')) {
		return mb_strtoupper($text, 'UTF-8');
	}

	// Fallback sans mbstring: majuscules ASCII + mapping d’accents courants
	$upperMap = [
		'à' => 'À', 'á' => 'Á', 'â' => 'Â', 'ã' => 'Ã', 'ä' => 'Ä', 'å' => 'Å',
		'æ' => 'Æ',
		'ç' => 'Ç',
		'è' => 'È', 'é' => 'É', 'ê' => 'Ê', 'ë' => 'Ë',
		'ì' => 'Ì', 'í' => 'Í', 'î' => 'Î', 'ï' => 'Ï',
		'ñ' => 'Ñ',
		'ò' => 'Ò', 'ó' => 'Ó', 'ô' => 'Ô', 'õ' => 'Õ', 'ö' => 'Ö', 'ø' => 'Ø',
		'ù' => 'Ù', 'ú' => 'Ú', 'û' => 'Û', 'ü' => 'Ü',
		'ý' => 'Ý', 'ÿ' => 'Ÿ',
		'œ' => 'Œ',
	];

	// strtoupper() n’est pas unicode, mais ok pour ASCII; les accents restent inchangés puis on map.
	$text = strtoupper($text);
	return strtr($text, $upperMap);
}

function toGdSingleByte(string $text): string
{
	// Les fontes bitmap de GD (imagestring) attendent un encodage mono-octet.
	// Si on leur passe de l'UTF-8, les accents peuvent disparaître (ex: "Générale" -> "G n rale").
	$text = toUtf8($text);
	if ($text === '') return '';
	if (function_exists('iconv')) {
		$converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
		if (is_string($converted) && $converted !== '') return $converted;
		$converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
		if (is_string($converted) && $converted !== '') return $converted;
	}
	// Fallback grossier (ISO-8859-1)
	if (function_exists('utf8_decode')) {
		return @utf8_decode($text);
	}
	return $text;
}

function fontCanRenderAccents(string $fontPath): bool
{
	if ($fontPath === '' || !is_file($fontPath)) return false;
	if (!function_exists('imagettftext') || !function_exists('imagecreatetruecolor')) return false;

	$im = @imagecreatetruecolor(80, 50);
	if (!$im) return false;
	$bg = imagecolorallocate($im, 255, 255, 255);
	$fg = imagecolorallocate($im, 0, 0, 0);
	imagefilledrectangle($im, 0, 0, 79, 49, $bg);

	// Tester un caractère accentué + ligature courante
	$test = toGdSingleByte('éœ');
	@imagettftext($im, 20, 0, 5, 30, $fg, $fontPath, $test);

	$ok = false;
	for ($y = 0; $y < 50 && !$ok; $y++) {
		for ($x = 0; $x < 80; $x++) {
			$rgb = imagecolorat($im, $x, $y) & 0xFFFFFF;
			if ($rgb !== 0xFFFFFF) {
				$ok = true;
				break;
			}
		}
	}
	imagedestroy($im);
	return $ok;
}

function firstExistingFontThatRendersAccents(array $candidates): ?string
{
	foreach ($candidates as $p) {
		$fp = null;
		if (@is_file($p)) {
			$fp = $p;
		} else {
			$rp = @realpath($p);
			if ($rp && is_file($rp)) $fp = $rp;
		}
		if (!$fp) continue;
		if (fontCanRenderAccents($fp)) return $fp;
	}
	return null;
}

function formatPersonName(string $name): string
{
	$name = trim(preg_replace('/\s+/', ' ', $name));
	$name = toUtf8($name);
	if ($name === '') return '';
	if (function_exists('mb_convert_case') && function_exists('mb_strtolower')) {
		$lower = mb_strtolower($name, 'UTF-8');
		return mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
	}

	// Fallback UTF-8 sans mbstring (accents courants)
	$lowerMap = [
		'À' => 'à', 'Á' => 'á', 'Â' => 'â', 'Ã' => 'ã', 'Ä' => 'ä', 'Å' => 'å',
		'Ç' => 'ç',
		'È' => 'è', 'É' => 'é', 'Ê' => 'ê', 'Ë' => 'ë',
		'Ì' => 'ì', 'Í' => 'í', 'Î' => 'î', 'Ï' => 'ï',
		'Ñ' => 'ñ',
		'Ò' => 'ò', 'Ó' => 'ó', 'Ô' => 'ô', 'Õ' => 'õ', 'Ö' => 'ö',
		'Ù' => 'ù', 'Ú' => 'ú', 'Û' => 'û', 'Ü' => 'ü',
		'Ý' => 'ý', 'Ÿ' => 'ÿ',
	];
	$upperMap = [
		'à' => 'À', 'á' => 'Á', 'â' => 'Â', 'ã' => 'Ã', 'ä' => 'Ä', 'å' => 'Å',
		'ç' => 'Ç',
		'è' => 'È', 'é' => 'É', 'ê' => 'Ê', 'ë' => 'Ë',
		'ì' => 'Ì', 'í' => 'Í', 'î' => 'Î', 'ï' => 'Ï',
		'ñ' => 'Ñ',
		'ò' => 'Ò', 'ó' => 'Ó', 'ô' => 'Ô', 'õ' => 'Õ', 'ö' => 'Ö',
		'ù' => 'Ù', 'ú' => 'Ú', 'û' => 'Û', 'ü' => 'Ü',
		'ý' => 'Ý', 'ÿ' => 'Ÿ',
	];

	$lower = strtolower($name);
	$lower = strtr($lower, $lowerMap);

	$parts = preg_split("/(\s+|\-|\'|’)/u", $lower, -1, PREG_SPLIT_DELIM_CAPTURE);
	if (!is_array($parts)) {
		return $lower;
	}
	$out = '';
	foreach ($parts as $p) {
		if ($p === '' || preg_match("/^(\s+|\-|\'|’)$/u", $p)) {
			$out .= $p;
			continue;
		}
		if (preg_match('/^./u', $p, $m) === 1) {
			$first = $m[0];
			$rest = (string)preg_replace('/^./u', '', $p);
			$firstUp = strtoupper($first);
			$firstUp = strtr($firstUp, $upperMap);
			$out .= $firstUp . $rest;
		} else {
			$out .= $p;
		}
	}
	return $out;
}

function drawText($im, bool $hasTtf, string $font, int $size, int $x, int $y, int $color, string $text): void
{
	$text = trim($text);
	if ($text === '') return;
	if ($hasTtf) {
		// GD/FreeType avec imagettftext est généralement plus fiable en mono-octet pour les accents (Windows-1252).
		$text = toGdSingleByte($text);
		@imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
	} else {
		$text = toGdSingleByte($text);
		imagestring($im, 5, $x, max(0, $y - 18), $text, $color);
	}
}

function drawTextBold($im, bool $hasTtf, string $font, string $fontBold, int $size, int $x, int $y, int $color, string $text): void
{
	$text = trim($text);
	if ($text === '') return;
	if ($hasTtf) {
		$text = toGdSingleByte($text);
		$useFont = ($fontBold !== '' && is_file($fontBold)) ? $fontBold : $font;
		@imagettftext($im, $size, 0, $x, $y, $color, $useFont, $text);
		@imagettftext($im, $size, 0, $x + 1, $y, $color, $useFont, $text);
		@imagettftext($im, $size, 0, $x, $y + 1, $color, $useFont, $text);
	} else {
		$text = toGdSingleByte($text);
		imagestring($im, 5, $x, max(0, $y - 18), $text, $color);
		imagestring($im, 5, $x + 1, max(0, $y - 18), $text, $color);
	}
}

function firstExistingFont(array $candidates): ?string
{
	foreach ($candidates as $p) {
		if (@is_file($p)) return $p;
		$rp = @realpath($p);
		if ($rp && is_file($rp)) return $rp;
	}
	return null;
}

function findTextFonts(): array
{
	// IMPORTANT: GD/imagettftext() nécessite un fichier .ttf ou .otf.
	// Les polices .woff/.woff2 (web) ne sont pas utilisables directement ici.

	$poppinsRegular = [
		// Dossier projet
		__DIR__ . '/../fonts/poppins/Poppins-Regular.ttf',
		__DIR__ . '/../fonts/poppins/Poppins-Medium.ttf',
		__DIR__ . '/../fonts/poppins/Poppins-500.ttf',
		__DIR__ . '/../fonts/poppins/Poppins-400.ttf',
		__DIR__ . '/../fonts/poppins/Poppins-Regular.otf',
		__DIR__ . '/../PDF/font/Poppins-Regular.ttf',
		__DIR__ . '/../PDF/font/Poppins-Medium.ttf',
		__DIR__ . '/../PDF/font/Poppins-500.ttf',
	];
	$poppinsBold = [
		__DIR__ . '/../fonts/poppins/Poppins-Bold.ttf',
		__DIR__ . '/../fonts/poppins/Poppins-SemiBold.ttf',
		__DIR__ . '/../fonts/poppins/Poppins-700.ttf',
		__DIR__ . '/../fonts/poppins/Poppins-600.ttf',
		__DIR__ . '/../fonts/poppins/Poppins-700.otf',
		__DIR__ . '/../PDF/font/Poppins-Bold.ttf',
		__DIR__ . '/../PDF/font/Poppins-SemiBold.ttf',
		__DIR__ . '/../PDF/font/Poppins-700.ttf',
		__DIR__ . '/../PDF/font/Poppins-600.ttf',
	];

	// Fallbacks (ancien comportement)
	$centuryRegular = [
		__DIR__ . '/../PDF/font/CenturyGothic.ttf',
		__DIR__ . '/../PDF/font/CenturyGothicBook.ttf',
		'/Library/Fonts/Century Gothic.ttf',
		'/System/Library/Fonts/Supplemental/Century Gothic.ttf',
		'C:\\Windows\\Fonts\\GOTHIC.TTF',
		'C:/Windows/Fonts/GOTHIC.TTF',
		'C:\\Windows\\Fonts\\GOTHICB.TTF',
		'C:/Windows/Fonts/GOTHICB.TTF',
	];
	$genericRegular = [
		__DIR__ . '/../PDF/font/FuturaCyrillicBook.ttf',
		'/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
		'/usr/share/fonts/truetype/dejavu/DejaVuSansCondensed.ttf',
		'/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
		'/usr/share/fonts/truetype/freefont/FreeSans.ttf',
		'/System/Library/Fonts/Supplemental/Arial Unicode.ttf',
		'/Library/Fonts/Arial Unicode.ttf',
		'/Library/Fonts/Arial.ttf',
		'/System/Library/Fonts/Supplemental/Arial.ttf',
		'C:\\Windows\\Fonts\\arial.ttf',
		'C:\\Windows\\Fonts\\segoeui.ttf',
	];

	$regular = firstExistingFont(array_merge($poppinsRegular, $centuryRegular, $genericRegular));
	$bold = firstExistingFont(array_merge($poppinsBold, $poppinsRegular, $centuryRegular, $genericRegular));

	// Si une police existe mais ne rend pas correctement les accents (souvent police "subset"), basculer vers une alternative.
	$regularAccent = firstExistingFontThatRendersAccents(array_merge($poppinsRegular, $centuryRegular, $genericRegular));
	$boldAccent = firstExistingFontThatRendersAccents(array_merge($poppinsBold, $poppinsRegular, $centuryRegular, $genericRegular));
	if ($regularAccent) $regular = $regularAccent;
	if ($boldAccent) $bold = $boldAccent;

	return [
		'regular' => $regular,
		'bold' => $bold,
	];
}

function formatContactPhone(string $raw): string
{
	$raw = trim($raw);
	if ($raw === '') return '';
	$digits = preg_replace('/\D+/', '', $raw);
	if ($digits === '') return $raw;

	// Cas Guinée: 9 chiffres => 3-2-2-2 (ex: 620583636 => 620 58 36 36)
	if (strlen($digits) === 9) {
		return substr($digits, 0, 3) . ' ' . substr($digits, 3, 2) . ' ' . substr($digits, 5, 2) . ' ' . substr($digits, 7, 2);
	}
	// Avec indicatif 224 + 9 chiffres
	if (strlen($digits) === 12 && str_starts_with($digits, '224')) {
		$rest = substr($digits, 3);
		return '224 ' . substr($rest, 0, 3) . ' ' . substr($rest, 3, 2) . ' ' . substr($rest, 5, 2) . ' ' . substr($rest, 7, 2);
	}

	return $raw;
}

function locateEntrepriseLogo(): ?string
{
	$candidates = [
		__DIR__ . '/../img/logo.jpg',
		__DIR__ . '/../img/logo.jpeg',
		__DIR__ . '/../img/logo.png',
	];
	foreach ($candidates as $p) {
		$rp = realpath($p);
		if ($rp && is_file($rp)) return $rp;
	}
	return null;
}

function applyWatermarkLogo($im, string $logoPath, int $W, int $H): void
{
	$ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
	$src = null;
	if (in_array($ext, ['jpg', 'jpeg'], true)) {
		$src = @imagecreatefromjpeg($logoPath);
	} elseif ($ext === 'png') {
		$src = @imagecreatefrompng($logoPath);
	}
	if (!$src) return;

	$sw = imagesx($src);
	$sh = imagesy($src);
	if ($sw <= 0 || $sh <= 0) {
		imagedestroy($src);
		return;
	}

	// Taille du filigrane: plus petit pour éviter un rendu trop “large”
	$targetW = (int)round($W * 0.45);
	$targetH = (int)round($targetW * ($sh / $sw));
	if ($targetH > (int)round($H * 0.55)) {
		$targetH = (int)round($H * 0.55);
		$targetW = (int)round($targetH * ($sw / $sh));
	}

	$dst = imagecreatetruecolor($targetW, $targetH);
	$white = imagecolorallocate($dst, 255, 255, 255);
	imagefilledrectangle($dst, 0, 0, $targetW - 1, $targetH - 1, $white);
	imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, $sw, $sh);

	// Position: à droite, centré verticalement (évite la zone photo)
	$marginRight = 20;
	$x = max(0, $W - $targetW - $marginRight);
	$y = (int)(($H - $targetH) / 2);
	@imagecopymerge($im, $dst, $x, $y, 0, 0, $targetW, $targetH, 8);

	imagedestroy($dst);
	imagedestroy($src);
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
	$raw = preg_replace('/[^0-9A-Z\-\. \$\/\+%]/', '', $raw);
	return '*' . $raw . '*';
}

function code39WidthPx(string $encoded, int $narrow, int $wide, int $gap): int
{
	$patterns = code39Patterns();
	$w = 0;
	foreach (str_split($encoded) as $ch) {
		$pat = $patterns[$ch] ?? null;
		if (!$pat) continue;
		for ($i = 0; $i < 9; $i++) {
			$w += ($pat[$i] === 'w') ? $wide : $narrow;
		}
		$w += $gap;
	}
	return $w;
}

function drawCode39($im, int $x, int $y, int $height, string $raw, int $narrow, int $wide, int $gap, int $color): void
{
	$patterns = code39Patterns();
	$encoded = code39Encode($raw);
	$cursor = $x;
	foreach (str_split($encoded) as $ch) {
		$pat = $patterns[$ch] ?? null;
		if (!$pat) continue;
		for ($i = 0; $i < 9; $i++) {
			$w = ($pat[$i] === 'w') ? $wide : $narrow;
			if (($i % 2) === 0) {
				imagefilledrectangle($im, $cursor, $y, $cursor + $w - 1, $y + $height, $color);
			}
			$cursor += $w;
		}
		$cursor += $gap;
	}
}

$userId = (int)($_SESSION['auth'] ?? 0);
if ($userId <= 0) {
	http_response_code(403);
	exit;
}

$idEmploye = (int)($_GET['id_employe'] ?? 0);
if ($idEmploye <= 0) {
	outputErrorPng('Paramètre id_employe manquant');
}

if (!function_exists('imagecreatetruecolor')) {
	outputErrorPng('GD non disponible');
}

// Détecter colonne nom
$nameCol = 'nomEmploye';
try {
	$st = $bdd->query('SHOW COLUMNS FROM employes');
	$cols = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
	$has = [];
	foreach ($cols as $c) {
		$f = (string)($c['Field'] ?? '');
		if ($f !== '') $has[$f] = true;
	}
	if (isset($has['nom_employe'])) $nameCol = 'nom_employe';
	if (isset($has['nomEmploye'])) $nameCol = 'nomEmploye';
} catch (Throwable $e) {
	// ignore
}

// Profil entreprise
$denomination = '';
$adresseEntreprise = '';
$contactEntreprise = '';
try {
	$st = $bdd->query('SELECT denomination, adresse, phone, email FROM profil_entreprise LIMIT 1');
	$row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;
	$denomination = trim((string)($row['denomination'] ?? ''));
	$adresseEntreprise = trim((string)($row['adresse'] ?? ''));
	$phoneEntreprise = trim((string)($row['phone'] ?? ''));
	$emailEntreprise = trim((string)($row['email'] ?? ''));
	$contactEntreprise = trim($phoneEntreprise . ($phoneEntreprise !== '' && $emailEntreprise !== '' ? ' | ' : '') . $emailEntreprise);
} catch (Throwable $e) {
	$denomination = '';
	$adresseEntreprise = '';
	$contactEntreprise = '';
}

// Employé
$emp = null;
try {
	$st = $bdd->prepare('SELECT id_employe, `' . $nameCol . '` AS nom, poste, service, telephone, email, photo, status, date_embauche FROM employes WHERE id_employe = ? LIMIT 1');
	$st->execute([$idEmploye]);
	$emp = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$emp = null;
}

if (!$emp) {
	outputErrorPng('Employé introuvable');
}

$nom = trim((string)($emp['nom'] ?? ''));
$nomAffiche = toUpperUtf8($nom);
$poste = trim((string)($emp['poste'] ?? ''));

// Matricule: année = année d'embauche
$anneeMatricule = (int)date('Y');
$dateEmbauche = trim((string)($emp['date_embauche'] ?? ''));
if ($dateEmbauche !== '') {
	$ts = @strtotime($dateEmbauche);
	if ($ts !== false && $ts > 0) {
		$anneeMatricule = (int)date('Y', $ts);
	}
}
$matricule = 'E' . $anneeMatricule . 'C' . $idEmploye;

// Service: stocké en ID (organigramme) ou anciennement en libellé
$serviceRaw = trim((string)($emp['service'] ?? ''));
$service = '';
if ($serviceRaw !== '' && ctype_digit($serviceRaw)) {
	try {
		$stS = $bdd->prepare('SELECT celulle FROM organigramme WHERE id_organigramme = ? LIMIT 1');
		$stS->execute([(int)$serviceRaw]);
		$rowS = $stS->fetch(PDO::FETCH_ASSOC);
		$service = trim((string)($rowS['celulle'] ?? ''));
	} catch (Throwable $e) {
		$service = '';
	}
}
if ($service === '') {
	$service = trim(service($serviceRaw));
}
$telephone = trim((string)($emp['telephone'] ?? ''));
$telephone = formatContactPhone($telephone);
$email = trim((string)($emp['email'] ?? ''));
$photo = trim((string)($emp['photo'] ?? ''));
$status = (int)($emp['status'] ?? 0);
$statusLabel = ($status === 1) ? 'EN FONCTION' : 'INACTIF';

// Format badge européen: 85.60 x 53.98 mm
$W = 1000;
$H = 638;

$im = imagecreatetruecolor($W, $H);

$white = imagecolorallocate($im, 255, 255, 255);
$black = imagecolorallocate($im, 15, 15, 15);
$gray = imagecolorallocate($im, 120, 120, 120);
$blue = imagecolorallocate($im, 20, 80, 150);
$light = imagecolorallocate($im, 240, 245, 250);
$red = imagecolorallocate($im, 180, 30, 30);
$green = imagecolorallocate($im, 0, 140, 70);

imagefilledrectangle($im, 0, 0, $W - 1, $H - 1, $white);

// Bandeau haut
$bannerH = 165;
imagefilledrectangle($im, 0, 0, $W - 1, $bannerH, $blue);
imagefilledrectangle($im, 0, $bannerH, $W - 1, $bannerH + 1, $white);

// Zone contenu
imagefilledrectangle($im, 0, $bannerH + 2, $W - 1, $H - 1, $light);

// Filigrane logo
$logoPath = locateEntrepriseLogo();
if ($logoPath) {
	applyWatermarkLogo($im, $logoPath, $W, $H);
}

// Bordure
imagerectangle($im, 0, 0, $W - 1, $H - 1, $gray);

$debug = (string)($_GET['debug'] ?? '') === '1';
$fonts = findTextFonts();
$font = (string)($fonts['regular'] ?? '');
$fontBold = (string)($fonts['bold'] ?? '');
$hasTtf = ($font !== '' && is_file($font));

// Titre entreprise + adresse sous la dénomination
$title = toUpperUtf8($denomination !== '' ? $denomination : 'ENTREPRISE');
drawTextBold($im, $hasTtf, $font, $fontBold, 26, 28, 52, $white, $title);
if ($adresseEntreprise !== '') {
	drawTextBold($im, $hasTtf, $font, $fontBold, 18, 28, 92, $white, $adresseEntreprise);
}
if ($contactEntreprise !== '') {
	drawTextBold($im, $hasTtf, $font, $fontBold, 16, 28, 125, $white, $contactEntreprise);
}

// Statut (coin droit)
$stBg = ($status === 1) ? $green : $red;
$stBoxH = 40;
$stYTop = (int)round(($bannerH - $stBoxH) / 2);
$stYBottom = $stYTop + $stBoxH;
imagefilledrectangle($im, $W - 190, $stYTop, $W - 22, $stYBottom, $stBg);
$stTextY = $stYTop + (int)round(($stBoxH + 16) / 2);
drawTextBold($im, $hasTtf, $font, $fontBold, 16, $W - 180, $stTextY, $white, $statusLabel);

// Photo
$photoX = 40;
$photoY = 190;
$photoW = 320;
$photoH = 395;
imagefilledrectangle($im, $photoX, $photoY, $photoX + $photoW, $photoY + $photoH, $white);
imagerectangle($im, $photoX, $photoY, $photoX + $photoW, $photoY + $photoH, $gray);

if ($photo !== '') {
	$abs = realpath(__DIR__ . '/../' . $photo);
	if (!$abs) {
		$abs = realpath(__DIR__ . '/../' . ltrim($photo, '/'));
	}
	if ($abs && is_file($abs)) {
		$ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
		$src = null;
		if (in_array($ext, ['jpg', 'jpeg'], true)) {
			$src = @imagecreatefromjpeg($abs);
		} elseif ($ext === 'png') {
			$src = @imagecreatefrompng($abs);
		}
		if ($src) {
			$sw = imagesx($src);
			$sh = imagesy($src);
			$side = min($sw, $sh);
			$sx = (int)(($sw - $side) / 2);
			$sy = (int)(($sh - $side) / 2);
			imagecopyresampled($im, $src, $photoX + 8, $photoY + 8, $sx, $sy, $photoW - 16, $photoH - 16, $side, $side);
			imagedestroy($src);
		}
	}
}

// Infos employé (nom + matricule en dessous en gras)
$infoX = 395;
$y = 225;

drawTextBold($im, $hasTtf, $font, $fontBold, 26, $infoX, $y, $black, $nomAffiche !== '' ? $nomAffiche : $nom);
$y += 46; // espace entre nom et matricule
drawTextBold($im, $hasTtf, $font, $fontBold, 22, $infoX, $y, $black, $matricule);
$y += 52;

drawText($im, $hasTtf, $font,  20, $infoX, $y, $black, '' . ($poste !== '' ? $poste : '—'));
$y += 52;
drawText($im, $hasTtf, $font, 20, $infoX, $y, $black, '' . ($service !== '' ? $service : '—'));
$y += 52;
drawText($im, $hasTtf, $font, 20, $infoX, $y, $black, ''. ($telephone !== '' ? $telephone : '—'));
$y += 52;
drawText($im, $hasTtf, $font, 20, $infoX, $y, $black, '' . ($email !== '' ? $email : '—'));

$emailY = $y;

// Pied: code-barres basé sur l'ID employé
$barcodeValue = (string)$matricule;
$barcodeH = 70;
$encoded = code39Encode($barcodeValue);
// Allonger le code-barres (modules plus larges) tout en s'adaptant à la place disponible
$maxW = max(120, ($W - 28) - $infoX);
$candidates = [
	[3, 9, 3],
	[3, 8, 3],
	[3, 8, 2],
	[3, 7, 2],
	[2, 6, 2],
	[2, 5, 2],
];
$narrow = 2;
$wide = 5;
$gapPx = 2;
foreach ($candidates as $c) {
	$wTest = code39WidthPx($encoded, $c[0], $c[1], $c[2]);
	if ($wTest <= $maxW) {
		$narrow = $c[0];
		$wide = $c[1];
		$gapPx = $c[2];
		break;
	}
}
$barcodeW = code39WidthPx($encoded, $narrow, $wide, $gapPx);
$bx = (int)$infoX;
$by = (int)min($emailY + 38, $H - 110);
drawCode39($im, $bx, $by, $barcodeH, $barcodeValue, $narrow, $wide, $gapPx, $black);

drawText($im, $hasTtf, $font, 16, 28, $H - 18, $gray, 'BADGE ID N° : COEC' . $idEmploye);

if ($debug) {
	$dbgY = $H - 40;
	$dbg1 = 'DEBUG font_regular=' . ($font !== '' ? basename($font) : 'NONE') . ' | font_bold=' . ($fontBold !== '' ? basename($fontBold) : 'NONE') . ' | hasTtf=' . ($hasTtf ? '1' : '0');
	$dbg2 = 'Test accents: é è ê à ç ù ï ô É Ç — | Source: woff2 non supporté (GD)';
	drawText($im, true, ($font !== '' && is_file($font)) ? $font : (firstExistingFont(['/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf']) ?? $font), 12, 28, $dbgY, $gray, $dbg1);
	drawText($im, true, ($font !== '' && is_file($font)) ? $font : (firstExistingFont(['/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf']) ?? $font), 12, 28, $dbgY + 18, $gray, $dbg2);
}

header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
imagepng($im);
imagedestroy($im);
