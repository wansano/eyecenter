<?php
session_start();
require_once('../public/connect.php');
require_once('../public/fonction.php');
require_once('../PDF/fpdf.php');
@require_once('../PDF/font/CenturyGothic.php');

function traitements_has_column(PDO $bdd, string $col): bool {
    try {
        $bdd->query('SELECT ' . $col . ' FROM traitements LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function status_cellule(PDO $bdd, int $idOrganigramme): int {
    $id = (int) $idOrganigramme;
    if ($id <= 0) return 1;

    try {
        $statusCol = null;
        try { $bdd->query('SELECT status FROM organigramme LIMIT 1'); $statusCol = 'status'; } catch (PDOException $e) {}
        if ($statusCol === null) {
            try { $bdd->query('SELECT statuts FROM organigramme LIMIT 1'); $statusCol = 'statuts'; } catch (PDOException $e) {}
        }
        if ($statusCol === null) return 1;

        $stmt = $bdd->prepare('SELECT ' . $statusCol . ' FROM organigramme WHERE id_organigramme = ? LIMIT 1');
        $stmt->execute([$id]);
        $val = $stmt->fetchColumn();
        return ($val === false) ? 1 : (int) $val;
    } catch (PDOException $e) {
        error_log('_liste_traitements.php status_cellule error: ' . $e->getMessage());
        return 1;
    }
}

class PDFListeTraitements extends FPDF {
    public function __construct($orientation='P',$unit='mm',$size='A4') {
        parent::__construct($orientation,$unit,$size);
        $this->tryFonts();
    }

    public function tryFonts(): void {
        if (!isset($this->fonts['CenturyGothic'])) {
            $fontDir = realpath(__DIR__ . '/../PDF/font');
            if ($fontDir && is_file($fontDir . '/CenturyGothic.php') && is_file($fontDir . '/CenturyGothic_bold.php')) {
                $this->AddFont('CenturyGothic', '', 'CenturyGothic.php');
                $this->AddFont('CenturyGothic', 'B', 'CenturyGothic_bold.php');
            }
        }
    }

    public function mainFont(): string {
        return 'CenturyGothic';
    }

    public function Header(): void {
        global $clinique;

        $this->SetMargins(10, 10);
        $this->SetAutoPageBreak(true, 12);
        genererEntete($this, $clinique, 12);
    }

    public function Footer(): void {
        $this->SetY(-12);
        $this->SetFont($this->mainFont(), '', 8);
        $this->Cell(0, 6, pdf_text('Page ' . $this->PageNo() . '/{nb}'), 0, 0, 'C');
    }
}

$clinique = getSingleRow($bdd, 'profil_entreprise');
$devise = (string)($clinique['devise'] ?? '');

$hasPrixAssurance = traitements_has_column($bdd, 'prix_assurance');
$hasIdOrg = traitements_has_column($bdd, 'id_organigramme');
$hasModel = traitements_has_column($bdd, 'model');

$cols = ['id_type', 'nom_type', 'montant', 'status'];
if ($hasPrixAssurance) { $cols[] = 'prix_assurance'; }
if ($hasIdOrg) { $cols[] = 'id_organigramme'; }
if ($hasModel) { $cols[] = 'model'; }

$sql = 'SELECT ' . implode(',', $cols) . ' FROM traitements ORDER BY id_type';
$stmt = $bdd->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pdf = new PDFListeTraitements('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();

$pdf->SetFont($pdf->mainFont(), 'B', 14);
$pdf->Cell(0, 10, pdf_text('LISTE DES TRAITEMENTS'), 0, 1, 'C');
$pdf->SetFont($pdf->mainFont(), '', 10);
$pdf->Cell(0, 6, pdf_text('Généré le : ' . date('d/m/Y H:i')), 0, 1, 'L');
$pdf->Ln(3);

// En-tête tableau
$wId = 18;
$wTraitement = 92;
$wStd = 38;
$wAss = 38;

$pdf->SetFont($pdf->mainFont(), 'B', 10);
$pdf->SetFillColor(235, 235, 235);
$pdf->Cell($wId, 7, pdf_text('ID'), 1, 0, 'C', true);
$pdf->Cell($wTraitement, 7, pdf_text('TRAITEMENT'), 1, 0, 'L', true);
$pdf->Cell($wStd, 7, pdf_text('PRIX NON ASSURÉ'), 1, 0, 'R', true);
$pdf->Cell($wAss, 7, pdf_text('PRIX ASSURÉ'), 1, 1, 'R', true);

$pdf->SetFont($pdf->mainFont(), '', 10);

foreach ($rows as $row) {
    $status = (int)($row['status'] ?? 0);
    if ($status === 3) continue;

    $idCell = 0;
    if ($hasIdOrg && isset($row['id_organigramme'])) {
        $idCell = (int)$row['id_organigramme'];
    } elseif ($hasModel && isset($row['model'])) {
        $idCell = (int)$row['model'];
    }
    if (status_cellule($bdd, $idCell) !== 1) continue;

    $idType = (string)($row['id_type'] ?? '');
    $nomType = (string)($row['nom_type'] ?? '');
    $montant = (float)($row['montant'] ?? 0);
    $prixAssurance = $hasPrixAssurance ? (float)($row['prix_assurance'] ?? 0) : 0.0;

    $montantTxt = ($montant > 0) ? (number_format($montant) . ' ' . $devise) : '';
    $prixAssuranceTxt = ($prixAssurance > 0) ? (number_format($prixAssurance) . ' ' . $devise) : '';

    $pdf->Cell($wId, 7, pdf_text($idType), 1, 0, 'C');
    $pdf->Cell($wTraitement, 7, pdf_text($nomType), 1, 0, 'L');
    $pdf->Cell($wStd, 7, pdf_text($montantTxt), 1, 0, 'R');
    $pdf->Cell($wAss, 7, pdf_text($prixAssuranceTxt), 1, 1, 'R');
}

$pdf->Output('I', 'liste-traitements.pdf');
