<?php
include('../PUBLIC/connect.php');
session_start();

$errors=0;

  if (isset($_POST['annuler'])) {
    $reponse = $bdd->prepare('UPDATE affectations SET status=1 WHERE id_affectation=?');
    $reponse ->execute(array($_POST['annuler']));
    $errors=3;
  }                   
  

  include('../PUBLIC/header.php');
	?>
	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Liste des rembourrsement éffectués</h2>
					</header>

					<!-- start: page -->
                        <div class="col-md-12">
						<div class="row">
							<div class="col">
								<section class="card">
									<header class="card-header">
										<h2 class="card-title">Liste des remboursements éffectués
										</h2>
									</header>
									<div class="card-body">
									<?php
                                            if ($errors==3) {
                                            echo '
                                                <div class="alert alert-success">
                                                <strong>Succès !</strong> <br/>  
                                                <li>Annulation du remboursement effectué avec succès.</li>
                                                <li>Le dossier du patient à été transmis au service concerné. Merci de rediriger le patient vers le service traitant.</li>
                                                </div>
                                                ';
                                                    }
                                        ?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
													<th>DATE</th>
                                                    <th>DOSSIER</th>
													<th>PATIENT</th>
													<th>CONTACT</th>
													<th>MOTIF</th>
													<th>MONTANT PAYE</th>
                                                    <th>PAYER PAR</th>
													<th>STATUS</th>
												</tr>
											</thead>
											<tbody>
											    <?php
												$reponse1 = $bdd->prepare('SELECT * FROM remboursements ORDER BY id_remboursement');
												$reponse1 -> execute();
												while ($donnees1 = $reponse1->fetch())
												{  
													echo' <tr>
													<td>'.$donnees1['date_ajout'].'</td>
                                                    <td>'.$donnees1['patient'].'</td>
													<td>'.patient($donnees1['patient']).'</td>
													<td>'.contact($donnees1['patient']).'</td>
													<td>'.type($donnees1['types']).'</td>
													<td>'.number_format($donnees1['montant_paye']).' '.$devise.'</td>
                                                    <td>'.compte($donnees1['compte']).'</td>
													<td>
                                                    <a href="bonderemboursement.php?affectation='.$donnees1['id_affectation'].'" target="_blank" class="btn btn-sm btn-info"> voir le bon</a>
                                                    </td>';
                                                    }
												?>
											</tbody>
										</table>
									</div>
								</section>
							</div>
						</div>
				    </section>
			    </div>
                
					
            <?php include('../PUBLIC/footer.php');?>