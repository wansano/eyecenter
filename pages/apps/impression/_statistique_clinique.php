<?php
require_once('../PDF/fpdf.php');
require_once('../PDF/html_table13.php');
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');

session_start();

if (!isset($_SESSION['auth'])) {
    http_response_code(401);
    die('Accès non autorisé.');
}

$dateDebut = isset($_GET['dateDebut']) ? trim((string)$_GET['dateDebut']) : date('Y-m-01');
$dateFin = isset($_GET['dateFin']) ? trim((string)$_GET['dateFin']) : date('Y-m-d');

// Validation dates
$isValidDebut = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut);
$isValidFin = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin);

if (!$isValidDebut || !$isValidFin || $dateDebut > $dateFin) {
    http_response_code(400);
    die('Paramètres de date invalides.');
}

// Récupérer les données
$tables = ['consultations', 'controles', 'examens', 'chirurgies', 'soins', 'mesures'];
$unionParts = [];
$params = [];

foreach ($tables as $index => $table) {
    $unionParts[] = "SELECT DATE(date_traitement) AS date_prestation, id_patient, traitant, id_type, '$table' AS source_table FROM $table WHERE date_traitement IS NOT NULL AND DATE(date_traitement) BETWEEN :debut$index AND :fin$index";
    $params[':debut' . $index] = $dateDebut;
    $params[':fin' . $index] = $dateFin;
}

$prestations = [];
$patientIds = [];
$typeCounts = [];
$typeLabels = [];
$sexCounts = [
    'Masculin' => 0,
    'Feminin' => 0,
    'Autre' => 0,
    'Non renseigné' => 0,
];

$sql = 'SELECT date_prestation, id_patient, traitant, id_type, source_table FROM (' . implode(' UNION ALL ', $unionParts) . ') AS prestations_period ORDER BY date_prestation ASC, id_patient ASC, id_type ASC';

try {
    $stmt = $bdd->prepare($sql);
    $stmt->execute($params);
    $prestations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($prestations as $row) {
        $idType = (int)($row['id_type'] ?? 0);
        $typeCounts[$idType > 0 ? $idType : 0] = ($typeCounts[$idType > 0 ? $idType : 0] ?? 0) + 1;

        $idPatient = (int)($row['id_patient'] ?? 0);
        if ($idPatient > 0) {
            $patientIds[$idPatient] = true;
        }
    }

    if (!empty($patientIds)) {
        $placeholders = implode(',', array_fill(0, count($patientIds), '?'));
        $stmtPatients = $bdd->prepare('SELECT id_patient, sexe FROM patients WHERE id_patient IN (' . $placeholders . ')');
        $stmtPatients->execute(array_keys($patientIds));

        while ($patient = $stmtPatients->fetch(PDO::FETCH_ASSOC)) {
            $sexeRaw = strtolower(trim((string)($patient['sexe'] ?? '')));
            if ($sexeRaw === '') {
                $bucket = 'Non renseigné';
            } elseif (in_array($sexeRaw, ['1', 'm', 'masculin', 'homme', 'male'], true)) {
                $bucket = 'Masculin';
            } elseif (in_array($sexeRaw, ['0', 'f', 'feminin', 'féminin', 'femme', 'female'], true)) {
                $bucket = 'Feminin';
            } else {
                $bucket = 'Autre';
            }
            $sexCounts[$bucket]++;
        }
    }

    if (!empty($typeCounts)) {
        arsort($typeCounts);
        foreach ($typeCounts as $idType => $count) {
            $label = (int)$idType > 0 ? trim((string)model((int)$idType)) : 'Type non renseigné';
            if ($label === '') {
                $label = 'Prestation #' . (int)$idType;
            }
            $typeLabels[] = $label;
        }
    }

    // Récupérer la répartition par sexe pour chaque type de prestation
    $typeGenderCounts = [];
    
    // Requête pour obtenir la répartition par sexe et type de prestation
    $genderSql = 'SELECT presta.id_type, p.sexe, COUNT(*) as prestation_count 
                  FROM (' . implode(' UNION ALL ', $unionParts) . ') AS presta 
                  LEFT JOIN patients p ON presta.id_patient = p.id_patient 
                  GROUP BY presta.id_type, p.sexe';
    
    try {
        $genderStmt = $bdd->prepare($genderSql);
        $genderStmt->execute($params);
        $genderResults = $genderStmt->fetchAll(PDO::FETCH_ASSOC);

        // Initialiser les compteurs pour chaque type
        foreach (array_keys($typeCounts) as $idType) {
            $typeGenderCounts[(int)$idType] = [
                'male' => 0,
                'female' => 0,
                'total' => 0
            ];
        }

        // Remplir les compteurs avec les résultats
        foreach ($genderResults as $row) {
            $idType = (int)($row['id_type'] ?? 0);
            $sexeRaw = strtolower(trim((string)($row['sexe'] ?? '')));
            $count = (int)($row['prestation_count'] ?? 0);

            if (isset($typeGenderCounts[$idType])) {
                if (in_array($sexeRaw, ['1', 'm', 'masculin', 'homme', 'male'], true)) {
                    $typeGenderCounts[$idType]['male'] += $count;
                } elseif (in_array($sexeRaw, ['0', 'f', 'feminin', 'féminin', 'femme', 'female'], true)) {
                    $typeGenderCounts[$idType]['female'] += $count;
                }
                $typeGenderCounts[$idType]['total'] += $count;
            }
        }
    } catch (Throwable $e) {
        error_log('Erreur requête gender _statistique_clinique.php: ' . $e->getMessage());
        // Si la requête gender échoue, continuer avec des comptes vides
    }

} catch (Throwable $e) {
    error_log('Erreur _statistique_clinique.php: ' . $e->getMessage());
    http_response_code(500);
    die('Erreur lors du chargement des données.');
}

