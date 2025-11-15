<?php
include('../PUBLIC/connect.php');
session_start();
$errors = 0;
$existe = 0;

if (isset($_POST['ajouter'])) {

	$req1 = $bdd->prepare('SELECT * FROM produits WHERE code_produit=?');
	$req1->execute(array($_POST['codeproduit']));
	while ($dta = $req1->fetch()) {
		$existe = 1;
	}

	if ($existe == 0) {

		$reponse2 = $bdd->prepare('SELECT * FROM approvisionnements WHERE no_livraison=?');
		$reponse2->execute(array($_POST['nolivraison']));
		while ($donnees2 = $reponse2->fetch()) {
			$qté = $donnees2['quantite'];
			$prix_unitaire = $donnees2['prix_unitaire'];
		}

		if ($qté>=1) {

		$reponse1 = $bdd->prepare('SELECT * FROM model_produits WHERE id_model=?');
		$reponse1->execute(array($_POST['model']));
		while ($donnees1 = $reponse1->fetch()) {
			$quantite = $donnees1['quantite'];
		}

		$req = $bdd->prepare('INSERT INTO produits (code_produit, no_livraison, id_model, couleur, prix, description) VALUES(?,?,?,?,?,?)');
		$req->execute(array($_POST['codeproduit'], $_POST['nolivraison'], $_POST['model'], $_POST['couleur'], $prix_unitaire, $_POST['description']));

		$quantite += 1;
		$reqQ = $bdd->prepare('UPDATE model_produits SET quantite=? WHERE id_model= ?');
		$reqQ->execute(array($quantite, $_POST['model']));

		$qté -= 1;
		$reqQ = $bdd->prepare('UPDATE approvisionnements SET quantite=? WHERE no_livraison= ?');
		$reqQ->execute(array($qté, $_POST['nolivraison']));

		$errors = 2;
		} else { $errors = 5;}
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
											<label class="col-form-label" for="formGroupExampleInput">Code de la monture</label>
											<input type="text" pattern="^\S*$" oninput="this.value = this.value.replace(/\s/g, '')" name="codeproduit" class="form-control" required="">
										</div>
									</div>
									<div class="col-md-3">
										<div class="form-group">
											<label class="col-form-label" for="formGroupExampleInput">N° bon de livraison</label>
											<select class="form-control populate" name="nolivraison" required="">
												<?php $type = $bdd->prepare('SELECT * FROM approvisionnements WHERE quantite>0 AND statut=? AND type_commande=? OR type_commande=?');
												$type->execute(array("livré","montures","montures et lentilles"));
												while ($livraison = $type->fetch()) {
													echo '<option value="' . $livraison['no_livraison'] . '">' . $livraison['no_livraison'] . '</option>';
												} ?>
											</select>
										</div>
									</div>
									<div class="col-md-3">
										<div class="form-group">
											<label class="col-form-label" for="formGroupExampleInput">Choisir la marque</label>
											<select class="form-control populate" name="model" required="">
												<option>---- choisir ----</option>'
												<?php $type = $bdd->prepare('SELECT * FROM model_produits WHERE status=1');
												$type->execute();
												while ($model = $type->fetch()) {
													$actif = $model['status'];
													if ($actif == 1) {
														echo '<option value="' . $model['id_model'] . '">' . $model['model'] . '</option>';
													}
												} ?>
											</select>
										</div>
									</div>
									<div class="col-md-3">
										<div class="form-group">
											<label class="col-form-label" for="formGroupExampleInput">Couleur</label>
											<input type="text" name="couleur" class="form-control" required="">
										</div>
									</div>
									<div class="col-md-12">
										<div class="form-group">
											<label class="col-form-label" for="formGroupExampleInput">Description de la monture</label>
											<textarea class="form-control" rows="5" name="description" id="formGroupExampleInput"></textarea>
										</div>
									</div>
								</div>
								<footer class="card-footer text-end">
									<button class="btn btn-primary" type="submit" name="ajouter">Ajouter le produit</button>
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