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

// Profil entreprise (pour l'entête)
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

$allowed = (
	$userId === $demandeurId
	|| ($validateurId > 0 && $userId === $validateurId)
	|| ($cliniqueId > 0 && $userId === $cliniqueId)
	|| ($comptaPayePar > 0 && $userId === $comptaPayePar)
);
if (!$allowed) {
	die('Accès refusé');
}

// Infos users
$demandeurPseudo = '';
$validateurPseudo = '';
$cliniquePseudo = '';
$cliniqueValidePseudo = '';
$comptaPseudo = '';
$demandeurServiceId = 0;
try {
	$ids = array_values(array_unique(array_filter([
		$demandeurId,
		$validateurId,
		$cliniqueId,
		(int)($d['clinique_valide_par'] ?? 0),
		$comptaPayePar,
	], fn($x) => (int)$x > 0)));
	if (!empty($ids)) {
		$ph = implode(',', array_fill(0, count($ids), '?'));
		$stU = $bdd->prepare('SELECT id, pseudo, id_service FROM users WHERE id IN (' . $ph . ')');
		$stU->execute($ids);
		$map = [];
		foreach ($stU->fetchAll(PDO::FETCH_ASSOC) as $u) {
			$map[(int)$u['id']] = (string)($u['pseudo'] ?? '');
			if ((int)$u['id'] === $demandeurId) {
				$demandeurServiceId = (int)($u['id_service'] ?? 0);
			}
		}
		$demandeurPseudo = (string)($map[$demandeurId] ?? '');
		$validateurPseudo = (string)($map[$validateurId] ?? '');
		$cliniquePseudo = (string)($map[$cliniqueId] ?? '');
		$cliniqueValidePseudo = (string)($map[(int)($d['clinique_valide_par'] ?? 0)] ?? '');
		$comptaPseudo = (string)($map[$comptaPayePar] ?? '');
	}
} catch (Throwable $e) {
	// noop
}

// Lignes
$stL = $bdd->prepare('SELECT designation, quantite, prix_unitaire, montant_ligne FROM depenses_lignes WHERE id_depense=? ORDER BY id_ligne');
$stL->execute([$idDepense]);
$lines = $stL->fetchAll(PDO::FETCH_ASSOC);

$total = 0.0;
foreach ($lines as $ln) {
	$total += (float)($ln['montant_ligne'] ?? 0);
}
$devise = !empty($dataProfil['devise']) ? trim((string)$dataProfil['devise']) : 'GNF';

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

$pdf->Ln(6);
$pdf->SetFont('CenturyGothic', 'B', 14);
$pdf->Cell(0, 8, pdf_text_compat('DEMANDE DE DEPENSE N° ' . $idDepense), 0, 1, 'C');

$pdf->SetFont('CenturyGothic', '', 11);
$pdf->Ln(2);
$serviceDemandeur = ($demandeurServiceId > 0 && function_exists('service')) ? trim((string)service($demandeurServiceId)) : '';
$demandeurLine = 'Demandeur : ' . ($demandeurPseudo !== '' ? $demandeurPseudo : ('#' . $demandeurId));
if ($serviceDemandeur !== '') {
	$demandeurLine .= ' | Service : ' . $serviceDemandeur;
}
$pdf->Cell(0, 6, pdf_text_compat($demandeurLine), 0, 1);

