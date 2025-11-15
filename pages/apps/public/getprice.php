<?php
include('connect.php');
header('Content-Type: application/json');

if (isset($_GET['categorie'])) {
    $productId = intval($_GET['categorie']);

    try {
        $query = $bdd->prepare('SELECT prix_vente FROM categorie_produits WHERE id_categorie = ?');
        $query->execute([$productId]);
        $product = $query->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            echo json_encode([
                'success' => true,
                'prix_vente' => $product['prix_vente']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Produit introuvable'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de l\'exécution de la requête.',
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'ID de la catégorie manquant'
    ]);
}
?>
