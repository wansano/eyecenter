<?php
include('../public/connect.php');
require('../PUBLIC/fonction.php');
session_start();

include('../public/header.php'); 

$rendezvous = isset($_GET['rdv']) ? (int) $_GET['rdv'] : 0;
$errors = 0;

if (isset($_POST['traiter'])) {
	try {
		$rdvPost = (int) $_POST['traiter'];
		// récupérer l'affectation liée au rdv (fonction existante)
		$affectation = getAffectationIdByRdv($bdd, $rdvPost ?: $rendezvous);

		if (empty($affectation)) {
			throw new Exception('Affectation introuvable pour le rendez-vous ' . $rdvPost);
		}

		// Récupérer le type de traitement
		$stmt = $bdd->prepare('SELECT type FROM affectations WHERE id_affectation = ?');
		$stmt->execute([$affectation]);
		$traitement = $stmt->fetchColumn();

		// Mettre à jour le statut pour indiquer prise en charge
		$upd = $bdd->prepare('UPDATE affectations SET status = 2 WHERE id_affectation = ?');
		$upd->execute([$affectation]);

		// Déterminer la page cible via operation()
		$traitementModel = operation((int) $traitement);
		$map = [
			0 => 'consultation.php',
			1 => 'chirurgie.php',
			3 => 'examen.php',
			4 => 'controle.php'
		];
		$actionLink = isset($map[$traitementModel]) ? $map[$traitementModel] : 'consultation.php';

		// Si le traitement nécessite un consentement, ouvrir l'impression dans un nouvel onglet
		if (function_exists('consentement') && consentement((int) $traitement) == 1) {
			echo '<script>window.onload=function(){ window.open("imprimer_consentement.php?affectation=' . $affectation . '", "_blank"); window.location.href="' . $actionLink . '?affectation=' . $affectation . '"; };</script>';
			exit();
		}

		// Redirection vers la page de traitement
		header('Location: ' . $actionLink . '?affectation=' . $affectation);
		exit();

	} catch (Exception $e) {
		error_log('validationRDV error: ' . $e->getMessage());
		// message d'erreur utilisateur minimal
		echo '<div class="alert alert-danger">Erreur lors de la préparation du traitement. Voir logs.</div>';
	}
}

?>

<body>
    <section class="body">

        <?php require('../PUBLIC/navbarmenu.php'); ?>

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
                        }
                        echo '
                        <div class="col-md-12">
							<section class="card">
								<div class="card-body">';
								if ($errors == 2 && $id_patient){ echo '
									<div class="alert alert-success">
										<strong>Succès</strong> <br/>  
										<li>Dossier patient transmis à la caisse pour paiement. Merci de rediriger le patient vers la caisse.</li>
									</div>';
								 } echo'
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
                                        <div class="col-md-3">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Rendez-vous avec le medecin</label>
												<input type="text" class="form-control" id="formGroupExampleInput" value="'.traitant($id_medecin).'" disabled>
											</div>
										</div>';
										if( $status === 2) { 
											echo '<div class="col-md-3 ">
											<form action="'.htmlspecialchars($_SERVER['PHP_SELF']).'?rdv='.$rendezvous.'" method="post">
                                            	<input type="hidden" name="traiter" value="'.$rendezvous.'">
                                            	<button class="btn btn-success text-center my-4" type="submit">procéder au traitement du patient</button>
                                            </form>
										</div>';
										 }'

                                        </div>
									</div>
							</section>
						</div>
					</div>';
                    ?>
                <!-- end: page -->
            </section>
        </div>
        <?php include('../PUBLIC/footer.php');?>
