<?php
require_once('../PDF/fpdf.php');
require_once('../PDF/font/CenturyGothic.php');
require_once('../PDF/html_table13.php');
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');

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

try {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception('Paramètre manquant.');
    }

    if (!tableExists($bdd, 'notes_service')) {
        throw new Exception('La table notes_service est introuvable. Exécutez db/notes_service.sql.');
    }

    // Profil (pour entête + lieu)
    $stProfil = $bdd->query('SELECT * FROM profil_entreprise LIMIT 1');
    $profil = $stProfil ? ($stProfil->fetch(PDO::FETCH_ASSOC) ?: []) : [];
    foreach (['denomination', 'adresse', 'phone', 'email', 'arrete', 'exploitation'] as $k) {
        if (!array_key_exists($k, $profil)) $profil[$k] = '';
    }

    $lieu = 'Conakry';

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

    $sql = 'SELECT n.*, e.`' . $nameCol . '` AS employe_nom'
        . ($colSexe ? ', e.`sexe` AS employe_sexe' : '')
        . ' FROM notes_service n
            JOIN employes e ON e.id_employe = n.id_employe
            WHERE n.id_note = ?
            LIMIT 1';
    $st = $bdd->prepare($sql);
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new Exception('Note introuvable.');
    }

    // Référence (la table n'a pas de colonne "reference")
    $createdAt = trim((string) ($row['created_at'] ?? ''));
    $dtRef = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $createdAt) ?: null;
    $reference = $dtRef ? ('NS-' . $dtRef->format('Ymd') . '-' . $id) : ('NS-' . $id);

    $dtSignature = $dtRef ?: new DateTimeImmutable();

    $empName = trim((string) ($row['employe_nom'] ?? ''));
    $empSexe = isset($row['employe_sexe']) ? (string) $row['employe_sexe'] : '';

    $ancien = trim((string) ($row['ancien_poste'] ?? ''));
    $nouveau = trim((string) ($row['nouveau_poste'] ?? ''));

    $dateDebutRaw = (string) ($row['date_debut'] ?? '');
    $dateFinRaw = (string) ($row['date_fin'] ?? '');
    $dtDebut = DateTimeImmutable::createFromFormat('Y-m-d', $dateDebutRaw) ?: new DateTimeImmutable();
    $dtFin = DateTimeImmutable::createFromFormat('Y-m-d', $dateFinRaw) ?: null;

    // Règle demandée: si une date de fin existe => temporaire, sinon définitif
    $isTemporaire = (bool) $dtFin;

    $signNom = trim((string) ($row['signataire_nom'] ?? ''));
    $signFonction = trim((string) ($row['signataire_fonction'] ?? ''));
    $motif = trim((string) ($row['motif'] ?? ''));

    // Civilité demandée: sexe=1 Monsieur, sexe=0 Madame
    $identite = "l'employé(e)";
    if ($empName !== '') {
        if ($empSexe === '1') {
            $identite = 'Monsieur ' . $empName;
        } elseif ($empSexe === '0') {
            $identite = 'Madame ' . $empName;
        } else {
            $identite = $empName;
        }
    }

    $objet = ($isTemporaire)
        ? 'Affectation temporaire au poste de ' . $nouveau.'.'
        : 'Changement de poste à titre définitif.';

    // Accords selon sexe (1 = Monsieur, 0 = Madame)
    $motDesigne = 'désigné(e)';
    $motInteresse = 'intéressé(e)';
    if ($empSexe === '1') {
        $motDesigne = 'désigné';
        $motInteresse = 'intéressé';
    } elseif ($empSexe === '0') {
        $motDesigne = 'désignée';
        $motInteresse = 'intéressée';
    }

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
    $pdf->Cell(0, 10, pdf_text_compat('NOTE DE SERVICE'), 0, 1, 'C');
    $pdf->Ln(8);

    // Objet
    $pdf->SetFont('CenturyGothic', 'B', 12);
    $pdf->Cell(0, 7, pdf_text_compat('Objet : ' . $objet), 0, 1, 'L');
    $pdf->Ln(6);

    // Corps (style similaire à l'attestation)
    $pdf->SetFont('CenturyGothic', '', 12);

    if (!$isTemporaire) {
        $texte1 = "Il est porté à la connaissance de l'ensemble du personnel que " . $identite;
        if ($ancien !== '') {
            $texte1 .= ', précédemment ' . $ancien;
        }
        $texte1 .= " est nommé(e) à compter du " . fmtDateFr($dtDebut) . " au poste de " . $nouveau . ", à titre définitif.";
        $pdf->MultiCell(0, 7, pdf_text_compat($texte1), 0, 'J');
        $pdf->Ln(6);

        $texte2 = "Cette décision s'inscrit dans le cadre de l'organisation et des besoins de service.";
        $pdf->MultiCell(0, 7, pdf_text_compat($texte2), 0, 'J');
        $pdf->Ln(6);

        $texte3 = "L'" . $motInteresse . " est invité(e) à prendre fonction et à collaborer pleinement avec les services concernés.";
        $pdf->MultiCell(0, 7, pdf_text_compat($texte3), 0, 'J');
    } else {
        $texte1 = "Il est porté à la connaissance de l'ensemble du personnel que " . $identite;
        if ($ancien !== '') {
            $texte1 .= ", occupant le poste de " . $ancien;
        }
        $texte1 .= " est " . $motDesigne . " pour assurer les fonctions de " . $nouveau . " à titre temporaire.";
        $pdf->MultiCell(0, 7, pdf_text_compat($texte1), 0, 'J');
        $pdf->Ln(6);

        $texte2 = 'Cette affectation est valable pour la période allant du ' . fmtDateFr($dtDebut);
        if ($dtFin) {
            $texte2 .= ' au ' . fmtDateFr($dtFin);
        }
        $texte2 .= '.';
        $pdf->MultiCell(0, 7, pdf_text_compat($texte2), 0, 'J');
        $pdf->Ln(6);

        $texte3 = "À l'issue de cette période, l'" . $motInteresse . " réintégrera son poste initial, sauf nouvelle décision contraire de la Direction.";
        $pdf->MultiCell(0, 7, pdf_text_compat($texte3), 0, 'J');
    }

    if ($motif !== '') {
        $pdf->Ln(6);
        $pdf->SetFont('CenturyGothic', 'B', 12);
        $pdf->Cell(0, 7, pdf_text_compat('Motif :'), 0, 1, 'L');
        $pdf->SetFont('CenturyGothic', '', 12);
        $pdf->MultiCell(0, 7, pdf_text_compat($motif), 0, 'J');
    }

    // Mention de clôture
    $pdf->Ln(8);
    $pdf->SetFont('CenturyGothic', '', 12);
    $pdf->MultiCell(0, 7, pdf_text_compat('La présente note prend effet à compter de sa date de signature.'), 0, 'J');

    // Signature (centrée comme l'attestation)
    $pdf->Ln(18);
    $pdf->SetFont('CenturyGothic', 'B', 12);
    $pdf->Cell(0, 7, pdf_text_compat($lieu . ', le ' . fmtDateFr($dtSignature)), 0, 1, 'C');

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
        $barcodeValue = 'NS-' . $id;
    }
    $pdf->SetY($yFooter);
    $pdf->SetFont('CenturyGothic', '', 10);
    $pdf->Codabar(10, $yFooter, $barcodeValue, '0', 'Z', 0.15, 8, false);

    $pdf->Output('I', 'NOTE_SERVICE_' . $id . '.pdf');
} catch (Throwable $e) {
    error_log('[note_service] pdf: ' . $e->getMessage());
    http_response_code(500);
    echo 'Une erreur est survenue lors de la génération du document';
}
