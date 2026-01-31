<?php
require_once('../PDF/fpdf.php');
require_once('../PDF/font/CenturyGothic.php');
require_once('../PDF/html_table13.php');
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');

session_start();

function tableExists(PDO $bdd, string $table): bool
{
    try {
        $st = $bdd->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);
        return (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function getEmployeNomCol(PDO $bdd): string
{
    try {
        $st = $bdd->query('SHOW COLUMNS FROM employes');
        if ($st) {
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $field = (string) ($r['Field'] ?? '');
                if ($field === 'nomEmploye' || $field === 'nom_employe') {
                    return $field;
                }
            }
        }
    } catch (Throwable $e) {
    }
    return 'nomEmploye';
}

function money(float $amount): string
{
    return number_format($amount, 0, '', ' ');
}

function monthLabelFr(int $m): string
{
    $months = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
        7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
    ];
    return $months[$m] ?? (string) $m;
}

try {
    if (!tableExists($bdd, 'bulletins_salaire')) {
        throw new Exception('La table bulletins_salaire est introuvable.');
    }

    $currentYear = (int) date('Y');
    $currentMonth = (int) date('n');

    $year = isset($_GET['annee']) ? (int) $_GET['annee'] : 0;
    $month = isset($_GET['mois_num']) ? (int) $_GET['mois_num'] : 0;

    $periodeMonth = (string) ($_GET['periode_month'] ?? '');
    if (($year <= 0 || $month <= 0) && preg_match('/^(\d{4})-(\d{2})$/', $periodeMonth, $m)) {
        $year = (int) $m[1];
        $month = (int) $m[2];
    }

    if ($year <= 0) $year = $currentYear;
    if ($month <= 0) $month = $currentMonth;
    if ($month < 1) $month = 1;
    if ($month > 12) $month = 12;

    // Empêcher un mois futur
    if ($year === $currentYear && $month > $currentMonth) {
        $month = $currentMonth;
    }
    if ($year > $currentYear) {
        $year = $currentYear;
        $month = $currentMonth;
    }

    $periode = sprintf('%04d-%02d-01', $year, $month);

    $stProfil = $bdd->query('SELECT * FROM profil_entreprise LIMIT 1');
    $profil = $stProfil ? ($stProfil->fetch(PDO::FETCH_ASSOC) ?: []) : [];

    $devise = '';
    if (isset($profil['devise'])) {
        $devise = trim((string) $profil['devise']);
    }
    if ($devise === '') $devise = 'GNF';

    $nameCol = getEmployeNomCol($bdd);

    $sql = 'SELECT b.id_bulletin, b.numero, b.total_brut, b.rts, b.net_a_payer, b.paye, b.mode_reglement, b.date_paiement,
                   e.`' . $nameCol . '` AS employe_nom
            FROM bulletins_salaire b
            JOIN employes e ON e.id_employe = b.id_employe
            WHERE b.periode = ?
            ORDER BY e.`' . $nameCol . '` ASC';
    $st = $bdd->prepare($sql);
    $st->execute([$periode]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $totalBrut = 0.0;
    $totalNet = 0.0;
    $totalRts = 0.0;
    $countPaid = 0;
    $countUnpaid = 0;

    foreach ($rows as $r) {
        $tb = (float) ($r['total_brut'] ?? 0);
        $rt = (float) ($r['rts'] ?? 0);
        $nt = (float) ($r['net_a_payer'] ?? 0);
        $totalBrut += $tb;
        $totalRts += $rt;
        $totalNet += $nt;
        if ((int) ($r['paye'] ?? 0) === 1) $countPaid++; else $countUnpaid++;
    }

    $pdf = new PDF('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->AddFont('CenturyGothic', '', 'CenturyGothic.php');
    $pdf->AddFont('CenturyGothic', 'B', 'CenturyGothic_bold.php');
    $pdf->SetAutoPageBreak(true, 10);

    if (!empty($profil)) {
        genererEntete($pdf, $profil);
    }

    $pdf->SetFont('CenturyGothic', 'B', 13);
    $pdf->Cell(0, 6, pdf_text_compat('ETAT DES SALAIRES'), 0, 1, 'C');
    $pdf->Ln(2);

    $pdf->SetFont('CenturyGothic', '', 11);
    $title = 'Période : ' . monthLabelFr($month) . ' ' . $year . '    Devise : ' . $devise . '.';
    $pdf->Cell(0, 6, pdf_text_compat($title), 0, 1, 'C');
    $pdf->Ln(3);

    // Tableau
    // Largeurs ajustées pour tenir sur A4 portrait (marges incluses)
    $wNum = 24;
    $wEmp = 52;
    $wBrut = 22;
    $wRts = 18;
    $wNet = 22;
    $wStat = 18;
    $wDate = 20;
    $wMode = 26;

    // Décaler légèrement le tableau à gauche
    $tableX = 4;

    $pdf->SetFont('CenturyGothic', 'B', 10);
    $pdf->SetFillColor(0, 102, 204);
    $pdf->SetTextColor(255, 255, 255);

    $pdf->SetX($tableX);
    $pdf->Cell($wNum, 8, pdf_text_compat('N°'), 1, 0, 'C', true);
    $pdf->Cell($wEmp, 8, pdf_text_compat('Employé'), 1, 0, 'C', true);
    $pdf->Cell($wBrut, 8, pdf_text_compat('Brut'), 1, 0, 'C', true);
    $pdf->Cell($wRts, 8, pdf_text_compat('RTS'), 1, 0, 'C', true);
    $pdf->Cell($wNet, 8, pdf_text_compat('A payer'), 1, 0, 'C', true);
    $pdf->Cell($wStat, 8, pdf_text_compat('Statut'), 1, 0, 'C', true);
    $pdf->Cell($wDate, 8, pdf_text_compat('Date'), 1, 0, 'C', true);
    $pdf->Cell($wMode, 8, pdf_text_compat('Mode'), 1, 1, 'C', true);

    $pdf->SetFont('CenturyGothic', '', 10);
    $pdf->SetTextColor(0, 0, 0);

    if (empty($rows)) {
        $pdf->SetX($tableX);
        $pdf->Cell($wNum + $wEmp + $wBrut + $wRts + $wNet + $wStat + $wDate + $wMode, 10, pdf_text_compat('Aucun bulletin trouvé pour cette période.'), 1, 1, 'C');
    } else {
        foreach ($rows as $r) {
            $numero = (string) ($r['numero'] ?? ('BS-' . ($r['id_bulletin'] ?? '')));
            $emp = (string) ($r['employe_nom'] ?? '');
            $tb = (float) ($r['total_brut'] ?? 0);
            $rt = (float) ($r['rts'] ?? 0);
            $nt = (float) ($r['net_a_payer'] ?? 0);
            $isPaid = (int) ($r['paye'] ?? 0) === 1;
            $stat = $isPaid ? 'Payé' : 'Non payé';
            $date = (string) ($r['date_paiement'] ?? '');
            if ($date === '' || strtolower($date) === 'null') $date = '—';
            $mode = (string) ($r['mode_reglement'] ?? '');
            if ($mode === '' || strtolower($mode) === 'null') $mode = '—';

            $pdf->SetX($tableX);
            $pdf->Cell($wNum, 7, pdf_text_compat($numero), 1, 0, 'L');
            $pdf->Cell($wEmp, 7, pdf_text_compat($emp), 1, 0, 'L');
            $pdf->Cell($wBrut, 7, pdf_text_compat(money($tb)), 1, 0, 'R');
            $pdf->Cell($wRts, 7, pdf_text_compat($rt > 0 ? (money($rt) ) : '-'), 1, 0, 'R');
            $pdf->Cell($wNet, 7, pdf_text_compat(money($nt)), 1, 0, 'R');
            $pdf->Cell($wStat, 7, pdf_text_compat($stat), 1, 0, 'C');
            $pdf->Cell($wDate, 7, pdf_text_compat($date), 1, 0, 'C');
            $pdf->Cell($wMode, 7, pdf_text_compat($mode), 1, 1, 'C');
        }

        // Totaux
        $pdf->SetFont('CenturyGothic', 'B', 10);
        $pdf->SetX($tableX);
        $pdf->Cell($wNum + $wEmp, 8, pdf_text_compat('TOTAUX'), 1, 0, 'R');
        $pdf->Cell($wBrut, 8, pdf_text_compat(money($totalBrut)), 1, 0, 'R');
        $pdf->Cell($wRts, 8, pdf_text_compat($totalRts > 0 ? (money($totalRts)) : '-'), 1, 0, 'R');
        $pdf->Cell($wNet, 8, pdf_text_compat(money($totalNet)), 1, 0, 'R');
        $pdf->Cell($wStat + $wDate + $wMode, 8, pdf_text_compat('Payés: ' . $countPaid . '  |  Non payés: ' . $countUnpaid), 1, 1, 'C');
    }

    $pdf->Output('I', 'ETAT_SALAIRES_' . $year . '_' . sprintf('%02d', $month) . '.pdf');
} catch (Throwable $e) {
    error_log('[etat_salaires] pdf: ' . $e->getMessage());
    http_response_code(500);
    echo 'Une erreur est survenue lors de la génération du document';
}
