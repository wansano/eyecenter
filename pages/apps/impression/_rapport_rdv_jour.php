<?php
require_once('../PDF/fpdf.php');
require_once('../PDF/font/CenturyGothic.php');
require_once('../PDF/html_table13.php');
require_once('../public/connect.php');
require_once('../public/fonction.php');
session_start();

function formatPhoneDisplayRapportRdv($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') {
        return '';
    }

    $parts = preg_split('/[;,\|\/]+/', $raw);
    if (is_array($parts) && isset($parts[0])) {
        $raw = trim((string)$parts[0]);
    }

    $digits = preg_replace('/\D+/', '', $raw);
    $digits = (string)$digits;
    if ($digits === '') {
        return '';
    }

    if (substr($digits, 0, 5) === '00224') {
        $digits = substr($digits, 5);
    } elseif (substr($digits, 0, 3) === '224' && strlen($digits) > 9) {
        $digits = substr($digits, 3);
    }

    if (strlen($digits) > 9) {
        $digits = substr($digits, -9);
    }

    if (strlen($digits) === 9) {
        return substr($digits, 0, 3) . ' ' . substr($digits, 3, 2) . ' ' . substr($digits, 5, 2) . ' ' . substr($digits, 7, 2);
    }

    return trim(chunk_split($digits, 2, ' '));
}

function resolveRdvPatientDisplay(PDO $bdd, array $r): array {
    $idPatient = (int)($r['id_patient'] ?? 0);
    $idDemande = (int)($r['id_demande'] ?? 0);

    $dossier = '';
    $nom = '';

    if ($idPatient > 0) {
        $dossier = (string)$idPatient;
        $nom = (string)nom_patient($idPatient);
        if (trim($nom) === '' && $idDemande > 0) {
            $demande = getDemandeEnAttenteById($bdd, $idDemande);
            $nm = (string)($demande['nom_patient'] ?? '');
            $nom = trim($nm) !== '' ? ($nm . ' (attente)') : 'Externe en attente';
        }
        if (trim($nom) === '') {
            $nom = 'Patient #' . $dossier;
        }
        return ['dossier' => $dossier, 'nom' => $nom];
    }

    if ($idDemande > 0) {
        $dossier = 'DEM-' . (string)$idDemande;
        $demande = getDemandeEnAttenteById($bdd, $idDemande);
        $nm = (string)($demande['nom_patient'] ?? '');
        $nom = trim($nm) !== '' ? ($nm . ' (attente)') : 'Externe en attente';
        return ['dossier' => $dossier, 'nom' => $nom];
    }

    return ['dossier' => '', 'nom' => '—'];
}

function renderRdvTable(PDO $bdd, PDF $pdf, string $title, array $rows): void {
    $pdf->SetFont('CenturyGothic', 'B', 11);
    $pdf->Cell(0, 7, pdf_text($title.' ('.count($rows).')'), 0, 1, 'L');
    $pdf->Ln(1);

    if (empty($rows)) {
        $pdf->SetFont('CenturyGothic', '', 10);
        $pdf->Cell(0, 6, pdf_text('Aucun.'), 0, 1, 'L');
        $pdf->Ln(2);
        return;
    }

    // En-têtes : 18 | 18 | 70 | 44 | 40 = 190mm
    $pdf->SetFont('CenturyGothic', 'B', 10);
    // Couleur fine (gris clair) sur les en-têtes
    $pdf->SetFillColor(242, 242, 242);
    $pdf->Cell(18, 7, pdf_text('Heure'), 1, 0, 'C', true);
    $pdf->Cell(18, 7, pdf_text('PAT-N°'), 1, 0, 'C', true);
    $pdf->Cell(65, 7, pdf_text('Patient'), 1, 0, 'C', true);
    $pdf->Cell(50, 7, pdf_text('Médecin'), 1, 0, 'C', true);
    $pdf->Cell(40, 7, pdf_text('Motif'), 1, 1, 'C', true);

    $pdf->SetFont('CenturyGothic', '', 9);
    foreach ($rows as $r) {
        $heure = isset($r['prochain_rdv']) ? substr((string)$r['prochain_rdv'], 11, 5) : '';
        $patient = resolveRdvPatientDisplay($bdd, $r);
        $dossier = (string)($patient['dossier'] ?? '');
        $nom = (string)($patient['nom'] ?? '');
        $medecin = '';
        if (isset($r['traitant']) && $r['traitant'] !== null && $r['traitant'] !== '') {
            $medecin = traitant($r['traitant']);
        }
        $motif = '';
        if (isset($r['motif'])) {
            // type_traitement() est utilisé dans d'autres impressions
            $motif = type_traitement($r['motif']);
        }

        $pdf->Cell(18, 6, pdf_text($heure), 1, 0, 'C');
        $pdf->Cell(18, 6, pdf_text($dossier), 1, 0, 'C');
        $pdf->Cell(65, 6, pdf_text(mb_strimwidth((string)$nom, 0, 48, '…', 'UTF-8')), 1, 0, 'L');
        $pdf->Cell(50, 6, pdf_text(mb_strimwidth((string)$medecin, 0, 32, '…', 'UTF-8')), 1, 0, 'L');
        $pdf->Cell(40, 6, pdf_text(mb_strimwidth((string)$motif, 0, 28, '…', 'UTF-8')), 1, 1, 'L');
    }
    $pdf->Ln(4);
}

