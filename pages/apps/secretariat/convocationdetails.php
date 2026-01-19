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
$showPrintModalOnLoad = false;
$printUrlOnLoad = '';
$printTitleOnLoad = '';

// RDV courant (utilisé pour action du formulaire et sécurisation)
$rendezvous = (int)($_GET['rdv'] ?? 0);

// Mise à jour du rendez-vous (depuis le modal)
if (isset($_POST['maj_rdv'])) {
    $rdv_post = (int)$_POST['maj_rdv'];

    // Sécuriser : le POST doit viser le même RDV que la page
    if ($rdv_post <= 0 || $rendezvous <= 0 || $rdv_post !== $rendezvous) {
        $errors = 7;
    } else {
        $dateRdv = isset($_POST['date_rdv']) ? trim((string)$_POST['date_rdv']) : '';
        $creneauRaw = isset($_POST['prochain_rdv']) ? trim((string)$_POST['prochain_rdv']) : '';
        $service = isset($_POST['service']) ? (int)$_POST['service'] : 0;
        $medecin = isset($_POST['medecin']) ? (int)$_POST['medecin'] : 0;
        $motif = isset($_POST['motif']) ? (int)$_POST['motif'] : 0;

        // Extraire l'heure du créneau (peut être ISO 2025-10-01T08:00:00 ou "08:00")
        $creneau = '';
        if ($creneauRaw !== '') {
            if (strpos($creneauRaw, 'T') !== false) {
                $parts = explode('T', $creneauRaw);
                $creneau = $parts[1] ?? '';
            } elseif (strpos($creneauRaw, ' ') !== false) {
                $parts = explode(' ', $creneauRaw);
                $creneau = $parts[1] ?? '';
            } else {
                $creneau = $creneauRaw;
            }
        }

        $validDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRdv);
        $validTime = preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $creneau);

        if ($validDate && $validTime && $service > 0 && $medecin > 0 && $motif > 0) {
            if (strlen($creneau) === 5) {
                $creneau .= ':00';
            }
            $nouveauRdv = $dateRdv . ' ' . $creneau;

            try {
                // Refuser la mise à jour si le RDV est déjà transmis/traité
                $userDataPost = getRdvInfo($bdd, $rdv_post);
                if (!$userDataPost) {
                    throw new Exception('Rendez-vous introuvable.');
                }
                if ((int)($userDataPost['status'] ?? -1) !== 0) {
                    throw new Exception('Mise à jour refusée : ce rendez-vous est déjà en cours de traitement.');
                }

                // Vérifier que le service (département) existe
                $stService = $bdd->prepare('SELECT COUNT(*) FROM organigramme WHERE id_organigramme = ?');
                $stService->execute([$service]);
                if ((int)$stService->fetchColumn() <= 0) {
                    throw new Exception('Service invalide.');
                }

                // Sécuriser: le motif choisi doit appartenir au service sélectionné
                $stMotif = $bdd->prepare('SELECT id_organigramme FROM traitements WHERE id_type = ? AND status = 1 LIMIT 1');
                $stMotif->execute([$motif]);
                $motifService = (int)$stMotif->fetchColumn();
                if ($motifService !== $service) {
                    throw new Exception('Motif invalide pour ce service.');
                }

                // Sécuriser: le médecin doit appartenir au service sélectionné (ou être global type 4/6)
                $stMed = $bdd->prepare('SELECT COUNT(*) FROM users WHERE id = ? AND status = 1 AND (type = ? OR type IN (4, 6))');
                $stMed->execute([$medecin, $service]);
                if ((int)$stMed->fetchColumn() <= 0) {
                    throw new Exception('Médecin invalide pour ce service.');
                }

                // Vérifier si le créneau est libre (exclure le RDV en cours)
                $check = $bdd->prepare('SELECT COUNT(*) FROM dmd_rendez_vous WHERE traitant = ? AND prochain_rdv = ? AND id_rdv != ?');
                $check->execute([$medecin, $nouveauRdv, $rdv_post]);

                if ((int)$check->fetchColumn() > 0) {
                    $errors = 9; // Créneau déjà occupé
                } else {
                    $stmt = $bdd->prepare('UPDATE dmd_rendez_vous SET prochain_rdv = ?, traitant = ?, motif = ?, id_service = ? WHERE id_rdv = ?');
                    $stmt->execute([$nouveauRdv, $medecin, $motif, $service, $rdv_post]);
                    $errors = 8; // Succès maj
                }
            } catch (Throwable $e) {
                error_log('Erreur mise à jour RDV (modal): ' . $e->getMessage());
                $errors = 7;
            }
        } else {
            $errors = 7;
        }
    }
}

