<?php
require_once('../PDF/fpdf.php');
require_once('../PDF/html_table13.php');
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');

session_start();

if (!isset($_SESSION['auth'])) {
    http_response_code(401);
    die('Accès non autorisé.');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    die('ID manquant.');
}

// Récupération entreprise
$profil = $bdd->prepare('SELECT * FROM profil_entreprise LIMIT 1');
$profil->execute();
$entreprise = $profil->fetch(PDO::FETCH_ASSOC);
if (!$entreprise) {
    http_response_code(500);
    die('Profil entreprise introuvable.');
}

// Récupération du rapport
$stmt = $bdd->prepare('SELECT * FROM preuvedecaisse WHERE id_preuve = ? LIMIT 1');
$stmt->execute([$id]);
$rapport = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$rapport) {
    http_response_code(404);
    die('Rapport introuvable.');
}

// Sécurité: uniquement le propriétaire (comme la page "mes rapports")
if ((int)$rapport['id_user'] !== (int)$_SESSION['auth']) {
    http_response_code(403);
    die('Accès refusé.');
}

// Infos utilisateur (si table users)
$userLabel = '';
if (function_exists('getUserInfo')) {
    $u = getUserInfo($bdd, (int)$rapport['id_user']);
    if (is_array($u)) {
        $userLabel = trim((string)($u['nom'] ?? $u['name'] ?? $u['username'] ?? ''));
    }
}

$dateRapport = (string)($rapport['date_rapportement'] ?? '');
$compteId = (int)($rapport['compte'] ?? 0);

// Affichage sans heure + normalisation pour les requêtes
$dateRapportKey = $dateRapport;
$dateRapportDisplay = $dateRapport;
if ($dateRapport !== '') {
    try {
        $dt = new DateTime($dateRapport);
        $dateRapportKey = $dt->format('Y-m-d');
        $dateRapportDisplay = $dt->format('d/m/Y');
    } catch (Exception $e) {
        // Fallback: si format "YYYY-mm-dd HH:ii:ss", enlever l'heure
        $dateRapportKey = trim((string)preg_replace('/\s+\d{1,2}:\d{2}(:\d{2})?\s*$/', '', $dateRapport));
        $dateRapportDisplay = $dateRapportKey;
    }
}

$entreePaiements = 0;
$entreePreuve = 0;
if ($dateRapportKey !== '' && $compteId > 0) {
    $entreePaiements = (float)getEntreePaiements($compteId, $dateRapportKey, $dateRapportKey, $bdd);
    $entreePreuve = (float)getEntreePreuve($compteId, $dateRapportKey, $dateRapportKey, $bdd);
}
$conforme = ($entreePaiements == $entreePreuve);

