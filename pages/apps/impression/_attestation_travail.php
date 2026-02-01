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

function fmtDateFr(DateTimeInterface $d): string
{
    $months = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
        7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
    ];
    $m = (int) $d->format('n');
    return (int) $d->format('d') . ' ' . ($months[$m] ?? $d->format('m')) . ' ' . $d->format('Y');
}

function computeDureeText(DateTimeInterface $start, DateTimeInterface $end): string
{
    $a = DateTimeImmutable::createFromInterface($start);
    $b = DateTimeImmutable::createFromInterface($end);
    if ($b < $a) {
        $tmp = $a;
        $a = $b;
        $b = $tmp;
    }

    $diff = $a->diff($b);
    $months = ((int) $diff->y * 12) + (int) $diff->m;
    $days = (int) $diff->d;

    if ($months <= 0) {
        $joursTotal = (int) $b->diff($a)->format('%a');
        if ($joursTotal <= 1) return 'une durée de 1 jour';
        return 'd\'une durée de ' . $joursTotal . ' jours';
    }

    // Arrondi simple: si plusieurs jours, on dit "X mois" sans sur-ajout.
    if ($months === 1) return 'd\'une durée de 1 mois';
    return 'd\'une durée de ' . $months . ' mois';
}

function numberWordFr(int $n): string
{
    $map = [
        0 => 'zéro',
        1 => 'un',
        2 => 'deux',
        3 => 'trois',
        4 => 'quatre',
        5 => 'cinq',
        6 => 'six',
        7 => 'sept',
        8 => 'huit',
        9 => 'neuf',
        10 => 'dix',
        11 => 'onze',
        12 => 'douze',
    ];
    return $map[$n] ?? (string) $n;
}

function computeStageDureeText(DateTimeInterface $start, DateTimeInterface $end): string
{
    $a = DateTimeImmutable::createFromInterface($start);
    $b = DateTimeImmutable::createFromInterface($end);
    if ($b < $a) {
        $tmp = $a;
        $a = $b;
        $b = $tmp;
    }

    $diff = $a->diff($b);
    $months = ((int) $diff->y * 12) + (int) $diff->m;
    if ($months <= 0) {
        $joursTotal = (int) $b->diff($a)->format('%a');
        if ($joursTotal <= 1) return "d'une durée de 1 jour";
        return "d'une durée de " . $joursTotal . ' jours';
    }

    $word = numberWordFr($months);
    $num2 = sprintf('%02d', $months);
    return "une durée de " . $word . ' (' . $num2 . ') mois';
}

