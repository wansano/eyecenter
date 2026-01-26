<?php
header('Content-Type: application/json');
include('../PUBLIC/connect.php');

if (isset($_GET['motif'])) {
    $motifId = intval($_GET['motif']);

    // Récupérer le prix du motif (+ prix assurance si disponible)
    $cols = ['montant'];
    try {
        $st = $bdd->prepare("SHOW COLUMNS FROM traitements LIKE 'prix_assurance'");
        $st->execute();
        $hasPrixAssurance = (bool)$st->fetch(PDO::FETCH_ASSOC);
        if ($hasPrixAssurance) {
            $cols[] = 'prix_assurance';
        }
    } catch (Throwable $e) {
        // Si SHOW COLUMNS échoue, on reste sur montant uniquement
    }

    $query = $bdd->prepare('SELECT ' . implode(', ', $cols) . ' FROM traitements WHERE id_type = ?');
    $query->execute([$motifId]);
    $motif = $query->fetch(PDO::FETCH_ASSOC);

    if ($motif) {
        echo json_encode([
            'success' => true,
            'montant' => $motif['montant'],
            'prix_assurance' => array_key_exists('prix_assurance', $motif) ? $motif['prix_assurance'] : null,
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
