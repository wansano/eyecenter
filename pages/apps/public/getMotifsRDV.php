<?php
header('Content-Type: application/json');

// Inclure la connexion à la base de données
include('../public/connect.php');

// Vérifier si l'ID du médecin est passé (compat: param "service" historique)
if (isset($_GET['medecin']) || isset($_GET['service'])) {
    $medecinId = isset($_GET['medecin']) ? intval($_GET['medecin']) : intval($_GET['service']);

    // Préparer la requête pour récupérer les traitements liés au service
    $query = $bdd->prepare('SELECT id_type, nom_type FROM traitements WHERE model = ? AND status = 1');
    $query->execute([$medecinId]);

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
        'message' => 'Identifiant médecin manquant'
    ]);
}
?>