// Charger profil entreprise
$profilStmt = $bdd->prepare('SELECT * FROM profil_entreprise LIMIT 1');
$profilStmt->execute();
$dataProfil = $profilStmt->fetch(PDO::FETCH_ASSOC);

if (!$dataProfil) {
    $dataProfil = [
        'denomination' => 'Clinique',
        'adresse' => '',
        'phone' => '',
        'email' => '',
        'arrete' => 'N/A',
        'exploitation' => 'N/A'
    ];
}

// Initialiser PDF
$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->AddFont('CenturyGothic', '', 'CenturyGothic.php');
$pdf->AddFont('CenturyGothic', 'B', 'CenturyGothic_bold.php');
$pdf->SetAutoPageBreak(true, -15);

// Entête clinique
genererEntete($pdf, $dataProfil);

// Titre du rapport
$pdf->SetFont('CenturyGothic', 'B', 14);
$pdf->Cell(0, 8, pdf_text('STATISTIQUE PRESTATIONS CLINIQUES'), 0, 1, 'C');
$pdf->Ln(2);

// Période
$pdf->SetFont('CenturyGothic', '', 10);
$periodeText = 'Période : ' . date('d/m/Y', strtotime($dateDebut)) . ' au ' . date('d/m/Y', strtotime($dateFin));
$pdf->Cell(0, 5, pdf_text($periodeText), 0, 1, 'C');
$pdf->Cell(0, 5, pdf_text('Édition du ' . date('d/m/Y H:i')), 0, 1, 'C');
$pdf->Ln(4);

// Statistiques Sexe
$totalPatients = count($patientIds);
$totalMale = (int)$sexCounts['Masculin'];
$totalFemale = (int)$sexCounts['Feminin'];
$percentMale = $totalPatients > 0 ? round(($totalMale / $totalPatients) * 100, 1) : 0;
$percentFemale = $totalPatients > 0 ? round(($totalFemale / $totalPatients) * 100, 1) : 0;

$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->Cell(0, 6, pdf_text('RÉPARTITION PAR SEXE'), 0, 1, 'L');
$pdf->SetFont('CenturyGothic', '', 10);

$pdf->Cell(60, 5, pdf_text('Patients Masculin :'), 0, 0, 'L');
$pdf->Cell(0, 5, pdf_text($totalMale . ' (' . $percentMale . '%)'), 0, 1, 'L');

$pdf->Cell(60, 5, pdf_text('Patients Feminin :'), 0, 0, 'L');
$pdf->Cell(0, 5, pdf_text($totalFemale . ' (' . $percentFemale . '%)'), 0, 1, 'L');

$pdf->Ln(4);

