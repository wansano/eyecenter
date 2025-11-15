<?php
session_start();
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');

class PatientManager {
    private $bdd;
    private $errors = [];
    private $success = false;

    public function __construct($bdd) {
        $this->bdd = $bdd;
    }

    public function validateInput($data) {
        $errors = [];
        
        if (empty($data['nom_patient'])) {
            $errors[] = "Le nom du patient est requis";
        }
        
        if (!empty($data['phone']) && !preg_match('/^\d{9}$/', $data['phone'])) {
            $errors[] = "Le numéro de téléphone doit contenir 9 chiffres";
        }
        
        if (!in_array($data['sexe'], ['Homme', 'Femme'])) {
            $errors[] = "Le genre spécifié n'est pas valide";
        }
        
        if (!empty($data['age'])) {
            $date = DateTime::createFromFormat('Y-m-d', $data['age']);
            if (!$date || $date->format('Y-m-d') !== $data['age']) {
                $errors[] = "La date de naissance n'est pas valide";
            }
        }
        
        return $errors;
    }

    public function searchPatient($id) {
        $stmt = $this->bdd->prepare('SELECT * FROM patients WHERE id_patient = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updatePatient($data) {
        try {
            $validationErrors = $this->validateInput($data);
            if (!empty($validationErrors)) {
                $this->errors = $validationErrors;
                return false;
            }

            $stmt = $this->bdd->prepare('
                UPDATE patients 
                SET nom_patient = :nom,
                    adresse = :id_quartier,
                    phone = :phone,
                    responsable = :responsable,
                    profession = :profession,
                    age = :age,
                    sexe = :sexe 
                WHERE id_patient = :id
            ');

            $success = $stmt->execute([
                ':nom' => trim(strip_tags($data['nom_patient'])),
                ':id_quartier' => trim(strip_tags($data['adresse'])),
                ':phone' => trim(strip_tags($data['phone'])),
                ':responsable' => trim(strip_tags($data['responsable'])),
                ':profession' => trim(strip_tags($data['profession'])),
                ':age' => $data['age'],
                ':sexe' => $data['sexe'],
                ':id' => $data['modif_in']
            ]);

            if ($success) {
                $this->success = true;
                return true;
            }

            $this->errors[] = "Erreur lors de la mise à jour";
            return false;

        } catch (PDOException $e) {
            $this->errors[] = "Erreur de base de données: " . $e->getMessage();
            return false;
        }
    }

    public function getErrors() {
        return $this->errors;
    }

    public function hasSuccess() {
        return $this->success;
    }
}

// Initialisation
$patientManager = new PatientManager($bdd);
$searchResult = null;
$patientData = null;

// Traitement de la recherche
if (isset($_POST['recherche']) && !empty($_POST['recherche'])) {
    $searchResult = $patientManager->searchPatient($_POST['recherche']);
    if ($searchResult) {
        header('Location: editpatient.php?ep=default&id_patient=' . $_POST['recherche']);
        exit;
    }
}

// Traitement de la mise à jour
if (isset($_POST['modif_in'])) {
    if ($patientManager->updatePatient($_POST)) {
        $patientData = $patientManager->searchPatient($_POST['modif_in']);
    }
}

// Récupération des données du patient pour l'affichage
if (isset($_GET['id_patient'])) {
    $patientData = $patientManager->searchPatient($_GET['id_patient']);
}

include('../PUBLIC/header.php');
?>

<body>
    <section class="body">
        <?php require('../PUBLIC/navbarmenu.php'); ?>
        
        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Modification des informations</h2>
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <section class="card">
                        <div class="card-body">
                            <?php
                            // Affichage des messages
                            if ($patientManager->hasSuccess()) {
                                echo '<div class="alert alert-success"><strong>Succès</strong><br/>Information du patient mise à jour</div>';
                            }
                            
                            if (!empty($patientManager->getErrors())) {
                                echo '<div class="alert alert-danger"><ul>';
                                foreach ($patientManager->getErrors() as $error) {
                                    echo '<li>' . htmlspecialchars($error) . '</li>';
                                }
                                echo '</ul></div>';
                            }

                            // Formulaire de recherche si pas d'ID patient
                            if (!isset($_GET['id_patient'])) {
                                ?>
                                <form class="form-horizontal" method="POST" action="editpatient.php">
                                    <div class="row form-group pb-3">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="col-form-label">Saisir le numéro de dossier</label>
                                                <input type="text" class="form-control" name="recherche" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer text-end">
                                        <button class="btn btn-primary" type="submit">Rechercher</button>
                                    </div>
                                </form>
                                <?php
                            }

                            // Formulaire d'édition si patient trouvé
                            if ($patientData) {
                                ?>
                                <form class="form-horizontal" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?ep=default&id_patient=<?php echo htmlspecialchars($_GET['id_patient']); ?>">
                                    <input type="hidden" name="modif_in" value="<?php echo htmlspecialchars($_GET['id_patient']); ?>">
                                    
                                    <div class="row form-group pb-3">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="col-form-label">Prénoms & Nom</label>
                                                <input type="text" name="nom_patient" class="form-control" value="<?php echo htmlspecialchars($patientData['nom_patient']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label">Genre</label>
                                                <select class="form-control" name="sexe" required>
                                                    <option value="Homme" <?php echo $patientData['sexe'] === 'Homme' ? 'selected' : ''; ?>>Homme</option>
                                                    <option value="Femme" <?php echo $patientData['sexe'] === 'Femme' ? 'selected' : ''; ?>>Femme</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label">Date de naissance</label>
                                                <input type="date" class="form-control" name="age" value="<?php echo htmlspecialchars($patientData['age']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="col-form-label">Profession</label>
                                                <input type="text" class="form-control" name="profession" value="<?php echo htmlspecialchars($patientData['profession']); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row form-group pb-3">
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Ville de residence</label>
                                                <select class="form-control populate" id="villeSelect" onchange="updateQuartier()" data-plugin-selectTwo data-plugin-options='{ "minimumInputLength": 0 }' required>
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
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Quartier</label>
                                                <select name="adresse" class="form-control populate" id="quartierSelect" data-plugin-selectTwo data-plugin-options='{ "minimumInputLength": 0 }' required>
                                                    <option value="">
                                                        <?php
                                                        $quartierValue = '';
                                                        if ($patientData && isset($patientData['adresse'])) {
                                                            $quartierNom = quartier($patientData['adresse']);
                                                            if (is_array($quartierNom)) {
                                                                $quartierValue = htmlspecialchars( $patientData['adresse']);
                                                            } elseif ($quartierNom) {
                                                                $quartierValue = htmlspecialchars($quartierNom);
                                                            } else {
                                                                $quartierValue = htmlspecialchars($patientData['adresse']);
                                                            }
                                                        }
                                                        echo $quartierValue;
                                                        ?>
                                                    </option>
                                                </select>
                                                <input type="hidden" id="hiddenquartierId" name="quartier_id" value="">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label">Contact</label>
                                                <input type="tel" class="form-control" name="phone" maxlength="9" value="<?php echo htmlspecialchars($patientData['phone']); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-5">
                                            <div class="form-group">
                                                <label class="col-form-label">Responsable</label>
                                                <input type="text" class="form-control" name="responsable" value="<?php echo htmlspecialchars($patientData['responsable']); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card-footer text-end">
                                        <button class="btn btn-primary" type="submit">Mettre à jour</button>
                                    </div>
                                </form>
                                <?php
                            }
                            ?>
                        </div>
                    </section>
                </div>
                <!-- end: page -->
            </section>
        </div>
        <?php include('../PUBLIC/footer.php'); ?>
    </section>
</body>
</html>
