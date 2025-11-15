<?php
require_once('../PDF/fpdf.php');
require_once('../PDF/html_table13.php');
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');

try {
    if (!isset($_GET['affectation'])) {
        throw new Exception("ID d'affectation manquant");
    }
    $affectation = $_GET['affectation'];

    // Récupération de toutes les données nécessaires en une seule requête
    $stmt = $bdd->prepare('
        SELECT a.*, p.*, p.adresse AS adresse_patient, pe.*, pe.adresse AS adresse_entreprise, m.*
        FROM affectations a
        JOIN patients p ON a.id_patient = p.id_patient
        JOIN profil_entreprise pe
        LEFT JOIN mesures m ON m.id_affectation = a.id_affectation
        WHERE a.id_affectation = ?
    ');
    $stmt->execute([$affectation]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        throw new Exception("Données non trouvées");
    }

    // Initialisation du PDF
    $pdf = new PDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(false, 0);
    setlocale(LC_CTYPE, 'fr_FR');
    $pdf->AddFont('CenturyGothic','','CenturyGothic.php');
    $pdf->AddFont('CenturyGothic','B','CenturyGothic_bold.php');

    // En-tête
    genererEntete($pdf, $data);
    // date de traitement
    $date = date('d/m/Y', strtotime($data['date_traitement']));
    //information patient
    $pdf->Cell(0, 5, utf8_decode('PAT-' . $data['id_patient'] . str_repeat(' ', 128) . 'Date : ' . $date), 0, 1);
$html = '<table align="center" border="">
<tr style="line-height:1px;">
<hr />
<td width="350" height="50">' . utf8_decode( $data['nom_patient']) . '</td>
</tr>
<tr style="line-height:1px;">
<td width="350" height="50">' . utf8_decode((adress($data['adresse_patient'])?:$data['adresse_patient'])) . ' | ' . return_phone($data['id_patient']) . '</td>
</tr>
</table> 
<hr />';
$pdf->WriteHTML($html);


    // Titre
    $pdf->SetFont('CenturyGothic', 'B', 16);
    $pdf->Cell(0, 25, utf8_decode('ORDONNANCE DES LUNETTES'), 0, 0, 'C');
    $pdf->Ln(22);
    $pdf->SetFont('CenturyGothic','',12);

    // Génération du tableau HTML de façon factorisée
    $fields = [
        'refraction' => ' %s',
        'od' => 'Oeil Droit : %s',
        'os' => 'Oeil Gauche : %s',
        'addit' => 'Add : %s',
        'eip' => 'EIP : %s'
    ];
    $htmls = '<table align="center" border="">';
    foreach ($fields as $key => $label) {
        if (!empty($data[$key])) {
            $htmls .= '<tr style="line-height:1px;"><td width="350" height="50">' . sprintf($label, utf8_decode($data[$key])) . '</td></tr><br>';
        }
    }
    $htmls .= '</table>';
    $pdf->WriteHTML($htmls);

    $pdf->Ln(8);
    if (!empty($data['details'])) {
        $pdf->SetFont('CenturyGothic', 'B', 12);
        $pdf->MultiCell(0,5,utf8_decode( $data['details']), '');
    }
    $pdf->Ln(16);
   
    $pdf->Cell(0, 5, utf8_decode('Dr ' . traitant($data['traitant'])), 0, 1, 'C');

    $filename = 'ORDONNANCE LUNETTES PAT-' . $data['id_patient'] . '.pdf';
    $pdf->Output($filename, 'I');

} catch (Exception $e) {
    error_log("Erreur lors de la génération du document : " . $e->getMessage());
    die("Une erreur est survenue lors de la génération du document : " . htmlspecialchars($e->getMessage()));
}
?>
