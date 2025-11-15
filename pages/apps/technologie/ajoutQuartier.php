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
    
    if (empty($data['quartier'])) {
        $errors[] = "Le nom du quartier est requis";
    }
    
    if (empty($data['ville'])) {
        $errors[] = "La ville du quartier est requis";
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
        'quartier' => cleanInput($_POST['quartier'] ?? ''),
        'ville' => cleanInput($_POST['ville'] ?? ''),
    ];
    
    // Validation des données
    $errors = validateAccountData($formData);
    
    if (empty($errors)) {
        try {
            $bdd->beginTransaction();

            // Vérification de l'existence du quartier
            $req1 = $bdd->prepare('SELECT COUNT(*) FROM adresses_quartiers WHERE quartier = ? AND id_ville = ?');
            $req1->execute([$formData['quartier'], $formData['ville']]);
            $quartier_existe = $req1->fetchColumn() > 0;

            if ($quartier_existe) {
                $errors[] = "Ce quartier existe déjà dans le système";
            } else {
                // Insertion du nouveau quartier
                $req = $bdd->prepare('INSERT INTO adresses_quartiers (id_ville, quartier) VALUES (?, ?)');
                $req->execute([$formData['ville'], $formData['quartier']]);
                // Commit de la transaction
                $bdd->commit();
                $success = true;
                $formData = []; // Réinitialisation du formulaire après succès
            }
        } catch (Exception $e) {
            $bdd->rollBack();
            error_log("Erreur lors de l'ajout du quartier : " . $e->getMessage());
            $errors[] = "Une erreur est survenue lors de l'ajout du quartier";
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
                    <h2>Ajouter un nouveau quatier</h2>
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <section class="card">
                        <div class="card-body">
                            <?php if ($success): ?>
                                <div class="alert alert-success">
                                    <strong>Succès</strong><br>
                                    Le quartier a été ajouté avec succès !
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <strong>Erreur</strong><br>
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <form class="form-horizontal" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" novalidate="novalidate">
                                <input type="hidden" name="ajouter" value="1">
                                <div class="row form-group pb-3">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Ville</label>
                                            <select class="form-control populate" name="ville" value="<?php echo getFormValue('ville'); ?>"  data-plugin-selectTwo data-plugin-options='{ "minimumInputLength": 0 }' required>
                                                <option value="">--- Choisir la ville ---</option>';
                                                    <?php
                                                    $coll = $bdd->prepare('SELECT id_ville, nom FROM adresses_villes');
                                                    $coll -> execute();
                                                    while ($ville = $coll->fetch(PDO::FETCH_ASSOC))
                                                    {
                                                        echo '<option value="'.$ville['id_ville'].'">'.$ville['nom'].'</option>';
                                                    } 
                                                    ?>
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <div class="form-group">
                                            <label class="col-form-label" for="quartier">Nom du quartier</label>
                                            <input type="text" class="form-control" name="quartier" id="quartier" value="<?php echo getFormValue('quartier'); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <footer class="card-footer text-end">
                                    <button class="btn btn-primary" type="submit">ajouter le quartier</button>
                                </footer>
                            </form>
                        </div>
                    </section>
                </div>
                <!-- end: page -->
            </section>
        </div>
        <?php include('../PUBLIC/footer.php'); ?>

