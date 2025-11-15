<?php

require('../PDF/fpdf.php');
require('../PDF/font/courier.php');
require('../PDF/html_table13.php');
include('../PUBLIC/connect.php');
include('../PUBLIC/fonction.php');

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();// on y ajoute une première page
$pdf->AddFont('courier','','courier.php');
$pdf->SetAutoPageBreak(false,0);//Creation d'une nouvelle page auto à false
    // Arial bold 15
//$pdf->Ln(5);
setlocale(LC_CTYPE, 'fr_FR');
$pdf->SetFont('courier','',14);

function assurance($nom){
include('../PUBLIC/connect.php');
$reponse1 = $bdd->prepare('SELECT * FROM assurance WHERE id_assurance = ?');
$reponse1 -> execute(array($nom));
$assurance=" ";
  while ($donnees1 = $reponse1->fetch())
  {
      $assurance=$donnees1['assurance'];

    }
    return $assurance;
  }

function service($nom){
include('../PUBLIC/connect.php');
$reponse1 = $bdd->prepare('SELECT * FROM services WHERE id_service = ?');
$reponse1 -> execute(array($nom));
$service=" ";
  while ($donnees1 = $reponse1->fetch())
  {
      $service=$donnees1['nom_service'];

    }
    return $service;
  } 
  
