<?php
require_once('connect.php');
require_once('MenuConfig.php');
require_once('fonction.php');

$isEmbed = isset($_GET['embed']) && (string)$_GET['embed'] === '1';

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

// Préparation des données utilisateur (utile même si on masque le menu)
$userData = getUserInfo($bdd, $_SESSION['auth']);
$user = '';
$id_user = 0;
$id_service = 0;
$email = '';
$types = '';
$responsable = 1;
if (is_array($userData) && !empty($userData)) {
    $user = (string)($userData['pseudo'] ?? '');
    $id_user = (int)($userData['id'] ?? 0);
    $id_service = (int)($userData['id_service'] ?? 0);
    $email = (string)($userData['email'] ?? '');
    $types = (string)($userData['type'] ?? '');
    $responsable = (int)($userData['responsable'] ?? 1);
}

// En mode embed (iframe/modal), on n'affiche pas le menu (mais les variables restent disponibles)
if ($isEmbed) {
    return;
}

if (!defined('APP_HEADER_INCLUDED')) {
    include('header.php');
}
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
                        // Variables utilisateur déjà initialisées plus haut.
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
                    <div class="rounded-circle d-flex align-items-center justify-content-center" 
						style="width: 40px; height: 40px; background:#0d6efd; color: white; font-weight: bold; font-size: 25px;">
                    <?php
                        $initial = '?';
                        if (!empty($user)) {
                            $initial = mb_substr($user, 0, 1, 'UTF-8');
                        }
                        echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8');
                    ?>
                    </div>
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
                            case "hr":
                                echo "Ressources Humaines";
                                break;
                            case "tresorérie":
                                echo "Trésorier";
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
                        <a role="menuitem" tabindex="-1" href="../public/profil.php?id=<?php echo (int)$id_user; ?>"><i class="bx bx-user"></i> Mon Profil</a>
                    </li>
                    <li>
                        <a role="menuitem" tabindex="-1" href="../public/deconnexion.php"><i class="bx bx-power-off"></i> Se déconnecter</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <!-- end: search & user box -->
</header>
<!-- end: header -->