<?php
include('../public/connect.php');
require('../public/fonction.php');
session_start();

include('../public/header.php'); 

// initialisation du flag d'état
$errors = 0;
$existe = 0;
if (isset($_POST['transmettre'])) {
    // on reçoit l'id du rdv (et non l'id patient) pour sécuriser l'opération
    $rdv_post = intval($_POST['transmettre']);

    // récupérer les informations du rdv
    $userDataPost = getRdvInfo($bdd, $rdv_post);
    $id_patient_post = getPatientIdByRdv($bdd, $rdv_post);

    if (!$userDataPost || !$id_patient_post) {
        // données manquantes
        $errors = 4;
    } else {
        $id_service_post = $userDataPost['id_service'];
        $motifrdv_post = $userDataPost['motif'];

        try {
            // insertion dans affectations
            $stmt = $bdd->prepare('INSERT INTO affectations (id_patient, id_service, type, id_rdv) VALUES (?, ?, ?, ?)');
            $stmt->execute([$id_patient_post, $id_service_post, $motifrdv_post, $rdv_post]);

            // mise à jour du rdv
            $stmt = $bdd->prepare('UPDATE dmd_rendez_vous SET status = 1 WHERE id_rdv = ?');
            $stmt->execute([$rdv_post]);

            $errors = 2; // succès

        } catch (PDOException $e) {
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
            window.open('imprimer_dossier.php?id_patient=".$id_patient_post."', '_blank');
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
                        $id_patient = getPatientIdByRdv($bdd, $_GET['rdv']);
                        $rendezvous = $_GET['rdv'];
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
								if ($errors==2) {
                                        echo '
                                            <div class="alert alert-success">
                                            <strong>Succès</strong> <br/>  
                                            <li>Dossier patient transmis à la caisse pour paiement. Merci de rediriger le patient vers la caisse.</li>
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
										<div class="col-md-4">
                                            <div class="d-flex gap-2">
                                                <a href="miseajourdv.php?rdv='.($rendezvous).'" class="btn btn-dark text-center my-4"> <i class="fa fa-edit"></i> modifier RdV</a>
                                            </div>
                                        </div>';
										} echo'
									</div>
							</section>
						</div>
					</div>';
                    ?>
                <!-- end: page -->
            </section>
        </div>
        <?php include('../public/footer.php');?>
