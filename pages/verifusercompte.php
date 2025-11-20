<?php
session_start();

// Vérification de la session existante
if (!isset($_SESSION['auth']) || empty($_SESSION['auth'])) {
    header('Location: login.php');
    exit;
}

include('apps/PUBLIC/connect.php');

// Configuration
const MAX_ATTEMPTS = 3;
const LOCKOUT_TIME = 300; // 5 minutes
const SESSION_TIMEOUT = 600; // 10 minutes

// Vérification du timeout de session
if (isset($_SESSION['otp_start_time']) && (time() - $_SESSION['otp_start_time'] > SESSION_TIMEOUT)) {
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}

$errors = [];

// Gestion des tentatives
if (!isset($_SESSION['otp_attempts'])) {
    $_SESSION['otp_attempts'] = 0;
    $_SESSION['otp_start_time'] = time();
}

if (isset($_POST['goin'])) {
    // Vérification du nombre de tentatives
    if ($_SESSION['otp_attempts'] >= MAX_ATTEMPTS) {
        $time_remaining = LOCKOUT_TIME - (time() - $_SESSION['last_attempt_time']);
        if ($time_remaining > 0) {
            $errors[] = "Trop de tentatives. Veuillez attendre " . ceil($time_remaining/60) . " minutes.";
        } else {
            // Réinitialisation des tentatives après le délai
            $_SESSION['otp_attempts'] = 0;
        }
    }

    if (empty($errors)) {
        $code = trim($_POST['code'] ?? '');
        
        if (empty($code)) {
            $errors[] = "Le code de vérification est obligatoire.";
        } else {
            try {
                $stmt = $bdd->prepare('SELECT id, type, token FROM users WHERE id = ? AND status = 1');
                $stmt->execute([$_SESSION['auth']]);
                $user = $stmt->fetch();

                if ($user && password_verify($code, $user['token'])) {
                    // Succès de la vérification
                    $_SESSION['user_type'] = $user['type'];
                    $_SESSION['verified'] = true;
                    $_SESSION['last_activity'] = time();
                    // Nettoyage des variables de vérification
                    unset($_SESSION['otp_attempts']);
                    unset($_SESSION['otp_start_time']);
                    unset($_SESSION['last_attempt_time']);

                    // Récupérer la plage_connexion de l'utilisateur
                    $stmt2 = $bdd->prepare('SELECT plage_connexion FROM users WHERE id = ?');
                    $stmt2->execute([$user['id']]);
                    $user_plage = $stmt2->fetch(PDO::FETCH_ASSOC);
                    $redirection = '';
                    if ($user_plage && !empty($user_plage['plage_connexion'])) {
                        $jour = strtolower(date('l', time()));
                        $jours_fr = [
                            'monday' => 'lundi',
                            'tuesday' => 'mardi',
                            'wednesday' => 'mercredi',
                            'thursday' => 'jeudi',
                            'friday' => 'vendredi',
                            'saturday' => 'samedi',
                            'sunday' => 'dimanche',
                        ];
                        $jour_fr = $jours_fr[$jour];
                        $plages = explode(';', $user_plage['plage_connexion']);
                        foreach ($plages as $plage) {
                            list($j, $val) = array_pad(explode(':', $plage, 2), 2, '');
                            if (trim(strtolower($j)) === $jour_fr && trim($val) !== '') {
                                $redirection = trim(strtolower($val));
                                break;
                            }
                        }
                    }
                    // Redirection stricte selon la valeur du jour dans plage_connexion
                    switch ($redirection) {
                        case 'boutique':
                            header('Location: apps/boutique/homeboutique.php?day='.$jour);
                            break;
                        case 'logistique':
                            header('Location: apps/logistique/homelogistique.php?day='.$jour);
                            break;
                        case 'secretariat':
                            header('Location: apps/secretariat/homesecretariat.php?day='.$jour);
                            break;
                        case 'caisse':
                            header('Location: apps/caisse/homecaisse.php?day='.$jour);
                            break;
                        case 'ophtalmologue':
                            header('Location: apps/ophtalmologie/homeophtalmologie.php?day='.$jour);
                            break;
                        case 'comptabilite':
                            header('Location: apps/comptabilite/homecomptabilite.php?day='.$jour);
                            break;
                        case 'technologie':
                            header('Location: apps/technologie/hometechnologie.php?day='.$jour);
                            break;
                        case 'infirmier':
                            header('Location: apps/infirmerie/homeinfirmier.php?day='.$jour);
                            break;
                        case 'optometriste':
                            header('Location: apps/optometrie/homeoptometriste.php?day='.$jour);
                            break;
                        case 'medecin':
                            header('Location: apps/medecinchef/homemedecinchef.php?day='.$jour);
                            break;
                        default:
                            header('Location: index.php');
                    }
                    exit;
                } else {
                    $_SESSION['otp_attempts']++;
                    $_SESSION['last_attempt_time'] = time();
                    $errors[] = "Code de vérification incorrect.";
                }
            } catch (PDOException $e) {
                error_log("Erreur lors de la vérification OTP : " . $e->getMessage());
                $errors[] = "Une erreur est survenue. Veuillez réessayer.";
            }
        }
    }
}
?>

