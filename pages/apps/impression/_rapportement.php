<?php
require_once('../PDF/fpdf.php');
require_once('../PDF/font/CenturyGothic.php');
require_once('../PDF/html_table13.php');
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();// on y ajoute une première page
$pdf->AddFont('CenturyGothic','','CenturyGothic.php');
$pdf->AddFont('CenturyGothic','B','CenturyGothic_bold.php');
$pdf->SetAutoPageBreak(true,-1);//Creation d'une nouvelle page auto à false
    // Arial bold 15
//$pdf->Ln(5);
$pdf->SetFont('CenturyGothic','',14);

// Récupération des informations de l'entreprise
$profil = $bdd->prepare('SELECT * FROM profil_entreprise');
$profil->execute();
$data = $profil->fetch();

// Fonctions pour générer l'en-tête du reçu

    $reponse1 = $bdd->prepare('SELECT * FROM affectations WHERE id_affectation=?');
    $reponse1 -> execute(array($_GET['affectation']));
    $donnees1 = $reponse1->fetch();

    $reponse3 = $bdd->prepare('SELECT * FROM rapportements WHERE id_affectation=?');
    $reponse3 -> execute(array($_GET['affectation']));
    $donnees3 = $reponse3->fetch();

    genererEntete($pdf, $data);

    // date de traitement
    $date = date('d/m/Y', strtotime($donnees3['date_traitement']));
    //information patient
$pdf->Cell(0, 5, utf8_decode('Ref n° : ' . $donnees3['reference'] . str_repeat(' ', 90) . 'Date : ' . $date), 0, 1);
//$pdf->Ln(2);
$html = '<table align="center" border="0">';
$html = '<hr />';
$html .= '<tr style="line-height:1px;"><td width="350" height="50">' . utf8_decode(nom_patient($donnees1['id_patient'])) . '</td></tr>';
$html .= '<tr style="line-height:1px;"><td width="350" height="50">' . utf8_decode(adress(return_adresse($donnees1['id_patient'])) ?: return_adresse($donnees1['id_patient'])) . ' | ' . return_phone($donnees1['id_patient']) . '</td></tr>';
$html .= '<hr />';
$html .= '</table>';
$pdf->WriteHTML($html);
$pdf->Ln(1);
$pdf->SetFont('CenturyGothic','B',13);
$pdf->Cell(0,16,iconv('UTF-8', 'ISO-8859-1//TRANSLIT', strtoupper("RAPPORT MEDICAL")),0,0,'C');
$pdf->Ln(16);
$pdf->SetFont('CenturyGothic','',11);
$pdf->MultiCell(0,6,iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $donnees3['rapport']), '');
$pdf->Ln(2);
$pdf->SetFont('CenturyGothic','B',10);
$pdf->Cell(0,8,utf8_decode('Dr '.traitant($donnees3['traitant'])),0,0,'C');
if ($donnees3['pathologie'] != NULL) {
   $pdf->Image(realpath('../documents/photo/'.$donnees3['pathologie']),175,52,27,32);
}
// Positionnement du code-barres en bas de page
$pageHeight = $pdf->GetPageHeight();
$yFooter = $pageHeight - 25; // 25mm du bas, ajustable selon la hauteur du code-barres
$pdf->SetY($yFooter);
$pdf->SetFont('CenturyGothic','',10);
$pdf->Codabar(10, $yFooter, $donnees1['type'].''.$_GET['affectation'], '0', 'Z', 0.35, 16, false); // false pour ne pas afficher le numéro
$filename = 'RAPPORT MEDICAL PAT-' . $donnees1['id_patient'] . '.pdf';

    // Génération du PDF
    $pdf->Output   ($filename, 'I');