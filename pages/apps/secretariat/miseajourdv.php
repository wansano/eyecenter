<?php
include('../public/connect.php');
require('../public/fonction.php');
session_start();

include('../public/header.php');

// Initialisation du flag d'état
$errors = 0;

// Récupération sécurisée de l'ID RDV
$rendezvous = isset($_GET['rdv']) ? (int) $_GET['rdv'] : 0;
if ($rendezvous <= 0) {
    $errors = 4; // ID RDV invalide
}

// Traitement de l'impression du dossier
if (isset($_POST['impression']) && $rendezvous > 0) {
    $rdv_print = (int) $_POST['impression'];
    if ($rdv_print === $rendezvous) {
        $id_patient_print = getPatientIdByRdv($bdd, $rdv_print);
        if ($id_patient_print) {
            echo "<script>window.onload=function(){window.open('imprimer_dossier.php?id_patient=" . (int)$id_patient_print . "','_blank');};</script>";
        }
    }
}

// Traitement de la mise à jour du rendez-vous
if (isset($_POST['ajouter']) && $rendezvous > 0) {
    $dateRdv = isset($_POST['date_rdv']) ? trim($_POST['date_rdv']) : '';
    $creneauRaw = isset($_POST['prochain_rdv']) ? trim($_POST['prochain_rdv']) : '';
    $medecin = isset($_POST['medecin']) ? (int) $_POST['medecin'] : 0;
    
    // Extraire l'heure du créneau (qui peut être au format 2025-10-01T08:00:00 ou juste 08:00:00)
    $creneau = '';
    if (!empty($creneauRaw)) {
        if (strpos($creneauRaw, 'T') !== false) {
            // Format ISO avec date : 2025-10-01T08:00:00
            $parts = explode('T', $creneauRaw);
            $creneau = isset($parts[1]) ? $parts[1] : '';
        } elseif (strpos($creneauRaw, ' ') !== false) {
            // Format avec espace : 2025-10-01 08:00:00
            $parts = explode(' ', $creneauRaw);
            $creneau = isset($parts[1]) ? $parts[1] : '';
        } else {
            // Format heure seule : 08:00:00 ou 08:00
            $creneau = $creneauRaw;
        }
    }
    
    // Validation des formats
    $validDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRdv);
    $validTime = preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $creneau);
    
    // Debug : loguer les valeurs pour diagnostic
    error_log("DEBUG miseajourdv.php - dateRdv: '$dateRdv', creneauRaw: '$creneauRaw', creneau: '$creneau', medecin: $medecin, validDate: $validDate, validTime: $validTime");
    
    if ($validDate && $validTime && $medecin > 0) {
        // Ajouter les secondes si manquantes
        if (strlen($creneau) === 5) {
            $creneau .= ':00';
        }
        $nouveauRdv = $dateRdv . ' ' . $creneau;
        
        try {
            // Vérifier si le créneau est libre
            $check = $bdd->prepare('SELECT COUNT(*) FROM dmd_rendez_vous WHERE traitant = ? AND prochain_rdv = ? AND id_rdv != ?');
            $check->execute([$medecin, $nouveauRdv, $rendezvous]);
            
            if ($check->fetchColumn() > 0) {
                $errors = 5; // Créneau déjà occupé
            } else {
                // Mettre à jour le rendez-vous
                $stmt = $bdd->prepare('UPDATE dmd_rendez_vous SET prochain_rdv = ?, traitant = ? WHERE id_rdv = ?');
                $stmt->execute([$nouveauRdv, $medecin, $rendezvous]);
                $errors = 2; // Succès
            }
        } catch (PDOException $e) {
            error_log('Erreur mise à jour RDV: ' . $e->getMessage());
            $errors = 1; // Erreur générale
        }
    } else {
        $errors = 4; // Données invalides
    }
}

?>

