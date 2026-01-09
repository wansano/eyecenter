<?php
include('../PUBLIC/connect.php');
include('../PUBLIC/fonction.php');
session_start();
$errors = 0;
$existe = 0;

if (isset($_POST['ajouter'])) {
	$codeProduit = isset($_POST['codeproduit']) ? trim((string)$_POST['codeproduit']) : '';
	$noLivraison = isset($_POST['nolivraison']) ? trim((string)$_POST['nolivraison']) : '';
	$idModel     = isset($_POST['model']) ? (int)$_POST['model'] : 0;
	$couleur     = isset($_POST['couleur']) ? trim((string)$_POST['couleur']) : '';
	$description = isset($_POST['description']) ? trim((string)$_POST['description']) : '';

	if ($codeProduit === '' || $noLivraison === '' || $idModel <= 0 || $couleur === '') {
		// Champs incomplets / invalides
		$errors = 8;
	} else {
		try {
			$req1 = $bdd->prepare('SELECT 1 FROM montures WHERE code_monture = ? LIMIT 1');
			$req1->execute([$codeProduit]);
			$existe = $req1->fetchColumn() ? 1 : 0;

			if ($existe == 0) {
				$reponse2 = $bdd->prepare('SELECT quantite, prix_unitaire FROM approvisionnements WHERE no_livraison = ? LIMIT 1');
				$reponse2->execute([$noLivraison]);
				$donnees2 = $reponse2->fetch(PDO::FETCH_ASSOC);

				if (!$donnees2) {
					// Bon de livraison introuvable
					$errors = 6;
				} else {
					$qteAppro = (int)$donnees2['quantite'];
					$prixUnitaire = (float)$donnees2['prix_unitaire'];

					if ($qteAppro < 1) {
						// Quantité insuffisante
						$errors = 5;
					} else {
						$reponse1 = $bdd->prepare('SELECT quantite FROM marques WHERE id_marque = ? LIMIT 1');
						$reponse1->execute([$idModel]);
						$quantiteModeleRaw = $reponse1->fetchColumn();
						if ($quantiteModeleRaw === false) {
							// Modèle introuvable
							$errors = 7;
						} else {
							$quantiteModele = (int)$quantiteModeleRaw;

							$bdd->beginTransaction();
							$req = $bdd->prepare('INSERT INTO montures (code_monture, no_livraison, id_marque, couleur, prix, description) VALUES(?,?,?,?,?,?)');
							$req->execute([$codeProduit, $noLivraison, $idModel, $couleur, $prixUnitaire, $description]);

							$reqQ = $bdd->prepare('UPDATE marques SET quantite = ? WHERE id_marque = ?');
							$reqQ->execute([$quantiteModele + 1, $idModel]);

							// Sécuriser la décrémentation (évite quantite négative en cas de concurrence)
							$reqA = $bdd->prepare('UPDATE approvisionnements SET quantite = quantite - 1 WHERE no_livraison = ? AND quantite > 0');
							$reqA->execute([$noLivraison]);
							if ($reqA->rowCount() < 1) {
								throw new Exception('Quantité approvisionnement insuffisante');
							}

							$bdd->commit();
							$errors = 2;
						}
					}
				}
			}
		} catch (Exception $e) {
			if ($bdd->inTransaction()) {
				$bdd->rollBack();
			}
			error_log('Erreur ajout produit: ' . $e->getMessage());
			$errors = 9;
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
					<h2>Ajout d'une monture</h2>
				</header>

				<!-- start: page -->
				<div class="col-md-12">
					<section class="card">
						<div class="card-body">
							<?php
							if ($errors == 2) {
								echo '
								<div class="alert alert-success">
									<strong>Succès</strong> <br/>  
									<li>La monture à été ajouter avec succès !</li>
								</div>
								';
							}
							if ($errors == 5) {
								echo '
								<div class="alert alert-danger">
										<li>Ajout de la monture non effectué, merci de vérifier la quantité</li>.
								</div>
								';
							}
								if ($errors == 6) {
									echo '
									<div class="alert alert-warning">
										<li>Bon de livraison introuvable. Vérifiez le N° sélectionné.</li>
									</div>
									';
								}
								if ($errors == 7) {
									echo '
									<div class="alert alert-warning">
										<li>Modèle introuvable. Merci de sélectionner une marque valide.</li>
									</div>
									';
								}
								if ($errors == 8) {
									echo '
									<div class="alert alert-warning">
										<li>Veuillez remplir tous les champs obligatoires (code, livraison, marque, couleur).</li>
									</div>
									';
								}
								if ($errors == 9) {
									echo '
									<div class="alert alert-danger">
										<li>Erreur technique lors de l\'ajout. Réessayez, puis contactez l\'administrateur si le problème persiste.</li>
									</div>
									';
								}

							if ($existe == 1) {
								echo '
								<div class="alert alert-warning">
									<li>La monture existe déjà dans le système.</li>
								</div>
								';
							}
							?>
							<form class="form-horizontal" novalidate="novalidate" method="POST" action="addproduct.php" enctype="multipart/form-data">
								<input type="hidden" name="ajouter" value="1">
								<div class="row form-group pb-3">
									<div class="col-md-3">
										<div class="form-group">
											<label class="col-form-label" for="formGroupExampleInput">N° de la monture</label>
											<input type="text" pattern="^\S*$" oninput="this.value = this.value.replace(/\s/g, '')" name="codeproduit" class="form-control" required="">
										</div>
									</div>
									<div class="col-md-3">
										<div class="form-group">
											<label class="col-form-label" for="formGroupExampleInput">Du bon de livraison N°</label>
											<select class="form-control populate" name="nolivraison" required="">
												<?php
												$type = $bdd->prepare('SELECT no_livraison FROM approvisionnements WHERE quantite > 0 AND statut = ? AND (type_commande = ? OR type_commande = ?)');
												$type->execute(["livré", "montures", "montures et lentilles"]);
												while ($livraison = $type->fetch(PDO::FETCH_ASSOC)) {
													$no = (string)$livraison['no_livraison'];
													echo '<option value="' . htmlspecialchars($no, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($no, ENT_QUOTES, 'UTF-8') . '</option>';
												}
												?>
											</select>
										</div>
									</div>
									<div class="col-md-3">
										<div class="form-group">
											<label class="col-form-label" for="formGroupExampleInput">De la marque</label>
											<select class="form-control populate" name="model" required="">
												<option value="">---- choisir ----</option>
												<?php
												$type = $bdd->prepare('SELECT id_marque, marque FROM marques WHERE status = 1');
												$type->execute();
												while ($model = $type->fetch(PDO::FETCH_ASSOC)) {
													$id = (string)$model['id_marque'];
													$label = (string)$model['marque'];
													echo '<option value="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
												}
												?>
											</select>
										</div>
									</div>
									<div class="col-md-3">
										<div class="form-group">
											<label class="col-form-label" for="formGroupExampleInput">Couleur</label>
											<input type="text" name="couleur" class="form-control" required="">
										</div>
									</div>
									<div class="col-md-2">
										<div class="form-group">
											<label class="col-form-label" for="formGroupExampleInput">Type de la monture</label>
											<select class="form-control populate" name="description">
												<option value="">---- choisir ----</option>
												<option value="Adulte Homme">Adulte Homme</option>
												<option value="Adulte Femme">Adulte Femme</option>
												<option value="Enfant">Enfant</option>
											</select>
										</div>
									</div>
								</div>
								<footer class="card-footer text-end">
									<button class="btn btn-primary" type="submit" name="ajouter">Ajouter la monture</button>
								</footer>
							</form>
						</div>
					</section>
				</div>
				<!-- end: page -->
			</section>
		</div>
	</section>
	<?php include('../PUBLIC/footer.php'); ?>