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

function toFloat($value): float
{
    if ($value === null) return 0.0;
    if (is_int($value) || is_float($value)) return (float) $value;

    $s = trim((string) $value);
    if ($s === '') return 0.0;

    $s = str_replace(["\xC2\xA0", ' '], '', $s);
    $s = str_replace(',', '.', $s);
    if (substr_count($s, '.') > 1) {
        $s = str_replace('.', '', $s);
    }

    return is_numeric($s) ? (float) $s : 0.0;
}

function fmtDateFr(DateTimeInterface $d): string
{
    $months = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
        7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
    ];
    $m = (int) $d->format('n');
    return (int) $d->format('d') . ' ' . ($months[$m] ?? $d->format('m')) . ' ' . $d->format('Y');
}

function fmtDatePaiementFr(string $value): string
{
    $v = trim($value);
    if ($v === '') return '';

    // Formats attendus
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $v)
        ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $v)
        ?: DateTimeImmutable::createFromFormat('d/m/Y', $v);

    return $dt ? fmtDateFr($dt) : $v;
}

function money(float $amount): string
{
    // Devise déjà affichée en haut
    return number_format($amount, 0, '', ' ');
}

try {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception('Paramètre id manquant.');
    }

    if (!tableExists($bdd, 'bulletins_salaire')) {
        throw new Exception('La table bulletins_salaire est introuvable. Exécutez db/bulletins_salaire.sql.');
    }

    $stProfil = $bdd->query('SELECT * FROM profil_entreprise LIMIT 1');
    $profil = $stProfil ? ($stProfil->fetch(PDO::FETCH_ASSOC) ?: []) : [];
    foreach (['denomination', 'adresse', 'phone', 'email', 'arrete', 'exploitation', 'devise'] as $k) {
        if (!array_key_exists($k, $profil)) $profil[$k] = '';
    }

    $nameCol = getEmployeNomCol($bdd);

    $sql = 'SELECT b.id_bulletin, b.periode, b.numero, b.mode_reglement, b.date_paiement, b.devise,
                   b.salaire_base, b.prime_transport, b.prime_logement, b.prime_vie, b.heures_sup, b.autres_primes,
                   b.rts, b.total_brut,
                   e.`' . $nameCol . '` AS employe_nom,
                   e.adresse AS employe_adresse,
                   e.poste AS employe_poste
            FROM bulletins_salaire b
            JOIN employes e ON e.id_employe = b.id_employe
            WHERE b.id_bulletin = ?
            LIMIT 1';
    $st = $bdd->prepare($sql);
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new Exception('Bulletin introuvable.');
    }

    $devise = trim((string) ($row['devise'] ?? ''));
    if ($devise === '') $devise = trim((string) ($profil['devise'] ?? ''));
    if ($devise === '') $devise = 'GNF';

    $periode = (string) ($row['periode'] ?? '');
    $periodeDt = DateTimeImmutable::createFromFormat('Y-m-d', $periode) ?: new DateTimeImmutable('first day of this month');
    $start = $periodeDt->modify('first day of this month');
    $end = $periodeDt->modify('last day of this month');
    $periodeStr = 'du ' . fmtDateFr($start) . ' au ' . fmtDateFr($end);

    $numero = (string) ($row['numero'] ?? ('BS-' . $id));
    $empName = trim((string) ($row['employe_nom'] ?? ''));
    $empAdresse = trim((string) ($row['employe_adresse'] ?? ''));
    $empPoste = trim((string) ($row['employe_poste'] ?? ''));
    $mode = trim((string) ($row['mode_reglement'] ?? ''));
    $datePaiement = trim((string) ($row['date_paiement'] ?? ''));
    $datePaiementFr = fmtDatePaiementFr($datePaiement);

    $salaire = toFloat($row['salaire_base'] ?? null);
    $pt = toFloat($row['prime_transport'] ?? null);
    $pl = toFloat($row['prime_logement'] ?? null);
    $pv = toFloat($row['prime_vie'] ?? null);
    $hs = toFloat($row['heures_sup'] ?? null);
    $ap = toFloat($row['autres_primes'] ?? null);
    $rts = toFloat($row['rts'] ?? null);

    $totalBrut = toFloat($row['total_brut'] ?? ($salaire + $pt + $pl + $pv + $hs + $ap));
    // Pas d'autres retenues => net = brut - RTS
    $net = $totalBrut - $rts;

    $pdf = new PDF('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->AddFont('CenturyGothic', '', 'CenturyGothic.php');
    $pdf->AddFont('CenturyGothic', 'B', 'CenturyGothic_bold.php');
    $pdf->SetAutoPageBreak(true, -1);

    if (!empty($profil)) {
        genererEntete($pdf, $profil);
    }

    $pdf->SetFont('CenturyGothic', 'B', 12);
    $pdf->Cell(0, 5, pdf_text_compat('BULLETIN DE PAIE'), 0, 1, 'C');
    $pdf->Ln(6);

    $sections = [
        ['Réference :', $numero],
        ['Période :', $periodeStr],
        ['Personnel :', ($empName !== '' ? $empName : '-')],
        ['Emploi :', ($empPoste !== '' ? $empPoste : '-')],
        ['Adresse :', ($empAdresse !== '' ? $empAdresse : '-')],
        ['Mode de règlement :', ($mode !== '' ? $mode : '-')],
        ['Date de paiement :', ($datePaiementFr !== '' ? $datePaiementFr : '-')],
        ['Devise :', $devise],
    ];
    foreach ($sections as [$t, $c]) {
        $pdf->SetFont('CenturyGothic', 'B', 11);
        $pdf->Cell(50, 5, pdf_text_compat($t), 0, 0);
        $pdf->SetFont('CenturyGothic', '', 11);
        $pdf->Cell(0, 5, pdf_text_compat($c), 0, 1);
        $pdf->Ln(3);
    }

    $pdf->Ln(2);

    // Tableau rubriques (sans colonne taux) + montants sans devise
    $wRub = 105;
    $wImp = 25;
    $wBase = 30;
    $wMontant = 30;

    $pdf->SetFont('CenturyGothic', 'B', 11);
    $pdf->SetFillColor(0, 102, 204);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($wRub, 10, pdf_text_compat('Rubrique'), 1, 0, 'C', true);
    $pdf->Cell($wImp, 10, pdf_text_compat('Imposable'), 1, 0, 'C', true);
    $pdf->Cell($wBase, 10, pdf_text_compat('Base'), 1, 0, 'C', true);
    $pdf->Cell($wMontant, 10, pdf_text_compat('Montant'), 1, 1, 'C', true);

    $pdf->SetFont('CenturyGothic', '', 11);
    $pdf->SetTextColor(0, 0, 0);

    $rubriques = [
        ['Salaire de base indiciaire', $salaire],
        ['Indemnité de transport', $pt],
        ['Indemnité de logement', $pl],
        ['Indemnité de cherté de vie', $pv],
        ['Heures supplémentaires', $hs],
    ];
    if ($ap > 0) $rubriques[] = ['Autres primes', $ap];

    foreach ($rubriques as [$label, $amount]) {
        $amount = (float) $amount;
        $isImposable = ($label === 'Salaire de base indiciaire');
        $pdf->Cell($wRub, 8, pdf_text_compat($label), 1, 0, 'L');
        $pdf->Cell($wImp, 8, pdf_text_compat($isImposable ? 'Oui' : 'Non'), 1, 0, 'C');
        $pdf->Cell($wBase, 8, pdf_text_compat(money($amount)), 1, 0, 'R');
        $pdf->Cell($wMontant, 8, pdf_text_compat(money($amount)), 1, 1, 'R');
    }

    $pdf->SetFont('CenturyGothic', 'B', 11);
    $merge = $wRub + $wImp + $wBase;

    $pdf->Cell($merge, 8, pdf_text_compat('TOTAL BRUT (HT)'), 1, 0, 'R');
    $pdf->Cell($wMontant, 8, pdf_text_compat(money($totalBrut)), 1, 1, 'R');

    $pdf->Cell($merge, 8, pdf_text_compat('RTS'), 1, 0, 'R');
    $pdf->Cell($wMontant, 8, pdf_text_compat($rts > 0 ? money($rts) : '-'), 1, 1, 'R');

    $pdf->Cell($merge, 8, pdf_text_compat('NET A PAYER'), 1, 0, 'R');
    $pdf->Cell($wMontant, 8, pdf_text_compat(money($net)), 1, 1, 'R');

    // Texte légal en bas (après le tableau)
    $pdf->Ln(10);
    $pdf->SetFont('CenturyGothic', '', 9);
    $footerText = "Dans votre intérêt, et pour vous aider à faire valoir vos droits, conservez ce bulletin sans limitation de durée.\n"
        . "Pour toute question concernant ce bulletin de paie, vous pouvez contacter la direction générale à l’adresse indiquée sur\n"
        . "l’entête du bulletin.";
    $pdf->MultiCell(0, 5, pdf_text_compat($footerText), 0, 'J');

    // Signature
    $pdf->Ln(4);
    $pdf->SetFont('CenturyGothic', '', 8);
    $printedBy = isset($_SESSION['auth']) ? traitant($_SESSION['auth']) : '';
    $signature = 'Imprimé le ' . date('d/m/Y');
    $pdf->Output('I', 'BULLETIN_PAIE_' . $id . '.pdf');
} catch (Throwable $e) {
    error_log('[bulletin_salaire] pdf: ' . $e->getMessage());
    http_response_code(500);
    echo 'Une erreur est survenue lors de la génération du document';
}
