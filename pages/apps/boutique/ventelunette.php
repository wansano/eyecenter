<?php
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

$errors = 0;

// évite notices (utilisé plus bas)
$existe = 0;
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

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function resolveUserServiceId(PDO $bdd, int $userId): int {
	if ($userId <= 0) return 0;
	try {
		$stmt = $bdd->prepare('SELECT id_service FROM users WHERE id = ? LIMIT 1');
		$stmt->execute([$userId]);
		return (int)($stmt->fetchColumn() ?: 0);
	} catch (Throwable $e) {
		error_log('[ventelunette] resolveUserServiceId: ' . $e->getMessage());
		return 0;
	}
}

function resolveVenteLunetteTraitementId(PDO $bdd): int {
	try {
		$stmt = $bdd->prepare("SELECT id_type FROM traitements WHERE LOWER(nom_type) LIKE '%lunet%' OR LOWER(nom_type) LIKE '%monture%' ORDER BY id_type ASC LIMIT 1");
		$stmt->execute();
		$id = (int)($stmt->fetchColumn() ?: 0);
		if ($id > 0) return $id;
	} catch (Throwable $e) {
		error_log('[ventelunette] resolveVenteLunetteTraitementId(lunet/monture): ' . $e->getMessage());
	}

	try {
		$stmt = $bdd->prepare("SELECT id_type FROM traitements WHERE LOWER(nom_type) LIKE '%vente%' ORDER BY id_type ASC LIMIT 1");
		$stmt->execute();
		return (int)($stmt->fetchColumn() ?: 0);
	} catch (Throwable $e) {
		error_log('[ventelunette] resolveVenteLunetteTraitementId(vente): ' . $e->getMessage());
		return 0;
	}
}

function createAffectationForVente(PDO $bdd, int $patientId, int $userId): int {
	$patientId = (int)$patientId;
	$userId = (int)$userId;
	if ($patientId <= 0 || $userId <= 0) return 0;

	$idService = resolveUserServiceId($bdd, $userId);
	$idType = resolveVenteLunetteTraitementId($bdd);
	if ($idService <= 0 || $idType <= 0) return 0;

	try {
		$hasAffecterPar = true;
		if (function_exists('dbTableHasColumn')) {
			$hasAffecterPar = dbTableHasColumn($bdd, 'affectations', 'affecter_par');
		}
		if ($hasAffecterPar) {
			$stmt = $bdd->prepare('INSERT INTO affectations (id_patient, id_service, type, affecter_par, status, montant, taux, type_paiement) VALUES (?, ?, ?, ?, 1, 0, 0, 0)');
			$stmt->execute([$patientId, $idService, $idType, $userId]);
		} else {
			$stmt = $bdd->prepare('INSERT INTO affectations (id_patient, id_service, type, status, montant, taux, type_paiement) VALUES (?, ?, ?, 1, 0, 0, 0)');
			$stmt->execute([$patientId, $idService, $idType]);
		}
		return (int)$bdd->lastInsertId();
	} catch (Throwable $e) {
		error_log('[ventelunette] createAffectationForVente: ' . $e->getMessage());
		return 0;
	}
}

$openSaleModal = false;
$montureRow = null;
$montureNotFound = false;

// Flash succès (PRG) pour éviter la resoumission du formulaire
$flashSuccessAffectation = 0;
if (!empty($_SESSION['flash_vente_lunette_success_affectation'])) {
	$flashSuccessAffectation = (int)$_SESSION['flash_vente_lunette_success_affectation'];
	unset($_SESSION['flash_vente_lunette_success_affectation']);
	$errors = 6;
	$openSaleModal = true;
}

if (isset($_POST['recherche'])) {
    $productCode = isset($_POST['productcode']) ? trim((string)$_POST['productcode']) : '';
    if ($productCode === '') {
        $existe = 1;
    } else {
        $req1 = $bdd->prepare('SELECT 1 FROM montures WHERE code_monture = ? AND vendu = 0 LIMIT 1');
        $req1->execute([$productCode]);
        if ($req1->fetchColumn()) {
            $client = isset($_GET['client']) ? (int)$_GET['client'] : 0;
			$aff = isset($_GET['affectation']) ? (int)$_GET['affectation'] : 0;
			$url = 'ventelunette.php?client=' . $client . '&codeproduit=' . urlencode($productCode) . '&open=1';
			if ($aff > 0) {
				$url .= '&affectation=' . $aff;
			}
			header('Location: ' . $url);
            exit;
        }
        $existe = 1;
    }
}

