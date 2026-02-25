<?php
    include('../PUBLIC/connect.php');
	session_start();
    $errors=0; $existe=0;

    if (isset($_POST['ajouter'])) {

    $req1 = $bdd->prepare('SELECT * FROM fournisseur_produit WHERE fournisseur=? OR email=? OR telephone=?');
    $req1->execute(array($_POST['fournisseur'], $_POST['email'], $_POST['telephone']));
    while ($dta = $req1->fetch()) 
	{
      $existe=1;
    }

    if ($existe==0) {

    $req = $bdd->prepare('INSERT INTO fournisseur_produit (type_fournisseur, fournisseur, responsable, telephone, email, adresse) VALUES(?,?,?,?,?,?)');
    $req->execute(array($_POST['typefournisseur'], $_POST['fournisseur'], $_POST['responsable'], $_POST['telephone'], $_POST['email'], $_POST['adresse']));
    $errors=2;

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
						<h2>Ajouter un nouveau fournisseurs</h2>
					</header>

					<!-- start: page -->
                    <div class="col-md-12">
							<section class="card">
								<header class="card-header">
								</header>
								<div class="card-body">
                                    <?php
                                        if ($errors==2) {
                                        echo '
                                            <div class="alert alert-success">
                                                <strong>Succès</strong> <br/>  
                                                <li>Le fournisseur à été ajouter avec succès !</li>
                                            </div>
                                            ';
                                                }
                                        if ($errors==3) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <li>Ajout du fournisseur non effectué, merci de vérifier les informations saisies</li>.
                                            </div>
                                            ';}

                                        if ($existe==1) {
                                        echo '
                                            <div class="alert alert-warning">
                                                <li>Le fournisseur existe déjà dans le système.</li>
                                            </div>
                                            ';
                                        }
                                    ?>
                                    <form class="form-horizontal" novalidate="novalidate" method="POST" action="addsupplyer.php" enctype="multipart/form-data">
                                    <input type="hidden" name="ajouter" value="1">
									<div class="row form-group pb-3">
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Type de fournisseur</label>
                                                <select class="form-control populate" name="typefournisseur" required="">
                                                    <option value="particulier">Particulier</option>
                                                    <option value="entreprise">Entreprise</option>
                                                    <option value="interne">Interne</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Nom du fournisseur</label>
                                                <input type="text" name="fournisseur" class="form-control" placeholder="" required>
											</div>
										</div>
                                        <div class="col-md-4">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Personne contact</label>
                                                <input type="text" name="responsable" class="form-control" placeholder="" required>
											</div>
										</div>
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Téléphone</label>
												<input type="text" class="form-control" name="telephone" id="formGroupExampleInput" placeholder="" require>
											</div>
										</div>
										<div class="col-md-4">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Courriel</label>
												<input type="email" class="form-control" name="email" id="formGroupExampleInput" placeholder="" require>
											</div>
										</div>
                                        <div class="col-md-12">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Adresse</label>
												<input type="text" class="form-control" name="adresse" id="formGroupExampleInput" placeholder="" require>
											</div>
										</div>
                                    </div>

                                    <footer class="card-footer text-end">
                                        <button class="btn btn-primary" type="submit">Ajouter le fournisseur</button>
                                    </footer>
                                </form>
							</section>
						</div>
					</div>
					<!-- end: page -->
				</section>
			</div>
            <?php include('../PUBLIC/footer.php');?>
