<?php
    include('../PUBLIC/connect.php');
	session_start();
    $errors=0; $existe=0;

    if (isset($_POST['modification'])) {

        $req = $bdd->prepare('UPDATE categorie_produits SET categorie=?,  code_categorie=?, prix_achat=?, p_vente=?, prix_montage=?, description=? WHERE id_categorie = ?');
        $req->execute(array($_POST['categorie'], $_POST['codecategorie'], $_POST['prixachat'], $_POST['pvente'], $_POST['pmontage'], $_POST['description'], $_POST['modification']));
        $errors=2;
    
        }
    
      $reponse1 = $bdd->prepare('SELECT * FROM categorie_produits WHERE id_categorie=?');
        $reponse1 -> execute(array($_GET['idcategorie']));
        while ($donnees1 = $reponse1->fetch())
        {
            $categories=$donnees1['categorie'];
            $codecategories = $donnees1['code_categorie'];
            $prixachat = $donnees1['prix_achat'];
            $pvente = $donnees1['p_vente'];
            $descriptions=$donnees1['description'];
			$prixmontage = $donnees1['prix_montage'];
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
									<a href="#">
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
                                                <li>La catégorie à été mise à jour avec succès !</li>
                                            </div>
                                            ';
                                                }
                                        if ($errors==3) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <li>Mise à jour de la catégorie non effectué, merci de vérifier les informations saisies</li>.
                                            </div>
                                            ';}
                                    ?>
                                    <form class="form-horizontal" novalidate="novalidate" method="POST" action="editproductcategorie.php?idcategorie=<?php echo $_GET['idcategorie'];?>" enctype="multipart/form-data">
                                    <input type="hidden" name="modification" value="<?php echo $_GET['idcategorie'];?>">
										<div class="row form-group pb-3">
											<div class="col-md-6">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Nom de la catégorie</label>
													<input type="text" name="categorie" class="form-control" value="<?php echo $categories;?>" required>
												</div>
											</div>
											<div class="col-md-2">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Code lunettes</label>
													<input type="text" name="codecategorie" class="form-control" value="<?php echo $codecategories;?>" required>
												</div>
											</div>
											<div class="col-md-2">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Prix d'achat</label>
													<input type="number" class="form-control" min="1" step="1" name="prixachat" id="formGroupExampleInput" value="<?php echo $prixachat;?>" required>
												</div>
											</div>
											<div class="col-md-2">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">% de vente (En entier)</label>
													<input type="number" class="form-control" min="1" step="1" name="pvente" id="formGroupExampleInput" value="<?php echo $pvente;?>" required>
												</div>
											</div>
											<div class="col-md-2">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Prix montage</label>
													<input type="number" class="form-control" min="1" step="1" name="pmontage" id="formGroupExampleInput" value="<?php echo $prixmontage;?>" required>
												</div>
											</div>
											<div class="col-md-12">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Description</label>
													<textarea class="form-control" rows="4" name="description" id="formGroupExampleInput" required><?php echo $descriptions;?></textarea>
												</div>
											</div>
										</div>

                                    <footer class="card-footer text-end">
                                        <button class="btn btn-primary" type="submit">Mettre à jour la catégorie</button>
                                    </footer>
                                </form>
							</section>
						</div>
					</div>
					<!-- end: page -->
				</section>
			</div>
            <?php include('../PUBLIC/footer.php');?>
		