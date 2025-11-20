# Module Logistique Clinique

Ce module gère l'inventaire, les fournisseurs, les commandes d'achat, les mouvements de stock, les alertes et les rapports de valorisation / rotation.

## Tables créées
- log_articles
- log_fournisseurs
- log_commandes_achat
- log_commandes_lignes
- log_mouvements_stock
- log_alertes_stock

Création automatique via `fonctions_logistique.php` (fonction `createTablesLogistique`).

## Pages
- `inventaire.php` : ajout + liste des articles
- `fournisseurs.php` : gestion de base des fournisseurs
- `commandes.php` : création commande, ajout lignes, réception
- `mouvements.php` : mouvements manuels (entrée / sortie / ajustement)
- `alertes.php` : suivi des alertes de stock
- `rapports.php` : valeur stock + rotation + articles sous seuil

## Fonctions clés (fonctions_logistique.php)
- `ajouterArticle`, `mettreAJourArticle`, `listerArticles`
- `ajouterFournisseur`, `listerFournisseurs`
- `creerCommandeAchat`, `ajouterLigneCommande`, `recevoirCommande`, `listerCommandes`
- `enregistrerMouvementStock`, `listerMouvements`
- `verifierEtGenererAlerte`, `listerAlertes`, `marquerAlerteTraitee`
- `valeurStockActuel`, `rotationArticles`

## Alertes
Types : `seuil`, `rupture`, `surstock` (surstock réservée pour évolutions). Générées lors de chaque mouvement si conditions atteintes.

## Sécurité & Validation
Entrées nettoyées basiquement (`logi_sanitize`). Pour production : ajouter CSRF token, rôles/permissions, validation stricte (types, limites), pagination.

## Évolutions possibles
- Endpoints AJAX pour intégration datatables
- Interface d'édition article/fournisseur
- Gestion des surstocks + prévisions
- État commandes partielle / annulée
- Historique d'ajustements signé
- Export CSV / PDF inventaire et mouvements

## Intégration
Inclure dans le menu principal un lien vers chaque page selon profil utilisateur.
