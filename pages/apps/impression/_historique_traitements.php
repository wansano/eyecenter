<?php
session_start();
require_once('../public/connect.php');
require_once('../public/fonction.php');
require_once('../PDF/fpdf.php');
@require_once('../PDF/font/CenturyGothic.php');

$id_patient = isset($_GET['id_patient']) ? (int)$_GET['id_patient'] : 0;
if ($id_patient <= 0) {
    http_response_code(400);
    echo 'Paramètre id_patient invalide';
    exit;
}

$clinique = getSingleRow($bdd, 'profil_entreprise');
$devise   = $clinique['devise'] ?? '';
$patient  = getPatientInfo($id_patient);

// Tables de traitements à agréger (fourni par l'utilisateur)
// Paramètres de filtre de dates (optionnels)
$start = isset($_GET['start_date']) ? $_GET['start_date'] : '1900-01-01';
$end   = isset($_GET['end_date'])   ? $_GET['end_date']   : '2100-12-31';
// Validation sommaire format YYYY-MM-DD
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$start)) $start = '1900-01-01';
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$end))   $end   = '2100-12-31';

// Définition des tables et champs à afficher (page séparée par traitement)
$tables = [
    ['table' => 'consultations',   'label' => 'Consultation', 'id' => 'id_affectation', 'fields' => ['diagnostic'=>'DIAGNOSTIC','bilan'=>'BILAN','traitement'=>'TRAITEMENT','prescription'=>'PRESCRIPTION']],
    ['table' => 'chirurgies',      'label' => 'Chirurgie',    'id' => 'id_affectation', 'fields' => ['diagnostic'=>'DIAGNOSTIC','traitement'=>'TRAITEMENT','protocole'=>'PROTOCOLE','prescription'=>'PRESCRIPTION']],
    ['table' => 'soins',           'label' => 'Soin',         'id' => 'id_affectation', 'fields' => ['diagnostic'=>'DIAGNOSTIC','conduite'=>'CONDUITE TENUE','prescription'=>'PRESCRIPTION']],
    ['table' => 'examens',         'label' => 'Examen',       'id' => 'id_affectation', 'fields' => ['diagnostic'=>'DIAGNOSTIC','resultat'=>'RESULTAT']],
    ['table' => 'controles',       'label' => 'Contrôle',     'id' => 'id_affectation', 'fields' => ['diagnostic'=>'DIAGNOSTIC','traitement'=>'TRAITEMENT','prescription'=>'PRESCRIPTION']],
    ['table' => 'mesures',         'label' => 'Mesure',       'id' => 'id_affectation', 'fields' => ['refraction'=>'REFRACTION','od'=>'OEIL DROIT','os'=>'OEIL GAUCHE','addit'=>'ADD','eip'=>'EIP','details'=>'DETAILS']],
    ['table' => 'rapportements',   'label' => 'Rapport',      'id' => 'id_affectation', 'fields' => ['rapport'=>'RAPPORT MEDICAL','pathologie'=>'PATHOLOGIE']],
];

