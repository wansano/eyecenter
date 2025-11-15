<?php
// ../public/getMedecins.php
header('Content-Type: application/json; charset=utf-8');
include('../public/connect.php');

try {
    if (empty($_GET['service'])) {
        echo json_encode([
            'success'  => false,
            'message'  => 'ID du service manquant',
            'medecins' => []
        ]);
        exit;
    }

    $serviceId = (int) $_GET['service'];

    // Ici type correspond directement à l'id_organigramme du service
    $query = $bdd->prepare("
        SELECT id, pseudo
        FROM users
        WHERE status = 1
          AND type = :serviceId
        ORDER BY pseudo ASC
    ");
    $query->execute(['serviceId' => $serviceId]);

    $medecins = $query->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'  => true,
        'count'    => count($medecins),
        'medecins' => $medecins
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success'  => false,
        'message'  => 'Erreur serveur : ' . $e->getMessage(),
        'medecins' => []
    ]);
}
