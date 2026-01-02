<?php
include('../PUBLIC/connect.php');
require('../PUBLIC/fonction.php');
session_start();
$existe = 0;

function tc_buildAffectationHtml(PDO $bdd, $id_patient, array $state = []) {
    $id_patient = (string)$id_patient;

    $errors = (int)($state['errors'] ?? 0);
    $existe = (int)($state['existe'] ?? 0);
    $rdvBloquant = (int)($state['rdvBloquant'] ?? 0);
    $rdvDuJour = $state['rdvDuJour'] ?? null;
    $heuresRestantes = (int)($state['heuresRestantes'] ?? 0);
    $minutesRestantes = (int)($state['minutesRestantes'] ?? 0);
    $selectedType = $state['selectedType'] ?? null;

    $patient = nom_patient($id_patient);
    $telephone = return_phone($id_patient);
    $adresse = return_adresse($id_patient);
    $responsable = return_responsable($id_patient);
    $profession = return_profession($id_patient);
    $age = return_age($id_patient);
    $sexe = return_sexe($id_patient);
    $assure = return_assure($id_patient);

    ob_start();
    ?>
    <div class="col-md-12">
        <section class="card">
            <div class="card-body">
                <?php if ($errors === 2): ?>
                    <div class="alert alert-success">
                        <strong>Succès</strong><br>
                        <li>Dossier patient transmis à la caisse pour paiement. Merci de rediriger le patient vers la caisse.</li>
                    </div>
                <?php endif; ?>

                <?php if ($errors === 4): ?>
                    <div class="alert alert-danger">
                        <strong>Erreur</strong><br>
                        <li>Patient non transmis, merci de vérifier les informations saisies.</li>
                    </div>
                <?php endif; ?>

                <?php if ($rdvBloquant === 1 && !empty($rdvDuJour)): ?>
                    <div class="alert alert-warning">
                        <strong>Attention</strong><br>
                        <li>Ce patient a un rendez-vous prévu aujourd'hui pour <strong><?php echo htmlspecialchars(model($rdvDuJour['motif']), ENT_QUOTES, 'UTF-8'); ?></strong> (<strong><?php echo htmlspecialchars($rdvDuJour['prochain_rdv'], ENT_QUOTES, 'UTF-8'); ?></strong>).</li>
                        <li>Veuillez faire l'affectation à partir du calendrier des rendez-vous.</li>
                        <li><a href="convocationdetails.php?rdv=<?php echo urlencode($rdvDuJour['id_rdv']); ?>">Ouvrir le rendez-vous</a> ou <a href="convocation.php">ouvrir le calendrier</a>.</li>
                    </div>
                <?php endif; ?>

                <?php if ($existe === 2): ?>
                    <div class="alert alert-warning">
                        <strong>Attention</strong><br>
                        <li>Ce patient a déjà une affectation active pour le traitement de <strong><?php echo htmlspecialchars(model($selectedType), ENT_QUOTES, 'UTF-8'); ?></strong>.</li>
                        <li>Vous pourrez le transmettre à nouveau dans <strong><?php echo (int)$heuresRestantes; ?> heure(s) et <?php echo (int)$minutesRestantes; ?> minute(s)</strong>.</li>
                        <li>L'ancienne affectation ne sera pas supprimée, une nouvelle sera créée.</li>
                    </div>
                <?php endif; ?>

                <div class="table-responsive mb-3">
                    <table class="table table-bordered mb-0">
                        <tbody>
                            <tr><th style="width: 35%">Dossier</th><td><?php echo htmlspecialchars('PAT-' . $id_patient, ENT_QUOTES, 'UTF-8'); ?></td></tr>
                            <tr><th>Nom</th><td><?php echo htmlspecialchars($patient, ENT_QUOTES, 'UTF-8'); ?></td></tr>
                            <tr><th>Genre</th><td><?php echo htmlspecialchars($sexe, ENT_QUOTES, 'UTF-8'); ?></td></tr>
                            <tr><th>Date de naissance</th><td><?php echo htmlspecialchars($age, ENT_QUOTES, 'UTF-8'); ?></td></tr>
                            <tr><th>Profession</th><td><?php echo htmlspecialchars($profession, ENT_QUOTES, 'UTF-8'); ?></td></tr>
                            <tr><th>Téléphone</th><td><?php echo htmlspecialchars($telephone, ENT_QUOTES, 'UTF-8'); ?></td></tr>
                            <tr><th>Adresse</th><td><?php echo htmlspecialchars((adress($adresse) ?: $adresse), ENT_QUOTES, 'UTF-8'); ?></td></tr>
                            <tr><th>Responsable</th><td><?php echo htmlspecialchars($responsable, ENT_QUOTES, 'UTF-8'); ?></td></tr>
                            <tr><th>Type de patient</th><td><?php echo htmlspecialchars(determinerStatutAssurance($assure), ENT_QUOTES, 'UTF-8'); ?></td></tr>
                        </tbody>
                    </table>
                </div>

                <form id="tcAffectationForm" class="form-horizontal" novalidate="novalidate" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>?id_patient=<?php echo urlencode($id_patient); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="ajax_transmettre" value="1">
                    <input type="hidden" name="id_patient" value="<?php echo htmlspecialchars($id_patient, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="row form-group pb-3">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="col-form-label">Departement concerné</label>
                                <select name="service" class="form-control populate" id="serviceSelect" onchange="updateMotifs()">
                                    <option value=""> ------ Choisir ----- </option>
                                    <?php
                                    $coll = $bdd->prepare('SELECT * FROM organigramme WHERE id_organigramme IN (?, ?, ?, ?, ?)');
                                    $coll->execute([1, 2, 3, 4, 14]);
                                    while ($services = $coll->fetch(PDO::FETCH_ASSOC)) {
                                        echo '<option value="' . htmlspecialchars($services['id_organigramme'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($services['celulle'], ENT_QUOTES, 'UTF-8') . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="col-form-label">Motif de présence</label>
                                <select class="form-control populate" id="motifSelect" name="type" onchange="fetchMotifPrice()" required>
                                    <option value=""> ------ Choisir un service ----- </option>
                                </select>
                                <input type="hidden" id="hiddenMotifId" name="motif_id" value="">
                            </div>
                        </div>
                        <div class="col-md-2" id="productPrice"></div>
                    </div>
                </form>
            </div>
        </section>
    </div>
    <?php
    return ob_get_clean();
}

function tc_handleTransmission(PDO $bdd, $id_patient, array $post) {
    $state = [
        'errors' => 0,
        'existe' => 0,
        'rdvDuJour' => null,
        'rdvBloquant' => 0,
        'heuresRestantes' => 0,
        'minutesRestantes' => 0,
        'selectedType' => $post['type'] ?? null,
    ];

    $id_patient = (string)$id_patient;

    try {
        // Bloquer l'affectation uniquement si le patient a un rendez-vous aujourd'hui
        // pour le même motif/type de traitement sélectionné.
        $selectedType = $post['type'] ?? null;
        if (!empty($selectedType)) {
            $stRdv = $bdd->prepare('SELECT id_rdv, prochain_rdv, motif FROM dmd_rendez_vous WHERE id_patient = ? AND motif = ? AND DATE(prochain_rdv) = CURDATE() AND status IN (0,1,2) ORDER BY prochain_rdv ASC LIMIT 1');
            $stRdv->execute([$id_patient, $selectedType]);
            if ($stRdv->rowCount() > 0) {
                $state['rdvDuJour'] = $stRdv->fetch(PDO::FETCH_ASSOC);
                $state['rdvBloquant'] = 1;
            }
        }
    } catch (PDOException $e) {
        error_log('Erreur lors de la vérification du RDV du jour : ' . $e->getMessage());
    }

    if ($state['rdvBloquant'] === 1) {
        return $state;
    }

    try {
        // Vérifier s'il existe une affectation récente (moins de 24h) pour ce type de traitement
        $req1 = $bdd->prepare('SELECT * FROM affectations WHERE id_patient=? AND type=? AND status IN (?, ?, ?) ORDER BY date DESC LIMIT 1');
        $req1->execute([$id_patient, $post['type'], 6, 1, 2]);
        if ($req1->rowCount() > 0) {
            $affectationRecente = $req1->fetch(PDO::FETCH_ASSOC);
            if (!empty($affectationRecente['date'])) {
                $dateAffectation = new DateTime($affectationRecente['date']);
                $dateActuelle = new DateTime();
                $intervalle = $dateActuelle->diff($dateAffectation);
                $heuresEcoulees = $intervalle->h + ($intervalle->days * 24);
                if ($heuresEcoulees < 24) {
                    $state['existe'] = 2;
                    $state['heuresRestantes'] = 24 - $heuresEcoulees;
                    $state['minutesRestantes'] = 60 - $intervalle->i;
                }
            }
        }
    } catch (Throwable $e) {
        error_log('Erreur vérification affectation récente: ' . $e->getMessage());
        $state['errors'] = 4;
        return $state;
    }

    if ($state['existe'] !== 0) {
        return $state;
    }

    try {
        $model = null;
        $reponse1 = $bdd->prepare('SELECT * FROM traitements WHERE id_type = ?');
        $reponse1->execute([$post['type']]);
        while ($donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC)) {
            $model = $donnees1['id_organigramme'];
        }

        $req = $bdd->prepare('INSERT INTO affectations (id_patient, id_service, type) VALUES(?,?,?)');
        $req->execute([$id_patient, $model, $post['motif_id']]);

        $state['errors'] = 2;
        return $state;
    } catch (Throwable $e) {
        error_log('Erreur transmission caisse: ' . $e->getMessage());
        $state['errors'] = 4;
        return $state;
    }
}

// ===================== AJAX: charger le formulaire en modal =====================
if (isset($_GET['ajax_modal'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $id_patient_ajax = $_GET['id_patient'] ?? '';
    $id_patient_ajax = trim((string)$id_patient_ajax);
    if ($id_patient_ajax === '') {
        echo json_encode(['success' => false, 'message' => 'Numéro dossier invalide.']);
        exit;
    }
    try {
        $stmt = $bdd->prepare('SELECT id_patient FROM patients WHERE id_patient = ?');
        $stmt->execute([$id_patient_ajax]);
        if ($stmt->rowCount() <= 0) {
            echo json_encode(['success' => false, 'message' => 'Les numéro dossier saisie n\'existe pas dans le système.']);
            exit;
        }
        $html = tc_buildAffectationHtml($bdd, $id_patient_ajax, ['errors' => 0, 'existe' => 0]);
        echo json_encode(['success' => true, 'html' => $html]);
        exit;
    } catch (Throwable $e) {
        error_log('[TC ajax_modal] ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement.']);
        exit;
    }
}

// ===================== AJAX: valider affectation depuis le modal =====================
if (isset($_POST['ajax_transmettre']) && isset($_POST['id_patient'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $id_patient_ajax = trim((string)$_POST['id_patient']);
    if ($id_patient_ajax === '') {
        echo json_encode(['success' => false, 'message' => 'Numéro dossier invalide.']);
        exit;
    }
    if (empty($_POST['type'])) {
        echo json_encode(['success' => false, 'message' => 'Veuillez sélectionner un motif de présence.']);
        exit;
    }
    $state = tc_handleTransmission($bdd, $id_patient_ajax, $_POST);
    $html = tc_buildAffectationHtml($bdd, $id_patient_ajax, $state);
    echo json_encode([
        'success' => ((int)$state['errors'] === 2),
        'state' => $state,
        'html' => $html,
    ]);
    exit;
}

// Recherche classique (fallback sans JS)
if (isset($_POST['numero_dossier']) || isset($_POST['recherche'])) {
    try {
        $numero = isset($_POST['numero_dossier']) ? $_POST['numero_dossier'] : $_POST['recherche'];
        
        // Vérification de la connexion à la base de données
        $stmt = $bdd->prepare('SELECT id_patient FROM patients WHERE id_patient = ?');
        $stmt->execute([$numero]);
        
        if ($stmt->rowCount() > 0) {
            echo '<script>';
            echo 'document.location.href="'.htmlspecialchars($_SERVER['PHP_SELF']).'?id_patient='.$numero.'"';
            echo '</script>';
        } else {
            $existe = 1;
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la recherche du patient : " . $e->getMessage());
        $errors = 4;
    }
}

include('../PUBLIC/header.php'); 

?>

<body>
    <section class="body">

        <?php require('../PUBLIC/navbarmenu.php'); ?>

        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Transmission du patient à la caisse</h2>
                </header>

                <!-- start: page -->
                <?php 
                        if (!isset($_GET['id_patient'])) {
                        echo '
                        <div class="col-md-12">
                                <section class="card">
                                    <div class="card-body">';
                                        if ($existe==1) {
                                            echo '
                                                <div class="alert alert-danger">
                                                    <li>Les numéro dossier saisie n\'existe pas dans le système.</li>
                                                </div>
                                                ';
                                                } 
                                        echo'
                                        <form id="tcRechercheForm" class="form-horizontal" novalidate="novalidate" method="POST" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'" enctype="multipart/form-data">
                                        <div class="row form-group pb-3">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="formGroupExampleInput">Saisir le numero dossier du patient</label>
                                                    <input type="text" class="form-control" name="numero_dossier" id="formGroupExampleInput" required="">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <footer class="card-footer text-end">
                                        <button class="btn btn-primary" type="submit">continuer</button>
                                    </footer>
                                    </form>
                                </section>
                            </div>
                        </div>'; }

                        $id_patient = $_GET['id_patient'] ?? null;
                        if (!empty($id_patient)) {
                              $errors=0; $existe=0;
                            $rdvDuJour = null;
                            $rdvBloquant = 0;
                            $patient = nom_patient($id_patient);
                            $telephone = return_phone($id_patient);
                            $adresse = return_adresse($id_patient);
                            $responsable = return_responsable($id_patient);
                            $profession = return_profession($id_patient);
                            $age = return_age($id_patient);
                            $sexe = return_sexe($id_patient);
                            $assure = return_assure($id_patient);
                            $assurance = return_assurance($id_patient);
                            
                            if (isset($_POST['transmettre'])) {
                                // Bloquer l'affectation uniquement si le patient a un rendez-vous aujourd'hui
                                // pour le même motif/type de traitement sélectionné.
                                try {
                                    $selectedType = $_POST['type'] ?? null;
                                    if (!empty($selectedType)) {
                                        $stRdv = $bdd->prepare('SELECT id_rdv, prochain_rdv, motif FROM dmd_rendez_vous WHERE id_patient = ? AND motif = ? AND DATE(prochain_rdv) = CURDATE() AND status IN (0,1,2) ORDER BY prochain_rdv ASC LIMIT 1');
                                        $stRdv->execute([$id_patient, $selectedType]);
                                    } else {
                                        $stRdv = null;
                                    }
                                    if ($stRdv->rowCount() > 0) {
                                        $rdvDuJour = $stRdv->fetch(PDO::FETCH_ASSOC);
                                        $rdvBloquant = 1;
                                    }
                                } catch (PDOException $e) {
                                    error_log("Erreur lors de la vérification du RDV du jour : " . $e->getMessage());
                                }

                                if ($rdvBloquant === 0) {
                                // Vérifier s'il existe une affectation récente (moins de 24h) pour ce type de traitement
                                $req1 = $bdd->prepare('SELECT * FROM affectations WHERE id_patient=? AND type=? AND status IN (?, ?, ?) ORDER BY date DESC LIMIT 1');
                                $req1->execute([$id_patient, $_POST['type'], 6, 1, 2]);
                                $affectationRecente = null;
                                $heuresRestantes = 0;
                                $minutesRestantes = 0;
                                
                                if ($req1->rowCount() > 0) {
                                    $affectationRecente = $req1->fetch(PDO::FETCH_ASSOC);
                                    
                                    // Vérifier si moins de 24h se sont écoulées
                                    if (!empty($affectationRecente['date'])) {
                                        $dateAffectation = new DateTime($affectationRecente['date']);
                                        $dateActuelle = new DateTime();
                                        $intervalle = $dateActuelle->diff($dateAffectation);
                                        $heuresEcoulees = $intervalle->h + ($intervalle->days * 24);
                                        
                                        if ($heuresEcoulees < 24) {
                                            $existe = 2;
                                            $heuresRestantes = 24 - $heuresEcoulees;
                                            $minutesRestantes = 60 - $intervalle->i;
                                        }
                                    }
                                }

                                if ($existe == 0) {
                                
                                $reponse1 = $bdd->prepare('SELECT * FROM traitements WHERE id_type = ?');
                                $reponse1->execute([$_POST['type']]);
                                    while ($donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC))
                                    {
                                    $model = $donnees1['id_organigramme'];
                                    }
                            
                                $req = $bdd->prepare('INSERT INTO affectations (id_patient, id_service, type) VALUES(?,?,?)');
                                $req->execute([$id_patient, $model, $_POST['motif_id']]);

                                $errors=2; 
                                }
                                }
                            }
                        echo '
                        <div class="col-md-12">
							<section class="card">
								<div class="card-body">';
                                        if ($errors==2) {
                                        echo '
                                            <div class="alert alert-success">
                                            <strong>Succès</strong> <br/>  
                                            <li>Dossier patient transmis à la caisse pour paiement. Merci de rediriger le patient vers la caisse.</li>
                                            </div>
                                            ';
                                                }
                                        if ($errors==4) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <strong>Erreur</strong> <br/>  
                                                <li>Patient non transmis, merci de vérifier les informations saisies</li>.
                                            </div>
                                            ';}
                                        if ($rdvBloquant==1 && !empty($rdvDuJour)) {
                                            echo '
                                                <div class="alert alert-warning">
                                                    <strong>Attention</strong><br/>
                                                    <li>Ce patient a un rendez-vous prévu aujourd\'hui pour <strong>'.model($rdvDuJour['motif']).'</strong> (<strong>'.$rdvDuJour['prochain_rdv'].'</strong>).</li>
                                                    <li>Veuillez faire l\'affectation à partir du calendrier des rendez-vous.</li>
                                                    <li><a href="convocationdetails.php?rdv='.$rdvDuJour['id_rdv'].'">Ouvrir le rendez-vous</a> ou <a href="convocation.php">ouvrir le calendrier</a>.</li>
                                                </div>
                                            ';}
                                        if ($existe==2) {
                                            echo '
                                                <div class="alert alert-warning">
                                                    <strong>Attention</strong> <br/>  
                                                    <li>Ce patient a déjà une affectation active pour le traitement de <strong>'.model($_POST['type']).'</strong>.</li>
                                                    <li>Vous pourrez le transmettre à nouveau dans <strong>'.$heuresRestantes.' heure(s) et '.$minutesRestantes.' minute(s)</strong>.</li>
                                                    <li>L\'ancienne affectation ne sera pas supprimée, une nouvelle sera créée.</li>
                                                </div>
                                                ';}
                                    echo '
									<div class="row form-group pb-3">
										<div class="col-md-4">
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
										<div class="col-md-4">
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
												<label class="col-form-label" for="formGroupExampleInput">Type de patient</label>
												<input type="text" class="form-control" id="formGroupExampleInput" value="'.determinerStatutAssurance($assure).'" disabled>
											</div>
										</div>
                                        <div class="col-md-4">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Responsable</label>
												<input type="text" class="form-control" id="formGroupExampleInput" value="'.$responsable.'" disabled>
											</div>
										</div>
									</div>
                                    <form class="form-horizontal" novalidate="novalidate" method="POST" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'?id_patient='.$_GET['id_patient'].'" enctype="multipart/form-data" onsubmit="return confirmSubmit(event)">
                                    <input type="hidden" value="'.$_GET['id_patient'].'"> 
                                        <div class="row form-group pb-3">
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="formGroupExampleInput">Departement concerné</label>
                                                    <select name="service" class="form-control populate" id="serviceSelect" onchange="updateMotifs()">
                                                        <option value=""> ------ Choisir ----- </option>';
                                                            $coll = $bdd->prepare('SELECT * FROM organigramme WHERE id_organigramme IN (?, ?, ?, ?, ?)');
                                                            $coll -> execute([1, 2, 3, 4, 14]);
                                                            while ($services = $coll->fetch(PDO::FETCH_ASSOC))
                                                            {
                                                                echo '<option value="'.$services['id_organigramme'].'">'.$services['celulle'].'</option>';
                                                            } echo '
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="formGroupExampleInput">Motif de présence</label>
                                                    <select class="form-control populate" id="motifSelect" name="type" onchange="fetchMotifPrice()" data-plugin-selectTwo data-plugin-options="{ "minimumInputLength": O }" required>
                                                    <option value=""> ------ Choisir un service ----- </option>
                                                    </select>
                                                    <input type="hidden" id="hiddenMotifId" name="motif_id" value="">
                                                </div>
                                            </div>
                                            <div class="col-md-2" id="productPrice"></div>
                                        </div>
                                    <footer class="card-footer text-end">
                                        <button class="btn btn-primary" type="submit" name="transmettre">Transmettre à la caisse</button>
                                    </footer>
                                </form>
							</section>
						</div>
					</div>';}
                    ?>
                <!-- end: page -->
            </section>
        </div>
        <?php include('../PUBLIC/footer.php');?>
    </body>
</html>

<!-- Modal Transmission caisse -->
<div class="modal fade" id="tcModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transmission du patient à la caisse</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="tcModalAlert" class="alert d-none" role="alert"></div>
                <div id="tcModalBody"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="tcModalSubmit" disabled>Transmettre à la caisse</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
function confirmSubmit(event) {
    const motifSelect = document.getElementById('motifSelect');
    const motifValue = motifSelect.value;
    
    if (motifValue === '') {
        alert('Veuillez sélectionner un motif de présence avant de continuer.');
        return false;
    }
    
    // Vérifier s'il existe déjà une affectation pour ce type de traitement
    const patientId = new URLSearchParams(window.location.search).get('id_patient');
    
    // Afficher une notification Bootstrap pour confirmer
    const confirmMessage = 'Êtes-vous sûr de vouloir affecter ce patient à ce traitement ? Si une affectation existe déjà, elle sera mises à jour.';
    
    if (!confirm(confirmMessage)) {
        event.preventDefault();
        return false;
    }
    
    return true;
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('tcRechercheForm');
    const input = document.getElementById('formGroupExampleInput');
    const modalEl = document.getElementById('tcModal');
    const modalBody = document.getElementById('tcModalBody');
    const modalAlert = document.getElementById('tcModalAlert');
    const modalSubmitBtn = document.getElementById('tcModalSubmit');

    function showModalAlert(message, kind) {
        if (!modalAlert) return;
        modalAlert.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info');
        modalAlert.classList.add('alert-' + (kind || 'info'));
        modalAlert.textContent = message || '';
        modalAlert.classList.remove('d-none');
    }
    function hideModalAlert() {
        if (!modalAlert) return;
        modalAlert.classList.add('d-none');
        modalAlert.textContent = '';
    }

    async function loadModal(idPatient) {
        hideModalAlert();
        if (modalBody) modalBody.innerHTML = '<div class="text-muted">Chargement…</div>';
        if (modalSubmitBtn) modalSubmitBtn.disabled = true;

        const url = 'transmission-caisse.php?ajax_modal=1&id_patient=' + encodeURIComponent(idPatient);
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        if (!data || !data.success) {
            showModalAlert((data && data.message) ? data.message : 'Erreur de chargement.', 'danger');
            if (modalBody) modalBody.innerHTML = '';
            return;
        }
        if (modalBody) modalBody.innerHTML = data.html || '';
        if (modalSubmitBtn) modalSubmitBtn.disabled = false;
        if (window.bootstrap) {
            const instance = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
            instance.show();
        }
    }

    // Bouton "Transmettre" dans le footer du modal
    if (modalSubmitBtn) {
        modalSubmitBtn.addEventListener('click', function () {
            const affForm = modalBody ? modalBody.querySelector('#tcAffectationForm') : null;
            if (!affForm) {
                showModalAlert('Formulaire introuvable.', 'danger');
                return;
            }
            // Déclenche la soumission (sera interceptée en AJAX)
            if (typeof affForm.requestSubmit === 'function') {
                affForm.requestSubmit();
            } else {
                affForm.submit();
            }
        });
    }

    // Ouvre le formulaire en modal au lieu de rediriger
    if (form && input) {
        form.addEventListener('submit', function (e) {
            // Si JS actif, on fait en modal
            e.preventDefault();
            const val = (input.value || '').trim();
            if (!val) return;
            loadModal(val).catch(function () {
                showModalAlert('Erreur lors du chargement.', 'danger');
            });
        });
    }

    // Soumission de l'affectation depuis le modal (AJAX)
    document.addEventListener('submit', function (e) {
        const affForm = e.target;
        if (!affForm || affForm.id !== 'tcAffectationForm') return;
        e.preventDefault();
        hideModalAlert();

        const motifSelect = affForm.querySelector('#motifSelect');
        const motifValue = motifSelect ? motifSelect.value : '';
        if (!motifValue) {
            showModalAlert('Veuillez sélectionner un motif de présence avant de continuer.', 'warning');
            return;
        }

        const confirmMessage = 'Êtes-vous sûr de vouloir affecter ce patient à ce traitement ? Si une affectation existe déjà, elle sera mises à jour.';
        if (!confirm(confirmMessage)) {
            return;
        }

        const fd = new FormData(affForm);
        fetch('transmission-caisse.php', {
            method: 'POST',
            body: fd,
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            if (!data || !data.html) {
                showModalAlert('Erreur lors de la transmission.', 'danger');
                return;
            }
            if (modalBody) modalBody.innerHTML = data.html;
        })
        .catch(() => {
            showModalAlert('Erreur lors de la transmission.', 'danger');
        });
    });

    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', function () {
            hideModalAlert();
            if (modalBody) modalBody.innerHTML = '';
            if (modalSubmitBtn) modalSubmitBtn.disabled = true;
        });
    }
});
</script>
