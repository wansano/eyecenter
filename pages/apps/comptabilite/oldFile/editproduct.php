<?php
    include('../PUBLIC/connect.php');
	session_start();
    $errors=0; $existe=0;

    if (isset($_POST['modification'])) {

        $req = $bdd->prepare('UPDATE produit_catégories SET catégorie=?, description=? WHERE id_catégorie = ?');
        $req->execute(array($_POST['categorie'], $_POST['description'], $_POST['modification']));
        $errors=2;
    
        }
    
      	$reponse1 = $bdd->prepare('SELECT * FROM produit_catégories WHERE id_catégorie=?');
        $reponse1 -> execute(array($_GET['idcategorie']));
        while ($donnees1 = $reponse1->fetch())
        {
            $catégories=$donnees1['catégorie'];
            $descriptions=$donnees1['description'];
        }

?>
<?php include('../PUBLIC/header.php'); ?>
	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Modification d'une catégorie de produit</h2>

						<div class="right-wrapper text-end">
							<ol class="breadcrumbs">
								<li>
									<a href="../welcome.php?profil=ecv2">
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
                                                <li>Mise à jour catégorie non effectué, merci de vérifier les informations saisies</li>.
                                            </div>
                                            ';}
                                    ?>
                                    <form class="form-horizontal" novalidate="novalidate" method="POST" action="editcategorie.php?idcategorie=<?php echo $_GET['idcategorie'];?>" enctype="multipart/form-data">
										<input type="hidden" name="modification" value="<?php echo $_GET['idcategorie'];?>">
										<div class="row form-group pb-3">
											<div class="col-md-4">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Catégorie</label>
													<input type="text" name="categorie" class="form-control" value="<?php echo $catégories;?>" required>
												</div>
											</div>
											<div class="col-md-12">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Description de la catégorie</label>
													<textarea class="form-control" rows="5" name="description" id="formGroupExampleInput"> <?php echo $descriptions; ?> </textarea>
												</div>
											</div>
										</div>
										<footer class="card-footer text-end">
											<button class="btn btn-primary" type="submit">Mettre à jour la catégorie</button>
										</footer>
                                	</form>
								</div>
							</section>
						</div>
					<!-- end: page -->
				</section>
			</div>
		</section>
	<?php include('../PUBLIC/footer.php');?>