<?php
// ../public/getPersonnel.php
// Objectif: retourner le personnel (users) du service (organigramme) demandé.
header('Content-Type: application/json; charset=utf-8');
include('../public/connect.php');

function table_has_column(PDO $bdd, string $table, string $column): bool {
    try {
        $bdd->query('SELECT `' . str_replace('`', '', $column) . '` FROM `' . str_replace('`', '', $table) . '` LIMIT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function resolve_first_existing_column(PDO $bdd, string $table, array $candidates): ?string {
    foreach ($candidates as $col) {
        if (table_has_column($bdd, $table, $col)) {
            return $col;
        }
    }
    return null;
}

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
    if ($serviceId <= 0) {
        echo json_encode([
            'success'  => false,
            'message'  => 'ID du service invalide',
            'medecins' => []
        ]);
        exit;
    }

    // Règle: on affiche les employés du département choisi (users.id_service = service)
    $query = $bdd->prepare(
        'SELECT id, pseudo, type FROM users WHERE status = 1 AND id_service = :serviceId ORDER BY pseudo ASC'
    );
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
