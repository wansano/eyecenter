<?php
header('Content-Type: application/json');
include('../PUBLIC/connect.php');

if (isset($_GET['bl'])) {
    $motifId = intval($_GET['bl']);

    // Récupérer le prix du motif
    $query = $bdd->prepare('SELECT quantite FROM approvisionnements WHERE id_appro = ?');
    $query->execute([$motifId]);
    $motif = $query->fetch(PDO::FETCH_ASSOC);

    if ($motif) {
        echo json_encode([
            'success' => true,
            'quantite' => $motif['quantite']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'quantité introuvable'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'ID du BL manquant'
    ]);
}
?>
