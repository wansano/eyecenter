<?php
include('../PUBLIC/connect.php');
session_start();

// Fonction pour nettoyer les entrées
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Fonction pour valider les données du formulaire
function validateAccountData($data) {
    $errors = [];
    
    if (empty($data['nom_compte'])) {
        $errors[] = "Le nom du compte est requis";
    }
    
    if (empty($data['code'])) {
        $errors[] = "Le code du compte est requis";
    }
    
    if (!in_array($data['types'], ['Espèce', 'Chèque', 'Mobile'])) {
        $errors[] = "Type de règlement invalide";
    }
    
    if (!is_numeric($data['disponibilite'])) {
        $errors[] = "La disponibilité doit être un nombre";
    }
    
    if ($data['types'] === 'Mobile' && (!is_numeric($data['taux']) || $data['taux'] < 0)) {
        $errors[] = "Le taux doit être un nombre positif pour le paiement marchand";
    }
    
    if (!in_array($data['defaut'], ['0', '1'])) {
        $errors[] = "Valeur de confidentialité invalide";
    }
    
    if (!in_array($data['pour'], ['1', '2'])) {
        $errors[] = "Valeur 'pour' invalide";
    }
    
    return $errors;
}

// Initialisation des variables
$errors = [];
$success = false;
$formData = [];

// Traitement du formulaire
if (isset($_POST['ajouter'])) {
    // Nettoyage et récupération des données
    $formData = [
        'nom_compte' => cleanInput($_POST['nom_compte'] ?? ''),
        'types' => cleanInput($_POST['types'] ?? ''),
        'code' => cleanInput($_POST['code'] ?? ''),
        'disponibilite' => cleanInput($_POST['disponibilite'] ?? ''),
        'taux' => cleanInput($_POST['taux'] ?? '0'),
        'defaut' => cleanInput($_POST['defaut'] ?? ''),
        'pour' => cleanInput($_POST['pour'] ?? '')
    ];
    
    // Validation des données
    $errors = validateAccountData($formData);
    
    if (empty($errors)) {
        try {
            $bdd->beginTransaction();
            
            // Vérification de l'existence du compte
            $req1 = $bdd->prepare('SELECT COUNT(*) FROM comptes WHERE nom_compte = ? OR code = ?');
            $req1->execute([$formData['nom_compte'], $formData['code']]);
            $compte_existe = $req1->fetchColumn() > 0;
            
            if ($compte_existe) {
                $errors[] = "Ce compte ou ce code existe déjà dans le système";
            } else {
                // Insertion du nouveau compte
                $req = $bdd->prepare('INSERT INTO comptes (nom_compte, types, code, debit, taux, defaut, compte_pour) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?)');
                                    
                $req->execute([
                    $formData['nom_compte'],
                    $formData['types'],
                    $formData['code'],
                    $formData['disponibilite'],
                    $formData['taux'],
                    $formData['defaut'],
                    $formData['pour']
                ]);
                
                $bdd->commit();
                $success = true;
                $formData = []; // Réinitialisation du formulaire après succès
            }
        } catch (Exception $e) {
            $bdd->rollBack();
            error_log("Erreur lors de l'ajout du compte : " . $e->getMessage());
            $errors[] = "Une erreur est survenue lors de l'ajout du compte";
        }
    }
}

// Fonction pour récupérer la valeur d'un champ
function getFormValue($field, $default = '') {
    global $formData;
    return isset($formData[$field]) ? htmlspecialchars($formData[$field]) : $default;
}
?>
<?php include '../PUBLIC/header.php'; ?>
<body>
    <section class="body">
        <?php require '../PUBLIC/navbarmenu.php'; ?>
        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Ajouter un nouveau compte</h2>
                    <div class="right-wrapper text-end">
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <section class="card">
                        <header class="card-header">
                            <h2 class="card-title">Formulaire d'ajout de compte</h2>
                        </header>
                        <div class="card-body">
                            <?php if ($success): ?>
                                <div class="alert alert-success">
                                    <strong>Succès</strong><br>
                                    Le compte a été ajouté avec succès !
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <strong>Erreur(s)</strong><br>
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <form class="form-horizontal" method="POST" action="addaccount.php" novalidate="novalidate">
                                <input type="hidden" name="ajouter" value="1">
                                <div class="row form-group pb-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="nom_compte">Nom du compte</label>
                                            <input type="text" name="nom_compte" id="nom_compte" class="form-control" 
                                                   value="<?php echo getFormValue('nom_compte'); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label">Type de règlement</label>
                                            <select class="form-control populate" name="types" required>
                                                <?php
                                                $types = ['Espèce', 'Chèque', 'Mobile'];
                                                foreach ($types as $type):
                                                    $selected = getFormValue('types') === $type ? 'selected' : '';
                                                ?>
                                                    <option value="<?php echo $type; ?>" <?php echo $selected; ?>><?php echo $type; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="code">Code compte</label>
                                            <input type="text" class="form-control" name="code" id="code"
                                                   value="<?php echo getFormValue('code'); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="disponibilite">Disponibilité actuelle</label>
                                            <input type="number" class="form-control" name="disponibilite" id="disponibilite"
                                                   value="<?php echo getFormValue('disponibilite', '0'); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row form-group pb-3">
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label">Confidentialité</label>
                                            <select class="form-control populate" name="defaut" required>
                                                <option value="1" <?php echo getFormValue('defaut') === '1' ? 'selected' : ''; ?>>Public</option>
                                                <option value="0" <?php echo getFormValue('defaut') === '0' ? 'selected' : ''; ?>>Privé</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="taux">Taux si Paiement Marchand sinon 0</label>
                                            <input type="number" step="0.1" class="form-control" name="taux" id="taux"
                                                   value="<?php echo getFormValue('taux', '0'); ?>" 
                                                   placeholder="exemple : 1.0 sans le %" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label">Pour</label>
                                            <select class="form-control populate" name="pour" required>
                                                <option value="1" <?php echo getFormValue('pour') === '1' ? 'selected' : ''; ?>>Clinique</option>
                                                <option value="2" <?php echo getFormValue('pour') === '2' ? 'selected' : ''; ?>>Boutique</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <footer class="card-footer text-end">
                                    <button class="btn btn-primary" type="submit">Ajouter le compte</button>
                                </footer>
                            </form>
                        </div>
                    </section>
                </div>
                <!-- end: page -->
            </section>
        </div>
        <?php include('../PUBLIC/footer.php'); ?>

