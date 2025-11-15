<?php
    include('../PUBLIC/connect.php');
	session_start();
    $errors=0; $existe=0;

    if (isset($_POST['ajouter'])) {

    $req1 = $bdd->prepare('SELECT * FROM budgets WHERE nom_budget=?');
    $req1->execute(array($_POST['nom_budget']));
    while ($dta = $req1->fetch()) 
	{
      $existe=1;
    }

    if ($existe==0) {

    $req = $bdd->prepare('INSERT INTO budgets (nom_budget, type_budget, date_debut, date_fin, montant_initial, responsable, notes) VALUES(?,?,?,?,?,?,?)');
    $req->execute(array($_POST['nom_budget'], $_POST['type_budget'], $_POST['date_debut'], $_POST['date_fin'], $_POST['montant_initial'], $_POST['responsable'], $_POST['notes']));
    $errors=2;

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
						<h2>Ajouter un nouveau budget</h2>
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
                                                <li>Le budget à été ajouter avec succès !</li>
                                            </div>
                                            ';
                                                }
                                        if ($errors==3) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <li>Ajout de budget non effectué, merci de vérifier les informations saisies</li>.
                                            </div>
                                            ';}

                                        if ($existe==1) {
                                        echo '
                                            <div class="alert alert-warning">
                                                <li>Le budget existe déjà dans le système.</li>
                                            </div>
                                            ';
                                        }
                                    ?>
                                <form class="form-horizontal" novalidate="novalidate" method="POST" action="addbudget.php?addbudgets" enctype="multipart/form-data">
                                    <input type="hidden" name="ajouter" value="1">
									<div class="row form-group pb-3">
										<div class="col-md-6">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Intitulé du budget</label>
                                                <input type="text" name="nom_budget" class="form-control" placeholder="" required>
											</div>
										</div>
                                    </div>
                                    <div class="row form-group pb-3">
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Type de budget</label>
												<select class="form-control populate" name="type_budget" required="">
                                                    <option value="opérationnel">Opérationnel</option>
                                                    <option value="capital">Capital</option>
                                                    <option value="autre">Autres</option>
                                                </select>
											</div>
										</div>
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Date debut</label>
                                                <input type="date" name="date_debut" class="form-control" placeholder="" required>
											</div>
										</div>
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Date fin</label>
                                                <input type="date" name="date_fin" class="form-control" placeholder="" required>
											</div>
										</div>
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Montant initial</label>
												<input type="number" class="form-control" name="montant_initial" id="formGroupExampleInput" placeholder="" require>
											</div>
										</div>
										<div class="col-md-4">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Responsable du budget</label>
												<input type="text" class="form-control" name="responsable" id="formGroupExampleInput" placeholder="Nom du responsable" require>
											</div>
										</div>
									</div>
									<div class="row form-group pb-3">
										<div class="col-md-12">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Notes</label>
												<textarea class="form-control" name="notes" id="formGroupExampleInput" rows="8" placeholder="notes sur le budget"></textarea>
											</div>
										</div>
									</div>
                                    <footer class="card-footer text-end">
                                        <button class="btn btn-primary" type="submit" name="ajouter">Ajouter le budget</button>
                                    </footer>
                                </form>
							</section>
						</div>
					</div>
					<!-- end: page -->
				</section>
			</div>
            <?php include('../PUBLIC/footer.php');?>
		