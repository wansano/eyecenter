<?php
header('Content-Type: application/json');

// Inclure la connexion à la base de données
include('../PUBLIC/connect.php');

// Vérifier si l'ID de la ville est passé
if (isset($_GET['ville'])) {
    $villeId = intval($_GET['ville']);

    // Préparer la requête pour récupérer les quartiers liés à la ville
    $query = $bdd->prepare('SELECT id_quartier, quartier FROM adresses_quartiers WHERE id_ville = ?');
    $query->execute([$villeId]);

    $quartiers = [];
    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $quartiers[] = [
            'id' => $row['id_quartier'],
            'nom' => $row['quartier']
        ];
    }

    echo json_encode([
        'success' => true,
        'quartier' => $quartiers
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Ville ID manquant'
    ]);
}
?>