<!doctype html>
<html class="landing simple-sticky-header-enabled">
	<head>

		<!-- Basic -->
		<meta charset="UTF-8">

		<title>PLATEFORME DE GESTION EYE CENTER</title>

		<meta name="keywords" content="Clinique d'Ophtalmologie EYE Center" />
		<meta name="description" content="Clinique d'Ophtalmologie EYE Center">
		<meta name="author" content="Clinique d'Ophtalmologie EYE Center">

		<!-- Mobile Metas -->
		<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />

		<!-- Web Fonts  -->
		<link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700,800%7COpen+Sans:400,700,800" rel="stylesheet" type="text/css">

		<link rel="icon" href="img/logo.jpg" type="image/jpg">
		<!-- Vendor CSS -->
		<link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.css" />
		<link rel="stylesheet" href="vendor/animate/animate.compat.css">
		<link rel="stylesheet" href="vendor/font-awesome/css/all.min.css" />
		<link rel="stylesheet" href="vendor/boxicons/css/boxicons.min.css" />
		<link rel="stylesheet" href="vendor/magnific-popup/magnific-popup.css" />
		<link rel="stylesheet" href="vendor/bootstrap-datepicker/css/bootstrap-datepicker3.css" />
		<link rel="stylesheet" href="vendor/owl.carousel/assets/owl.carousel.css" />
		<link rel="stylesheet" href="vendor/owl.carousel/assets/owl.theme.default.css" />

		<!-- Theme CSS -->
		<link rel="stylesheet" href="css/theme.css" />