// Mentions workflow (Validé / Approuvé / Payé)
$etatClin = (int)($d['etat_clinique'] ?? 0);
$etatCompta = (int)($d['etat_compta'] ?? 0);
$status = (int)($d['status'] ?? 0);
if ($etatClin === 1) {
	$who = $cliniqueValidePseudo !== '' ? $cliniqueValidePseudo : ($cliniquePseudo !== '' ? $cliniquePseudo : ($cliniqueId > 0 ? ('#' . $cliniqueId) : '-'));
	$sig = trim((string)($d['signature_clinique'] ?? ''));
	$dt = appec_fmt_dt((string)($d['date_validation_clinique'] ?? ''));
	$pdf->Cell(0, 6, pdf_text_compat('Validé le ' . ($dt !== '' ? $dt : '-') . ' par ' . $who . ($sig !== '' ? (' | Signature : ' . $sig) : '')), 0, 1);
}
if ($status === 4 || $etatCompta === 1) {
	$who = $comptaPseudo !== '' ? $comptaPseudo : ($comptaPayePar > 0 ? ('#' . $comptaPayePar) : '-');
	$sig = trim((string)($d['signature_compta'] ?? ''));
	$dt = appec_fmt_dt((string)($d['date_paiement'] ?? ''));
	$pdf->Cell(0, 6, pdf_text_compat('Payé le ' . ($dt !== '' ? $dt : '-') . ' par ' . $who . ($sig !== '' ? (' | Signature : ' . $sig) : '')), 0, 1);
}

$sigRec = trim((string)($d['signature_reception'] ?? ''));
$dtRec = appec_fmt_dt((string)($d['date_reception'] ?? ''));
if ($sigRec !== '' || $dtRec !== '') {
	$pdf->Cell(0, 6, pdf_text_compat('Réception le ' . ($dtRec !== '' ? $dtRec : '-') . ($sigRec !== '' ? (' | Signature : ' . $sigRec) : '')), 0, 1);
}

$pdf->Ln(1);
$pdf->MultiCell(0, 6, pdf_text_compat('Objet : ' . (string)($d['description'] ?? '')), 0, 'L');
$pdf->Ln(2);

// Tableau
$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->SetFillColor(0, 102, 204);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(95, 9, pdf_text_compat('Désignation'), 1, 0, 'C', true);
$pdf->Cell(20, 9, pdf_text_compat('Qte'), 1, 0, 'C', true);
$pdf->Cell(35, 9, pdf_text_compat('PU'), 1, 0, 'C', true);
$pdf->Cell(40, 9, pdf_text_compat('Montant'), 1, 1, 'C', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(255, 255, 255);
$pdf->SetFont('CenturyGothic', '', 11);

foreach ($lines as $ln) {
	$designation = (string)($ln['designation'] ?? '');
	$qte = (int)($ln['quantite'] ?? 0);
	$pu = (float)($ln['prix_unitaire'] ?? 0);
	$montant = (float)($ln['montant_ligne'] ?? 0);

	$pdf->Cell(95, 8, pdf_text_compat($designation), 1, 0, 'L');
	$pdf->Cell(20, 8, pdf_text_compat((string)$qte), 1, 0, 'C');
	$pdf->Cell(35, 8, pdf_text_compat(number_format($pu, 0, ',', ' ') . ' ' . $devise), 1, 0, 'R');
	$pdf->Cell(40, 8, pdf_text_compat(number_format($montant, 0, ',', ' ') . ' ' . $devise), 1, 1, 'R');
}

$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->Cell(150, 9, pdf_text_compat('Total'), 1, 0, 'R');
$pdf->Cell(40, 9, pdf_text_compat(number_format($total, 0, ',', ' ') . ' ' . $devise), 1, 1, 'R');

$pdf->Ln(10);
$pdf->SetFont('CenturyGothic', 'B', 11);
$demandeurNomSig = ($demandeurPseudo !== '' ? $demandeurPseudo : ('#' . $demandeurId));
$validateurNomSig = ($validateurPseudo !== '' ? $validateurPseudo : ($validateurId > 0 ? ('#' . $validateurId) : '-'));
$pdf->Cell(95, 6, pdf_text_compat('Signature du demandeur'), 0, 0, 'L');
$pdf->Cell(95, 6, pdf_text_compat('Signature du validateur'), 0, 1, 'R');
$pdf->Ln(24);
$pdf->SetFont('CenturyGothic', '', 10);
$pdf->Cell(95, 6, pdf_text_compat($demandeurNomSig), 0, 0, 'L');
$pdf->Cell(95, 6, pdf_text_compat($validateurNomSig), 0, 1, 'R');
$pdf->Output('DEMANDE_DEPENSE_' . $idDepense . '.pdf', 'I');
