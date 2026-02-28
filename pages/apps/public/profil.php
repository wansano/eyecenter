<?php
require_once('connect.php');
require_once('fonction.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['auth'])) {
    header('Location: ../../login.php');
    exit;
}

function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$viewerId = (int)($_SESSION['auth'] ?? 0);
$viewerInfo = $viewerId > 0 ? (getUserInfo($bdd, $viewerId) ?: []) : [];
$viewerType = (string)($viewerInfo['type'] ?? '');
$viewerResponsableFlag = (int)($viewerInfo['responsable'] ?? 1);

$requestedId = (int)($_GET['id'] ?? ($_GET['id_user'] ?? ($_GET['u'] ?? 0)));
if ($requestedId <= 0) {
    $requestedId = $viewerId;
}

$canViewOthers = ($viewerResponsableFlag === 0) || in_array($viewerType, ['technologie', 'hr'], true);

$warning = null;
if ($requestedId !== $viewerId && !$canViewOthers) {
    $warning = "Accès refusé : affichage de votre profil.";
    $requestedId = $viewerId;
}

$profileId = $requestedId;
$profileInfo = $profileId > 0 ? (getUserInfo($bdd, $profileId) ?: []) : [];

$messages = [];
$errors = [];

$isSelf = ($profileId === $viewerId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if (!$isSelf) {
        $errors[] = "Vous ne pouvez modifier que votre propre profil.";
    } else {
        if ($action === 'update_password') {
            $password = (string)($_POST['mdp'] ?? '');
            $confirm = (string)($_POST['confirm'] ?? '');

            if ($password === '' || $confirm === '') {
                $errors[] = 'Les champs mot de passe ne peuvent pas être vides.';
            } elseif ($password !== $confirm) {
                $errors[] = 'Les mots de passe ne correspondent pas.';
            } elseif (strlen($password) < 8) {
                $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
            } else {
                try {
                    $stmt = $bdd->prepare('UPDATE users SET mdp = ? WHERE id = ?');
                    $ok = $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $viewerId]);
                    if ($ok) {
                        $messages[] = 'Votre mot de passe a été mis à jour avec succès.';
                    } else {
                        $errors[] = 'Erreur lors de la mise à jour du mot de passe.';
                    }
                } catch (PDOException $e) {
                    error_log('[profil] update_password: ' . $e->getMessage());
                    $errors[] = "Une erreur est survenue lors de la mise à jour.";
                }
            }
        }

        if ($action === 'update_code') {
            $code = (string)($_POST['code'] ?? '');
            $confirmCode = (string)($_POST['code_confirm'] ?? '');

            if ($code === '' || $confirmCode === '') {
                $errors[] = 'Les champs code secret ne peuvent pas être vides.';
            } elseif ($code !== $confirmCode) {
                $errors[] = 'Les codes secrets ne correspondent pas.';
            } elseif (!preg_match('/^\d{4,6}$/', $code)) {
                $errors[] = 'Le code secret doit contenir entre 4 et 6 chiffres.';
            } else {
                try {
                    $stmt = $bdd->prepare('UPDATE users SET token = ? WHERE id = ?');
                    $ok = $stmt->execute([password_hash($code, PASSWORD_DEFAULT), $viewerId]);
                    if ($ok) {
                        $messages[] = 'Votre code secret a été mis à jour avec succès.';
                    } else {
                        $errors[] = 'Erreur lors de la mise à jour du code secret.';
                    }
                } catch (PDOException $e) {
                    error_log('[profil] update_code: ' . $e->getMessage());
                    $errors[] = "Une erreur est survenue lors de la mise à jour.";
                }
            }
        }
    }

    // Rafraîchir les infos profil après update
    $profileInfo = $profileId > 0 ? (getUserInfo($bdd, $profileId) ?: []) : [];
}

// Liste des utilisateurs (si autorisé)
$usersList = [];
if ($canViewOthers) {
    try {
        $st = $bdd->query('SELECT id, pseudo, type FROM users ORDER BY pseudo ASC LIMIT 500');
        $usersList = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        error_log('[profil] usersList: ' . $e->getMessage());
        $usersList = [];
    }
}

include('header.php');
?>

