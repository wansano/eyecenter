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
            $affectationRefus = (int) $_POST['remboursement'];
            $motifRefus = isset($_POST['motif_refus']) ? trim($_POST['motif_refus']) : '';

            try {
                // Démarrer une transaction pour garantir cohérence
                $bdd->beginTransaction();

                // Récupérer infos nécessaires de l'affectation
                $stmtInfo = $bdd->prepare('SELECT id_patient, type FROM affectations WHERE id_affectation = ? LIMIT 1');
                $stmtInfo->execute([$affectationRefus]);
                $affInfo = $stmtInfo->fetch(PDO::FETCH_ASSOC);

                if (!$affInfo) {
                    throw new Exception('Affectation introuvable pour remboursement.');
                }

                $patientId = (int) $affInfo['id_patient'];
                $typeId = (int) $affInfo['type'];

                // Mettre à jour le statut de l'affectation (99 = refus / remboursement)
                $stmtUpd = $bdd->prepare('UPDATE affectations SET status = 99 WHERE id_affectation = ?');
                $stmtUpd->execute([$affectationRefus]);

                // Harmoniser l'identifiant utilisateur : ailleurs c'est $_SESSION['auth']
                $payeur = 0;
                if (isset($_SESSION['auth'])) {
                    $payeur = (int) $_SESSION['auth'];
                } elseif (isset($_SESSION['id_user'])) {
                    $payeur = (int) $_SESSION['id_user'];
                }

                if ($motifRefus === '') {
                    throw new Exception('Motif obligatoire.');
                }

                // Schéma BDD: montant_paye est NOT NULL => au refus on initialise à 0.
                // Les autres champs restent NULL (autorisés) et seront complétés en comptabilité si besoin.
                $stmtInsert = $bdd->prepare('INSERT INTO remboursements (paye_a, id_affectation, patient, types, montant_paye, montant_remboursse, compte, motif, date_ajout, refererpar) VALUES (?,?,?,?,0,NULL,NULL,?,CURDATE(),?)');
                $payeA = null;
                $stmtInsert->execute([$payeA, $affectationRefus, $patientId, $typeId, $motifRefus, $payeur]);

                $bdd->commit();
                $errors = 8; // Code succès insertion remboursement
            } catch (Throwable $ex) {
                if ($bdd->inTransaction()) {
                    $bdd->rollBack();
                }
                error_log('Erreur refus/remboursement ophtalmologie: ' . $ex->getMessage());
                $errors = 7; // Conserver ancien code d'alerte problème
            }
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
                                                <strong>Attention !</strong><br>Une erreur est survenue ou remboursement engagé partiellement.
                                            </div>';
                                        }
                                        if ($errors==8) {
                                            echo '
                                            <div class="alert alert-success">
                                                <strong>Refus enregistré.</strong><br>Motif sauvegardé et procédure de remboursement lancée.
                                            </div>';
                                        }
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
                                
                                  if ($status==1 || $status==2) {
                                      echo '
                                      <div class="d-flex gap-1">
                                        <form action="'.htmlspecialchars($_SERVER['PHP_SELF']).'" method="post">
                                            <input type="hidden" name="accepter" value="'.$affectation.'">
                                            <input type="hidden" name="traitement" value="'.$traitement.'">
                                            <button type="submit" class="btn btn-sm btn-success"><i class="fa-regular fa-circle-check"></i> traiter</button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-danger open-refus-modal" data-id="'.$affectation.'">refuser</button>
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
        <!-- Modal Motif de refus -->
        <div class="modal fade" id="refusModal" tabindex="-1" role="dialog" aria-labelledby="refusModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <div class="modal-body">
                            <input type="hidden" name="remboursement" id="remboursementId" value="">
                            <div class="form-group">
                                <label for="motifRefus">Veuillez saisir le motif</label>
                                <textarea class="form-control" id="motifRefus" name="motif_refus" rows="4" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-danger">Confirmer le refus</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var buttons = document.querySelectorAll('.open-refus-modal');
                buttons.forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var id = this.getAttribute('data-id');
                        var input = document.getElementById('remboursementId');
                        if (input) { input.value = id; }
                        if (window.jQuery && $('#refusModal').modal) {
                            $('#refusModal').modal('show');
                        } else {
                            var modal = document.getElementById('refusModal');
                            if (modal) { modal.style.display = 'block'; }
                        }
                    });
                });
            });
        </script>

        <?php include('../public/footer.php'); ?>
</body>
</html>