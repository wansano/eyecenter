<?php
require_once('../PUBLIC/connect.php');

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

$errors = 0;
$existe = 0;
$errorMessage = '';

function h($value): string {
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$profilId = 1;

// Chargement du profil (1 seule ligne)
$denomination = '';
$adresse = '';
$standard = '';
$courriel = '';
$sigle = '';
$dg = '';
$exploitation = '';
$agrement = '';

// Logo standard utilisé partout dans l'app (../img/logo.jpg depuis pages/apps/*)
$logoTargetPath = __DIR__ . '/../img/logo.jpg';
$hasExistingLogo = (is_file($logoTargetPath) && filesize($logoTargetPath) > 0);

// Liste des utilisateurs pour le choix du responsable
$responsables = [];
try {
	$uStmt = $bdd->prepare('SELECT id, pseudo FROM users WHERE status = 1 ORDER BY pseudo');
	$uStmt->execute();
	$responsables = $uStmt->fetchAll();
} catch (PDOException $e) {
	try {
		$uStmt = $bdd->prepare('SELECT id, pseudo FROM users ORDER BY pseudo');
		$uStmt->execute();
		$responsables = $uStmt->fetchAll();
	} catch (PDOException $e2) {
		$responsables = [];
	}
}

try {
	$stmt = $bdd->prepare('SELECT * FROM profil_entreprise WHERE id = ? LIMIT 1');
	$stmt->execute([$profilId]);
	$profil = $stmt->fetch();
	if ($profil) {
		$denomination = $profil['denomination'] ?? '';
		$adresse = $profil['adresse'] ?? '';
		$standard = $profil['phone'] ?? '';
		$courriel = $profil['email'] ?? '';
		$sigle = $profil['sigle'] ?? '';
		$dg = $profil['responsable'] ?? '';
		$exploitation = $profil['exploitation'] ?? '';
		$agrement = $profil['arrete'] ?? '';
	}
} catch (PDOException $e) {
	$errors = 3;
}

if (isset($_POST['profil'])) {
	$postedId = (int) ($_POST['profil'] ?? 0);
	if ($postedId > 0) {
		$profilId = $postedId;
	}

	$nextDenomination = trim((string) ($_POST['denomination'] ?? ''));
	$nextSigle = trim((string) ($_POST['sigle'] ?? ''));
	$nextAdresse = trim((string) ($_POST['adresse'] ?? ''));
	$nextPhone = trim((string) ($_POST['phone'] ?? ''));
	$nextCourriel = trim((string) ($_POST['courriel'] ?? ''));
	$nextResponsable = trim((string) ($_POST['responsable'] ?? ''));
	$nextAgrement = trim((string) ($_POST['agrement'] ?? ''));
	$nextExploitation = trim((string) ($_POST['exploitation'] ?? ''));

	$logoUploaded = (isset($_FILES['logo']) && isset($_FILES['logo']['error']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE);
	if (!$hasExistingLogo && !$logoUploaded) {
		$errors = 3;
		$errorMessage = "Le logo de l'institution est obligatoire.";
	}

	if ($errors !== 0) {
		// stop early
	} else {
		if ($logoUploaded) {
			if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
				$errors = 3;
				$errorMessage = "Upload du logo échoué. Veuillez réessayer.";
			} else {
				// Validation : uniquement JPG/JPEG pour rester compatible avec les chemins existants (logo.jpg)
				$tmpName = $_FILES['logo']['tmp_name'];
				$finfo = new finfo(FILEINFO_MIME_TYPE);
				$mime = $finfo->file($tmpName);
				$allowed = ['image/jpeg', 'image/pjpeg'];
				if (!in_array($mime, $allowed, true)) {
					$errors = 3;
					$errorMessage = "Le logo doit être une image JPG/JPEG.";
				} else {
					// Écrase le logo standard
					if (!@move_uploaded_file($tmpName, $logoTargetPath)) {
						$errors = 3;
						$errorMessage = "Impossible d'enregistrer le logo. Vérifiez les permissions du dossier img.";
					} else {
						@chmod($logoTargetPath, 0644);
						$hasExistingLogo = true;
					}
				}
			}
		}
	}

	$hasRequired = ($nextDenomination !== '' && $nextAdresse !== '' && $nextPhone !== '' && $nextCourriel !== '' && $nextResponsable !== '');
	$isEmailValid = ($nextCourriel === '' || filter_var($nextCourriel, FILTER_VALIDATE_EMAIL));

	// Responsable doit exister parmi les utilisateurs
	$isResponsableValid = true;
	if ($nextResponsable !== '') {
		try {
			$check = $bdd->prepare('SELECT COUNT(*) FROM users WHERE pseudo = ?');
			$check->execute([$nextResponsable]);
			$isResponsableValid = ((int) $check->fetchColumn() > 0);
		} catch (PDOException $e) {
			$isResponsableValid = false;
		}
	}

	if ($errors === 0 && (!$hasRequired || !$isEmailValid || !$isResponsableValid)) {
		$errors = 3;
		if ($errorMessage === '') {
			$errorMessage = "Enregistrement non effectué, merci de vérifier les informations saisies si elles sont correctes.";
		}
	} else {
		if ($errors !== 0) {
			// Erreur déjà définie (ex: logo)
		} else {
		$noChange = (
			$nextDenomination === (string) $denomination
			&& $nextSigle === (string) $sigle
			&& $nextAdresse === (string) $adresse
			&& $nextPhone === (string) $standard
			&& $nextCourriel === (string) $courriel
			&& $nextResponsable === (string) $dg
			&& $nextAgrement === (string) $agrement
			&& $nextExploitation === (string) $exploitation
		);

		if ($noChange) {
			$existe = 1;
		} else {
			try {
				$req = $bdd->prepare('UPDATE profil_entreprise SET denomination = ?, sigle = ?, adresse = ?, phone = ?, email = ?, responsable = ?, arrete = ?, exploitation = ? WHERE id = ?');
				$req->execute([$nextDenomination, $nextSigle, $nextAdresse, $nextPhone, $nextCourriel, $nextResponsable, $nextAgrement, $nextExploitation, $profilId]);
				$errors = 2;

				// Rafraîchir les valeurs affichées
				$denomination = $nextDenomination;
				$sigle = $nextSigle;
				$adresse = $nextAdresse;
				$standard = $nextPhone;
				$courriel = $nextCourriel;
				$dg = $nextResponsable;
				$agrement = $nextAgrement;
				$exploitation = $nextExploitation;
			} catch (PDOException $e) {
				$errors = 3;
				if ($errorMessage === '') {
					$errorMessage = "Enregistrement non effectué, merci de vérifier les informations saisies si elles sont correctes.";
				}
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
						<h2>Profillage de l'institution</h2>
					</header>

					<!-- start: page -->
                    <div class="col-md-12">
							<section class="card">
								<div class="card-body">
                                    <?php
                                        if ($errors==2) {
                                        echo '
                                            <div class="alert alert-success">
                                            <strong>Succès</strong> <br/>  
                                            <li>Modification des informations éffectué avec succès.</li> 
                                            </div>
                                            ';
                                                }
										if ($errors==3) {
										echo '
											<div class="alert alert-danger">
												<li>' . h($errorMessage !== '' ? $errorMessage : "Enregistrement non effectué, merci de vérifier les informations saisies si elles sont correctes.") . '</li>
											</div>
											';}

                                        if ($existe==1) {
                                        echo '
                                            <div class="alert alert-warning">
                                                <li>Vous n\'avez éffectuer aucune modification.</li>
                                            </div>
                                            ';
                                                }
                                    ?>
                                    <form class="form-horizontal" novalidate="novalidate" method="POST" action="profilentreprise.php?pe=entreprise" enctype="multipart/form-data">
                                        <input type="hidden" name="profil" value="1" >
									<div class="row form-group pb-3">
										<div class="col-md-6">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Denomination de l'institution</label>
																	<input type="text" name="denomination" class="form-control" placeholder="" value="<?php echo h($denomination); ?>" required>
											</div>
										</div>
										<div class="col-md-6">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Adresse de l'institution</label>
												<input type="text" class="form-control" name="adresse" id="formGroupExampleInput" placeholder="" value="<?php echo h($adresse); ?>" required>
											</div>
										</div>
                                        <div class="col-md-3">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">N° Agrement de création</label>
												<input type="text" class="form-control" name="agrement" id="formGroupExampleInput" placeholder="" value="<?php echo h($agrement); ?>">
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">N° Agrement d'exploitation</label>
												<input type="text" class="form-control" name="exploitation" id="formGroupExampleInput" placeholder="" value="<?php echo h($exploitation); ?>">
											</div>
										</div>
                                        <div class="col-md-6">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Responsable de l'institution</label>
												<select class="form-control" name="responsable" id="formGroupExampleInput" required>
													<?php
													$dgExists = false;
													foreach ($responsables as $r) {
														if (($r['pseudo'] ?? '') === (string) $dg) {
															$dgExists = true;
															break;
														}
													}
													?>
													<option value="" <?php echo ($dg === '' || !$dgExists) ? 'selected' : ''; ?>>-- Sélectionner un utilisateur --</option>
													<?php if ($dg !== '' && !$dgExists) { ?>
														<option value="" selected>Responsable actuel : <?php echo h($dg); ?> (non trouvé)</option>
													<?php } ?>
													<?php foreach ($responsables as $r) {
														$pseudo = $r['pseudo'] ?? '';
														if ($pseudo === '') continue;
														$selected = ($pseudo === (string) $dg) ? 'selected' : '';
														?>
														<option value="<?php echo h($pseudo); ?>" <?php echo $selected; ?>><?php echo h($pseudo); ?></option>
													<?php } ?>
												</select>
											</div>
										</div>
                                        <div class="col-md-1">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Sigle</label>
												<input type="text" class="form-control" name="sigle" id="formGroupExampleInput" placeholder="" value="<?php echo h($sigle); ?>">
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Email contact</label>
												<input type="email" class="form-control" name="courriel" id="formGroupExampleInput" placeholder="" value="<?php echo h($courriel); ?>" required>
											</div>
										</div>
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Telephone standard</label>
												<input type="text" class="form-control" name="phone" id="formGroupExampleInput" placeholder="" value="<?php echo h($standard); ?>" required>
											</div>
										</div>
                                        <div class="col-md-3">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Logo de l'institution</label>
												<input type="file" class="form-control" name="logo" id="formGroupExampleInput" placeholder="" accept="image/jpeg" <?php echo $hasExistingLogo ? '' : 'required'; ?>>
											</div>
										</div>
                                        <div class="col-md-3">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Cachet standard </label>
												<input type="file" class="form-control" name="cachet" id="formGroupExampleInput" placeholder="">
											</div>
										</div>
									</div>
								</div>
								<footer class="card-footer text-end">
									<button class="btn btn-primary" type="submit">Mettre à jour les informations</button>
								</footer>
                                </form>
							</section>
						</div>
					</div>
					<!-- end: page -->
				</section>
			</div>
            <?php include('../PUBLIC/footer.php');?>