// Chargement monture si code fourni (pour affichage dans le modal)
if (isset($_GET['codeproduit'])) {
    $codeMontureParam = trim((string)$_GET['codeproduit']);
    if ($codeMontureParam !== '') {
        $stmt = $bdd->prepare('SELECT m.*, ma.marque AS marque_nom FROM montures m LEFT JOIN marques ma ON ma.id_marque = m.id_marque WHERE m.code_monture = ? LIMIT 1');
        $stmt->execute([$codeMontureParam]);
        $montureRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($montureRow) {
            $openSaleModal = (isset($_GET['open']) && (string)$_GET['open'] === '1');
        } else {
            $montureNotFound = true;
        }
    }
}

if (isset($_POST['vendre'])) {
	$affectation = isset($_GET['affectation']) ? (int)$_GET['affectation'] : null;
	$patient = isset($_GET['client']) ? (int)$_GET['client'] : null;
	$codeMonture = isset($_GET['codeproduit']) ? trim((string)$_GET['codeproduit']) : null;
    $modePaiement = $_POST['estAssure'] ?? 0;
    $categorie = $_POST['categorie'] ?? null;
    $compte = $_POST['compte'] ?? null;
    $taux = $_POST['taux'] ?? 0;
    $acompte = 0;

	// Réouvrir le modal après soumission (sinon les messages restent invisibles après reload)
	$openSaleModal = true;
    
	$userId = isset($_SESSION['auth']) ? (int)$_SESSION['auth'] : 0;
	// L'utilisateur ne souhaite pas sélectionner de collaborateur:
	// on fixe une valeur par défaut compatible DB (non NULL) = utilisateur connecté.
	$collaborateur = $userId;

	if (!$patient || !$codeMonture || !$categorie || !$compte) {
        $errors = 1;
    } else {
		// Si aucune affectation n'a été fournie, on en crée une automatiquement
		if (empty($affectation) || (int)$affectation <= 0) {
			$newAff = createAffectationForVente($bdd, (int)$patient, $userId);
			if ($newAff <= 0) {
				$errors = 4;
			} else {
				$affectation = $newAff;
			}
		} else {
			// Si une affectation est fournie mais déjà payée, on en crée une nouvelle pour cette vente.
			if (paiementDejaEffectue($bdd, (int)$affectation)) {
				$newAff = createAffectationForVente($bdd, (int)$patient, $userId);
				if ($newAff <= 0) {
					$existe = 3;
				} else {
					$affectation = $newAff;
					$existe = 0;
				}
			}
		}

		if ($errors !== 0 || $existe === 3) {
			// stop
		} else {
        // Récupération des infos produit
		$reponse1 = $bdd->prepare('SELECT * FROM montures WHERE code_monture=? AND vendu = 0');
        $reponse1->execute([$codeMonture]);
        $donnees1 = $reponse1->fetch();
        if (!$donnees1) {
            $errors = 2;
        } else {
            $produit = $donnees1['id_monture'];
            $prixmonture = $donnees1['prix'];
			$idMarque = (int)($donnees1['id_marque'] ?? 0);
            // Récupération des infos catégorie
            $reponse2 = $bdd->prepare('SELECT * FROM lentilles WHERE id_lentille=?');
            $reponse2->execute([$categorie]);
            $donnees2 = $reponse2->fetch();
            if (!$donnees2) {
                $errors = 3;
            } else {
                $prixverre = $donnees2['prix_vente'];
                // Paiement
                if ($modePaiement == 0) {
                    // Paiement total
                    $acompte = $prixmonture + $prixverre;
                } else {
                    // Paiement partiel
                    $acompteN = str_replace([' ', ','], '', $_POST['acompte'] ?? '0');
                    $acompte = floatval($acompteN);
                }
                // Enregistrement vente
				$hasAffCol = true;
				if (function_exists('dbTableHasColumn')) {
					$hasAffCol = dbTableHasColumn($bdd, 'ventes_produits', 'id_affectation');
				}
				if ($hasAffCol) {
					$req = $bdd->prepare('INSERT INTO ventes_produits (id_affectation, id_monture, id_lentille, id_patient, id_caissier, prix_monture, prix_verre, compte, collaborateur) VALUES(?,?,?,?,?,?,?,?,?)');
					$req->execute([(int)$affectation, $produit, $categorie, $patient, $_SESSION['auth'], $prixmonture, $prixverre, $compte, $collaborateur]);
				} else {
					$req = $bdd->prepare('INSERT INTO ventes_produits (id_monture, id_lentille, id_patient, id_caissier, prix_monture, prix_verre, compte, collaborateur) VALUES(?,?,?,?,?,?,?,?)');
					$req->execute([$produit, $categorie, $patient, $_SESSION['auth'], $prixmonture, $prixverre, $compte, $collaborateur]);
				}

				// Mise à jour des stocks et débits (schéma montures/lentilles)
				if ($idMarque > 0) {
					$bdd->prepare('UPDATE marques SET quantite = GREATEST(quantite - 1, 0) WHERE id_marque = ?')
						->execute([$idMarque]);
				}
				$bdd->prepare('UPDATE lentilles SET quantite = GREATEST(quantite - 1, 0) WHERE id_lentille = ?')
					->execute([$categorie]);
				$bdd->prepare('UPDATE montures SET id_lentille = ?, vendu = 1, date_modification = CURRENT_TIMESTAMP WHERE id_monture = ?')
					->execute([$categorie, $produit]);
                // Paiement
                $code = genererNumeroPaiement();
                $mtotal = $prixmonture + $prixverre;
                $bdd->prepare('UPDATE affectations SET status=?, montant=?, taux=?, type_paiement=?, datetraitement=? WHERE id_affectation=?')
                    ->execute([4, $mtotal, $taux, $compte, date('Y-m-d'), $affectation]);
                $motif = $bdd->prepare('SELECT type FROM affectations WHERE id_affectation=?');
                $motif->execute([$affectation]);
                $motif = $motif->fetchColumn();
                if ($modePaiement == 0) {
                    $paie = $bdd->prepare('INSERT INTO paiements (id_affectation, code, types, montant, montant_paye, compte, patient, caisse) VALUES(?,?,?,?,?,?,?,?)');
                    $paie->execute([$affectation, $code, $motif, $mtotal, $mtotal, $compte, $patient, $_SESSION['auth']]);
                    updateCompteDebit($bdd, $compte, $mtotal);
                } else {
                    $paie = $bdd->prepare('INSERT INTO paiements (id_affectation, code, types, montant, montant_paye, compte, patient, caisse) VALUES(?,?,?,?,?,?,?,?)');
                    $paie->execute([$affectation, $code, $motif, $mtotal, $acompte, $compte, $patient, $_SESSION['auth']]);
                    updateCompteDebit($bdd, $compte, $acompte);
                }
				$errors = 6;
				// PRG: évite la resoumission du POST (refresh)
				$_SESSION['flash_vente_lunette_success_affectation'] = (int)$affectation;
				$redirect = 'ventelunette.php?client=' . (int)$patient . '&codeproduit=' . urlencode((string)$codeMonture) . '&open=1&affectation=' . (int)$affectation;
				header('Location: ' . $redirect);
				exit;
            }
        }
    }
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
					<div class="d-flex align-items-center justify-content-between" style="gap:12px;">
						<h2 class="m-0">Vente de lunettes</h2>
					</div>
				</header>

				<div class="col-md-12">
					<section class="card">
						<div class="card-body">
							<?php
							$clientParam = isset($_GET['client']) ? (int)$_GET['client'] : 0;
							$affectationParam = isset($_GET['affectation']) ? (int)$_GET['affectation'] : 0;
							if ($existe == 1 || $montureNotFound) {
								echo '<div class="alert alert-danger"><li>Cette monture n\'existe pas ou a déjà été vendue dans le système.</li></div>';
							}
							?>
							<form class="form-horizontal" novalidate="novalidate" method="POST" action="<?php echo h($_SERVER['PHP_SELF']); ?>?client=<?php echo (int)$clientParam; ?><?php if ((int)$affectationParam > 0) { echo '&affectation=' . (int)$affectationParam; } ?>" enctype="multipart/form-data">
								<input type="hidden" name="recherche" value="1">
								<div class="row form-group pb-3">
									<div class="col-md-4">
										<div class="form-group">
											<label class="col-form-label" for="productcode">Saisir le code de la monture à vendre</label>
											<input type="text" pattern="^\S*$" oninput="this.value = this.value.replace(/\s/g, '')" class="form-control" name="productcode" id="productcode" required>
										</div>
									</div>
								</div>
								<footer class="card-footer text-end">
									<button class="btn btn-primary" type="submit">Suivant</button>
								</footer>
							</form>
						</div>
					</section>
				</div>
			</section>
		</div>
	</section>

		<?php if ($montureRow): ?>
		<!-- Modal vente monture -->
		<div class="modal fade" id="venteLunetteModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-xl modal-dialog-scrollable">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Vente de lunettes</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<?php if ($errors === 1): ?>
							<div class="alert alert-danger">
								<strong>Champs obligatoires manquants.</strong><br>
								<li>Veuillez choisir une lentille et un mode de règlement.</li>
							</div>
						<?php endif; ?>
						<?php if ($errors === 2): ?>
							<div class="alert alert-danger">
								<strong>Monture introuvable.</strong><br>
								<li>Cette monture n'existe pas ou a déjà été vendue.</li>
							</div>
						<?php endif; ?>
						<?php if ($errors === 3): ?>
							<div class="alert alert-danger">
								<strong>Lentille invalide.</strong><br>
								<li>Veuillez sélectionner une lentille valide.</li>
							</div>
						<?php endif; ?>
						<?php if ($errors === 4): ?>
							<div class="alert alert-danger">
								<strong>Impossible de créer l'affectation.</strong><br>
								<li>Vérifiez la configuration du service/traitement puis réessayez.</li>
							</div>
						<?php endif; ?>
						<?php
							$affectationForReceipt = 0;
							if (!empty($affectation)) {
								$affectationForReceipt = (int)$affectation;
							} elseif (!empty($_GET['affectation'])) {
								$affectationForReceipt = (int)$_GET['affectation'];
							} elseif ($flashSuccessAffectation > 0) {
								$affectationForReceipt = (int)$flashSuccessAffectation;
							}
						?>
						<?php if ($errors == 6): ?>
							<div class="alert alert-success">
								<strong>Succès paiement éffectué !</strong><br>
							</div>
						<?php endif; ?>
						<?php if ($existe == 3): ?>
							<div class="alert alert-danger">
								<strong>Erreur de Paiement !</strong><br>
								<li>Paiement déjà éffectué par le client.</li>
								<li>Vous pouvez ré-imprimer le reçu en cliquant sur <a href="../caisse/imprimer_recu.php?affectation=<?php echo (int)($affectation ?? ($_GET['affectation'] ?? 0)); ?>" target="_blank"><i class="fa fa-file-pdf-o"></i> Reçu de paiement</a>.</li>
							</div>
						<?php endif; ?>

						<div class="table-responsive mb-3">
							<table class="table table-bordered table-striped mb-0">
								<tbody>
									<?php $prixMontureVal = (float)($montureRow['prix'] ?? 0); ?>
									<tr><th style="width:30%">Code Monture</th><td><?php echo h($montureRow['code_monture'] ?? ''); ?></td></tr>
									<tr><th>Marque</th><td><?php echo h($montureRow['marque_nom'] ?? ''); ?></td></tr>
									<tr><th>Couleur</th><td><?php echo h($montureRow['couleur'] ?? ''); ?></td></tr>
									<tr><th>Monture pour</th><td><?php echo h($montureRow['monture_pour'] ?? ''); ?></td></tr>
									<tr><th>Prix monture</th><td><span id="prixMontureLabel" data-monture-price="<?php echo h($prixMontureVal); ?>"><?php echo h(number_format($prixMontureVal)); ?></span></td></tr>
									<tr><th>Prix lentille</th><td><span id="prixLentilleLabel" data-lentille-price="0">0</span></td></tr>
									<tr><th>Montant total</th><td><span id="montantTotalLabel" class="fw-bold text-success" data-devise="<?php echo h($devise); ?>"><?php echo h(number_format($prixMontureVal)) . ' ' . h($devise) . '.' ;?></span></td></tr>
								</tbody>
							</table>
						</div>

						<form class="form-horizontal" novalidate="novalidate" method="POST" action="<?php echo h($_SERVER['PHP_SELF']); ?>?client=<?php echo (int)($_GET['client'] ?? 0); ?><?php if ((int)($_GET['affectation'] ?? 0) > 0) { echo '&affectation=' . (int)($_GET['affectation'] ?? 0); } ?>&codeproduit=<?php echo urlencode((string)($_GET['codeproduit'] ?? '')); ?>" enctype="multipart/form-data">
							<input type="hidden" name="vendre" value="1">
							<div class="row form-group pb-3">
								<div class="col-md-3">
									<div class="form-group">
										<label class="col-form-label" for="productSelect">Lentille</label>
										<select class="form-control populate" id="productSelect" name="categorie" required>
											<option value=""> --- Choisir les verres --- </option>
											<?php
											$type = $bdd->prepare('SELECT * FROM lentilles WHERE quantite > 0 AND status = ?');
											$type->execute([1]);
											while ($categorie = $type->fetch(PDO::FETCH_ASSOC)) {
												$prixL = (float)($categorie['prix_vente'] ?? 0);
												echo '<option value="' . h($categorie['id_lentille']) . '" data-price="' . h($prixL) . '">' . h($categorie['lentille']) . '</option>';
											}
											?>
										</select>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-group d-flex align-items-center h-100">
										<div class="mt-4">
											<input type="radio" name="estAssure" id="paiementtotal" value="0" onclick="toggleAccompteField()" checked>
											<label for="paiementtotal">Paiement Total</label>
										</div>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-group d-flex align-items-center h-100">
										<div class="mt-4">
											<input type="radio" name="estAssure" id="paiementpartiel" value="1" onclick="toggleAccompteField()">
											<label for="paiementpartiel">Paiement Partiel</label>
										</div>
									</div>
								</div>
								<div class="col-md-3" id="accompteField" style="display:none;">
									<div class="form-group">
										<label class="col-form-label">Accompte versé en <?php echo h($devise); ?></label>
										<input type="text" class="form-control" name="acompte" id="acompte" placeholder="Montant de l'accompte" min="0" step="1">
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-group">
										<label class="col-form-label">Mode de règlement</label>
										<select class="form-control" name="compte" required>
											<?php
											$type = $bdd->prepare('SELECT * FROM comptes WHERE status=? AND compte_pour=?');
											$type->execute([1, 2]);
											while ($type_paiement = $type->fetch(PDO::FETCH_ASSOC)) {
												$conf = $type_paiement['defaut'];
												if ((int)$conf === 1) {
													echo '<option value="' . h($type_paiement['id_compte']) . '">' . h($type_paiement['nom_compte']) . '</option>';
												}
											}
											?>
										</select>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-group">
										<label class="col-form-label">Remise/Ristourne</label>
										<select name="taux" class="form-control">
											<?php
											$rabais = $bdd->prepare('SELECT * FROM taux WHERE status=1 AND taux_pour = ?');
											$rabais->execute([1]);
											while ($taux = $rabais->fetch(PDO::FETCH_ASSOC)) {
												$status = (int)$taux['taux'];
												if ($status === 0) {
													echo '<option value="0">Non Appliqué</option>';
												}
												if ($status !== 0 && $status !== 3) {
													echo '<option value="' . h($taux['taux']) . '">' . h($taux['taux']) . '%</option>';
												}
											}
											?>
										</select>
									</div>
								</div>
							</div>
							<footer class="card-footer text-end">
								<button type="button" class="btn btn-default" data-bs-dismiss="modal">Fermer</button>
								<?php if ($errors == 6 && $affectationForReceipt > 0): ?>
									<button type="button" class="btn btn-success" id="btnImprimerRecu" data-affectation="<?php echo (int)$affectationForReceipt; ?>">
										<i class="fa fa-file-pdf-o"></i> Imprimer le reçu
									</button>
								<?php endif; ?>
								<button class="btn btn-primary" type="submit" <?php echo ($errors == 6 ? 'disabled' : ''); ?>>Valider la vente</button>
							</footer>
						</form>
					</div>
				</div>
			</div>
		</div>

		<?php if ($errors == 6 && $affectationForReceipt > 0): ?>
		<!-- Modal impression reçu -->
		<div class="modal fade" id="recuPaiementModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-xl modal-dialog-scrollable">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Reçu de paiement</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body" style="height:75vh;">
						<iframe id="recuIframe" src="../caisse/imprimer_recu.php?affectation=<?php echo (int)$affectationForReceipt; ?>" style="width:100%;height:100%;border:0;"></iframe>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-bs-dismiss="modal">Fermer</button>
						<button type="button" class="btn btn-primary" id="btnPrintRecuModal">Imprimer</button>
					</div>
				</div>
			</div>
		</div>
		<?php endif; ?>
		<?php endif; ?>

		<?php if ($openSaleModal): ?>
		<script>
			window.addEventListener('load', function () {
				try {
					if (!window.bootstrap) return;
					var el = document.getElementById('venteLunetteModal');
					if (!el) return;
					var modal = window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el);
					modal.show();
				} catch (e) {
					// noop
				}
			});
		</script>
		<?php endif; ?>


		<script>
			document.addEventListener('DOMContentLoaded', function () {
				var btn = document.getElementById('btnImprimerRecu');
				if (btn) {
					btn.addEventListener('click', function () {
					try {
						if (!window.bootstrap) return;
						var el = document.getElementById('recuPaiementModal');
						if (!el) return;
						var modal = window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el);
						modal.show();
					} catch (e) {
						// noop
					}
					});
				}

				var btnPrint = document.getElementById('btnPrintRecuModal');
				if (btnPrint) {
					btnPrint.addEventListener('click', function () {
						try {
							var iframe = document.getElementById('recuIframe');
							if (!iframe || !iframe.contentWindow) return;
							iframe.contentWindow.focus();
							iframe.contentWindow.print();
						} catch (e) {
							// noop
						}
					});
				}
			});
		</script>

		<script>
			function toggleAccompteField() {
				const accompteField = document.getElementById('accompteField');
				const selected = document.querySelector('input[name="estAssure"]:checked');
				const estPaiementPartiel = selected && selected.value === '1';
				if (accompteField) accompteField.style.display = estPaiementPartiel ? 'block' : 'none';
				if (!estPaiementPartiel) {
					const acompteInput = document.getElementById('acompte');
					if (acompteInput) acompteInput.value = '';
				}
			}

			document.addEventListener('DOMContentLoaded', function() {
				const montantInput = document.getElementById('acompte');
				if (!montantInput) return;
				montantInput.addEventListener('input', function() {
					let selectionStart = this.selectionStart;
					let oldLength = this.value.length;
					let value = this.value.replace(/\s/g, '').replace(/\D/g, '');
					if (!value) {
						this.value = '';
						return;
					}
					let formatted = value.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
					this.value = formatted;
					let newLength = formatted.length;
					let diff = newLength - oldLength;
					try { this.setSelectionRange(selectionStart + diff, selectionStart + diff); } catch (e) {}
				});
			});
		</script>

		<script>
			function formatNumber(num) {
				try { return (Math.round(num)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' '); } catch (e) { return String(num); }
			}

			function updateLentilleAndTotal() {
				var select = document.getElementById('productSelect');
				var prixMontureEl = document.getElementById('prixMontureLabel');
				var prixLentilleEl = document.getElementById('prixLentilleLabel');
				var totalEl = document.getElementById('montantTotalLabel');
				if (!select || !prixMontureEl || !prixLentilleEl || !totalEl) return;

				var monturePrice = parseFloat(prixMontureEl.getAttribute('data-monture-price') || '0') || 0;
				var opt = select.options[select.selectedIndex];
				var lentillePrice = 0;
				if (opt && opt.getAttribute) {
					lentillePrice = parseFloat(opt.getAttribute('data-price') || '0') || 0;
				}

				prixLentilleEl.setAttribute('data-lentille-price', String(lentillePrice));
				prixLentilleEl.textContent = formatNumber(lentillePrice);
				var devise = totalEl.getAttribute('data-devise') || '';
				var totalTxt = formatNumber(monturePrice + lentillePrice);
				if (devise) totalTxt += ' ' + devise + '.';
				totalEl.textContent = totalTxt;
			}

			document.addEventListener('DOMContentLoaded', function () {
				var select = document.getElementById('productSelect');
				if (select) {
					select.addEventListener('change', updateLentilleAndTotal);
				}
				updateLentilleAndTotal();
			});
		</script>

		<?php include('../PUBLIC/footer.php'); ?>