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
 * Récupère les informations d'une ville
 * @param int $id_ville Identifiant de la ville
 * @return string Adresse formatée de la ville
 */
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
        
        $quartier = $quartiers['quartier'];
        
        // Mettre en cache
        Cache::set($cacheKey, $quartier);
        
        return $quartier;
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
        $stmt = $bdd->prepare('SELECT nom_service FROM services WHERE id_service = ?');
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

/**
 * Récupère les informations d'un caissier
 * @param int $id_user Identifiant de l'utilisateur
 * @return string Pseudo du caissier
 */
function caissier($id_user) {
    global $bdd;
    
    $cacheKey = "caissier_" . $id_user;
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }
    
    try {
        $stmt = $bdd->prepare('SELECT pseudo FROM users WHERE id = ?');
        $stmt->execute([$id_user]);
        $result = $stmt->fetchColumn();
        
        $caissier = $result ?: "";
        Cache::set($cacheKey, $caissier);
        
        return $caissier;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du caissier : " . $e->getMessage());
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
    $stmt = $bdd->prepare('SELECT COUNT(*) FROM paiements WHERE types = ? AND remboursement = 0 AND datepaiement = ?');
    $stmt->execute([$nom, date('Y-m-d')]);
    return (int)$stmt->fetchColumn();
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

// Insertion de rendez-vous externe et interne

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

// fin des fonctions