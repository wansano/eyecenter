<?php
require_once('../PDF/fpdf.php');
require_once('../PDF/font/CenturyGothic.php');
require_once('../PDF/html_table13.php');
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');

class DossierPatientPDF extends PDF {
    private $data;
    private $patient;
    
    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A5') {
        parent::__construct($orientation, $unit, $size);
        $this->SetMargins(1, 1);
        $this->AliasNbPages();
        $this->SetAutoPageBreak(true, 1);
        $this->AddFont('CenturyGothic', '', 'CenturyGothic.php');
        $this->AddFont('CenturyGothic', 'B', 'CenturyGothic_bold.php');

        $this->SetFont('CenturyGothic', '', 12);
    }

    public function initializeData($bdd, $id_patient) {
        try {
            // Charger les informations de l'entreprise
            $stmt = $bdd->prepare('SELECT * FROM profil_entreprise');
            $stmt->execute();
            $this->data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Charger les informations du patient
            $stmt = $bdd->prepare('SELECT * FROM patients WHERE id_patient = ?');
            $stmt->execute([$id_patient]);
            $this->patient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$this->patient) {
                throw new Exception("Patient non trouvé");
            }
        } catch (PDOException $e) {
            error_log("Erreur lors du chargement des données : " . $e->getMessage());
            throw new Exception("Erreur lors du chargement des données");
        }
    }
    
    public function generateHeader() {
        $this->Ln(250);
        $this->SetFont('CenturyGothic', 'B', 20);
        $this->Cell(0, 9, utf8_decode('N° ' . $this->patient['id_patient']), 0, 0, '');
        $this->Ln(9);
        if ($this->data) {
            genererEnteteDossier($this, $this->data);
        }
    }
    
    public function generatePatientInfo() {
        $this->SetFont('CenturyGothic', '', 8);
        $statut = $this->patient['assure'] == 1 ? 'Assuré' : 'Non assuré';
        $this->Cell(0, 5, utf8_decode($statut . str_repeat(' ', 101) . 'Date d\'admission ' . $this->patient['date']), 0, 1, 'L');
        
        $html = $this->generatePatientTable();
        $this->WriteHTML($html);
    }
    
    private function generatePatientTable() {
        $anneeNaissance = date('Y', strtotime($this->patient['age']));
        
        $html = '<table align="center">
<hr widht="50px"/>
<tr><td>Patient : ' . utf8_decode($this->patient['nom_patient'] . '    Né(e) en : ' . $anneeNaissance . '    Genre : ' . $this->patient['sexe'] . '  Téléphone : ' . $this->patient['phone']) . '</td></tr>
<tr><td>Adresse : ' . utf8_decode(adress($this->patient['adresse']) ?: $this->patient['adresse']) . '    Profession : ' . utf8_decode($this->patient['profession']) . '</td></tr>
<tr><hr widht="50px"/> 
<td>Motif de consultation : ....................................................................................................................................................<br>...........................................................................................................................................................................................</td></tr>
<tr><td>Evolution : ........................................................................................................................................................................</td></tr>
<tr><td>Terrain : ............................................................................................................................................................................</td></tr>
<tr><td>' . utf8_decode('Antécédents') . ' : .................................................................................................................................................................</td></tr><br>
<tr><hr widht="50px"/><br> 
<td> AVLSC :  OD .......... OS ..........  |  AVC :  OD ......... OS .........  |  TS :  OD ......... OS .........    P :  ..............................</td></tr><br>
<tr><td>1. Examen Externe : </td></tr>
<tr><td>2. Biomicroscopie : <br> </td><br>
<td> - Annexes : <br><br> </td>
<td> - ' . utf8_decode('Segment Antérieur') . ' : <br><br></td>
<td> - ' . utf8_decode('Segment Postérieur') . ' : </td></tr><br>
<tr><td>3. ' . utf8_decode('Diagnostic de présomption') . ' : </td></tr><br>
<tr><td>4. Conduite tenue :  <br> </td></tr>
<tr><td>5. ' . utf8_decode('Diagnostic définitif') . ' : </td></tr><br>
<tr><td>6. ' . utf8_decode('Contrôle de suivi') . ' : </td></tr><br><br>
<hr widht="50px"/>';
        
        return $html;
    }
    
    public function generateFooter() {
        $this->Cell(0, 5, utf8_decode("Voir le monde sous un nouveau jour !"), 0, 0, 'C');
    }
}

try {
    if (!isset($_GET['id_patient'])) {
        throw new Exception("ID patient non spécifié");
    }
    
    $pdf = new DossierPatientPDF();
    $pdf->AddPage();
    $pdf->initializeData($bdd, $_GET['id_patient']);
    $pdf->generateHeader();
    $pdf->generatePatientInfo();
    $pdf->generateFooter();
    $filename = 'DOSSIER DU PATIENT PAT-' . $_GET['id_patient'] . '.pdf';
    $pdf->Output($filename, 'I');
    
} catch (Exception $e) {
    error_log("Erreur dans la génération du dossier patient : " . $e->getMessage());
    die("Une erreur est survenue lors de la génération du dossier patient.");
}
?>
