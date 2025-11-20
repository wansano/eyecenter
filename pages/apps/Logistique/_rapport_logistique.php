<?php
session_start();
require_once('../public/connect.php');
require_once('../public/fonction.php');
require_once('../PDF/fpdf.php');
// Charger la police CenturyGothic comme sur les reçus de paiement si disponible
@require_once('../PDF/font/CenturyGothic.php');
require_once('fonctions_logistique.php');

$jours = isset($_GET['jours']) ? max(1, (int)$_GET['jours']) : 30;
$clinique = getSingleRow($bdd, 'profil_entreprise');
$devise = $clinique['devise'] ?? '';
$valeur = valeurStockActuel($bdd);
$rotation = rotationArticles($bdd, $jours);
$alertes = articlesSousSeuil($bdd);

class PDFRapportLogistique extends FPDF {
    function Header() {
        global $clinique;
        $this->SetMargins(10, 10);
        $this->SetAutoPageBreak(true, 12);
        // Chemin logo robuste (remonte à pages/img/ logo.jpg)
        $candidates = [
            __DIR__ . '/../../img/logo.jpg', // pages/apps/Logistique -> pages/img/
            __DIR__ . '/../img/logo.jpg',    // fallback pages/apps/img/
        ];
        $logoPath = null;
        foreach ($candidates as $c) { if (is_file($c)) { $logoPath = realpath($c); break; } }
        // Charger police CenturyGothic si disponible pour éviter erreurs dans genererEntete
        $this->tryRegisterFonts();
        // genererEntete attend le logo à un chemin relatif ../img/logo.jpg; on force si trouvé
        if ($logoPath) {
            // Temporisation : on copie éventuellement vers emplacement attendu si différent (optionnel)
            // On laisse genererEntete utiliser son chemin relatif; si besoin futur ajustement.
        }
        // Utiliser la fonction standard du projet pour uniformité
        genererEntete($this, $clinique, 12);
    }

    function tryRegisterFonts() {
        // Évite redéclarations
        if(!isset($this->fonts['CenturyGothic'])) {
            // Fichiers police attendus dans ../PDF/font/
            $fontDir = realpath(__DIR__ . '/../PDF/font');
            if($fontDir && is_file($fontDir.'/CenturyGothic.php') && is_file($fontDir.'/CenturyGothic_bold.php')) {
                $this->AddFont('CenturyGothic','', 'CenturyGothic.php');
                $this->AddFont('CenturyGothic','B','CenturyGothic_bold.php');
            }
        }
    }
    function getPrimaryFont() {
        return isset($this->fonts['CenturyGothic']) ? 'CenturyGothic' : 'Arial';
    }
}

try {
    $pdf = new PDFRapportLogistique('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->tryRegisterFonts();
    $pdf->SetFont('CenturyGothic','B',14);
    $pdf->Cell(0,8, pdf_text(strtoupper('Rapport Logistique')), 0, 1, 'L');
    $pdf->SetFont('CenturyGothic','',10);
    $pdf->Cell(0,6, pdf_text('Date: '.date('d/m/Y')), 0, 1, 'L');
    $pdf->Ln(2);

    // Bloc synthèse
    $pdf->SetFont('CenturyGothic','B',11);
    $pdf->Cell(0,7, pdf_text('Synthèse'), 0, 1, 'L');
    $pdf->SetFont('CenturyGothic','',10);
    $pdf->Cell(0,6, pdf_text('Valeur du stock: '.number_format($valeur, 2, ',', ' ').' '.$devise), 0, 1, 'L');
    $pdf->Cell(0,6, pdf_text('Articles sous seuil: '.count($alertes)), 0, 1, 'L');
    $pdf->Ln(3);

    // Bloc rotation
    $pdf->SetFont('CenturyGothic','B',11);
    $pdf->Cell(0,7, pdf_text('Rotation ('.$jours.' jours)'), 0, 1, 'L');
    $pdf->SetFont('CenturyGothic','B',9);
    $wNom = 110; $wMvt = 30; $wStock = 40;
    $pdf->Cell($wNom,7, pdf_text('Article'), 1, 0, 'L');
    $pdf->Cell($wMvt,7, pdf_text('Mouvements'), 1, 0, 'C');
    $pdf->Cell($wStock,7, pdf_text('Stock actuel'), 1, 1, 'C');
    $pdf->SetFont('CenturyGothic','',9);
    foreach($rotation as $row) {
        $pdf->Cell($wNom,6, pdf_text((string)$row['nom']), 1, 0, 'L');
        $pdf->Cell($wMvt,6, pdf_text((string)(int)$row['mouvements']), 1, 0, 'C');
        $pdf->Cell($wStock,6, pdf_text((string)(int)$row['stock_actuel']), 1, 1, 'C');
    }

    $pdf->Output('I', 'rapport_logistique_'.date('Ymd').'.pdf');
    exit;
} catch (Exception $e) {
    error_log('Erreur PDF Logistique: '.$e->getMessage());
    http_response_code(500);
    echo '<h3>Erreur génération PDF</h3><p>'.htmlspecialchars($e->getMessage()).'</p>';
}
