<?php
require('../PDF/fpdf.php');
require('../PDF/html_table13.php');
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');

try {
    // Vérification des paramètres
    if (!isset($_GET['affectation'])) {
        throw new Exception("ID d'affectation manquant");
    }

    // Récupération des données en une seule requête
    $stmt = $bdd->prepare('
        SELECT a.*, p.*, pe.* 
        FROM affectations a
        JOIN patients p ON a.id_patient = p.id_patient
        JOIN profil_entreprise pe
        WHERE a.id_affectation = ?
    ');
    $stmt->execute([$_GET['affectation']]);
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

    // Titre
    $pdf->SetFont('CenturyGothic', 'B', 16);
    $pdf->Cell(0, 25, utf8_decode('FICHE DE CONSENTEMENT A UNE INTERVENTION CHIRURGICALE'), 0, 0, 'C');
    $pdf->Ln(22);

    // Corps du document
    $pdf->SetFont('CenturyGothic', '', 11);
    $consentText = sprintf(
        'Je soussigné(e) %s inscrit à la %s sous le n° dossier PAT-%s atteste que :
Le Médecin Thierno Madjou BAH m\'a délivré des informations claires concernant le diagnostic de ma maladie et l\'intervention chirurgicale d\'une %s qu\'il me recommande.
J\'ai été informé(e) des bénefices de cette intervention et des risques liés à la chirurgie.
Des risques particuliers liés à l\'intervention proposée pour laquelle j\'ai reçu des informations spécifiques.
J\'ai également été prévenu(e) qu\'au cours de l\'intervention, le chirurgien peut faire face à un évènement imprevu imposant des gestes différents de ceux initialement programmés et j\'autorise, dans ces conditions, le chirurgien à effectuer tout acte qu\'il estimerait indispensable en application des connaissances médicales actuelles.
Je reconnais avoir poser toutes les questions concernant cette intervention et avoir compris les explications données en réponse.
D\'un commun accord, nous sommes convenus d\'un délai entre la consultation et l\'intervention éventuelle; ce délai tient compte du type de pathologie à traiter, des disponibiltés de l\'équipe chirurgicale et de mes souhaits. Dans cet intervalle le chirurgien se rendra disponible pour repondre à d\'éventuelles demandes d\'informations complémentaires que je ferai directement ou par l\'intermediaire d\'un autre médecin traitant ou d\'un de mes proches parents.
Je m\'engage à me rendre aux consultations et à me soumettre aux soins prescrits avant et après l\'intervention chirurgicale.
En foi de quoi je conscents librement à cette intervention et j\'autorise le médecin de proceder à la chirurgie.',
        $data['nom_patient'],
        utf8_decode($data['denomination']),
        $data['id_patient'],
        model($data['type'])
    );
    $pdf->MultiCell(0, 7, utf8_decode($consentText));

    // Date et signatures
    $pdf->Ln(3);
    $pdf->Cell(0, 8, utf8_decode('Conakry, le ' . $data['date']), 0, 0, 'R');
    $pdf->Ln(10);
    
    // Signatures
    $patientLabel = ($data['sexe'] == "Femme") ? "La Patiente" : "Le Patient";
    $pdf->Cell(0, 10, utf8_decode($patientLabel), 0, 0, 'L');
    $pdf->Cell(0, 10, utf8_decode('Le Chirurgien'), 0, 0, 'R');

    // Code-barres
    // $pdf->Codabar(10, 273, 'COEC' . $data['id_affectation']);
    $filename = 'CONSENTEMENT CHIRURGICALE PAT-' . $data['id_patient'] . '.pdf';

    // Génération du PDF
    $pdf->Output   ($filename, 'I');

} catch (Exception $e) {
    error_log("Erreur lors de la génération du consentement : " . $e->getMessage());
    die("Une erreur est survenue lors de la génération du document : " . $e->getMessage());
}

