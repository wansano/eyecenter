<?php
include('../PUBLIC/connect.php');
session_start();

$errors = 0;
$existe = 0;
$id_patient = null;
$error_messages = array();

if (isset($_POST['ajouter'])) {
    // Vérification des champs requis
    if (empty($_POST['nom_patient'])) {
        $error_messages[] = "Le nom du patient est requis";
    }
    if (empty($_POST['age'])) {
        $error_messages[] = "La date de naissance est requise";
    }
    if (empty($_POST['phone'])) {
        $error_messages[] = "Le numéro de téléphone est requis";
    }
    if (empty($_POST['profession'])) {
        $error_messages[] = "La profession est requise";
    }
    if (empty($_POST['sexe'])) {
        $error_messages[] = "Le genre est requis";
    }
    if (empty($_POST['adresse'])) {
        $error_messages[] = "L'adresse est requise";
    }

    // Si pas d'erreurs, procéder à l'insertion
    if (empty($error_messages)) {
        try {
            $bdd->beginTransaction();

            // Vérification de l'existence du patient
            $req1 = $bdd->prepare('SELECT id_patient FROM patients WHERE phone = ? AND profession = ? AND sexe = ? AND adresse = ?');
            $req1->execute([
                $_POST['phone'], 
                $_POST['profession'], 
                $_POST['sexe'], 
                $_POST['adresse']
            ]);

            if ($data = $req1->fetch()) {
                $existe = 1;
                $patientid = $data['id_patient'];
            } else {
                // Insertion du patient
                $assure = $_POST['estAssure'] == 1 ? 1 : 0;
                $responsable = !empty($_POST['responsable']) ? $_POST['responsable'] : null;
                $entrepriseAssurance = $assure ? $_POST['entrepriseAssurance'] : 0;

                $req = $bdd->prepare('INSERT INTO patients (nom_patient, sexe, profession, age, adresse, phone, responsable, assure, assurance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $req->execute([
                    $_POST['nom_patient'], 
                    $_POST['sexe'], 
                    $_POST['profession'], 
                    $_POST['age'], 
                    $_POST['adresse'], 
                    $_POST['phone'], 
                    $responsable,
                    $assure, 
                    $entrepriseAssurance
                ]);

                $errors = 2;
                
            }

            // Récupération du dernier patient ajouté
            $dossier = $bdd->query('SELECT id_patient FROM patients ORDER BY id_patient DESC LIMIT 1');
            if ($id = $dossier->fetch()) {
                $id_patient = $id['id_patient'];
            }

            $bdd->commit();
        } catch (Exception $e) {
            $bdd->rollBack();
            $errors = 3;
            error_log("Erreur lors de l'insertion du patient: " . $e->getMessage());
        }
    }
}
require('../PUBLIC/header.php');
?>
<body>
    <section class="body">
        <?php require('../PUBLIC/navbarmenu.php'); ?>
        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Ajouter un nouveau patient</h2>
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <section class="card">
                        <div class="card-body">
                            <?php if ($errors == 2 && $id_patient): ?>
                                <div class="alert alert-success">
                                    <strong>Succès</strong><br/>  
                                    <li>Enregistrement du patient effectué avec succès. Le dossier est ouvert sous le numéro <strong>PAT-<?= $id_patient ?></strong>.</li>
                                    <li>Vous pouvez l'affecter à un service traitant en cliquant sur <a href="transmission-caisse.php?id_patient=<?= $id_patient ?>">transmettre pour un traitement</a>.</li>
                                </div>
                            <?php elseif ($errors == 3): ?>
                                <div class="alert alert-danger">
                                    <li>Enregistrement non effectué, merci de vérifier les informations saisies.</li>
                                </div>
                            <?php elseif ($existe == 1): ?>
                                <div class="alert alert-warning">
                                    <li>Ce patient est déjà enregistré dans le système et possède le numéro dossier N° <strong>PAT-<?= $patientid ?></strong>.</li>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($error_messages)): ?>
                                <div class="alert alert-danger">
                                    <strong>Erreurs :</strong><br/>
                                    <?php foreach($error_messages as $message): ?>
                                        <li><?php echo htmlspecialchars($message); ?></li>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <form class="form-horizontal" novalidate="novalidate" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?ap=default" enctype="multipart/form-data">
                                <input type="hidden" name="ajouter" value="1">
                                <div class="row form-group pb-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Prénoms & Nom</label>
                                            <input type="text" name="nom_patient" class="form-control" placeholder="" value="<?php echo isset($_POST['nom_patient']) ? htmlspecialchars($_POST['nom_patient']) : ''; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Genre</label>
                                            <select class="form-control populate" name="sexe" required="">
                                                <option value="Masculin" <?php echo (isset($_POST['sexe']) && $_POST['sexe'] == 'Masculin') ? 'selected' : ''; ?>>Masculin</option>
                                                <option value="Feminin" <?php echo (isset($_POST['sexe']) && $_POST['sexe'] == 'Feminin') ? 'selected' : ''; ?>>Feminin</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Date de naissance</label>
                                            <input type="date" class="form-control" name="age" id="formGroupExampleInput" value="<?php echo isset($_POST['age']) ? htmlspecialchars($_POST['age']) : ''; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Profession</label>
                                            <input type="text" class="form-control" name="profession" id="formGroupExampleInput" value="<?php echo isset($_POST['profession']) ? htmlspecialchars($_POST['profession']) : ''; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Contact</label>
                                            <input type="number" class="form-control" maxlength="" name="phone" id="formGroupExampleInput" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                                        </div>
                                    </div>
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
                                                <option value="">-- vous devez choisir une ville --</option>
                                            </select>
                                            <input type="hidden" id="hiddenquartierId" name="quartier_id" value="">
                                        </div>
                                        <a href="../administration/ajoutQuartier.php" target="_blank">Quartier manquant ? ajouter</a>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Autre personne à contacter</label>
                                            <input type="text" class="form-control" name="responsable" id="formGroupExampleInput" value="<?php echo isset($_POST['responsable']) ? htmlspecialchars($_POST['responsable']) : ''; ?>" placeholder="">
                                        </div>
                                    </div>
                                </div>
                                <div class="row form-group pb-3">
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <input type="radio" name="estAssure" value="0" onclick="toggleAssuranceField()" <?php echo (!isset($_POST['estAssure']) || $_POST['estAssure'] == '0') ? 'checked' : ''; ?>> non assuré
                                        </div>
                                    </div>
                                    <div class="col-md-10">
                                        <div class="form-group">
                                            <input type="radio" name="estAssure" value="1" onclick="toggleAssuranceField()" <?php echo (isset($_POST['estAssure']) && $_POST['estAssure'] == '1') ? 'checked' : ''; ?>> assuré
                                        </div>
                                    </div>
                                </div>
                                <div class="row form-group pb-3" id="assuranceField" style="display:none;">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Assureur</label>
                                            <select class="form-control populate" name="entrepriseAssurance" id="entrepriseAssurance">
                                                <option value="">-------- Choisir l'assurance --------</option>
                                                <?php 
                                                    $client = $bdd->prepare('SELECT * FROM clients WHERE status=?');
                                                    $client -> execute([1]);
                                                    while ($clients = $client->fetch(PDO::FETCH_ASSOC))
                                                    {
                                                        $selected = (isset($_POST['entrepriseAssurance']) && $_POST['entrepriseAssurance'] == $clients['id_client']) ? 'selected' : '';
                                                        echo '<option value="'.$clients['id_client'].'" '.$selected.'>'.$clients['nom_client'].'</option>';
                                                    } 
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <footer class="card-footer text-end">
                                    <button class="btn btn-primary" type="submit" name="ajouter">Ajouter le patient</button>
                                </footer>
                            </form>
                        </div>
                    </section>
                </div>
            </section>
        </div>
        <script>
            function toggleAssuranceField() {
                var assuranceField = document.getElementById("assuranceField");
                var estAssure = document.querySelector('input[name="estAssure"]:checked').value;
                assuranceField.style.display = estAssure === "1" ? "block" : "none";
            }
        </script>
        <?php if ($errors == 2 && $id_patient): ?>
            <script>
                window.onload = function() {
                    window.open('imprimer_dossier.php?id_patient=<?= $id_patient ?>', '_blank');
                    window.location.href = 'transmission-caisse.php?id_patient=<?= $id_patient ?>';
                };
            </script>
        <?php endif; ?>
        <?php include('../PUBLIC/footer.php');?>