<body>
    <section class="body">

        <?php require('../public/navbarmenu.php'); ?>

        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Details de rendez-vous</h2>
                </header>

                <!-- start: page -->
                <?php
                        $id_patient = $rendezvous > 0 ? getPatientIdByRdv($bdd, $rendezvous) : null;
                        if (isset($id_patient)) {
                            $patient = nom_patient($id_patient);
                            $telephone = return_phone($id_patient);
                            $adresse = return_adresse($id_patient);
                            $responsable = return_responsable($id_patient);
                            $profession = return_profession($id_patient);
                            $age = return_age($id_patient);
                            $sexe = return_sexe($id_patient);
                            $assure = return_assure($id_patient);
                            $assurance = return_assurance($id_patient);
                        }

                        $userData = getRdvInfo($bdd, $rendezvous);
                        if ($userData) {
                            $motifrdv = $userData['motif'];
                            $rdv = $userData['prochain_rdv'];
                            $id_service = $userData['id_service'];
                            $id_medecin = $userData['traitant'];
							$status = $userData['status'];
                            $type_patient = $userData['type_patient'];
                        }
                        echo '
                        <div class="col-md-12">
							<section class="card">
								<div class="card-body">';
								    if ($errors == 2) {
                                        echo '
                                            <div class="alert alert-success">
                                                <strong>Succès</strong> <br/>  
                                                <li>Rendez-vous mis à jour avec succès.</li>
                                            </div>';
                                    }
                                    if ($errors == 1) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <strong>Erreur</strong> <br/>  
                                                <li>Une erreur est survenue lors de la mise à jour.</li>
                                            </div>';
                                    }
                                    if ($errors == 4) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <strong>Erreur</strong> <br/>  
                                                <li>Données manquantes ou invalides. Merci de vérifier les champs.</li>
                                            </div>';
                                    }
                                    if ($errors == 5) {
                                        echo '
                                            <div class="alert alert-warning">
                                                <strong>Attention</strong> <br/>  
                                                <li>Ce créneau est déjà occupé. Merci de choisir un autre créneau.</li>
                                            </div>';
                                    }
                                    echo '
									<div class="row form-group pb-3">
										<div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">N° dossier</label>
                                                <input type="text" class="form-control" value="'.$id_patient.'" disabled>
											</div>
										</div>
                                        <div class="col-md-3">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Prénoms & Nom</label>
                                                <input type="text" class="form-control" value="'.$patient.'" disabled>
											</div>
										</div>
										<div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Genre</label>
												<select class="form-control populate" disabled>
                                                    <option value="'.$sexe.'">'.$sexe.'</option>';
                                                        if ($sexe=="Homme") {
                                                            echo '<option value="Feminin">Feminin</option>';
                                                        } else {
                                                            echo '<option value="Masculin">Masculin</option>';
                                                        }
                                                echo '</select>
											</div>
										</div>
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Date de naissance</label>
												<input type="date" class="form-control" id="formGroupExampleInput" value="'.$age.'" disabled>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Profession</label>
												<input type="text" class="form-control" id="formGroupExampleInput" value="'.$profession.'" disabled>
											</div>
										</div>
										<div class="col-md-4">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Adresse</label>
												<input type="text" class="form-control" id="formGroupExampleInput" value="'.(adress($adresse)?: $adresse).'" disabled>
											</div>
										</div>
										<div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Contact</label>
												<input type="number" class="form-control" maxlength="09" id="formGroupExampleInput" value="'.$telephone.'" disabled>
											</div>
										</div>
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Assurance</label>
												<input type="text" class="form-control" id="formGroupExampleInput" value="'.determinerStatutAssurance($assure).'" disabled>
											</div>
										</div>
                                        <div class="col-md-4">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Responsable</label>
												<input type="text" class="form-control" id="formGroupExampleInput" value="'.$responsable.'" disabled>
											</div>
										</div>
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Ancienne date rendez-vous</label>
												<input type="text" class="form-control" id="formGroupExampleInput" value="'.$rdv.'" disabled>
											</div>
										</div>
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Département</label>
												<input type="text" class="form-control" id="formGroupExampleInput" value="'.service($id_service).'" disabled>
											</div>
										</div>
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Motif de rendez-vous</label>
												<input type="text" class="form-control" id="formGroupExampleInput" value="'.model($motifrdv).'" disabled>
											</div>
										</div>
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Rendez-vous avec le medecin</label>
												<input type="text" class="form-control" id="formGroupExampleInput" value="'.traitant($id_medecin).'" disabled>
											</div>
										</div>
                                    </div>

                                    <form action="'.htmlspecialchars($_SERVER['PHP_SELF']).'?rdv='.$rendezvous.'" method="post" id="formMajRdv">
                                        <div class="row form-group pb-3">
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label class="col-form-label">Nouvelle date</label>
                                                    <input type="date" class="form-control" name="date_rdv" id="dateRdvInput" min="'.date('Y-m-d').'" required>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label class="col-form-label">Médecin</label>
                                                    <select name="medecin" class="form-control" id="medecinSelect" required>
                                                        <option value="">-- Sélectionner un médecin --</option>';
                                                        // Charger les médecins du service
                                                        if (isset($id_service) && $id_service > 0) {
                                                            $stmt_med = $bdd->prepare('SELECT id, pseudo FROM users WHERE type = ? AND status = 1 ORDER BY pseudo');
                                                            $stmt_med->execute([$id_service]);
                                                            while ($med = $stmt_med->fetch(PDO::FETCH_ASSOC)) {
                                                                $selected = (isset($id_medecin) && $med['id'] == $id_medecin) ? 'selected' : '';
                                                                echo '<option value="'.$med['id'].'" '.$selected.'>'.$med['pseudo'].'</option>';
                                                            }
                                                        }
                                                echo '</select>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label class="col-form-label">Créneau disponible</label>
                                                    <select name="prochain_rdv" class="form-control" id="creneauSelect" required disabled>
                                                        <option value="">-- Choisir date et médecin --</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
										<footer class="card-footer text-end">
                                            <button class="btn btn-primary" type="submit" name="ajouter">mettre à jour le rendez-vous</button>
                                        </footer>
                                    </form>
                                </div>
							</section>
						</div>
					</div>';
                    ?>
                <!-- end: page -->
            </section>
        </div>
                <script>
        // Fonction pour récupérer le médecin en fonction du service sélectionné
        function resetSelect(selectEl, placeholder) {
            selectEl.innerHTML = '';
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = placeholder || '---';
            selectEl.appendChild(opt);
            // Si tu utilises Select2, déclenche l'update :
            if ($(selectEl).data('select2')) {
                $(selectEl).val('').trigger('change');
            }
        }

        function updateMedecins() {
            const serviceId = <?php echo isset($id_service) ? (int)$id_service : 0; ?>;
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

        function updateCreneaux() {
            const medecinSelect = document.getElementById('medecinSelect');
            const dateInput = document.getElementById('dateRdvInput');
            const creneauSelect = document.getElementById('creneauSelect');
            
            const medecin = medecinSelect ? medecinSelect.value : '';
            const date = dateInput ? dateInput.value : '';

            if (!medecin || !date) {
                if (creneauSelect) {
                    resetSelect(creneauSelect, '------ Choisir médecin et date -----');
                    creneauSelect.disabled = true;
                }
                return;
            }

            // Utiliser la fonction genererCreneaux de custom.js avec exclusion du RDV en cours
            const rdvId = <?php echo (int)$rendezvous; ?>;
            
            if (typeof genererCreneaux === 'function') {
                genererCreneaux(date, medecin, rdvId);
            } else {
                console.error('Fonction genererCreneaux non trouvée. Vérifiez que custom.js est chargé.');
                if (creneauSelect) {
                    resetSelect(creneauSelect, 'Erreur de chargement');
                }
            }
        }

        // Initialisation et événements
        document.addEventListener('DOMContentLoaded', function () {
            const serviceId = <?php echo isset($id_service) ? (int)$id_service : 0; ?>;
            const medecinSelect = document.getElementById('medecinSelect');
            const dateInput = document.getElementById('dateRdvInput');
            
            // Charger les médecins si service disponible
            if (serviceId) {
                updateMedecins();
            }
            
            // Événements pour charger les créneaux
            if (medecinSelect) {
                medecinSelect.addEventListener('change', updateCreneaux);
            }
            if (dateInput) {
                dateInput.addEventListener('change', updateCreneaux);
            }
            
            // Si médecin et date déjà sélectionnés, charger les créneaux
            if (medecinSelect && medecinSelect.value && dateInput && dateInput.value) {
                updateCreneaux();
            }
        });
        </script>

        <?php include('../public/footer.php');?>
