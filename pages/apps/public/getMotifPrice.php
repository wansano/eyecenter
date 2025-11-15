<?php
header('Content-Type: application/json');
include('../PUBLIC/connect.php');

if (isset($_GET['motif'])) {
    $motifId = intval($_GET['motif']);

    // Récupérer le prix du motif
    $query = $bdd->prepare('SELECT montant FROM traitements WHERE id_type = ?');
    $query->execute([$motifId]);
    $motif = $query->fetch(PDO::FETCH_ASSOC);

    if ($motif) {
        echo json_encode([
            'success' => true,
            'montant' => $motif['montant']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Motif introuvable'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'ID du motif manquant'
    ]);
}
?>
