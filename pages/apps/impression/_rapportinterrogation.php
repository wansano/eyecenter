<?php
require_once('../PDF/fpdf.php');
require_once('../PDF/font/CenturyGothic.php');
require_once('../PDF/html_table13.php');
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();
try {
    // Vérification du paramètre
    if (!isset($_GET['compte'])) {
        throw new Exception("ID de compte manquant");
    }

    // Récupération des données en une seule requête

    // Initialisation du PDF
    $pdf = new PDF('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->AddFont('CenturyGothic','','CenturyGothic.php');
    $pdf->AddFont('CenturyGothic','B','CenturyGothic_bold.php');
    $pdf->SetAutoPageBreak(true, -1);

    // Récupération des infos entreprise pour l'entête
    $profil = $bdd->prepare('SELECT * FROM profil_entreprise LIMIT 1');
    $profil->execute();
    $dataProfil = $profil->fetch(PDO::FETCH_ASSOC);
    if ($dataProfil) {
        genererEntete($pdf, $dataProfil);
    }

    // En-tête
    // Informations patient
$html = '<table align="center" border="">
<tr>
</tr>';
$pdf->WriteHTML($html);

    // Détails du traitement
    $pdf->SetFont('CenturyGothic', 'B', 12);
    $pdf->Cell(0, 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', "RAPPORT D'INTERROGATION DE COMPTE" ), 0, 0, 'C');
    $pdf->Ln(14);
    $pdf->SetFont('CenturyGothic', '', 11);
    // Fonction helper pour ajouter une section
    function addSection($pdf, $title, $content) {
        $pdf->SetFont('CenturyGothic', 'B', 11); // Titre en gras
        $pdf->Cell(50, 5, pdf_text_compat($title), 0, 0); // Largeur fixe pour le titre
        $pdf->SetFont('CenturyGothic', '', 11); // Texte normal
        $pdf->Cell(0, 5, pdf_text_compat($content), 0, 1); // Contenu aligné sur la même ligne
        $pdf->Ln(2);
    }

    if ($_GET['compte']!=0) {
        $nom_compte = compte($_GET['compte']);
    } else {
        $nom_compte = "Tous les comptes";
    }

    $devise = 'GNF';
    // Paramètres transmis par la page appelante (sécurisés >= 0)
    $rapportCaissierParam = isset($_GET['rapportcaisse']) ? (int)$_GET['rapportcaisse'] : 0;
    if ($rapportCaissierParam < 0) { $rapportCaissierParam = 0; }


    $total = $bdd->prepare('SELECT * FROM traitements ORDER BY id_type');
    $total->execute();
    $data = $total->fetch(PDO::FETCH_ASSOC);
    if ($data) {
        $nom = $data['id_type'];
        $nb = nombrejour_periode($nom, $_GET['debut'], $_GET['fin']);
    }

    // Ajout des sections
    // Fonction locale de format français (fallback si Intl indisponible)
    if (!function_exists('safeDateFr')) {
        function safeDateFr($dateStr) {
            // N'utiliser la fonction globale que si Intl est disponible
            if (function_exists('dateEnFrancais') && class_exists('IntlDateFormatter')) {
                try { return dateEnFrancais($dateStr); } catch (Exception $t) { /* fallback */ }
            }
            // Fallback sans Intl
            $dt = DateTime::createFromFormat('Y-m-d', $dateStr) ?: new DateTime($dateStr);
            $mois = [
                '01'=>'janvier','02'=>'février','03'=>'mars','04'=>'avril','05'=>'mai','06'=>'juin',
                '07'=>'juillet','08'=>'août','09'=>'septembre','10'=>'octobre','11'=>'novembre','12'=>'décembre'
            ];
            $m = $dt->format('m');
            return $dt->format('d') . ' ' . ($mois[$m] ?? $m) . ' ' . $dt->format('Y');
        }
    }

    $periode = 'du ' . safeDateFr($_GET['debut']) . ' au ' . safeDateFr($_GET['fin']);
    addSection($pdf, 'Période :', $periode);
    addSection($pdf, 'Compte :', $nom_compte);
    addSection($pdf, 'Devise :', $devise);

    // Calcul du remboursement sur la période: SUM(montant_paye) si dispo, sinon SUM(montant)
    $paiementsAmountCol = 'montant';
    try {
        $stCols = $bdd->query('SHOW COLUMNS FROM paiements');
        if ($stCols) {
            while ($c = $stCols->fetch(PDO::FETCH_ASSOC)) {
                if (($c['Field'] ?? '') === 'montant_paye') {
                    $paiementsAmountCol = 'montant_paye';
                    break;
                }
            }
        }
    } catch (Throwable $e) {
        $paiementsAmountCol = 'montant';
    }

    $remboursementTotal = 0;
    try {
        if (isset($_GET['compte']) && (int)$_GET['compte'] !== 0) {
            $rembStmt = $bdd->prepare('SELECT COALESCE(SUM(`' . $paiementsAmountCol . '`), 0) AS remboursement_total FROM paiements WHERE compte = :compte AND remboursement = 1 AND datepaiement BETWEEN :debut AND :fin');
            $rembStmt->execute([
                ':compte' => (int)$_GET['compte'],
                ':debut' => $_GET['debut'],
                ':fin' => $_GET['fin'],
            ]);
        } else {
            $rembStmt = $bdd->prepare('SELECT COALESCE(SUM(`' . $paiementsAmountCol . '`), 0) AS remboursement_total FROM paiements WHERE remboursement = 1 AND datepaiement BETWEEN :debut AND :fin');
            $rembStmt->execute([
                ':debut' => $_GET['debut'],
                ':fin' => $_GET['fin'],
            ]);
        }
        if ($rowRemb = $rembStmt->fetch(PDO::FETCH_ASSOC)) {
            $remboursementTotal = ($rowRemb['remboursement_total'] !== null) ? (float)$rowRemb['remboursement_total'] : 0;
        }
    } catch (Throwable $e) {
        $remboursementTotal = 0;
    }

    // Calcul de l'entrée (hors remboursements) sur la période
    $entreeTotal = 0;
    try {
        if (function_exists('getEntreePaiements') && isset($_GET['compte']) && (int)$_GET['compte'] !== 0) {
            $entreeTotal = (float)getEntreePaiements((int)$_GET['compte'], $_GET['debut'], $_GET['fin'], $bdd);
        } else {
            if (isset($_GET['compte']) && (int)$_GET['compte'] !== 0) {
                $stEntree = $bdd->prepare('SELECT COALESCE(SUM(montant),0) AS entree FROM paiements WHERE compte = :compte AND (remboursement = 0 OR remboursement IS NULL) AND datepaiement BETWEEN :debut AND :fin');
                $stEntree->execute([
                    ':compte' => (int)$_GET['compte'],
                    ':debut' => $_GET['debut'],
                    ':fin' => $_GET['fin'],
                ]);
            } else {
                $stEntree = $bdd->prepare('SELECT COALESCE(SUM(montant),0) AS entree FROM paiements WHERE (remboursement = 0 OR remboursement IS NULL) AND datepaiement BETWEEN :debut AND :fin');
                $stEntree->execute([
                    ':debut' => $_GET['debut'],
                    ':fin' => $_GET['fin'],
                ]);
            }
            $rowEntree = $stEntree->fetch(PDO::FETCH_ASSOC);
            $entreeTotal = $rowEntree && $rowEntree['entree'] !== null ? (float)$rowEntree['entree'] : 0;
        }
    } catch (Throwable $e) {
        $entreeTotal = 0;
    }

    // Calcul des frais de retrait selon le taux du compte électronique utilisé (table comptes)
    $entreeElectroniqueTotal = 0;
    $fraisRetrait = 0;
    $entreeNetteApresFrais = 0;
    try {
        if (isset($_GET['compte']) && (int)$_GET['compte'] !== 0) {
            $compteId = (int)$_GET['compte'];
            $stElec = $bdd->prepare(
                'SELECT '
                . 'COALESCE(SUM(p.montant),0) AS total_elec, '
                . 'COALESCE(SUM(p.montant * (COALESCE(c.taux,0) / 100)),0) AS frais '
                . 'FROM paiements p '
                . 'INNER JOIN comptes c ON c.id_compte = p.compte '
                . 'WHERE c.id_compte = :compte '
                . 'AND c.electronique = 1 '
                . 'AND (p.remboursement = 0 OR p.remboursement IS NULL) '
                . 'AND p.datepaiement BETWEEN :debut AND :fin'
            );
            $stElec->execute([
                ':compte' => $compteId,
                ':debut' => $_GET['debut'],
                ':fin' => $_GET['fin'],
            ]);
            $rowElec = $stElec->fetch(PDO::FETCH_ASSOC);
            $entreeElectroniqueTotal = max(0, (float)($rowElec['total_elec'] ?? 0));
            $fraisRetrait = max(0, (float)($rowElec['frais'] ?? 0));
        } else {
            // Tous les comptes: somme des paiements électroniques avec leur taux propre
            $stElec = $bdd->prepare(
                'SELECT '
                . 'COALESCE(SUM(p.montant),0) AS total_elec, '
                . 'COALESCE(SUM(p.montant * (COALESCE(c.taux,0) / 100)),0) AS frais '
                . 'FROM paiements p '
                . 'INNER JOIN comptes c ON c.id_compte = p.compte '
                . 'WHERE c.electronique = 1 '
                . 'AND (p.remboursement = 0 OR p.remboursement IS NULL) '
                . 'AND p.datepaiement BETWEEN :debut AND :fin'
            );
            $stElec->execute([
                ':debut' => $_GET['debut'],
                ':fin' => $_GET['fin'],
            ]);
            $rowElec = $stElec->fetch(PDO::FETCH_ASSOC);
            $entreeElectroniqueTotal = max(0, (float)($rowElec['total_elec'] ?? 0));
            $fraisRetrait = max(0, (float)($rowElec['frais'] ?? 0));
        }

        $entreeNetteApresFrais = max(0, (float)$entreeTotal - (float)$fraisRetrait);
    } catch (Throwable $e) {
        $entreeElectroniqueTotal = 0;
        $fraisRetrait = 0;
        $entreeNetteApresFrais = max(0, (float)$entreeTotal);
    }

    $totalGlobal = (float)$entreeTotal + (float)$remboursementTotal;
    $differenceGlobal = $totalGlobal - (float)$rapportCaissierParam;
    addSection($pdf, 'Entrée total :', number_format($totalGlobal < 0 ? 0 : $totalGlobal, 0, '', ' '));
    addSection($pdf, 'Remboursement :', number_format($remboursementTotal < 0 ? 0 : $remboursementTotal, 0, '', ' '));
    addSection($pdf, 'Solde hors frais :', number_format($entreeTotal < 0 ? 0 : $entreeTotal, 0, '', ' '));
    addSection($pdf, 'Rapport caissier :', number_format($rapportCaissierParam, 0, '', ' '));
    addSection($pdf, 'Différence :', number_format($differenceGlobal, 0, '', ' '));
    addSection($pdf, 'Réalisations :', '');

    // Construction du tableau directement avec FPDF pour contrôler largeur et taille des cellules
    $pdf->Ln(1);
    $pdf->SetFont('CenturyGothic','B',11);
    // Définir largeurs des colonnes (en mm) — total 190 mm (A4 largeur utile par défaut)
    $wType = 100;   // colonne Prestation
    $wNb = 20;      // colonne Nombre
    $wPrix = 30;    // colonne Prix Unitaire
    $wMontant = 35; // colonne Montant

    // Entête stylisée
    $pdf->SetFillColor(0,102,204); // bleu
    $pdf->SetTextColor(255,255,255); // texte blanc
    $pdf->Cell($wType,10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Prestation'), 1, 0, 'C', true);
    $pdf->Cell($wPrix,10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Prix Unitaire'), 1, 0, 'C', true);
    $pdf->Cell($wNb,10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Nombre'), 1, 0, 'C', true);
    $pdf->Cell($wMontant,10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Montant'), 1, 1, 'C', true);

    // Corps du tableau
    $pdf->SetFont('CenturyGothic','',11);
    $pdf->SetFillColor(255,255,255);
    $pdf->SetTextColor(0,0,0);

    $total = $bdd->prepare('SELECT * FROM traitements ORDER BY id_type');
    $total->execute();
    $totalNb = 0;
    $totalMontant = 0;
    while ($data = $total->fetch(PDO::FETCH_ASSOC)) {
        $nom = $data['id_type'];
        // Si un compte est spécifié, compter uniquement sur ce compte
        if (isset($_GET['compte']) && (int)$_GET['compte'] !== 0) {
            $nb = nombrejourPeriodeCompte($nom, $_GET['debut'], $_GET['fin'], (int)$_GET['compte']);
        } else {
            $nb = nombrejour_periode($nom, $_GET['debut'], $_GET['fin']);
        }
        if ($nb != 0) {
            $prixUnitaire = montant($nom);
            $montantPrestation = $nb * $prixUnitaire;
            $totalNb += $nb;
            $totalMontant += $montantPrestation;
            $pdf->Cell($wType,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', model($nom)), 1, 0, 'L');
            $pdf->Cell($wPrix,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', number_format($prixUnitaire, 0, '', ' ')), 1, 0, 'R');
            $pdf->Cell($wNb,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$nb), 1, 0, 'C');
            $pdf->Cell($wMontant,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', number_format($montantPrestation, 0, '', ' ')), 1, 1, 'R');
        }
    }

    // Lignes additionnelles demandées
    $pdf->SetFont('CenturyGothic','B',11);
    // Montant total (ENTREE) - Fusion Prestation + Prix Unitaire
    $pdf->Cell($wType + $wPrix,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Total des entrées'), 1, 0, 'R');
    $pdf->Cell($wNb,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$totalNb), 0, 0, 'C');
    $pdf->Cell($wMontant,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', number_format($totalGlobal, 0, '', ' ')), 1, 1, 'R');

    // Remboursement
    $pdf->Cell($wType + $wPrix,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Montant remboursé'), 1, 0, 'R');
    $pdf->Cell($wNb,8, '', 1, 0, 'C');
    $pdf->Cell($wMontant,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', number_format($remboursementTotal, 0, '', ' ')), 1, 1, 'R');

    // Total après retrait des frais électroniques
    $pdf->Cell($wType + $wPrix,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Total après remboursement'), 1, 0, 'R');
    $pdf->Cell($wNb,8, '', 0, 0, 'C');
    $pdf->Cell($wMontant,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', number_format($entreeNetteApresFrais, 0, '', ' ')), 1, 1, 'R');
    
    // Frais de retrait: 1% des paiements électroniques (hors remboursements)
    $fraisRetrait = max(0, (float)$fraisRetrait);
    $pdf->Cell($wType + $wPrix,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Frais de retrait inclus'), 1, 0, 'R');
    $pdf->Cell($wNb,8, '', 0, 0, 'C');
    $pdf->Cell($wMontant,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', number_format($fraisRetrait, 0, '', ' ')), 1, 1, 'R');

    /* Ligne Total Général
    $pdf->SetFont('CenturyGothic','B',11);
    $pdf->Cell($wType + $wPrix,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Solde'), 1, 0, 'R');
    $pdf->Cell($wNb,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$totalNb), 1, 0, 'C', true);
    $pdf->SetFillColor(255,255,255); // Retour à blanc
    $pdf->Cell($wMontant,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', number_format($totalMontant, 0, '', ' ')), 1, 1, 'R');
    */

    // Signature
    $pdf->Ln(4);
    $pdf->SetFont('CenturyGothic', '', 8);
    $pdf->Cell(0, 8, pdf_text_compat("Imprimé le " . date('d/m/Y') . " par " . traitant($_SESSION['auth'])), 0, 0, 'R');

    $pdf->Output();

} catch (Exception $e) {
    error_log("Erreur lors de la génération du document : " . $e->getMessage());
    die("Une erreur est survenue lors de la génération du document");
}