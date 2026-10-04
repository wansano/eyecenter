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
$reponse1 = $bdd->prepare('SELECT * FROM collaborateurs WHERE id_collaborateur = ?');
$reponse1 -> execute(array($nom));
$patient=" ";
    while ($donnees1 = $reponse1->fetch())
    {
        $patient=$donnees1['nom_collaborateur'];
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

function contact($nom){
include('../PUBLIC/connect.php');
$reponse1 = $bdd->prepare('SELECT * FROM collaborateurs WHERE id_collaborateur=?');
$reponse1 -> execute(array($nom));
$contact=" ";
    while ($donnees1 = $reponse1->fetch())
        {
        $contact=$donnees1['telephone'];
        }
        return $contact;
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

    $reponse2 = $bdd->prepare('SELECT * FROM paiements_collaborateurs WHERE id_paie=?');
    $reponse2 -> execute(array($_GET['paiement']));
    $donnees2 = $reponse2->fetch();

    $reponse1 = $bdd->prepare('SELECT * FROM collaborateurs WHERE id_collaborateur=?');
    $reponse1 -> execute(array($donnees2['id_collaborateur']));
    $donnees1 = $reponse1->fetch();
    // Title
    $pdf->Cell(0,5,utf8_decode('Date Paiement :'.' '.$donnees2['date_ajout'].''),0,1);
//$pdf->Ln(2);
$pdf->SetFont('courier','B',13);
$pdf->Cell(0,10,utf8_decode('REÇU DE PAIEMENT COLLABORATEUR N°'.$donnees2['id_paie'].''),0,0,'C');
$pdf->Ln(10);
    $pdf->SetFont('courier','',11);
$html='<table align="center" border="">
<hr />
<tr style="line-height:1px;">
<td width="350" height="50">COLLABORATEUR : '.utf8_decode(patient($donnees2['id_collaborateur'])).'</td>
</tr>';
$html.='<tr style="line-height:1px;">
<td width="350" height="50" >CONTACT : '.contact($donnees2['id_collaborateur']).'</td>
</tr>';
if ($donnees1['collaborateur_pour'] == 2) {
$html.='<tr style="line-height:1px;">
<td width="350" height="50" >COLLABORATION : Boutique</td>
</tr>';}
if ($donnees1['collaborateur_pour'] == 1) {
$html.='<tr style="line-height:1px;">
<td width="350" height="50" >COLLABORATION : Clinique</td>
</tr>';}
$html.='<tr style="line-height:1px;">
<td width="350" height="50" >MONTANT PAYE : '.number_format($donnees2['montant_paye']).' '.$data['devise'].'</td>
</tr>';
$html.='<tr style="line-height:1px;">
<td width="350" height="50" >MOTIF: '.iconv('UTF-8', 'ISO-8859-1//TRANSLIT',$donnees2['motif']).'</td>
</tr>';
$html.='<tr style="line-height:1px;border:1px;">
<td width="350" height="50">'.utf8_decode('PAYER PAR : '.compte($donnees2['compte'])).' </td>
</tr>';
$html.='<tr style="line-height:1px;border:1px;">
<td width="350" height="50">'.utf8_decode('PAYER A : '.$donnees2['paye_a']).' </td>
</tr>
<hr />';
$pdf->WriteHTML($html);
$pdf->Ln(8);
$pdf->Cell(0,5,utf8_decode("Signature collaborateur"),0,0,'L');
$pdf->Cell(0,5,utf8_decode("Signature & Cachet"),0,0,'R');
$pdf->Cell(0,60,utf8_decode(caissier($donnees2['payeur'])),0,0,'R');
$pdf->Output();

?>
