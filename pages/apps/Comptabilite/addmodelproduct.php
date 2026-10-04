<?php
    include('../PUBLIC/connect.php');
	session_start();
    $errors=0; $existe=0;

    if (isset($_POST['ajouter'])) {
        $model = trim($_POST['model']);
        $description = trim($_POST['description']);
        if ($model === '') {
            $errors = 3;
        } else {
            $req1 = $bdd->prepare('SELECT 1 FROM model_produits WHERE model=?');
            $req1->execute([$model]);
            if ($req1->fetch()) {
                $existe = 1;
            } else {
                $req = $bdd->prepare('INSERT INTO model_produits (model, description) VALUES(?,?)');
                $req->execute([$model, $description]);
                $errors = 2;
            }
        }
    }
?>
<?php include('../PUBLIC/header.php'); ?>
	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Ajout d'un model ou marque de produit</h2>
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
                                                <li>La marque à été ajouter avec succès !</li>
                                            </div>
                                            ';
                                                }
                                        if ($errors==3) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <li>Ajout de marque non effectué, merci de vérifier les informations saisies</li>.
                                            </div>
                                            ';}

                                        if ($existe==1) {
                                        echo '
                                            <div class="alert alert-warning">
                                                <li>La marque existe déjà dans le système.</li>
                                            </div>
                                            ';
                                        }
                                    ?>
                                    <form class="form-horizontal" novalidate="novalidate" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
										<input type="hidden" name="ajouter" value="1">
										<div class="row form-group pb-3">
											<div class="col-md-3">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Model ou marque</label>
													<input type="text" name="model" class="form-control" placeholder="" required>
												</div>
											</div>
											<div class="col-md-12">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Description</label>
													<textarea class="form-control" rows="5" name="description" id="formGroupExampleInput"></textarea>
												</div>
											</div>
										</div>
										<footer class="card-footer text-end">
											<button class="btn btn-primary" type="submit" name="ajouter">Ajouter le model</button>
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