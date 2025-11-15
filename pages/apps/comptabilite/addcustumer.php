<?php
    include('../PUBLIC/connect.php');
	session_start();
    $errors=0; $existe=0;

    if (isset($_POST['ajouter'])) {

    $req1 = $bdd->prepare('SELECT * FROM clients WHERE nom_client=? OR email=? OR telephone=?');
    $req1->execute(array($_POST['nom_client'], $_POST['email'], $_POST['telephone']));
    while ($dta = $req1->fetch(PDO::FETCH_ASSOC)) 
	{
      $existe=1;
    }

    if ($existe==0) {

    $req = $bdd->prepare('INSERT INTO clients (nom_client, adresse, telephone, email, type_client,taux) VALUES(?,?,?,?,?,?)');
    $req->execute(array($_POST['nom_client'], $_POST['adresse'], $_POST['telephone'], $_POST['email'], $_POST['type_client'], $_POST['taux']));
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
						<h2>Ajouter un nouveau clients</h2>

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
									<p class="card-subtitle">
										ATTENTION ! Merci de saisir les informations du clients correctement, vous pouvez aussi modifier les informations sur la rubrique modification information dans clients.
									</p>
								</header>
								<div class="card-body">
                                    <?php
                                        if ($errors==2) {
                                        echo '
                                            <div class="alert alert-success">
                                                <strong>Succès</strong> <br/>  
                                                <li>Le client à été ajouter avec succès !</li>
                                            </div>
                                            ';
                                                }
                                        if ($errors==3) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <li>Ajout du client non effectué, merci de vérifier les informations saisies</li>.
                                            </div>
                                            ';}

                                        if ($existe==1) {
                                        echo '
                                            <div class="alert alert-warning">
                                                <li>Le client existe déjà dans le système.</li>
                                            </div>
                                            ';
                                        }
                                    ?>
                                    <form class="form-horizontal" novalidate="novalidate" method="POST" action="addcustumer.php?new" enctype="multipart/form-data">
                                    <input type="hidden" name="ajouter" value="1">
									<div class="row form-group pb-3">
                                        <div class="col-md-4">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Nom du client</label>
                                                <input type="text" name="nom_client" class="form-control" placeholder="" required>
											</div>
										</div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Type de client</label>
                                                <select class="form-control populate" name="type_client" required="">
                                                    <option value="particulier">Particulier</option>
                                                    <option value="entreprise">Entreprise</option>
                                                    <option value="interne">Interne</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Téléphone</label>
												<input type="text" class="form-control" name="telephone" id="formGroupExampleInput" placeholder="" required>
											</div>
										</div>
										<div class="col-md-4">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Courriel</label>
												<input type="email" class="form-control" name="email" id="formGroupExampleInput" placeholder="" required>
											</div>
										</div>
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Taux (%) de prise en charge</label>
												<input type="number" class="form-control" name="taux" id="formGroupExampleInput" step="1" min="1" max="100" required>
											</div>
										</div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Ville de residence</label>
                                                <select class="form-control populate" id="villeSelect" onchange="updateQuartier()" data-plugin-selectTwo data-plugin-options='{ "minimumInputLength": 0 }' required>
                                                    <option value="">--- Choisir la ville ---</option>';
                                                        <?php
                                                        $coll = $bdd->prepare('SELECT id_ville, nom FROM adresses_villes');
                                                        $coll -> execute();
                                                        while ($ville = $coll->fetch(PDO::FETCH_ASSOC))
                                                        {
                                                            echo '<option value="'.$ville['id_ville'].'">'.$ville['nom'].'</option>';
                                                        } 
                                                        ?>
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Quartier</label>
                                                <select name="adresse" class="form-control populate" id="quartierSelect" data-plugin-selectTwo data-plugin-options='{ "minimumInputLength": 0 }' required>
                                                    <option value="">-- vous devez choisir une ville --</option>
                                                </select>
                                                <input type="hidden" id="hiddenquartierId" name="quartier_id" value="">
                                            </div>
                                        </div>
                                    </div>

                                    <footer class="card-footer text-end">
                                        <button class="btn btn-primary" type="submit" name="ajouter">Ajouter</button>
                                    </footer>
                                </form>
							</section>
						</div>
					</div>
					<!-- end: page -->
				</section>
			</div>
            <?php include('../PUBLIC/footer.php');?>
