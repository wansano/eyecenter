<?php
include('../public/connect.php');
require('../public/fonction.php');
session_start();

include('../public/header.php'); 

// initialisation du flag d'état
$errors = 0;
$existe = 0;
$showReprintModalOnLoad = false;
$bypassCaisseAfterTransmit = false;
if (isset($_POST['transmettre'])) {
    // on reçoit l'id du rdv (et non l'id patient) pour sécuriser l'opération
    $rdv_post = intval($_POST['transmettre']);

    // récupérer les informations du rdv
    $userDataPost = getRdvInfo($bdd, $rdv_post);
    $id_patient_post = getPatientIdByRdv($bdd, $rdv_post);

    if (!$userDataPost) {
        $errors = 4;
    } else {
        $id_service_post = $userDataPost['id_service'] ?? null;
        $motifrdv_post = $userDataPost['motif'] ?? null;
        $type_patient = (int)($userDataPost['type_patient'] ?? 0);
        $id_demande = (int)($userDataPost['id_demande'] ?? 0);

        try {
            $bdd->beginTransaction();

            $createdPatientNow = false;

            // RDV externe en attente : créer le patient à la transmission
            if (!$id_patient_post && $type_patient === 1 && $id_demande > 0 && dbTableHasColumn($bdd, 'dmd_rendez_vous', 'id_demande')) {
                $demandeRow = getDemandeEnAttenteById($bdd, $id_demande);
                if (!$demandeRow) {
                    throw new Exception('Demande en attente introuvable.');
                }

                $existingBefore = findPatientIdByExternalIdentity($bdd, $demandeRow);
                $id_patient_post = (int)createOrFindPatientFromDemande($bdd, $demandeRow);
                $createdPatientNow = ($existingBefore === null);
                $stUp = $bdd->prepare('UPDATE dmd_rendez_vous SET id_patient = ? WHERE id_rdv = ?');
                $stUp->execute([(int)$id_patient_post, $rdv_post]);

                $stC = $bdd->prepare('SELECT COUNT(*) FROM dmd_rendez_vous WHERE id_demande = ? AND id_rdv != ?');
                $stC->execute([$id_demande, $rdv_post]);
                if ((int)$stC->fetchColumn() === 0) {
                    $stDel = $bdd->prepare('DELETE FROM dossier_en_attente WHERE id_demande = ?');
                    $stDel->execute([$id_demande]);
                }
            }

            if (!$id_patient_post || !$id_service_post || !$motifrdv_post) {
                throw new Exception('Informations manquantes pour transmettre.');
            }

            // Avis médical (operation=8) : ne pas passer par la caisse
            $bypassCaisseAfterTransmit = ((int)operation((int)$motifrdv_post) === 8);

            $affecterPar = (int)($_SESSION['auth'] ?? 0);
            $hasAffecterPar = false;
            if (function_exists('dbTableHasColumn')) {
                $hasAffecterPar = dbTableHasColumn($bdd, 'affectations', 'affecter_par');
            }

            if ($bypassCaisseAfterTransmit) {
                if ($hasAffecterPar && $affecterPar > 0) {
                    $stmt = $bdd->prepare('INSERT INTO affectations (id_patient, id_service, type, id_rdv, affecter_par, status, montant, taux, type_paiement) VALUES (?, ?, ?, ?, ?, 1, 0, 0, 0)');
                    $stmt->execute([(int)$id_patient_post, (int)$id_service_post, (int)$motifrdv_post, $rdv_post, $affecterPar]);
                } else {
                    $stmt = $bdd->prepare('INSERT INTO affectations (id_patient, id_service, type, id_rdv, status, montant, taux, type_paiement) VALUES (?, ?, ?, ?, 1, 0, 0, 0)');
                    $stmt->execute([(int)$id_patient_post, (int)$id_service_post, (int)$motifrdv_post, $rdv_post]);
                }
            } else {
                if ($hasAffecterPar && $affecterPar > 0) {
                    $stmt = $bdd->prepare('INSERT INTO affectations (id_patient, id_service, type, id_rdv, affecter_par) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([(int)$id_patient_post, (int)$id_service_post, (int)$motifrdv_post, $rdv_post, $affecterPar]);
                } else {
                    $stmt = $bdd->prepare('INSERT INTO affectations (id_patient, id_service, type, id_rdv) VALUES (?, ?, ?, ?)');
                    $stmt->execute([(int)$id_patient_post, (int)$id_service_post, (int)$motifrdv_post, $rdv_post]);
                }
            }

            // status=1 : transmis (en attente caisse) | status=2 : considéré payé (bypass caisse)
            $rdvStatus = $bypassCaisseAfterTransmit ? 2 : 1;
            $stmt = $bdd->prepare('UPDATE dmd_rendez_vous SET status = ? WHERE id_rdv = ?');
            $stmt->execute([(int)$rdvStatus, $rdv_post]);

            $bdd->commit();
            $errors = 2;

            if ($createdPatientNow) {
                $showReprintModalOnLoad = true;
            }
        } catch (Throwable $e) {
            if ($bdd->inTransaction()) {
                $bdd->rollBack();
            }
            error_log("Erreur lors de la transmission du rendez-vous : " . $e->getMessage());
            $errors = 4;
        }
    }
}

if (isset($_POST['impression'])) {
    
    // on reçoit l'id du rdv (et non l'id patient) pour sécuriser l'opération

    $rdv_post = intval($_POST['impression']);
    $id_patient_post = getPatientIdByRdv($bdd, $rdv_post);

    if ($id_patient_post) {
        echo "<script>
        window.onload = function() {
            if (typeof window.openPrintModal === 'function') {
                window.openPrintModal('imprimer_dossier.php?id_patient=".$id_patient_post."', 'Impression dossier');
            }
        };
        </script>";
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
                        $rendezvous = (int)($_GET['rdv'] ?? 0);
                        $userData = $rendezvous ? getRdvInfo($bdd, $rendezvous) : null;

                        $id_patient = $rendezvous ? getPatientIdByRdv($bdd, $rendezvous) : null;
                        $id_demande = (int)($userData['id_demande'] ?? 0);

                        $patient = '';
                        $telephone = '';
                        $adresse = '';
                        $responsable = '';
                        $profession = '';
                        $age = '';
                        $sexe = '';
                        $assure = 0;
                        $assurance = 0;

                        if ($id_patient) {
                            $patient = nom_patient($id_patient);
                            $telephone = return_phone($id_patient);
                            $adresse = return_adresse($id_patient);
                            $responsable = return_responsable($id_patient);
                            $profession = return_profession($id_patient);
                            $age = return_age($id_patient);
                            $sexe = return_sexe($id_patient);
                            $assure = return_assure($id_patient);
                            $assurance = return_assurance($id_patient);
                        } elseif ($userData && (int)($userData['type_patient'] ?? 0) === 1 && $id_demande > 0 && dbTableHasColumn($bdd, 'dmd_rendez_vous', 'id_demande')) {
                            $demandeRow = getDemandeEnAttenteById($bdd, $id_demande);
                            if ($demandeRow) {
                                $patient = $demandeRow['nom_patient'] ?? '';
                                $telephone = $demandeRow['phone'] ?? '';
                                $adresse = $demandeRow['adresse'] ?? '';
                                $responsable = $demandeRow['responsable'] ?? '';
                                $profession = $demandeRow['profession'] ?? '';
                                $age = $demandeRow['age'] ?? '';
                                $sexe = $demandeRow['sexe'] ?? '';
                                $assure = $demandeRow['assure'] ?? 0;
                                $assurance = $demandeRow['assurance'] ?? 0;
                            }
                        }

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
								if ($errors==2) {
                                        echo '
                                            <div class="alert alert-success">
                                            <strong>Succès</strong> <br/>  
                                            <li>' . (!empty($bypassCaisseAfterTransmit)
                                                ? 'Le dossier patient est transmis directement au médecin. Aucun paiement n\'est requis.'
                                                : 'Dossier patient transmis à la caisse pour paiement. Merci de rediriger le patient vers la caisse.'
                                            ) . '</li>
                                            </div>
                                            ';}
                                        if ($errors==4) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <strong>Erreur</strong> <br/>  
                                                <li>Patient non transmis, merci de vérifier les informations saisies</li>.
                                            </div>
                                            ';}
                                        if ($existe==2) {
                                            echo '
                                                <div class="alert alert-danger">
                                                    <strong>Erreur</strong> <br/>  
                                                    <li>Ce patient est déjà transmis pour ce traitement de <strong>'.model($_POST['type']).'</strong>.</li>
                                                </div>
                                    ';} echo'
									<div class="row form-group pb-3">
										<div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">N° dossier</label>
                                                    <input type="text" class="form-control" value="'.($id_patient ? $id_patient : ($id_demande ? ('DEM-'.$id_demande) : '')).'" disabled>
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
                                        <hr/>
                                        <hr/>
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Date rendez-vous</label>
												<input type="text" class="form-control" id="formGroupExampleInput" value="'.$rdv.'" disabled>
											</div>
										</div>
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Service</label>
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
										</div>';
                                            if( $status === 0) { echo'
    										<div class="col-md-12">
                                                <div class="d-flex gap-2">
                                                    <a href="miseajourdv.php?rdv='.($rendezvous).'" class="btn btn-dark text-center my-4"> <i class="fa fa-edit"></i> modifier RdV</a>
                                                </div>
                                            </div>';
    										}

                                            if ($status !== 0 && !empty($id_patient) && !empty($showReprintModalOnLoad)) {
    											echo '
    											<div class="col-md-12">
    												<button class="btn btn-success text-center my-4" type="button" data-bs-toggle="modal" data-bs-target="#patientInfoModal" data-patient-id="'.$id_patient.'"> <i class="fa fa-print"></i> réimpression</button>
    											</div>
    										';
    										}

    										echo'
									</div>
							</section>
						</div>
					</div>';
                    ?>
                <!-- end: page -->
            </section>
        </div>

            <!-- Modal: Réimpression documents (dossier + carte) -->
            <div class="modal fade" id="patientInfoModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Réimpression documents</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info mb-0">
                                Sélectionnez le document à imprimer.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                <a href="#" class="btn btn-primary" id="btnImprimerDossier"> <i class="fa fa-print"></i> dossier</a>
                                <a href="#" class="btn btn-info" id="btnImprimerCarte"> <i class="fa fa-print"></i> carte d'adhésion</a>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalEl = document.getElementById('patientInfoModal');
                const btnDossier = document.getElementById('btnImprimerDossier');
                const btnCarte = document.getElementById('btnImprimerCarte');

                if (!modalEl) return;

                modalEl.addEventListener('show.bs.modal', function (event) {
                    const trigger = event.relatedTarget;
                    const pid = trigger ? trigger.getAttribute('data-patient-id') : null;
                    if (!pid) return;

                    if (btnDossier) btnDossier.href = 'imprimer_dossier.php?id_patient=' + encodeURIComponent(pid);
                    if (btnCarte) btnCarte.href = 'imprimer_carte.php?id_patient=' + encodeURIComponent(pid);
                });
            });
            </script>

            <?php if (!empty($showReprintModalOnLoad) && !empty($id_patient)) { ?>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (!window.bootstrap) return;
                const modalEl = document.getElementById('patientInfoModal');
                if (!modalEl) return;

                const pid = <?php echo (int)$id_patient; ?>;
                const btnDossier = document.getElementById('btnImprimerDossier');
                const btnCarte = document.getElementById('btnImprimerCarte');
                if (btnDossier) btnDossier.href = 'imprimer_dossier.php?id_patient=' + encodeURIComponent(pid);
                if (btnCarte) btnCarte.href = 'imprimer_carte.php?id_patient=' + encodeURIComponent(pid);

                const instance = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
                instance.show();
            });
            </script>
            <?php } ?>

        <?php include('../public/footer.php');?>
