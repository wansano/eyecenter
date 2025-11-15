<?php
    include('../PUBLIC/connect.php');
	session_start();
    $errors=0; $existe=0;

    if (isset($_POST['ajouter'])) {

    $req1 = $bdd->prepare('SELECT * FROM categorie_produits WHERE categorie=? AND code_categorie=?');
    $req1->execute(array($_POST['categorie'], $_POST['codecategorie']));
    while ($dta = $req1->fetch()) 
	{
      $existe=1;
    }

    if ($existe==0) {

    $req = $bdd->prepare('INSERT INTO categorie_produits (categorie, code_categorie, prix_achat, p_vente, prix_montage, description) VALUES(?,?,?,?,?,?)');
    $req->execute(array($_POST['categorie'], $_POST['codecategorie'], $_POST['prixachat'],$_POST['pvente'], $_POST['prixmontage'], $_POST['description']));
    $errors=2;

    }

  }
?>

<?php include('../PUBLIC/header.php'); ?>	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Ajout d'une catégorie de produit</h2>

						<div class="right-wrapper text-end">
							<ol class="breadcrumbs">
								<li>
									<a href="welcome.php?profil=ecv2">
										<i class="bx bx-home-alt"></i>
									</a>
								</li>

								<li><span>Acceuil</span></li>

							</ol>

							<a class="sidebar-right-toggle" data-open="sidebar-right"></a>
						</div>
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
                                                <li>La catégorie à été ajouter avec succès !</li>
                                            </div>
                                            ';
                                                }
                                        if ($errors==3) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <li>Ajout de la catégorie non effectué, merci de vérifier les informations saisies</li>.
                                            </div>
                                            ';}

                                        if ($existe==1) {
                                        echo '
                                            <div class="alert alert-warning">
                                                <li>La catégorie existe déjà dans le système.</li>
                                            </div>
                                            ';
                                        }
                                    ?>
                                    <form class="form-horizontal" novalidate="novalidate" method="POST" action="addproductcategorie.php" enctype="multipart/form-data">
                                    <input type="hidden" name="ajouter" value="1">
										<div class="row form-group pb-3">
											<div class="col-md-6">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Nom de la catégorie</label>
													<input type="text" name="categorie" class="form-control" placeholder="" required>
												</div>
											</div>
											<div class="col-md-2">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Code lunettes</label>
													<input type="text" name="codecategorie" class="form-control" placeholder="" required>
												</div>
											</div>
											<div class="col-md-2">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Prix d'achat</label>
													<input type="number" class="form-control" min="1" step="1" name="prixachat" id="formGroupExampleInput" placeholder="" require>
												</div>
											</div>
											<div class="col-md-2">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">% de vente (En entier)</label>
													<input type="number" class="form-control" min="1" step="1" name="pvente" id="formGroupExampleInput" placeholder="" require>
												</div>
											</div>
											<div class="col-md-2">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Prix montage</label>
													<input type="number" class="form-control" min="1" step="1" name="prixmontage" id="formGroupExampleInput" placeholder="" require>
												</div>
											</div>
											<div class="col-md-12">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Description</label>
													<textarea class="form-control" rows="4" name="description" id="formGroupExampleInput" placeholder="" require></textarea>
												</div>
											</div>
										</div>

                                    <footer class="card-footer text-end">
                                        <button class="btn btn-primary" type="submit" name="ajouter">Ajouter la catégorie</button>
                                    </footer>
                                </form>
							</section>
						</div>
					</div>
					<!-- end: page -->
				</section>
			</div>
            <?php include('../PUBLIC/footer.php');?>
		