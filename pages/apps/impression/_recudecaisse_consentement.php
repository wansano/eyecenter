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

// Fonctions pour générer le contenu du reçu
function genererContenuRecu($pdf, $donnees1, $donnees2, $rdvDisplay = '') {
    $html = '<table align="center" border="">';
    $html .= '<hr />';
    $html .= '<tr style="line-height:1px;"><td width="350" height="50">' . nom_patient($donnees1['id_patient']) . '</td></tr>';
    $html .= '<tr style="line-height:1px;"><td width="350" height="50">' . (adress(return_adresse($donnees1['id_patient'])) ?: return_adresse($donnees1['id_patient'])) . ' | ' . return_phone($donnees1['id_patient']) . '</td></tr>';
    $html .= '<hr />';
    $html .= '<tr style="line-height:1px;"><td width="350" height="50">' . ('Prestation : ' . model($donnees1['type'])) . '</td></tr>';

    if (is_string($rdvDisplay) && trim($rdvDisplay) !== '') {
        $html .= '<tr style="line-height:1px;"><td width="350" height="50">' . ('Date de rendez-vous : ' . $rdvDisplay) . '</td></tr>';
    }

    $html .= '<tr style="line-height:1px;border:1px;"><td width="350" height="50">' . ('Montant Payé : ' . number_format($donnees2['montant_paye'])) . ' GNF </td></tr>';

    if (!empty($donnees2['solde']) && floatval($donnees2['solde']) != 0) {
        $html .= '<tr style="line-height:1px;border:1px;"><td width="350" height="50">'
            . ('Reste à Payer : ' . number_format($donnees2['solde'])) . ' GNF </td></tr>';
    }

    $html .= '<tr style="line-height:1px;border:1px;"><td width="350" height="50">' . ('Payé Par : ' . type_paiement($donnees1['type_paiement'])) . ' </td></tr>';
    $html .= '<tr style="line-height:1px;border:1px;"><td width="350" height="50">' . ('Paiement N° : ' . $donnees2['code']) . ' </td></tr>';
    return pdf_text_compat($html);
}

// Vérification des paramètres
$affectationId = isset($_GET['affectation']) ? (int)$_GET['affectation'] : 0;
if ($affectationId <= 0) {
    die('ID affectation invalide');
}

// Profil entreprise
$profilStmt = $bdd->prepare('SELECT * FROM profil_entreprise');
$profilStmt->execute();
$profil = $profilStmt->fetch(PDO::FETCH_ASSOC);

// Affectation + paiement
$reponse1 = $bdd->prepare('SELECT * FROM affectations WHERE id_affectation = ?');
$reponse1->execute([$affectationId]);
$donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC);

$reponse2 = $bdd->prepare('SELECT * FROM paiements WHERE id_affectation = ?');
$reponse2->execute([$affectationId]);
$donnees2 = $reponse2->fetch(PDO::FETCH_ASSOC);

if (!$donnees1 || !$donnees2 || !$profil) {
    die('Données non trouvées');
}

// Date RDV (affichage conditionnel) : uniquement si la prestation est liée à un rendez-vous et que celui-ci n'est pas encore arrivé.
$rdvDisplay = '';
$idRdv = (int)($donnees1['id_rdv'] ?? 0);
if ($idRdv > 0 && function_exists('getRdvInfo')) {
    $rdvInfo = getRdvInfo($bdd, $idRdv);
    $rawRdv = is_array($rdvInfo) ? trim((string)($rdvInfo['prochain_rdv'] ?? '')) : '';
    if ($rawRdv !== '') {
        try {
            $dtRdv = new DateTime($rawRdv);
            $now = new DateTime();
            if ($dtRdv > $now) {
                $rdvDisplay = $dtRdv->format('d/m/Y');
                if ($dtRdv->format('H:i:s') !== '00:00:00') {
                    $rdvDisplay .= ' ' . $dtRdv->format('H:i');
                }
            }
        } catch (Exception $e) {
            $rdvDisplay = '';
        }
    }
}

// --------- 1) REÇU (deux exemplaires) ---------
genererEntete($pdf, $profil);

$pdf->SetFont('CenturyGothic', '', 11);
$pdf->Cell(0, 5, pdf_text_compat('PAT-' . $donnees1['id_patient'] . str_repeat(' ', 128) . 'Date : ' . $donnees1['date']), 0, 1);
$pdf->WriteHTML(genererContenuRecu($pdf, $donnees1, $donnees2, $rdvDisplay));

$pdf->Cell(0, -60, pdf_text_compat('signature et cachet'), 0, 0, 'R');
$pdf->Ln(2);
$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->Cell(0, -13, pdf_text_compat(traitant($donnees2['caisse'])), 0, 0, 'R');
$pdf->Ln(8);
$pdf->Cell(0, 5, pdf_text_compat('NB : Veuillez apporter un de vos reçu de paiement lors de votre prochaine visite.'), 0, 0, 'L');
$pdf->SetFont('CenturyGothic', '', 11);

