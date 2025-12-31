<?php
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');

session_start();

class UserProfile {
    private $bdd;
    private $errors = [];
    private $messages = [];
    private $userId;
    
    const MSG_PASSWORD_UPDATED = 'Votre mot de passe a été mis à jour avec succès.';
    const MSG_CODE_UPDATED = 'Votre code secret a été mis à jour avec succès.';
    const ERR_PASSWORD_MISMATCH = 'Les mots de passe ne correspondent pas.';
    const ERR_CODE_MISMATCH = 'Les codes secrets ne correspondent pas.';
    
    public function __construct($bdd, $userId) {
        $this->bdd = $bdd;
        $this->userId = $userId;
    }
    
    public function updatePassword($password, $confirmPassword) {
        if (!$this->validatePasswords($password, $confirmPassword)) {
            return false;
        }
        
        try {
            $stmt = $this->bdd->prepare('UPDATE users SET mdp = ? WHERE id = ?');
            $success = $stmt->execute([
                password_hash($password, PASSWORD_DEFAULT),
                $this->userId
            ]);
            
            if ($success) {
                $this->messages[] = self::MSG_PASSWORD_UPDATED;
                return true;
            }
            
            $this->errors[] = "Erreur lors de la mise à jour du mot de passe.";
            return false;
            
        } catch (PDOException $e) {
            error_log("Erreur de mise à jour du mot de passe: " . $e->getMessage());
            $this->errors[] = "Une erreur est survenue lors de la mise à jour.";
            return false;
        }
    }
    
    public function updateSecretCode($code, $confirmCode) {
        if (!$this->validateCodes($code, $confirmCode)) {
            return false;
        }
        
        try {
            $stmt = $this->bdd->prepare('UPDATE users SET token = ? WHERE id = ?');
            $success = $stmt->execute([
                password_hash($code, PASSWORD_DEFAULT),
                $this->userId
            ]);
            
            if ($success) {
                $this->messages[] = self::MSG_CODE_UPDATED;
                return true;
            }
            
            $this->errors[] = "Erreur lors de la mise à jour du code secret.";
            return false;
            
        } catch (PDOException $e) {
            error_log("Erreur de mise à jour du code secret: " . $e->getMessage());
            $this->errors[] = "Une erreur est survenue lors de la mise à jour.";
            return false;
        }
    }
    
    private function validatePasswords($password, $confirmPassword) {
        if (empty($password) || empty($confirmPassword)) {
            $this->errors[] = "Les champs mot de passe ne peuvent pas être vides.";
            return false;
        }
        
        if ($password !== $confirmPassword) {
            $this->errors[] = self::ERR_PASSWORD_MISMATCH;
            return false;
        }
        
        if (strlen($password) < 8) {
            $this->errors[] = "Le mot de passe doit contenir au moins 8 caractères.";
            return false;
        }
        
        return true;
    }
    
    private function validateCodes($code, $confirmCode) {
        if (empty($code) || empty($confirmCode)) {
            $this->errors[] = "Les champs code secret ne peuvent pas être vides.";
            return false;
        }
        
        if ($code !== $confirmCode) {
            $this->errors[] = self::ERR_CODE_MISMATCH;
            return false;
        }
        
        if (!preg_match('/^\d{4,6}$/', $code)) {
            $this->errors[] = "Le code secret doit contenir entre 4 et 6 chiffres.";
            return false;
        }
        
        return true;
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function getMessages() {
        return $this->messages;
    }
    
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    public function hasMessages() {
        return !empty($this->messages);
    }
}

// Initialisation et traitement des formulaires
$userProfile = new UserProfile($bdd, $_GET['id'] ?? null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mdpupdate'])) {
        $userProfile->updatePassword(
            $_POST['mdp'] ?? '',
            $_POST['confirm'] ?? ''
        );
    }
    
    if (isset($_POST['codeupdate'])) {
        $userProfile->updateSecretCode(
            $_POST['code'] ?? '',
            $_POST['code_confirm'] ?? ''
        );
    }
}

// Inclusion de l'en-tête
include('../PUBLIC/header.php');
?>

