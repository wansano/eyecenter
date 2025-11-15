<?php
include('../public/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

/**
 * Calcule l'âge en années à partir d'une date de naissance
 * @param string $dateNaissance Date de naissance au format Y-m-d
 * @return int Âge en années
 */

$errors = 0;

// Traitement des actions
try {
        if (isset($_POST['accepter']) && isset($_POST['traitement'])) {
            // Récupérer et valider les entrées
            $affectationId = (int) $_POST['accepter'];
            $traitement = (int) $_POST['traitement'];

            // Déterminer la page cible via operation()
            $traitementModel = operation($traitement);
            switch ($traitementModel) {
                case "0":
                    $actionLink = 'consultation.php';
                    break;
                case "1":
                    $actionLink = 'biometrie-chirurgie.php';
                    break;
                case "4":
                    $actionLink = 'controle.php';
                    break;
                case "3":
                    $actionLink = 'examen.php';
                    break;
                case "6":
                    $actionLink = 'rapportement.php';
                    break;
                default:
                    $actionLink = 'consultation.php';
            }

            // Mettre à jour le statut de l'affectation
            if ($traitementModel == 1) {
                $stmt = $bdd->prepare('UPDATE affectations SET status = 7 WHERE id_affectation = ?');
                $stmt->execute([$affectationId]);
                $errors = 2;
            } else {
                $stmt = $bdd->prepare('UPDATE affectations SET status = 2 WHERE id_affectation = ?');
                $stmt->execute([$affectationId]);
                $errors = 2;
            }

            // Redirection normale vers la page de traitement
            header('Location: ' . $actionLink . '?affectation=' . $affectationId);
            exit;
        }

        if (isset($_POST['remboursement'])) {
        $stmt = $bdd->prepare('UPDATE affectations SET status = 99 WHERE id_affectation = ?');
        $stmt->execute([$_POST['remboursement']]);
        $errors = 7;
        }
        
} catch (PDOException $e) {
    error_log("Erreur lors de la mise à jour du statut : " . $e->getMessage());
    $errors = 1;
}

include('../PUBLIC/header.php');
	?>

    <script>
			setTimeout(function() {
				location.reload();
			}, 90000); // Actualisation toutes les 90 secondes
		</script>

	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Liste des patients en salle pour un traitement</h2>
					</header>

					<!-- start: page -->
					<div class="col-md-12">
						<div class="row">
							<div class="col">
								<section class="card">
									<div class="card-body">
									<?php 
                                        if ($errors==7) {
                                        echo '
                                            <div class="alert alert-warning">
                                            <li><strong>Annuler !</strong>
                                            <br>Procedure de rembourssement engagé merci de rediriger le patient vers la comptabilité.</li>
                                            </div>
                                            '; }
									?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">

											<thead>
												<tr>
                                    <th>AFFECTE LE</th>
                                    <th>DOSSIER</th>
                                    <th>PATIENT</th>
                                    <th>ADRESSE</th>
                                    <th>AGE</th>
                                    <th>EXAMEN</th>
                                    <th>ACTION</th>
                                </tr>
                              </thead>
                              <tbody> 
                      <?php
                          try {
                              $stmt = $bdd->prepare('SELECT a.*, p.age as patient_age, t.operation as traitement_operation
                                FROM affectations a JOIN patients p ON a.id_patient = p.id_patient
                                JOIN traitements t ON a.type = t.id_type WHERE t.id_organigramme IN (1) AND a.status IN (1,2,7)
                                ORDER BY a.id_affectation');
                              $stmt->execute();
                              
                              while ($donnees1 = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                  // Récupération des données nécessaires
                                  $patientInfo = getPatientInfo($donnees1['id_patient']);
                                  $status = $donnees1['status'];
                                  $affectation = $donnees1['id_affectation'];
                                  if (!$patientInfo) {
                                      continue; // Si aucune info patient, on passe à l'itération suivante
                                  }
                                  $traitement = $donnees1['type'];
                                  $service = $donnees1['id_service'];
                                  $age = calculerAge($donnees1['patient_age']);
                                  $rdv = $donnees1['id_rdv'];

                                  if ($rdv === 0 && in_array($status, [1, 2, 7], true)) {
                                  echo' <tr>
                                      <td>'.htmlspecialchars($donnees1['date']).'</td>
                                      <td>'.htmlspecialchars($donnees1['id_patient']).'</td>
                                      <td>'.htmlspecialchars($patientInfo['nom_patient'] ?: 'Non renseigné').'</td>
                                      <td>'.htmlspecialchars(adress($patientInfo['adresse']) ?: $patientInfo['adresse']).'</td>
                                      <td>'.htmlspecialchars($age).' ans</td>
                                      <td>'.htmlspecialchars(model($traitement)).'</td>
                                      <td>';
                                
                                  if ($status==1) {
                                      echo '
                                      <div class="d-flex gap-1">
                                        <form action="'.htmlspecialchars($_SERVER['PHP_SELF']).'" method="post">
                                            <input type="hidden" name="accepter" value="'.$affectation.'">
                                            <input type="hidden" name="traitement" value="'.$traitement.'">
                                            <button type="submit" class="btn btn-sm btn-success"><i class="fa-regular fa-circle-check"></i> traiter</button>
                                        </form>
                                        <form action="'.htmlspecialchars($_SERVER['PHP_SELF']).'" method="post">
                                          <input type="hidden" name="remboursement" value="'.$affectation.'">
                                          <button type="submit" class="btn btn-sm btn-danger">refuser</button>
                                        </form>
                                      </div>';
                                  }

                                  if ($status==2) {
                                      echo '
                                      <div class="d-flex gap-1">
                                        <form action="'.htmlspecialchars($_SERVER['PHP_SELF']).'" method="post">
                                            <input type="hidden" name="accepter" value="'.$affectation.'">
                                            <input type="hidden" name="traitement" value="'.$traitement.'">
                                            <button type="submit" class="btn btn-sm btn-success"><i class="fa-regular fa-circle-check"></i> traiter</button>
                                        </form>
                                        <form action="'.htmlspecialchars($_SERVER['PHP_SELF']).'" method="post">
                                          <input type="hidden" name="remboursement" value="'.$affectation.'">
                                          <button type="submit" class="btn btn-sm btn-danger">référer</button>
                                        </form>
                                      </div>';
                                  }
                                }
                            echo '</td>
                                </tr>';
                              }
                          } catch (PDOException $e) {
                              error_log("Erreur lors de la récupération des affectations : " . $e->getMessage());
                              echo '<tr><td colspan="7">Une erreur est survenue lors du chargement des données.</td></tr>';
                          }
                      ?>
											</tbody>
										</table>
									</div>
								</section>
							</div>
						</div>
				    </section>
			    </div>
        </section>
    </div>
    <?php include('../public/footer.php'); ?>
</body>
</html>