function renderStatCards(PDF $pdf, array $items): void {
    // Grille 5 colonnes (A4 portrait: 190mm utiles par défaut)
    if (empty($items)) {
        return;
    }

    $count = count($items);
    // 5 items -> 1 ligne de 5, 6+ -> 2 lignes de 3 (plus lisible)
    $cols = ($count <= 5) ? $count : 3;
    $cellW = 190 / $cols;
    // Plus haut pour que les libellés longs (ex: "N'ont pas payé") tiennent sur 2 lignes
    $labelH = 7;
    $valueH = 7;
    $cellH = $labelH + $valueH;

    $i = 0;
    foreach ($items as $it) {
        $col = $i % $cols;
        if ($col === 0 && $i > 0) {
            $pdf->Ln($cellH);
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();

        // Couleur fine sur l'en-tête (zone label)
        $pdf->SetFillColor(242, 242, 242);
        $pdf->Rect($x, $y, $cellW, $labelH, 'F');

        // Bordure cellule
        $pdf->Rect($x, $y, $cellW, $cellH);
        // Séparateur entre label et valeur
        $pdf->Line($x, $y + $labelH, $x + $cellW, $y + $labelH);

        // Label (haut)
        $pdf->SetFont('CenturyGothic', 'B', 8);
        $pdf->SetXY($x, $y + 1);
        $pdf->MultiCell($cellW, 4, pdf_text((string)($it['label'] ?? '')), 0, 'C');

        // Valeur (bas)
        $pdf->SetXY($x, $y + $labelH);
        $pdf->SetFont('CenturyGothic', 'B', 13);
        $pdf->Cell($cellW, $valueH, pdf_text((string)($it['value'] ?? '0')), 0, 0, 'C');

        // Avancer à la colonne suivante
        $pdf->SetXY($x + $cellW, $y);
        $i++;
    }

    // Se placer sous la grille (même si la dernière ligne est complète)
    $pdf->Ln($cellH);
}

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Sécurité basique: format YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date)) {
    $date = date('Y-m-d');
}

