<?php
require_once('../PDF/fpdf.php');
require_once('../PDF/font/CenturyGothic.php');
require_once('../PDF/html_table13.php');
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');

try {
    // Vérification du paramètre
    if (!isset($_GET['affectation'])) {
        throw new Exception("ID d'affectation manquant");
    }

    // Récupération des données en une seule requête
    $stmt = $bdd->prepare('
        SELECT a.*, t.*, av.*, t.date_traitement as date_traitement,
               p.nom_patient, p.adresse, p.phone
        FROM affectations a
        LEFT JOIN chirurgies t ON a.id_affectation = t.id_affectation
        LEFT JOIN acquitte_visuelle av ON a.id_affectation = av.id_affectation
        LEFT JOIN patients p ON a.id_patient = p.id_patient
        WHERE a.id_affectation = ?
    ');
    $stmt->execute([$_GET['affectation']]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        throw new Exception("Données non trouvées");
    }

    // Initialisation du PDF
    $pdf = new PDF('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->AddFont('CenturyGothic','','CenturyGothic.php');
    $pdf->AddFont('CenturyGothic','B','CenturyGothic_bold.php');
    $pdf->SetAutoPageBreak(true, -1);
    

     // Récupération des infos entreprise pour l'entête
    $profil = $bdd->prepare('SELECT * FROM profil_entreprise LIMIT 1');
    $profil->execute();
    $dataProfil = $profil->fetch(PDO::FETCH_ASSOC);
    if ($dataProfil) {
        genererEntete($pdf, $dataProfil);
    }

    // En-tête
    // date de traitement
    $date = date('d/m/Y', strtotime($data['date_traitement']));
    //information patient
    $pdf->Cell(0, 5, utf8_decode('PAT-' . $data['id_patient'] . str_repeat(' ', 128) . 'Date : ' . $date), 0, 1);
    $pdf->SetFont('CenturyGothic', '', 12);

    // Informations patient
$html = '<table align="center" border="">
<hr />
<tr style="line-height:1px;">
<td width="350" height="50">' . utf8_decode($data['nom_patient']) . '</td>
</tr>
<tr style="line-height:1px;">
<td width="350" height="50">' .  utf8_decode(adress($data['adresse']) ?: $data['adresse']) . ' | ' . $data['phone'] . '</td>
</tr>
<hr /> <br>
<tr>
<td>AVLSC : OD ' . $data['od_avlsc'] . ' et OS ' . $data['os_avlsc'] . '
<br>AVC : OD ' . $data['od_avc'] . ' et OS ' . $data['os_avc'] . '
<br>TS : OD ' . $data['od_ts'] . ' et OS ' . $data['os_ts'] . '
<br>P : ' . $data['p'] . '</td>
</tr>';
$pdf->WriteHTML($html);

    // Détails du traitement
    $pdf->SetFont('CenturyGothic', 'B', 12);
    $pdf->Cell(0, 20, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', "INFORMATION DE " . strtoupper(model($data['id_type']))), 0, 0, 'C');
    $pdf->Ln(18);

    // Fonction helper pour ajouter une section
    function addSection($pdf, $title, $content) {
        $pdf->SetFont('CenturyGothic', 'B', 12); // Titre en gras
        $pdf->Cell(0, 5, utf8_decode($title), 0, 1);
        $pdf->SetFont('CenturyGothic', '', 12); // Texte normal
        $pdf->MultiCell(0, 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $content), '');
        $pdf->Ln(6);
    }

    // Ajout des sections
    addSection($pdf, 'DIAGNOSTIC :', $data['diagnostic']);
    addSection($pdf, 'TRAITEMENT :', $data['traitement']);
    addSection($pdf, 'PROTOCOLE :', $data['protocole']);
    addSection($pdf, 'PRESCRIPTION :', $data['prescription']);

    // Signature
    $pdf->Ln(4);
    $pdf->SetFont('CenturyGothic', 'B', 12);
    $pdf->Cell(0, 8, utf8_decode('Dr ' . traitant($data['traitant'])), 0, 0, 'C');

    $pdf->Output();

} catch (Exception $e) {
    error_log("Erreur lors de la génération du document : " . $e->getMessage());
    die("Une erreur est survenue lors de la génération du document");
}