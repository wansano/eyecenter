<?php
// ../public/getMedecin.php
// Objectif: retourner les médecins du service demandé + tous les médecins "globaux" (type = 4)
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

    // Sélection: tous status=1 où (type = service OU type = 4 (médecins globaux))
    // On renvoie aussi le type pour distinguer visuellement si besoin côté front.
    $query = $bdd->prepare("
        SELECT id, pseudo, type
        FROM users
        WHERE status = 1
          AND (type = :serviceId OR type = 4)
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
