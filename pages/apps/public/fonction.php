<?php
require_once(__DIR__ . '/connect.php');


/**
 * Cache pour stocker les résultats des requêtes fréquentes
 */
class Cache {
    private static $data = [];
    
    public static function get($key) {
        return self::$data[$key] ?? null;
    }
    
    public static function set($key, $value) {
        self::$data[$key] = $value;
    }
    
    public static function has($key) {
        return isset(self::$data[$key]);
    }
}

/**
 * Convertit une chaîne UTF-8 vers un encodage compatible FPDF (Windows-1252)
 * en évitant l'usage de utf8_decode() (déprécié en PHP 8.2).
 */
function pdf_text($str) {
    if ($str === null) return '';

    $s = (string)$str;
    if ($s === '') {
        return '';
    }

    // Tentative via iconv avec translit (si l'extension est dispo)
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
        if ($converted !== false) {
            return $converted;
        }
    }

    // Fallback via mb_convert_encoding (si mbstring est dispo)
    if (function_exists('mb_convert_encoding')) {
        $converted = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }
    }

    // Dernier recours : renvoyer tel quel (évite un fatal error)
    return $s;
}

/**
 * Récupère les informations d'une ville
 * @param int $id_ville Identifiant de la ville
 * @return string Adresse formatée de la ville
 */

/**
 * Génère l'entête PDF de l'entreprise
 * @param object $pdf Instance du PDF
 * @param array $data Données de l'entreprise (dénomination, adresse, phone, email, arrete, exploitation)
 * @param int $yPosition Position verticale du logo (défaut : 33)
 */
function genererEntete($pdf, $data, $yPosition = 12) {
    $logoPath = realpath(__DIR__ . '/../img/logo.jpg');
    if ($logoPath !== false) {
        $pdf->Image($logoPath, 152, $yPosition, 50, 25);
    }
    $pdf->SetFont('CenturyGothic','B',11);
    $pdf->Cell(0, 5, pdf_text(strtoupper($data['denomination'])), 0, 1, 'L');
    $pdf->SetFont('CenturyGothic','',11);
    $pdf->Cell(0, 5, pdf_text($data['adresse']), 0, 1, 'L');
    $pdf->Cell(0, 5, pdf_text($data['phone'] . ' | ' . $data['email']), 0, 1, 'L');
    $pdf->Cell(0, 5, pdf_text('Agrément de création n° ' . $data['arrete']), 0, 1, 'L');
    $pdf->Cell(0, 5, pdf_text('Agrément d\'exploitation n° ' . $data['exploitation']), 0, 1, 'L');
    $pdf->Cell(0, 2, str_repeat("_", 98), 0, 0, 'L');
    $pdf->Ln(10);
}

function genererEnteteDossier($pdf, $data, $yPosition = 6) {
    $logoPath = realpath(__DIR__ . '/../img/logo.jpg');
    if ($logoPath !== false) {
        $pdf->Image($logoPath, 100, $yPosition, 50, 25);
    }
    $pdf->SetFont('CenturyGothic','B',8);
    $pdf->Cell(0, 4, pdf_text(strtoupper($data['denomination'])), 0, 1, 'L');
    $pdf->SetFont('CenturyGothic','',8);
    $pdf->Cell(0, 4, pdf_text($data['adresse']), 0, 1, 'L');
    $pdf->Cell(0, 4, pdf_text($data['phone'] . ' | ' . $data['email']), 0, 1, 'L');
    $pdf->Cell(0, 4, pdf_text('Agrément de création n° ' . $data['arrete']), 0, 1, 'L');
    $pdf->Cell(0, 4, pdf_text('Agrément d\'exploitation n° ' . $data['exploitation']), 0, 1, 'L');
    $pdf->Cell(0, 0, str_repeat("_", 104), 0, 0, 'L');
    $pdf->Ln(4);
}

function adresseville($id_ville) {
    global $bdd;
    
    // Vérifier le cache
    $cacheKey = "ville_" . $id_ville;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT nom, region FROM adresses_villes WHERE id_ville = ?');
        $stmt->execute([$id_ville]);
        $ville = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $addresscity = $ville ? $ville['region'] . ", " . $ville['nom'] : "";
        
        // Mettre en cache
        Cache::set($cacheKey, $addresscity);
        
        return $addresscity;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de la ville : " . $e->getMessage());
        return "";
    }
}

function quartier($id_quartier) {
    global $bdd;
    
    // Vérifier le cache
    $cacheKey = "quartier_" . $id_quartier;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT quartier FROM adresses_quartiers WHERE id_quartier = ?');
        $stmt->execute([$id_quartier]);
        $quartiers = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($quartiers && isset($quartiers['quartier'])) {
    $quartier = $quartiers['quartier'];

    // Mettre en cache
    Cache::set($cacheKey, $quartier);

        return $quartier;
        } else {
            return ""; // Aucun quartier trouvé
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du quartier : " . $e->getMessage());
        return "";
    }
}


/**
 * Récupère l'adresse complète avec quartier et ville
 * @param int $id_quartier Identifiant du quartier
 * @return string Adresse complète formatée
 */
function adress($id_quartier) {
    global $bdd;
    
    // Vérifier le cache
    $cacheKey = "adress_" . $id_quartier;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT id_ville, quartier FROM adresses_quartiers WHERE id_quartier = ?');
        $stmt->execute([$id_quartier]);
        $quartier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$quartier) {
            return "";
        }
        
        $adresse = adresseville($quartier['id_ville']) . ", " . $quartier['quartier'];
        
        // Mettre en cache
        Cache::set($cacheKey, $adresse);
        
        return $adresse;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de l'adresse : " . $e->getMessage());
        return "";
    }
}


