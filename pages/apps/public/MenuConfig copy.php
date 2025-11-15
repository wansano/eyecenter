<?php

class MenuConfig {
    /**
     * Génère le menu en fonction du type d'utilisateur
     */
    public static function getUserMenu($type, $user_data) {
        return self::getMenuItems($user_data);
    }

    private static function getAdminMenu($user_data) {
        return '
        <li class="dropdown">
            <a class="nav-link dropdown-toggle" href="#">Administration</a>
            <ul class="dropdown-menu">
                <li><a class="nav-link" href="listeutilisateurs.php">Liste des utilisateurs</a></li>
                <li><a class="nav-link" href="listedesservices.php">Liste des services</a></li>
                <li><a class="nav-link" href="traitementslist.php">Liste des traitements</a></li>
                <li><a class="nav-link" href="profilentreprise.php?pe=entreprise">Profil de l\'entreprise</a></li>
            </ul>
        </li>
        <li class="dropdown">
            <a href="#" class="nav-link dropdown-toggle">Gestion HR</a>
            <ul class="dropdown-menu">
                <li class="dropdown-submenu">
                    <a class="nav-link" href="#">Employés</a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="#">Ajouter un employé</a></li>
                        <li><a class="nav-link" href="#">Liste des employés</a></li>
                    </ul>
                </li>
                <li class="dropdown-submenu">
                    <a class="nav-link" href="#">Congés</a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="#">Liste des congés</a></li>
                    </ul>
                </li>
            </ul>
        </li>';
    }

    private static function getAccueilMenu($user_data) {
        return '
        <li class="dropdown">
            <a class="nav-link dropdown-toggle" href="#">Gestion Patients</a>
            <ul class="dropdown-menu">
                <li><a class="nav-link" href="rechercheinformation.php">Recherche</a></li>
                <li><a class="nav-link" href="addpatient.php?ap=default">Ajout</a></li>
                <li><a class="nav-link" href="editpatient.php?ep=default">Modification</a></li>
                <li><a class="nav-link" href="transmission-caisse.php">Affectation</a></li>
                <li><a class="nav-link" href="patientensalle.php?pes=' . $user_data['type'] . '">En salle</a></li>
            </ul>
        </li>
        <li class="dropdown">
            <a href="#" class="nav-link dropdown-toggle">Documentation</a>
            <ul class="dropdown-menu">
                <li><a class="nav-link" href="reimpressiondocument.php?rdp=' . $user_data['type'] . '">Réimpression dossier</a></li>
            </ul>
        </li>
        <!-- <li class="dropdown">
            <a href="#" class="nav-link dropdown-toggle">Prestations</a>
            <ul class="dropdown-menu">
                <li><a class="nav-link" href="situationjournaliere.php?sj=0">Prestations du jour</a></li>
            </ul>
        </li> -->
        <li class="dropdown">
            <a href="#" class="nav-link dropdown-toggle">Rendez-vous</a>
            <ul class="dropdown-menu">
                <li><a class="nav-link" href="convocation.php">Rendez-vous patients</a></li>
            </ul>
        </li>';
    }

    private static function getCaisseMenu($user_data) {
        return '
        <li class="dropdown">
            <a class="nav-link dropdown-toggle" href="#">Paiements Patients</a>
            <ul class="dropdown-menu">
                <li><a class="nav-link" href="patientensalle.php?lpeap=' . $user_data['type'] . '">Paiement en attente</a></li>
            </ul>
        </li>
        <li class="dropdown">
            <a class="nav-link dropdown-toggle" href="#">Documentation</a>
            <ul class="dropdown-menu">
                <li><a class="nav-link" href="reimpressiondocument.php?rdp=' . $user_data['type'] . '">Réimpression reçu</a></li>
            </ul>
        </li>
        <li class="dropdown">
            <a class="nav-link dropdown-toggle" href="#">Situation</a>
            <ul class="dropdown-menu">
                <li><a class="nav-link" href="rapportjournalier.php?rdp=' . $user_data['type'] . '">Proof de caisse</a></li>
                <li><a class="nav-link" href="mesrapportdecaisse.php?rdp=' . $user_data['type'] . '">Mes proofs du mois</a></li>
            </ul>
        </li>';
    }

    private static function getModeServicesMenu($user_data) {
        return '
        <li class="dropdown">
            <a class="nav-link dropdown-toggle" href="#">Patient</a>
            <ul class="dropdown-menu">
                <li><a class="nav-link" href="patientservice.php">Patients du service</a></li>
            </ul>
        </li>
        <li class="dropdown">
            <a class="nav-link dropdown-toggle" href="#">Documentation</a>
            <ul class="dropdown-menu">
                <li><a class="nav-link" href="reimpressiondocument.php?rdp=' . $user_data['type'] . '">Réimpression</a></li>
            </ul>
        </li>';
    }

