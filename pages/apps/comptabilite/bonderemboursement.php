<?php
session_start();

require('../PDF/fpdf.php');
require('../PDF/font/CenturyGothic.php');
require('../PDF/html_table13.php');
include('../PUBLIC/connect.php');

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();// on y ajoute une première page
$pdf->AddFont('courier','','courier.php');
$pdf->SetAutoPageBreak(false,0);//Creation d'une nouvelle page auto à false
    // Arial bold 15
//$pdf->Ln(5);
$pdf->SetFont('courier','',14);

function patient($nom){
include('../PUBLIC/connect.php');
$reponse1 = $bdd->prepare('SELECT * FROM patients WHERE id_patient = ?');
$reponse1 -> execute(array($nom));
$patient=" ";
    while ($donnees1 = $reponse1->fetch())
    {
        $patient=$donnees1['nom_patient'];
    }
    return $patient;
    }



function compte($nom){
include('../PUBLIC/connect.php');
$reponse1 = $bdd->prepare('SELECT * FROM comptes WHERE id_compte= ?');
$reponse1 -> execute(array($nom));
$compte=" ";
    while ($donnees1 = $reponse1->fetch())
    {
        $compte=$donnees1['types'];

    }
    return $compte;
    }

function traitement($nom){
include('../PUBLIC/connect.php');
$reponse1 = $bdd->prepare('SELECT * FROM traitements WHERE id_type=?');
$reponse1 -> execute(array($nom));
$traitement=" ";
    while ($donnees1 = $reponse1->fetch())
        {
        $traitement=$donnees1['nom_type'];

        }
        return $traitement;
        }

function caissier($nom){
include('../PUBLIC/connect.php');
$reponse1 = $bdd->prepare('SELECT * FROM users WHERE id=?');
$reponse1 -> execute(array($nom));
$caissier=" ";
    while ($donnees1 = $reponse1->fetch())
    {
    $caissier=$donnees1['pseudo'];

    }
    return $caissier;
    }

    // Title
    $profil = $bdd->prepare('SELECT * FROM profil_entreprise ');
    $profil -> execute();
    $data = $profil->fetch();
    $pdf->Image(realpath('../img/logo.jpg'),70,8,75,28);
    $pdf->Ln(23);
    $pdf->Cell(0,12,utf8_decode($data['denomination']),0,1,'C');
    $pdf->SetFont('courier','',11);
    $pdf->Cell(0,0,utf8_decode('ARRETE N° '.$data['arrete']),0,1,'C');
    $pdf->Ln(6);
    $pdf->Cell(0,0,utf8_decode($data['adresse'].' | '.$data['phone'].' | '.$data['email'].''),0,1,'C');
    // Line break
    $pdf->Ln(10);

    $reponse2 = $bdd->prepare('SELECT * FROM remboursements WHERE id_affectation=?');
    $reponse2 -> execute(array($_GET['affectation']));
    $donnees2 = $reponse2->fetch();

    $reponse1 = $bdd->prepare('SELECT * FROM patients WHERE id_patient=?');
    $reponse1 -> execute(array($donnees2['patient']));
    $donnees1 = $reponse1->fetch();
    // Title
    $pdf->Cell(0,5,utf8_decode('Date :'.' '.$donnees2['date_ajout'].''),0,1);
//$pdf->Ln(2);
$pdf->SetFont('courier','B',13);
$pdf->Cell(0,10,utf8_decode('REÇU DE REMBOURSEMENT PATIENT N°'.$donnees2['id_remboursement'].''),0,0,'C');
$pdf->Ln(10);
    $pdf->SetFont('courier','',11);
