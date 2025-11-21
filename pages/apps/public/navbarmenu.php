<?php
require_once('connect.php');
require_once('MenuConfig.php');
require_once('fonction.php');

// Vérifier et démarrer la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier l'authentification
if (!isset($_SESSION['auth'])) {
    header('Location: ../../login.php');
    exit;
}

// Initialiser la configuration du menu
$menuConfig = new MenuConfig();

// Vérifier l'expiration de la session (30 minutes)
$session_timeout = 1800; // 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    session_unset();
    session_destroy();
    header('Location: ../../login.php?timeout=1');
    exit;
}

// Mettre à jour le timestamp de la dernière activité
$_SESSION['last_activity'] = time();

// Protection contre les attaques XSS
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');

//Information de l'entreprise

$clinique = getSingleRow($bdd, 'profil_entreprise');
$devise = $clinique['devise'];

include ('header.php');
?>
<!-- start: header -->
<header class="header header-nav-menu header-nav-top-line">
    <div class="logo-container">
        <a href="#" class="logo">
            <img src="../img/logo.jpg" width="75" height="35" alt="logo" />
        </a>
        <button class="btn header-btn-collapse-nav d-lg-none" data-bs-toggle="collapse" data-bs-target=".header-nav">
            <i class="fas fa-bars"></i>
        </button>

        <!-- start: header nav menu -->
        <div class="header-nav collapse bg-color-dark-scale-5">
            <div class="header-nav-main header-nav-main-effect-1 header-nav-main-sub-effect-1 header-nav-main-square">
                <nav>
                    <ul class="nav nav-pills" id="mainNav">
                        <?php
                        // Préparation des données utilisateur pour MenuConfig
                        $userData = getUserInfo($bdd, $_SESSION['auth']);
                        if ($userData) {
                            $user = $userData['pseudo'];
                            $id_user = $userData['id'];
                            $id_service = $userData['id_service'];
                            $email = $userData['email'];
                            $types = $userData['type'];
                            $responsable = $userData['responsable'];
                        }
                        $user_data = [
                            'type' => $types,
                            'service' => isset($service) ? $service : '',
                            'user' => isset($user) ? $user : '',
                            'id_user' => isset($id_user) ? $id_user : '',
                            'responsable' => isset($responsable) ? $responsable : 1,
                        ];
                        echo MenuConfig::getUserMenu($types, array_merge($user_data, ['plage_connexion' => $userData['plage_connexion'] ?? '']));
                        ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
	<!-- start: search & user box -->
    <div class="header-right">

        <span class="separator"></span>

        <div id="userbox" class="userbox">
            <a href="#" data-bs-toggle="dropdown">
                <figure class="profile-picture">
                    <?php
                        $initial = '?';
                        if (!empty($user)) {
                            $initial = mb_substr($user, 0, 1, 'UTF-8');
                        }
                        echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8');
                    ?>
                </figure>
                <div class="profile-info" data-lock-name="John Doe" data-lock-email="johndoe@okler.com">
                    <?php
                        // Récupérer le type du jour selon plage_connexion
                        $type_jour = MenuConfig::getTypeJourPlageConnexion($userData['plage_connexion'] ?? $types);
                        echo '<span class="name">'.$user.'</span><span class="role">';
                        switch ($type_jour) {
                            case "secretariat":
                                echo "Secretaire";
                                break;
                            case "caisse":
                                echo "Caissier";
                                break;
                            case "boutique":
                                echo "Caissier";
                                break;
                            case "ophtalmologue":
                                echo "Médecin Ophtalmologue";
                                break;
                            case "optometriste":
                                echo "Médecin Optométriste";
                                break;
                            case "technologie":
                                echo "TI & Support";
                                break;
                            case "infirmier":
                                echo "Infirmier Major";
                                break;
                            case "comptabilite":
                                echo "Comptable";
                                break;
                            case "superviseur":
                                echo "Superviseur";
                                break;
                            case "medecin":
                                echo "Médecin-Chef";
                                break;
                            default:
                                echo ucfirst($type_jour);
                        }
                        echo '</span>';
                    ?>
                </div>
                <i class="fa custom-caret"></i>
            </a>

            <div class="dropdown-menu">
                <ul class="list-unstyled">
                    <li class="divider"></li>
                    <li>
                        <a role="menuitem" tabindex="-1" href="../public/deconnexion.php"><i class="bx bx-power-off"></i> Se
                            deconnecter</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <!-- end: search & user box -->
</header>
<!-- end: header -->