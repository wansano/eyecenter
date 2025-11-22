<?php
header('Content-Type: application/json; charset=utf-8');

// Inclure connect.php avec chemin absolu
$connectPath = __DIR__ . '/connect.php';
if (!file_exists($connectPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Fichier connect.php non trouvé']);
    exit;
}

require_once $connectPath;

// Vérifier que la connexion DB existe
if (!isset($bdd)) {
    error_log('Erreur: $bdd non défini dans getMedecinsRdvByDate.php');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Connexion DB non disponible']);
    exit;
}

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

try {
    $sql = "SELECT DISTINCT dr.traitant AS id, COALESCE(u.pseudo, CONCAT('#', dr.traitant)) AS pseudo
            FROM dmd_rendez_vous dr
            LEFT JOIN users u ON u.id = dr.traitant
            WHERE DATE(dr.prochain_rdv) = ? AND dr.status IN (0,1,2)
            ORDER BY u.pseudo";
    $st = $bdd->prepare($sql);
    $st->execute([$date]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'medecins' => $rows, 'debug' => ['date' => $date]]);
} catch (Throwable $e) {
    error_log('getMedecinsRdvByDate error: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