$html='<table align="center" border="">
<hr />
<tr style="line-height:1px;">
<td width="350" height="50">PATIENT : '.utf8_decode(patient($donnees2['patient'])).'</td>
</tr>';
$html.='<tr style="line-height:1px;">
<td width="350" height="50" >MOTIF : '.utf8_decode(traitement($donnees2['types'])).'</td>
</tr>';
$html.='<tr style="line-height:1px;">
<td width="350" height="50" >MONTANT PAYE : '.number_format($donnees2['montant_paye']).' '.$data['devise'].'</td>
</tr>';
$html.='<tr style="line-height:1px;">
<td width="350" height="50" >MONTANT RESTITUE: '.number_format($donnees2['montant_paye']).' '.$data['devise'].'</td>
</tr>';
$html.='<tr style="line-height:1px;">
<td width="350" height="50" >MONTANT RESTANT: '.number_format($donnees2['montant_restant']).' '.$data['devise'].'</td>
</tr>';
$html.='<tr style="line-height:1px;border:1px;">
<td width="350" height="50">'.utf8_decode('PAYER PAR : '.compte($donnees2['compte'])).' </td>
</tr>';
$html.='<tr style="line-height:1px;border:1px;">
<td width="350" height="50">'.utf8_decode('PAYER A : '.$donnees2['paye_a']).' </td>
</tr>
<hr />';
$pdf->WriteHTML($html);
$pdf->SetFont('courier','',11);
$pdf->Cell(0,-60,utf8_decode("Signature & Cachet"),0,0,'R');
$pdf->Ln(2);
$pdf->Cell(0,-13,utf8_decode(caissier($donnees2['payeur'])),0,0,'R');
$pdf->SetFont('courier','',11);
$pdf->Ln(4);
$pdf->Cell(-5,0,("----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------"),0,0,'C');

$pdf->Ln(10);
$pdf->Image(realpath('../img/logo.jpg'),70,150,70,28);
    $pdf->Ln(23);
    $pdf->Cell(0,12,utf8_decode($data['denomination']),0,1,'C');
    $pdf->SetFont('courier','',11);
    $pdf->Cell(0,0,utf8_decode('ARRETE N° '.$data['arrete']),0,1,'C');
    $pdf->Ln(6);
    $pdf->Cell(0,0,utf8_decode($data['adresse'].' | '.$data['phone'].' | '.$data['email'].''),0,1,'C');
    // Line break
    $pdf->Ln(10);

    $pdf->Cell(0,5,utf8_decode('Date :'.' '.$donnees2['date_ajout'].''),0,1);
//$pdf->Ln(2);
$pdf->SetFont('courier','B',13);
$pdf->Cell(0,10,utf8_decode('REÇU DE REMBOURSEMENT PATIENT N°'.$donnees2['id_remboursement'].''),0,0,'C');
$pdf->Ln(10);
    $pdf->SetFont('courier','',11);
$html='<table align="center" border="">
<hr />
<tr style="line-height:1px;">
<td width="350" height="50">PATIENT : '.utf8_decode(patient($donnees2['patient'])).'</td>
</tr>';
$html.='<tr style="line-height:1px;">
<td width="350" height="50" >MOTIF : '.utf8_decode(traitement($donnees2['types'])).'</td>
</tr>';
$html.='<tr style="line-height:1px;">
<td width="350" height="50" >MONTANT PAYE : '.number_format($donnees2['montant_paye']).' '.$data['devise'].'</td>
</tr>';
$html.='<tr style="line-height:1px;">
<td width="350" height="50" >MONTANT RESTITUE: '.number_format($donnees2['montant_paye']).' '.$data['devise'].'</td>
</tr>';
$html.='<tr style="line-height:1px;">
<td width="350" height="50" >MONTANT RESTANT: '.number_format($donnees2['montant_restant']).' '.$data['devise'].'</td>
</tr>';
$html.='<tr style="line-height:1px;border:1px;">
<td width="350" height="50">'.utf8_decode('PAYER PAR : '.compte($donnees2['compte'])).' </td>
</tr>';
$html.='<tr style="line-height:1px;border:1px;">
<td width="350" height="50">'.utf8_decode('PAYER A : '.$donnees2['paye_a']).' </td>
</tr>
<hr />';
$pdf->WriteHTML($html);
$pdf->SetFont('courier','',11);
$pdf->Cell(0,-60,utf8_decode("Signature & Cachet"),0,0,'R');
$pdf->Ln(2);
$pdf->Cell(0,-13,utf8_decode(caissier($donnees2['payeur'])),0,0,'R');    

$pdf->Output();

?>
