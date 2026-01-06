<?php
include('../PUBLIC/connect.php');
session_start();
$errors = 0;
$existe = 0;

if (isset($_POST['ajouter'])) {
    $qteappro = $_POST['quantite'];
    $qté = 0; // Initialisation pour éviter l'erreur undefined variable
    $reponse2 = $bdd->prepare('SELECT * FROM approvisionnements WHERE id_appro=?');
    $reponse2->execute(array($_POST['nolivraison']));
    while ($donnees2 = $reponse2->fetch()) {
        $qté = $donnees2['quantite'];
    }

    if ($qté>=$qteappro) {

        $reponse1 = $bdd->prepare('SELECT * FROM categorie_produits WHERE id_categorie=?');
        $reponse1->execute(array($_POST['categorie']));
        while ($donnees1 = $reponse1->fetch()) 
        {$quantite = $donnees1['quantite'];}

        $quantite += $qteappro;

        $errors=4;}

        if ($errors==4) {
            $reqQ = $bdd->prepare('UPDATE categorie_produits SET quantite=? WHERE id_categorie= ?');
        $reqQ->execute(array($quantite, $_POST['categorie']));

        $qté -= $qteappro;
        
        $reqQ = $bdd->prepare('UPDATE approvisionnements SET quantite=? WHERE id_appro= ?');
        $reqQ->execute(array($qté, $_POST['nolivraison']));

        $errors = 2;
        } else { $errors = 3; }
        
    } 

?>
<?php include('../PUBLIC/header.php'); ?>

<body>
	<section class="body">

		<?php require('../PUBLIC/navbarmenu.php'); ?>

		<div class="inner-wrapper">
			<section role="main" class="content-body">
				<header class="page-header">
					<h2>Provision de verres</h2>
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
                                                <li>Provision effectuée avec succès !</li>
                                            </div>
                                            ';
							}
							if ($errors == 3) {
								echo '
                                            <div class="alert alert-danger">
                                                <li>Provision non effectué, la quantité saisie est supérieur à la quantité restante</li>
                                            </div>
                                            ';
							}
							?>
							<form class="form-horizontal" novalidate="novalidate" method="POST" action="addapprocategorie.php" enctype="multipart/form-data">
								<input type="hidden" name="ajouter" value="1">
								<div class="row form-group pb-3">
									<div class="col-md-2">
										<div class="form-group">
											<label class="col-form-label" for="formGroupExampleInput">N° de bon de livraison</label>
											<select name="nolivraison" class="form-control populate" id="nolivraison" onchange="updateQTE()" >
												<option value=""> ------ Choisir ----- </option>
													<?php
													$coll = $bdd->prepare('SELECT * FROM approvisionnements WHERE quantite > 0 AND statut=? AND type_commande=? OR type_commande=?');
													$coll -> execute(['livré', 'lentilles', 'montures et lentilles']);
													while ($livraison = $coll->fetch(PDO::FETCH_ASSOC))
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
									<div class="col-md-3">
										<div class="form-group">
											<label class="col-form-label" for="formGroupExampleInput">Type de verres</label>
											<select class="form-control populate" name="categorie" required="">
												<option> ---- choisir ----- </option>
												<?php $type = $bdd->prepare('SELECT * FROM categorie_produits WHERE status=1');
												$type->execute();
												while ($model = $type->fetch()) {
													$actif = $model['status'];
													if ($actif == 1) {
														echo '<option value="' . $model['id_categorie'] . '">' . $model['categorie'] . '</option>';
													}
												} ?>
											</select>
										</div>
									</div>
									<div class="col-md-2">
										<div class="form-group">
											<label class="col-form-label" for="formGroupExampleInput">Quantité</label>
											<input type="number" class="form-control" min="1" step="1" name="quantite" id="formGroupExampleInput">
										</div>
									</div>
								</div>
								<footer class="card-footer text-end">
									<button class="btn btn-primary" type="submit" name="ajouter">valider</button>
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