    private static function getCaisseOptiqueMenu($user_data) {
        return '
        <li class="dropdown">
            <a class="nav-link dropdown-toggle" href="#">Clients en salle</a>
            <ul class="dropdown-menu">
                <li><a class="nav-link" href="listeclientensalle.php?lpeap=' . $user_data['type'] . '">Liste des clients</a></li>
            </ul>
        </li>
        <li class="dropdown">
            <a class="nav-link dropdown-toggle" href="#">Ventes</a>
            <ul class="dropdown-menu">
                <li><a class="nav-link" href="findingproduct.php">Verifier une monture</a></li>
                <li><a class="nav-link" href="listetotaldesventes.php">Liste des ventes</a></li>
                <li><a class="nav-link" href="#">Situation des ventes</a></li>
            </ul>
        </li>
        <li class="dropdown">
            <a class="nav-link dropdown-toggle" href="#">Fonction</a>
            <ul class="dropdown-menu">
                <li><a class="nav-link" href="addproduct.php">Ajouter une monture</a></li>
            </ul>
        </li>
        <li class="dropdown">
            <a class="nav-link dropdown-toggle" href="#">Réimpression</a>
            <ul class="dropdown-menu">
                <li><a class="nav-link" href="reimpressiondocument.php?rdp=' . $user_data['type'] . '">Reçu ou ordonance</a></li>
            </ul>
        </li>';
    }

    private static function getComptabiliteMenu($user_data) {
        $submenus = [
            self::getComptabiliteTraitementsMenu(),
            self::getComptabiliteStructureMenu(),
            self::getComptabiliteConfigurationMenu(),
            self::getComptabiliteStocksMenu(),
            self::getComptabiliteReportingMenu()
        ];
        
        return implode("\n", $submenus);
    }
    
    private static function getComptabiliteTraitementsMenu() {
        return '
        <li class="dropdown">
            <a href="#" class="nav-link dropdown-toggle">Traitements</a>
            <ul class="dropdown-menu">
                <li class="dropdown-submenu">
                    <a class="nav-link" href="#">Paiements</a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="demandetopay.php">Depense validé</a></li>
                        <li><a class="nav-link" href="remboursement.php">Rembourssement</a></li>
                        <li><a class="nav-link" href="listesituationdesfournisseurs.php">Fournisseur</a></li>
                        <li><a class="nav-link" href="listesituationdescollaborateurs.php">Collaborateur</a></li>
                    </ul>
                </li>
                <li class="dropdown-submenu">
                    <a class="nav-link" href="#">Remise</a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="addremiseaccountout.php?newinaccount">Faire une remise externe</a></li>
                        <li><a class="nav-link" href="addremiseaccountin.php?newinaccount">Faire une remise interne</a></li>
                        <li class="dropdown-submenu">
                            <a class="nav-link" href="#">Listes des remises</a>
                            <ul class="dropdown-menu">
                                <li><a class="nav-link" href="listeremisecompteinterne.php">Remise interne</a></li>
                                <li><a class="nav-link" href="listeremisecompteexterne.php">Remise externe</a></li>
                            </ul>
                        </li>
                    </ul>
                </li>
                <li class="dropdown-submenu">
                    <a class="nav-link" href="#">Rapprochement</a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="interocompte.php">Intéroger compte</a></li>
                        <li><a class="nav-link" href="#">Faire un rapprochement</a></li>
                    </ul>
                </li>
                <li class="dropdown-submenu">
                    <a class="nav-link" href="#">Approvisionnement</a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="addapprocategorie.php">Lentilles</a></li>
                        <li><a class="nav-link" href="addproduct.php">Montures</a></li>
                    </ul>
                </li>
                <li class="dropdown-submenu">
                    <a class="nav-link" href="#">Commande</a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="addcommandproduct.php">Initier une commande</a></li>
                    </ul>
                </li>
                <li class="dropdown-submenu">
                    <a class="nav-link" href="#">Livraison</a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="findingcommand.php">Enregistré une livraison</a></li>
                    </ul>
                </li>
            </ul>
        </li>';
    }
    
    private static function getComptabiliteStructureMenu() {
        return '
        <li class="dropdown">
            <a href="#" class="nav-link dropdown-toggle">Structure fonction</a>
            <ul class="dropdown-menu">
                <li class="dropdown-submenu">
                    <a class="nav-link" href="#">Comptabilité</a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="#">Etats financiers</a></li>
                        <li><a class="nav-link" href="#">Grand livre</a></li>
                    </ul>
                </li>
                <li class="dropdown-submenu">
                    <a class="nav-link" href="#">Fournisseur</a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="listedesfournisseurs.php">Liste des fournisseurs</a></li>
                        <li class="dropdown-submenu">
                            <a class="nav-link" href="#">Commande</a>
                            <ul class="dropdown-menu">
                                <li><a class="nav-link" href="listedescommandes.php?commande=guessing">En attente</a></li>
                                <li><a class="nav-link" href="listedescommandes.php?commande=delivred">Livrée</a></li>
                                <li><a class="nav-link" href="listedescommandes.php?commande=cancelled">Annulée</a></li>
                            </ul>
                        </li>
                    </ul>
                </li>
            </ul>
        </li>';
    }
    
