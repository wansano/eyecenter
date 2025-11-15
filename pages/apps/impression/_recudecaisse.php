<?php
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

// Récupération des informations de l'entreprise
$profil = $bdd->prepare('SELECT * FROM profil_entreprise');
$profil->execute();
$data = $profil->fetch();

// Fonctions pour générer l'en-tête du reçu
$pdf->SetFont('CenturyGothic','',12);
// Fonctions pour générer le contenu du reçu
function genererContenuRecu($pdf, $donnees1, $donnees2) {
    $html = '<table align="center" border="">';
    $html .= '<hr />';
    $html .= '<tr style="line-height:1px;"><td width="350" height="50">' . utf8_decode(nom_patient($donnees1['id_patient'])) . '</td></tr>';
    $html .= '<tr style="line-height:1px;"><td width="350" height="50">' . utf8_decode((adress(return_adresse($donnees1['id_patient'])) ?: return_adresse($donnees1['id_patient']))) . ' | ' . return_phone($donnees1['id_patient']) . '</td></tr>';
    $html .= '<hr />';
    $html .= '<tr style="line-height:1px;"><td width="350" height="50">' . utf8_decode('Prestation : ' . model($donnees1['type'])) . '</td></tr>';
    $html .= '<tr style="line-height:1px;border:1px;"><td width="350" height="50">' . utf8_decode('Montant Payé : ' . number_format($donnees2['montant_paye'])) . ' GNF </td></tr>';

    // Affichage conditionnel du solde
    if (!empty($donnees2['solde']) && floatval($donnees2['solde']) != 0) {
        $html .= '<tr style="line-height:1px;border:1px;"><td width="350" height="50">'
            . utf8_decode('Reste à Payer : ' . number_format($donnees2['solde'])) . ' GNF </td></tr>';
    }

    $html .= '<tr style="line-height:1px;border:1px;"><td width="350" height="50">' . utf8_decode('Payé Par : ' . type_paiement($donnees1['type_paiement'])) . ' </td></tr>';
    $html .= '<tr style="line-height:1px;border:1px;"><td width="350" height="50">' . utf8_decode('Paiement N° : ' . $donnees2['code']) . ' </td></tr>';
    return $html;
}

// Récupération des données
$reponse1 = $bdd->prepare('SELECT * FROM affectations WHERE id_affectation = ?');
$reponse1->execute(array($_GET['affectation']));
$donnees1 = $reponse1->fetch();

$reponse2 = $bdd->prepare('SELECT * FROM paiements WHERE id_affectation = ?');
$reponse2->execute(array($_GET['affectation']));
$donnees2 = $reponse2->fetch();

if (!$donnees1 || !$donnees2) {
    die("Données non trouvées");
}
// Premier reçu
genererEntete($pdf, $data);

$pdf->SetFont('CenturyGothic', '', 11);
$pdf->Cell(0, 5, utf8_decode('PAT-' . $donnees1['id_patient'] . str_repeat(' ', 128) . 'Date : ' . $donnees1['date']), 0, 1);
$pdf->WriteHTML(genererContenuRecu($pdf, $donnees1, $donnees2));

// Signature et NB
$pdf->Cell(0, -60, utf8_decode("signature et cachet"), 0, 0, 'R');
$pdf->Ln(2);
$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->Cell(0, -13, utf8_decode(traitant($donnees2['caisse'])), 0, 0, 'R');
$pdf->Ln(8);
$pdf->Cell(0, 5, utf8_decode("NB : Veuillez apporter un de vos reçu de paiement lors de votre prochaine visite."), 0, 0, 'L');
$pdf->SetFont('CenturyGothic', '', 11);
// Séparateur
$pdf->Ln(25);
$pdf->Cell(5, 0, str_repeat("-", 146), 0, 0, 'L');
$pdf->Ln(25);
// Deuxième reçu
genererEntete($pdf, $data, 163);
$pdf->Cell(0, 5, utf8_decode('PAT-' . $donnees1['id_patient'] . str_repeat(' ', 128) . 'Date : ' . $donnees1['date']), 0, 1);
$pdf->WriteHTML(genererContenuRecu($pdf, $donnees1, $donnees2));

// Signature et NB pour le deuxième reçu
$pdf->Cell(0, -60, utf8_decode("signature et cachet"), 0, 0, 'R');
$pdf->Ln(2);
$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->Cell(0, -13, utf8_decode(traitant($donnees2['caisse'])), 0, 0, 'R');
$pdf->Ln(8);
$pdf->Cell(0, 5, utf8_decode("NB : Veuillez remettre ce reçu à la comptabilité."), 0, 0, 'L');
$filename = 'RECU DE PAIEMENT PAT-' . $donnees1['id_patient'] . '.pdf';
$pdf->Output($filename, 'I');
?>
