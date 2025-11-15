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
        $pdf->Cell(50, 5, utf8_decode($title), 0, 0); // Largeur fixe pour le titre
        $pdf->SetFont('CenturyGothic', '', 11); // Texte normal
        $pdf->Cell(0, 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $content), 0, 1); // Contenu aligné sur la même ligne
        $pdf->Ln(6);
    }

    if ($_GET['compte']!=0) {
        $nom_compte = compte($_GET['compte']);
    } else {
        $nom_compte = "Tous les comptes";
    }

    $devise = 'GNF';
    // Montant transmis par la page appelante (sécurisé >= 0)
    $montantParam = isset($_GET['montant']) ? (int)$_GET['montant'] : 0;
    if ($montantParam < 0) { $montantParam = 0; }


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
    addSection($pdf, 'Montant total :', (number_format($_GET['montant'] < 0 ? 0 : $_GET['montant']) .' '.$devise));
    addSection($pdf, 'Rapport caissier :', (number_format($_GET['rapportcaisse'] < 0 ? 0 : $_GET['rapportcaisse']) .' '.$devise));
    addSection($pdf, 'Différence :', (number_format($_GET['solde'] < 0 ? 0 : $_GET['solde']) .' '.$devise));
    addSection($pdf, 'Prestations :', '');

    // Construction du tableau directement avec FPDF pour contrôler largeur et taille des cellules
    $pdf->Ln(1);
    $pdf->SetFont('CenturyGothic','B',11);
    // Définir largeurs des colonnes (en mm) — total 190 mm (A4 largeur utile par défaut)
    $wType = 130;   // colonne Prestation
    $wNb = 25;      // colonne Nombre
    $wMontant = 35; // colonne Montant

    // Entête stylisée
    $pdf->SetFillColor(0,102,204); // bleu
    $pdf->SetTextColor(255,255,255); // texte blanc
    $pdf->Cell($wType,10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Type de prestation'), 1, 0, 'C', true);
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
            $montantPrestation = $nb * montant($nom);
            $totalNb += $nb;
            $totalMontant += $montantPrestation;
            $pdf->Cell($wType,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', model($nom)), 1, 0, 'L');
            $pdf->Cell($wNb,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$nb), 1, 0, 'C');
            $pdf->Cell($wMontant,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', number_format($montantPrestation) . ' ' . $devise), 1, 1, 'R');
        }
    }

    // Lignes additionnelles demandées
    $pdf->SetFont('CenturyGothic','B',11);
    // Montant total (paramètre fourni)
    $pdf->Cell($wType,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Montant total'), 1, 0, 'R');
    $pdf->Cell($wNb,8, '', 0, 0, 'C');
    $pdf->Cell($wMontant,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', number_format($montantParam) . ' ' . $devise), 1, 1, 'R');
    
    // Frais de retrait = Montant total - Total général (planché à 0)
    $fraisRetrait = max(0, $montantParam - (int)$totalMontant);
    $pdf->Cell($wType,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Frais de retrait'), 1, 0, 'R');
    $pdf->Cell($wNb,8, '', 0, 0, 'C');
    $pdf->Cell($wMontant,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', number_format($fraisRetrait) . ' ' . $devise), 1, 1, 'R');

    // Ligne Total Général
    $pdf->SetFont('CenturyGothic','B',11);
    $pdf->Cell($wType,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Total Général'), 1, 0, 'R');
    $pdf->Cell($wNb,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$totalNb), 1, 0, 'C');
    $pdf->Cell($wMontant,8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', number_format($totalMontant) . ' ' . $devise), 1, 1, 'R');

    // Signature
    $pdf->Ln(4);
    $pdf->SetFont('CenturyGothic', '', 8);
    $pdf->Cell(0, 8, utf8_decode("Imprimé le " . date('d/m/Y') . " par " . traitant($_SESSION['auth'])), 0, 0, 'R');

    $pdf->Output();

} catch (Exception $e) {
    error_log("Erreur lors de la génération du document : " . $e->getMessage());
    die("Une erreur est survenue lors de la génération du document");
}