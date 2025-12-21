<?php
// Génération PDF des rendez-vous
require_once('../PDF/fpdf.php');
require_once('../PDF/font/CenturyGothic.php');
require_once('../PDF/html_table13.php');
require_once('../public/connect.php');
require_once('../public/fonction.php');
session_start();

function formatPhoneDisplayConvocation($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') {
        return '';
    }

    // Si plusieurs numéros sont fournis, prendre le premier
    $parts = preg_split('/[;,\|\/]+/', $raw);
    if (is_array($parts) && isset($parts[0])) {
        $raw = trim((string)$parts[0]);
    }

    $digits = preg_replace('/\D+/', '', $raw);
    $digits = (string)$digits;
    if ($digits === '') {
        return '';
    }

    // Retirer indicatifs courants (ex: +224 / 00224 / 224)
    if (substr($digits, 0, 5) === '00224') {
        $digits = substr($digits, 5);
    } elseif (substr($digits, 0, 3) === '224' && strlen($digits) > 9) {
        $digits = substr($digits, 3);
    }

    // Format attendu: 9 chiffres => 3-2-2-2 (ex: 620 00 00 00)
    if (strlen($digits) > 9) {
        $digits = substr($digits, -9);
    }

    if (strlen($digits) === 9) {
        return substr($digits, 0, 3) . ' ' . substr($digits, 3, 2) . ' ' . substr($digits, 5, 2) . ' ' . substr($digits, 7, 2);
    }

    // Fallback: grouper par 2
    return trim(chunk_split($digits, 2, ' '));
}

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
        $pdf->Cell(20,8,pdf_text('Heure'),1,0,'C');
        $pdf->Cell(20,8,pdf_text('PAT-N°'),1,0,'C');
        $pdf->Cell(70,8,pdf_text('Patient'),1,0,'C');
        $pdf->Cell(30,8,pdf_text('Contact'),1,0,'C');
        $pdf->Cell(50,8,pdf_text('Motif'),1,1,'C');
        $pdf->SetFont('CenturyGothic','',10);

        foreach ($rdvs as $r) {
            $heure   = substr($r['prochain_rdv'],11,5);
            $dossier = $r['id_patient'];
            $nom     = nom_patient($r['id_patient']);
            $tel     = formatPhoneDisplayConvocation(return_phone($r['id_patient']));
            $motif   = type_traitement($r['motif']);

            // Respecte les largeurs d'en-tête: 20 | 30 | 70 | 40 | 50
            $pdf->Cell(20,7,pdf_text($heure),1,0,'C');
            $pdf->Cell(20,7,pdf_text($dossier),1,0,'C');
            $pdf->Cell(70,7,pdf_text(mb_strimwidth($nom,0,40,'…','UTF-8')),1,0,'L');
            $pdf->Cell(30,7,pdf_text(mb_strimwidth($tel,0,20,'','UTF-8')),1,0,'C');
            $pdf->Cell(50,7,pdf_text(mb_strimwidth($motif,0,30,'…','UTF-8')),1,1,'L');
        }
    }

    // Pied de page simple
    $pdf->Ln(10);
    $pdf->SetFont('CenturyGothic','',8);
    $pdf->Cell(0,5,pdf_text('Généré le '.date('d/m/Y H:i').' par '.(isset($_SESSION['auth'])?traitant($_SESSION['auth']):'Système')),0,1,'R');

    $pdf->Output('I','RdV_'.$date.'_'.traitant($medecinId).'.pdf');
    exit;

} catch (Exception $e) {
    error_log('Erreur convocation_print: '.$e->getMessage());
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Erreur: '.$e->getMessage();
    exit;
}
?>
