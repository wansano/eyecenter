<?php
    include('../PUBLIC/connect.php');
	session_start();
    $errors=0; $existe=0;

    if (isset($_POST['ajouter'])) {

    $req1 = $bdd->prepare('SELECT * FROM remise_de_compte WHERE reference=?');
    $req1->execute(array($_POST['reference']));
    while ($dta = $req1->fetch()) 
	{
      $existe=1;
    }

    if ($existe==0) {
    
	$req0 = $bdd->prepare('SELECT * FROM comptes WHERE id_compte=? ');
    $req0-> execute(array($_POST['id_compte']));
	while ($donnes = $req0->fetch())
	{
		$credit = $donnes['debit']; 
	}

    $req0 = $bdd->prepare('SELECT * FROM comptes WHERE id_compte=? ');
    $req0-> execute(array($_POST['id_compte2']));
	while ($donnes = $req0->fetch())
	{
		$debit = $donnes['debit'];
        $credit2 = $donnes['credit'];
	}

    if ($_POST['montant'] <= $debit) {
       
        $montantdebit = $debit-$_POST['montant'];
        $montantcredit2 = $credit2+$_POST['montant'];

		$req3 = $bdd->prepare('UPDATE comptes SET debit=?, date_update=CURRENT_TIMESTAMP() WHERE id_compte=?');
        $req3->execute(array($montantdebit, $_POST['id_compte2']));
        $req3 = $bdd->prepare('UPDATE comptes SET credit=? WHERE id_compte=?');
		$req3->execute(array($montantcredit2, $_POST['id_compte2']));

        $montantcredit = $credit+$_POST['montant'];
		$req3 = $bdd->prepare('UPDATE comptes SET debit=?, date_update=CURRENT_TIMESTAMP() WHERE id_compte=?');
		$req3->execute(array($montantcredit, $_POST['id_compte']));

        $req = $bdd->prepare('INSERT INTO remise_de_compte (id_employe, date_remise, montant, type_remise, mode_paiement, reference, id_compte, id_compte2, notes) VALUES(?,?,?,?,?,?,?,?,?)');
        $req->execute(array($_POST['id_employe'], $_POST['date_remise'], $_POST['montant'], $_POST['type_remise'], $_POST['mode_paiement'], $_POST['reference'], $_POST['id_compte'], $_POST['id_compte2'], $_POST['notes']));

        $errors=2;

    } else { $errors=3;}

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
						<h2>Remise de compte</h2>

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
										ATTENTION ! Merci de saisir les informations du compte correctement, vous pouvez aussi modifier les informations sur la rubrique modification information dans comptes.
									</p>
								</header>
								<div class="card-body">
                                    <?php
                                        if ($errors==2) {
                                        echo '
                                            <div class="alert alert-success">
                                                <strong>Succès</strong> <br/>  
                                                <li>Remise de compte éffectuée avec succès !</li>
                                            </div>
                                            ';
                                                }
                                        if ($errors==3) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <li>Remise de compte non effectuée, le montant saisie est supérieur au solde du compte</li>.
                                            </div>
                                            ';}

                                        if ($existe==1) {
                                        echo '
                                            <div class="alert alert-warning">
                                                <li>La remise à été déjà éffectuée.</li>
                                            </div>
                                            ';
                                        }
                                    ?>
                                    <form class="form-horizontal" novalidate="novalidate" method="POST" action="addremiseaccountin.php?newinaccount" enctype="multipart/form-data">
                                    <input type="hidden" name="ajouter" value="1">
									<div class="row form-group pb-3">
										<div class="col-md-4">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Remis par l'employé</label>
												<select class="form-control populate" name="id_employe" required="">
													<option>-------- Choisir le remettant --------</option>'
													<?php 
                                                        $employe = $bdd->prepare('SELECT * FROM employes WHERE status=?');
                                                        $employe -> execute(array(1));
                                                        while ($employes = $employe->fetch())
                                                        {
                                                            echo '<option value="'.$employes['id_employe'].'">'.$employes['nom_employe'].'</option>';
                                                        } 
                                                    ?>
                                                </select>
											</div>
										</div>
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Date de remise</label>
												<input type="date" class="form-control" name="date_remise" id="formGroupExampleInput" placeholder="" require>
											</div>
										</div>
										<div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Montant de remise</label>
												<input type="number" class="form-control" name="montant" id="formGroupExampleInput" placeholder="" require>
											</div>
										</div>
										<div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Type de remise</label>
												<select class="form-control populate" name="type_remise" required="">
													<option value="approvisionnement">Approvisionnement</option>
													<option value="paiement">Paiement</option>
													<option value="remboursement">Remboursement</option>
													<option value="crédit">Crédit</option>
												</select>
											</div>
										</div>
										<div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Mode de paiement</label>
												<select class="form-control populate" name="mode_paiement" required="">
													<option value="espèces">Espèces</option>
													<option value="chèque">Chèque</option>
													<option value="carte">Carte</option>
													<option value="virement">Virement</option>
												</select>
											</div>
										</div>
										<div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Référence de la remise</label>
												<input type="text" class="form-control" name="reference" id="formGroupExampleInput" placeholder="" require>
											</div>
										</div>
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Remise éffectuée sur le compte</label>
												<select class="form-control populate" name="id_compte" required="">
													<?php 
                                                        $type = $bdd->prepare('SELECT * FROM comptes');
                                                        $type -> execute();
                                                        while ($type_paiement = $type->fetch())
                                                        {
                                                            echo '<option value="'.$type_paiement['id_compte'].'">'.$type_paiement['nom_compte'].'</option>';
                                                        } 
                                                    ?>
												</select>
											</div>
										</div>
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Remise tirée sur le compte</label>
												<select class="form-control populate" name="id_compte2" required="">
													<?php 
                                                        $type = $bdd->prepare('SELECT * FROM comptes');
                                                        $type -> execute();
                                                        while ($type_paiement = $type->fetch())
                                                        {
                                                            echo '<option value="'.$type_paiement['id_compte'].'">'.$type_paiement['nom_compte'].'</option>';
                                                        } 
                                                    ?>
												</select>
											</div>
										</div>
									</div>
									<div class="row form-group pb-3">
										<div class="col-md-12">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Notes</label>
												<textarea class="form-control" name="notes" rows="5" required=""></textarea>
											</div>
										</div>
									</div>
									<footer class="card-footer text-end">
										<button class="btn btn-primary" type="submit" name="ajouter">Effectuer la remise</button>
									</footer>
                                </form>
							</section>
						</div>
					</div>
					<!-- end: page -->
				</section>
			</div>
            <?php include('../PUBLIC/footer.php');?>
		