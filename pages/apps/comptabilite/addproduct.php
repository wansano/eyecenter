<?php
include('../PUBLIC/connect.php');
session_start();
$errors = 0;
$existe = 0;

if (isset($_POST['ajouter'])) {
    $codeproduit = trim($_POST['codeproduit']);
    $nolivraison = trim($_POST['nolivraison']);
    $model = trim($_POST['model']);
    $couleur = trim($_POST['couleur']);
    $description = trim($_POST['description']);
    $prix = isset($_POST['prix']) ? floatval(str_replace([' ', ','], ['', '.'], $_POST['prix'])) : 0;

    $req1 = $bdd->prepare('SELECT 1 FROM produits WHERE code_produit=?');
    $req1->execute([$codeproduit]);
    if ($req1->fetch()) {
        $existe = 1;
    } else {
        // Correction : recherche par id_appro (et non no_livraison)
        $reponse2 = $bdd->prepare('SELECT quantite FROM approvisionnements WHERE id_appro=?');
        $reponse2->execute([$nolivraison]);
        $donnees2 = $reponse2->fetch();
        $qté = $donnees2 ? $donnees2['quantite'] : 0;

        if ($qté >= 1) {
            $reponse1 = $bdd->prepare('SELECT quantite FROM model_produits WHERE id_model=?');
            $reponse1->execute([$model]);
            $donnees1 = $reponse1->fetch();
            $quantite = $donnees1 ? $donnees1['quantite'] : 0;

            $req = $bdd->prepare('INSERT INTO produits (code_produit, no_livraison, id_model, couleur, description, prix) VALUES(?,?,?,?,?,?)');
            $req->execute([$codeproduit, $nolivraison, $model, $couleur, $description, $prix]);

            $quantite++;
            $reqQ = $bdd->prepare('UPDATE model_produits SET quantite=? WHERE id_model=?');
            $reqQ->execute([$quantite, $model]);

            $qté--;
            $reqQ = $bdd->prepare('UPDATE approvisionnements SET quantite=? WHERE id_appro=?');
            $reqQ->execute([$qté, $nolivraison]);

            $errors = 2;
        } else {
            $errors = 5;
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
									<li>Ajout de la monture non effectué</li>.
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
							<form class="form-horizontal" novalidate method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
								<input type="hidden" name="ajouter" value="1">
								<div class="row form-group pb-3">
									<div class="col-md-3">
										<div class="form-group">
											<label class="col-form-label" for="codeproduit">N° de monture</label>
											<input type="text" pattern="^\S*$" oninput="this.value = this.value.replace(/\s/g, '')" name="codeproduit" class="form-control" required>
										</div>
									</div>
									<div class="col-md-2">
										<div class="form-group">
											<label class="col-form-label" for="formGroupExampleInput">N° de bon de livraison</label>
											<select name="nolivraison" class="form-control populate" id="nolivraison" onchange="updateQTE()" >
												<option value=""> ------ Choisir ----- </option>
													<?php
													$coll = $bdd->prepare('SELECT * FROM approvisionnements WHERE quantite > 0 AND statut= ? AND (type_commande=? OR type_commande=?)');
													$coll -> execute(['livré', 'montures', 'montures et lentilles']);
													while ($livraison = $coll->fetch())
													{
														echo '<option value="'.$livraison['id_appro'].'">'.$livraison['no_livraison'].'</option>';
													} ?>
												</option>
											</select>
										</div>
									</div>
									<div class="col-md-2">
										<div class="form-group">
											<label class="col-form-label" for="formGroupExampleInput">QTÉ DISPONNIBLE</label>
											<input type="text" class="form-control" name="qteBL" id="qteBL" onchange="fetchQTEBL()" style="background-color:#64F584;" disabled>
										</div>
									</div>
									<div class="col-md-2">
										<div class="form-group">
											<label class="col-form-label" for="model">Marque de la monture</label>
											<select class="form-control populate" name="model" required>
												<option value=""> ---- choisir ----- </option>
												<?php $type = $bdd->prepare('SELECT id_model, model FROM model_produits WHERE status=1');
												$type->execute();
												while ($model = $type->fetch(PDO::FETCH_ASSOC)) {
													echo '<option value="' . htmlspecialchars($model['id_model']) . '">' . htmlspecialchars($model['model']) . '</option>';
												} ?>
											</select>
										</div>
									</div>
									<div class="col-md-3">
										<div class="form-group">
											<label class="col-form-label" for="couleur">Couleur</label>
											<input type="text" name="couleur" class="form-control" required>
										</div>
									</div>
									<div class="col-md-2">
										<div class="form-group">
											<label class="col-form-label" for="prix">Prix</label>
											<input type="text" name="prix" id="prix" class="form-control" inputmode="decimal" pattern="[0-9 ]+" required>
										</div>
									</div>
									<div class="col-md-12">
										<div class="form-group">
											<label class="col-form-label" for="description">Description de la catégorie</label>
											<textarea class="form-control" rows="5" name="description" id="description"></textarea>
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
		<script>
    document.addEventListener('DOMContentLoaded', function() {
        const montantInput = document.getElementById('prix');
        if (montantInput) {
            montantInput.addEventListener('input', function(e) {
                let selectionStart = this.selectionStart;
                let oldLength = this.value.length;
                let value = this.value.replace(/\s/g, '');
                value = value.replace(/[^\d]/g, '');
                if (value) {
                    let formatted = value.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                    this.value = formatted;
                    let newLength = formatted.length;
                    let diff = newLength - oldLength;
                    this.setSelectionRange(selectionStart + diff, selectionStart + diff);
                } else {
                    this.value = '';
                }
            });
        }
    });
</script>