<!-- Landing Page CSS -->
		<link rel="stylesheet" href="css/landing.css" />

		<!-- Skin CSS -->
		<link rel="stylesheet" href="css/skins/default.css" />

		<!-- Theme Custom CSS -->
		<link rel="stylesheet" href="css/custom.css">

		<!-- Head Libs -->
		<script src="vendor/modernizr/modernizr.js"></script>

	</head>

	<body class="alternative-font-4 loading-overlay-showing" data-plugin-page-transition data-loading-overlay data-plugin-options="{'hideDelay': 100}">
		<div class="loading-overlay">
			<div class="bounce-loader">
				<div class="bounce1"></div>
				<div class="bounce2"></div>
				<div class="bounce3"></div>
			</div>
		</div>

		<div class="body">
			<header id="header" class="header header-nav-links header-nav-menu bg-color-light-scale-1" data-plugin-options="{'stickyEnabled': true, 'stickyEffect': 'shrink', 'stickyEnableOnBoxed': false, 'stickyEnableOnMobile': true, 'stickyStartAt': 70, 'stickyChangeLogo': false, 'stickyHeaderContainerHeight': 70}">
				<div style="position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0, 0, 0, 0.73);z-index:1;"></div>
                <div class="header-body border-top-0 bg-light box-shadow-none">
					<div class="header-container container h-100">
						<div class="header-row h-100">
							<div class="header-column">
								<div class="header-row">
									<div class="header-logo">
										<a href="#" class="goto-top"><img alt="Porto" width="102" height="45" data-sticky-width="82" data-sticky-height="36" data-sticky-top="0" src="img/lazy.png" data-plugin-lazyload data-plugin-options="{'threshold': 500}" data-original="img/logo.jpg"></a>
									</div>
								</div>
							</div>
							<div class="header-column justify-content-end">
								<div class="header-row">
									<button class="btn header-btn-collapse-nav d-lg-none order-3 mt-0 ms-3 me-0" data-bs-toggle="collapse" data-bs-target=".header-nav">
										<i class="fas fa-bars"></i>
									</button>
									<!-- start: header nav menu -->
									<div class="header-nav header-nav-links header-nav-light-text header-nav-dropdowns-dark collapse">
										<div class="header-nav-main header-nav-main-mobile-dark header-nav-main-effect-1 header-nav-main-sub-effect-1">
											<nav>
												<ul class="nav nav-pills" id="mainNav">
													<li>
													    <a class="nav-link" style="color: #000;" href="login.php" data-hash data-hash-offset="120">
													        Retour à la page de connexion
													    </a>    
													</li>
												</ul>
											</nav>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</header>

			<div role="main" class="main" style="background-size: cover; background-position: center; animation-duration: 750ms; animation-delay: 300ms; animation-fill-mode: forwards;" data-plugin-lazyload data-plugin-options="{'threshold': 500}" data-original="img/bgec.png">
                <section class="body-sign">
                <div class="center-sign">
                    <div class="panel card-sign">
                        <div class="card-body">
                            <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul>
                                    <?php foreach($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            
                            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?profil=verification" method="post" class="needs-validation" novalidate>
                                <div class="form-group mb-3">
                                    <div class="clearfix">
                                        <label class="float-start">Code de vérification</label>
                                        <?php if ($_SESSION['otp_attempts'] > 0): ?>
                                            <small class="text-danger float-end">
                                                <?php echo MAX_ATTEMPTS - $_SESSION['otp_attempts']; ?> tentatives restantes
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="input-group">
                                        <input name="code" type="password" class="form-control form-control-lg" 
                                               required minlength="6" maxlength="20"
                                               autocomplete="one-time-code" />
                                        <span class="input-group-text">
                                            <i class="bx bx-lock text-4"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12 text-end">
                                        <button type="submit" name="goin" class="btn btn-primary mt-2">continuer</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
			    </div>
            </div>

			<footer id="footer" class="bg-color-dark-scale-5 border border-end-0 border-start-0 border-bottom-0 border-color-light-3 mt-0">
				<div class="copyright bg-color-dark-scale-4 py-4">
					<div class="container text-center py-2">
						<p class="mb-0 text-2 ls-0">Copyright <?= date('Y'); ?> Guinée Pro Solutions - Tous droits reservés !</p>
					</div>
				</div>
			</footer>
		</div>

		<!-- Vendor -->
		<script src="vendor/jquery/jquery.js"></script>
		<script src="vendor/jquery-browser-mobile/jquery.browser.mobile.js"></script>
		<script src="vendor/popper/umd/popper.min.js"></script>
		<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
		<script src="vendor/bootstrap-datepicker/js/bootstrap-datepicker.js"></script>
		<script src="vendor/common/common.js"></script>
		<script src="vendor/nanoscroller/nanoscroller.js"></script>
		<script src="vendor/magnific-popup/jquery.magnific-popup.js"></script>
		<script src="vendor/jquery-placeholder/jquery.placeholder.js"></script>

		<!-- Specific Page Vendor -->
		<script src="vendor/jquery-appear/jquery.appear.js"></script>
		<script src="vendor/owl.carousel/owl.carousel.js"></script>
		<script src="vendor/jquery.lazyload/jquery.lazyload.js"></script>

		<!-- Theme Base, Components and Settings -->
		<script src="js/theme.js"></script>

		<!-- Theme Custom -->
		<script src="js/custom.js"></script>

		<!-- Theme Initialization Files -->
		<script src="js/theme.init.js"></script>

	</body>
</html>