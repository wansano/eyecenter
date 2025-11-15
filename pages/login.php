<?php
    include('apps/PUBLIC/connect.php');

// Démarrer la session de manière sécurisée
session_start();
session_regenerate_id(true);

// Initialisation des variables
$errors = [];
$max_attempts = 3;
$lockout_time = 300; // 5 minutes

// Nettoyage des anciennes tentatives (plus de 5 minutes)
if (isset($_SESSION['login_attempts_time']) && (time() - $_SESSION['login_attempts_time'] > $lockout_time)) {
    unset($_SESSION['login_attempts']);
    unset($_SESSION['login_attempts_time']);
}

if (isset($_POST['goverif'])) {
    // Vérification anti-force brute
    if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= $max_attempts) {
        $time_left = $lockout_time - (time() - $_SESSION['login_attempts_time']);
        if ($time_left > 0) {
            $errors[] = "Trop de tentatives. Veuillez réessayer dans " . ceil($time_left/60) . " minutes.";
        }
    } else {
        // Validation des entrées
        $email = filter_var($_POST['username'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $_POST['pwd'] ?? '';

        if (empty($email) || empty($password)) {
            $errors[] = "Tous les champs sont obligatoires.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Format d'email invalide.";
        } else {
            try {
                $stmt = $bdd->prepare('SELECT id, mdp, type, status, plage_connexion FROM users WHERE email = ?');
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && $user['status'] == 1 && password_verify($password, $user['mdp'])) {
                    // Vérification de la plage de connexion
                    $jour = strtolower(date('l', time())); // ex: monday
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
                    $autorise = false;
                    if (!empty($user['plage_connexion'])) {
                        $plages = explode(';', $user['plage_connexion']);
                        foreach ($plages as $plage) {
                            list($j, $val) = array_pad(explode(':', $plage, 2), 2, '');
                            if (strtolower($j) === $jour_fr && !empty($val)) {
                                $autorise = true;
                                break;
                            }
                        }
                    }
                    if (!$autorise) {
                        $errors[] = "Votre compte n'est pas autorisé à ce connecter les ".ucfirst($jour_fr).".";
                    } else {
                        // Réinitialisation des tentatives de connexion
                        unset($_SESSION['login_attempts']);
                        unset($_SESSION['login_attempts_time']);

                        // Création de la session
                        $_SESSION['auth'] = $user['id'];
                        $_SESSION['user_type'] = $user['type'];
                        $_SESSION['last_activity'] = time();

                        // Redirection
                        header('Location: verifusercompte.php?profil=verification');
                        exit;
                    }
                } else {
                    // Incrémentation des tentatives de connexion
                    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                    $_SESSION['login_attempts_time'] = time();
                    
                    $errors[] = "Identifiants incorrects ou compte désactivé.";
                }
            } catch (PDOException $e) {
                error_log("Erreur de connexion : " . $e->getMessage());
                $errors[] = "Une erreur est survenue. Veuillez réessayer plus tard.";
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
										<a href="#" class="goto-top"><img alt="logo" width="102" height="45" data-sticky-width="82" data-sticky-height="36" data-sticky-top="0" src="img/lazy.png" data-plugin-lazyload data-plugin-options="{'threshold': 500}" data-original="img/logo.jpg"></a>
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
													    <a class="nav-link" style="color: #000;" href="index.php" data-hash data-hash-offset="120">
													        Retourner à l'acceuil
													    </a>    
													</li>
												</ul>
											</nav>
										</div>
									</div>
									<!-- end: header nav menu -->
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
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                </div>
                                <?php endif; ?>

                                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) ;?>" method="post" class="needs-validation" novalidate>
                                    <div class="form-group mb-3">
                                        <label>Courriel</label>
                                        <div class="input-group">
                                            <input name="username" type="email" class="form-control form-control-lg" required 
                                                pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                                                value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" />
                                            <span class="input-group-text">
                                                <i class="bx bx-user text-4"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="form-group mb-3">
                                        <div class="clearfix">
                                            <label class="float-start">Mot de passe</label>
                                        </div>
                                        <div class="input-group">
                                            <input name="pwd" type="password" class="form-control form-control-lg" />
                                            <span class="input-group-text">
                                                <i class="bx bx-lock text-4"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <div class="checkbox-custom checkbox-default">
                                            </div>
                                        </div>
                                        <div class="col-sm-6 text-end">
                                            <button type="submit" name="goverif" class="btn btn-primary mt-2">connexion</button>
                                        </div>
                                    </div>

                                </form>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

			<footer id="footer" class="bg-color-dark-scale-5 border border-end-0 border-start-0 border-bottom-0 border-color-light-3 mt-0">
				<div class="copyright bg-color-dark-scale-4 py-4">
					<div class="container text-center py-2">
						<p class="mb-0 text-2 ls-0">Copyright 2025 Guinée Pro Solutions - Tous droits reservés !</p>
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