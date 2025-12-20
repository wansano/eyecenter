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

function renderRdvTable(PDF $pdf, string $title, array $rows): void {
    $pdf->SetFont('CenturyGothic', 'B', 11);
    $pdf->Cell(0, 7, pdf_text($title.' ('.count($rows).')'), 0, 1, 'L');
    $pdf->Ln(1);

    if (empty($rows)) {
        $pdf->SetFont('CenturyGothic', '', 10);
        $pdf->Cell(0, 6, pdf_text('Aucun.'), 0, 1, 'L');
        $pdf->Ln(2);
        return;
    }

    // En-têtes : 20 | 20 | 90 | 60 = 190mm
    $pdf->SetFont('CenturyGothic', 'B', 10);
    $pdf->Cell(20, 7, pdf_text('Heure'), 1, 0, 'C');
    $pdf->Cell(20, 7, pdf_text('PAT-N°'), 1, 0, 'C');
    $pdf->Cell(90, 7, pdf_text('Patient'), 1, 0, 'C');
    $pdf->Cell(60, 7, pdf_text('Motif'), 1, 1, 'C');

    $pdf->SetFont('CenturyGothic', '', 9);
    foreach ($rows as $r) {
        $heure = isset($r['prochain_rdv']) ? substr((string)$r['prochain_rdv'], 11, 5) : '';
        $dossier = (string)($r['id_patient'] ?? '');
        $nom = nom_patient($r['id_patient'] ?? null);
        $motif = '';
        if (isset($r['motif'])) {
            // type_traitement() est utilisé dans d'autres impressions
            $motif = type_traitement($r['motif']);
        }

        $pdf->Cell(20, 6, pdf_text($heure), 1, 0, 'C');
        $pdf->Cell(20, 6, pdf_text($dossier), 1, 0, 'C');
        $pdf->Cell(90, 6, pdf_text(mb_strimwidth((string)$nom, 0, 60, '…', 'UTF-8')), 1, 0, 'L');
        $pdf->Cell(60, 6, pdf_text(mb_strimwidth((string)$motif, 0, 40, '…', 'UTF-8')), 1, 1, 'L');
    }
    $pdf->Ln(4);
}

function renderStatCards(PDF $pdf, array $items): void {
    // Grille 5 colonnes (A4 portrait: 190mm utiles par défaut)
    if (empty($items)) {
        return;
    }

    $cols = 5;
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
    $stmtList = $bdd->prepare('SELECT id_rdv, id_patient, prochain_rdv, motif, traitant, status
        FROM dmd_rendez_vous
        WHERE DATE(prochain_rdv) = :d AND status IN (0,1,2)
        ORDER BY prochain_rdv');
    $stmtList->execute(['d' => $date]);
    $allRdvs = $stmtList->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $bdd->prepare('SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status IN (1,2) THEN 1 ELSE 0 END) AS present,
        SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) AS absent,
        SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS paye,
        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS non_paye
        FROM dmd_rendez_vous
        WHERE DATE(prochain_rdv) = :d AND status IN (0,1,2)'
    );
    $stmt->execute(['d' => $date]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $total = (int)($row['total'] ?? 0);
    $present = (int)($row['present'] ?? 0);
    $absent = (int)($row['absent'] ?? 0);
    $paye = (int)($row['paye'] ?? 0);
    $nonPaye = (int)($row['non_paye'] ?? 0);

    $pdf = new PDF('P','mm','A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->AddFont('CenturyGothic', '', 'CenturyGothic.php');
    $pdf->AddFont('CenturyGothic','B','CenturyGothic_bold.php');
    $pdf->SetAutoPageBreak(true, -15);

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
    $pdf->Cell(0,7,pdf_text('STATISTIQUES'),0,1,'L');
    $pdf->Ln(1);

    renderStatCards($pdf, [
        ['label' => 'Total RDV', 'value' => $total],
        ['label' => 'Présents', 'value' => $present],
        ['label' => 'Absents', 'value' => $absent],
        ['label' => 'Ont payé', 'value' => $paye],
        ['label' => 'N\'ont pas payé', 'value' => $nonPaye],
    ]);

    // Tableaux détaillés
    $presents = [];
    $absents = [];
    $payeRows = [];
    $nonPayeRows = [];
    foreach ($allRdvs as $r) {
        $status = (int)($r['status'] ?? -1);
        if ($status === 0) {
            $absents[] = $r;
        }
        if ($status === 1 || $status === 2) {
            $presents[] = $r;
        }
        if ($status === 2) {
            $payeRows[] = $r;
        }
        if ($status === 1) {
            $nonPayeRows[] = $r;
        }
    }

    $pdf->Ln(6);
    renderRdvTable($pdf, 'LISTE DES PRÉSENTS', $presents);
    renderRdvTable($pdf, 'LISTE DES ABSENTS', $absents);
    renderRdvTable($pdf, 'LISTE DE CEUX QUI ONT PAYÉ', $payeRows);
    renderRdvTable($pdf, 'LISTE DE CEUX QUI N\'ONT PAS PAYÉ', $nonPayeRows);

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
