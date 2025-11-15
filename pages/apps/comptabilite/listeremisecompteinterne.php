<?php
include('../PUBLIC/connect.php');
// require_once('../public/fonction.php');
session_start();
$errors=0;


 function par($nom){
	include('../PUBLIC/connect.php');
	$reponse1 = $bdd->prepare('SELECT * FROM employes WHERE id_employe=?');
	$reponse1 -> execute(array($nom));
	$par=" ";
		while ($donnees1 = $reponse1->fetch())
			{
			$par=$donnees1['nom_employe'];

			}
			return $par;
			}

include('../PUBLIC/header.php');
	?>

	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Liste des remises de comptes</h2>
					</header>

					<!-- start: page -->
					<div class="col-md-12">
						<div class="row">
							<div class="col">
								<section class="card">
									<div class="card-body">
										<table class="table table-bordered table-striped mb-0" id="datatable-default">

											<thead>
												<tr>
                                                    <th>DATE REMISE</th>
													<th>EMPLOYE</th>
													<th>MONTANT</th>
                                                    <th>TYPE REMISE</th>
                                                    <th>MODE PAIEMENT</th>
													<th>REFERENCE</th>
													<th>COMPTE DEBITE</th>
                                                    <th>COMPTE CREDITE</th>
													<th>ACTION</th>
												</tr>
											</thead>
											<tbody>
											<?php
												$reponse1 = $bdd->prepare('SELECT * FROM remise_de_compte WHERE id_employe!=? ORDER BY id_remise');
												$reponse1 -> execute(array('NULL'));
												while ($donnees1 = $reponse1->fetch())
												{  
													echo' <tr>
													<td>'.$donnees1['date_remise'].'</td>
													<td>'.par($donnees1['id_employe']).'</td>
													<td>'.number_format($donnees1['montant']).' GNF</td>
													<td>'.$donnees1['type_remise'].'</td>
													<td>'.$donnees1['mode_paiement'].'</td>
													<td>'.$donnees1['reference'].'</td>
													<td>'.type_paiement($donnees1['id_compte2']).'</td>
													<td>'.type_paiement($donnees1['id_compte']).'</td>
													<td> 
														<a href="#.php?id='.$donnees1['id_remise'].'" type="button" class="btn btn-sm btn-info"><i class="fa fa-print"></i> bordereau</a>
													</td>';
													
												}
											?>
											</tbody>
										</table>
									</div>
								</section>
							</div>
						</div>
					</div>

			    </div>
            <?php include('../PUBLIC/footer.php');?>