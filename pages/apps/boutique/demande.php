<?php
    include('../PUBLIC/connect.php');
	session_start();
    $errors=0; $existe=0;

    if (isset($_POST['ajouter'])) 
        {   
            $req = $bdd->prepare('INSERT INTO depenses (description, montant, id, validateur) VALUES(?,?,?,?)');
            $req->execute(array($_POST['description'], $_POST['montant'], $_SESSION['auth'], $_POST['validateur']));
            $errors=2;  
        }

include('../PUBLIC/header.php');
?>
	<body>
		<section class="body">

			<?php include('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Faire une nouvelle demande de depense</h2>

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
								<header class="card-header">
									<h2 class="card-title">Formulaire de demande depense</h2>
									<p class="card-subtitle">
										ATTENTION ! Merci de spécifier le bésoin d'une manière precise.
									</p>
								</header>
								<div class="card-body">
                                    <?php
                                        if ($errors==2) {
                                        echo '
                                            <div class="alert alert-success">
                                                <strong>Succès</strong> <br/>  
                                                <li>Votre demande à été soumis au responsable pour validation</li>
                                            </div>
                                            ';
                                                }
                                        if ($errors==3) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <strong>Erreur</strong> <br/>  
                                                <li>Votre demande n\'est pas soumise, merci de spécifier le besoin correctement.</li>
                                            </div>
                                            ';}

                                    ?>
                                    <form class="form-horizontal" novalidate="novalidate" method="POST" action="demande.php?r=<?php echo $types; ?>" enctype="multipart/form-data">
                                     
									<div class="row form-group pb-3">
										<div class="col-md-12">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Description</label>
                                                <textarea type="text" name="description" row="6" class="form-control" placeholder="" required></textarea>
											</div>
										</div>
										<div class="col-md-4">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Montant Total</label>
												<input type="number" class="form-control" name="montant" id="formGroupExampleInput" placeholder="" require>
											</div>
										</div>
										<div class="col-md-4">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Validateur de la demande</label>
												<select name="validateur" data-plugin-selectTwo class="form-control populate">
                                                    <optgroup>
                                                    <?php
                                                    $validate = $bdd->prepare('SELECT * FROM users WHERE status=1 AND responsable=0');
                                                    $validate -> execute();
                                                    while ($validateur = $validate->fetch())
                                                    {   
                                                            echo '<option value="'.$validateur['id'].'">'.$validateur['pseudo'].'</option>';
                                                    }
                                                    ?>
                                                    </optgroup>
                                                </select>
											</div>
										</div>
									</div>
								<footer class="card-footer text-end">
									<button class="btn btn-primary" name="ajouter" type="submit">Placer ma demande depense à confirmer</button>
								</footer>
                                </form>
							</section>
						</div>
					</div>
					<!-- end: page -->
				</section>
			</div>
            <?php include('../PUBLIC/footer.php');?>