/**
 * Récupère le nom d'un traitement
 * @param int $id_type Identifiant du type de traitement
 * @return string Nom du traitement
 */
function model($id_type) {
    global $bdd;
    
    $cacheKey = "traitement_" . $id_type;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT nom_type FROM traitements WHERE id_type = ?');
        $stmt->execute([$id_type]);
        $result = $stmt->fetchColumn();
        
        $nom_type = $result ?: "";
        Cache::set($cacheKey, $nom_type);
        
        return $nom_type;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du traitement : " . $e->getMessage());
        return "";
    }
}

/**
 * Récupère le nom d'un traitement
 * @param int $id_type Identifiant du type de traitement
 * @return string type d'operation
 */
function operation($id_type) {
    global $bdd;
    
    $cacheKey = "operation_" . $id_type;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT operation FROM traitements WHERE id_type = ?');
        $stmt->execute([$id_type]);
        $result = $stmt->fetchColumn();
        
        $operation = $result ?: "";
        Cache::set($cacheKey, $operation);
        
        return $operation;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du type : " . $e->getMessage());
        return "";
    }
}

/**
 * Récupère les informations d'un patient
 * @param int $id_patient Identifiant du patient
 * @return array Informations du patient
 */
function getPatientInfo($id_patient) {
    global $bdd;
    
    $empty_patient = [
        'id_patient' => '',
        'nom_patient' => '',
        'phone' => '',
        'date_recu' => '',
        'adresse' => '',
        'id_quartier' => '',
        'age' => '',
        'sexe' => '',
        'assure' => '',
        'assurance' => '',
        'responsable' => '',
        'profession' => ''
    ];
    
    if (!$id_patient) {
        return $empty_patient;
    }
    
    $cacheKey = "patient_" . $id_patient;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT * FROM patients WHERE id_patient = ?');
        $stmt->execute([$id_patient]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($patient) {
            Cache::set($cacheKey, $patient);
            return $patient;
        }
        
        error_log("Patient non trouvé : ID " . $id_patient);
        return $empty_patient;
        
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du patient (ID: $id_patient): " . $e->getMessage());
        return $empty_patient;
    }
}

/**
 * Fonctions simplifiées utilisant getPatientInfo
 */
function nom_patient($id_patient) {
    $patient = getPatientInfo($id_patient);
    return $patient ? $patient['nom_patient'] : "";
}

function return_phone($id_patient) {
    $patient = getPatientInfo($id_patient);
    return $patient ? $patient['phone'] : "";
}

function return_date($id_patient) {
    $patient = getPatientInfo($id_patient);
    return $patient ? $patient['date_recu'] : "";
}
function return_adresse($id_patient) {
    $patient = getPatientInfo($id_patient);
    return $patient ? $patient['adresse'] : "";
}

function return_age($id_patient) {
    $patient = getPatientInfo($id_patient);
    return $patient ? $patient['age'] : null;
}
function return_sexe($id_patient) {
    $patient = getPatientInfo($id_patient);
    return $patient ? $patient['sexe'] : null;
}
function return_assure($id_patient) {
    $patient = getPatientInfo($id_patient);
    return $patient ? $patient['assure'] : null;
}
function return_assurance($id_patient) {
    $patient = getPatientInfo($id_patient);
    return $patient ? $patient['assurance'] : null;
}
function return_responsable($id_patient) {
    $patient = getPatientInfo($id_patient);
    return $patient ? $patient['responsable'] : null;
}
function return_profession($id_patient) {
    $patient = getPatientInfo($id_patient);
    return $patient ? $patient['profession'] : null;
}

/**
 * Récupère les informations d'une assurance
 * @param int $id_assurance Identifiant de l'assurance
 * @return string Nom de l'assurance
 */
function assurance($id_assurance) {
    global $bdd;
    
    $cacheKey = "assurance_" . $id_assurance;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT assurance FROM assurance WHERE id_assurance = ?');
        $stmt->execute([$id_assurance]);
        $result = $stmt->fetchColumn();
        
        $assurance = $result ?: "";
        Cache::set($cacheKey, $assurance);
        
        return $assurance;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de l'assurance : " . $e->getMessage());
        return "";
    }
}

/**
 * Récupère les informations d'un service
 * @param int $id_service Identifiant du service
 * @return string Nom du service
 */
function service($id_service) {
    global $bdd;
    
    $cacheKey = "service_" . $id_service;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT celulle FROM organigramme WHERE id_organigramme = ?');
        $stmt->execute([$id_service]);
        $result = $stmt->fetchColumn();
        
        $service = $result ?: "";
        Cache::set($cacheKey, $service);
        
        return $service;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du service : " . $e->getMessage());
        return "";
    }
}

/**
 * Récupère les informations d'un compte
 * @param int $id_compte Identifiant du compte
 * @return string Type de compte
 */
function compte($id_compte) {
    global $bdd;
    
    $cacheKey = "compte_" . $id_compte;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT nom_compte FROM comptes WHERE id_compte = ?');
        $stmt->execute([$id_compte]);
        $result = $stmt->fetchColumn();
        
        $compte = $result ?: "";
        Cache::set($cacheKey, $compte);

        return $compte;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du compte : " . $e->getMessage());
        return "";
    }
}

function type_compte($id_compte) {
    global $bdd;

    $cacheKey = "type_compte_" . $id_compte;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT types FROM comptes WHERE id_compte = ?');
        $stmt->execute([$id_compte]);
        $result = $stmt->fetchColumn();
        
        $compte = $result ?: "";
        Cache::set($cacheKey, $compte);
        
        return $compte;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du compte : " . $e->getMessage());
        return "";
    }
}

