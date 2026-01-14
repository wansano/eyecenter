<?php
require_once('../public/connect.php');
require_once('../public/fonction.php');
session_start();

function h($value) {
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Devise (fallback)
$devise = 'GNF';
try {
	$stDev = $bdd->query('SELECT devise FROM profil_entreprise LIMIT 1');
	$dev = $stDev ? $stDev->fetchColumn() : null;
	if (is_string($dev) && trim($dev) !== '') {
		$devise = trim($dev);
	}
} catch (Throwable $e) {
	// noop
}

// Déterminer le type "Vente lunettes" (même logique que ventelunette)
$lunetteTypeId = 0;
try {
	$stmt = $bdd->prepare("SELECT id_type FROM traitements WHERE LOWER(nom_type) LIKE '%lunet%' OR LOWER(nom_type) LIKE '%monture%' ORDER BY id_type ASC LIMIT 1");
	$stmt->execute();
	$lunetteTypeId = (int)($stmt->fetchColumn() ?: 0);
} catch (Throwable $e) {
	$lunetteTypeId = 0;
}
if ($lunetteTypeId <= 0) {
	try {
		$stmt = $bdd->prepare("SELECT id_type FROM traitements WHERE LOWER(nom_type) LIKE '%vente%' ORDER BY id_type ASC LIMIT 1");
		$stmt->execute();
		$lunetteTypeId = (int)($stmt->fetchColumn() ?: 0);
	} catch (Throwable $e) {
		$lunetteTypeId = 0;
	}
}

$errors = 0;
$success = 0;
$successAffectation = 0;

// Options comptes (mode de règlement) pour la boutique
$comptesOptions = [];
try {
	$stC = $bdd->prepare('SELECT id_compte, nom_compte, types FROM comptes WHERE status = 1 AND compte_pour = ? AND defaut = 1');
	$stC->execute([2]);
	while ($c = $stC->fetch(PDO::FETCH_ASSOC)) {
		$label = (string)($c['nom_compte'] ?? '');
		if (trim($label) === '') {
			$label = (string)($c['types'] ?? '');
		}
		$comptesOptions[] = [
			'id' => (int)($c['id_compte'] ?? 0),
			'label' => $label,
		];
	}
} catch (Throwable $e) {
	$comptesOptions = [];
}

// Flash succès
if (!empty($_SESSION['flash_complete_vente_lunette_affectation'])) {
	$successAffectation = (int)$_SESSION['flash_complete_vente_lunette_affectation'];
	unset($_SESSION['flash_complete_vente_lunette_affectation']);
	$success = 1;
}

// Compléter un paiement (en incrémentant montant_paye sur le même enregistrement)
if (isset($_POST['complete_payment'])) {
	$idPaiement = isset($_POST['id_paiement']) ? (int)$_POST['id_paiement'] : 0;
	$newCompte = isset($_POST['compte']) ? (int)$_POST['compte'] : 0;
	$montantAjoutN = str_replace([' ', ','], '', (string)($_POST['montant_ajout'] ?? '0'));
	$montantAjout = (float)$montantAjoutN;

	if ($idPaiement <= 0 || $montantAjout <= 0 || $newCompte <= 0) {
		$errors = 1;
	} else {
		try {
			$bdd->beginTransaction();
			try {
				$stmt = $bdd->prepare(
					'SELECT p.id_paiement, p.code, p.id_affectation, p.montant, p.montant_paye, p.compte, p.patient,
						a.type AS type_traitement
					 FROM paiements p
					 LEFT JOIN affectations a ON a.id_affectation = p.id_affectation
					 WHERE p.id_paiement = ?
					 LIMIT 1
					 FOR UPDATE'
				);
				$stmt->execute([$idPaiement]);
				$row = $stmt->fetch(PDO::FETCH_ASSOC);
				if (!$row) {
					throw new Exception('Paiement introuvable.');
				}

				// Sécurité minimale: utilisateur connecté
				if (empty($_SESSION['auth'])) {
					throw new Exception('Session expirée. Veuillez vous reconnecter.');
				}

				// S'assurer que c'est bien une vente lunette
				if ($lunetteTypeId > 0 && (int)($row['type_traitement'] ?? 0) !== $lunetteTypeId) {
					throw new Exception('Cette affectation ne correspond pas à une vente de lunettes.');
				}

				$montantTotal = (float)($row['montant'] ?? 0);
				$montantPaye = (float)($row['montant_paye'] ?? 0);
				if ($montantTotal <= 0) {
					throw new Exception('Montant total invalide.');
				}
				if ($montantPaye < 0) {
					$montantPaye = 0;
				}

				$reste = $montantTotal - $montantPaye;
				if ($reste <= 0) {
					throw new Exception('Cette vente est déjà réglée.');
				}
				if ($montantAjout > $reste) {
					throw new Exception('Le montant saisi dépasse le reste à payer.');
				}

				$newPaye = $montantPaye + $montantAjout;
				$newSolde = $montantTotal - $newPaye;

				$oldCompte = (int)($row['compte'] ?? 0);
				if ($oldCompte <= 0) {
					$oldCompte = $newCompte;
				}

				// Verrouiller comptes si besoin (si le mode change, on transfère l'historique payé sur le nouveau compte)
				if ($newCompte !== $oldCompte) {
					$stAcc = $bdd->prepare('SELECT id_compte, debit FROM comptes WHERE id_compte IN (?, ?) FOR UPDATE');
					$stAcc->execute([$oldCompte, $newCompte]);
					$accRows = $stAcc->fetchAll(PDO::FETCH_ASSOC);
					$map = [];
					foreach ($accRows as $ar) {
						$map[(int)$ar['id_compte']] = (float)($ar['debit'] ?? 0);
					}
					if (!array_key_exists($oldCompte, $map) || !array_key_exists($newCompte, $map)) {
						throw new Exception('Compte(s) de règlement introuvable(s).');
					}

					// Transfert de l'ancien payé vers le nouveau compte
					$oldDebit = $map[$oldCompte] - $montantPaye;
					$newDebit = $map[$newCompte] + $montantPaye;
					$bdd->prepare('UPDATE comptes SET debit = ? WHERE id_compte = ?')->execute([$oldDebit, $oldCompte]);
					$bdd->prepare('UPDATE comptes SET debit = ? WHERE id_compte = ?')->execute([$newDebit, $newCompte]);
				}

				$hasSolde = false;
				if (function_exists('dbTableHasColumn')) {
					$hasSolde = dbTableHasColumn($bdd, 'paiements', 'solde');
				}

				if ($hasSolde) {
					$upd = $bdd->prepare('UPDATE paiements SET montant_paye = ?, solde = ?, compte = ? WHERE id_paiement = ?');
					$upd->execute([$newPaye, $newSolde, $newCompte, $idPaiement]);
				} else {
					$upd = $bdd->prepare('UPDATE paiements SET montant_paye = ?, compte = ? WHERE id_paiement = ?');
					$upd->execute([$newPaye, $newCompte, $idPaiement]);
				}

				// Créditer le compte du montant ajouté (sur le compte choisi)
				$stOne = $bdd->prepare('SELECT debit FROM comptes WHERE id_compte = ? FOR UPDATE');
				$stOne->execute([$newCompte]);
				$debit = $stOne->fetchColumn();
				if ($debit === false) {
					throw new Exception('Compte de règlement invalide.');
				}
				$bdd->prepare('UPDATE comptes SET debit = ? WHERE id_compte = ?')->execute([(float)$debit + $montantAjout, $newCompte]);

				// Si soldé, marquer l'affectation comme payée/traitée
				$idAff = (int)($row['id_affectation'] ?? 0);
				if ($idAff > 0 && $newSolde <= 0.00001) {
					$bdd->prepare('UPDATE affectations SET status = 4 WHERE id_affectation = ?')->execute([$idAff]);
				}
				// Garder cohérent avec l'affectation (reçu affiche type_paiement)
				if ($idAff > 0) {
					$bdd->prepare('UPDATE affectations SET type_paiement = ? WHERE id_affectation = ?')->execute([$newCompte, $idAff]);
				}

				$bdd->commit();

				$_SESSION['flash_complete_vente_lunette_affectation'] = (int)($row['id_affectation'] ?? 0);
				header('Location: venteslunettes_incompletes.php?open=1&affectation=' . (int)($row['id_affectation'] ?? 0));
				exit;
			} catch (Throwable $txe) {
				$bdd->rollBack();
				throw $txe;
			}
		} catch (Throwable $e) {
			error_log('[venteslunettes_incompletes] complete_payment: ' . $e->getMessage());
			$errors = 2;
			$_SESSION['flash_complete_vente_lunette_error'] = $e->getMessage();
			header('Location: venteslunettes_incompletes.php');
			exit;
		}
	}
}

$flashError = '';
if (!empty($_SESSION['flash_complete_vente_lunette_error'])) {
	$flashError = (string)$_SESSION['flash_complete_vente_lunette_error'];
	unset($_SESSION['flash_complete_vente_lunette_error']);
}

// Charger la liste des ventes incomplètes
$rows = [];
if ($lunetteTypeId > 0) {
	$hasVpAff = true;
	if (function_exists('dbTableHasColumn')) {
		$hasVpAff = dbTableHasColumn($bdd, 'ventes_produits', 'id_affectation');
	}

	$stmt = $bdd->prepare(
		(
			$hasVpAff
				? 'SELECT p.id_paiement, p.code, p.id_affectation, p.montant, p.montant_paye, p.compte, p.patient, p.datepaiement,
						pa.nom_patient,
						a.date AS date_aff,
						vp.id_vente,
						m.code_monture,
						ma.marque AS marque_nom,
						l.lentille AS lentille_nom,
						vp.prix_monture AS vente_prix_monture,
						vp.prix_verre AS vente_prix_verre,
						COALESCE(c.nom_compte, c.types) AS compte_label
					FROM paiements p
					INNER JOIN affectations a ON a.id_affectation = p.id_affectation
					LEFT JOIN patients pa ON pa.id_patient = p.patient
					LEFT JOIN ventes_produits vp ON vp.id_affectation = p.id_affectation
					LEFT JOIN montures m ON m.id_monture = vp.id_monture
					LEFT JOIN marques ma ON ma.id_marque = m.id_marque
					LEFT JOIN lentilles l ON l.id_lentille = vp.id_lentille
					LEFT JOIN comptes c ON c.id_compte = p.compte
					WHERE a.type = ? AND COALESCE(p.montant_paye, 0) < COALESCE(p.montant, 0)
					ORDER BY p.id_paiement DESC'
				: 'SELECT p.id_paiement, p.code, p.id_affectation, p.montant, p.montant_paye, p.compte, p.patient, p.datepaiement,
						pa.nom_patient,
						a.date AS date_aff,
						COALESCE(c.nom_compte, c.types) AS compte_label
					FROM paiements p
					INNER JOIN affectations a ON a.id_affectation = p.id_affectation
					LEFT JOIN patients pa ON pa.id_patient = p.patient
					LEFT JOIN comptes c ON c.id_compte = p.compte
					WHERE a.type = ? AND COALESCE(p.montant_paye, 0) < COALESCE(p.montant, 0)
					ORDER BY p.id_paiement DESC'
		)
	);
	$stmt->execute([$lunetteTypeId]);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

include('../public/header.php');
?>

<body>
	<section class="body">
		<?php require('../public/navbarmenu.php'); ?>

		<div class="inner-wrapper">
			<section role="main" class="content-body">
				<header class="page-header">
					<h2>Ventes lunettes incomplètes</h2>
				</header>

				<div class="col-md-12">
					<section class="card">
						<div class="card-body">
							<div class="mb-3">
								<a class="btn btn-default" href="ventelunette.php">Retour vente lunettes</a>
							</div>

							<?php if ($lunetteTypeId <= 0): ?>
								<div class="alert alert-danger">
									Impossible de déterminer le type de traitement "vente lunettes".
								</div>
							<?php endif; ?>

							<?php if ($flashError !== ''): ?>
								<div class="alert alert-danger">
									<?php echo h($flashError); ?>
								</div>
							<?php endif; ?>

							<?php
								$affForReceipt = 0;
								if (!empty($_GET['affectation'])) {
									$affForReceipt = (int)$_GET['affectation'];
								} elseif ($successAffectation > 0) {
									$affForReceipt = $successAffectation;
								}
							?>

							<?php if ($success): ?>
								<div class="alert alert-success">
									<strong>Paiement complété avec succès.</strong>
									<?php if ($affForReceipt > 0): ?>
										<div class="mt-2">
											<button type="button" class="btn btn-success btn-sm" id="btnImprimerRecu" data-affectation="<?php echo (int)$affForReceipt; ?>">
												<i class="fa fa-file-pdf-o"></i> Imprimer le reçu
											</button>
										</div>
									<?php endif; ?>
								</div>
							<?php endif; ?>

							<div class="table-responsive">
								<table class="table table-bordered table-striped mb-0" id="datatable-default">
									<thead>
										<tr>
											<th>Affectation</th>
											<th>Monture</th>
											<th>Marque</th>
											<th>Lentille</th>
											<th>Patient</th>
											<th>Date</th>
											<th>Montant</th>
											<th>Payé</th>
											<th>Reste</th>
											<th>Action</th>
										</tr>
									</thead>
									<tbody>
									<?php foreach ($rows as $r):
										$montant = (float)($r['montant'] ?? 0);
										$paye = (float)($r['montant_paye'] ?? 0);
										$reste = max(0, $montant - $paye);
										$codeMonture = (string)($r['code_monture'] ?? '');
										$marqueNom = (string)($r['marque_nom'] ?? '');
										$lentilleNom = (string)($r['lentille_nom'] ?? '');
										$patientNom = (string)($r['nom_patient'] ?? '');
										$dateAff = (string)($r['datepaiement'] ?? $r['date_aff'] ?? '');
										$ventePrixMonture = (float)($r['vente_prix_monture'] ?? 0);
										$ventePrixVerre = (float)($r['vente_prix_verre'] ?? 0);
										$codePaiement = (string)($r['code'] ?? '');
										$compteId = (int)($r['compte'] ?? 0);
										$compteLabel = (string)($r['compte_label'] ?? '');
									?>
										<tr>
											<td><?php echo 'EC_AFF' . (int)($r['id_affectation'] ?? 0); ?></td>
											<td><?php echo h($codeMonture !== '' ? $codeMonture : '-'); ?></td>
											<td><?php echo h($marqueNom !== '' ? $marqueNom : '-'); ?></td>
											<td><?php echo h($lentilleNom !== '' ? $lentilleNom : '-'); ?></td>
											<td><?php echo h($patientNom); ?></td>
											<td><?php echo h($dateAff); ?></td>
											<td><?php echo h(number_format($montant, 0, ',', ' ')) . ' ' . h($devise); ?></td>
											<td><?php echo h(number_format($paye, 0, ',', ' ')) . ' ' . h($devise); ?></td>
											<td><span class="fw-bold text-danger"><?php echo h(number_format($reste, 0, ',', ' ')) . ' ' . h($devise); ?></span></td>
											<td>
												<button
													class="btn btn-primary btn-sm btnCompleter"
													data-idpaiement="<?php echo (int)$r['id_paiement']; ?>"
													data-affectation="<?php echo (int)($r['id_affectation'] ?? 0); ?>"
													data-montant="<?php echo h($montant); ?>"
													data-paye="<?php echo h($paye); ?>"
													data-reste="<?php echo h($reste); ?>"
													data-monture="<?php echo h($codeMonture); ?>"
													data-marque="<?php echo h($marqueNom); ?>"
													data-lentille="<?php echo h($lentilleNom); ?>"
													data-prixmonture="<?php echo h($ventePrixMonture); ?>"
													data-prixverre="<?php echo h($ventePrixVerre); ?>"
													data-codepaiement="<?php echo h($codePaiement); ?>"
													data-compteid="<?php echo (int)$compteId; ?>"
													data-comptelabel="<?php echo h($compteLabel); ?>"
												>
													Compléter
												</button>
											</td>
										</tr>
									<?php endforeach; ?>
									</tbody>
								</table>
							</div>

							<!-- Modal compléter paiement -->
							<div class="modal fade" id="completerPaiementModal" tabindex="-1" aria-hidden="true">
								<div class="modal-dialog modal-lg modal-dialog-scrollable">
									<div class="modal-content">
										<div class="modal-header">
											<h5 class="modal-title">Compléter le paiement</h5>
											<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
										</div>
										<div class="modal-body">
											<div class="table-responsive mb-3">
												<table class="table table-bordered mb-0">
													<tbody>
														<tr><th style="width:35%">Affectation</th><td id="lblAffectation"></td></tr>
														<tr><th>Code paiement</th><td id="lblCodePaiement"></td></tr>
														<tr><th>Mode actuel</th><td id="lblCompte"></td></tr>
														<tr><th>Monture</th><td id="lblMonture"></td></tr>
														<tr><th>Marque</th><td id="lblMarque"></td></tr>
														<tr><th>Lentille</th><td id="lblLentille"></td></tr>
														<tr><th>Prix monture</th><td id="lblPrixMonture"></td></tr>
														<tr><th>Prix lentille</th><td id="lblPrixVerre"></td></tr>
														<tr><th>Montant total</th><td id="lblMontant"></td></tr>
														<tr><th>Déjà payé</th><td id="lblPaye"></td></tr>
														<tr><th>Reste à payer</th><td class="fw-bold text-danger" id="lblReste"></td></tr>
													</tbody>
												</table>
											</div>

											<form method="POST" action="venteslunettes_incompletes.php" novalidate>
												<input type="hidden" name="complete_payment" value="1">
												<input type="hidden" name="id_paiement" id="inpIdPaiement" value="">
												<div class="row mb-3">
													<div class="col-md-4 mb-3">
														<label class="form-label">Montant à ajouter (<?php echo h($devise); ?>)</label>
														<input type="text" class="form-control" name="montant_ajout" id="inpMontantAjout" placeholder="Montant à payer" required>
														<div class="form-text">Par défaut: le reste à payer.</div>
													</div>
													<div class="col-md-4 mb-3">
														<label class="form-label">Mode de règlement</label>
														<select class="form-control" name="compte" id="inpCompte" required>
															<option value="">--- Choisir ---</option>
															<?php foreach ($comptesOptions as $co): ?>
																<option value="<?php echo (int)$co['id']; ?>"><?php echo h($co['label']); ?></option>
															<?php endforeach; ?>
														</select>
													</div>
												</div>
												<div class="text-end">
													<button type="submit" class="btn btn-primary">Valider le paiement</button>
												</div>
											</form>
										</div>
									</div>
								</div>
							</div>

							<?php if ($success && $affForReceipt > 0): ?>
							<!-- Modal impression reçu -->
							<div class="modal fade" id="recuPaiementModal" tabindex="-1" aria-hidden="true">
								<div class="modal-dialog modal-xl modal-dialog-scrollable">
									<div class="modal-content">
										<div class="modal-header">
											<h5 class="modal-title">Reçu de paiement</h5>
											<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
										</div>
										<div class="modal-body" style="height:75vh;">
											<iframe id="recuIframe" src="../caisse/imprimer_recu.php?affectation=<?php echo (int)$affForReceipt; ?>" style="width:100%;height:100%;border:0;"></iframe>
										</div>
									</div>
								</div>
							</div>
							<?php endif; ?>

						</div>
					</section>
				</div>
			</section>
		</div>
	</section>

	<script>
		function formatNumber(val) {
			try { return (Math.round(val)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' '); } catch (e) { return String(val); }
		}

		document.addEventListener('DOMContentLoaded', function () {
			// Bouton imprimer reçu
			var btnPrint = document.getElementById('btnImprimerRecu');
			if (btnPrint) {
				btnPrint.addEventListener('click', function () {
					try {
						if (!window.bootstrap) return;
						var el = document.getElementById('recuPaiementModal');
						if (!el) return;
						var modal = window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el);
						modal.show();
					} catch (e) {}
				});
			}

			// Ouvrir automatiquement le modal impression après redirect si demandé
			var shouldOpen = <?php echo (isset($_GET['open']) && (string)$_GET['open'] === '1' && $success && $affForReceipt > 0) ? 'true' : 'false'; ?>;
			if (shouldOpen) {
				try {
					if (window.bootstrap) {
						var el = document.getElementById('recuPaiementModal');
						if (el) {
							var m = window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el);
							m.show();
						}
					}
				} catch (e) {}
			}

			// Format input montant
			var montantInput = document.getElementById('inpMontantAjout');
			if (montantInput) {
				montantInput.addEventListener('input', function () {
					let selectionStart = this.selectionStart;
					let oldLength = this.value.length;
					let value = this.value.replace(/\s/g, '').replace(/\D/g, '');
					if (!value) { this.value = ''; return; }
					let formatted = value.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
					this.value = formatted;
					let newLength = formatted.length;
					let diff = newLength - oldLength;
					try { this.setSelectionRange(selectionStart + diff, selectionStart + diff); } catch (e) {}
				});
			}

			// Ouvrir modal compléter
			document.querySelectorAll('.btnCompleter').forEach(function (btn) {
				btn.addEventListener('click', function () {
					try {
						var idPaiement = this.getAttribute('data-idpaiement') || '';
						var aff = this.getAttribute('data-affectation') || '';
						var montant = parseFloat(this.getAttribute('data-montant') || '0') || 0;
						var paye = parseFloat(this.getAttribute('data-paye') || '0') || 0;
						var reste = parseFloat(this.getAttribute('data-reste') || '0') || 0;
						var codePaiement = this.getAttribute('data-codepaiement') || '';
						var compteLabel = this.getAttribute('data-comptelabel') || '';
						var compteId = parseInt(this.getAttribute('data-compteid') || '0', 10) || 0;
						var mCode = this.getAttribute('data-monture') || '';
						var mMarque = this.getAttribute('data-marque') || '';
						var mLentille = this.getAttribute('data-lentille') || '';
						var pMonture = parseFloat(this.getAttribute('data-prixmonture') || '0') || 0;
						var pVerre = parseFloat(this.getAttribute('data-prixverre') || '0') || 0;

						document.getElementById('lblAffectation').textContent = 'EC_AFF' + aff;
						document.getElementById('lblCodePaiement').textContent = codePaiement ? codePaiement : '-';
						document.getElementById('lblCompte').textContent = compteLabel ? compteLabel : '-';
						document.getElementById('lblMonture').textContent = mCode ? mCode : '-';
						document.getElementById('lblMarque').textContent = mMarque ? mMarque : '-';
						document.getElementById('lblLentille').textContent = mLentille ? mLentille : '-';
						document.getElementById('lblPrixMonture').textContent = formatNumber(pMonture) + ' <?php echo h($devise); ?>';
						document.getElementById('lblPrixVerre').textContent = formatNumber(pVerre) + ' <?php echo h($devise); ?>';
						document.getElementById('lblMontant').textContent = formatNumber(montant) + ' <?php echo h($devise); ?>';
						document.getElementById('lblPaye').textContent = formatNumber(paye) + ' <?php echo h($devise); ?>';
						document.getElementById('lblReste').textContent = formatNumber(reste) + ' <?php echo h($devise); ?>';
						document.getElementById('inpIdPaiement').value = idPaiement;
						document.getElementById('inpMontantAjout').value = formatNumber(reste);
						var compteSelect = document.getElementById('inpCompte');
						if (compteSelect) {
							if (compteId > 0) {
								compteSelect.value = String(compteId);
							} else {
								// Si on ne connait pas, choisir la première option valide
								for (var i = 0; i < compteSelect.options.length; i++) {
									if (compteSelect.options[i].value) { compteSelect.selectedIndex = i; break; }
								}
							}
						}

						if (!window.bootstrap) return;
						var el = document.getElementById('completerPaiementModal');
						var modal = window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el);
						modal.show();
					} catch (e) {}
				});
			});
		});
	</script>

	<?php include('../public/footer.php'); ?>