// Tableau des prestations
if (!empty($typeLabels)) {
    $pdf->SetFont('CenturyGothic', 'B', 11);
    $pdf->Cell(0, 6, pdf_text('PRESTATIONS PAR TYPE'), 0, 1, 'L');
    $pdf->Ln(2);
    
    $pdf->SetFont('CenturyGothic', 'B', 9);
    $pdf->SetFillColor(15, 23, 42);
    $pdf->SetTextColor(255, 255, 255);

    // Largeurs des colonnes (ajustées pour A4)
    $wLabel = 85;
    $wMale = 21;
    $wFemale = 21;
    $wTotal = 21;
    $wPctMale = 21;
    $wPctFemale = 21;

    // En-têtes du tableau
    $pdf->Cell($wLabel, 5, pdf_text('Type de Prestation'), 1, 0, 'L', true);
    $pdf->Cell($wMale, 5, pdf_text('Masculin'), 1, 0, 'C', true);
    $pdf->Cell($wFemale, 5, pdf_text('Feminin'), 1, 0, 'C', true);
    $pdf->Cell($wTotal, 5, pdf_text('Total'), 1, 0, 'C', true);
    $pdf->Cell($wPctMale, 5, pdf_text('%M'), 1, 0, 'C', true);
    $pdf->Cell($wPctFemale, 5, pdf_text('%F'), 1, 1, 'C', true);

    $pdf->SetFont('CenturyGothic', '', 8);
    $pdf->SetTextColor(0, 0, 0);

    // Totaux pour la ligne de fin
    $totalMaleGlobal = 0;
    $totalFemaleGlobal = 0;
    $totalPatientsGlobal = 0;

    // Boucle sur les prestations
    arsort($typeCounts);
    foreach ($typeCounts as $idType => $count) {
        $label = (int)$idType > 0 ? trim((string)model((int)$idType)) : 'Type non renseigné';
        if ($label === '') {
            $label = 'Prestation #' . (int)$idType;
        }

        $maleCount = (int)($typeGenderCounts[(int)$idType]['male'] ?? 0);
        $femaleCount = (int)($typeGenderCounts[(int)$idType]['female'] ?? 0);
        $totalTypePatients = $maleCount + $femaleCount;
        
        $pctMale = $totalTypePatients > 0 ? round(($maleCount / $totalTypePatients) * 100, 1) : 0;
        $pctFemale = $totalTypePatients > 0 ? round(($femaleCount / $totalTypePatients) * 100, 1) : 0;

        // Accumul pour total
        $totalMaleGlobal += $maleCount;
        $totalFemaleGlobal += $femaleCount;
        $totalPatientsGlobal += $totalTypePatients;

        // Position de départ
        $x = $pdf->GetX();
        $y = $pdf->GetY();

        // Libellé multi-ligne
        $pdf->MultiCell($wLabel, 5, pdf_text(mb_substr($label, 0, 100)), 1, 'L');

        // Revenir à la ligne du libellé pour les données
        $pdf->SetXY($x + $wLabel, $y);
        $pdf->Cell($wMale, 5, pdf_text((string)$maleCount), 1, 0, 'C');
        $pdf->Cell($wFemale, 5, pdf_text((string)$femaleCount), 1, 0, 'C');
        $pdf->Cell($wTotal, 5, pdf_text((string)$totalTypePatients), 1, 0, 'C');
        $pdf->Cell($wPctMale, 5, pdf_text($pctMale . '%'), 1, 0, 'C');
        $pdf->Cell($wPctFemale, 5, pdf_text($pctFemale . '%'), 1, 1, 'C');
    }

    // Ligne Total
    $pdf->SetFont('CenturyGothic', 'B', 9);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->SetTextColor(0, 0, 0);

    $totalPctMale = $totalPatientsGlobal > 0 ? round(($totalMaleGlobal / $totalPatientsGlobal) * 100, 1) : 0;
    $totalPctFemale = $totalPatientsGlobal > 0 ? round(($totalFemaleGlobal / $totalPatientsGlobal) * 100, 1) : 0;

    $pdf->Cell($wLabel, 5, pdf_text('TOTAL'), 1, 0, 'L', true);
    $pdf->Cell($wMale, 5, pdf_text((string)$totalMaleGlobal), 1, 0, 'C', true);
    $pdf->Cell($wFemale, 5, pdf_text((string)$totalFemaleGlobal), 1, 0, 'C', true);
    $pdf->Cell($wTotal, 5, pdf_text((string)$totalPatientsGlobal), 1, 0, 'C', true);
    $pdf->Cell($wPctMale, 5, pdf_text($totalPctMale . '%'), 1, 0, 'C', true);
    $pdf->Cell($wPctFemale, 5, pdf_text($totalPctFemale . '%'), 1, 1, 'C', true);
}

// Sauvegarder le fichier côté serveur et envoyer inline pour affichage dans modal
//$serverPath = __DIR__ . '/_statistique_clinique.pdf';
$displayName = '_statistique_clinique.pdf';
// Écrire le fichier sur le serveur
$pdf->Output();
// Envoyer inline au navigateur pour rendu dans l'iframe du modal
$pdf->Output('I', $displayName);
?>
