<?php
session_start();

require_once(__DIR__ . '/../PDF/fpdf.php');
require_once(__DIR__ . '/../PDF/html_table13.php');
require_once(__DIR__ . '/../public/connect.php');
require_once(__DIR__ . '/../public/fonction.php');

function appec_int($v): int {
	return (int)(is_numeric($v) ? $v : 0);
}

function appec_fmt_dt(string $dt): string {
	$dt = trim($dt);
	if ($dt === '') return '';
	try {
		return (new DateTime($dt))->format('d/m/Y H:i');
	} catch (Throwable $e) {
		return $dt;
	}
}

$userId = (int)($_SESSION['auth'] ?? 0);
if ($userId <= 0) {
	die('Accès refusé');
}

$idDepense = appec_int($_GET['id_depense'] ?? 0);
if ($idDepense <= 0) {
	die('Paramètres invalides');
}

$profil = $bdd->prepare('SELECT * FROM profil_entreprise LIMIT 1');
$profil->execute();
$dataProfil = $profil->fetch(PDO::FETCH_ASSOC) ?: [
	'denomination' => '',
	'adresse' => '',
	'phone' => '',
	'email' => '',
	'arrete' => '',
	'exploitation' => '',
	'devise' => 'GNF',
];
$devise = !empty($dataProfil['devise']) ? trim((string)$dataProfil['devise']) : 'GNF';

$st = $bdd->prepare('SELECT * FROM depenses WHERE id_depense = ? LIMIT 1');
$st->execute([$idDepense]);
$d = $st->fetch(PDO::FETCH_ASSOC);
if (!$d) {
	die('Demande introuvable');
}

$demandeurId = (int)($d['id'] ?? 0);
$validateurId = (int)($d['validateur'] ?? 0);
$cliniqueId = (int)($d['id_responsable_clinique'] ?? 0);
$comptaPayePar = (int)($d['compta_paye_par'] ?? 0);
$cliniqueValidePar = (int)($d['clinique_valide_par'] ?? 0);

$isPaid = ((int)($d['status'] ?? 0) === 4) || ((int)($d['etat_compta'] ?? 0) === 1);
if (!$isPaid) {
	die('Bon de paiement indisponible (non payée)');
}

$allowed = (
	$userId === $demandeurId
	|| ($validateurId > 0 && $userId === $validateurId)
	|| ($cliniqueId > 0 && $userId === $cliniqueId)
	|| ($comptaPayePar > 0 && $userId === $comptaPayePar)
);
if (!$allowed) {
	die('Accès refusé');
}

$demandeurPseudo = '';
$comptaPseudo = '';
$cliniquePseudo = '';
try {
	$ids = array_values(array_unique(array_filter([
		$demandeurId,
		$comptaPayePar,
		$cliniqueValidePar,
	], fn($x) => (int)$x > 0)));
	if (!empty($ids)) {
		$ph = implode(',', array_fill(0, count($ids), '?'));
		$stU = $bdd->prepare('SELECT id, pseudo FROM users WHERE id IN (' . $ph . ')');
		$stU->execute($ids);
		$map = [];
		foreach ($stU->fetchAll(PDO::FETCH_ASSOC) as $u) {
			$map[(int)$u['id']] = (string)($u['pseudo'] ?? '');
		}
		$demandeurPseudo = (string)($map[$demandeurId] ?? '');
		$comptaPseudo = (string)($map[$comptaPayePar] ?? '');
		$cliniquePseudo = (string)($map[$cliniqueValidePar] ?? '');
	}
} catch (Throwable $e) {
	// noop
}

$montant = (float)($d['montant'] ?? 0);
$datePaiement = (string)($d['date_paiement'] ?? '');
$reference = (string)($d['reference_paiement'] ?? '');
$signatureCompta = trim((string)($d['signature_compta'] ?? ''));
$signatureClinique = trim((string)($d['signature_clinique'] ?? ''));
$dtValidationClin = appec_fmt_dt((string)($d['date_validation_clinique'] ?? ''));
$dtPaiementFmt = appec_fmt_dt($datePaiement);

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
$pdf->Cell(0, 8, pdf_text_compat('BON DE PAIEMENT N° ' . $idDepense), 0, 1, 'C');

$pdf->SetFont('CenturyGothic', '', 11);
$pdf->Ln(2);
$pdf->Cell(0, 6, pdf_text_compat('Demandeur : ' . ($demandeurPseudo !== '' ? $demandeurPseudo : ('#' . $demandeurId))), 0, 1);
$pdf->Cell(0, 6, pdf_text_compat('Date paiement : ' . ($dtPaiementFmt !== '' ? $dtPaiementFmt : ($datePaiement !== '' ? $datePaiement : date('Y-m-d H:i:s')))), 0, 1);
if ((int)($d['etat_clinique'] ?? 0) === 1 && $cliniqueValidePar > 0) {
	$who = $cliniquePseudo !== '' ? $cliniquePseudo : ('#' . $cliniqueValidePar);
	$pdf->Cell(0, 6, pdf_text_compat('Validé le ' . ($dtValidationClin !== '' ? $dtValidationClin : '-') . ' par ' . $who . ($signatureClinique !== '' ? (' | Signature : ' . $signatureClinique) : '')), 0, 1);
}
$pdf->Ln(2);

$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->SetFillColor(0, 102, 204);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(70, 10, pdf_text_compat('Libellé'), 1, 0, 'C', true);
$pdf->Cell(120, 10, pdf_text_compat('Valeur'), 1, 1, 'C', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(255, 255, 255);
$pdf->SetFont('CenturyGothic', '', 11);

$pdf->Cell(70, 8, pdf_text_compat('Référence demande'), 1, 0, 'L');
$pdf->Cell(120, 8, pdf_text_compat('#' . $idDepense), 1, 1, 'L');

$pdf->Cell(70, 8, pdf_text_compat('Montant'), 1, 0, 'L');
$pdf->Cell(120, 8, pdf_text_compat(number_format($montant, 0, ',', ' ') . ' ' . $devise), 1, 1, 'R');

$pdf->Cell(70, 8, pdf_text_compat('Référence paiement'), 1, 0, 'L');
$pdf->Cell(120, 8, pdf_text_compat($reference !== '' ? $reference : '-'), 1, 1, 'L');

$pdf->Cell(70, 8, pdf_text_compat('Payé par'), 1, 0, 'L');
$pdf->Cell(120, 8, pdf_text_compat($comptaPseudo !== '' ? $comptaPseudo : ($comptaPayePar > 0 ? ('#' . $comptaPayePar) : '-')), 1, 1, 'L');

if ($signatureCompta !== '') {
	$pdf->Cell(70, 8, pdf_text_compat('Signature compta'), 1, 0, 'L');
	$pdf->Cell(120, 8, pdf_text_compat($signatureCompta), 1, 1, 'L');
}

$pdf->Ln(10);
$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->Cell(0, 6, pdf_text_compat('Signature et cachet'), 0, 1, 'R');
$pdf->Ln(10);
$pdf->SetFont('CenturyGothic', '', 10);
$pdf->Cell(0, 6, pdf_text_compat('Le comptable'), 0, 1, 'R');

$pdf->Output('BON_PAIEMENT_DEPENSE_' . $idDepense . '.pdf', 'I');