function adresse($nom){
include('../PUBLIC/connect.php');
$reponse1 = $bdd->prepare('SELECT * FROM patients WHERE id_patient = ?');
$reponse1 -> execute(array($nom));
$adresse=" ";
  while ($donnees1 = $reponse1->fetch())
  {
      $adresse=$donnees1['adresse'];

    }
    return $adresse;
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

  $profil = $bdd->prepare('SELECT * FROM profil_entreprise ');
  $profil -> execute();
  $data = $profil->fetch();
  $pdf->Image(realpath('../img/logo.jpg'),70,8,70,28);
  $pdf->Ln(23);
  $pdf->Cell(0,12,utf8_decode($data['denomination']),0,1,'C');
  $pdf->SetFont('courier','',11);
  $pdf->Cell(0,0,utf8_decode('ARRETE N° '.$data['arrete']),0,1,'C');
  $pdf->Ln(6);
  $pdf->Cell(0,0,utf8_decode($data['adresse'].' | '.$data['phone'].' | '.$data['email'].''),0,1,'C');

  $pdf->Ln(10);
  $reponse1 = $bdd->prepare('SELECT * FROM affectations WHERE id_affectation=?');
  $reponse1 -> execute(array($_GET['affectation']));
  $donnees1 = $reponse1->fetch();

  $reponse2 = $bdd->prepare('SELECT * FROM paiements WHERE id_affectation=?');
  $reponse2 -> execute(array($_GET['affectation']));
  $donnees2 = $reponse2->fetch();
  // Title
  $pdf->Cell(0,5,utf8_decode('ID PAT-'.$donnees1['id_patient'].'                                                                                                                       Date :'.' '.$donnees1['date'].''),0,1);

$pdf->SetFont('courier','',11);
$html='<table align="center" border="">
<hr />
<tr style="line-height:1px;">
<td width="350" height="50">'.utf8_decode(patient($donnees1['id_patient'])).'</td>
</tr>';
$html.='<tr style="line-height:1px;">
<td width="350" height="50" >'.utf8_decode(adress($donnees1['id_patient'])).' | '.contact($donnees1['id_patient']).'</td>
</tr>
<hr />';
$html.='<tr style="line-height:1px;">
<td width="350" height="50">'.utf8_decode('SERVICE : '.service($donnees1['id_service'])).'</td>
</tr>';

$html.='<tr style="line-height:1px;">
<td width="350" height="50" >'.utf8_decode('MOTIF : '.type($donnees1['type'])).'</td>
</tr>';
$html.='<tr style="line-height:1px;border:1px;">
<td width="350" height="50">'.utf8_decode('MONTANT PAYÉ : '.number_format($donnees1['montant'])).' GNF </td>
</tr>';
$html.='<tr style="line-height:1px;border:1px;">
<td width="350" height="50">'.utf8_decode('PAYÉ PAR : '.compte($donnees1['type_paiement'])).' </td>
</tr>';
$html.='<tr style="line-height:1px;border:1px;">
<td width="350" height="50">'.utf8_decode('PAIEMENT N° : '.$donnees2['code']).' du '.$donnees1['date'].'</td>
</tr>';

  $pdf->WriteHTML($html); 
  $pdf->SetFont('courier','',11);
  $pdf->Cell(0,-60,utf8_decode("Signature & Cachet"),0,0,'R');
  $pdf->Ln(2);
  $pdf->Cell(0,-13,utf8_decode(caissier($donnees2['caisse'])),0,0,'R');
  $pdf->Ln(2);
  $pdf->SetFont('courier','B',11);
  $pdf->Cell(0,5,utf8_decode("NB : Veuillez apporter votre reçu lors de votre prochaine visite."),0,0,'L');
  $pdf->Ln(12);
  $pdf->SetFont('courier','',11);
  $pdf->Cell(-5,0,("----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------"),0,0,'C');

  $pdf->Ln(10);

  $pdf->Image(realpath('../img/logo.jpg'),70,150,70,28);
  $pdf->Ln(23);
  $pdf->Cell(0,12,utf8_decode($data['denomination']),0,1,'C');
  $pdf->SetFont('courier','',11);
  $pdf->Cell(0,0,utf8_decode('ARRETE N° '.$data['arrete']),0,1,'C');
  $pdf->Ln(6);
  $pdf->Cell(0,0,utf8_decode($data['adresse'].' | '.$data['phone'].' | '.$data['email'].''),0,1,'C');

  $pdf->Ln(10);
  
  $pdf->SetFont('courier','',11);
  $pdf->Cell(0,5,utf8_decode('ID PAT-'.$donnees1['id_patient'].'                                                                                                                       Date :'.' '.$donnees1['date'].''),0,1); 
$html='<table align="center" border="">
<hr />
<tr style="line-height:1px;">
<td width="350" height="50">'.utf8_decode(patient($donnees1['id_patient'])).'</td>
</tr>';
$html.='<tr style="line-height:1px;">
<td width="350" height="50" >'.utf8_decode(adresse($donnees1['id_patient'])).' | '.contact($donnees1['id_patient']).'</td>
</tr>
<hr />';
$html.='<tr style="line-height:1px;">
<td width="350" height="50">'.utf8_decode('SERVICE : '.service($donnees1['id_service'])).'</td>
</tr>';

$html.='<tr style="line-height:1px;">
<td width="350" height="50" >'.utf8_decode('MOTIF : '.type($donnees1['type'])).'</td>
</tr>';
$html.='<tr style="line-height:1px;border:1px;">
<td width="350" height="50">'.utf8_decode('MONTANT PAYÉ : '.number_format($donnees1['montant'])).' GNF </td>
</tr>';
$html.='<tr style="line-height:1px;border:1px;">
<td width="350" height="50">'.utf8_decode('PAYÉ PAR : '.compte($donnees1['type_paiement'])).' </td>
</tr>';
$html.='<tr style="line-height:1px;border:1px;">
<td width="350" height="50">'.utf8_decode('PAIEMENT N° : '.$donnees2['code']).' du '.$donnees1['date'].'</td>
</tr>';
  $pdf->WriteHTML($html); 

  $pdf->Cell(0,-60,utf8_decode("Signature & Cachet"),0,0,'R');
  $pdf->Ln(2);
  $pdf->Cell(0,-13,utf8_decode(caissier($donnees2['caisse'])),0,0,'R');
  $pdf->Ln(2);
  $pdf->SetFont('courier','B',11);
  $pdf->Cell(0,5,utf8_decode("NB : Veuillez remettre ce reçu à la comptabilité."),0,0,'L');
$pdf->Output();

?>