try {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception('Paramètre id manquant.');
    }

    if (!tableExists($bdd, 'attestations_travail')) {
        throw new Exception('La table attestations_travail est introuvable. Exécutez db/attestations_travail.sql.');
    }

    $stProfil = $bdd->query('SELECT * FROM profil_entreprise LIMIT 1');
    $profil = $stProfil ? ($stProfil->fetch(PDO::FETCH_ASSOC) ?: []) : [];
    foreach (['denomination', 'adresse', 'phone', 'email', 'arrete', 'exploitation'] as $k) {
        if (!array_key_exists($k, $profil)) $profil[$k] = '';
    }

    $nameCol = getEmployeNomCol($bdd);

    // Vérifier si la colonne sexe existe
    $colSexe = null;
    try {
        $stCols = $bdd->query('SHOW COLUMNS FROM employes');
        if ($stCols) {
            while ($r = $stCols->fetch(PDO::FETCH_ASSOC)) {
                if (($r['Field'] ?? '') === 'sexe') {
                    $colSexe = 'sexe';
                    break;
                }
            }
        }
    } catch (Throwable $e) {}

    $sql = 'SELECT a.*, e.`' . $nameCol . '` AS employe_nom'
        . ($colSexe ? ', e.`sexe` AS employe_sexe' : '')
        . ' FROM attestations_travail a
            JOIN employes e ON e.id_employe = a.id_employe
            WHERE a.id_attestation = ?
            LIMIT 1';
    $st = $bdd->prepare($sql);
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new Exception('Attestation introuvable.');
    }

    $reference = trim((string) ($row['reference'] ?? ''));
    if ($reference === '') $reference = 'AT-' . $id;

    $type = trim((string) ($row['type_attestation'] ?? 'travail'));
    if ($type !== 'stage') $type = 'travail';

    $empName = trim((string) ($row['employe_nom'] ?? ''));
    $poste = trim((string) ($row['poste'] ?? ''));
    $empSexe = isset($row['employe_sexe']) ? (string)$row['employe_sexe'] : '';

    $dateDebut = (string) ($row['date_debut'] ?? '');
    $dateFin = (string) ($row['date_fin'] ?? '');
    $dateDelivrance = (string) ($row['date_delivrance'] ?? '');

    $lieu = trim((string) ($row['lieu'] ?? ''));
    if ($lieu === '') $lieu = 'Conakry';

    $signNom = trim((string) ($row['signataire_nom'] ?? ''));
    $signFonction = trim((string) ($row['signataire_fonction'] ?? ''));

    $dtDebut = DateTimeImmutable::createFromFormat('Y-m-d', $dateDebut) ?: new DateTimeImmutable('first day of this month');
    $dtFin = DateTimeImmutable::createFromFormat('Y-m-d', $dateFin) ?: new DateTimeImmutable('last day of this month');

    if (trim($dateDelivrance) === '') {
        $dateDelivrance = date('Y-m-d');
    }
    $dtDelivrance = DateTimeImmutable::createFromFormat('Y-m-d', $dateDelivrance) ?: new DateTimeImmutable();

    $dureeText = ($type === 'stage') ? computeStageDureeText($dtDebut, $dtFin) : computeDureeText($dtDebut, $dtFin);

    $pdf = new PDF('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->AddFont('CenturyGothic', '', 'CenturyGothic.php');
    $pdf->AddFont('CenturyGothic', 'B', 'CenturyGothic_bold.php');
    $pdf->SetAutoPageBreak(true, 12);

    if (!empty($profil)) {
        genererEntete($pdf, $profil);
    }

    // Référence
    $pdf->SetFont('CenturyGothic', 'B', 11);
    $pdf->Cell(0, 6, pdf_text_compat('Réf : ' . $reference), 0, 1, 'L');
    $pdf->Ln(8);

    // Titre
    $pdf->SetFont('CenturyGothic', 'B', 20);
    $pdf->Cell(0, 10, pdf_text_compat($type === 'stage' ? 'ATTESTATION DE STAGE' : 'ATTESTATION DE TRAVAIL'), 0, 1, 'C');
    $pdf->Ln(10);

    // Corps
    $pdf->SetFont('CenturyGothic', '', 12);

    $prefix = 'Je, soussigné';
    if ($signNom !== '') {
        $prefix .= ' ' . $signNom;
    }
    if ($signFonction !== '') {
        $prefix .= ', ' . $signFonction;
    }
    if (!empty($profil) && trim((string) ($profil['denomination'] ?? '')) !== '') {
        $prefix .= ' de la ' . trim((string) $profil['denomination']);
    }

    $texte1 = $prefix . ', atteste par la présente que ';
    if ($empName !== '') {
        if ($empSexe === '0') {
            $texte1 .= 'Madame ' . $empName;
        } elseif ($empSexe === '1') {
            $texte1 .= 'Monsieur ' . $empName;
        } else {
            $texte1 .= $empName;
        }
    } else {
        $texte1 .= "l'employé(e)";
    }


    if ($type === 'stage') {
        $texte1 .= ' a effectué un stage au sein de notre établissement ' . $dureeText;
        $texte1 .= ', du ' . fmtDateFr($dtDebut) . ' au ' . fmtDateFr($dtFin);
        if ($poste !== '') {
            $texte1 .= ', en qualité de ' . $poste;
        }
        $texte1 .= '.';
    } else {
        $texte1 .= ' a travaillé au sein de notre établissement ' . $dureeText;
        $texte1 .= ', du ' . fmtDateFr($dtDebut) . ' au ' . fmtDateFr($dtFin);
        if ($poste !== '') {
            $texte1 .= ', en qualité de ' . $poste;
        }
        $texte1 .= '.';
    }

    $pdf->MultiCell(0, 7, pdf_text_compat($texte1), 0, 'J');
    $pdf->Ln(6);

    $texte2 = 'La présente attestation lui est délivrée pour servir et faire valoir ce que de droit.';
    $pdf->MultiCell(0, 7, pdf_text_compat($texte2), 0, 'J');

    // Signature
    $pdf->Ln(18);
    $pdf->SetFont('CenturyGothic', 'B', 12);
    $pdf->Cell(0, 7, pdf_text_compat($lieu . ', le ' . fmtDateFr($dtDelivrance)), 0, 1, 'C');

    $pdf->Ln(22);
    $pdf->SetFont('CenturyGothic', '', 12);
    $pdf->Cell(0, 7, pdf_text_compat($signNom !== '' ? $signNom : 'Signature et cachet'), 0, 1, 'C');

    if ($signFonction !== '') {
        $pdf->SetFont('CenturyGothic', '', 10);
        $pdf->Cell(0, 6, pdf_text_compat($signFonction), 0, 1, 'C');
    }

    // Code-barres en pied de page (comme _rapportement)
    $pageHeight = $pdf->GetPageHeight();
    $yFooter = $pageHeight - 18;
    $barcodeValue = preg_replace('/\s+/', '', (string) $reference);
    if ($barcodeValue === '') {
        $barcodeValue = 'AT-' . $id;
    }
    $pdf->SetY($yFooter);
    $pdf->SetFont('CenturyGothic', '', 10);
    $pdf->Codabar(10, $yFooter, $barcodeValue, '0', 'Z', 0.15, 8, false);

$pdf->Output('I', 'ATTESTATION_TRAVAIL_' . $id . '.pdf');
} catch (Throwable $e) {
    error_log('[attestation_travail] pdf: ' . $e->getMessage());
    http_response_code(500);
    echo 'Une erreur est survenue lors de la génération du document';
}
