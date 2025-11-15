<?
/**
 * Insertion vente produit
 */
function insertVenteProduit($bdd, $data) {
    $stmt = $bdd->prepare('
        INSERT INTO ventes_produits 
        (id_affectation, id_produit, id_categorie, id_patient, id_caissier, 
         prix_monture, prix_verre, compte, collaborateur, date_vente) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ');

    $stmt->execute([
        $data['id_affectation'],
        $data['id_produit'],
        $data['id_categorie'],
        $data['id_patient'],
        $data['id_caissier'],
        $data['prix_monture'],
        $data['prix_verre'],
        $data['compte'],
        $data['collaborateur']
    ]);
}

/**
 * Fonction pour récupérer le modèle de produit par son ID
 */
function model_produits($id_model) {
    global $bdd;
    $req = $bdd->prepare('SELECT model FROM model_produits WHERE id_model = ?');
    $req->execute([$id_model]);
    $donnees = $req->fetch();
    return $donnees ? $donnees['model'] : '';
}