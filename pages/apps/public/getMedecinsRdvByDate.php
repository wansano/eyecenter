<?php
header('Content-Type: application/json; charset=utf-8');

try {
    // Inclure connect.php depuis le même dossier
    @require_once __DIR__ . '/connect.php';
    
    // Vérifier que la connexion DB existe
    if (!isset($bdd) || $bdd === null) {
        throw new Exception('Connexion DB non initialisée');
    }
    
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    
    // Valider le format de la date
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new Exception('Format de date invalide: ' . $date);
    }
    
    // Construire la requête SQL
    $sql = "SELECT DISTINCT dr.traitant AS id, 
            COALESCE(u.pseudo, CONCAT('#', dr.traitant)) AS pseudo
            FROM dmd_rendez_vous dr
            LEFT JOIN users u ON u.id = dr.traitant
            WHERE DATE(dr.prochain_rdv) = :date AND dr.status IN (0,1,2)
            ORDER BY u.pseudo";
    
    // Préparer et exécuter
    $st = $bdd->prepare($sql);
    if (!$st) {
        throw new Exception('Erreur préparation requête');
    }
    
    $st->execute([':date' => $date]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    
    // Retourner les résultats
    echo json_encode([
        'success' => true, 
        'medecins' => is_array($rows) ? $rows : []
    ]);
    exit;
    
} catch (Throwable $e) {
    error_log('getMedecinsRdvByDate error: ' . get_class($e) . ' - ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'trace' => getenv('APP_DEBUG') ? $e->getTraceAsString() : null
    ]);
    exit;
}