try {
    // Détails RDV du jour
    $stmtList = $bdd->prepare('SELECT id_rdv, id_patient, id_demande, prochain_rdv, motif, traitant, status
        FROM dmd_rendez_vous
        WHERE DATE(prochain_rdv) = :d AND status IN (0,1,2,4)
        ORDER BY prochain_rdv');
    $stmtList->execute(['d' => $date]);
    $allRdvs = $stmtList->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $bdd->prepare('SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status IN (1,2,4) THEN 1 ELSE 0 END) AS present,
        SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) AS absent,
        SUM(CASE WHEN status IN (2,4) THEN 1 ELSE 0 END) AS paye,
        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS non_paye,
        SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) AS vu
        FROM dmd_rendez_vous
        WHERE DATE(prochain_rdv) = :d AND status IN (0,1,2,4)'
    );
    $stmt->execute(['d' => $date]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $total = (int)($row['total'] ?? 0);
    $present = (int)($row['present'] ?? 0);
    $absent = (int)($row['absent'] ?? 0);
    $paye = (int)($row['paye'] ?? 0);
    $nonPaye = (int)($row['non_paye'] ?? 0);
    $vu = (int)($row['vu'] ?? 0);

    $pdf = new PDF('P','mm','A4');
    $pdf->AliasNbPages();
    // Marges + saut de page automatique: évite que les dernières lignes soient coupées en bas
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();
    $pdf->AddFont('CenturyGothic', '', 'CenturyGothic.php');
    $pdf->AddFont('CenturyGothic','B','CenturyGothic_bold.php');

    // Entête entreprise
    $profil = $bdd->prepare('SELECT * FROM profil_entreprise LIMIT 1');
    $profil->execute();
    if ($dataProfil = $profil->fetch(PDO::FETCH_ASSOC)) {
        genererEntete($pdf, $dataProfil, 12);
    }

    $pdf->SetFont('CenturyGothic','B',13);
    $titreDate = dateEnFrancais($date);
    $pdf->Cell(0,8,pdf_text('RAPPORT DES RENDEZ-VOUS DU '.strtoupper($titreDate)),0,1,'C');
    $pdf->Ln(6);

    // Tableau récapitulatif des statistiques
    $pdf->SetFont('CenturyGothic','B',11);
    $pdf->SetFillColor(242, 242, 242);
    $pdf->Cell(0,7,pdf_text('STATISTIQUES'),0,1,'L', true);
    $pdf->Ln(1);

    renderStatCards($pdf, [
        ['label' => 'Total RDV', 'value' => $total],
        ['label' => 'Etaient Présents', 'value' => $present],
        ['label' => 'Etaient Absents', 'value' => $absent],
        ['label' => 'Ont payé', 'value' => $paye],
        ['label' => 'N\'ont pas payé', 'value' => $nonPaye],
        ['label' => 'Etaient vus par le médecin', 'value' => $vu],
    ]);

    // Tableaux détaillés
    $presents = [];
    $absents = [];
    $payeRows = [];
    $nonPayeRows = [];
    $vusRows = [];
    foreach ($allRdvs as $r) {
        $status = (int)($r['status'] ?? -1);
        if ($status === 0) {
            $absents[] = $r;
        }
        if ($status === 1 || $status === 2 || $status === 4) {
            $presents[] = $r;
        }
        if ($status === 2 || $status === 4) {
            $payeRows[] = $r;
        }
        if ($status === 1) {
            $nonPayeRows[] = $r;
        }
        if ($status === 4) {
            $vusRows[] = $r;
        }
    }

    $pdf->Ln(6);
    renderRdvTable($bdd, $pdf, 'LISTE DES PRÉSENTS', $presents);
    renderRdvTable($bdd, $pdf, 'LISTE DES ABSENTS', $absents);
    renderRdvTable($bdd, $pdf, 'LISTE DE CEUX QUI ONT PAYÉ', $payeRows);
    renderRdvTable($bdd, $pdf, 'LISTE DE CEUX QUI N\'ONT PAS PAYÉ', $nonPayeRows);
    renderRdvTable($bdd, $pdf, 'LISTE DE CEUX QUI ONT ÉTÉ VUS PAR LE MÉDECIN', $vusRows);

    $pdf->Ln(10);
    $pdf->SetFont('CenturyGothic','',8);
    $pdf->Cell(0,5,pdf_text('Généré le '.date('d/m/Y H:i').' par '.(isset($_SESSION['auth']) ? traitant($_SESSION['auth']) : 'Système')),0,1,'R');

    $pdf->Output('I','rapport_rdv_'.$date.'.pdf');
    exit;

} catch (Exception $e) {
    error_log('Erreur rapport_rdv_jour: '.$e->getMessage());
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Erreur: '.$e->getMessage();
    exit;
}
