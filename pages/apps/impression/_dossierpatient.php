<?php
require_once('../PDF/fpdf.php');
require_once('../PDF/font/CenturyGothic.php');
require_once('../PDF/html_table13.php');
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');

class DossierPatientPDF extends PDF {
    private $data;
    private $patient;
    private $angle = 0;
    
    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A5') {
        parent::__construct($orientation, $unit, $size);
        $this->SetMargins(1, 1);
        $this->AliasNbPages();
        $this->SetAutoPageBreak(true, 1);
        $this->AddFont('CenturyGothic', '', 'CenturyGothic.php');
        $this->AddFont('CenturyGothic', 'B', 'CenturyGothic_bold.php');

        $this->SetFont('CenturyGothic', '', 12);
    }

    // Rotation locale (utile pour code-barres vertical)
    public function Rotate($angle, $x = null, $y = null) {
        if ($x === null) {
            $x = $this->x;
        }
        if ($y === null) {
            $y = $this->y;
        }

        if ($this->angle != 0) {
            $this->_out('Q');
        }

        $this->angle = $angle;
        if ($angle != 0) {
            $rad = $angle * M_PI / 180;
            $c = cos($rad);
            $s = sin($rad);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }

    protected function _endpage() {
        if ($this->angle != 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }

    private function splitTextToLines($text, $maxWidth): array {
        $s = utf8_decode((string)$text);
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/\s+/', ' ', trim($s));
        if ($s === '') {
            return [''];
        }

        $words = explode(' ', $s);
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            $candidate = ($line === '') ? $word : ($line . ' ' . $word);
            if ($this->GetStringWidth($candidate) <= $maxWidth) {
                $line = $candidate;
                continue;
            }

            if ($line !== '') {
                $lines[] = $line;
                $line = '';
            }

            // Mot trop long : le couper par caractères
            if ($this->GetStringWidth($word) > $maxWidth) {
                $chunk = '';
                $len = strlen($word);
                for ($i = 0; $i < $len; $i++) {
                    $ch = $word[$i];
                    $cand = $chunk . $ch;
                    if ($this->GetStringWidth($cand) <= $maxWidth) {
                        $chunk = $cand;
                    } else {
                        if ($chunk !== '') {
                            $lines[] = $chunk;
                        }
                        $chunk = $ch;
                    }
                }
                $line = $chunk;
            } else {
                $line = $word;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    private function formatPhoneDisplay($raw): string {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return '';
        }

        // Si plusieurs numéros sont fournis, prendre le premier
        $parts = preg_split('/[;,\|\/]+/', $raw);
        if (is_array($parts) && isset($parts[0])) {
            $raw = trim((string)$parts[0]);
        }

        $digits = preg_replace('/\D+/', '', $raw);
        $digits = (string)$digits;
        if ($digits === '') {
            return '';
        }

        // Retirer indicatifs courants (ex: +224 / 00224 / 224)
        if (substr($digits, 0, 5) === '00224') {
            $digits = substr($digits, 5);
        } elseif (substr($digits, 0, 3) === '224' && strlen($digits) > 9) {
            $digits = substr($digits, 3);
        }

        // Format attendu: 9 chiffres => 3-2-2-2 (ex: 620 00 00 00)
        if (strlen($digits) > 9) {
            $digits = substr($digits, -9);
        }

        if (strlen($digits) === 9) {
            return substr($digits, 0, 3) . ' ' . substr($digits, 3, 2) . ' ' . substr($digits, 5, 2) . ' ' . substr($digits, 7, 2);
        }

        // Fallback: grouper par 2
        return trim(chunk_split($digits, 2, ' '));
    }

    private function writeLabelValue($x, $w, $label, $value, $lineH = 4.5, $fontSize = 8, $multiline = false, $maxLines = null) {
        $labelText = (string)$label;
        $valueText = (string)$value;

        $this->SetX($x);
        $this->SetFont('CenturyGothic', 'B', $fontSize);
        $labelTextWithSpace = $labelText . ' ';
        $labelW = $this->GetStringWidth(utf8_decode($labelTextWithSpace));
        if ($labelW > ($w * 0.55)) {
            $labelW = $w * 0.55;
        }

        $this->Cell($labelW, $lineH, utf8_decode($labelTextWithSpace), 0, 0, 'L');
        $this->SetFont('CenturyGothic', '', $fontSize);

        $remainingW = $w - $labelW;
        if ($remainingW < 5) {
            $remainingW = 5;
        }

        if (!$multiline) {
            $this->Cell($remainingW, $lineH, utf8_decode($valueText), 0, 1, 'L');
            return;
        }

        // Multi-ligne : possibilité de limiter le nombre de lignes pour éviter les débordements
        $valueX = $x + $labelW;
        $startY = $this->y;
        $this->SetXY($valueX, $startY);

        $lines = $this->splitTextToLines($valueText, $remainingW);
        if ($maxLines !== null) {
            $maxLines = (int)$maxLines;
            if ($maxLines < 1) {
                $maxLines = 1;
            }
            if (count($lines) > $maxLines) {
                $lines = array_slice($lines, 0, $maxLines);
                $last = count($lines) - 1;
                $ellipsis = '...';
                $base = rtrim($lines[$last]);
                while ($base !== '' && $this->GetStringWidth($base . $ellipsis) > $remainingW) {
                    $base = rtrim(substr($base, 0, -1));
                }
                $lines[$last] = $base . $ellipsis;
            }
        }

        $first = true;
        foreach ($lines as $ln) {
            if ($first) {
                $this->Cell($remainingW, $lineH, $ln, 0, 1, 'L');
                $first = false;
            } else {
                $this->SetX($valueX);
                $this->Cell($remainingW, $lineH, $ln, 0, 1, 'L');
            }
        }
    }

    private function estimateCodabarLengthMm($code, $start = 'A', $end = 'B', $basewidth = 0.25): float {
        // Reproduit le calcul interne de PDF::Codabar() (FPDF) pour estimer la longueur.
        $barChar = array (
            '0' => array (6.5, 10.4, 6.5, 10.4, 6.5, 24.3, 17.9),
            '1' => array (6.5, 10.4, 6.5, 10.4, 17.9, 24.3, 6.5),
            '2' => array (6.5, 10.0, 6.5, 24.4, 6.5, 10.0, 18.6),
            '3' => array (17.9, 24.3, 6.5, 10.4, 6.5, 10.4, 6.5),
            '4' => array (6.5, 10.4, 17.9, 10.4, 6.5, 24.3, 6.5),
            '5' => array (17.9, 10.4, 6.5, 10.4, 6.5, 24.3, 6.5),
            '6' => array (6.5, 24.3, 6.5, 10.4, 6.5, 10.4, 17.9),
            '7' => array (6.5, 24.3, 6.5, 10.4, 17.9, 10.4, 6.5),
            '8' => array (6.5, 24.3, 17.9, 10.4, 6.5, 10.4, 6.5),
            '9' => array (18.6, 10.0, 6.5, 24.4, 6.5, 10.0, 6.5),
            '$' => array (6.5, 10.0, 18.6, 24.4, 6.5, 10.0, 6.5),
            '-' => array (6.5, 10.0, 6.5, 24.4, 18.6, 10.0, 6.5),
            ':' => array (16.7, 9.3, 6.5, 9.3, 16.7, 9.3, 14.7),
            '/' => array (14.7, 9.3, 16.7, 9.3, 6.5, 9.3, 16.7),
            '.' => array (13.6, 10.1, 14.9, 10.1, 17.2, 10.1, 6.5),
            '+' => array (6.5, 10.1, 17.2, 10.1, 14.9, 10.1, 13.6),
            '*' => array (6.5, 10.1, 13.2, 10.1, 14.9, 5.1, 2.6),
            'A' => array (6.5, 8.0, 19.6, 19.4, 6.5, 16.1, 6.5),
            'B' => array (6.5, 8.0, 19.6, 19.4, 6.5, 16.1, 6.5),
            'C' => array (6.5, 16.1, 6.5, 19.4, 6.5, 8.0, 19.6),
            'D' => array (6.5, 16.1, 6.5, 19.4, 6.5, 8.0, 19.6),
            'E' => array (10.5, 8.0, 6.5, 19.4, 6.5, 16.1, 19.6),
            'F' => array (6.5, 8.0, 6.5, 19.4, 6.5, 16.1, 19.6),
            'G' => array (6.5, 8.0, 6.5, 19.4, 19.6, 16.1, 6.5),
            'H' => array (6.5, 8.0, 6.5, 19.4, 19.6, 16.1, 6.5),
            'I' => array (6.5, 8.0, 6.5, 19.4, 19.6, 16.1, 6.5),
            'J' => array (6.5, 8.0, 6.5, 19.4, 19.6, 16.1, 6.5),
            'K' => array (6.5, 8.0, 6.5, 19.4, 19.6, 16.1, 6.5),
            'L' => array (6.5, 8.0, 6.5, 19.4, 19.6, 16.1, 6.5),
            'M' => array (6.5, 8.0, 6.5, 19.4, 19.6, 16.1, 6.5),
            'N' => array (6.5, 8.0, 6.5, 19.4, 19.6, 16.1, 6.5),
            'O' => array (6.5, 8.0, 6.5, 19.4, 19.6, 16.1, 6.5),
            'P' => array (6.5, 8.0, 6.5, 19.4, 19.6, 16.1, 6.5),
            'Q' => array (14.7, 9.3, 16.7, 9.3, 6.5, 9.3, 16.7),
            'R' => array (6.5, 8.0, 6.5, 19.4, 19.6, 16.1, 6.5),
            'S' => array (6.5, 8.0, 6.5, 19.4, 19.6, 16.1, 6.5),
            'T' => array (6.5, 10.4, 17.9, 10.4, 6.5, 24.3, 6.5),
            'U' => array (14.7, 9.3, 16.7, 9.3, 6.5, 9.3, 16.7),
            'V' => array (6.5, 8.0, 6.5, 19.4, 19.6, 16.1, 6.5),
            'W' => array (6.5, 10.4, 17.9, 10.4, 6.5, 24.3, 6.5),
            'X' => array (6.5, 8.0, 6.5, 19.4, 19.6, 16.1, 6.5),
            'Y' => array (14.7, 9.3, 16.7, 9.3, 6.5, 9.3, 16.7),
            'Z' => array (14.7, 9.3, 16.7, 9.3, 6.5, 9.3, 16.7),
            '' => array (9.7, 5.3, 16.7, 9.3, 6.5, 9.3, 2.7),
        );

        $code = strtoupper((string)$start . (string)$code . (string)$end);
        $len = 0.0;
        $gap = $basewidth * 10.4 / 6.5;

        for ($i = 0; $i < strlen($code); $i++) {
            $char = $code[$i];
            if (!isset($barChar[$char])) {
                return 0.0;
            }
            $seq = $barChar[$char];
            for ($bar = 0; $bar < 7; $bar++) {
                $len += $basewidth * $seq[$bar] / 6.5;
            }
            $len += $gap;
        }

        return $len;
    }

    public function initializeData($bdd, $id_patient) {
        try {
            // Charger les informations de l'entreprise
            $stmt = $bdd->prepare('SELECT * FROM profil_entreprise');
            $stmt->execute();
            $this->data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Charger les informations du patient
            $stmt = $bdd->prepare('SELECT * FROM patients WHERE id_patient = ?');
            $stmt->execute([$id_patient]);
            $this->patient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$this->patient) {
                throw new Exception("Patient non trouvé");
            }
        } catch (PDOException $e) {
            error_log("Erreur lors du chargement des données : " . $e->getMessage());
            throw new Exception("Erreur lors du chargement des données");
        }
    }
    
    public function generateHeader() {
        $this->Ln(250);
        $this->SetFont('CenturyGothic', 'B', 20);
        $this->Cell(0, 9, utf8_decode('N° ' . $this->patient['id_patient']), 0, 0, '');
        $this->Ln(9);
        if ($this->data) {
            genererEnteteDossier($this, $this->data);
        }
    }
    
    public function generatePatientInfo() {
        $this->SetFont('CenturyGothic', '', 8);
        $statut = $this->patient['assure'] == 1 ? 'Assuré' : 'Non assuré';
        $this->Cell(0, 5, utf8_decode($statut . str_repeat(' ', 101) . 'Date d\'admission ' . $this->patient['date']), 0, 1, 'L');
        
        $html = $this->generatePatientTable();
        $this->WriteHTML($html);
    }

    private function getPatientYearOfBirth(): string {
        $raw = (string)($this->patient['age'] ?? '');
        $raw = trim($raw);

        if ($raw === '') {
            return '';
        }

        if (preg_match('/^(\d{4})\b/', $raw, $m)) {
            return $m[1];
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            return '';
        }
        return date('Y', $ts);
    }

    private function getPatientFullName(): string {
        $nom = trim((string)($this->patient['nom_patient'] ?? $this->patient['nom'] ?? ''));
        $prenom = trim((string)($this->patient['prenom_patient'] ?? $this->patient['prenom'] ?? ''));

        if ($prenom !== '' && $nom !== '') {
            return $nom . ' ' . $prenom;
        }
        return $nom !== '' ? $nom : $prenom;
    }

    private function getPatientAddress(): string {
        $adresse = (string)($this->patient['adresse'] ?? '');
        $adresse = trim($adresse);
        if ($adresse === '') {
            return '';
        }

        $formatted = adress($adresse);
        $formatted = is_string($formatted) ? trim($formatted) : '';
        return $formatted !== '' ? $formatted : $adresse;
    }

    private function getPatientContact(): string {
        // phone est déjà la valeur à afficher (return_phone() attend un id_patient)
        return $this->formatPhoneDisplay($this->patient['phone'] ?? '');
    }

    private function getPatientAdhesion(): string {
        // La date d'adhésion est stockée dans la colonne `date`
        $raw = trim((string)($this->patient['date'] ?? ''));
        if ($raw === '') {
            return '';
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            return $raw;
        }

        return date('d-m-Y', $ts);
    }

    public function generateHealthCardPage() {
        // Format carte bancaire (ID-1) : 85.6 x 54 mm, orientation paysage.
        $cardWmm = 80.0;
        $cardHmm = 47.0;
        $this->AddPage('L', [$cardWmm, $cardHmm]);
        $this->SetAutoPageBreak(false);

        $margin = 2.5;
        $x = $margin;
        $y = $margin;
        $w = $cardWmm - ($margin * 2);
        $h = $cardHmm - ($margin * 2);

        $this->SetDrawColor(0, 0, 0);
        $this->Rect($x, $y, $w, $h);

        // Entête (entreprise) adaptée au format carte
        $innerPad = 2.5;
        $headerTop = $y + $innerPad;
        $logoPath = realpath('../img/logo.jpg');
        $logoW = 14;
        $logoH = 10;
        if ($logoPath && file_exists($logoPath)) {
            $this->Image($logoPath, $x + $innerPad, $headerTop, $logoW, $logoH);
        }

        $textX = $x + $innerPad + $logoW + 2.5;
        $textW = ($x + $w) - $textX - $innerPad;
        $this->SetXY($textX, $headerTop);
        $this->SetFont('CenturyGothic', 'B', 6.5);
        if (!empty($this->data['denomination'])) {
            $this->Cell($textW, 3.5, pdf_text(strtoupper($this->data['denomination'])), 0, 1, 'L');
        } else {
            $this->Ln(3);
        }

        $this->SetX($textX);
        $this->SetFont('CenturyGothic', '', 5.5);
        if (!empty($this->data['adresse'])) {
            // Limiter l'adresse entreprise à 2 lignes pour préserver l'espace de la carte
            $addrLines = $this->splitTextToLines($this->data['adresse'], $textW);
            if (count($addrLines) > 2) {
                $addrLines = array_slice($addrLines, 0, 2);
                $last = count($addrLines) - 1;
                $ellipsis = '...';
                $base = rtrim($addrLines[$last]);
                while ($base !== '' && $this->GetStringWidth($base . $ellipsis) > $textW) {
                    $base = rtrim(substr($base, 0, -1));
                }
                $addrLines[$last] = $base . $ellipsis;
            }
            foreach ($addrLines as $ln) {
                $this->SetX($textX);
                $this->Cell($textW, 3.2, $ln, 0, 1, 'L');
            }
        }
        $this->SetX($textX);
        $contact = trim((string)($this->data['phone'] ?? ''));
        $email = trim((string)($this->data['email'] ?? ''));
        $contactLine = $contact;
        if ($email !== '') {
            $contactLine = $contactLine !== '' ? ($contactLine . ' | ' . $email) : $email;
        }
        if ($contactLine !== '') {
            $this->Cell($textW, 3.2, pdf_text($contactLine), 0, 1, 'L');
        }

        // Séparateur sous entête
        $separatorY = max($this->y, $headerTop + $logoH) + 1.5;
        $this->Line($x + $innerPad, $separatorY, $x + $w - $innerPad, $separatorY);
        $this->SetY($separatorY + 2);

        $dossier = (string)($this->patient['id_patient'] ?? '');
        $fullName = $this->getPatientFullName();
        $year = $this->getPatientYearOfBirth();
        $address = $this->getPatientAddress();
        $contact = $this->getPatientContact();
        $adhesion = $this->getPatientAdhesion();

        // Réserver une zone à droite pour le code-barres
        $barcodeAreaW = 12.0;
        $contentX = $x + $innerPad;
        $contentW = $w - ($innerPad * 2) - $barcodeAreaW;

        $this->SetX($contentX);
        $this->SetFont('CenturyGothic', 'B', 7);
        $this->Cell($contentW, 4.0, utf8_decode('CARTE D\'ADHESION PATIENT N° ' . $dossier), 0, 1, 'C');

        $lineH = 3.4;
        $fontSize = 6.2;
        $this->writeLabelValue($contentX, $contentW, 'Date d\'adhésion :', $adhesion, $lineH, $fontSize, false);
        $this->writeLabelValue($contentX, $contentW, 'Patient :', $fullName, $lineH, $fontSize, false);
        $this->writeLabelValue($contentX, $contentW, 'Année de naissance :', $year, $lineH, $fontSize, false);
        $this->writeLabelValue($contentX, $contentW, 'Contact :', $contact, $lineH, $fontSize, false);

        // Limiter l'adresse à l'espace restant pour éviter tout débordement
        $bottomLimitY = $y + $h - $innerPad;
        $availableH = $bottomLimitY - $this->y;
        $maxLines = (int)floor($availableH / $lineH);
        if ($maxLines < 1) {
            $maxLines = 1;
        }
        $this->writeLabelValue($contentX, $contentW, 'Adresse :', $address, $lineH, $fontSize, true, $maxLines);

        // Code-barres vertical à droite (Codabar), basé sur le numéro dossier
        if (method_exists($this, 'Codabar') && $dossier !== '') {
            // Plus court (moins de caractères => code-barres moins long)
            $barcodeValue = (string)$dossier;
            $barcodeTopY = $separatorY + 2;
            $barcodeAnchorX = $x + $w - $innerPad; // bord interne droit
            $barThickness = max(8.5, $barcodeAreaW - 2.0); // largeur visible du code-barres dans la carte

            // Ajuster automatiquement l'épaisseur pour éviter tout débordement (selon la hauteur disponible)
            $bottomLimitY = $y + $h - $innerPad;
            $maxLen = $bottomLimitY - $barcodeTopY;
            if ($maxLen > 0) {
                // Légèrement moins compact => code-barres un peu plus long (plus lisible)
                $baseMax = 0.24;
                $baseMin = 0.07;
                $lenAt1 = $this->estimateCodabarLengthMm($barcodeValue, 'A', 'B', 1.0);
                $basewidth = $baseMax;
                if ($lenAt1 > 0) {
                    $basewidth = min($baseMax, $maxLen / $lenAt1);
                    $basewidth = max($baseMin, $basewidth);
                }

                // Si malgré tout c'est trop long, forcer une basewidth encore plus petite (dernier recours)
                $lenEst = $this->estimateCodabarLengthMm($barcodeValue, 'A', 'B', $basewidth);
                if ($lenEst > $maxLen && $lenAt1 > 0) {
                    $basewidth = max(0.05, $maxLen / $lenAt1);
                }

                // Rotation -90° : le code-barres devient vertical; il s'étend vers le bas
                $this->Rotate(-90, $barcodeAnchorX, $barcodeTopY);
                $this->Codabar($barcodeAnchorX, $barcodeTopY, $barcodeValue, 'A', 'B', $basewidth, $barThickness, false);
                $this->Rotate(0);
            }
        }
    }
    
    private function generatePatientTable() {
        $anneeNaissance = date('Y', strtotime($this->patient['age']));
        $telephone = $this->formatPhoneDisplay($this->patient['phone'] ?? '');
        
        $html = '<table align="center">
<hr widht="50px"/>
<tr><td>Patient : ' . utf8_decode($this->patient['nom_patient'] . '    Né(e) en : ' . $anneeNaissance . '    Genre : ' . $this->patient['sexe'] . '  Téléphone : ' . $telephone) . '</td></tr>
<tr><td>Adresse : ' . utf8_decode(adress($this->patient['adresse']) ?: $this->patient['adresse']) . '    Profession : ' . utf8_decode($this->patient['profession']) . '</td></tr>
<tr><hr widht="50px"/> 
<td>Motif de consultation : ....................................................................................................................................................<br>...........................................................................................................................................................................................</td></tr>
<tr><td>Evolution : ........................................................................................................................................................................</td></tr>
<tr><td>Terrain : ............................................................................................................................................................................</td></tr>
<tr><td>' . utf8_decode('Antécédents') . ' : .................................................................................................................................................................</td></tr><br>
<tr><hr widht="50px"/><br> 
<td> AVLSC :  OD .......... OS ..........  |  AVC :  OD ......... OS .........  |  TS :  OD ......... OS .........    P :  ..............................</td></tr><br>
<tr><td>1. Examen Externe : </td></tr>
<tr><td>2. Biomicroscopie : <br> </td><br>
<td> - Annexes : <br><br> </td>
<td> - ' . utf8_decode('Segment Antérieur') . ' : <br><br></td>
<td> - ' . utf8_decode('Segment Postérieur') . ' : </td></tr><br>
<tr><td>3. ' . utf8_decode('Diagnostic de présomption') . ' : </td></tr><br>
<tr><td>4. Conduite tenue :  <br> </td></tr>
<tr><td>5. ' . utf8_decode('Diagnostic définitif') . ' : </td></tr><br>
<tr><td>6. ' . utf8_decode('Contrôle de suivi') . ' : </td></tr><br><br>
<hr widht="50px"/>';
        
        return $html;
    }
    
    public function generateFooter() {
        $this->Cell(0, 5, utf8_decode("Voir le monde sous un nouveau jour !"), 0, 0, 'C');
    }
}

try {
    if (!isset($_GET['id_patient'])) {
        throw new Exception("ID patient non spécifié");
    }
    
    $pdf = new DossierPatientPDF();
    $pdf->AddPage();
    $pdf->initializeData($bdd, $_GET['id_patient']);
    $pdf->generateHeader();
    $pdf->generatePatientInfo();
    $pdf->generateFooter();
    //$pdf->generateHealthCardPage();
    $filename = 'DOSSIER DU PATIENT PAT-' . $_GET['id_patient'] . '.pdf';
    $pdf->Output($filename, 'I');
    
} catch (Exception $e) {
    error_log("Erreur dans la génération du dossier patient : " . $e->getMessage());
    die("Une erreur est survenue lors de la génération du dossier patient.");
}
?>
