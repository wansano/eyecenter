<?php
    include('../PUBLIC/connect.php');
	session_start();
    $errors=0; $existe=0;

    if (isset($_POST['ajouter'])) {

    $req1 = $bdd->prepare('SELECT * FROM taux WHERE taux=? AND taux_pour=?');
    $req1->execute([$_POST['taux'], $_POST['pour']]);
    while ($dta = $req1->fetch(PDO::FETCH_ASSOC)) 
	{
      $existe=1;
    }

    if ($existe==0) {

    $req = $bdd->prepare('INSERT INTO taux (taux, taux_pour) VALUES(?,?)');
    $req->execute([$_POST['taux'], $_POST['pour']]);
    $errors=2;

    }

  }
?>
	<?php include '../PUBLIC/header.php'; ?>
	<body>
		<section class="body">

			<?php require '../PUBLIC/navbarmenu.php'; ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Ajouter un nouveau taux</h2>

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
								<header class="card-header">
									<h2 class="card-title">Formulaire d'ajout de taux</h2>
								</header>
								<div class="card-body">
                                    <?php
                                        if ($errors==2) {
                                        echo '
                                            <div class="alert alert-success">
                                                <strong>Succès</strong> <br/>  
                                                <li>Le taux à été ajouter avec succès !</li>
                                            </div>
                                            ';
                                                }
                                        if ($errors==3) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <li>Ajout de taux non effectué, merci de vérifier les informations saisies</li>.
                                            </div>
                                            ';}

                                        if ($existe==1) {
                                        echo '
                                            <div class="alert alert-warning">
                                                <li>Le taux existe déjà dans le système, voulez-vous l\'activer ?</li>
                                            </div>
                                            ';
                                        }
                                    ?>
                                    <form class="form-horizontal" novalidate="novalidate" method="POST" action="addtaux.php" enctype="multipart/form-data">
                                        <input type="hidden" name="ajouter" value="1">
									<div class="row form-group pb-3">
										<div class="col-md-3">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Taux</label>
												<input type="double" class="form-control" name="taux" id="formGroupExampleInput" placeholder="exemple :  1.0 sans le %" require>
											</div>
										</div>
										<div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Pour</label>
												<select class="form-control populate" name="pour" required="">
                                                    <option value="0">Clinique</option>
                                                    <option value="1">Boutique</option>
                                                </select>
											</div>
										</div>
									</div>
								<footer class="card-footer text-end">
									<button class="btn btn-primary" type="submit" name="ajouter">Ajouter le taux</button>
								</footer>
                                </form>
							</section>
						</div>
					</div>
					<!-- end: page -->
				</section>
			</div>
            <?php include('../PUBLIC/footer.php');?>

		