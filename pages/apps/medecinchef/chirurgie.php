<?php
include('../PUBLIC/connect.php');
include('../PUBLIC/fonction.php');
include('../PUBLIC/medecin_ieu.php');
session_start();

$existe = 0; 
$errors = 0;
$affectation = null;
$data = null;
$id_patient = null;
$type = null;

try {
    // Vérification des paramètres
    if (!isset($_GET['affectation'])) {
        throw new Exception("ID d'affectation manquant");
    }

    $affectation = (int)$_GET['affectation'];
    
    // Récupération des données en une seule requête
    $stmt = $bdd->prepare('
        SELECT a.*, p.nom_patient, p.responsable
        FROM affectations a
        JOIN patients p ON a.id_patient = p.id_patient
        WHERE a.id_affectation = ?
    ');
    $stmt->execute([$affectation]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        throw new Exception("Affectation non trouvée");
    }

    extract($data);
    $type = (int)$type;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['consulter'])) {
        // Validation des champs obligatoires
        $champsObligatoires = ['diagnostic', 'traitement', 'protocole', 'prescription', 'glycemie', 'date_chirurgie'];
        $champsManquants = array_filter($champsObligatoires, function($champ) {
            return empty($_POST[$champ]);
        });

        if (!empty($champsManquants)) {
            throw new Exception("Champs obligatoires manquants: " . implode(', ', $champsManquants));
        }

        // Vérification si déjà traité
        if (checkTraitementExisteChirurgie($bdd, $affectation)) {
            $existe = 1;
        } else {
            $bdd->beginTransaction();
            
            try {
                // Traitement des fichiers (optionnels)
                $fileData = handleMedicalFiles();
                
                // Préparation des données POST avec fichiers
                $postData = array_merge($_POST, $fileData);
                
                // Vérification que l'utilisateur est authentifié
                if (empty($_SESSION['auth'])) {
                    throw new Exception("Utilisateur non authentifié. Reconnexion requise.");
                }
                
                // Insertion des enregistrements
                insertChirurgie($bdd, $id_patient, $type, $affectation, $postData);
                insertGlycemie($bdd, $affectation, $postData);
                updateAffectationStatus($bdd, $affectation);

                $bdd->commit();
                $errors = 4;
            } catch (Exception $e) {
                $bdd->rollBack();
                error_log("Erreur lors du traitement de la chirurgie: " . $e->getMessage());
                $errors = $e->getMessage();
            }
        }         
    }  
} catch (Exception $e) {
    $errors = $e->getMessage();
}

function handleMedicalFiles() {
    $result = ['biometrie' => null, 'echographie' => null];
    
    // Chemin exact demandé : pages/apps/documents/biometrieEchographie/
    $uploadDir = __DIR__ . '/../documents/biometrieEchographie/';
    
    // Créer le dossier s'il n'existe pas
    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0777, true)) {
            error_log("Impossible de créer le dossier: $uploadDir");
            return $result;
        }
    }
    
    // Vérifier les permissions d'écriture
    if (!is_writable($uploadDir)) {
        error_log("Pas de permissions d'écriture sur: $uploadDir");
        return $result;
    }

    $fileFields = ['biometrie', 'echographie'];
    
    foreach ($fileFields as $field) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] !== UPLOAD_ERR_NO_FILE) {
            // Traiter les erreurs d'upload - ne pas lever d'exception, les fichiers sont optionnels
            if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
                error_log("Erreur upload optionnel $field: " . $_FILES[$field]['error']);
                continue;
            }
            
            $file = $_FILES[$field];
            
            // Vérifier que le fichier temporaire existe
            if (!file_exists($file['tmp_name'])) {
                error_log("Fichier temporaire $field introuvable");
                continue;
            }
            
            // Valider le type MIME
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if ($mimeType !== 'application/pdf') {
                error_log("Le fichier $field n'est pas un PDF valide (type: $mimeType)");
                continue;
            }
            
            // Générer un nom sécurisé
            $filename = uniqid($field . '_', true) . '.pdf';
            $filepath = $uploadDir . $filename;
            
            // Déplacer le fichier
            if (@move_uploaded_file($file['tmp_name'], $filepath) && file_exists($filepath)) {
                $result[$field] = $filename;
                error_log("Fichier $field sauvegardé: $filepath");
            } else {
                error_log("Impossible de sauvegarder le fichier optionnel $field vers $filepath");
            }
        }
    }
    
    return $result;
}

include('../PUBLIC/header.php');   
?>

