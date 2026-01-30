<?php
require('../PDF/fpdf.php');
require('../PDF/html_table13.php');
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');

if (!function_exists('appec_isCardValid')) {
    function appec_isCardValid($dateStr): bool
    {
        $s = trim((string)$dateStr);
        if ($s === '') return false;

        $tryFormats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'Y/m/d', 'd-m-Y', 'm-d-Y'];
        $dt = null;
        foreach ($tryFormats as $fmt) {
            $tmp = DateTimeImmutable::createFromFormat($fmt, $s);
            if ($tmp instanceof DateTimeImmutable) {
                $errors = DateTimeImmutable::getLastErrors();
                if (empty($errors['warning_count']) && empty($errors['error_count'])) {
                    $dt = $tmp;
                    break;
                }
            }
        }

        if (!$dt) {
            $ts = strtotime($s);
            if ($ts === false) return false;
            $dt = (new DateTimeImmutable())->setTimestamp($ts);
        }

        $expiryEnd = $dt->setTime(23, 59, 59);
        $now = new DateTimeImmutable();
        return $expiryEnd >= $now;
    }
}

if (!function_exists('appec_toFloat')) {
    function appec_toFloat($value): float
    {
        if ($value === null) return 0.0;
        if (is_float($value) || is_int($value)) return (float)$value;
        $s = trim((string)$value);
        if ($s === '') return 0.0;
        $s = str_replace([' ', ','], ['', '.'], $s);
        return (float)$s;
    }
}