function fmt_money_gnf($value): string {
    return number_format((float)$value, 0, ',', ' ');
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->AddFont('CenturyGothic', '', 'CenturyGothic.php');
$pdf->AddFont('CenturyGothic', 'B', 'CenturyGothic_bold.php');
$pdf->SetAutoPageBreak(true, 12);

// Entête entreprise
$pdf->SetFont('CenturyGothic', '', 11);
genererEntete($pdf, $entreprise);

// Titre
$pdf->SetFont('CenturyGothic', 'B', 16);
$pdf->Cell(0, 10, pdf_text('RAPPORT DE CAISSE'), 0, 1, 'C');
$pdf->Ln(2);

$pdf->SetFont('CenturyGothic', '', 11);
$pdf->Cell(0, 6, pdf_text('Référence : PR-C' . $id), 0, 1, 'L');
$pdf->Cell(0, 6, pdf_text('Date du rapport : ' . $dateRapportDisplay), 0, 1, 'L');
$pdf->Cell(0, 6, pdf_text('Compte : ' . ($compteId > 0 ? type_paiement($compteId) : '')), 0, 1, 'L');
if ($userLabel !== '') {
    $pdf->Cell(0, 6, pdf_text('Utilisateur : ' . $userLabel), 0, 1, 'L');
}

$pdf->Ln(3);

// Caissier
$caissierName = '';
if (function_exists('traitant')) {
    $caissierName = trim((string)traitant((int)$rapport['id_user']));
}
if ($caissierName === '') {
    $caissierName = $userLabel;
}
if ($caissierName !== '') {
    $pdf->SetFont('CenturyGothic', '', 11);
    $pdf->Cell(0, 6, pdf_text('Caissier : ' . $caissierName), 0, 1, 'L');
    $pdf->Ln(1);
}

// Montant
$pdf->SetFont('CenturyGothic', '', 12);
$pdf->Cell(0, 7, pdf_text('Montant total : ' . fmt_money_gnf($rapport['montant'] ?? 0) . ' ' . ($GLOBALS['devise'] ?? '')), 0, 1, 'L');
$pdf->SetFont('CenturyGothic', '', 11);
$montantLettre = trim((string)($rapport['montant_lettre'] ?? ''));
if ($montantLettre !== '') {
    $pdf->MultiCell(0, 6, pdf_text('En lettres : ' . $montantLettre), 0, 'L');
}

$pdf->Ln(4);

// Détail billets
$pdf->SetFont('CenturyGothic', 'B', 12);
$pdf->Cell(0, 7, pdf_text('Détail des billets'), 0, 1, 'L');

$pdf->SetFont('CenturyGothic', 'B', 10);
// Charte couleur (comme le rapport d'interrogation)
$pdf->SetFillColor(0, 102, 204);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(45, 7, pdf_text('Billet'), 1, 0, 'C', true);
$pdf->Cell(35, 7, pdf_text('Quantité'), 1, 0, 'C', true);
$pdf->Cell(55, 7, pdf_text('Sous-total'), 1, 1, 'C', true);

// Retour au style normal
$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(0, 0, 0);

$pdf->SetFont('CenturyGothic', '', 10);
$billets = [
    1000 => (int)($rapport['b1'] ?? 0),
    2000 => (int)($rapport['b2'] ?? 0),
    5000 => (int)($rapport['b5'] ?? 0),
    10000 => (int)($rapport['b10'] ?? 0),
    20000 => (int)($rapport['b20'] ?? 0),
];

$calcTotal = 0.0;
$totalBilletsQty = 0;
$hasAnyBillet = false;
foreach ($billets as $valeur => $qty) {
    if ($qty <= 0) {
        continue;
    }

    $hasAnyBillet = true;
    $totalBilletsQty += $qty;
    $lineTotal = $qty * $valeur;
    $calcTotal += $lineTotal;

    $pdf->Cell(45, 7, pdf_text(fmt_money_gnf($valeur) . ' ' . ($devise ?? 'GNF')), 1, 0, 'L');
    $pdf->Cell(35, 7, pdf_text((string)$qty), 1, 0, 'C');
    $pdf->Cell(55, 7, pdf_text(fmt_money_gnf($lineTotal) . ' ' . ($devise ?? 'GNF')), 1, 1, 'R');
}

if (!$hasAnyBillet) {
    $pdf->Cell(135, 7, pdf_text('Aucun billet saisi'), 1, 1, 'C');
}

$pdf->SetFont('CenturyGothic', 'B', 10);
$pdf->Cell(45, 7, pdf_text('Total billets'), 1, 0, 'R');
$pdf->Cell(35, 7, pdf_text((string)$totalBilletsQty), 1, 0, 'C');
$pdf->Cell(55, 7, pdf_text(fmt_money_gnf($calcTotal) . ' GNF'), 1, 1, 'R');

$pdf->Ln(5);

// Conformité
$pdf->SetFont('CenturyGothic', 'B', 12);
$pdf->Cell(0, 7, pdf_text('Conformité'), 0, 1, 'L');
$pdf->SetFont('CenturyGothic', '', 11);
$pdf->Cell(0, 6, pdf_text('Entrées des paiements : ' . fmt_money_gnf($entreePaiements) . ' GNF'), 0, 1, 'L');
$pdf->Cell(0, 6, pdf_text('Entrées preuves saisies par l\'agent : ' . fmt_money_gnf($entreePreuve) . ' GNF'), 0, 1, 'L');
$pdf->Cell(0, 6, pdf_text('Statut : ' . ($conforme ? 'Conforme' : 'Non conforme')), 0, 1, 'L');

$pdf->Ln(12);
$pdf->Cell(0, 6, pdf_text('Signature'), 0, 1, 'R');

$pdf->Ln(4);
$pdf->SetFont('CenturyGothic', '', 8);
$pdf->Cell(0, 6, pdf_text('Imprimé le ' . date('d/m/Y') . ' par ' . traitant($_SESSION['auth'])), 0, 1, 'R');

$filename = 'RAPPORT_CAISSE_' . $id . '.pdf';
$pdf->Output($filename, 'I');
