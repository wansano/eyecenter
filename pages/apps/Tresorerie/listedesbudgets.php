<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();
$errors=0;

if (isset($_POST['activer'])) 
{	
	$reponse = $bdd->prepare('SELECT * FROM budgets WHERE id_budget=?');
    $reponse ->execute(array($_POST['activer']));
	while ( $donnesbudgets = $reponse->fetch()) 
	{ 	$date_fin = $donnesbudgets['date_fin'];
		$date_debut = $donnesbudgets['date_debut'];
		$date_du_jour = date('Y-m-d');

	if ($date_du_jour >= $date_debut && $date_du_jour <= $date_fin) {
		$reponse = $bdd->prepare('UPDATE budgets SET status=1 WHERE id_budget=?');
		$reponse ->execute(array($_POST['activer']));
		$errors=2;
	} 
	else { $errors=3; }

	}
}

if (isset($_POST['supprimer'])) 
{
    $reponse = $bdd->prepare('UPDATE budgets SET status=3 WHERE id_budget=?');
    $reponse ->execute(array($_POST['supprimer']));
    $errors=4;
} 


include('../PUBLIC/header.php');
?>

	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Liste des budgets</h2>
					</header>

					<!-- start: page -->
					<div class="col-md-12">
						<div class="row">
							<div class="col">
								<section class="card">
									<div class="card-body">
										<div class="mb-3 text-end">
											<button type="button" class="btn btn-primary" id="openAddBudgetBtn">Ajouter un budget</button>
										</div>
										<?php 
                                            if ($errors==2) {
                                            echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Le budget à été activé avec succès, il est possible de l\'utiliser à partir de l\'instant.</li>
                                                </div>
                                                '; }

											if ($errors==3) {
												echo '
													<div class="alert alert-danger">
													<li><strong>Erreur !</strong>
													<br>Il n\'est pas possible d\'activer le budget car la date du jour n\'est pas inclus entre la date de debut et fin de l\'utilisation prevu pour ce budget.</li>
													</div>
													'; }

                                            if ($errors==4) {
                                                echo '
                                                <div class="alert alert-warning">
                                                <li><strong>Succès !</strong>
                                                <br>Ce budget à été archiver avec succès.</li>
                                                </div>
                                                '; }
                                        ?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
                                                    <th>N°</th>
                                                    <th>BUDGET</th>
                                                    <th>DATE DEBUT</th>
                                                    <th>DATE FIN</th>
                                                    <th>INITIAL</th>
                                                    <th>UTILISÉ</th>
                                                    <th>RESTANT</th>
                                                    <th>TYPE</th>
                                                    <th>RESPONSABLE</th>
													<th>ACTION</th>
												</tr>
											</thead>
											<tbody>
											<?php
												$reponse1 = $bdd->prepare('SELECT * FROM budgets ORDER BY id_budget');
												$reponse1 -> execute();
												while ($donnees1 = $reponse1->fetch())
												{  
														$montantInitial = (float)($donnees1['montant_initial'] ?? 0);
														$montantUtilise = 0.0;
														if (isset($donnees1['montant_utilise'])) {
															$montantUtilise = (float)$donnees1['montant_utilise'];
														} elseif (isset($donnees1['montant_utilisé'])) {
															$montantUtilise = (float)$donnees1['montant_utilisé'];
														}
														$montantRestant = null;
														if (isset($donnees1['montant_restant'])) {
															$montantRestant = (float)$donnees1['montant_restant'];
														} elseif (isset($donnees1['solde'])) {
															$montantRestant = (float)$donnees1['solde'];
														} else {
															$montantRestant = $montantInitial - $montantUtilise;
														}

														$responsableAffiche = '';
														if (isset($donnees1['responsable']) && $donnees1['responsable'] !== null && $donnees1['responsable'] !== '') {
															$respRaw = (string)$donnees1['responsable'];
															if (ctype_digit($respRaw)) {
																$respPseudo = traitant((int)$respRaw);
																$responsableAffiche = $respPseudo !== '' ? $respPseudo : $respRaw;
															} else {
																$responsableAffiche = $respRaw;
															}
														}

														echo' <tr>
														<td>'.htmlspecialchars($donnees1['id_budget']).'</td>
														<td>'.htmlspecialchars($donnees1['nom_budget']).'</td>
														<td>'.htmlspecialchars($donnees1['date_debut']).'</td>
														<td>'.htmlspecialchars($donnees1['date_fin']).'</td>
														<td>'.number_format($montantInitial, 0, ',', ' ').' '.htmlspecialchars($devise).'</td>
														<td>'.number_format($montantUtilise, 0, ',', ' ').' '.htmlspecialchars($devise).'</td>
														<td>'.number_format($montantRestant, 0, ',', ' ').' '.htmlspecialchars($devise).'</td>
														<td>'.htmlspecialchars($donnees1['type_budget']).'</td>
														<td>'.htmlspecialchars($responsableAffiche).'</td>
														<td>';
													// Bouton modifier (sauf budgets archivés)
													if ((int)($donnees1['status'] ?? 0) !== 3) {
														echo '<button type="button" class="btn btn-sm btn-primary js-edit-budget" data-id-budget="'.htmlspecialchars($donnees1['id_budget']).'"><i class="fa fa-edit"></i> modifier</button> ';
													}

													if ($donnees1['status']==0) {
                                                        echo'
															<form action="listedesbudgets.php" method="post">
                                                        <input type="hidden" name="activer" value="'.$donnees1['id_budget'].'">
                                                        <button type="submit" class="btn btn-sm btn-success"><i class="fa fa-unlock-alt"></i> activé</button>
                                                        </form>
                                                        
															<form action="listedesbudgets.php" method="post">
                                                        <input type="hidden" name="supprimer" value="'.$donnees1['id_budget'].'">
                                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-times"></i> supprimer</button>
                                                        </form>
                                                        ';}
													echo'
													</td>';

												}
											?>
											</tbody>
										</table>
									</div>
								</section>
							</div>
						</div>
			    </div>

				<!-- Modal: Ajouter un budget -->
				<div class="modal fade" id="addBudgetModal" tabindex="-1" aria-hidden="true">
					<div class="modal-dialog modal-lg modal-dialog-centered">
						<div class="modal-content">
							<div class="modal-header">
								<h5 class="modal-title">Ajouter un budget</h5>
								<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
							</div>
							<div class="modal-body" id="addBudgetModalBody">
								<div class="text-muted">Chargement...</div>
							</div>
						</div>
					</div>
				</div>

				<script>
					(function () {
						const openBtn = document.getElementById('openAddBudgetBtn');
						const modalEl = document.getElementById('addBudgetModal');
						const modalBody = document.getElementById('addBudgetModalBody');
						const modalTitle = modalEl ? modalEl.querySelector('.modal-title') : null;
						let pendingReload = false;

						function getModalInstance() {
							if (!window.bootstrap || !window.bootstrap.Modal) return null;
							return window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
						}

						async function loadBudgetForm(url, title) {
							if (modalTitle) modalTitle.textContent = title || 'Budget';
							modalBody.innerHTML = '<div class="text-muted">Chargement...</div>';
							try {
								const res = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
								const html = await res.text();
								modalBody.innerHTML = html;
							} catch (e) {
								modalBody.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement du formulaire.</div>';
							}
						}

						function showErrors(errors) {
							const el = modalBody.querySelector('#addBudgetErrors') || modalBody.querySelector('#editBudgetErrors');
							if (!el) return;
							const okEl = modalBody.querySelector('#addBudgetSuccess') || modalBody.querySelector('#editBudgetSuccess');
							if (okEl) { okEl.style.display = 'none'; okEl.textContent = ''; }
							const items = (errors || []).map(msg => '<li>' + String(msg) + '</li>').join('');
							el.innerHTML = '<strong>Erreur(s)</strong><br><ul class="mb-0">' + items + '</ul>';
							el.style.display = '';
						}

						function showSuccess(message) {
							const el = modalBody.querySelector('#addBudgetSuccess') || modalBody.querySelector('#editBudgetSuccess');
							if (!el) return;
							const errEl = modalBody.querySelector('#addBudgetErrors') || modalBody.querySelector('#editBudgetErrors');
							if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
							el.innerHTML = '<strong>Succès</strong><br>' + String(message || 'Budget créé avec succès.');
							el.style.display = '';
						}

						async function submitBudget(form) {
							const submitBtn = modalBody.querySelector('#addBudgetSubmitBtn') || modalBody.querySelector('#editBudgetSubmitBtn') || form.querySelector('button[type="submit"]');
							if (submitBtn) submitBtn.disabled = true;
							const fd = new FormData(form);

							try {
								const actionUrl = form.getAttribute('action') || 'addbudget.php';
								const res = await fetch(actionUrl, {
									method: 'POST',
									body: fd,
									credentials: 'same-origin',
									headers: { 'X-Requested-With': 'XMLHttpRequest' }
								});

								let payload = null;
								try { payload = await res.json(); } catch (e) { payload = null; }

								if (res.ok && payload && payload.success) {
									pendingReload = true;
									showSuccess(payload.message || 'Budget enregistré avec succès.');
									const instance = getModalInstance();
									setTimeout(function () {
										if (instance) instance.hide();
									}, 900);
									return;
								}

								showErrors((payload && payload.errors) ? payload.errors : ['Une erreur est survenue.']);
							} catch (e) {
								showErrors(['Une erreur est survenue.']);
							} finally {
								if (submitBtn) submitBtn.disabled = false;
							}
						}

						if (openBtn && modalEl) {
							openBtn.addEventListener('click', async function () {
								await loadBudgetForm('addbudget.php', 'Ajouter un budget');
								const instance = getModalInstance();
								if (instance) instance.show();
							});
						}

						// Modifier un budget
						document.addEventListener('click', async function (e) {
							const btn = e.target && e.target.closest ? e.target.closest('.js-edit-budget') : null;
							if (!btn) return;
							const idBudget = btn.getAttribute('data-id-budget');
							if (!idBudget) return;
							await loadBudgetForm('editbudget.php?id_budget=' + encodeURIComponent(idBudget), 'Modifier un budget');
							const instance = getModalInstance();
							if (instance) instance.show();
						});

						modalEl.addEventListener('submit', function (e) {
							const form = e.target;
							if (form && (form.id === 'addBudgetForm' || form.id === 'editBudgetForm')) {
								e.preventDefault();
								submitBudget(form);
							}
						});

						modalEl.addEventListener('hidden.bs.modal', function () {
							if (pendingReload) {
								window.location.reload();
							}
						});
					})();
				</script>
            <?php include('../PUBLIC/footer.php');?>