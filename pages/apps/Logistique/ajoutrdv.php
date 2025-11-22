<?php
include('../public/connect.php');
require_once('../PUBLIC/fonction.php');
// PHPMailer (envoi SMTP)
require_once('../public/PHPMailer/vendor/phpmailer/phpmailer/src/PHPMailer.php');
require_once('../public/PHPMailer/vendor/phpmailer/phpmailer/src/SMTP.php');
require_once('../public/PHPMailer/vendor/phpmailer/phpmailer/src/Exception.php');
// Config SMTP centralisée
$smtpConfig = require('../public/smtp_config.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
session_start();

$errors = 0;            // 0 aucun, 2 RDV interne OK, 4 RDV externe OK, 3 erreur
$existe = 0;            // RDV déjà existant
$id_patient = null;     // identifiant patient
$error_messages = array();
$emailSent = false;     // notification envoyée ?
$emailError = '';       // log interne en cas d'échec

$bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (isset($_POST['ajouter'])) {
    // Vérification des champs requis
    $isInterne = isset($_POST['estInterne']) && $_POST['estInterne'] == '0';
    if (!$isInterne && empty($_POST['nom_patient'])) {
        $error_messages[] = "Le nom est requis";
    }
    if (!$isInterne && empty($_POST['age'])) {
        $error_messages[] = "La date de naissance est requise";
    }
    if (!$isInterne && empty($_POST['phone'])) {
        $error_messages[] = "Le numéro de téléphone est requis";
    }
    if (!$isInterne && empty($_POST['profession'])) {
        $error_messages[] = "La profession est requise";
    }
    if (!$isInterne && empty($_POST['sexe'])) {
        $error_messages[] = "Le genre est requis";
    }
    if (!$isInterne && empty($_POST['adresse'])) {
        $error_messages[] = "L'adresse est requise";
    }
    if (empty($_POST['prochain_rdv'])) {
        $error_messages[] = "La date du prochain rendez-vous est requise";
    }
    if (empty($_POST['service'])) {
        $error_messages[] = "Le service est requis";
    }
    if (empty($_POST['medecin'])) {
        $error_messages[] = "Le médecin est requis";
    }

    if (empty($error_messages)) {
        // Traiter le créneau reçu (peut être au format ISO complet)
        $creneauRaw = isset($_POST['prochain_rdv']) ? trim($_POST['prochain_rdv']) : '';
        $creneauFinal = '';
        
        if (!empty($creneauRaw)) {
            if (strpos($creneauRaw, 'T') !== false) {
                // Format ISO avec date : 2025-10-01T08:00:00 -> convertir en datetime complet
                $creneauFinal = str_replace('T', ' ', $creneauRaw);
            } elseif (strpos($creneauRaw, ' ') !== false) {
                // Format avec espace : 2025-10-01 08:00:00 -> garder tel quel
                $creneauFinal = $creneauRaw;
            } else {
                // Format heure seule : 08:00:00 -> combiner avec la date du RDV
                $dateRdv = isset($_POST['date_rdv']) ? $_POST['date_rdv'] : '';
                if (!empty($dateRdv)) {
                    $creneauFinal = $dateRdv . ' ' . $creneauRaw;
                } else {
                    $creneauFinal = $creneauRaw; // Fallback
                }
            }
        }
        
        try {
            $bdd->beginTransaction();
            $assure = isset($_POST['estAssure']) && $_POST['estAssure'] == 1 ? 1 : 0;
            $responsable = !empty($_POST['responsable']) ? $_POST['responsable'] : null;
            $entrepriseAssurance = isset($_POST['entrepriseAssurance']) ? $_POST['entrepriseAssurance'] : 0;
            if ($isInterne) {
                // Cas rendez-vous interne : on récupère l'id_patient fourni
                $id_patient = isset($_POST['dossier']) ? $_POST['dossier'] : null;
                if (!$id_patient) {
                    throw new Exception("Le numéro de dossier patient est requis pour un rendez-vous interne.");
                }
                // Vérification de l'existence du dossier patient
                $verif_patient = $bdd->prepare('SELECT id_patient FROM patients WHERE id_patient = ?');
                $verif_patient->execute([$id_patient]);
                if (!$verif_patient->fetch()) {
                    throw new Exception("Le numéro de dossier patient n'existe pas dans la base de données.");
                }
                // Vérification de l'existence du rendez-vous
                $verif = $bdd->prepare('SELECT id_rdv FROM dmd_rendez_vous WHERE id_patient = ? AND id_service = ? AND motif = ? AND traitant = ? AND prochain_rdv = ?');
                $verif->execute([
                    $id_patient,
                    $_POST['service'],
                    $_POST['type'],
                    $_POST['medecin'],
                    $creneauFinal
                ]);
                if ($verif->fetch()) {
                    $existe = 1;
                } else {
                    insererRendezVousInterne($bdd, $id_patient, $_POST['service'], $_POST['type'], $_POST['medecin'], $creneauFinal);
                    $errors = 2;
                }
            } else {
                // Cas rendez-vous externe : on insère d'abord dans patients
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
                // Récupérer le dernier id_patient
                $id_patient = $bdd->lastInsertId();
                // Vérification de l'existence du rendez-vous pour ce patient
                $verif2 = $bdd->prepare('SELECT id_rdv FROM dmd_rendez_vous WHERE id_patient = ? AND id_service = ? AND motif = ? AND traitant = ? AND prochain_rdv = ?');
                $verif2->execute([
                    $id_patient,
                    $_POST['service'],
                    $_POST['type'],
                    $_POST['medecin'],
                    $creneauFinal
                ]);
                if ($verif2->fetch()) {
                    $existe = 1;
                } else {
                    insererRendezVousExterne($bdd, $id_patient, $_POST['service'], $_POST['type'], $_POST['medecin'], $creneauFinal, $type_patient = 1);
                    $errors = 4;
                }
            }
        }
            $bdd->commit();
            // Notification email au médecin via PHPMailer (SMTP)
            if (($errors === 2 || $errors === 4) && $existe === 0) {
                try {
                    $stmtMed = $bdd->prepare('SELECT id, pseudo, email FROM users WHERE id = ? LIMIT 1');
                    $stmtMed->execute([$_POST['medecin']]);
                    $medInfo = $stmtMed->fetch(PDO::FETCH_ASSOC);
                    if ($medInfo && !empty($medInfo['email'])) {
                        $pInfo = $id_patient ? getPatientInfo($id_patient) : [
                            'nom_patient' => $_POST['nom_patient'] ?? 'Patient',
                            'phone'       => $_POST['phone'] ?? '',
                            'age'         => $_POST['age'] ?? '',
                            'sexe'        => $_POST['sexe'] ?? ''
                        ];
                        $serviceNom = service($_POST['service']);
                        $motifType  = $_POST['type'];
                        $dateHeure  = $creneauFinal;
                        $clinique   = getSingleRow($bdd, 'profil_entreprise');

                        $mail = new PHPMailer(true);
                        try {
                            // Encodage UTF-8 pour éviter les caractères bizarres
                            $mail->CharSet  = 'UTF-8';
                            $mail->Encoding = 'base64';
                            // CONFIG SMTP via fichier smtp_config.php
                            $mail->isSMTP();
                            $mail->Host       = $smtpConfig['host'];
                            $mail->SMTPAuth   = $smtpConfig['auth'];
                            $mail->Username   = $smtpConfig['username'];
                            $mail->Password   = $smtpConfig['password'];
                            $mail->SMTPSecure = $smtpConfig['secure'];
                            $mail->Port       = $smtpConfig['port'];

                            $fromEmail  = $smtpConfig['from_email'] ?? ($clinique['email'] ?? 'no-reply@example.com');
                            $fromName   = $smtpConfig['from_name']  ?? (strtoupper($clinique['denomination'] ?? 'CLINIQUE'));

                            $mail->setFrom($fromEmail, $fromName);
                            $mail->addAddress($medInfo['email'], $medInfo['pseudo']);

                            $mail->Subject = 'Nouveau rendez-vous - ' . $serviceNom;
                            $bodyHtml = "Bonjour Dr " . ($medInfo['pseudo'] ?? '') . "<br><br>" .
                                       "Un nouveau rendez-vous a été programmé pour vous :<br>" .
                                       "<b>Service</b> : $serviceNom<br>" .
                                       "<b>Motif</b> : " . model($motifType) . "<br>" .
                                       "<b>Date & créneau</b> : $dateHeure<br><br>" .
                                       "<b>Patient</b> : " . ($pInfo['nom_patient'] ?? 'N/A') . "<br>" .
                                       "<b>Contact</b> : " . ($pInfo['phone'] ?? '') . "<br><br>" .
                                       "Coordialement.<br><br>" .
                                       $fromName;

                            $mail->isHTML(true);
                            $mail->Body    = $bodyHtml;
                            $mail->AltBody = strip_tags(str_replace('<br>', "\n", $bodyHtml));

                            $mail->send();
                            $emailSent = true;
                        } catch (Exception $e) {
                            $emailError = 'PHPMailer: ' . $mail->ErrorInfo;
                        }
                    } else {
                        $emailError = 'Email médecin introuvable ou vide';
                    }
                } catch (Throwable $te) {
                    $emailError = 'Exception notification: ' . $te->getMessage();
                }
                if ($emailError) { error_log('[RDV NOTIF] ' . $emailError); }
            }
        } catch (Exception $e) {
            $bdd->rollBack();
            $errors = 3;
            error_log("Erreur lors de l'insertion du patient/rendez-vous: " . $e->getMessage());
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
                    <h2>Prise de rendez-vous patient</h2>
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <section class="card">
                        <div class="card-body">
                            <?php if ($errors == 4 && $id_patient): ?>
                                <div class="alert alert-success">
                                    <strong>Succès</strong><br/>  
                                    <li>Enregistrement du patient effectué avec succès. Le dossier est ouvert sous le numéro <strong><?= $id_patient ?></strong>.</li>
                                    <li>Le rendez-vous a été ajouté avec succès.</li>
                                    <?php if ($emailSent): ?>
                                        <li>Notification email envoyée au médecin.</li>
                                    <?php else: ?>
                                        <li>Notification email non envoyée (adresse manquante ou erreur).</li>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($errors == 2): ?>
                                <div class="alert alert-success">
                                    <strong>Succès</strong><br/>
                                    <li>Le rendez-vous a été ajouté avec succès.</li>
                                    <?php if ($emailSent): ?>
                                        <li>Notification email envoyée au médecin.</li>
                                    <?php else: ?>
                                        <li>Notification email non envoyée (adresse manquante ou erreur).</li>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($errors == 3): ?>
                                <div class="alert alert-danger">
                                    <li>Enregistrement non effectué, merci de vérifier les informations saisies.</li>
                                    <?php if ($isInterne): ?>
                                        <li>Vérifiez le numéro de dossier patient et les champs du rendez-vous.</li>
                                    <?php else: ?>
                                        <li>Vérifiez les informations du patient et les champs du rendez-vous.</li>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($existe == 1): ?>
                                <div class="alert alert-warning">
                                    <li>Un rendez-vous identique existe déjà pour ce patient à cette date.</li>
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
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <input type="radio" name="estInterne" value="0" onclick="toggleTypeRDV()" <?php echo (!isset($_POST['estInterne']) || $_POST['estInterne'] == '0') ? 'checked' : ''; ?>> Rendez-vous interne
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <input type="radio" name="estInterne" value="1" onclick="toggleTypeRDV()" <?php echo (isset($_POST['estInterne']) && $_POST['estInterne'] == '1') ? 'checked' : ''; ?>> Rendez-vous externe
                                        </div>
                                    </div>
                                </div>

                                <div id="typeRDVFieldInterne" style="display: <?php echo (isset($_POST['estInterne']) && $_POST['estInterne'] == '0') ? 'block' : 'none'; ?>;">
                                    <div class="row form-group pb-3">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="col-form-label" for="dossierInput">Saisir le n° dossier du patient</label>
                                                <input type="text" id="dossierInput" name="dossier" class="form-control" placeholder="" value="<?php echo isset($_POST['dossier']) ? htmlspecialchars($_POST['dossier']) : ''; ?>" required>
                                                <div id="dossierStatus" class="mt-1 small"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div id="typeRDVFieldExterne" style="display: <?php echo (isset($_POST['estInterne']) && $_POST['estInterne'] == '1') ? 'block' : 'none'; ?>;">
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
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <input type="radio" name="estAssure" value="1" onclick="toggleAssuranceField()" <?php echo (isset($_POST['estAssure']) && $_POST['estAssure'] == '1') ? 'checked' : ''; ?>> assuré
                                            </div>
                                        </div>
                                        <div class="col-md-3 pb-1" id="assuranceField" style="display:none;">
                                            <div class="form-group">
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
                                </div>
                                <div class="row form-group pb-3">
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Département concerné</label>
                                            <select name="service" class="form-control populate" id="serviceSelect" onchange="updateMotifs(); updateMedecins();">
                                                <option value=""> ------ choisir ----- </option>';
                                                    <?php $coll = $bdd->prepare('SELECT * FROM organigramme WHERE id_organigramme IN (?, ?, ?)');
                                                    $coll -> execute([1, 2, 3]);
                                                    while ($services = $coll->fetch(PDO::FETCH_ASSOC))
                                                    {
                                                        echo '<option value="'.$services['id_organigramme'].'">'.$services['celulle'].'</option>';
                                                    } ?>
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Motif</label>
                                            <select class="form-control populate" id="motifSelect" name="type" onchange="fetchMotifPrice()" data-plugin-selectTwo data-plugin-options='{ "minimumInputLength": 0 }' required>
                                                <option value=""> ------ Choisir un departement ----- </option>
                                            </select>
                                            <input type="hidden" id="hiddenMotifId" name="motif_id" value="">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="medecinSelect">Médecin disponible</label>
                                            <select class="form-control populate" id="medecinSelect" name="medecin" data-plugin-selectTwo data-plugin-options='{ "minimumInputLength": 0 }' required>
                                            <option value=""> ------ Choisir un departement ----- </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Date prochain rendez-vous</label>
                                            <input type="date" class="form-control mb-2" id="dateRdvInput" name="date_rdv" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Créneau disponible</label>
                                            <select name="prochain_rdv" class="form-control" id="creneauSelect" required>
                                                <option value="">-- Choisir un créneau disponible --</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <footer class="card-footer text-end">
                                    <button class="btn btn-primary" type="submit" name="ajouter">ajouter le rendez-vous</button>
                                </footer>
                            </form>
                        </div>
                    </section>
                </div>
            </section>
        </div>
        <script>
        // Optimisation : robustesse, DRY, initialisation dynamique
        function toggleAssuranceField() {
            var assuranceField = document.getElementById("assuranceField");
            var estAssureRadio = document.querySelector('input[name="estAssure"]:checked');
            var estAssure = estAssureRadio ? estAssureRadio.value : "0";
            assuranceField.style.display = estAssure === "1" ? "block" : "none";
        }

        function toggleTypeRDV() {
            var typeRDVFieldInterne = document.getElementById("typeRDVFieldInterne");
            var typeRDVFieldExterne = document.getElementById("typeRDVFieldExterne");
            var interneRadio = document.querySelector('input[name="estInterne"]:checked');
            var interne = interneRadio ? interneRadio.value : "0";
            typeRDVFieldInterne.style.display = interne === "0" ? "block" : "none";
            typeRDVFieldExterne.style.display = interne === "1" ? "block" : "none";
        }

        // Initialisation dynamique au chargement
        document.addEventListener('DOMContentLoaded', function() {
            toggleAssuranceField();
            toggleTypeRDV();
        });

    
// Fonction pour récupérer le médecin en fonction du service sélectionné
    function resetSelect(selectEl, placeholder) {
    selectEl.innerHTML = '';
    const opt = document.createElement('option');
    opt.value = '';
    opt.textContent = placeholder || '---';
    selectEl.appendChild(opt);
    // Si tu utilises Select2, déclenche l’update :
    if ($(selectEl).data('select2')) {
        $(selectEl).val('').trigger('change');
        }
    }

function updateMedecins() {
    const serviceId = document.getElementById('serviceSelect').value;
    const medecinSelect = document.getElementById('medecinSelect');

    if (!serviceId) {
        resetSelect(medecinSelect, '------ Choisir un département -----');
        return;
    }

    resetSelect(medecinSelect, 'Chargement...');

    fetch(`../public/getMedecin.php?service=${encodeURIComponent(serviceId)}`)
        .then(resp => {
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            return resp.json();
        })
        .then(data => {
            resetSelect(medecinSelect, data.medecins && data.medecins.length ? '------ Choisir le médecin -----' : 'Aucun médecin pour ce service');
            if (data.success && Array.isArray(data.medecins)) {
                for (const m of data.medecins) {
                    const opt = document.createElement('option');
                    opt.value = m.id;              // valeur envoyée au serveur
                    opt.textContent = m.pseudo;    // libellé affiché
                    medecinSelect.appendChild(opt);
                }
                if ($(medecinSelect).data('select2')) {
                    $(medecinSelect).trigger('change');
                }
            }
        })
        .catch(err => {
            console.error('Erreur chargement médecins:', err);
            resetSelect(medecinSelect, 'Erreur de chargement');
        });
}

// Si besoin d’initialiser au chargement (en cas de postback avec service déjà choisi)
document.addEventListener('DOMContentLoaded', function () {
    const serviceId = document.getElementById('serviceSelect').value;
    if (serviceId) updateMedecins();
});
        </script>
        <script>
        // Vérification automatique de l'existence du numéro de dossier (RDV interne)
        (function(){
            const input = document.getElementById('dossierInput');
            const statusEl = document.getElementById('dossierStatus');
            const submitBtn = document.querySelector('button[type="submit"][name="ajouter"]');
            const interneRadios = document.querySelectorAll('input[name="estInterne"]');

            if (!input || !statusEl || !submitBtn) return;

            let debounceTimer = null;

            function isInterneSelected(){
                const r = document.querySelector('input[name="estInterne"]:checked');
                return !r || r.value === '0'; // 0 = RDV interne
            }

            function setStatus(msg, type){
                statusEl.textContent = msg || '';
                statusEl.classList.remove('text-danger','text-success');
                if (type === 'ok') statusEl.classList.add('text-success');
                if (type === 'err') statusEl.classList.add('text-danger');
            }

            function setSubmitEnabled(enabled){
                submitBtn.disabled = !enabled;
            }

            async function checkDossier(value){
                if (!value){
                    setStatus('', null);
                    setSubmitEnabled(true);
                    return;
                }
                try {
                    setStatus('Vérification du dossier…', null);
                    const resp = await fetch(`../public/checkPatient.php?dossier=${encodeURIComponent(value)}`);
                    if (!resp.ok){
                        throw new Error('HTTP '+resp.status);
                    }
                    const data = await resp.json();
                    if (data && data.success){
                        const nom = (data.patient && data.patient.nom) ? `: ${data.patient.nom}` : '';
                        setStatus('Dossier trouvé'+nom, 'ok');
                        setSubmitEnabled(true);
                    } else {
                        setStatus('Dossier introuvable', 'err');
                        // Bloquer l’envoi uniquement si RDV interne
                        setSubmitEnabled(!isInterneSelected() ? true : false);
                    }
                } catch(e){
                    console.error('Erreur vérification dossier:', e);
                    setStatus('Erreur de vérification', 'err');
                    setSubmitEnabled(!isInterneSelected() ? true : false);
                }
            }

            function debouncedCheck(){
                if (!isInterneSelected()){
                    // Si RDV externe, ne pas bloquer
                    setStatus('', null);
                    setSubmitEnabled(true);
                    return;
                }
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => checkDossier(input.value.trim()), 350);
            }

            input.addEventListener('input', debouncedCheck);
            input.addEventListener('blur', debouncedCheck);
            interneRadios.forEach(r => r.addEventListener('change', debouncedCheck));

            // Initial check si valeur déjà présente
            if (input.value) debouncedCheck();
        })();
        </script>
        <?php include('../public/footer.php');?>
