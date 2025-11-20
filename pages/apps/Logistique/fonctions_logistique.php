<?php
// Fonctions principales du module Logistique
// Création et gestion d'inventaire, fournisseurs, commandes d'achat, mouvements de stock et alertes
session_start();
require_once('../public/connect.php');
require_once('../public/fonction.php');

/**
 * Initialise les tables si inexistantes.
 */
function createTablesLogistique(PDO $bdd) {
    $queries = [
        // Articles
        "CREATE TABLE IF NOT EXISTS log_articles (\n            id_article INT AUTO_INCREMENT PRIMARY KEY,\n            code VARCHAR(30) UNIQUE,\n            nom VARCHAR(150) NOT NULL,\n            categorie VARCHAR(100),\n            description TEXT,\n            unite VARCHAR(30) DEFAULT 'pcs',\n            stock_initial INT DEFAULT 0,\n            stock_actuel INT DEFAULT 0,\n            seuil_alerte INT DEFAULT 0,\n            prix_achat DECIMAL(12,2) DEFAULT 0,\n            prix_vente DECIMAL(12,2) DEFAULT 0,\n            actif TINYINT DEFAULT 1,\n            date_creation DATETIME DEFAULT CURRENT_TIMESTAMP\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        // Fournisseurs
        "CREATE TABLE IF NOT EXISTS log_fournisseurs (\n            id_fournisseur INT AUTO_INCREMENT PRIMARY KEY,\n            nom VARCHAR(150) NOT NULL,\n            contact VARCHAR(150),\n            telephone VARCHAR(30),\n            email VARCHAR(120),\n            adresse TEXT,\n            actif TINYINT DEFAULT 1,\n            date_creation DATETIME DEFAULT CURRENT_TIMESTAMP\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        // Commandes d'achat
        "CREATE TABLE IF NOT EXISTS log_commandes_achat (\n            id_commande INT AUTO_INCREMENT PRIMARY KEY,\n            numero VARCHAR(40) UNIQUE,\n            id_fournisseur INT,\n            date_commande DATE,\n            statut ENUM('brouillon','envoyee','reçue','partielle','annulée') DEFAULT 'brouillon',\n            total_estime DECIMAL(14,2) DEFAULT 0,\n            date_reception_prevue DATE NULL,\n            date_reception_effective DATE NULL,\n            FOREIGN KEY (id_fournisseur) REFERENCES log_fournisseurs(id_fournisseur) ON DELETE SET NULL\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        // Lignes de commande
        "CREATE TABLE IF NOT EXISTS log_commandes_lignes (\n            id_ligne INT AUTO_INCREMENT PRIMARY KEY,\n            id_commande INT NOT NULL,\n            id_article INT NOT NULL,\n            quantite INT NOT NULL,\n            prix_unitaire DECIMAL(12,2) DEFAULT 0,\n            FOREIGN KEY (id_commande) REFERENCES log_commandes_achat(id_commande) ON DELETE CASCADE,\n            FOREIGN KEY (id_article) REFERENCES log_articles(id_article) ON DELETE RESTRICT\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        // Mouvements de stock
        "CREATE TABLE IF NOT EXISTS log_mouvements_stock (\n            id_mouvement INT AUTO_INCREMENT PRIMARY KEY,\n            id_article INT NOT NULL,\n            type ENUM('entrée','sortie','ajustement','reception') NOT NULL,\n            origine VARCHAR(60),\n            reference VARCHAR(60),\n            quantite INT NOT NULL,\n            stock_avant INT NOT NULL,\n            stock_apres INT NOT NULL,\n            date_mouvement DATETIME DEFAULT CURRENT_TIMESTAMP,\n            FOREIGN KEY (id_article) REFERENCES log_articles(id_article) ON DELETE RESTRICT\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        // Alertes de stock
        "CREATE TABLE IF NOT EXISTS log_alertes_stock (\n            id_alerte INT AUTO_INCREMENT PRIMARY KEY,\n            id_article INT NOT NULL,\n            type ENUM('seuil','rupture','surstock') NOT NULL,\n            message VARCHAR(255) NOT NULL,\n            date_alerte DATETIME DEFAULT CURRENT_TIMESTAMP,\n            traitee TINYINT DEFAULT 0,\n            FOREIGN KEY (id_article) REFERENCES log_articles(id_article) ON DELETE CASCADE\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    ];
    foreach ($queries as $sql) { $bdd->exec($sql); }
}

createTablesLogistique($bdd);

// --- Utilitaires ---
function logi_sanitize($value) { return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8'); }
function genererCodeArticle(PDO $bdd): string {
    do {
        $code = 'ART-' . strtoupper(substr(uniqid('', true), -6));
        $stmt = $bdd->prepare('SELECT COUNT(*) FROM log_articles WHERE code=?');
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn() > 0);
    return $code;
}
function genererNumeroCommande(PDO $bdd): string {
    do {
        $num = 'PO-' . date('ym') . '-' . strtoupper(substr(uniqid('', true), -4));
        $stmt = $bdd->prepare('SELECT COUNT(*) FROM log_commandes_achat WHERE numero=?');
        $stmt->execute([$num]);
    } while ($stmt->fetchColumn() > 0);
    return $num;
}

// --- Articles ---
function ajouterArticle(PDO $bdd, array $data): int {
    $code = genererCodeArticle($bdd);
    $stmt = $bdd->prepare('INSERT INTO log_articles(code, nom, categorie, description, unite, stock_initial, stock_actuel, seuil_alerte, prix_achat, prix_vente) VALUES(?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $code,
        logi_sanitize($data['nom'] ?? ''),
        logi_sanitize($data['categorie'] ?? ''),
        logi_sanitize($data['description'] ?? ''),
        logi_sanitize($data['unite'] ?? 'pcs'),
        (int)($data['stock_initial'] ?? 0),
        (int)($data['stock_initial'] ?? 0),
        (int)($data['seuil_alerte'] ?? 0),
        (float)($data['prix_achat'] ?? 0),
        (float)($data['prix_vente'] ?? 0)
    ]);
    return (int)$bdd->lastInsertId();
}
function mettreAJourArticle(PDO $bdd, int $id, array $data): bool {
    $stmt = $bdd->prepare('UPDATE log_articles SET nom=?, categorie=?, description=?, unite=?, seuil_alerte=?, prix_achat=?, prix_vente=?, actif=? WHERE id_article=?');
    return $stmt->execute([
        logi_sanitize($data['nom'] ?? ''),
        logi_sanitize($data['categorie'] ?? ''),
        logi_sanitize($data['description'] ?? ''),
        logi_sanitize($data['unite'] ?? 'pcs'),
        (int)($data['seuil_alerte'] ?? 0),
        (float)($data['prix_achat'] ?? 0),
        (float)($data['prix_vente'] ?? 0),
        isset($data['actif']) ? (int)$data['actif'] : 1,
        $id
    ]);
}
function listerArticles(PDO $bdd): array {
    $stmt = $bdd->query('SELECT * FROM log_articles ORDER BY nom');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function stockActuelArticle(PDO $bdd, int $id): int {
    $stmt = $bdd->prepare('SELECT stock_actuel FROM log_articles WHERE id_article=?');
    $stmt->execute([$id]);
    return (int)$stmt->fetchColumn();
}
function articlesSousSeuil(PDO $bdd): array {
    $stmt = $bdd->query('SELECT * FROM log_articles WHERE stock_actuel <= seuil_alerte AND actif=1');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Fournisseurs ---
function ajouterFournisseur(PDO $bdd, array $data): int {
    $stmt = $bdd->prepare('INSERT INTO log_fournisseurs(nom, contact, telephone, email, adresse) VALUES(?,?,?,?,?)');
    $stmt->execute([
        logi_sanitize($data['nom'] ?? ''),
        logi_sanitize($data['contact'] ?? ''),
        logi_sanitize($data['telephone'] ?? ''),
        logi_sanitize($data['email'] ?? ''),
        logi_sanitize($data['adresse'] ?? '')
    ]);
    return (int)$bdd->lastInsertId();
}
function listerFournisseurs(PDO $bdd): array { $r=$bdd->query('SELECT * FROM log_fournisseurs WHERE actif=1 ORDER BY nom'); return $r->fetchAll(PDO::FETCH_ASSOC); }

// --- Commandes d'achat ---
function creerCommandeAchat(PDO $bdd, int $idFournisseur, ?string $datePrevue=null): int {
    $numero = genererNumeroCommande($bdd);
    $stmt = $bdd->prepare('INSERT INTO log_commandes_achat(numero, id_fournisseur, date_commande, date_reception_prevue) VALUES(?,?,?,?)');
    $stmt->execute([$numero, $idFournisseur, date('Y-m-d'), $datePrevue]);
    return (int)$bdd->lastInsertId();
}
function ajouterLigneCommande(PDO $bdd, int $idCommande, int $idArticle, int $qte, float $pu): bool {
    $stmt = $bdd->prepare('INSERT INTO log_commandes_lignes(id_commande, id_article, quantite, prix_unitaire) VALUES(?,?,?,?)');
    $ok = $stmt->execute([$idCommande, $idArticle, $qte, $pu]);
    if ($ok) recalculerTotalCommande($bdd, $idCommande); return $ok;
}
function recalculerTotalCommande(PDO $bdd, int $idCommande): void {
    $stmt = $bdd->prepare('SELECT SUM(quantite * prix_unitaire) FROM log_commandes_lignes WHERE id_commande=?');
    $stmt->execute([$idCommande]);
    $total = (float)$stmt->fetchColumn();
    $up = $bdd->prepare('UPDATE log_commandes_achat SET total_estime=? WHERE id_commande=?');
    $up->execute([$total, $idCommande]);
}
function recevoirCommande(PDO $bdd, int $idCommande): bool {
    $bdd->beginTransaction();
    try {
        // Récupérer les lignes
        $lignes = $bdd->prepare('SELECT id_article, quantite FROM log_commandes_lignes WHERE id_commande=?');
        $lignes->execute([$idCommande]);
        foreach ($lignes->fetchAll(PDO::FETCH_ASSOC) as $lg) {
            enregistrerMouvementStock($bdd, (int)$lg['id_article'], 'reception', 'commande', (string)$idCommande, (int)$lg['quantite']);
        }
        $stmt = $bdd->prepare('UPDATE log_commandes_achat SET statut="reçue", date_reception_effective=? WHERE id_commande=?');
        $stmt->execute([date('Y-m-d'), $idCommande]);
        $bdd->commit();
        return true;
    } catch (Exception $e) {
        $bdd->rollBack();
        error_log('Erreur réception commande: '.$e->getMessage());
        return false;
    }
}
function listerCommandes(PDO $bdd): array { $r=$bdd->query('SELECT c.*, f.nom AS fournisseur FROM log_commandes_achat c LEFT JOIN log_fournisseurs f ON f.id_fournisseur=c.id_fournisseur ORDER BY c.id_commande DESC'); return $r->fetchAll(PDO::FETCH_ASSOC); }

// --- Mouvements de stock ---
function enregistrerMouvementStock(PDO $bdd, int $idArticle, string $type, string $origine, string $reference, int $quantite): bool {
    // Stock avant
    $stockAvant = stockActuelArticle($bdd, $idArticle);
    $stockApres = $stockAvant;
    if ($type === 'entrée' || $type === 'reception') { $stockApres += $quantite; }
    elseif ($type === 'sortie') { $stockApres -= $quantite; }
    elseif ($type === 'ajustement') { $stockApres = $quantite; }

    // Update article
    $up = $bdd->prepare('UPDATE log_articles SET stock_actuel=? WHERE id_article=?');
    $up->execute([$stockApres, $idArticle]);

    // Insert mouvement
    $stmt = $bdd->prepare('INSERT INTO log_mouvements_stock(id_article, type, origine, reference, quantite, stock_avant, stock_apres) VALUES(?,?,?,?,?,?,?)');
    $ok = $stmt->execute([$idArticle, $type, $origine, $reference, $quantite, $stockAvant, $stockApres]);

    verifierEtGenererAlerte($bdd, $idArticle, $stockApres);
    return $ok;
}
function listerMouvements(PDO $bdd, int $limit=200): array {
    $stmt = $bdd->prepare('SELECT m.*, a.nom AS article FROM log_mouvements_stock m JOIN log_articles a ON a.id_article=m.id_article ORDER BY m.id_mouvement DESC LIMIT ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT); $stmt->execute(); return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Alertes ---
function verifierEtGenererAlerte(PDO $bdd, int $idArticle, int $stockActuel): void {
    $stmt = $bdd->prepare('SELECT seuil_alerte, nom FROM log_articles WHERE id_article=?');
    $stmt->execute([$idArticle]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return;
    $seuil = (int)$row['seuil_alerte'];
    $type = null; $message = null;
    if ($stockActuel <= 0) { $type='rupture'; $message='Rupture de stock: '.$row['nom']; }
    elseif ($stockActuel <= $seuil && $seuil>0) { $type='seuil'; $message='Stock sous seuil pour: '.$row['nom']; }
    if ($type) {
        $ins = $bdd->prepare('INSERT INTO log_alertes_stock(id_article, type, message) VALUES(?,?,?)');
        $ins->execute([$idArticle, $type, $message]);
    }
}
function listerAlertes(PDO $bdd, $nonTraitees=false) {
    $sql = 'SELECT a.*, ar.nom AS article FROM log_alertes_stock a JOIN log_articles ar ON ar.id_article=a.id_article';
    if ($nonTraitees) $sql .= ' WHERE a.traitee=0';
    $sql .= ' ORDER BY a.id_alerte DESC';
    $r=$bdd->query($sql); return $r->fetchAll(PDO::FETCH_ASSOC);
}
function marquerAlerteTraitee(PDO $bdd, int $idAlerte): bool { $stmt=$bdd->prepare('UPDATE log_alertes_stock SET traitee=1 WHERE id_alerte=?'); return $stmt->execute([$idAlerte]); }

// --- Rapports simples ---
function valeurStockActuel(PDO $bdd): float {
    $stmt = $bdd->query('SELECT SUM(stock_actuel * prix_achat) FROM log_articles');
    return (float)$stmt->fetchColumn();
}
function rotationArticles(PDO $bdd, int $jours=30): array {
    $stmt = $bdd->prepare('SELECT a.nom, a.stock_actuel, COUNT(m.id_mouvement) AS mouvements FROM log_articles a LEFT JOIN log_mouvements_stock m ON m.id_article=a.id_article AND m.date_mouvement >= DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY a.id_article ORDER BY mouvements DESC');
    $stmt->execute([$jours]); return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>