<?php
header('Content-Type: application/json');

// Inclure la connexion à la base de données
include('../public/connect.php');

// Vérifier si l'ID du service est passé
if (isset($_GET['service'])) {
    $serviceId = intval($_GET['service']);

    // Préparer la requête pour récupérer les traitements liés au service
    $query = $bdd->prepare('SELECT id_type, nom_type FROM traitements WHERE id_organigramme = ? AND status = 1');
    $query->execute([$serviceId]);

    $motifs = [];
    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $motifs[] = [
            'id' => $row['id_type'],
            'nom' => $row['nom_type']
        ];
    }

    echo json_encode([
        'success' => true,
        'motifs' => $motifs
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Service ID manquant'
    ]);
}
?>