<body>
    <section class="body">

        <?php include('../PUBLIC/navbarmenu.php'); ?>

        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Chirurgie d'un patient</h2>
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <section class="card">
                        <div class="card-body">
                            <?php
                                if ($errors === 4) {
                                    echo '
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <strong>succès</strong> <br/>
                                            Les informations relatives à la chirurgie de '.htmlspecialchars(nom_patient($id_patient)).' ont été enregistrées. 
                                            <a href="imprimer_chirurgie.php?affectation='.$affectation.'" target="_blank" class="alert-link">imprimer les données</a>
                                        </div>
                                    ';
                                }
                                if ($existe === 1) {
                                    echo '
                                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                            <strong>Attention</strong> <br/>
                                            Cette chirurgie a déjà été approuvée ou traitée. Il est également possible <a href="imprimer_chirurgie.php?affectation='.$affectation.'" target="_blank" class="alert-link">d\'imprimer les données</a>
                                        </div>
                                    ';
                                }
                                if (is_string($errors) && $errors !== '0' && $errors !== '1' && $errors !== '4') {
                                    echo '
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <strong>Erreur</strong> <br/>
                                            '.htmlspecialchars($errors).'
                                        </div>
                                    ';
                                }
                            ?>
                            <?php include __DIR__ . '/../public/acquitehistorique.php'; ?>
                            <!-- Formulaire de consultation -->
                            <form class="form-horizontal" id="chirurgieForm" method="POST"
                                action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?affectation=<?php echo htmlspecialchars($affectation); ?>" enctype="multipart/form-data">
                                <input type="hidden" name="consulter" value="1">
                                
                                <!-- Section Acuité Visuelle et Glycémie -->
                                <fieldset class="mb-4">
                                    <div class="row form-group pb-3">
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label">Resultat Glycémie <span class="text-danger">*</span></label>
                                                <input type="text" name="glycemie" class="form-control" placeholder="Ex: 100 mg/dL" required value="<?php echo htmlspecialchars(getFormValue('glycemie')); ?>">
                                            </div>
                                        </div>                              
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="col-form-label">Kerato Biométrie <span class="text-muted">(Optionnel)</span></label>
                                                <input type="file" name="biometrie" id="biometrie" class="form-control" accept=".pdf">
                                                <small class="form-text text-muted">Format: PDF uniquement</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="col-form-label">Échographie <span class="text-muted">(Optionnel)</span></label>
                                                <input type="file" name="echographie" id="echographie" class="form-control" accept=".pdf">
                                                <small class="form-text text-muted">Format: PDF uniquement</small>
                                            </div>
                                        </div>
                                    </div>
                                </fieldset>

                                <!-- Section Diagnostique et Traitement -->
                                <fieldset class="mb-4">
                                    <div class="row form-group pb-3">                                    
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="col-form-label">Diagnostic <span class="text-danger">*</span></label>
                                                <textarea name="diagnostic" class="form-control" rows="4" placeholder="Décrivez le diagnostic" required><?php echo htmlspecialchars(getFormValue('diagnostic')); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="col-form-label">Traitement <span class="text-danger">*</span></label>
                                                <textarea name="traitement" class="form-control" rows="4" placeholder="Décrivez le traitement proposé" required><?php echo htmlspecialchars(getFormValue('traitement')); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row form-group pb-3">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="col-form-label">Protocole <span class="text-danger">*</span></label>
                                                <textarea name="protocole" class="form-control" rows="4" placeholder="Décrivez le protocole suivi" required><?php echo htmlspecialchars(getFormValue('protocole')); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="col-form-label">Prescription <span class="text-danger">*</span></label>
                                                <textarea name="prescription" class="form-control" rows="4" placeholder="Décrivez la prescription" required><?php echo htmlspecialchars(getFormValue('prescription')); ?></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row form-group pb-3">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="col-form-label">Date et heure chirurgie prévue <span class="text-danger">*</span></label>
                                                <input type="datetime-local" name="date_chirurgie" id="date_chirurgie" class="form-control" placeholder="Décrivez la date prévue pour la chirurgie" required value="<?php echo htmlspecialchars(getFormValue('date_chirurgie')); ?>">
                                            </div>
                                        </div>
                                    </div>

                                </fieldset>
                            <footer class="card-footer text-end">
                                <button class="btn btn-primary" type="submit" name="ajouter">Valider la chirurgie</button>
                            </footer>
                        </form>
                        <!-- Fin du formulaire de consultation -->
                    </section>
                </div>
            </div>
        <!-- end: page -->
    </section>
    </div>
        <?php if ($errors == 4 && $affectation): ?>
            <script>
                window.onload = function() {
                    window.open('imprimer_chirurgie.php?affectation=<?= $affectation ?>', '_blank');
                };
            </script>
        <?php endif; ?>
        
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Obtenir l'input datetime-local
                const dateInput = document.getElementById('date_chirurgie');
                
                if (dateInput) {
                    // Définir la date/heure minimale à maintenant
                    const now = new Date();
                    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                    const minDateTime = now.toISOString().slice(0, 16);
                    
                    dateInput.min = minDateTime;
                    
                    // Validation au changement
                    dateInput.addEventListener('change', function() {
                        const selectedDate = new Date(this.value);
                        const currentDate = new Date();
                        
                        if (selectedDate < currentDate) {
                            alert('La date et l\'heure ne peuvent pas être dans le passé');
                            this.value = '';
                        }
                    });
                }
            });
        </script>
    <?php include('../PUBLIC/footer.php');?>
