<?php
require_once('../PDF/fpdf.php');
require_once('../PDF/font/CenturyGothic.php');
require_once('../PDF/html_table13.php');
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

try {
    if (!isset($_GET['medecin'], $_GET['debut'], $_GET['fin'])) {
        throw new Exception("Paramètres manquants");
    }

    $idMedecin = (int)$_GET['medecin'];
    $dateDebut = $_GET['debut'];
    $dateFin   = $_GET['fin'];

    // Récupération des prestations (mêmes règles que sur la page HTML)
    $tables = ['consultations', 'controles', 'examens', 'chirurgies', 'soins', 'mesures'];
    $queryParts = [];
    $params = [];

    foreach ($tables as $index => $table) {
        $userParam = ":userId{$index}";
        $dateDebParam = ":dateDeb{$index}";
        $dateFinParam = ":dateFin{$index}";

        $queryParts[] = "
            SELECT id_type, COUNT(*) AS count 
            FROM {$table} 
            WHERE traitant = {$userParam} 
              AND DATE(date_traitement) BETWEEN {$dateDebParam} AND {$dateFinParam}
            GROUP BY id_type
        ";

        $params[$userParam]    = $idMedecin;
        $params[$dateDebParam] = $dateDebut;
        $params[$dateFinParam] = $dateFin;
    }

    $unionSql = implode(' UNION ALL ', $queryParts);

    $finalSql = "
        SELECT t.id_type, t.nom_type, SUM(sub.count) AS total
        FROM ({$unionSql}) AS sub
        JOIN traitements t ON t.id_type = sub.id_type
        GROUP BY t.id_type, t.nom_type
        ORDER BY t.id_type
    ";

    $stmt = $bdd->prepare($finalSql);
    $stmt->execute($params);
    $traitements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($traitements)) {
        throw new Exception("Aucune prestation pour cette période");
    }

    // Initialisation du PDF
    $pdf = new PDF('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->AddFont('CenturyGothic','', 'CenturyGothic.php');
    $pdf->AddFont('CenturyGothic','B','CenturyGothic_bold.php');
    $pdf->SetAutoPageBreak(true, -15);

    // Entête entreprise
    $profil = $bdd->prepare('SELECT * FROM profil_entreprise LIMIT 1');
    $profil->execute();
    $dataProfil = $profil->fetch(PDO::FETCH_ASSOC);
    if ($dataProfil) {
        genererEntete($pdf, $dataProfil);
    }

    // Titre
    $pdf->Ln(5);
    $pdf->SetFont('CenturyGothic', 'B', 12);
    $titre = "RAPPORT DES REALISATIONS MEDICALES";
    $pdf->Cell(0, 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $titre), 0, 1, 'C');
    $pdf->Ln(4);

    // Infos médecin + période
    if (!function_exists('safeDateFr')) {
        function safeDateFr($dateStr) {
            $dt = DateTime::createFromFormat('Y-m-d', $dateStr) ?: new DateTime($dateStr);
            $mois = [
                '01'=>'janvier','02'=>'février','03'=>'mars','04'=>'avril','05'=>'mai','06'=>'juin',
                '07'=>'juillet','08'=>'août','09'=>'septembre','10'=>'octobre','11'=>'novembre','12'=>'décembre'
            ];
            $m = $dt->format('m');
            return $dt->format('d') . ' ' . ($mois[$m] ?? $m) . ' ' . $dt->format('Y');
        }
    }

    $pdf->SetFont('CenturyGothic', '', 11);
    $periode = 'Du ' . safeDateFr($dateDebut) . ' au ' . safeDateFr($dateFin);
    $pdf->Cell(0, 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Médecin : ' . traitant($idMedecin)), 0, 1, 'L');
    $pdf->Cell(0, 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $periode), 0, 1, 'L');
    $pdf->Ln(4);

    // Tableau des prestations
    $pdf->SetFont('CenturyGothic','B',11);
    $wType = 150;
    $wNb   = 40;

    $pdf->SetFillColor(0,102,204);
    $pdf->SetTextColor(255,255,255);
    $pdf->Cell($wType, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Type de prestation'), 1, 0, 'C', true);
    $pdf->Cell($wNb,   8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Nombre'),          1, 1, 'C', true);

    $pdf->SetFont('CenturyGothic','',11);
    $pdf->SetFillColor(255,255,255);
    $pdf->SetTextColor(0,0,0);

    $totalGeneral = 0;
    foreach ($traitements as $row) {
        $libelle = $row['nom_type'];
        $nb      = (int)$row['total'];
        $totalGeneral += $nb;

        $pdf->Cell($wType, 7, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $libelle), 1, 0, 'L');
        $pdf->Cell($wNb,   7, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$nb), 1, 1, 'C');
    }

    // Total
    $pdf->SetFont('CenturyGothic','B',11);
    $pdf->Cell($wType, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'TOTAL GENERAL'), 1, 0, 'R');
    $pdf->Cell($wNb,   8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$totalGeneral), 1, 1, 'C');

    // Pied de page
    $pdf->Ln(6);
    $pdf->SetFont('CenturyGothic', '', 8);
    $texteFooter = "Imprimé le " . date('d/m/Y') . " par " . traitant($_SESSION['auth']);
    $pdf->Cell(0, 6, utf8_decode($texteFooter), 0, 0, 'R');

    $pdf->Output();

} catch (Exception $e) {
    error_log("Erreur lors de la génération du rapport réalisations médecin : " . $e->getMessage());
    die("Une erreur est survenue lors de la génération du document");
}