// Fonction utilitaire pour récupérer les lignes d'une table donnée
function fetchRowsTraitement(PDO $bdd, $table, $id_patient, $start, $end) {
    $sql = "SELECT a.*, t.*, av.*, COALESCE(t.date_traitement, a.date) AS date_traitement, 
                   p.nom_patient, p.adresse, p.phone
            FROM affectations a
            JOIN $table t ON a.id_affectation = t.id_affectation
            LEFT JOIN acquitte_visuelle av ON a.id_affectation = av.id_affectation
            LEFT JOIN patients p ON a.id_patient = p.id_patient
            WHERE a.id_patient = ? AND COALESCE(t.date_traitement, a.date) BETWEEN ? AND ?
            ORDER BY date_traitement ASC";
    $stmt = $bdd->prepare($sql);
    $stmt->execute([$id_patient, $start, $end]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

class PDFHistorique extends FPDF {
    function __construct($orientation='P',$unit='mm',$size='A4') {
        parent::__construct($orientation,$unit,$size);
        $this->tryFonts();
    }
    function Header() {
        global $clinique;

        $this->SetMargins(10,10);
        $this->SetAutoPageBreak(true,12);
        genererEntete($this, $clinique, 12);
    }
    function tryFonts() {
        if(!isset($this->fonts['CenturyGothic'])) {
            $fontDir = realpath(__DIR__.'/../PDF/font');
            if($fontDir && is_file($fontDir.'/CenturyGothic.php') && is_file($fontDir.'/CenturyGothic_bold.php')) {
                $this->AddFont('CenturyGothic','', 'CenturyGothic.php');
                $this->AddFont('CenturyGothic','B','CenturyGothic_bold.php');
            }
        }
    }
    function mainFont() { return 'CenturyGothic'; }
    function Footer() {
        $this->SetY(-12);
        $this->SetFont($this->mainFont(),'',8);
        $this->Cell(0,6,pdf_text('Page '.$this->PageNo().'/{nb}'),0,0,'C');
    }
}

try {
    $pdf = new PDFHistorique('P','mm','A4');
    $pdf->AliasNbPages();

    // Préchargement des traitements pour construire l'index
    $traitementsParType = [];
    $totalTraitements = 0;
    foreach ($tables as $cfg) {
        $rows = fetchRowsTraitement($bdd, $cfg['table'], $id_patient, $start, $end);
        $traitementsParType[$cfg['label']] = $rows;
        $totalTraitements += count($rows);
    }
    // Calcul dynamique des pages et des libellés via model(id_type)
    $summaryCounts = []; // label => ['count'=>int,'start'=>int,'end'=>int]
    $currentPage = 2; // sommaire = page 1
    foreach ($tables as $cfg) {
        foreach ($traitementsParType[$cfg['label']] as $row) {
            $dynLabel = model($row['id_type']);
            if(!isset($summaryCounts[$dynLabel])) {
                $summaryCounts[$dynLabel] = ['count'=>0,'start'=>$currentPage,'end'=>$currentPage];
            }
            $summaryCounts[$dynLabel]['count']++;
            $summaryCounts[$dynLabel]['end'] = $currentPage;
            $currentPage++; // chaque traitement = 1 page
        }
    }

    // Page de sommaire
    $pdf->AddPage();
    $pdf->SetFont($pdf->mainFont(),'B',15);
    $pdf->Cell(0,10,pdf_text('SOMMAIRE HISTORIQUE DOSSIER PATIENT'),0,1,'C');
    $pdf->SetFont($pdf->mainFont(),'',10);
    $pdf->Cell(0,6,pdf_text('Patient : '.($patient['nom_patient'] ?? 'Inconnu')),0,1,'L');
    $pdf->Cell(0,6,pdf_text('PAT-'.($patient['id_patient'] ?? 'Inconnu')),0,1,'L');
    $pdf->Cell(0,6,pdf_text('Généré le: '.date('d/m/Y H:i')),0,1,'L');
    if($start!='1900-01-01' || $end!='2100-12-31') {
        $pdf->Cell(0,6,pdf_text('Période: '.date('d/m/Y',strtotime($start)).' - '.date('d/m/Y',strtotime($end))),0,1,'L');
    }
    $pdf->Ln(4);
    $pdf->SetFont($pdf->mainFont(),'B',10);
    $pdf->Cell(60,7,pdf_text('Prestation'),1,0,'L');
    $pdf->Cell(30,7,pdf_text('Nombre'),1,0,'C');
    $pdf->Cell(40,7,pdf_text('Pages'),1,1,'C');
    $pdf->SetFont($pdf->mainFont(),'',9);
    // Tri par page de début
    uasort($summaryCounts, function($a,$b){ return $a['start'] <=> $b['start']; });
    foreach ($summaryCounts as $lbl => $meta) {
        if($meta['count'] === 0) continue;
        $pages = ($meta['start']===$meta['end']) ? $meta['start'] : ($meta['start'].'-'.$meta['end']);
        $pdf->Cell(60,6,pdf_text($lbl),1,0,'L');
        $pdf->Cell(30,6,pdf_text((string)$meta['count']),1,0,'C');
        $pdf->Cell(40,6,pdf_text($pages),1,1,'C');
    }
    $pdf->Ln(4);
    $pdf->SetFont($pdf->mainFont(),'B',10);
    $pdf->Cell(0,6,pdf_text('Total traitements: '.$totalTraitements),0,1,'L');
    $pdf->SetFont($pdf->mainFont(),'',9);
    $pdf->MultiCell(0,4,pdf_text('Chaque traitement est imprimé sur une page distincte suivant le format des documents individuels. Les numéros de pages sont indicatifs et incluent cette page de sommaire.'));

    // Pages individuelles
    foreach ($tables as $cfg) {
        foreach ($traitementsParType[$cfg['label']] as $row) {
            $pdf->AddPage();
            $pdf->SetFont('CenturyGothic','B',14);
            $titre = ($cfg['table']==='mesures') ? 'ORDONNANCE DES LUNETTES' : ( strtoupper(model($row['id_type'])));
            $pdf->Cell(0,8,pdf_text($titre),0,1,'C');
            $pdf->SetFont('CenturyGothic','',11);
            $dateAff = $row['date_traitement'] ? date('d/m/Y', strtotime($row['date_traitement'])) : '';
            $pdf->Cell(0,6,pdf_text('Effectuée le : '.$dateAff),0,1,'R');
            $pdf->SetFont('CenturyGothic','',9);
            // $pdf->Cell(0,5,pdf_text(($row['nom_patient']??'').' | '.(adress($row['adresse'])?:$row['adresse']).' | '.($row['phone']??'')),0,1,'L');
            // Acuité (afficher uniquement les paramètres ayant une valeur)
            $acuSegments = [];
            // AVLSC
            if((isset($row['od_avlsc']) && trim($row['od_avlsc'])!=='') || (isset($row['os_avlsc']) && trim($row['os_avlsc'])!=='')) {
                $seg = 'AVLSC';
                if(isset($row['od_avlsc']) && trim($row['od_avlsc'])!=='') { $seg .= ' OD '.$row['od_avlsc']; }
                if(isset($row['os_avlsc']) && trim($row['os_avlsc'])!=='') { $seg .= (strpos($seg,'OD')!==false ? ' /' : '') .' OS '.$row['os_avlsc']; }
                $acuSegments[] = $seg;
            }
            // AVC
            if((isset($row['od_avc']) && trim($row['od_avc'])!=='') || (isset($row['os_avc']) && trim($row['os_avc'])!=='')) {
                $seg = 'AVC';
                if(isset($row['od_avc']) && trim($row['od_avc'])!=='') { $seg .= ' OD '.$row['od_avc']; }
                if(isset($row['os_avc']) && trim($row['os_avc'])!=='') { $seg .= (strpos($seg,'OD')!==false ? ' /' : '') .' OS '.$row['os_avc']; }
                $acuSegments[] = $seg;
            }
            // TS
            if((isset($row['od_ts']) && trim($row['od_ts'])!=='') || (isset($row['os_ts']) && trim($row['os_ts'])!=='')) {
                $seg = 'TS';
                if(isset($row['od_ts']) && trim($row['od_ts'])!=='') { $seg .= ' OD '.$row['od_ts']; }
                if(isset($row['os_ts']) && trim($row['os_ts'])!=='') { $seg .= (strpos($seg,'OD')!==false ? ' /' : '') .' OS '.$row['os_ts']; }
                $acuSegments[] = $seg;
            }
            // P
            if(isset($row['p']) && trim($row['p'])!=='') {
                $acuSegments[] = 'P '.$row['p'];
            }
            // Affichage si au moins un segment
            if(!empty($acuSegments) || (isset($row['glycemie']) && trim($row['glycemie'])!=='')) {
                $pdf->SetFont('CenturyGothic','B',10);
                $pdf->Cell(0,5,pdf_text('ACUITÉ VISUELLE :'),0,1,'L');
                if(!empty($acuSegments)) {
                    $pdf->SetFont('CenturyGothic','',9);
                    $pdf->Cell(0,5,pdf_text(implode(' | ',$acuSegments)),0,1,'L');
                }
                if(isset($row['glycemie']) && trim($row['glycemie'])!=='') {
                    $pdf->SetFont('CenturyGothic','',9);
                    $pdf->Cell(0,4,pdf_text('Glycémie: '.$row['glycemie']),0,1,'L');
                }
                $pdf->Ln(3);
            }
            else {
                // Aucun paramètre d'acuité avec valeur : ne rien afficher
            }
            $pdf->Ln(3);
            // Sections
            foreach ($cfg['fields'] as $col => $label) {
                if(isset($row[$col]) && trim((string)$row[$col])!=='') {
                    $pdf->SetFont('CenturyGothic','B',10);
                    $pdf->Cell(0,5,pdf_text($label.' :'),0,1,'L');
                    $pdf->SetFont('CenturyGothic','',9);
                    $pdf->MultiCell(0,5,pdf_text($row[$col]),0,'L');
                    $pdf->Ln(2);
                }
            }
            if(isset($row['traitant'])) {
                $pdf->Ln(3);
                $pdf->SetFont('CenturyGothic','B',10);
                $pdf->Cell(0,8,pdf_text('Dr '.traitant($row['traitant'])),0,1,'C');
            }
        }
    }

    if($totalTraitements===0) {
        // Aucun traitement
        $pdf->AddPage();
        $pdf->SetFont('CenturyGothic','B',14);
        $pdf->Cell(0,10,pdf_text('Aucun traitement enregistré pour ce patient sur la période.'),0,1,'L');
    }

    $pdf->Output('I','historique_traitements_patient_'.$id_patient.'_'.date('Ymd').'.pdf');
    exit;
} catch (Exception $e) {
    error_log('Erreur génération historique traitements: '.$e->getMessage());
    http_response_code(500);
    echo 'Erreur PDF: '.htmlspecialchars($e->getMessage());
}
