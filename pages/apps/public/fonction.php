<?php
require_once('connect.php');


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
    // Tentative via iconv avec translit
    $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', (string)$str);
    if ($converted === false) {
        // Fallback via mb_convert_encoding
        $converted = mb_convert_encoding((string)$str, 'Windows-1252', 'UTF-8');
    }
    return $converted;
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
    $pdf->Image(realpath('../img/logo.jpg'), 152, $yPosition, 50, 25);
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
    $pdf->Image(realpath('../img/logo.jpg'), 100, $yPosition, 50, 25);
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
        $stmt = $bdd->prepare('SELECT model FROM model_produits WHERE id_model = ?');
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
    $stmt = $bdd->prepare('SELECT SUM(montant) AS entree FROM paiements WHERE remboursement=0 AND compte = ? AND datepaiement BETWEEN ? AND ?');
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
        return $result !== false ? (int)$result : null;
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
?>