<?php
session_start();

require('../PDF/fpdf.php');
require('../PDF/font/CenturyGothic.php');
require('../PDF/html_table13.php');
include('../PUBLIC/connect.php');

$idPaiement = isset($_GET['paiement']) ? (int)$_GET['paiement'] : 0;
if ($idPaiement <= 0) {
    http_response_code(400);
    echo 'Paiement invalide.';
    exit;
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->AddFont('courier','','courier.php');
$pdf->SetAutoPageBreak(false,0);
$pdf->SetFont('courier','',14);

function fournisseur_nom($id){
    include('../PUBLIC/connect.php');
    $st = $bdd->prepare('SELECT fournisseur FROM fournisseur_produit WHERE id_fournisseur = ?');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ? (string)$row['fournisseur'] : '';
}

function fournisseur_tel($id){
    include('../PUBLIC/connect.php');
    $st = $bdd->prepare('SELECT telephone FROM fournisseur_produit WHERE id_fournisseur = ?');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ? (string)$row['telephone'] : '';
}

function compte_type($id){
    include('../PUBLIC/connect.php');
    $st = $bdd->prepare('SELECT types, nom_compte FROM comptes WHERE id_compte = ?');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) return '';
    return (string)($row['types'] ?? $row['nom_compte'] ?? '');
}

function user_pseudo($id){
    include('../PUBLIC/connect.php');
    $st = $bdd->prepare('SELECT pseudo FROM users WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ? (string)$row['pseudo'] : '';
}

$profil = $bdd->prepare('SELECT * FROM profil_entreprise');
$profil->execute();
$data = $profil->fetch(PDO::FETCH_ASSOC) ?: [];
$devise = (string)($data['devise'] ?? '');

$logoPath = realpath('../img/logo.jpg');
if ($logoPath) {
    $pdf->Image($logoPath,70,8,75,28);
}
$pdf->Ln(23);
$pdf->Cell(0,12,utf8_decode((string)($data['denomination'] ?? '')),0,1,'C');
$pdf->SetFont('courier','',11);
$pdf->Cell(0,0,utf8_decode('ARRETE N° '.(string)($data['arrete'] ?? '')),0,1,'C');
$pdf->Ln(6);
$pdf->Cell(0,0,utf8_decode((string)($data['adresse'] ?? '').' | '.(string)($data['phone'] ?? '').' | '.(string)($data['email'] ?? '')),0,1,'C');
$pdf->Ln(10);

$stP = $bdd->prepare('SELECT * FROM paiements_fournisseurs WHERE id_paie = ?');
$stP->execute([$idPaiement]);
$p = $stP->fetch(PDO::FETCH_ASSOC);
if (!$p) {
    http_response_code(404);
    echo 'Paiement introuvable.';
    exit;
}

$stF = $bdd->prepare('SELECT * FROM fournisseur_produit WHERE id_fournisseur = ?');
$stF->execute([(int)$p['id_fournisseur']]);
$f = $stF->fetch(PDO::FETCH_ASSOC) ?: [];

$pdf->Cell(0,5,utf8_decode('Date Paiement : '.(string)($p['date_ajout'] ?? '')),0,1);
$pdf->SetFont('courier','B',13);
$pdf->Cell(0,10,utf8_decode('REÇU DE PAIEMENT FOURNISSEUR N°'.(string)($p['id_paie'] ?? $idPaiement)),0,0,'C');
$pdf->Ln(10);
$pdf->SetFont('courier','',11);

$html='<table align="center" border="">'
    .'<hr />'
    .'<tr style="line-height:1px;"><td width="350" height="50">FOURNISSEUR : '.utf8_decode(fournisseur_nom((int)$p['id_fournisseur'])).'</td></tr>'
    .'<tr style="line-height:1px;"><td width="350" height="50">CONTACT : '.utf8_decode(fournisseur_tel((int)$p['id_fournisseur'])).'</td></tr>'
    .'<tr style="line-height:1px;"><td width="350" height="50">TYPE : '.utf8_decode((string)($f['type_fournisseur'] ?? '')).'</td></tr>'
    .'<tr style="line-height:1px;"><td width="350" height="50">MONTANT PAYE : '.number_format((float)($p['montant_paye'] ?? 0)).' '.utf8_decode($devise).'</td></tr>'
    .'<tr style="line-height:1px;"><td width="350" height="50">MOTIF: '.iconv('UTF-8','ISO-8859-1//TRANSLIT',(string)($p['motif'] ?? '')).'</td></tr>'
    .'<tr style="line-height:1px;"><td width="350" height="50">PAYER PAR : '.utf8_decode(compte_type((int)($p['compte'] ?? 0))).'</td></tr>'
    .'<tr style="line-height:1px;"><td width="350" height="50">PAYER A : '.utf8_decode((string)($p['paye_a'] ?? '')).'</td></tr>'
    .'<hr />'
    .'</table>';

$pdf->WriteHTML($html);
$pdf->Ln(8);
$pdf->Cell(0,5,utf8_decode('Signature du Responsable'),0,0,'L');
$pdf->Cell(0,5,utf8_decode('Signature & Cachet'),0,0,'R');
$pdf->Ln(7);
$pdf->Cell(0,60,utf8_decode((string)($f['responsable'] ?? '')),0,0,'L');
$pdf->Cell(0,60,utf8_decode(user_pseudo((int)($p['payeur'] ?? 0))),0,0,'R');
$pdf->Output();
