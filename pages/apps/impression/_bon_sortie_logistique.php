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

$idDemande = appec_int($_GET['id_demande'] ?? 0);
if ($idDemande <= 0) {
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
];

$st = $bdd->prepare('SELECT * FROM log_demandes WHERE id_demande = ? LIMIT 1');
$st->execute([$idDemande]);
$d = $st->fetch(PDO::FETCH_ASSOC);
if (!$d) {
	die('Demande introuvable');
}

$demandeurId = (int)($d['id_user'] ?? 0);
$validateurId = (int)($d['id_validateur'] ?? 0);
$cliniqueId = (int)($d['id_responsable_clinique'] ?? 0);
$traitePar = (int)($d['traite_par'] ?? 0);

$allowed = (
	$userId === $demandeurId
	|| ($validateurId > 0 && $userId === $validateurId)
	|| ($cliniqueId > 0 && $userId === $cliniqueId)
	|| ($traitePar > 0 && $userId === $traitePar)
);
if (!$allowed) {
	die('Accès refusé');
}

$etatTraitement = (int)($d['etat_traitement'] ?? 0);
$statut = strtolower(trim((string)($d['statut'] ?? '')));
if (!($etatTraitement === 1 || $traitePar > 0 || $statut === 'traitee')) {
	die('Bon de sortie indisponible (demande non traitée)');
}

$demandeurPseudo = '';
$cliniquePseudo = '';
$cliniqueValidePseudo = '';
$traiteParPseudo = '';
try {
	$ids = array_values(array_unique(array_filter([
		$demandeurId,
		$cliniqueId,
		(int)($d['clinique_valide_par'] ?? 0),
		$traitePar,
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
		$cliniquePseudo = (string)($map[$cliniqueId] ?? '');
		$cliniqueValidePseudo = (string)($map[(int)($d['clinique_valide_par'] ?? 0)] ?? '');
		$traiteParPseudo = (string)($map[$traitePar] ?? '');
	}
} catch (Throwable $e) {
	// noop
}

$stL = $bdd->prepare(
	'SELECT dl.quantite, a.nom, a.unite '
	. 'FROM log_demandes_lignes dl '
	. 'JOIN log_articles a ON a.id_article = dl.id_article '
	. 'WHERE dl.id_demande = ? ORDER BY dl.id_ligne'
);
$stL->execute([$idDemande]);
$lines = $stL->fetchAll(PDO::FETCH_ASSOC);

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
$pdf->Cell(0, 8, pdf_text_compat('BON DE SORTIE N° ' . $idDemande), 0, 1, 'C');

$pdf->SetFont('CenturyGothic', '', 11);
$pdf->Ln(2);
$pdf->Cell(0, 6, pdf_text_compat('Demandeur : ' . ($demandeurPseudo !== '' ? $demandeurPseudo : ('#' . $demandeurId))), 0, 1);
$pdf->Cell(0, 6, pdf_text_compat('Responsable clinique : ' . ($cliniquePseudo !== '' ? $cliniquePseudo : ($cliniqueId > 0 ? ('#' . $cliniqueId) : '-'))), 0, 1);

$etatClin = (int)($d['etat_clinique'] ?? 0);
if ($etatClin === 1) {
	$whoId = (int)($d['clinique_valide_par'] ?? 0);
	$who = $cliniqueValidePseudo !== '' ? $cliniqueValidePseudo : ($whoId > 0 ? ('#' . $whoId) : ($cliniquePseudo !== '' ? $cliniquePseudo : '-'));
	$sig = trim((string)($d['signature_clinique'] ?? ''));
	$dt = appec_fmt_dt((string)($d['date_validation_clinique'] ?? ''));
	$pdf->Cell(0, 6, pdf_text_compat('Validé le ' . ($dt !== '' ? $dt : '-') . ' par ' . $who . ($sig !== '' ? (' | Signature : ' . $sig) : '')), 0, 1);
}

$who = $traiteParPseudo !== '' ? $traiteParPseudo : ($traitePar > 0 ? ('#' . $traitePar) : '-');
$sig = trim((string)($d['signature_traitement'] ?? ''));
$dt = appec_fmt_dt((string)($d['date_traitement'] ?? ''));
$pdf->Cell(0, 6, pdf_text_compat('Sortie le ' . ($dt !== '' ? $dt : '-') . ' par ' . $who . ($sig !== '' ? (' | Signature : ' . $sig) : '')), 0, 1);

$pdf->Ln(1);
$pdf->MultiCell(0, 6, pdf_text_compat('Objet : ' . (string)($d['description'] ?? '')), 0, 'L');
$pdf->Ln(2);

$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->SetFillColor(0, 102, 204);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(150, 9, pdf_text_compat('Article'), 1, 0, 'C', true);
$pdf->Cell(40, 9, pdf_text_compat('Quantité'), 1, 1, 'C', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(255, 255, 255);
$pdf->SetFont('CenturyGothic', '', 11);

foreach ($lines as $ln) {
	$nom = trim((string)($ln['nom'] ?? ''));
	$unite = trim((string)($ln['unite'] ?? ''));
	$label = $nom;
	if ($unite !== '') $label .= ' (' . $unite . ')';
	$qte = (int)($ln['quantite'] ?? 0);

	$pdf->Cell(150, 8, pdf_text_compat($label), 1, 0, 'L');
	$pdf->Cell(40, 8, pdf_text_compat((string)$qte), 1, 1, 'R');
}

$pdf->Ln(10);
$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->Cell(0, 6, pdf_text_compat('Signature et cachet'), 0, 1, 'R');
$pdf->Ln(12);
$pdf->SetFont('CenturyGothic', '', 10);
$pdf->Cell(0, 6, pdf_text_compat('Le responsable logistique'), 0, 1, 'R');

$pdf->Output('BON_SORTIE_LOGISTIQUE_' . $idDemande . '.pdf', 'I');