// La fonction determinerStatutAssurance reste inchangée car elle est déjà optimale
function determinerStatutAssurance($assure) {
    return $assure == 0 ? "non assuré" : "assuré";
}

/**
 * Récupère le type de paiement à partir de son ID
 * @param int $id_compte Identifiant du compte
 * @return string Type de paiement
 */
function type_paiement($id_compte) {
    global $bdd;
    
    $cacheKey = "type_paiement_" . $id_compte;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT types FROM comptes WHERE id_compte = ?');
        $stmt->execute([$id_compte]);
        $type = $stmt->fetchColumn();
        
        $resultat = $type ?: "";
        Cache::set($cacheKey, $resultat);
        
        return $resultat;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du type de paiement : " . $e->getMessage());
        return "";
    }
}

/**
 * Récupère le type de paiement à partir de son ID
 * @param int $id_compte Identifiant du compte
 * @return string Type de paiement
 */
function IsPaiementElectronique($id_compte) {
    global $bdd;
    
    $cacheKey = "IsPaiementElectronique_" . $id_compte;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT electronique FROM comptes WHERE id_compte = ?');
        $stmt->execute([$id_compte]);
        $type = $stmt->fetchColumn();
        
        $resultat = $type ?: "";
        Cache::set($cacheKey, $resultat);
        
        return $resultat;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du type de paiement : " . $e->getMessage());
        return "";
    }
}

/**
 * Récupère le nom d'un type de traitement
 * @param int $id_type Identifiant du type de traitement
 * @return string Nom du type de traitement
 */

function type_traitement($id_type) {
    global $bdd;
    
    $cacheKey = "type_traitement_" . $id_type;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT nom_type FROM traitements WHERE id_type = ?');
        $stmt->execute([$id_type]);
        $resultat = $stmt->fetchColumn();
        
        $nom_type = $resultat ?: "";
        Cache::set($cacheKey, $nom_type);
        
        return $nom_type;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du type de traitement : " . $e->getMessage());
        return "";
    }
}
 
/**
 * Récupère le montant d'une affectation
 * @param int $id_affectation Identifiant de l'affectation
 * @return float Montant de l'affectation
 */
function montant($id_type) {
    global $bdd;

    $cacheKey = "montant_" . $id_type;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT montant FROM traitements WHERE id_type = ?');
        $stmt->execute([$id_type]);
        $resultat = $stmt->fetchColumn();
        
        $montant = $resultat !== false ? floatval($resultat) : 0.0;
        Cache::set($cacheKey, $montant);
        
        return $montant;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du montant : " . $e->getMessage());
        return 0.0;
    }
}

/**
 * Récupère les informations d'une affectation
 * @param int $id_affectation Identifiant de l'affectation
 * @return array|null Informations de l'affectation ou null si non trouvée
 */
function return_affectation($id_affectation) {
    global $bdd;
    
    $cacheKey = "affectation_" . $id_affectation;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT * FROM affectations WHERE id_affectation = ?');
        $stmt->execute([$id_affectation]);
        $affectation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($affectation) {
            Cache::set($cacheKey, $affectation);
            return $affectation;
        }
        
        return null;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de l'affectation : " . $e->getMessage());
        return null;
    }
}

/**
 * Récupère le pseudo d'un responsable
 * @param int $id_user Identifiant de l'utilisateur
 * @return string Pseudo du responsable
 */
function responsable($id_user) {
    global $bdd;
    
    $cacheKey = "responsable_" . $id_user;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT pseudo FROM users WHERE id = ?');
        $stmt->execute([$id_user]);
        $result = $stmt->fetchColumn();
        
        $responsable = $result ?: "";
        Cache::set($cacheKey, $responsable);
        
        return $responsable;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du responsable : " . $e->getMessage());
        return "";
    }
}

/**
 * Récupère l'année d'engagement d'un utilisateur
 * @param int $id_user Identifiant de l'utilisateur
 * @return string Date d'engagement
 */
function return_annee($id_user) {
    global $bdd;
    
    $cacheKey = "annee_engagement_" . $id_user;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT date_engagement FROM users WHERE id = ?');
        $stmt->execute([$id_user]);
        $result = $stmt->fetchColumn();
        
        $annee = $result ?: "";
        Cache::set($cacheKey, $annee);
        
        return $annee;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de la date d'engagement : " . $e->getMessage());
        return "";
    }
}

/**
 * Récupère le pseudo d'un utilisateur traitant
 * @param int $id_user Identifiant de l'utilisateur traitant
 * @return string Pseudo de l'utilisateur
 */
function traitant($id_user) {
    global $bdd;
    
    $cacheKey = "traitant_" . $id_user;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT pseudo FROM users WHERE id = ?');
        $stmt->execute([$id_user]);
        $result = $stmt->fetchColumn();
        
        $username = $result ?: "";
        Cache::set($cacheKey, $username);
        
        return $username;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du traitant : " . $e->getMessage());
        return "";
    }
}

/**
 * Récupère le nom d'un type de traitement
 * @param int $id_type Identifiant du type de traitement
 * @return string Nom du type de traitement
 */
function calculerAge($dateNaissance) {
    $diff = abs(strtotime(date('Y-m-d')) - strtotime($dateNaissance));
    return floor($diff / (365 * 24 * 60 * 60));
}