if (isset($_POST['suppression'])) {
    // on reçoit l'id du rdv (et non l'id patient) pour sécuriser l'opération
    $rdv_post = intval($_POST['suppression']);

    $userDataPost = getRdvInfo($bdd, $rdv_post);
    if (!$userDataPost) {
        $errors = 6;
    } else {
        try {
            // Autoriser uniquement la suppression si le rendez-vous n'a pas encore été transmis/traité
            if ((int)($userDataPost['status'] ?? -1) !== 0) {
                throw new Exception("Suppression refusée : ce rendez-vous est déjà en cours de traitement.");
            }

            $bdd->beginTransaction();

            // Bloquer si une affectation existe déjà pour ce rendez-vous
            $stmt = $bdd->prepare('SELECT COUNT(*) FROM affectations WHERE id_rdv = ?');
            $stmt->execute([$rdv_post]);
            $hasAffectation = (int)$stmt->fetchColumn() > 0;
            if ($hasAffectation) {
                throw new Exception("Suppression refusée : ce rendez-vous a déjà une affectation associée.");
            }

            // Récupérer une éventuelle demande en attente pour nettoyage
            $idDemandeToMaybeCleanup = null;
            if (dbTableHasColumn($bdd, 'dmd_rendez_vous', 'id_demande')) {
                $stD = $bdd->prepare('SELECT id_demande FROM dmd_rendez_vous WHERE id_rdv = ?');
                $stD->execute([$rdv_post]);
                $val = $stD->fetchColumn();
                $idDemandeToMaybeCleanup = ($val !== false && $val !== null && $val !== '') ? (int)$val : null;
            }

            $stmt = $bdd->prepare('DELETE FROM dmd_rendez_vous WHERE id_rdv = ?');
            $stmt->execute([$rdv_post]);

            // Si c'était une demande en attente et qu'elle n'est plus référencée par aucun RDV, on la supprime.
            if ($idDemandeToMaybeCleanup) {
                $stC = $bdd->prepare('SELECT COUNT(*) FROM dmd_rendez_vous WHERE id_demande = ?');
                $stC->execute([$idDemandeToMaybeCleanup]);
                if ((int)$stC->fetchColumn() === 0) {
                    $stDel = $bdd->prepare('DELETE FROM dossier_en_attente WHERE id_demande = ?');
                    $stDel->execute([$idDemandeToMaybeCleanup]);
                }
            }

            $bdd->commit();

            // Redirection après suppression pour éviter re-POST
            header('Location: convocation.php?deleted=1');
            exit();
        } catch (Exception $e) {
            if ($bdd->inTransaction()) {
                $bdd->rollBack();
            }
            error_log('Erreur suppression rendez-vous : ' . $e->getMessage());
            $errors = 6;
        }
    }
}

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

            // Si RDV externe en attente : créer le patient à partir de dossier_en_attente au moment de transmettre
            if (!$id_patient_post && $type_patient === 1 && $id_demande > 0 && dbTableHasColumn($bdd, 'dmd_rendez_vous', 'id_demande')) {
                $demandeRow = getDemandeEnAttenteById($bdd, $id_demande);
                if (!$demandeRow) {
                    throw new Exception('Demande en attente introuvable.');
                }

                $existingBefore = findPatientIdByExternalIdentity($bdd, $demandeRow);
                $id_patient_post = (int)createOrFindPatientFromDemande($bdd, $demandeRow);
                $createdPatientNow = ($existingBefore === null);

                // Lier le RDV au nouveau patient
                $stUp = $bdd->prepare('UPDATE dmd_rendez_vous SET id_patient = ? WHERE id_rdv = ?');
                $stUp->execute([(int)$id_patient_post, $rdv_post]);

                // Nettoyer la demande si plus aucun RDV ne la référence
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

            // insertion dans affectations
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

            // mise à jour du rdv
            // status=1 : transmis (en attente caisse) | status=2 : considéré payé (bypass caisse)
            $rdvStatus = $bypassCaisseAfterTransmit ? 2 : 1;
            $stmt = $bdd->prepare('UPDATE dmd_rendez_vous SET status = ? WHERE id_rdv = ?');
            $stmt->execute([(int)$rdvStatus, $rdv_post]);

            $bdd->commit();
            $errors = 2; // succès

            // Proposer la réimpression uniquement si c'est un nouveau patient créé maintenant
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
        // Toujours en modal (pas de window.open)
        $showPrintModalOnLoad = true;
        $printUrlOnLoad = 'imprimer_dossier.php?id_patient=' . (int)$id_patient_post;
        $printTitleOnLoad = 'Impression dossier';
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
                        $userData = $rendezvous ? getRdvInfo($bdd, $rendezvous) : null;

                        $id_patient = $rendezvous ? getPatientIdByRdv($bdd, $rendezvous) : null;
                        $id_demande = (int)($userData['id_demande'] ?? 0);

                        // Valeurs par défaut (évite notices)
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
                                        if ($errors==8) {
                                            echo '
                                                <div class="alert alert-success">
                                                    <strong>Succès</strong> <br/>
                                                    <li>Rendez-vous mis à jour avec succès.</li>
                                                </div>
                                            ';
                                        }
                                        if ($errors==9) {
                                            echo '
                                                <div class="alert alert-warning">
                                                    <strong>Attention</strong> <br/>
                                                    <li>Ce créneau est déjà occupé. Merci de choisir un autre créneau.</li>
                                                </div>
                                            ';
                                        }
                                        if ($errors==7) {
                                            echo '
                                                <div class="alert alert-danger">
                                                    <strong>Erreur</strong> <br/>
                                                    <li>Impossible de mettre à jour ce rendez-vous. Vérifiez les champs ou le statut.</li>
                                                </div>
                                            ';
                                        }
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
								if ($errors==6) {
									echo '
										<div class="alert alert-danger">
											<strong>Erreur</strong> <br/>
											<li>Impossible de supprimer ce rendez-vous. Vérifiez son statut ou s\'il est déjà lié à une affectation.</li>
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
										</div>
                                        </div>';
        									if( $status === 0) { echo'
                                        <div class="row form-group pb-3">
                                            <div class="col-md-12">
                                                <div class="d-flex gap-2">
    												<button type="button" class="btn btn-dark text-center my-4" data-bs-toggle="modal" data-bs-target="#majRdvModal"> <i class="fa fa-edit"></i> mettre à jour</button>
                                                    <form action="'.htmlspecialchars($_SERVER['PHP_SELF']).'?rdv='.$rendezvous.'" method="post" class="d-inline">
                                                        <input type="hidden" name="transmettre" value="'.$rendezvous.'">
                                                        <button class="btn btn-info text-center my-4" type="submit"> <i class="fa fa-paper-plane"></i> transmettre</button>
                                                    </form>
                                                    <form action="'.htmlspecialchars($_SERVER['PHP_SELF']).'?rdv='.$rendezvous.'" method="post" class="d-inline" onsubmit="return confirm(\'Confirmer la suppression de ce rendez-vous ?\');">
                                                        <input type="hidden" name="suppression" value="'.$rendezvous.'">
                                                        <button class="btn btn-danger text-center my-4" type="submit"> <i class="fa fa-trash"></i> Annuler</button>
                                                    </form>';
                                                    echo'
                                                </div>
                                            </div>
									    </div>

                                            ';}

                                            // Après transmission (status != 0), proposer aussi la réimpression
                                            if ($status !== 0 && !empty($id_patient) && !empty($showReprintModalOnLoad)) {
                                                echo '
                                                <div class="row form-group pb-3">
                                                    <div class="col-md-12">
                                                        <button class="btn btn-success text-center my-4" type="button" data-bs-toggle="modal" data-bs-target="#patientInfoModal" data-patient-id="'.$id_patient.'"> <i class="fa fa-print"></i> réimpression</button>
                                                    </div>
                                                </div>
                                            ';
                                            }

                                            echo '
							</section>
						</div>
					</div>';
                    ?>
                <!-- end: page -->
            </section>
        </div>

                            <!-- Modal: Mise à jour rendez-vous -->
                            <div class="modal fade" id="majRdvModal" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Mise à jour du rendez-vous</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?rdv=<?php echo (int)$rendezvous; ?>" method="post" id="formMajRdvModal">
                                            <div class="modal-body">
                                                <div class="row form-group pb-3">
                                                    <div class="col-md-3">
                                                        <div class="form-group">
                                                            <label class="col-form-label">Département concerné</label>
                                                            <select name="service" class="form-control" id="serviceSelectMaj" required>
                                                                <?php
                                                                $currentService = !empty($id_service) ? (int)$id_service : 0;
                                                                $allowed = [1, 2, 3];

                                                                // Charger les services (inspiré de ajoutrdv.php)
                                                                $ids = $allowed;
                                                                if ($currentService > 0 && !in_array($currentService, $ids, true)) {
                                                                    $ids[] = $currentService;
                                                                }
                                                                $ids = array_values(array_unique($ids));

                                                                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                                                                $stSrv = $bdd->prepare('SELECT id_organigramme, celulle FROM organigramme WHERE id_organigramme IN (' . $placeholders . ')');
                                                                $stSrv->execute($ids);
                                                                $rowsSrv = $stSrv->fetchAll(PDO::FETCH_ASSOC);

                                                                // Conserver un ordre stable: ids
                                                                $srvById = [];
                                                                foreach ($rowsSrv as $r) {
                                                                    $srvById[(int)$r['id_organigramme']] = (string)$r['celulle'];
                                                                }
                                                                foreach ($ids as $sid) {
                                                                    $sid = (int)$sid;
                                                                    if (empty($srvById[$sid])) continue;
                                                                    $selected = ($currentService > 0 && $sid === $currentService) ? 'selected' : '';
                                                                    echo '<option value="' . $sid . '" ' . $selected . '>' . htmlspecialchars($srvById[$sid]) . '</option>';
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="form-group">
                                                            <label class="col-form-label">Motif</label>
                                                            <select name="motif" class="form-control" id="motifSelectMaj" required>
                                                                <option value="">-- Choisir un motif --</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="form-group">
                                                            <label class="col-form-label">Médecin</label>
                                                            <select name="medecin" class="form-control" id="medecinSelect" required>
                                                                <option value="">-- Sélectionner un médecin --</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="form-group">
                                                            <label class="col-form-label">Nouvelle date</label>
                                                            <input type="date" class="form-control" name="date_rdv" id="dateRdvInput" min="<?php echo date('Y-m-d'); ?>" required>
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

                                                <input type="hidden" name="maj_rdv" value="<?php echo (int)$rendezvous; ?>">
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                                <button class="btn btn-primary" type="submit">Mettre à jour</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
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

                <!-- Modal Impression (aperçu + impression) -->
                <div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="printModalTitle">Impression</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-0" style="height:80vh;">
                                <iframe id="printFrame" src="about:blank" style="width:100%; height:100%;" frameborder="0"></iframe>
                            </div>
                            <div class="modal-footer">
                                <button type="button" id="printBtn" class="btn btn-primary">Imprimer</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
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

                <script>
                // Impression dossier/carte : toujours en modal (iframe)
                document.addEventListener('DOMContentLoaded', function () {
                    const printModalEl = document.getElementById('printModal');
                    const printFrameEl = document.getElementById('printFrame');
                    const printTitleEl = document.getElementById('printModalTitle');
                    const printBtnEl = document.getElementById('printBtn');

                    function withAutoPrintDisabled(url) {
                        if (!url) return url;
                        return url + (url.indexOf('?') >= 0 ? '&' : '?') + 'autoprint=0';
                    }

                    window.openPrintModal = function (url, titleText) {
                        if (!url) return;
                        if (!window.bootstrap || !printModalEl || !printFrameEl) {
                            alert('Impossible d\'ouvrir l\'impression: Bootstrap indisponible.');
                            return;
                        }
                        if (printTitleEl) printTitleEl.textContent = titleText || 'Impression';
                        printFrameEl.src = withAutoPrintDisabled(url);
                        const instance = window.bootstrap.Modal.getInstance(printModalEl) || new window.bootstrap.Modal(printModalEl);
                        instance.show();
                    };

                    function bind(btn, titleText) {
                        if (!btn) return;
                        btn.addEventListener('click', function (e) {
                            const href = btn.getAttribute('href');
                            if (!href || href === '#') return;
                            e.preventDefault();
                            window.openPrintModal(href, titleText);
                        });
                    }

                    bind(document.getElementById('btnImprimerDossier'), 'Impression dossier');
                    bind(document.getElementById('btnImprimerCarte'), "Impression carte d'adhésion");

                    if (printBtnEl) {
                        printBtnEl.addEventListener('click', function () {
                            try {
                                const win = printFrameEl && printFrameEl.contentWindow ? printFrameEl.contentWindow : null;
                                if (win && typeof win.printPdf === 'function') {
                                    win.printPdf();
                                    return;
                                }
                                if (win && typeof win.print === 'function') {
                                    win.print();
                                }
                            } catch (err) {
                                // noop
                            }
                        });
                    }

                    if (printModalEl) {
                        printModalEl.addEventListener('hidden.bs.modal', function () {
                            if (printFrameEl) printFrameEl.src = 'about:blank';
                        });
                    }

                    // Ouverture automatique si impression déclenchée côté serveur
                    var autoOpen = <?php echo !empty($showPrintModalOnLoad) ? 'true' : 'false'; ?>;
                    if (autoOpen) {
                        window.openPrintModal(
                            <?php echo json_encode((string)$printUrlOnLoad); ?>,
                            <?php echo json_encode((string)$printTitleOnLoad); ?>
                        );
                    }
                });
                </script>

                <script>
                // Chargement des créneaux dans le modal via la fonction genererCreneaux (custom.js)
                function resetSelect(selectEl, placeholder) {
                    if (!selectEl) return;
                    selectEl.innerHTML = '';
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = placeholder || '---';
                    selectEl.appendChild(opt);
                    if (window.jQuery && window.jQuery(selectEl).data('select2')) {
                        window.jQuery(selectEl).val('').trigger('change');
                    }
                }

                function updateCreneaux() {
                    const medecinSelect = document.getElementById('medecinSelect');
                    const dateInput = document.getElementById('dateRdvInput');
                    const creneauSelect = document.getElementById('creneauSelect');

                    const medecin = medecinSelect ? medecinSelect.value : '';
                    const date = dateInput ? dateInput.value : '';

                    if (!medecin || !date) {
                        resetSelect(creneauSelect, '------ Choisir médecin et date -----');
                        if (creneauSelect) creneauSelect.disabled = true;
                        return;
                    }

                    const rdvId = <?php echo (int)$rendezvous; ?>;
                    if (typeof genererCreneaux === 'function') {
                        genererCreneaux(date, medecin, rdvId);
                    } else {
                        console.error('Fonction genererCreneaux non trouvée. Vérifiez que custom.js est chargé.');
                        resetSelect(creneauSelect, 'Erreur de chargement');
                        if (creneauSelect) creneauSelect.disabled = true;
                    }
                }

                document.addEventListener('DOMContentLoaded', function () {
                    const serviceSelect = document.getElementById('serviceSelectMaj');
                    const medecinSelect = document.getElementById('medecinSelect');
                    const dateInput = document.getElementById('dateRdvInput');
                    const modalEl = document.getElementById('majRdvModal');
                    const motifSelect = document.getElementById('motifSelectMaj');

                    const initialService = <?php echo !empty($id_service) ? (int)$id_service : 0; ?>;
                    const initialMotif = <?php echo !empty($motifrdv) ? (int)$motifrdv : 0; ?>;
                    const initialMedecin = <?php echo !empty($id_medecin) ? (int)$id_medecin : 0; ?>;

                    function getSelectedServiceId() {
                        if (!serviceSelect) return initialService;
                        const v = parseInt(serviceSelect.value || '0', 10);
                        return Number.isFinite(v) ? v : 0;
                    }

                    function loadMotifsForService(keepInitialSelection) {
                        const serviceId = getSelectedServiceId();
                        if (!motifSelect) return;
                        if (!serviceId) {
                            motifSelect.innerHTML = '<option value="">-- Service introuvable --</option>';
                            return;
                        }

                        const selectedMotif = keepInitialSelection ? initialMotif : 0;
                        motifSelect.innerHTML = '<option value="">Chargement…</option>';

                        fetch(`../public/getMotifs.php?service=${encodeURIComponent(serviceId)}`)
                            .then(r => {
                                if (!r.ok) throw new Error('HTTP ' + r.status);
                                return r.json();
                            })
                            .then(data => {
                                motifSelect.innerHTML = '<option value="">-- Choisir un motif --</option>';
                                if (data && data.success && Array.isArray(data.motifs)) {
                                    for (const m of data.motifs) {
                                        const opt = document.createElement('option');
                                        opt.value = m.id;
                                        opt.textContent = m.nom;
                                        if (selectedMotif && String(m.id) === String(selectedMotif)) {
                                            opt.selected = true;
                                        }
                                        motifSelect.appendChild(opt);
                                    }
                                } else {
                                    motifSelect.innerHTML = '<option value="">Aucun motif</option>';
                                }
                            })
                            .catch(err => {
                                console.error('Erreur chargement motifs:', err);
                                motifSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                            });
                    }

                    function loadMedecinsForService(keepInitialSelection) {
                        const serviceId = getSelectedServiceId();
                        if (!medecinSelect) return;
                        resetSelect(medecinSelect, 'Chargement…');

                        if (!serviceId) {
                            resetSelect(medecinSelect, '------ Choisir un département -----');
                            return;
                        }

                        const selectedMedecin = keepInitialSelection ? initialMedecin : 0;

                        fetch(`../public/getMedecin.php?service=${encodeURIComponent(serviceId)}`)
                            .then(r => {
                                if (!r.ok) throw new Error('HTTP ' + r.status);
                                return r.json();
                            })
                            .then(data => {
                                resetSelect(medecinSelect, data && data.medecins && data.medecins.length ? '-- Sélectionner un médecin --' : 'Aucun médecin pour ce service');
                                if (data && data.success && Array.isArray(data.medecins)) {
                                    for (const m of data.medecins) {
                                        const opt = document.createElement('option');
                                        opt.value = m.id;
                                        opt.textContent = m.pseudo;
                                        if (selectedMedecin && String(m.id) === String(selectedMedecin)) {
                                            opt.selected = true;
                                        }
                                        medecinSelect.appendChild(opt);
                                    }
                                }
                                // Mettre à jour les créneaux si date déjà choisie
                                updateCreneaux();
                            })
                            .catch(err => {
                                console.error('Erreur chargement médecins:', err);
                                resetSelect(medecinSelect, 'Erreur de chargement');
                            });
                    }

                    function onServiceChange() {
                        loadMotifsForService(false);
                        loadMedecinsForService(false);
                        const creneauSelect = document.getElementById('creneauSelect');
                        resetSelect(creneauSelect, '------ Choisir médecin et date -----');
                        if (creneauSelect) creneauSelect.disabled = true;
                    }

                    if (serviceSelect) serviceSelect.addEventListener('change', onServiceChange);
                    if (medecinSelect) medecinSelect.addEventListener('change', updateCreneaux);
                    if (dateInput) dateInput.addEventListener('change', updateCreneaux);

                    // Quand le modal s'ouvre, réinitialiser et préparer les créneaux
                    if (modalEl) {
                        modalEl.addEventListener('shown.bs.modal', function () {
                            // Initialiser motifs & médecins en fonction du service sélectionné
                            loadMotifsForService(true);
                            loadMedecinsForService(true);
                            updateCreneaux();
                        });
                    }
                });
                </script>

                <?php if (!empty($showReprintModalOnLoad) && !empty($id_patient)) { ?>
                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (!window.bootstrap) return;
                    const modalEl = document.getElementById('patientInfoModal');
                    if (!modalEl) return;

                    // Préparer les liens (cas auto-ouverture sans clic)
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
