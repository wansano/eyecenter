<?php
session_start();

require('../PDF/fpdf.php');
require('../PDF/html_table13.php');
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->AddFont('CenturyGothic','','CenturyGothic.php');
$pdf->AddFont('CenturyGothic','B','CenturyGothic_bold.php');
$pdf->SetAutoPageBreak(false,0);
setlocale(LC_CTYPE, 'fr_FR');

$profil = $bdd->prepare('SELECT * FROM profil_entreprise');
$profil->execute();
$data = $profil->fetch();

$affStmt = $bdd->prepare('SELECT * FROM affectations WHERE id_affectation = ?');
$affStmt->execute([$_GET['affectation']]);
$aff = $affStmt->fetch();

$rembStmt = $bdd->prepare('SELECT * FROM remboursements WHERE id_affectation = ? ORDER BY id_remboursement DESC LIMIT 1');
$rembStmt->execute([$_GET['affectation']]);
$remb = $rembStmt->fetch();

if (!$aff || !$remb) {
    die('Données remboursement introuvables');
}

$patientId = $aff['id_patient'];
$patientNom = nom_patient($patientId);
$patientAdresse = adress(return_adresse($patientId)) ?: return_adresse($patientId);
$patientPhone = return_phone($patientId);
$prestation = model($aff['type']);
$montantRembourse = isset($remb['montant_remboursse']) ? (float)$remb['montant_remboursse'] : (isset($remb['montant_paye']) ? (float)$remb['montant_paye'] : 0);
$montantRestant = isset($remb['montant_restant']) ? (float)$remb['montant_restant'] : (isset($aff['montant']) ? (float)$aff['montant'] : 0);
$motif = isset($remb['motif']) ? $remb['motif'] : (isset($remb['motif_refus']) ? $remb['motif_refus'] : '');
$compteTxt = isset($remb['compte']) ? type_paiement($remb['compte']) : '';
$caissierTxt = traitant($remb['payeur']);
$devise = isset($data['devise']) ? $data['devise'] : 'GNF';

function contenuRemboursement($patientNom, $patientAdresse, $patientPhone, $prestation, $motif, $montantRembourse, $montantRestant, $compteTxt, $remb, $devise){
    $html = '<table align="center" border="">';
    $html .= '<hr />';
    $html .= '<tr style="line-height:1px;"><td width="350" height="50">'.utf8_decode($patientNom).'</td></tr>';
    $html .= '<tr style="line-height:1px;"><td width="350" height="50">'.utf8_decode($patientAdresse).' | '.$patientPhone.'</td></tr>';
    $html .= '<hr />';
    $html .= '<tr style="line-height:1px;"><td width="350" height="50">'.utf8_decode('Prestation : '.$prestation).'</td></tr>';
    if($motif!==''){
        $html .= '<tr style="line-height:1px;"><td width="350" height="50">'.utf8_decode('Motif : '.$motif).'</td></tr>';
    }
    $html .= '<tr style="line-height:1px;border:1px;"><td width="350" height="50">'.utf8_decode('Montant Remboursé : '.number_format($montantRembourse)).' '.utf8_decode($devise).' </td></tr>';
    if($montantRestant>0){
        $html .= '<tr style="line-height:1px;border:1px;"><td width="350" height="50">'.utf8_decode('Montant Restant : '.number_format($montantRestant)).' '.utf8_decode($devise).' </td></tr>';
    }
    if($compteTxt!==''){
        $html .= '<tr style="line-height:1px;border:1px;"><td width="350" height="50">'.utf8_decode('Remboursé Par : '.$compteTxt).' </td></tr>';
    }
    $html .= '<tr style="line-height:1px;border:1px;"><td width="350" height="50">'.utf8_decode('Remboursement N° : '.$remb['id_remboursement']).' </td></tr>';
    return $html;
}

// 1er exemplaire
genererEntete($pdf,$data);
$pdf->SetFont('CenturyGothic','',11);
$pdf->Cell(0,5,utf8_decode('PAT-'.$patientId.str_repeat(' ',128).'Date : '.$remb['date_ajout']),0,1);
$pdf->WriteHTML(contenuRemboursement($patientNom,$patientAdresse,$patientPhone,$prestation,$motif,$montantRembourse,$montantRestant,$compteTxt,$remb,$devise));
$pdf->Cell(0,-60,utf8_decode('signature et cachet'),0,0,'R');
$pdf->Ln(2);
$pdf->SetFont('CenturyGothic','B',11);
$pdf->Cell(0,-13,utf8_decode($caissierTxt),0,0,'R');
$pdf->Ln(8);
$pdf->SetFont('CenturyGothic','',11);
$pdf->Cell(0,5,utf8_decode('NB : Conservez ce reçu pour toute réclamation.'),0,0,'L');

// Séparateur
$pdf->Ln(25);
$pdf->Cell(5,0,str_repeat('-',146),0,0,'L');
$pdf->Ln(25);

// 2e exemplaire
genererEntete($pdf,$data,163);
$pdf->SetFont('CenturyGothic','',11);
$pdf->Cell(0,5,utf8_decode('PAT-'.$patientId.str_repeat(' ',128).'Date : '.$remb['date_ajout']),0,1);
$pdf->WriteHTML(contenuRemboursement($patientNom,$patientAdresse,$patientPhone,$prestation,$motif,$montantRembourse,$montantRestant,$compteTxt,$remb,$devise));
$pdf->Cell(0,-60,utf8_decode('signature et cachet'),0,0,'R');
$pdf->Ln(2);
$pdf->SetFont('CenturyGothic','B',11);
$pdf->Cell(0,-13,utf8_decode($caissierTxt),0,0,'R');
$pdf->Ln(8);
$pdf->SetFont('CenturyGothic','',11);
$pdf->Cell(0,5,utf8_decode('NB : À remettre à la comptabilité.'),0,0,'L');

$filename = 'RECU_REMBOURSEMENT_PAT-'.$patientId.'.pdf';
$pdf->Output($filename,'I');

?>