$pdf->Ln(25);
$pdf->Cell(5, 0, str_repeat('-', 146), 0, 0, 'L');
$pdf->Ln(25);

genererEntete($pdf, $profil, 163);
$pdf->Cell(0, 5, pdf_text_compat('PAT-' . $donnees1['id_patient'] . str_repeat(' ', 128) . 'Date : ' . $donnees1['date']), 0, 1);
$pdf->WriteHTML(genererContenuRecu($pdf, $donnees1, $donnees2, $rdvDisplay));

$pdf->Cell(0, -60, pdf_text_compat('signature et cachet'), 0, 0, 'R');
$pdf->Ln(2);
$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->Cell(0, -13, pdf_text_compat(traitant($donnees2['caisse'])), 0, 0, 'R');
$pdf->Ln(8);
$pdf->Cell(0, 5, pdf_text_compat('NB : Veuillez remettre ce reçu à la comptabilité.'), 0, 0, 'L');

// --------- 2) CONSENTEMENT (nouvelle page) ---------
$pdf->AddPage();

// Charger patient
$patientStmt = $bdd->prepare('SELECT * FROM patients WHERE id_patient = ?');
$patientStmt->execute([(int)$donnees1['id_patient']]);
$patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
if (!$patient) {
    $patient = [];
}

$consData = array_merge($profil, $donnees1, $patient);

// Entête identique au reçu (profil entreprise)
genererEntete($pdf, $profil);

$pdf->SetFont('CenturyGothic', 'B', 16);
$pdf->Cell(0, 25, pdf_text_compat('FICHE DE CONSENTEMENT A UNE INTERVENTION CHIRURGICALE'), 0, 0, 'C');
$pdf->Ln(22);

$pdf->SetFont('CenturyGothic', '', 11);
$consentText = sprintf(
    "Je soussigné(e) %s inscrit à la %s sous le n° dossier PAT-%s atteste que :\n"
        . "Le Médecin Dr Thierno Madjou BAH m'a délivré des informations claires concernant le diagnostic de ma maladie et l'intervention chirurgicale d'une %s qu'il me recommande.\n"
        . "J'ai été informé(e) des bénefices de cette intervention et des risques liés à la chirurgie.\n"
        . "Des risques particuliers liés à l'intervention proposée pour laquelle j'ai reçu des informations spécifiques.\n"
        . "J'ai également été prévenu(e) qu'au cours de l'intervention, le chirurgien peut faire face à un évènement imprevu imposant des gestes différents de ceux initialement programmés et j'autorise, dans ces conditions, le chirurgien à effectuer tout acte qu'il estimerait indispensable en application des connaissances médicales actuelles.\n"
        . "Je reconnais avoir poser toutes les questions concernant cette intervention et avoir compris les explications données en réponse.\n"
        . "D'un commun accord, nous sommes convenus d'un délai entre la consultation et l'intervention éventuelle; ce délai tient compte du type de pathologie à traiter, des disponibiltés de l'équipe chirurgicale et de mes souhaits. Dans cet intervalle le chirurgien se rendra disponible pour repondre à d'éventuelles demandes d'informations complémentaires que je ferai directement ou par l'intermediaire d'un autre médecin traitant ou d'un de mes proches parents.\n"
        . "Je m'engage à me rendre aux consultations et à me soumettre aux soins prescrits avant et après l'intervention chirurgicale.\n"
        . "En foi de quoi je conscents librement à cette intervention et j'autorise le médecin de proceder à la chirurgie.",
    (string)($consData['nom_patient'] ?? nom_patient($donnees1['id_patient'])),
    pdf_text_compat((string)($consData['denomination'] ?? '')),
    (string)($donnees1['id_patient'] ?? ''),
    model((int)($donnees1['type'] ?? 0))
);
$pdf->MultiCell(0, 7, pdf_text_compat($consentText));

$pdf->Ln(3);
$pdf->Cell(0, 8, pdf_text_compat('Conakry, le ' . (string)($donnees1['date'] ?? '')), 0, 0, 'R');
$pdf->Ln(10);

$patientLabel = ((string)($consData['sexe'] ?? '') === 'Femme') ? 'La Patiente' : 'Le Patient';
$pdf->Cell(0, 10, pdf_text_compat($patientLabel), 0, 0, 'L');
$pdf->Cell(0, 10, pdf_text_compat('Le Chirurgien'), 0, 0, 'R');

$filename = 'RECU + CONSENTEMENT PAT-' . $donnees1['id_patient'] . '.pdf';
$pdf->Output($filename, 'I');
