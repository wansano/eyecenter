<?php
require_once('../PDF/fpdf.php');
require_once('../PDF/font/CenturyGothic.php');
require_once('../PDF/html_table13.php');
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

function safeDateFr($dateStr) {
    $dt = DateTime::createFromFormat('Y-m-d', $dateStr);
    if (!$dt) {
        try { $dt = new DateTime($dateStr); } catch (Throwable $e) { return (string)$dateStr; }
    }
    $mois = [
        '01'=>'janvier','02'=>'février','03'=>'mars','04'=>'avril','05'=>'mai','06'=>'juin',
        '07'=>'juillet','08'=>'août','09'=>'septembre','10'=>'octobre','11'=>'novembre','12'=>'décembre'
    ];
    $m = $dt->format('m');
    return $dt->format('d') . ' ' . ($mois[$m] ?? $m) . ' ' . $dt->format('Y');
}

function normalize_user_type($type): string {
    return strtolower(trim((string)$type));
}

function is_medical_service(int $serviceId): bool {
    return in_array($serviceId, [1, 2, 3, 4], true);
}

function is_cashier_service(int $serviceId): bool {
    return $serviceId === 8;
}

function is_secretary_service(int $serviceId): bool {
    return $serviceId === 7;
}

function table_has_column(PDO $bdd, string $table, string $column): bool {
    try {
        $bdd->query('SELECT `' . str_replace('`', '', $column) . '` FROM `' . str_replace('`', '', $table) . '` LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function resolve_first_existing_column(PDO $bdd, string $table, array $candidates): ?string {
    foreach ($candidates as $col) {
        if (table_has_column($bdd, $table, $col)) {
            return $col;
        }
    }
    return null;
}

function get_user_row(PDO $bdd, int $userId): ?array {
    $stmt = $bdd->prepare('SELECT id, pseudo, email, type, id_service, status, date_engagement FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function get_secretary_stats(PDO $bdd, int $userId, string $dateDebut, string $dateFin): array {
    $out = [
        'nb_affectations' => 0,
        'nb_patients_affectes' => 0,
        'nb_affectations_ayant_paye' => 0,
    ];

    $stmt = $bdd->prepare('SELECT COUNT(*) FROM affectations WHERE affecte_par = ? AND DATE(date) BETWEEN ? AND ?');
    $stmt->execute([$userId, $dateDebut, $dateFin]);
    $out['nb_affectations'] = (int)($stmt->fetchColumn() ?: 0);

    $stmt = $bdd->prepare('SELECT COUNT(DISTINCT id_patient) FROM affectations WHERE affecte_par = ? AND DATE(date) BETWEEN ? AND ?');
    $stmt->execute([$userId, $dateDebut, $dateFin]);
    $out['nb_patients_affectes'] = (int)($stmt->fetchColumn() ?: 0);

    $sql = '
        SELECT COUNT(DISTINCT a.id_affectation)
        FROM affectations a
        JOIN paiements p ON p.id_affectation = a.id_affectation
        WHERE a.affecte_par = ?
          AND p.remboursement = 0
          AND DATE(p.datepaiement) BETWEEN ? AND ?
    ';
    $stmt = $bdd->prepare($sql);
    $stmt->execute([$userId, $dateDebut, $dateFin]);
    $out['nb_affectations_ayant_paye'] = (int)($stmt->fetchColumn() ?: 0);

    return $out;
}

function get_user_org_info(PDO $bdd, int $userId): array {
    $out = ['departement' => '', 'celulle' => ''];

    $userServiceCol = resolve_first_existing_column($bdd, 'users', ['id_service', 'id_organigramme', 'service']);
    if ($userServiceCol === null) {
        return $out;
    }

    $stmt = $bdd->prepare('SELECT ' . $userServiceCol . ' FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $serviceId = (int)($stmt->fetchColumn() ?: 0);
    if ($serviceId <= 0) {
        return $out;
    }

    $hasDepartement = table_has_column($bdd, 'organigramme', 'departement');
    $cellCol = resolve_first_existing_column($bdd, 'organigramme', ['celulle', 'cellule']);
    if ($cellCol === null) {
        return $out;
    }

    $cols = ($hasDepartement ? 'departement,' : '') . $cellCol;
    $stmt = $bdd->prepare('SELECT ' . $cols . ' FROM organigramme WHERE id_organigramme = ? LIMIT 1');
    $stmt->execute([$serviceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return $out;
    }

    if ($hasDepartement) {
        $out['departement'] = trim((string)($row['departement'] ?? ''));
    }
    $out['celulle'] = trim((string)($row[$cellCol] ?? ''));

    return $out;
}

function get_medical_realisations(PDO $bdd, int $userId, string $dateDebut, string $dateFin): array {
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

        $params[$userParam] = $userId;
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
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_cashier_stats(PDO $bdd, int $userId, string $dateDebut, string $dateFin): array {
    $out = [
        'nb_paiements' => 0,
        'montant_paiements' => 0.0,
        'montant_global_paiements' => 0.0,
        'nb_preuves' => 0,
        'montant_preuves' => 0.0,
    ];

    $stmt = $bdd->prepare('SELECT COUNT(*), COALESCE(SUM(montant_paye), 0) FROM paiements WHERE caisse = ? AND remboursement = 0 AND DATE(datepaiement) BETWEEN ? AND ?');
    $stmt->execute([$userId, $dateDebut, $dateFin]);
    $row = $stmt->fetch(PDO::FETCH_NUM);
    if ($row) {
        $out['nb_paiements'] = (int)($row[0] ?? 0);
        $out['montant_paiements'] = (float)($row[1] ?? 0);
    }

    $stmt = $bdd->prepare('SELECT COALESCE(SUM(montant_paye), 0) FROM paiements WHERE remboursement = 0 AND DATE(datepaiement) BETWEEN ? AND ?');
    $stmt->execute([$dateDebut, $dateFin]);
    $out['montant_global_paiements'] = (float)($stmt->fetchColumn() ?: 0);

    $stmt = $bdd->prepare('SELECT COUNT(*), COALESCE(SUM(montant), 0) FROM preuvedecaisse WHERE id_user = ? AND DATE(date_rapportement) BETWEEN ? AND ?');
    $stmt->execute([$userId, $dateDebut, $dateFin]);
    $row = $stmt->fetch(PDO::FETCH_NUM);
    if ($row) {
        $out['nb_preuves'] = (int)($row[0] ?? 0);
        $out['montant_preuves'] = (float)($row[1] ?? 0);
    }

    return $out;
}

try {
    if (!isset($_GET['employe'], $_GET['debut'], $_GET['fin'])) {
        throw new Exception('Paramètres manquants');
    }

    $userId = (int)$_GET['employe'];
    $dateDebut = (string)$_GET['debut'];
    $dateFin = (string)$_GET['fin'];

    if ($userId <= 0) {
        throw new Exception('Employé invalide');
    }

    $user = get_user_row($bdd, $userId);
    if (!$user) {
        throw new Exception('Employé introuvable');
    }

    $org = get_user_org_info($bdd, $userId);

    $role = 'other';
    $medicalRows = [];
    $cash = null;
    $sec = null;

    $serviceIdUser = (int)($user['id_service'] ?? 0);
    if (is_medical_service($serviceIdUser)) {
        $role = 'medical';
        $medicalRows = get_medical_realisations($bdd, $userId, $dateDebut, $dateFin);
    } elseif (is_cashier_service($serviceIdUser)) {
        $role = 'cashier';
        $cash = get_cashier_stats($bdd, $userId, $dateDebut, $dateFin);
    } elseif (is_secretary_service($serviceIdUser)) {
        $role = 'secretary';
        $sec = get_secretary_stats($bdd, $userId, $dateDebut, $dateFin);
    }

    $pdf = new PDF('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->AddFont('CenturyGothic','', 'CenturyGothic.php');
    $pdf->AddFont('CenturyGothic','B','CenturyGothic_bold.php');
    $pdf->SetAutoPageBreak(true, -15);

    $profil = $bdd->prepare('SELECT * FROM profil_entreprise LIMIT 1');
    $profil->execute();
    $dataProfil = $profil->fetch(PDO::FETCH_ASSOC);
    if ($dataProfil) {
        genererEntete($pdf, $dataProfil);
    }

    $pdf->Ln(5);
    $pdf->SetFont('CenturyGothic', 'B', 12);
    $pdf->Cell(0, 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'RAPPORT - REALISATION INDIVIDUELLE'), 0, 1, 'C');
    $pdf->Ln(4);

    $pdf->SetFont('CenturyGothic', '', 11);
    $pdf->Cell(0, 6, pdf_text_compat('Employé : ' . ((string)($user['pseudo'] ?? ''))), 0, 1, 'L');
    $pdf->Cell(0, 6, pdf_text_compat('Email : ' . ((string)($user['email'] ?? ''))), 0, 1, 'L');
    $pdf->Cell(0, 6, pdf_text_compat('Département : ' . ($org['departement'] !== '' ? $org['departement'] : '-')), 0, 1, 'L');
    $pdf->Cell(0, 6, pdf_text_compat('Cellule : ' . ($org['celulle'] !== '' ? $org['celulle'] : '-')), 0, 1, 'L');
    $pdf->Cell(0, 6, pdf_text_compat("Date d'engagement : " . ((string)($user['date_engagement'] ?? ''))), 0, 1, 'L');
    $pdf->Cell(0, 6, pdf_text_compat('Période : Du ' . safeDateFr($dateDebut) . ' au ' . safeDateFr($dateFin)), 0, 1, 'L');
    $pdf->Ln(4);

    if ($role === 'medical') {
        $pdf->SetFont('CenturyGothic', 'B', 11);
        $pdf->SetFillColor(0,102,204);
        $pdf->SetTextColor(255,255,255);
        $wType = 150;
        $wNb = 40;
        $pdf->Cell($wType, 8, pdf_text_compat('Type de prestation'), 1, 0, 'C', true);
        $pdf->Cell($wNb, 8, pdf_text_compat('Nombre'), 1, 1, 'C', true);

        $pdf->SetFont('CenturyGothic', '', 11);
        $pdf->SetTextColor(0,0,0);

        if (empty($medicalRows)) {
            $pdf->Cell(0, 7, pdf_text_compat('Aucune réalisation trouvée sur la période.'), 1, 1, 'L');
        } else {
            $totalGeneral = 0;
            foreach ($medicalRows as $r) {
                $nb = (int)($r['total'] ?? 0);
                $totalGeneral += $nb;
                $pdf->Cell($wType, 7, pdf_text_compat((string)($r['nom_type'] ?? '')), 1, 0, 'L');
                $pdf->Cell($wNb, 7, pdf_text_compat((string)$nb), 1, 1, 'C');
            }
            $pdf->SetFont('CenturyGothic','B',11);
            $pdf->Cell($wType, 8, pdf_text_compat('TOTAL GENERAL'), 1, 0, 'R');
            $pdf->Cell($wNb, 8, pdf_text_compat((string)$totalGeneral), 1, 1, 'C');
        }
    } elseif ($role === 'cashier') {
        $pdf->SetFont('CenturyGothic', 'B', 11);
        $pdf->Cell(0, 6, pdf_text_compat('Performance (caisse)'), 0, 1, 'L');
        $pdf->Ln(2);

        $pdf->SetFont('CenturyGothic', '', 11);
        $pdf->Cell(0, 6, pdf_text_compat('Paiements validés : ' . (string)($cash['nb_paiements'] ?? 0)), 0, 1, 'L');
        $pdf->Cell(0, 6, pdf_text_compat('Preuves de caisse : ' . (string)($cash['nb_preuves'] ?? 0)), 0, 1, 'L');
        $pdf->Cell(0, 6, pdf_text_compat('Montant preuves de caisse : ' . number_format((float)($cash['montant_preuves'] ?? 0), 0, ',', ' ')), 0, 1, 'L');
        $pdf->Cell(0, 6, pdf_text_compat('Montant validé (employé) : ' . number_format((float)($cash['montant_paiements'] ?? 0), 0, ',', ' ')), 0, 1, 'L');
        $pdf->Cell(0, 6, pdf_text_compat('Montant validé (global) : ' . number_format((float)($cash['montant_global_paiements'] ?? 0), 0, ',', ' ')), 0, 1, 'L');
    } elseif ($role === 'secretary') {
        $pdf->SetFont('CenturyGothic', 'B', 11);
        $pdf->Cell(0, 6, pdf_text_compat('Performance (secrétariat)'), 0, 1, 'L');
        $pdf->Ln(2);
        $pdf->SetFont('CenturyGothic', '', 11);
        $pdf->Cell(0, 6, pdf_text_compat('Affectations réalisées : ' . (string)($sec['nb_affectations'] ?? 0)), 0, 1, 'L');
        $pdf->Cell(0, 6, pdf_text_compat('Patients affectés : ' . (string)($sec['nb_patients_affectes'] ?? 0)), 0, 1, 'L');
        $pdf->Cell(0, 6, pdf_text_compat('Affectations ayant payé : ' . (string)($sec['nb_affectations_ayant_paye'] ?? 0)), 0, 1, 'L');
    } else {
        $pdf->SetFont('CenturyGothic', '', 11);
        $pdf->MultiCell(0, 6, pdf_text_compat('Aucun indicateur défini pour ce profil.'), 1, 'L');
    }

    $pdf->Ln(6);
    $pdf->SetFont('CenturyGothic', '', 8);
    $printedBy = isset($_SESSION['auth']) ? (string)traitant((int)$_SESSION['auth']) : '';
    $footer = 'Imprimé le ' . date('d/m/Y') . ($printedBy !== '' ? (' par ' . $printedBy) : '');
    $pdf->Cell(0, 6, pdf_text_compat($footer), 0, 0, 'R');

    $filename = 'realisation_individuelle_' . $userId . '_' . $dateDebut . '_' . $dateFin . '.pdf';
    $pdf->Output($filename, 'I');

} catch (Exception $e) {
    error_log('Erreur PDF realisation individuelle: ' . $e->getMessage());
    die('Une erreur est survenue lors de la génération du document');
}