    private static function getComptabiliteConfigurationMenu() {
        return '
        <li class="dropdown">
            <a href="#" class="nav-link dropdown-toggle">Configuration</a>
            <ul class="dropdown-menu">
                <li class="dropdown-submenu">
                    <a class="nav-link" href="#">Comptes</a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="addaccount.php?new">Ajouter un compte</a></li>
                    </ul>
                </li>
                <li class="dropdown-submenu">
                    <a class="nav-link" href="#">Budgets</a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="addbudget.php?addbudgets">Ajouter un budget</a></li>
                    </ul>
                </li>
            </ul>
        </li>';
    }
    
    private static function getComptabiliteStocksMenu() {
        return '
        <li class="dropdown">
            <a href="#" class="nav-link dropdown-toggle">Gestion de stocks</a>
            <ul class="dropdown-menu">
                <li class="dropdown-submenu">
                    <a class="nav-link" href="#">Catégorie lentilles</a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="addproductcategorie.php">Ajouter catégorie lentille</a></li>
                        <li><a class="nav-link" href="listedescategoriesproduits.php">Liste catégorie lentilles</a></li>
                    </ul>
                </li>
                <li class="dropdown-submenu">
                    <a class="nav-link" href="#">Montures</a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="findingproduct.php">Vérifier une monture</a></li>
                    </ul>
                </li>
            </ul>
        </li>';
    }
    
    private static function getComptabiliteReportingMenu() {
        return '
        <li class="dropdown">
            <a href="#" class="nav-link dropdown-toggle">Reporting</a>
            <ul class="dropdown-menu">
                <li class="dropdown-submenu">
                    <a class="nav-link" href="#">Rapport Clinique</a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="#">Inventaire équipement</a></li>
                    </ul>
                </li>
                <li class="dropdown-submenu">
                    <a class="nav-link" href="#">Rapport Boutique</a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="listeinventairemontures.php">Inventaire montures</a></li>
                        <li><a class="nav-link" href="listeinventairelentilles.php">Inventaire lentilles</a></li>
                        <li><a class="nav-link" href="listeproduitretour.php">Produit à retourner</a></li>
                    </ul>
                </li>
            </ul>
        </li>';
    }

    private static function getResponsableMenu($user_data) {
        return '
        <li class="dropdown">
            <a href="#" class="nav-link dropdown-toggle">Depenses & Comptes</a>
            <ul class="dropdown-menu">
                <li><a class="nav-link" href="interocompte.php">Intérogation de compte</a></li>
                <li><a class="nav-link" href="demandevalidation.php?u=' . $user_data['user'] . '">Depense en attente de validation</a></li>
                <li><a class="nav-link" href="demandevalidate.php?u=' . $user_data['id_user'] . '">Demande de depense validées</a></li>
                <li><a class="nav-link" href="demandeordered.php?u=' . $user_data['id_user'] . '">Demande de depense payées</a></li>
                <li><a class="nav-link" href="#">Situation des partenaires</a></li>
            </ul>
        </li>';
    }

    public static function getMenuItems($user_data) {
        $menu_items = '
        <li class="nav-item">
            <a class="nav-link" href="'.($user_data['type'] == 'caisseoptic' ? 'dashboard-boutique.php?token='.sha1('EYECenterV2.0').'' : 'acceuil.php?token='.sha1('EYECenterV2.0')).'">Accueil</a>
        </li>';

        // Ajout des menus selon le type d'utilisateur
        switch($user_data['type']) {
            case 'administrateur':
                $menu_items .= self::getAdminMenu($user_data);
                break;
            case 'acceuil':
                $menu_items .= self::getAccueilMenu($user_data);
                break;
            case 'caisse':
                $menu_items .= self::getCaisseMenu($user_data);
                break;
            case 'caisseoptic':
                $menu_items .= self::getCaisseOptiqueMenu($user_data);
                break;
            case 'comptabilité':
                $menu_items .= self::getComptabiliteMenu($user_data);
                break;
            case 'modeservices':
                $menu_items .= self::getModeServicesMenu($user_data);
                break;
        }

        // Menu des dépenses pour les responsables
        if (isset($user_data['responsable']) && $user_data['responsable'] == 0) {
            $menu_items .= self::getResponsableMenu($user_data);
        }

        // Menu des demandes pour tous les utilisateurs

         $menu_items .= '
        <li class="dropdown">
            <a class="nav-link dropdown-toggle" href="#">Profil</a>
            <ul class="dropdown-menu">
                <li><a class="nav-link" href="#profil.php?id=' . $user_data['id_user'] . '">Mon Profil</a></li>
                <li><a class="nav-link" href="#.php?task=' . $user_data['id_user'] . '">Mes taches</a></li>
            </ul>
        </li>';

        /*
        
        $menu_items .= '
        <li class="dropdown">
            <a class="nav-link dropdown-toggle" href="#">Demandes</a>
            <ul class="dropdown-menu">
                <li><a class="nav-link" href="demande.php?u=' . $user_data['id_user'] . '">Demande de depense</a></li>
                <li><a class="nav-link" href="listedemesdemandes.php?usr=' . $user_data['user'] . '">Mes demandes</a></li>
            </ul>
        </li>';
        
        */

        return $menu_items;
    }
}