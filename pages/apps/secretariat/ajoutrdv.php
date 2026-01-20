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

// Vérification rendez-vous (AJAX) : recherche par dossier ou téléphone et retourne les RDV à venir.
if (isset($_GET['ajax_check_rdv'])) {
    header('Content-Type: application/json; charset=UTF-8');

    $mode = isset($_GET['mode']) ? trim((string)$_GET['mode']) : '';
    $qRaw = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

    if ($qRaw === '' || ($mode !== 'dossier' && $mode !== 'phone')) {
        echo json_encode(['success' => false, 'message' => 'Paramètres invalides.']);
        exit;
    }

    try {
        $rows = [];
        $hasIdDemande = dbTableHasColumn($bdd, 'dmd_rendez_vous', 'id_demande');

        if ($mode === 'dossier') {
            $idPatient = (int)$qRaw;
            if ($idPatient <= 0) {
                echo json_encode(['success' => false, 'message' => 'Numéro de dossier invalide.']);
                exit;
            }

            $stmt = $bdd->prepare(
                'SELECT id_rdv, id_patient, ' . ($hasIdDemande ? 'id_demande,' : '') . ' id_service, motif, traitant, prochain_rdv, status
                 FROM dmd_rendez_vous
                  WHERE id_patient = ? AND prochain_rdv >= CURDATE()
                 ORDER BY prochain_rdv ASC
                 LIMIT 20'
            );
            $stmt->execute([$idPatient]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($mode === 'phone') {
            // Recherche exacte sur le téléphone (fallback : sans espaces)
            $phone = preg_replace('/\s+/', '', $qRaw);
            if ($phone === '') {
                echo json_encode(['success' => false, 'message' => 'Numéro de téléphone invalide.']);
                exit;
            }

            $stmt = $bdd->prepare('SELECT id_patient FROM patients WHERE phone = ? LIMIT 20');
            $stmt->execute([$phone]);
            $patientIds = array_map(fn($r) => (int)$r['id_patient'], $stmt->fetchAll(PDO::FETCH_ASSOC));

            $demandeIds = [];
            if ($hasIdDemande) {
                $stD = $bdd->prepare('SELECT id_demande FROM dossier_en_attente WHERE phone = ? LIMIT 20');
                $stD->execute([$phone]);
                $demandeIds = array_map(fn($r) => (int)$r['id_demande'], $stD->fetchAll(PDO::FETCH_ASSOC));
            }

            if (empty($patientIds) && empty($demandeIds)) {
                echo json_encode(['success' => true, 'rdvs' => []]);
                exit;
            }

            $whereParts = [];
            $args = [];
            if (!empty($patientIds)) {
                $phP = implode(',', array_fill(0, count($patientIds), '?'));
                $whereParts[] = 'id_patient IN (' . $phP . ')';
                $args = array_merge($args, $patientIds);
            }
            if ($hasIdDemande && !empty($demandeIds)) {
                $phD = implode(',', array_fill(0, count($demandeIds), '?'));
                $whereParts[] = 'id_demande IN (' . $phD . ')';
                $args = array_merge($args, $demandeIds);
            }

            $sql =
                'SELECT id_rdv, id_patient, ' . ($hasIdDemande ? 'id_demande,' : '') . ' id_service, motif, traitant, prochain_rdv, status
                 FROM dmd_rendez_vous
                 WHERE (' . implode(' OR ', $whereParts) . ') AND prochain_rdv >= CURDATE()
                 ORDER BY prochain_rdv ASC
                 LIMIT 50';

            $stmt = $bdd->prepare($sql);
            $stmt->execute($args);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $statusLabels = [
            0 => 'Programmé',
            1 => 'Transmis',
            2 => 'En cours',
        ];

        $out = [];
        foreach ($rows as $r) {
            $status = (int)($r['status'] ?? 0);
            $idPatient = (int)($r['id_patient'] ?? 0);
            $idDemande = (int)($r['id_demande'] ?? 0);
            $dossierLabel = $idPatient > 0 ? ('PAT-' . $idPatient) : ($idDemande > 0 ? ('DEM-' . $idDemande) : 'N/A');
            $out[] = [
                'id_rdv' => (int)$r['id_rdv'],
                'id_patient' => $idPatient,
                'id_demande' => $idDemande,
                'dossier_label' => $dossierLabel,
                'prochain_rdv' => (string)($r['prochain_rdv'] ?? ''),
                'service' => (string)service($r['id_service'] ?? 0),
                'motif' => (string)model($r['motif'] ?? 0),
                'medecin' => (string)traitant($r['traitant'] ?? 0),
                'status' => $status,
                'status_label' => $statusLabels[$status] ?? ('Statut ' . $status),
            ];
        }

        echo json_encode(['success' => true, 'rdvs' => $out]);
        exit;
    } catch (Exception $e) {
        error_log('[CHECK RDV] ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la vérification.']);
        exit;
    }
}

// Ajout quartier (AJAX) : renvoie JSON et stoppe l'exécution pour ne pas afficher toute la page.
if (isset($_POST['ajax_add_quartier'])) {
    header('Content-Type: application/json; charset=UTF-8');

    $villeId = isset($_POST['ville_id']) ? (int)$_POST['ville_id'] : 0;
    $quartier = isset($_POST['quartier']) ? trim((string)$_POST['quartier']) : '';

    if ($villeId <= 0 || $quartier === '') {
        echo json_encode(['success' => false, 'message' => 'Ville et quartier sont requis.']);
        exit;
    }

    try {
        $bdd->beginTransaction();

        // Vérifier si la ville existe
        $stVille = $bdd->prepare('SELECT COUNT(*) FROM adresses_villes WHERE id_ville = ?');
        $stVille->execute([$villeId]);
        if ((int)$stVille->fetchColumn() <= 0) {
            throw new Exception('Ville invalide.');
        }

        // Vérifier doublon (même quartier dans la même ville)
        $stExists = $bdd->prepare('SELECT id_quartier FROM adresses_quartiers WHERE quartier = ? AND id_ville = ? LIMIT 1');
        $stExists->execute([$quartier, $villeId]);
        $existing = $stExists->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $bdd->rollBack();
            echo json_encode([
                'success' => true,
                'already_exists' => true,
                'id' => (int)$existing['id_quartier'],
                'nom' => $quartier,
                'message' => 'Ce quartier existe déjà.'
            ]);
            exit;
        }

        $stIns = $bdd->prepare('INSERT INTO adresses_quartiers (id_ville, quartier) VALUES (?, ?)');
        $stIns->execute([$villeId, $quartier]);
        $newId = (int)$bdd->lastInsertId();

        $bdd->commit();

        echo json_encode([
            'success' => true,
            'already_exists' => false,
            'id' => $newId,
            'nom' => $quartier,
            'message' => 'Quartier ajouté.'
        ]);
        exit;
    } catch (Exception $e) {
        if ($bdd->inTransaction()) {
            $bdd->rollBack();
        }
        error_log('[AJOUT QUARTIER] ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => "Erreur lors de l'ajout du quartier."]); 
        exit;
    }
}

$errors = 0;            // 0 aucun, 2 RDV interne OK, 4 RDV externe OK, 3 erreur
$existe = 0;            // RDV déjà existant
$id_patient = null;     // identifiant patient
$pending_demande_id = null; // identifiant dans dossier_en_attente (RDV externe en attente)
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
        $dateChoisie = isset($_POST['date_rdv']) ? trim((string)$_POST['date_rdv']) : '';
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
                // Vérification: pas de RDV le même jour pour le même traitement
                // (même patient + même service + même motif/type + même date)
                $verifJour = $bdd->prepare('SELECT id_rdv FROM dmd_rendez_vous WHERE id_patient = ? AND id_service = ? AND motif = ? AND DATE(prochain_rdv) = ? LIMIT 1');
                $verifJour->execute([
                    $id_patient,
                    (int) $_POST['service'],
                    (int) $_POST['type'],
                    $dateChoisie
                ]);
                if ($verifJour->fetch()) {
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
                // Patient déjà connu: on peut quand même créer un RDV.
                $id_patient = (int) $data['id_patient'];

                // Vérification: pas de RDV le même jour pour le même traitement
                $verifJour2 = $bdd->prepare('SELECT id_rdv FROM dmd_rendez_vous WHERE id_patient = ? AND id_service = ? AND motif = ? AND DATE(prochain_rdv) = ? LIMIT 1');
                $verifJour2->execute([
                    $id_patient,
                    (int) $_POST['service'],
                    (int) $_POST['type'],
                    $dateChoisie
                ]);

                if ($verifJour2->fetch()) {
                    $existe = 1;
                } else {
                    insererRendezVousExterne($bdd, $id_patient, $_POST['service'], $_POST['type'], $_POST['medecin'], $creneauFinal, $type_patient = 1);
                    // Pas d'ouverture de dossier (patient existant) => message simple
                    $errors = 2;
                }
            } else {
                // Nouveau flux : pour un patient externe inconnu, ne pas créer de dossier tout de suite.
                // On enregistre d'abord dans dossier_en_attente, puis on crée le dossier (patients) au moment de "transmettre".
                if (!dbTableHasColumn($bdd, 'dmd_rendez_vous', 'id_demande')) {
                    throw new Exception("Configuration BD manquante : ajoutez dmd_rendez_vous.id_demande (NULL) pour enregistrer les RDV externes en attente sans créer de patient.");
                }

                $assure = isset($_POST['estAssure']) && $_POST['estAssure'] == 1 ? 1 : 0;
                $responsable = !empty($_POST['responsable']) ? $_POST['responsable'] : null;
                $entrepriseAssurance = $assure ? (int)($_POST['entrepriseAssurance'] ?? 0) : 0;

                $pending_demande_id = findOrCreateDemandeEnAttente($bdd, [
                    'nom_patient' => $_POST['nom_patient'] ?? '',
                    'sexe' => $_POST['sexe'] ?? '',
                    'profession' => $_POST['profession'] ?? '',
                    'age' => $_POST['age'] ?? null,
                    'adresse' => $_POST['adresse'] ?? '',
                    'phone' => $_POST['phone'] ?? null,
                    'responsable' => $responsable,
                    'assure' => $assure,
                    'assurance' => $entrepriseAssurance,
                ]);

                $verifJour3 = $bdd->prepare('SELECT id_rdv FROM dmd_rendez_vous WHERE id_demande = ? AND id_service = ? AND motif = ? AND DATE(prochain_rdv) = ? LIMIT 1');
                $verifJour3->execute([
                    (int)$pending_demande_id,
                    (int)$_POST['service'],
                    (int)$_POST['type'],
                    $dateChoisie
                ]);

                if ($verifJour3->fetch()) {
                    $existe = 1;
                } else {
                    insererRendezVousExterneEnAttente($bdd, (int)$pending_demande_id, $_POST['service'], $_POST['type'], $_POST['medecin'], $creneauFinal, $type_patient = 1);
                    $errors = 2;
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
            $error_messages[] = $e->getMessage();
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
                            <div class="d-flex justify-content-start mb-3">
                                <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#verificationRdvModal">
                                    <i class="fa fa-search"></i> Vérifier un RDV
                                </button>
                            </div>
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
                                    <?php if (!empty($pending_demande_id)): ?>
                                        <li>Demande enregistrée en attente : <strong>DEM-<?= (int)$pending_demande_id ?></strong> (le dossier patient sera créé à la transmission).</li>
                                    <?php endif; ?>
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
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label" for="dossierInput">Saisir le n° dossier du patient</label>
                                                <input type="text" id="dossierInput" name="dossier" class="form-control" placeholder="" value="<?php echo isset($_POST['dossier']) ? htmlspecialchars($_POST['dossier']) : ''; ?>" required>
                                            </div>
                                        </div>
                                        <strong><div id="dossierStatus" class="mt-1 small "></div></strong>
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
                                            <a href="#" onclick="return false;" data-bs-toggle="modal" data-bs-target="#ajoutQuartierModal">Quartier manquant ? ajouter</a>
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
                                                        $client = $bdd->prepare('SELECT * FROM assurances WHERE status = ? ');
                                                        $client -> execute([1]);
                                                        while ($clients = $client->fetch(PDO::FETCH_ASSOC))
                                                        {
                                                            $selected = (isset($_POST['entrepriseAssurance']) && $_POST['entrepriseAssurance'] == $clients['id_assurance']) ? 'selected' : '';
                                                            echo '<option value="'.$clients['id_assurance'].'" '.$selected.'>'.$clients['assurance'].'</option>';
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

                        let rdvInfo = '';
                        if (data.last_rdv && data.last_rdv.date) {
                            const label = data.last_rdv.state_label || 'Inconnu';
                            rdvInfo = ` | Rendez-vous du : ${data.last_rdv.date} ${label}`;
                        }

                        // Si dernier RDV non honoré, on affiche en rouge mais on n'empêche pas la création du nouveau RDV.
                        if (data.last_rdv && data.last_rdv.state === 'non_respecte') {
                            setStatus('Patient '+nom+rdvInfo, 'err');
                        } else {
                            setStatus('Patient '+nom+rdvInfo, 'ok');
                        }
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

        <!-- Modal: Ajout quartier (formulaire intégré) -->
        <div class="modal fade" id="ajoutQuartierModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Ajouter un quartier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="quartierModalAlert" class="alert d-none" role="alert"></div>

                        <form id="ajoutQuartierForm">
                            <input type="hidden" name="ajax_add_quartier" value="1">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="col-form-label">Ville</label>
                                    <select class="form-control" name="ville_id" id="villeQuartierModal" required>
                                        <option value="">--- Choisir la ville ---</option>
                                        <?php
                                        $collModal = $bdd->prepare('SELECT id_ville, nom FROM adresses_villes');
                                        $collModal->execute();
                                        while ($ville = $collModal->fetch(PDO::FETCH_ASSOC)) {
                                            echo '<option value="'.(int)$ville['id_ville'].'">'.htmlspecialchars($ville['nom']).'</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="col-form-label">Nom du quartier</label>
                                    <input type="text" class="form-control" name="quartier" id="quartierNomModal" required>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                        <button type="button" class="btn btn-primary" id="btnSaveQuartier">Ajouter</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        // Chargement des quartiers selon ville (sécurise le cas où updateQuartier n'était pas défini ailleurs)
        function updateQuartier() {
            const villeId = document.getElementById('villeSelect') ? document.getElementById('villeSelect').value : '';
            const quartierSelect = document.getElementById('quartierSelect');
            const hiddenId = document.getElementById('hiddenquartierId');
            if (!quartierSelect) return;

            if (!villeId) {
                quartierSelect.innerHTML = '<option value="">-- vous devez choisir une ville --</option>';
                if (hiddenId) hiddenId.value = '';
                if (window.$ && $(quartierSelect).data('select2')) $(quartierSelect).val('').trigger('change');
                return;
            }

            quartierSelect.innerHTML = '<option value="">Chargement...</option>';
            fetch(`../public/getQuartiers.php?ville=${encodeURIComponent(villeId)}`)
                .then(r => r.json())
                .then(data => {
                    if (!data || !data.success || !Array.isArray(data.quartier)) {
                        quartierSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                        return;
                    }
                    quartierSelect.innerHTML = '<option value="">-- Choisir le quartier --</option>';
                    for (const q of data.quartier) {
                        const opt = document.createElement('option');
                        opt.value = q.id;
                        opt.textContent = q.nom;
                        quartierSelect.appendChild(opt);
                    }

                    // Sélectionner automatiquement un quartier nouvellement créé si demandé
                    if (window.__pendingQuartierSelectId) {
                        quartierSelect.value = String(window.__pendingQuartierSelectId);
                        if (hiddenId) hiddenId.value = String(window.__pendingQuartierSelectId);
                        if (window.$ && $(quartierSelect).data('select2')) $(quartierSelect).val(String(window.__pendingQuartierSelectId)).trigger('change');
                        window.__pendingQuartierSelectId = null;
                    } else {
                        if (hiddenId) hiddenId.value = quartierSelect.value || '';
                        if (window.$ && $(quartierSelect).data('select2')) $(quartierSelect).trigger('change');
                    }
                })
                .catch(() => {
                    quartierSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                });
        }

        document.addEventListener('DOMContentLoaded', function () {
            const quartierSelect = document.getElementById('quartierSelect');
            const hiddenId = document.getElementById('hiddenquartierId');
            if (quartierSelect && hiddenId) {
                quartierSelect.addEventListener('change', function () {
                    hiddenId.value = quartierSelect.value || '';
                });
            }

            // Pré-remplir la ville du modal à l'ouverture
            const modalEl = document.getElementById('ajoutQuartierModal');
            if (modalEl) {
                modalEl.addEventListener('show.bs.modal', function () {
                    const villeSelect = document.getElementById('villeSelect');
                    const villeModal = document.getElementById('villeQuartierModal');
                    const qNom = document.getElementById('quartierNomModal');
                    const alertEl = document.getElementById('quartierModalAlert');
                    if (alertEl) {
                        alertEl.className = 'alert d-none';
                        alertEl.textContent = '';
                    }
                    if (villeModal && villeSelect && villeSelect.value) {
                        villeModal.value = villeSelect.value;
                    }
                    if (qNom) qNom.value = '';
                });
            }

            // Submit AJAX ajout quartier
            const btn = document.getElementById('btnSaveQuartier');
            const form = document.getElementById('ajoutQuartierForm');
            const alertEl = document.getElementById('quartierModalAlert');
            if (btn && form) {
                btn.addEventListener('click', async function () {
                    btn.disabled = true;
                    try {
                        const fd = new FormData(form);
                        const resp = await fetch('<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>', {
                            method: 'POST',
                            body: fd
                        });
                        const data = await resp.json();
                        if (!data || !data.success) {
                            if (alertEl) {
                                alertEl.className = 'alert alert-danger';
                                alertEl.textContent = (data && data.message) ? data.message : "Erreur lors de l'ajout.";
                                alertEl.classList.remove('d-none');
                            }
                            return;
                        }

                        // Mettre à jour le select de ville principal si l'utilisateur a choisi une autre ville dans le modal
                        const villeMain = document.getElementById('villeSelect');
                        const villeModal = document.getElementById('villeQuartierModal');
                        if (villeMain && villeModal && villeModal.value && villeMain.value !== villeModal.value) {
                            villeMain.value = villeModal.value;
                            if (window.$ && $(villeMain).data('select2')) $(villeMain).val(villeModal.value).trigger('change');
                        }

                        window.__pendingQuartierSelectId = data.id;
                        updateQuartier();

                        // Fermer le modal
                        const modalEl = document.getElementById('ajoutQuartierModal');
                        if (modalEl && window.bootstrap) {
                            const instance = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
                            instance.hide();
                        }
                    } catch (e) {
                        if (alertEl) {
                            alertEl.className = 'alert alert-danger';
                            alertEl.textContent = "Erreur lors de l'ajout du quartier.";
                            alertEl.classList.remove('d-none');
                        }
                    } finally {
                        btn.disabled = false;
                    }
                });
            }
        });
        </script>

        <!-- Modal: Vérification rendez-vous -->
        <div class="modal fade" id="verificationRdvModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Vérification rendez-vous</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="col-form-label">Rechercher par</label>
                                <select class="form-control" id="rdvCheckMode">
                                    <option value="dossier">Numéro dossier</option>
                                    <option value="phone">Téléphone</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="col-form-label" id="rdvCheckLabel">Numéro dossier</label>
                                <input type="text" class="form-control" id="rdvCheckQuery" placeholder="Ex: 123">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-primary w-100" id="btnCheckRdv">OK</button>
                            </div>
                        </div>

                        <div id="rdvCheckAlert" class="alert d-none mt-3" role="alert"></div>

                        <div class="table-responsive mt-3">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>RDV</th>
                                        <th>Dossier</th>
                                        <th>Date</th>
                                        <th>Service</th>
                                        <th>Motif</th>
                                        <th>Médecin</th>
                                        <th>Statut</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="rdvCheckTbody">
                                    <tr><td colspan="8">Saisissez un dossier ou téléphone.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modeEl = document.getElementById('rdvCheckMode');
            const labelEl = document.getElementById('rdvCheckLabel');
            const queryEl = document.getElementById('rdvCheckQuery');
            const btnEl = document.getElementById('btnCheckRdv');
            const alertEl = document.getElementById('rdvCheckAlert');
            const tbodyEl = document.getElementById('rdvCheckTbody');

            function setAlert(type, msg) {
                if (!alertEl) return;
                if (!msg) {
                    alertEl.className = 'alert d-none';
                    alertEl.textContent = '';
                    return;
                }
                alertEl.className = 'alert alert-' + type;
                alertEl.textContent = msg;
                alertEl.classList.remove('d-none');
            }

            function setLoading() {
                if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="8">Chargement...</td></tr>';
            }

            function setEmpty() {
                if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="8">Aucun rendez-vous à venir trouvé.</td></tr>';
            }

            function renderRows(rows) {
                if (!tbodyEl) return;
                if (!rows || !rows.length) {
                    setEmpty();
                    return;
                }
                tbodyEl.innerHTML = '';
                for (const r of rows) {
                    const tr = document.createElement('tr');
                    const detailsUrl = 'convocationdetails.php?rdv=' + encodeURIComponent(r.id_rdv);
                    tr.innerHTML =
                        '<td>RDV-' + String(r.id_rdv) + '</td>' +
                        '<td>' + String(r.dossier_label || ('PAT-' + String(r.id_patient))) + '</td>' +
                        '<td>' + (r.prochain_rdv || '') + '</td>' +
                        '<td>' + (r.service || '') + '</td>' +
                        '<td>' + (r.motif || '') + '</td>' +
                        '<td>' + (r.medecin || '') + '</td>' +
                        '<td>' + (r.status_label || r.status) + '</td>' +
                        '<td><a class="btn btn-sm btn-dark" href="' + detailsUrl + '">Détails</a></td>';
                    tbodyEl.appendChild(tr);
                }
            }

            async function checkRdv() {
                const mode = modeEl ? modeEl.value : 'dossier';
                const q = queryEl ? queryEl.value.trim() : '';
                setAlert(null, '');
                if (!q) {
                    setAlert('warning', 'Veuillez saisir une valeur.');
                    return;
                }
                setLoading();
                if (btnEl) btnEl.disabled = true;
                try {
                    const url = '<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>' +
                        '?ajax_check_rdv=1&mode=' + encodeURIComponent(mode) + '&q=' + encodeURIComponent(q);
                    const resp = await fetch(url);
                    const data = await resp.json();
                    if (!data || !data.success) {
                        setAlert('danger', (data && data.message) ? data.message : 'Erreur lors de la vérification.');
                        setEmpty();
                        return;
                    }
                    renderRows(data.rdvs || []);
                } catch (e) {
                    setAlert('danger', 'Erreur lors de la vérification.');
                    setEmpty();
                } finally {
                    if (btnEl) btnEl.disabled = false;
                }
            }

            function syncLabel() {
                if (!modeEl || !labelEl || !queryEl) return;
                if (modeEl.value === 'phone') {
                    labelEl.textContent = 'Téléphone';
                    queryEl.placeholder = 'Ex: 621000000';
                } else {
                    labelEl.textContent = 'Numéro dossier';
                    queryEl.placeholder = 'Ex: 123';
                }
            }

            if (modeEl) modeEl.addEventListener('change', syncLabel);
            if (btnEl) btnEl.addEventListener('click', checkRdv);
            if (queryEl) {
                queryEl.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        checkRdv();
                    }
                });
            }

            syncLabel();
        });
        </script>
        <?php include('../public/footer.php');?>