<body>
    <section class="body">
        <?php require('navbarmenu.php'); ?>

        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Profil</h2>
                </header>

                <?php if ($warning): ?>
                    <div class="alert alert-warning"><?php echo h($warning); ?></div>
                <?php endif; ?>

                <?php foreach ($messages as $m): ?>
                    <div class="alert alert-success"><strong>Succès !</strong><br><?php echo h($m); ?></div>
                <?php endforeach; ?>

                <?php foreach ($errors as $e): ?>
                    <div class="alert alert-danger"><?php echo h($e); ?></div>
                <?php endforeach; ?>

                <div class="row">
                    <div class="col-lg-10 col-xl-10">
                        <section class="card">
                            <div class="card-body">

                                <?php if ($canViewOthers): ?>
                                    <form method="GET" class="d-flex align-items-end mb-3" style="gap:10px;">
                                        <div>
                                            <label class="form-label" for="id">Accéder à un profil</label>
                                            <select class="form-select" name="id" id="id" style="min-width:260px;">
                                                <?php foreach ($usersList as $u): ?>
                                                    <option value="<?php echo (int)($u['id'] ?? 0); ?>" <?php echo ((int)($u['id'] ?? 0) === $profileId) ? 'selected' : ''; ?>>
                                                        <?php
                                                            $label = trim((string)($u['pseudo'] ?? ''));
                                                            $t = trim((string)($u['type'] ?? ''));
                                                            echo h($label . ($t !== '' ? ' (' . $t . ')' : ''));
                                                        ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Ouvrir</button>
                                        <?php if (!$isSelf): ?>
                                            <a class="btn btn-default" href="profil.php?id=<?php echo (int)$viewerId; ?>">Revenir à mon profil</a>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>

                                <?php if (empty($profileInfo)): ?>
                                    <div class="alert alert-danger">Utilisateur introuvable.</div>
                                <?php else: ?>
                                    <div class="row form-group pb-3">
                                        <div class="col-lg-6">
                                            <div class="form-group">
                                                <label class="col-form-label">Employé</label>
                                                <input type="text" class="form-control" value="<?php echo h($profileInfo['pseudo'] ?? ''); ?>" disabled>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="form-group">
                                                <label class="col-form-label">Courriel</label>
                                                <input type="text" class="form-control" value="<?php echo h($profileInfo['email'] ?? ''); ?>" disabled>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row form-group pb-3">
                                        <div class="col-lg-6">
                                            <div class="form-group">
                                                <label class="col-form-label">Service</label>
                                                <input type="text" class="form-control" value="<?php echo h(service((int)($profileInfo['id_service'] ?? 0))); ?>" disabled>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="form-group">
                                                <label class="col-form-label">Responsable</label>
                                                <input type="text" class="form-control" value="<?php echo h(responsable((int)($profileInfo['responsable'] ?? 0))); ?>" disabled>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row form-group pb-3">
                                        <div class="col-lg-6">
                                            <div class="form-group">
                                                <label class="col-form-label">Date d'engagement</label>
                                                <input type="text" class="form-control" value="<?php echo h(return_annee($profileId)); ?>" disabled>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($isSelf): ?>
                                        <div class="row">
                                            <div class="col-sm-6 text-begin">
                                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUpdatePassword">
                                                    Mettre à jour mon mot de passe
                                                </button>
                                            </div>
                                            <div class="col-sm-6 text-end">
                                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalUpdateCode">
                                                    Mettre à jour mon code secret
                                                </button>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info mb-0">
                                            Consultation en lecture seule : modifications réservées au propriétaire du profil.
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                            </div>
                        </section>
                    </div>

                    <div class="col-xl-2 col-lg-2">
                        <h4 class="mb-3 mt-0 font-weight-semibold text-dark">Réalisations</h4>
                        <ul class="simple-card-list mb-3">
                            <li class="primary">
                                <h3>0</h3>
                                <p class="text-light">Projets.</p>
                            </li>
                            <li class="primary">
                                <h3>0</h3>
                                <p class="text-light">Tâches.</p>
                            </li>
                            <li class="primary">
                                <h3>0 GNF</h3>
                                <p class="text-light">Prime.</p>
                            </li>
                            <li class="primary">
                                <h3>0</h3>
                                <p class="text-light">Évaluations.</p>
                            </li>
                            <li class="primary">
                                <?php
                                $dateEngagement = $profileId > 0 ? return_annee($profileId) : '';
                                if ($dateEngagement) {
                                    $annee = abs(strtotime('now') - strtotime($dateEngagement));
                                    $ancien_de = floor($annee / (365 * 60 * 60 * 24));
                                    ?>
                                    <h3><?php echo (int)$ancien_de; ?> ans</h3>
                                    <p class="text-light">En service depuis le : <?php echo h($dateEngagement); ?></p>
                                <?php } ?>
                            </li>
                        </ul>
                    </div>
                </div>

            </section>
        </div>

        <?php if ($isSelf): ?>
            <!-- Modal: Mise à jour mot de passe -->
            <div class="modal fade" id="modalUpdatePassword" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Mettre à jour mon mot de passe</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <form action="profil.php?id=<?php echo (int)$viewerId; ?>" method="POST" class="p-3" autocomplete="off">
                            <input type="hidden" name="action" value="update_password">
                            <div class="modal-body">
                                <div class="row form-group pb-3">
                                    <div class="form-group col-md-6">
                                        <label>Nouveau mot de passe</label>
                                        <input type="password" name="mdp" class="form-control" placeholder="Mot de passe" minlength="8" required autocomplete="new-password">
                                    </div>
                                    <div class="col-md-6">
                                        <label>Confirmer le nouveau mot de passe</label>
                                        <input type="password" name="confirm" class="form-control" placeholder="Confirmation" minlength="8" required autocomplete="new-password">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-bs-dismiss="modal">Annuler</button>
                                <button type="submit" class="btn btn-primary">Valider</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Modal: Mise à jour code secret -->
            <div class="modal fade" id="modalUpdateCode" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Mettre à jour mon code secret</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <form action="profil.php?id=<?php echo (int)$viewerId; ?>" method="POST" class="p-3" autocomplete="off">
                            <input type="hidden" name="action" value="update_code">
                            <div class="modal-body">
                                <div class="row form-group pb-3">
                                    <div class="form-group col-md-6">
                                        <label>Nouveau code</label>
                                        <input type="password" name="code" class="form-control" placeholder="Code confidentiel" pattern="\d{4,6}" title="4 à 6 chiffres" required inputmode="numeric" autocomplete="one-time-code">
                                    </div>
                                    <div class="col-md-6">
                                        <label>Confirmer le nouveau code</label>
                                        <input type="password" name="code_confirm" class="form-control" placeholder="Confirmation" pattern="\d{4,6}" title="4 à 6 chiffres" required inputmode="numeric" autocomplete="one-time-code">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-bs-dismiss="modal">Annuler</button>
                                <button type="submit" class="btn btn-success">Valider</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php include('footer.php'); ?>