function consentement($id_type) {
    global $bdd;
    
    $cacheKey = "consentement_" . $id_type;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT operation FROM traitements WHERE id_type = ?');
        $stmt->execute([$id_type]);
        $resultat = $stmt->fetchColumn();
        
        $consentement = $resultat ?: "";
        Cache::set($cacheKey, $consentement);
        
        return $consentement;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du consentement : " . $e->getMessage());
        return "";
    }
}
/**
 * Récupère le nom d'un type de service
 * @param int $operation 
 * @return string consentement
 */


function model_produits($id_model) {
    global $bdd;
    
    // Vérifier le cache
    $cacheKey = "model_produits_" . $id_model;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT marque FROM marques WHERE id_marque = ?');
        $stmt->execute([$id_model]);
        $result = $stmt->fetchColumn();
        
        $nom_model = $result ?: "";
        Cache::set($cacheKey, $nom_model);
        
        return $nom_model;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du modèle : " . $e->getMessage());
        return "";
    }
}

/**
 * Récupère le nom de la catégorie de produit
 * @param int $id_categorie Identifiant de la catégorie
 * @return string Nom de la catégorie
 */
function categorie($id_categorie) {
    global $bdd;
    
    // Vérifier le cache
    $cacheKey = "categorie_" . $id_categorie;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT categorie FROM categorie_produits WHERE id_categorie = ?');
        $stmt->execute([$id_categorie]);
        $result = $stmt->fetchColumn();
        
        $categorie = $result ?: "";
        Cache::set($cacheKey, $categorie);
        
        return $categorie;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de la catégorie : " . $e->getMessage());
        return "";
    }
}

/**
 * Récupère le prix de vente d'un produit par catégorie
 * @param int $id_categorie Identifiant de la catégorie
 * @return float Prix de vente
 */
function prix($id_categorie) {
    global $bdd;
    
    // Vérifier le cache
    $cacheKey = "prix_" . $id_categorie;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT prix_vente FROM categorie_produits WHERE id_categorie = ?');
        $stmt->execute([$id_categorie]);
        $result = $stmt->fetchColumn();
        
        $prix = $result !== false ? (float)$result : 0.0;
        Cache::set($cacheKey, $prix);
        
        return $prix;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du prix : " . $e->getMessage());
        return 0.0;
    }
}

/**
 * Génère un numéro de paiement unique
 * @return string Numéro de paiement
 */
function genererNumeroPaiement() {
    return strtoupper("EC" . substr(uniqid(mt_rand(), true), -4));
}

/**
 * Récupère une seule ligne d'une table
 * @param PDO $bdd Instance de la connexion à la base de données
 * @param string $table Nom de la table
 * @return array|null Données de la première ligne ou null si aucune ligne trouvée
 */
