<?php
include('connect.php');
header('Content-Type: application/json');

// Vérification du paramètre type
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Paramètres manquants'
    ]);
    exit;
}

$type = $_GET['type'];
$id = intval($_GET['id']); // Conversion sécurisée en entier

try {
    if ($type === 'compte') {
        $query = $bdd->prepare('SELECT solde FROM comptes WHERE id_compte = ?');
        $query->execute([$id]);
        $result = $query->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            echo json_encode([
                'success' => true,
                'solde' => $result['solde']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Compte introuvable'
            ]);
        }
    } 
    elseif ($type === 'budget') {
        $query = $bdd->prepare('SELECT solde FROM budgets WHERE id_budget = ?');
        $query->execute([$id]);
        $result = $query->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            echo json_encode([
                'success' => true,
                'solde' => $result['solde']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Budget introuvable'
            ]);
        }
    } 
    else {
        echo json_encode([
            'success' => false,
            'message' => 'Type invalide'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'exécution de la requête',
        'error' => $e->getMessage()
    ]);
}
?>
