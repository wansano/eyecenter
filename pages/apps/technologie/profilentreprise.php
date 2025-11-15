<?php
include('../PUBLIC/connect.php');
session_start();
$errors=0; $existe=0;

    $reponse1 = $bdd->prepare('SELECT * FROM profil_entreprise');
    $reponse1 -> execute();
        while ($profil = $reponse1->fetch())
        {
            $denomination=$profil['denomination'];
            $adresse=$profil['adresse'];
            $standard=$profil['phone'];
            $courriel=$profil['email'];
            $sigle=$profil['sigle'];
            $dg=$profil['responsable'];
            $agrement = $profil['arrete'];
        }

if (isset($_POST['profil']))
{
$req = $bdd->prepare('UPDATE profil_entreprise SET  denomination=?, sigle=?, adresse=?, phone=?, email=?, responsable=?, arrete=? WHERE id=?');
$req->execute(array( $_POST['denomination'], $_POST['sigle'], $_POST['adresse'], $_POST['phone'], $_POST['courriel'], $_POST['responsable'], $_POST['agrement'], $_POST['profil']));
$errors=2;
}

include('../PUBLIC/header.php');
?>

	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Profillage de l'institution</h2>

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
									<h2 class="card-title">Information de l'Institution</h2>
									<p class="card-subtitle">
										ATTENTION ! Merci de saisir les bonnes informations de l'institution.
									</p>
								</header>
								<div class="card-body">
                                    <?php
                                        if ($errors==2) {
                                        echo '
                                            <div class="alert alert-success">
                                            <strong>Succès</strong> <br/>  
                                            <li>Modification des informations éffectué avec succès.</li> 
                                            </div>
                                            ';
                                                }
                                        if ($errors==3) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <li>Enregistrement non effectué, merci de vérifier les informations saisies si elles sont correctes.</li>.
                                            </div>
                                            ';}

                                        if ($existe==1) {
                                        echo '
                                            <div class="alert alert-warning">
                                                <li>Vous n\'avez éffectuer aucune modification.</li>
                                            </div>
                                            ';
                                                }
                                    ?>
                                    <form class="form-horizontal" novalidate="novalidate" method="POST" action="profilentreprise.php?pe=entreprise" enctype="multipart/form-data">
                                        <input type="hidden" name="profil" value="1" >
									<div class="row form-group pb-3">
										<div class="col-md-6">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Denomination de l'institution</label>
                                                <input type="text" name="denomination" class="form-control" placeholder="" value="<?php echo $denomination; ?>" required>
											</div>
										</div>
										<div class="col-md-6">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Adresse de l'institution</label>
												<input type="text" class="form-control" name="adresse" id="formGroupExampleInput" placeholder="" value="<?php echo $adresse; ?>" required>
											</div>
										</div>
                                        <div class="col-md-6">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">N° Agrement de l'institution</label>
												<input type="text" class="form-control" name="agrement" id="formGroupExampleInput" placeholder="" value="<?php echo $agrement; ?>">
											</div>
										</div>
                                        <div class="col-md-6">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Responsable de l'institution</label>
												<input type="text" class="form-control" name="responsable" id="formGroupExampleInput" placeholder="" value="<?php echo $dg; ?>" required>
											</div>
										</div>
                                        <div class="col-md-1">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Sigle</ins></label>
												<input type="text" class="form-control" name="sigle" id="formGroupExampleInput" placeholder="" value="<?php echo $sigle; ?>">
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Email contact</label>
												<input type="email" class="form-control" name="courriel" id="formGroupExampleInput" placeholder="" value="<?php echo $courriel; ?>" required>
											</div>
										</div>
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Telephone standard</label>
												<input type="text" class="form-control" name="phone" id="formGroupExampleInput" placeholder="" value="<?php echo $standard; ?>" required>
											</div>
										</div>
                                        <div class="col-md-3">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Logo de l'institution</label>
												<input type="file" class="form-control" name="logo" id="formGroupExampleInput" placeholder="">
											</div>
										</div>
                                        <div class="col-md-3">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Cachet standard </label>
												<input type="file" class="form-control" name="cachet" id="formGroupExampleInput" placeholder="">
											</div>
										</div>
									</div>
								</div>
								<footer class="card-footer text-end">
									<button class="btn btn-primary" type="submit">Modifier les information</button>
								</footer>
                                </form>
							</section>
						</div>
					</div>
					<!-- end: page -->
				</section>
			</div>
            <?php include('../PUBLIC/footer.php');?>