<body>
    <section class="body">
        <?php require('../PUBLIC/navbarmenu.php'); ?>
        
        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Profil de l'utilisateur</h2>
                </header>

                <!-- start: page -->
                <div class="row">
                    <div class="col-lg-10 col-xl-10">
                        <div class="tabs">
                            
                            <div class="tab-content">
                                <?php if ($userProfile->hasMessages()): ?>
                                    <?php foreach ($userProfile->getMessages() as $message): ?>
                                        <div class="alert alert-success">
                                            <strong>Succès !</strong><br>
                                            <?php echo htmlspecialchars($message); ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if ($userProfile->hasErrors()): ?>
                                    <?php foreach ($userProfile->getErrors() as $error): ?>
                                        <div class="alert alert-danger">
                                            <?php echo htmlspecialchars($error); ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <div id="info" class="tab-pane active">
                                    <div class="p-3">
                                        
                                        <div class="row form-group pb-3">
                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label class="col-form-label">Employé</label>
                                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user); ?>" disabled>
                                                </div>
                                            </div>
                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label class="col-form-label">Courriel</label>
                                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($email); ?>" disabled>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row form-group pb-3">
                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label class="col-form-label">Mon service</label>
                                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(service($id_service)); ?>" disabled>
                                                </div>
                                            </div>
                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label class="col-form-label">Mon responsable</label>
                                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(responsable($responsable)); ?>" disabled>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
										<div class="col-sm-6 text-begin">
                                                <button type="button" class="btn btn-primary"
                                                        data-bs-toggle="modal" data-bs-target="#modalUpdatePassword"
                                                        data-toggle="modal" data-target="#modalUpdatePassword">
                                                    Mettre à jour mon mot de passe
                                                </button>
                                            </div>
                                            <div class="col-sm-6 text-end">
                                                <button type="button" class="btn btn-success"
                                                        data-bs-toggle="modal" data-bs-target="#modalUpdateCode"
                                                        data-toggle="modal" data-target="#modalUpdateCode">
                                                    Mettre à jour mon code secret
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-2 col-lg-2">
                        <h4 class="mb-3 mt-0 font-weight-semibold text-dark">Mes réalisations</h4>
                        <ul class="simple-card-list mb-3">
                            <li class="primary">
                                <h3>0</h3>
                                <p class="text-light">Mes projets.</p>
                            </li>
                            <li class="primary">
                                <h3>0</h3>
                                <p class="text-light">Mes tâches.</p>
                            </li>
                            <li class="primary">
                                <h3>0 GNF</h3>
                                <p class="text-light">Ma prime.</p>
                            </li>
                            <li class="primary">
                                <h3>0</h3>
                                <p class="text-light">Mes évaluations.</p>
                            </li>
                            <li class="primary">
                                <?php
                                $dateEngagement = return_annee($_SESSION['auth']);
                                if ($dateEngagement) {
                                    $annee = abs(strtotime('now') - strtotime($dateEngagement));
                                    $ancien_de = floor($annee / (365 * 60 * 60 * 24));
                                    ?>
                                    <h3><?php echo $ancien_de; ?> ans</h3>
                                    <p class="text-light">En service depuis le : <?php echo htmlspecialchars($dateEngagement); ?></p>
                                <?php } ?>
                            </li>
                        </ul>
                    </div>
                </div>
                <!-- end: page -->
            </section>
        </div>

        <!-- Modal: Mise à jour mot de passe -->
        <div class="modal fade" id="modalUpdatePassword" tabindex="-1" role="dialog" aria-labelledby="modalUpdatePasswordLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalUpdatePasswordLabel">Mettre à jour mon mot de passe</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <form action="profil.php?id=<?php echo htmlspecialchars($_GET['id']); ?>" method="POST" class="p-3" autocomplete="off">
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
                            <button type="button" class="btn btn-default" data-bs-dismiss="modal" data-dismiss="modal">Annuler</button>
                            <button type="submit" name="mdpupdate" class="btn btn-primary">Valider</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal: Mise à jour code secret -->
        <div class="modal fade" id="modalUpdateCode" tabindex="-1" role="dialog" aria-labelledby="modalUpdateCodeLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalUpdateCodeLabel">Mettre à jour mon code secret</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <form action="profil.php?id=<?php echo htmlspecialchars($_GET['id']); ?>" method="POST" class="p-3" autocomplete="off">
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
                            <button type="button" class="btn btn-default" data-bs-dismiss="modal" data-dismiss="modal">Annuler</button>
                            <button type="submit" name="codeupdate" class="btn btn-success">Valider</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include('../PUBLIC/footer.php'); ?>
