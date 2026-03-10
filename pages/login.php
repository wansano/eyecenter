<?php
    include('apps/PUBLIC/connect.php');

// Démarrer la session de manière sécurisée
session_start();
session_regenerate_id(true);

function is_ajax_request(): bool {
    if (isset($_GET['ajax']) && (string)$_GET['ajax'] === '1') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }
    return false;
}

function json_response(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

// Initialisation des variables
$errors = [];
$max_attempts = 4;
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

                        // Redirection / suite du flux
                        if (is_ajax_request()) {
                            json_response([
                                'success' => true,
                                'nextUrl' => 'verifusercompte.php?profil=verification&modal=1',
                            ]);
                        }

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

// Réponse AJAX en cas d'erreurs
if (is_ajax_request() && isset($_POST['goverif'])) {
    json_response([
        'success' => false,
        'errors' => $errors,
    ], 400);
}

// Rendu partiel pour affichage en modal (chargé via AJAX)
if (isset($_GET['modal']) && (string)$_GET['modal'] === '1') {
    ?>
    <div class="panel card-sign mb-0">
        <div class="card-body">
            <h4 class="mb-3"></h4>
            <form action="login.php" method="post" data-auth-form="login" data-ajax-action="login.php?ajax=1">
                <input type="hidden" name="goverif" value="1" />
                <div data-auth-errors></div>
                <div class="form-group mb-3">
                    <label>Courriel</label>
                    <div class="input-group">
                        <input name="username" type="email" class="form-control" required
                            pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" autocomplete="username" />
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
                        <input name="pwd" type="password" class="form-control" autocomplete="current-password" />
                        <button type="button" class="input-group-text" data-toggle-password aria-label="Afficher le mot de passe" aria-pressed="false">
                            <i class="bx bx-show text-4"></i>
                        </button>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12 text-end">
                        <button type="submit" class="btn btn-primary mt-2">connexion</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php
    exit;
}

?>

        <script>
            (function(){
                var input = document.getElementById('loginPassword');
                var btn = document.getElementById('togglePassword');
                var icon = document.getElementById('togglePasswordIcon');
                if (!input || !btn || !icon) return;

                btn.addEventListener('click', function(){
                    var isHidden = input.getAttribute('type') === 'password';
                    input.setAttribute('type', isHidden ? 'text' : 'password');
                    btn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
                    btn.setAttribute('aria-label', isHidden ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
                    icon.classList.remove(isHidden ? 'bx-show' : 'bx-hide');
                    icon.classList.add(isHidden ? 'bx-hide' : 'bx-show');
                });
            })();
        </script>