if (!function_exists('appec_getAssuranceIdColumn')) {
    function appec_getAssuranceIdColumn(PDO $bdd): ?string
    {
        if (!function_exists('dbTableHasColumn')) return null;
        if (dbTableHasColumn($bdd, 'assurances', 'id_assurance')) return 'id_assurance';
        if (dbTableHasColumn($bdd, 'assurances', 'd_assurance')) return 'd_assurance';
        if (dbTableHasColumn($bdd, 'assurances', 'id')) return 'id';
        return null;
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->AddFont('CenturyGothic','','CenturyGothic.php');
$pdf->AddFont('CenturyGothic','B','CenturyGothic_bold.php');
$pdf->SetAutoPageBreak(false,0);
setlocale(LC_CTYPE, 'fr_FR');

// Récupération des informations de l'entreprise
$profil = $bdd->prepare('SELECT * FROM profil_entreprise');
$profil->execute();
$data = $profil->fetch();

// Fonctions pour générer l'en-tête du reçu
$pdf->SetFont('CenturyGothic','',12);
// Fonctions pour générer le contenu du reçu
function genererContenuRecu($pdf, $donnees1, $donnees2, $rdvDisplay = '', $assuranceRowsHtml = '', $hidePaymentMethod = false) {
    $html = '<table align="center" border="">';
    $html .= '<hr />';
    $html .= '<tr style="line-height:1px;"><td width="350" height="50">' . nom_patient($donnees1['id_patient']) . '</td></tr>';
    $html .= '<tr style="line-height:1px;"><td width="350" height="50">' . (adress(return_adresse($donnees1['id_patient'])) ?: return_adresse($donnees1['id_patient'])) . ' | ' . return_phone($donnees1['id_patient']) . '</td></tr>';
    $html .= '<hr />';
    $html .= '<tr style="line-height:1px;"><td width="350" height="50">' . ('Prestation : ' . model($donnees1['type'])) . '</td></tr>';

    if (is_string($assuranceRowsHtml) && trim($assuranceRowsHtml) !== '') {
        $html .= $assuranceRowsHtml;
    }

    if (is_string($rdvDisplay) && trim($rdvDisplay) !== '') {
        $html .= '<tr style="line-height:1px;"><td width="350" height="50">' . ('Date de rendez-vous : ' . $rdvDisplay) . '</td></tr>';
    }

    $html .= '<tr style="line-height:1px;border:1px;"><td width="350" height="50">' . ('Montant Payé : ' . number_format($donnees2['montant_paye'])) . ' GNF </td></tr>';

    // Affichage conditionnel du solde
    if (!empty($donnees2['solde']) && floatval($donnees2['solde']) != 0) {
        $html .= '<tr style="line-height:1px;border:1px;"><td width="350" height="50">'
            . ('Reste à Payer : ' . number_format($donnees2['solde'])) . ' GNF </td></tr>';
    }

    if (!$hidePaymentMethod) {
        $payMethodId = 0;
        if (!empty($donnees2['compte'])) {
            $payMethodId = (int)$donnees2['compte'];
        } elseif (!empty($donnees1['type_paiement'])) {
            $payMethodId = (int)$donnees1['type_paiement'];
        }
        $html .= '<tr style="line-height:1px;border:1px;"><td width="350" height="50">' . ('Payé Par : ' . type_paiement($payMethodId)) . ' </td></tr>';
    }
    $html .= '<tr style="line-height:1px;border:1px;"><td width="350" height="50">' . ('Paiement N° : ' . $donnees2['code']) . ' </td></tr>';
    return pdf_text_compat($html);
}

// Récupération des données
$reponse1 = $bdd->prepare('SELECT * FROM affectations WHERE id_affectation = ?');
$reponse1->execute(array($_GET['affectation']));
$donnees1 = $reponse1->fetch();

$affectationId = (int)($_GET['affectation'] ?? 0);
$paiementId = (int)($_GET['paiement'] ?? 0);

// Sélectionner le paiement à imprimer (celui du jour si fourni, sinon le dernier)
if ($paiementId > 0) {
    $reponse2 = $bdd->prepare('SELECT * FROM paiements WHERE id_paiement = ? LIMIT 1');
    $reponse2->execute([$paiementId]);
    $donnees2 = $reponse2->fetch();
    if ($donnees2 && (int)($donnees2['id_affectation'] ?? 0) !== $affectationId) {
        $donnees2 = false;
    }
} else {
    $reponse2 = $bdd->prepare('SELECT * FROM paiements WHERE id_affectation = ? ORDER BY id_paiement DESC LIMIT 1');
    $reponse2->execute([$affectationId]);
    $donnees2 = $reponse2->fetch();
    $paiementId = (int)($donnees2['id_paiement'] ?? 0);
}

if (!$donnees1 || !$donnees2) {
    die("Données non trouvées");
}

// Pour les achats de lunettes: l'assurance ne prend pas en charge => ne pas afficher la ligne de prise en charge.
$isVenteLunette = false;
try {
    $venteLunetteTypeId = 0;
    $stType = $bdd->prepare("SELECT id_type FROM traitements WHERE LOWER(nom_type) LIKE '%lunet%' OR LOWER(nom_type) LIKE '%monture%' ORDER BY id_type ASC LIMIT 1");
    $stType->execute();
    $venteLunetteTypeId = (int)($stType->fetchColumn() ?: 0);
    if ($venteLunetteTypeId <= 0) {
        $stType = $bdd->prepare("SELECT id_type FROM traitements WHERE LOWER(nom_type) LIKE '%vente%' ORDER BY id_type ASC LIMIT 1");
        $stType->execute();
        $venteLunetteTypeId = (int)($stType->fetchColumn() ?: 0);
    }
    $isVenteLunette = ($venteLunetteTypeId > 0 && (int)($donnees1['type'] ?? 0) === $venteLunetteTypeId);
} catch (Throwable $e) {
    $isVenteLunette = false;
}

// Recalcul du solde: plusieurs paiements possibles. On calcule le total payé jusqu'à ce paiement.
$montantTotal = 0.0;
if (isset($donnees1['montant'])) {
    $montantTotal = appec_toFloat($donnees1['montant']);
}
if ($montantTotal <= 0 && isset($donnees2['montant'])) {
    $montantTotal = appec_toFloat($donnees2['montant']);
}

$montantPayeJusqua = 0.0;
if ($affectationId > 0 && $paiementId > 0) {
    $stPaid = $bdd->prepare('SELECT COALESCE(SUM(COALESCE(montant_paye,0)),0) FROM paiements WHERE id_affectation = ? AND id_paiement <= ?');
    $stPaid->execute([$affectationId, $paiementId]);
    $montantPayeJusqua = (float)($stPaid->fetchColumn() ?: 0);
}
$resteCalc = $montantTotal - $montantPayeJusqua;
if ($resteCalc < 0) $resteCalc = 0;

// Injecter un solde fiable même si la colonne générée ne correspond pas à l'historique
$donnees2['solde'] = $resteCalc;

// Date RDV (affichage conditionnel) : uniquement si la prestation est liée à un rendez-vous et que celui-ci n'est pas encore arrivé.
$rdvDisplay = '';
$idRdv = (int)($donnees1['id_rdv'] ?? 0);
if ($idRdv > 0 && function_exists('getRdvInfo')) {
    $rdvInfo = getRdvInfo($bdd, $idRdv);
    $rawRdv = is_array($rdvInfo) ? trim((string)($rdvInfo['prochain_rdv'] ?? '')) : '';
    if ($rawRdv !== '') {
        try {
            $dtRdv = new DateTime($rawRdv);
            $now = new DateTime();
            if ($dtRdv > $now) {
                $rdvDisplay = $dtRdv->format('d/m/Y');
                if ($dtRdv->format('H:i:s') !== '00:00:00') {
                    $rdvDisplay .= ' ' . $dtRdv->format('H:i');
                }
            }
        } catch (Exception $e) {
            // Ne rien afficher si la date est invalide
            $rdvDisplay = '';
        }
    }
}

// Infos assurance (si patient assuré)
$assuranceRowsHtml = '';
$hidePaymentMethod = false;
if (!$isVenteLunette) {
    try {
        if (function_exists('dbTableHasColumn') && dbTableHasColumn($bdd, 'patients', 'assure')) {
            $patCols = ['assure'];
            foreach (['assurance', 'tauxPrisecharge', 'dateExpiration'] as $col) {
                if (dbTableHasColumn($bdd, 'patients', $col)) {
                    $patCols[] = $col;
                }
            }
            $stPat = $bdd->prepare('SELECT ' . implode(', ', $patCols) . ' FROM patients WHERE id_patient = ?');
            $stPat->execute([(int)$donnees1['id_patient']]);
            $pat = $stPat->fetch(PDO::FETCH_ASSOC) ?: [];

            $assureFlag = (int)($pat['assure'] ?? 0);
            if ($assureFlag === 1) {
                $dateExp = (string)($pat['dateExpiration'] ?? '');
                $carteValide = appec_isCardValid($dateExp);
                $tauxPrise = appec_toFloat($pat['tauxPrisecharge'] ?? 0);
                $assuranceId = (int)($pat['assurance'] ?? 0);
                $assuranceNom = '';
                if ($assuranceId > 0) {
                    $assuranceIdCol = appec_getAssuranceIdColumn($bdd);
                    if ($assuranceIdCol) {
                        $stAss = $bdd->prepare('SELECT assurance FROM assurances WHERE ' . $assuranceIdCol . ' = ? LIMIT 1');
                        $stAss->execute([$assuranceId]);
                        $assuranceNom = (string)($stAss->fetchColumn() ?: '');
                    }
                }

                if ($carteValide && $tauxPrise > 0 && $assuranceNom !== '') {
                    $tauxStr = rtrim(rtrim(number_format($tauxPrise, 2, '.', ''), '0'), '.');
                    $assuranceRowsHtml .= '<tr style="line-height:1px;"><td width="350" height="50">' . ('Pris en charge à ' . $tauxStr . '% par ' . $assuranceNom) . '</td></tr>';
                }

                // Si prise en charge à 100% et carte valide : ne pas afficher le moyen de paiement
                if ($carteValide && $tauxPrise >= 99.999) {
                    $hidePaymentMethod = true;
                }
            }
        }
    } catch (Exception $e) {
        $assuranceRowsHtml = '';
        $hidePaymentMethod = false;
    }
}

// Premier reçu
genererEntete($pdf, $data);

$pdf->SetFont('CenturyGothic', '', 11);
$dateAffiche = (string)($donnees2['datepaiement'] ?? $donnees1['date'] ?? '');
$pdf->Cell(0, 5, pdf_text_compat('PAT-' . $donnees1['id_patient'] . str_repeat(' ', 128) . 'Date : ' . $dateAffiche), 0, 1);
$pdf->WriteHTML(genererContenuRecu($pdf, $donnees1, $donnees2, $rdvDisplay, $assuranceRowsHtml, $hidePaymentMethod));

// Signature et NB
$pdf->Cell(0, -60, pdf_text_compat("signature et cachet"), 0, 0, 'R');
$pdf->Ln(2);
$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->Cell(0, -13, pdf_text_compat(traitant($donnees2['caisse'])), 0, 0, 'R');
$pdf->Ln(8);
$pdf->Cell(0, 5, pdf_text_compat("NB : Veuillez apporter un de vos reçu de paiement lors de votre prochaine visite."), 0, 0, 'L');
$pdf->SetFont('CenturyGothic', '', 11);
// Séparateur
$pdf->Ln(25);
$pdf->Cell(5, 0, str_repeat("-", 146), 0, 0, 'L');
$pdf->Ln(16);
// Deuxième reçu
genererEntete($pdf, $data, 156);
$pdf->Cell(0, 5, pdf_text_compat('PAT-' . $donnees1['id_patient'] . str_repeat(' ', 128) . 'Date : ' . $dateAffiche), 0, 1);
$pdf->WriteHTML(genererContenuRecu($pdf, $donnees1, $donnees2, $rdvDisplay, $assuranceRowsHtml, $hidePaymentMethod));

// Signature et NB pour le deuxième reçu
$pdf->Cell(0, -60, pdf_text_compat("signature et cachet"), 0, 0, 'R');
$pdf->Ln(2);
$pdf->SetFont('CenturyGothic', 'B', 11);
$pdf->Cell(0, -13, pdf_text_compat(traitant($donnees2['caisse'])), 0, 0, 'R');
$pdf->Ln(8);
$pdf->Cell(0, 5, pdf_text_compat("NB : Veuillez remettre ce reçu à la comptabilité."), 0, 0, 'L');
$filename = 'RECU DE PAIEMENT PAT-' . $donnees1['id_patient'] . '.pdf';
$pdf->Output($filename, 'I');
?>