function getSingleRow(PDO $bdd, string $table) {
    $stmt = $bdd->prepare("SELECT * FROM $table LIMIT 1");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserInfo(PDO $bdd, $userId) {
    $stmt = $bdd->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function nombrejour(PDO $bdd, $nom) {
    $stmt = $bdd->prepare('SELECT COUNT(*) FROM paiements WHERE types = ? AND remboursement = ? AND datepaiement = ?');
    $stmt->execute([$nom, 0, date('Y-m-d')]);
    return (int)$stmt->fetchColumn();
}

// Surcharge pour compatibilité : permet d'appeler nombrejour($nom) sans passer $bdd
function nombrejour_simple($nom) {
    global $bdd;
    return nombrejour($bdd, $nom);
}

function getRdvInfo(PDO $bdd, $rdv_id) {
    $stmt = $bdd->prepare('SELECT * FROM dmd_rendez_vous WHERE id_rdv = ?');
    $stmt->execute([$rdv_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// fonction de recuperation de la dernière acquittée et de l'historique

function recupererDerniereAcquiteEtHistorique(PDO $bdd, int $id_patient) {
    $stmt = $bdd->prepare('
        SELECT av.*, h.motif, h.evolution, h.terrain, h.antecedents
        FROM acquitte_visuelle av
        LEFT JOIN historique h ON h.id_patient = av.id_patient
        WHERE av.id_patient = ?
        ORDER BY av.id_acquitte DESC, h.id_historique DESC
        LIMIT 1
    ');

    $stmt->execute([$id_patient]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


// fin des fonctions
/**
 * Récupère la disponibilité (débit) d'un compte
 * @param int $id_compte Identifiant du compte
 * @return string Disponibilité du compte
 */
function disponibilite($id_compte) {
    global $bdd;
    $cacheKey = "disponibilite_" . $id_compte;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    try {
        $stmt = $bdd->prepare('SELECT debit FROM comptes WHERE id_compte = ?');
        $stmt->execute([$id_compte]);
        $disponibilite = $stmt->fetchColumn();
        $disponibilite = $disponibilite !== false ? $disponibilite : "";
        Cache::set($cacheKey, $disponibilite);
        return $disponibilite;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de la disponibilité : " . $e->getMessage());
        return "";
    }
}

/**
 * Récupère le code d'un compte
 * @param int $id_compte Identifiant du compte
 * @return string Code du compte
 */
function code($id_compte) {
    global $bdd;
    $cacheKey = "code_" . $id_compte;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    try {
        $stmt = $bdd->prepare('SELECT code FROM comptes WHERE id_compte = ?');
        $stmt->execute([$id_compte]);
        $code = $stmt->fetchColumn();
        $code = $code !== false ? $code : "";
        Cache::set($cacheKey, $code);
        return $code;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du code du compte : " . $e->getMessage());
        return "";
    }
}
function nombrejour_periode($id_type, $date_debut, $date_fin) {
    global $bdd;
    $stmt = $bdd->prepare('SELECT COUNT(*) FROM paiements WHERE types = ? AND remboursement = 0 AND datepaiement BETWEEN ? AND ?');
    $stmt->execute([$id_type, $date_debut, $date_fin]);
    return (int)$stmt->fetchColumn();
}

function nombrejourPeriodeCompte($id_type, $date_debut, $date_fin, $id_compte) {
    global $bdd;
    $stmt = $bdd->prepare('SELECT COUNT(*) FROM paiements WHERE types = ? AND remboursement = 0 AND datepaiement BETWEEN ? AND ? AND compte = ?');
    $stmt->execute([$id_type, $date_debut, $date_fin, $id_compte]);
    return (int)$stmt->fetchColumn();
}
// Extraction des premiers mots d'un texte
function extrairePremiersMots($texte, $nombre = 10) {
    $texte = strip_tags($texte); // Supprime les balises HTML
    $mots = preg_split('/\s+/', trim($texte)); // Sépare les mots proprement

    if (count($mots) > $nombre) {
        $mots = array_slice($mots, 0, $nombre);
        return implode(' ', $mots) . '...';
    }

    return implode(' ', $mots);
}

/**
 * Récupère le nom d'un produit par son ID
 * @param int $id_produit Identifiant du produit
 * @return string Nom du produit
 */


 // Fonction pour récupérer la somme des montants de preuvedecaisse pour un compte et une période donnée

 function getEntreePreuve($compte, $debut, $fin, $bdd) {
    $stmt = $bdd->prepare('SELECT SUM(montant) AS entreePreuve FROM preuvedecaisse WHERE compte = ? AND date_rapportement BETWEEN ? AND ?');
    $stmt->execute([$compte, $debut, $fin]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row && isset($row['entreePreuve']) ? $row['entreePreuve'] : 0;
}

 // Fonction pour récupérer la somme des montants de entree compte pour un compte et une période donnée
function getEntreePaiements($compte, $debut, $fin, $bdd) {
    // Utiliser le montant réellement payé (montant_paye) quand disponible.
    $expr = 'montant';
    try {
        if (function_exists('dbTableHasColumn') && dbTableHasColumn($bdd, 'paiements', 'montant_paye')) {
            $expr = 'COALESCE(montant_paye, montant)';
        }
    } catch (Throwable $e) {
        $expr = 'montant';
    }

    $stmt = $bdd->prepare('SELECT SUM(' . $expr . ') AS entree FROM paiements WHERE remboursement=0 AND compte = ? AND datepaiement BETWEEN ? AND ?');
    $stmt->execute([$compte, $debut, $fin]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row && isset($row['entree']) ? $row['entree'] : 0;
}


/// Pour la boutique 


// Fonctions utilitaires pour éviter la duplication
function updateQuantiteModel($bdd, $model) {
    $reponse = $bdd->prepare('SELECT quantite FROM model_produits WHERE id_model=?');
    $reponse->execute([$model]);
    $quantite = $reponse->fetchColumn();
    if ($quantite !== false) {
        $quantite -= 1;
        $req = $bdd->prepare('UPDATE model_produits SET quantite=? WHERE id_model=?');
        $req->execute([$quantite, $model]);
    }
}

function updateQuantiteCategorie($bdd, $categorie) {
    $reponse = $bdd->prepare('SELECT quantite FROM categorie_produits WHERE id_categorie=?');
    $reponse->execute([$categorie]);
    $quantite = $reponse->fetchColumn();
    if ($quantite !== false) {
        $quantite -= 1;
        $req = $bdd->prepare('UPDATE categorie_produits SET quantite=? WHERE id_categorie=?');
        $req->execute([$quantite, $categorie]);
    }
}

function updateProduitVendu($bdd, $categorie, $codeproduit) {
    $req = $bdd->prepare('UPDATE produits SET id_categorie=?, vendu=1 WHERE code_produit=?');
    $req->execute([$categorie, $codeproduit]);
}

function updateCollaborateurDebit($bdd, $collaborateur, $montant) {
    $reponse = $bdd->prepare('SELECT debit FROM collaborateurs WHERE id_collaborateur=?');
    $reponse->execute([$collaborateur]);
    $debit = $reponse->fetchColumn();
    if ($debit !== false) {
        $debit += $montant;
        $req = $bdd->prepare('UPDATE collaborateurs SET debit=? WHERE id_collaborateur=?');
        $req->execute([$debit, $collaborateur]);
    }
}

function updateCompteDebit($bdd, $compte, $montant) {
    $reponse = $bdd->prepare('SELECT debit FROM comptes WHERE id_compte=?');
    $reponse->execute([$compte]);
    $debit = $reponse->fetchColumn();
    if ($debit !== false) {
        $debit += $montant;
        $req = $bdd->prepare('UPDATE comptes SET debit=? WHERE id_compte=?');
        $req->execute([$debit, $compte]);
    }
}

function paiementDejaEffectue($bdd, $affectation) {
    $req = $bdd->prepare('SELECT COUNT(*) FROM paiements WHERE id_affectation=?');
    $req->execute([$affectation]);
    return $req->fetchColumn() > 0;
}

// fonction pour recuperer le numéro de paiement
function getNumeroPaiement($bdd, $id_affectation) {
    $req = $bdd->prepare('SELECT code FROM paiements WHERE id_affectation=?');
    $req->execute([$id_affectation]);
    return $req->fetchColumn();
}

// insertion de rendez-vous interne

function insererRendezVousInterne($bdd, $id_patient, $service, $motif, $medecin, $prochain_rdv) {
    $req = $bdd->prepare('INSERT INTO dmd_rendez_vous (id_patient, id_service, motif, traitant, prochain_rdv) VALUES (?, ?, ?, ?, ?)');
    $req->execute([
        $id_patient,
        $service,
        $motif,
        $medecin,
        $prochain_rdv
    ]);
}

//// insertion de rendez-vous Externe

function insererRendezVousExterne($bdd, $id_patient, $service, $motif, $medecin, $prochain_rdv, $type_patient) {
    $req = $bdd->prepare('INSERT INTO dmd_rendez_vous (id_patient, id_service, motif, traitant, prochain_rdv, type_patient) VALUES (?, ?, ?, ?, ?, ?)');
    $req->execute([
        $id_patient,
        $service,
        $motif,
        $medecin,
        $prochain_rdv,
        $type_patient
    ]);
}

// Helpers DB : vérifie l'existence d'une colonne (utile pour déploiement progressif)
function dbTableHasColumn(PDO $bdd, string $table, string $column): bool {
    static $cache = [];
    $key = $table . ':' . $column;
    if (array_key_exists($key, $cache)) {
        return (bool)$cache[$key];
    }
    try {
        // Utilise information_schema pour éviter les soucis de droits/quoting sur SHOW COLUMNS
        $stmt = $bdd->prepare(
            'SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        $cache[$key] = (bool)$stmt->fetchColumn();
        return (bool)$cache[$key];
    } catch (Throwable $e) {
        error_log('[dbTableHasColumn] ' . $table . '.' . $column . ' => ' . $e->getMessage());
        $cache[$key] = false;
        return false;
    }
}

function getDemandeEnAttenteById(PDO $bdd, int $id_demande): ?array {
    $stmt = $bdd->prepare('SELECT * FROM dossier_en_attente WHERE id_demande = ? LIMIT 1');
    $stmt->execute([$id_demande]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function findPatientIdByExternalIdentity(PDO $bdd, array $data): ?int {
    $stmt = $bdd->prepare('SELECT id_patient FROM patients WHERE phone = ? AND profession = ? AND sexe = ? AND adresse = ? LIMIT 1');
    $stmt->execute([
        $data['phone'] ?? null,
        $data['profession'] ?? null,
        $data['sexe'] ?? null,
        $data['adresse'] ?? null,
    ]);
    $id = $stmt->fetchColumn();
    if ($id === false || $id === null || $id === '') {
        return null;
    }
    return (int)$id;
}

function findOrCreateDemandeEnAttente(PDO $bdd, array $data): int {
    $stmt = $bdd->prepare('SELECT id_demande FROM dossier_en_attente WHERE phone = ? AND profession = ? AND sexe = ? AND adresse = ? LIMIT 1');
    $stmt->execute([
        $data['phone'] ?? null,
        $data['profession'] ?? null,
        $data['sexe'] ?? null,
        $data['adresse'] ?? null,
    ]);
    $existing = $stmt->fetchColumn();
    if ($existing !== false && $existing !== null && $existing !== '') {
        return (int)$existing;
    }

    $stmt = $bdd->prepare('INSERT INTO dossier_en_attente (nom_patient, sexe, profession, age, adresse, phone, responsable, assure, assurance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $data['nom_patient'] ?? '',
        $data['sexe'] ?? '',
        $data['profession'] ?? '',
        $data['age'] ?? null,
        $data['adresse'] ?? '',
        $data['phone'] ?? null,
        $data['responsable'] ?? null,
        (int)($data['assure'] ?? 0),
        (int)($data['assurance'] ?? 0),
    ]);
    return (int)$bdd->lastInsertId();
}

function createOrFindPatientFromDemandeWithStatus(PDO $bdd, array $demandeRow): array {
    $existingId = findPatientIdByExternalIdentity($bdd, $demandeRow);
    if ($existingId) {
        return ['id_patient' => (int)$existingId, 'created' => false];
    }

    $stmt = $bdd->prepare('INSERT INTO patients (nom_patient, sexe, profession, age, adresse, phone, responsable, assure, assurance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $demandeRow['nom_patient'] ?? '',
        $demandeRow['sexe'] ?? '',
        $demandeRow['profession'] ?? '',
        $demandeRow['age'] ?? null,
        $demandeRow['adresse'] ?? '',
        $demandeRow['phone'] ?? null,
        $demandeRow['responsable'] ?? null,
        (int)($demandeRow['assure'] ?? 0),
        (int)($demandeRow['assurance'] ?? 0),
    ]);
    return ['id_patient' => (int)$bdd->lastInsertId(), 'created' => true];
}

function createOrFindPatientFromDemande(PDO $bdd, array $demandeRow): int {
    $res = createOrFindPatientFromDemandeWithStatus($bdd, $demandeRow);
    return (int)($res['id_patient'] ?? 0);
}

function insererRendezVousExterneEnAttente(PDO $bdd, int $id_demande, $service, $motif, $medecin, $prochain_rdv, $type_patient): void {
    if (!dbTableHasColumn($bdd, 'dmd_rendez_vous', 'id_demande')) {
        throw new Exception("La colonne dmd_rendez_vous.id_demande est requise pour stocker un RDV externe en attente.");
    }
    $req = $bdd->prepare('INSERT INTO dmd_rendez_vous (id_patient, id_demande, id_service, motif, traitant, prochain_rdv, type_patient) VALUES (NULL, ?, ?, ?, ?, ?, ?)');
    $req->execute([
        $id_demande,
        $service,
        $motif,
        $medecin,
        $prochain_rdv,
        $type_patient
    ]);
}

// date en français

/*
function dateEnFrancais($date) {
        setlocale(LC_TIME, 'fr_FR.UTF-8'); // Définir la locale en français
        return strftime('%d %B %Y', strtotime($date)); // Formater la date en "jour mois année"
}
*/
function dateEnFrancais($date) {
    try {
        $dt = new DateTime($date);
    } catch (Exception $e) {
        return (string)$date;
    }
    if (class_exists('IntlDateFormatter')) {
        $fmt = new IntlDateFormatter(
            'fr_FR',
            IntlDateFormatter::LONG,
            IntlDateFormatter::NONE,
            'Europe/Paris',
            IntlDateFormatter::GREGORIAN
        );
        return $fmt->format($dt);
    }
    $mois = [1=>'janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
    $j = (int)$dt->format('j');
    $m = (int)$dt->format('n');
    $y = $dt->format('Y');
    return $j.' '.$mois[$m].' '.$y;
}


// fonction de recupération de l'information du patient ID dans RDV
function getPatientIdByRdv(PDO $bdd, $rdv_id) {
    try {
        $stmt = $bdd->prepare('SELECT id_patient FROM dmd_rendez_vous WHERE id_rdv = ?');
        $stmt->execute([$rdv_id]);
        $result = $stmt->fetchColumn();
        if ($result === false || $result === null || $result === '') {
            return null;
        }
        return (int)$result;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de l'id_patient pour le rdv $rdv_id : " . $e->getMessage());
        return null;
    }
}

// fonction pour recuperer le l'affectation du patient à partir de son id_rdv dans affectation
function getAffectationIdByRdv(PDO $bdd, $rdv_id) {
    try {
        $stmt = $bdd->prepare('SELECT id_affectation FROM affectations WHERE id_rdv = ?');
        $stmt->execute([$rdv_id]);
        $result = $stmt->fetchColumn();
        return $result !== false ? (int)$result : null;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de l'id_affectation pour le rdv $rdv_id : " . $e->getMessage());
        return null;
    }
}

/**
 * Génère une référence automatique pour les rapportements médicaux
 * Format : COEC/DG/DC/25/10/01
 * 
 * - "COEC/DG/DC/" : préfixe fixe
 * - "25" : année en cours (2 chiffres)
 * - "10" : mois en cours (2 chiffres)
 * - "01" : numéro séquentiel (auto-incrémenté)
 * 
 * @param PDO $bdd Instance de la connexion à la base de données
 * @return string La nouvelle référence générée
 */
function genererReferenceRapportement(PDO $bdd): string
{
    // Constantes pour éviter la répétition
    static $prefixe = "COEC/DG/DC/";
    
    // Génération du pattern une seule fois
    $annee = date('y');
    $mois = date('m');
    $pattern = "{$prefixe}{$annee}/{$mois}/";
    
    try {
        // Requête optimisée avec MAX() pour éviter ORDER BY + LIMIT
        $sql = "SELECT MAX(CAST(SUBSTRING_INDEX(reference, '/', -1) AS UNSIGNED)) as max_num 
                FROM rapportements 
                WHERE reference LIKE CONCAT(:pattern, '%')";
        
        $stmt = $bdd->prepare($sql);
        $stmt->execute([':pattern' => $pattern]);
        
        // Récupération directe du numéro maximum + 1
        $maxNum = $stmt->fetchColumn();
        $numero = ($maxNum !== false && $maxNum !== null) ? $maxNum + 1 : 1;
        
        // Retour direct avec formatage
        return sprintf('%s%02d', $pattern, $numero);
        
    } catch (PDOException $e) {
        error_log("Erreur lors de la génération de la référence rapportement : " . $e->getMessage());
        // Fallback en cas d'erreur : utiliser timestamp
        return sprintf('%s%s/%s/ERR%s', $prefixe, $annee, $mois, substr(time(), -2));
    }
}

// Fonction pour récupérer la valeur d'un champ
function getFormValue($field, $default = '') {
    // Unifiée: priorité aux données POST pour pré-remplissage après soumission
    // puis éventuellement aux données préparées dans $formData.
    // Si $errors == 4 (succès traitement), on retourne chaîne vide pour ne pas ré-afficher l'ancienne saisie.
    global $formData, $errors;
    if (isset($errors) && $errors == 4) {
        return '';
    }
    if (isset($_POST[$field])) {
        return htmlspecialchars($_POST[$field], ENT_QUOTES, 'UTF-8');
    }
    if (isset($formData) && isset($formData[$field])) {
        return htmlspecialchars($formData[$field], ENT_QUOTES, 'UTF-8');
    }
    return $default;
}

// fonction pour l'encodage des textes pour FPDF
function pdf_text_compat($text): string {
    $text = (string)$text;
    if ($text === '') {
        return '';
    }

    // Si ce n'est pas du UTF-8 valide, on ne touche pas (probablement déjà en encodage mono-octet attendu par FPDF).
    if (!preg_match('//u', $text)) {
        return $text;
    }

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        if ($converted !== false) {
            return $converted;
        }
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
        if ($converted !== false) {
            return $converted;
        }
    }

    if (function_exists('mb_convert_encoding')) {
        $converted = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }
    }

    return $text;
}

if (!function_exists('appecEnsureAssuranceFacturationTables')) {
    /**
     * Crée (si nécessaire) les tables utilisées pour la facturation mensuelle des assurances.
     *
     * Note: On évite toute mise à jour d'une éventuelle colonne générée (ex: assurances.solde).
     */
    function appecEnsureAssuranceFacturationTables(PDO $bdd): void
    {
        // Table des créances (lignes) générées lors des paiements patient (part assurance)
        $bdd->exec(
            "CREATE TABLE IF NOT EXISTS assurance_creances (\n"
            . "  id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "  assurance_id INT NOT NULL,\n"
            . "  id_affectation INT NOT NULL,\n"
            . "  id_paiement INT NULL,\n"
            . "  patient_id INT NOT NULL,\n"
            . "  type_traitement INT NULL,\n"
            . "  code_paiement VARCHAR(50) NULL,\n"
            . "  date_operation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "  montant_total DECIMAL(12,2) NOT NULL DEFAULT 0,\n"
            . "  montant_assurance DECIMAL(12,2) NOT NULL DEFAULT 0,\n"
            . "  montant_patient DECIMAL(12,2) NOT NULL DEFAULT 0,\n"
            . "  taux_prise_en_charge DECIMAL(6,2) NOT NULL DEFAULT 0,\n"
            . "  created_by INT NULL,\n"
            . "  PRIMARY KEY (id),\n"
            . "  KEY idx_ac_assurance_date (assurance_id, date_operation),\n"
            . "  KEY idx_ac_affectation (id_affectation),\n"
            . "  KEY idx_ac_patient (patient_id)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // Table des règlements effectués par les assureurs (paiements reçus)
        $bdd->exec(
            "CREATE TABLE IF NOT EXISTS assurance_reglements (\n"
            . "  id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "  assurance_id INT NOT NULL,\n"
            . "  date_paiement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "  montant DECIMAL(12,2) NOT NULL DEFAULT 0,\n"
            . "  mode_paiement VARCHAR(50) NULL,\n"
            . "  reference VARCHAR(100) NULL,\n"
            . "  periode_debut DATE NULL,\n"
            . "  periode_fin DATE NULL,\n"
            . "  commentaire TEXT NULL,\n"
            . "  caisse INT NULL,\n"
            . "  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "  PRIMARY KEY (id),\n"
            . "  KEY idx_ar_assurance_date (assurance_id, date_paiement),\n"
            . "  KEY idx_ar_periode (periode_debut, periode_fin)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // Colonnes optionnelles (ajouts non destructifs)
        // - compte_id : compte de caisse utilisé lors du règlement
        // - preuve : chemin relatif du document justificatif
        if (function_exists('dbTableHasColumn')) {
            try {
                if (!dbTableHasColumn($bdd, 'assurance_reglements', 'compte_id')) {
                    $bdd->exec('ALTER TABLE assurance_reglements ADD COLUMN compte_id INT NULL AFTER montant');
                    $bdd->exec('CREATE INDEX idx_ar_compte ON assurance_reglements (compte_id)');
                }
            } catch (Throwable $e) {
                // ignore
            }

            try {
                if (!dbTableHasColumn($bdd, 'assurance_reglements', 'preuve')) {
                    $bdd->exec('ALTER TABLE assurance_reglements ADD COLUMN preuve VARCHAR(255) NULL AFTER commentaire');
                }
            } catch (Throwable $e) {
                // ignore
            }
        }
    }
}

if (!function_exists('appecEnsurePartAssurancesTable')) {
    /**
     * Crée (si nécessaire) la table `partAssurances`.
     * Schéma demandé : id_part, id_paiement, id_affectation, types, montant, montant_paye, solde (GENERATED), patient, datepaiement.
     */
    function appecEnsurePartAssurancesTable(PDO $bdd): void
    {
        // Certains MySQL n'acceptent pas CURRENT_TIMESTAMP sur un champ DATE.
        // On tente d'abord la version demandée, puis fallback si erreur.
        $sql1 = "CREATE TABLE IF NOT EXISTS partAssurances (\n"
            . "  id_part INT(11) NOT NULL AUTO_INCREMENT,\n"
            . "  id_paiement INT(11) NOT NULL,\n"
            . "  id_affectation INT(11) NOT NULL,\n"
            . "  types INT(11) NOT NULL,\n"
            . "  montant DECIMAL(15,0) NOT NULL,\n"
            . "  montant_paye DECIMAL(15,0) NOT NULL DEFAULT 0,\n"
            . "  solde DECIMAL(15,0) GENERATED ALWAYS AS (COALESCE(montant,0) - COALESCE(montant_paye,0)) STORED,\n"
            . "  patient INT(11) NOT NULL,\n"
            . "  datepaiement DATE NOT NULL DEFAULT (CURRENT_TIMESTAMP),\n"
            . "  PRIMARY KEY (id_part),\n"
            . "  KEY idx_pa_patient_date (patient, datepaiement),\n"
            . "  KEY idx_pa_affectation (id_affectation),\n"
            . "  KEY idx_pa_paiement (id_paiement)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $sql2 = "CREATE TABLE IF NOT EXISTS partAssurances (\n"
            . "  id_part INT(11) NOT NULL AUTO_INCREMENT,\n"
            . "  id_paiement INT(11) NOT NULL,\n"
            . "  id_affectation INT(11) NOT NULL,\n"
            . "  types INT(11) NOT NULL,\n"
            . "  montant DECIMAL(15,0) NOT NULL,\n"
            . "  montant_paye DECIMAL(15,0) NOT NULL DEFAULT 0,\n"
            . "  solde DECIMAL(15,0) GENERATED ALWAYS AS (COALESCE(montant,0) - COALESCE(montant_paye,0)) STORED,\n"
            . "  patient INT(11) NOT NULL,\n"
            . "  datepaiement DATE NOT NULL DEFAULT (CURRENT_DATE),\n"
            . "  PRIMARY KEY (id_part),\n"
            . "  KEY idx_pa_patient_date (patient, datepaiement),\n"
            . "  KEY idx_pa_affectation (id_affectation),\n"
            . "  KEY idx_pa_paiement (id_paiement)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        try {
            $bdd->exec($sql1);
        } catch (Throwable $e) {
            $bdd->exec($sql2);
        }
    }
}

?>