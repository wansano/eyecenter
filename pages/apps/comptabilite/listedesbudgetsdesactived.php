<?php
include('../PUBLIC/connect.php');
session_start();
$errors=0;

if (isset($_POST['activer'])) 
{	
	$reponse = $bdd->prepare('SELECT * FROM budgets WHERE id_budget=?');
    $reponse ->execute(array($_POST['activer']));
	while ( $donnesbudgets = $reponse->fetch()) 
	{ 	$date_fin = $donnesbudgets['date_fin'];
		$date_debut = $donnesbudgets['date_debut'];
		$date_du_jour = date('Y-m-d');

	if ($date_du_jour >= $date_debut && $date_du_jour <= $date_fin) {
		$reponse = $bdd->prepare('UPDATE budgets SET status=1 WHERE id_budget=?');
		$reponse ->execute(array($_POST['activer']));
		$errors=2;
	} 
	else { $errors=3; }

	}
}

if (isset($_POST['supprimer'])) 
{
    $reponse = $bdd->prepare('UPDATE budgets SET status=3 WHERE id_budget=?');
    $reponse ->execute(array($_POST['supprimer']));
    $errors=4;
} 


include('../PUBLIC/header.php');
?>

	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Liste et disponibilité des comptes.</h2>

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
						<div class="row">
							<div class="col">
								<section class="card">
									<header class="card-header">
										<h2 class="card-title">
											Liste des budgets
										</h2>
									</header>
									<div class="card-body">
										<?php 
                                            if ($errors==2) {
                                            echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Le budget à été activé avec succès, il est possible de l\'utiliser à partir de l\'instant.</li>
                                                </div>
                                                '; }

											if ($errors==3) {
												echo '
													<div class="alert alert-danger">
													<li><strong>Erreur !</strong>
													<br>Il n\'est pas possible d\'activer le budget car la date du jour n\'est pas inclus entre la date de debut et fin de l\'utilisation prevu pour ce budget.</li>
													</div>
													'; }

                                            if ($errors==4) {
                                                echo '
                                                <div class="alert alert-warning">
                                                <li><strong>Succès !</strong>
                                                <br>Ce budget à été archiver avec succès.</li>
                                                </div>
                                                '; }
                                        ?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
                                                    <th>N°</th>
                                                    <th>BUDGET</th>
                                                    <th>DATE DEBUT</th>
                                                    <th>DATE FIN</th>
                                                    <th>MONTANT INITIAL</th>
                                                    <th>MONTANT UTILISE</th>
                                                    <th>MONTANT RESTANT</th>
                                                    <th>TYPE BUDGET</th>
                                                    <th>RESPONSABLE</th>
													<th>ACTION</th>
												</tr>
											</thead>
											<tbody>
											<?php
												$reponse1 = $bdd->prepare('SELECT * FROM budgets ORDER BY id_budget');
												$reponse1 -> execute();
												while ($donnees1 = $reponse1->fetch())
												{  
													echo' <tr>
													<td>'.$donnees1['id_budget'].'</td>
													<td>'.$donnees1['nom_budget'].'</a></td>
													<td>'.$donnees1['date_debut'].'</td>
													<td>'.$donnees1['date_fin'].'</td>
													<td>'.number_format($donnees1['montant_initial']).' GNF</td>
													<td>'.number_format($donnees1['montant_utilisé']).' GNF</td>
													<td>'.number_format($donnees1['montant_restant']).' GNF</td>
													<td>'.$donnees1['type_budget'].'</a></td>
													<td>'.$donnees1['responsable'].'</a></td>
													<td>';
													if ($donnees1['status']==0) {
                                                        echo'
                                                        <form action="listedesbudgetsdesactived.php?id='.$donnees1['id_budget'].'" method="post">
                                                        <input type="hidden" name="activer" value="'.$donnees1['id_budget'].'">
                                                        <button type="submit" class="btn btn-sm btn-success"><i class="fa fa-unlock-alt"></i> activé</button>
                                                        </form>
                                                        
                                                        <form action="listedesbudgetsdesactived.php&id='.$donnees1['id_budget'].'" method="post">
                                                        <input type="hidden" name="supprimer" value="'.$donnees1['id_budget'].'">
                                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-times"></i> supprimer</button>
                                                        </form>
                                                        ';}
													echo'
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
            <?php include('../PUBLIC/footer.php');?>