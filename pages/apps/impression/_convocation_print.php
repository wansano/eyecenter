<?php
// Génération PDF des rendez-vous
require_once('../PDF/fpdf.php');
require_once('../PDF/font/CenturyGothic.php');
require_once('../PDF/html_table13.php');
require_once('../public/connect.php');
require_once('../public/fonction.php');
session_start();

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$medecinId = isset($_GET['medecin']) ? (int)$_GET['medecin'] : 0;

try {
    if (!$medecinId) {
        throw new Exception('Médecin non spécifié');
    }

    // Récupération des RDV
    $sql = "SELECT id_patient, prochain_rdv, motif FROM dmd_rendez_vous
            WHERE DATE(prochain_rdv) = ? AND traitant = ? AND status IN (0,1,2)
            ORDER BY prochain_rdv";
    $st = $bdd->prepare($sql);
    $st->execute([$date, $medecinId]);
    $rdvs = $st->fetchAll(PDO::FETCH_ASSOC);

    // Initialisation PDF
    $pdf = new PDF('P','mm','A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->AddFont('CenturyGothic', '', 'CenturyGothic.php');
    $pdf->AddFont('CenturyGothic','B','CenturyGothic_bold.php');
    $pdf->SetAutoPageBreak(true, -15);
    $pdf->SetFont('CenturyGothic','',11);

    // Entête entreprise
    $profil = $bdd->prepare('SELECT * FROM profil_entreprise LIMIT 1');
    $profil->execute();
    if ($dataProfil = $profil->fetch(PDO::FETCH_ASSOC)) {
        genererEntete($pdf, $dataProfil, 12);
    }

    // Titre principal
    $pdf->SetFont('CenturyGothic','B',13);
    $titreDate = dateEnFrancais($date);
    $pdf->Cell(0,8,pdf_text('LISTE DES RENDEZ-VOUS DU '.strtoupper($titreDate)),0,1,'C');
    $pdf->Ln(2);
    $pdf->SetFont('CenturyGothic','',11);
    $pdf->Cell(0,6,pdf_text('Médecin : Dr '.traitant($medecinId)),0,1,'L');
    $pdf->Cell(0,6,pdf_text('Total rendez-vous : '.count($rdvs)),0,1,'L');
    $pdf->Ln(4);

    // Tableau des rendez-vous
    if (!$rdvs) {
        $pdf->SetFont('CenturyGothic','B',11);
        $pdf->Cell(0,8,utf8_decode('Aucun rendez-vous pour cette date.'),0,1,'C');
    } else {
        // En-têtes
        $pdf->SetFont('CenturyGothic','B',11);
        $pdf->Cell(30,8,pdf_text('Heure'),1,0,'C');
        $pdf->Cell(70,8,pdf_text('Patient'),1,0,'C');
        $pdf->Cell(40,8,pdf_text('Contact'),1,0,'C');
        $pdf->Cell(50,8,pdf_text('Motif'),1,1,'C');
        $pdf->SetFont('CenturyGothic','',10);

        foreach ($rdvs as $r) {
            $heure = substr($r['prochain_rdv'],11,5);
            $nom   = nom_patient($r['id_patient']);
            $tel   = return_phone($r['id_patient']);
            $motif = type_traitement($r['motif']);

            // Gestion du retour à la ligne pour les longues valeurs
            $pdf->Cell(30,7,pdf_text($heure),1,0,'C');
            $pdf->Cell(70,7,pdf_text(mb_strimwidth($nom,0,40,'…','UTF-8')),1,0,'L');
            $pdf->Cell(40,7,pdf_text(mb_strimwidth($tel,0,20,'','UTF-8')),1,0,'L');
            $pdf->Cell(50,7,pdf_text(mb_strimwidth($motif,0,30,'…','UTF-8')),1,1,'L');
        }
    }

    // Pied de page simple
    $pdf->Ln(10);
    $pdf->SetFont('CenturyGothic','',8);
    $pdf->Cell(0,5,pdf_text('Généré le '.date('d/m/Y H:i').' par '.(isset($_SESSION['auth'])?traitant($_SESSION['auth']):'Système')),0,1,'R');

    $pdf->Output('I','rdv_'.$date.'_medecin_'.$medecinId.'.pdf');
    exit;

} catch (Exception $e) {
    error_log('Erreur convocation_print: '.$e->getMessage());
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Erreur: '.$e->getMessage();
    exit;
}
?>
