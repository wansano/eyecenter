<?php
include('../PUBLIC/connect.php');
session_start();
$errors=0;

if (isset($_POST['activer'])) 
{
    $reponse = $bdd->prepare('UPDATE fournisseur_produit SET status=1 WHERE id_fournisseur=?');
    $reponse ->execute([$_POST['activer']]);
    $errors=2;
}

if (isset($_POST['desactiver'])) 
{
    $reponse = $bdd->prepare('UPDATE fournisseur_produit SET status=0 WHERE id_fournisseur=?');
    $reponse ->execute([$_POST['desactiver']]);
    $errors=3;
}

if (isset($_POST['supprimer'])) 
{
    $reponse = $bdd->prepare('UPDATE fournisseur_produit SET status=3 WHERE id_fournisseur=?');
    $reponse ->execute([$_POST['supprimer']]);
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
						<h2>Situation des fournisseurs</h2>
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
										<h2 class="card-title">Situation des fournisseurs</h2>
									</header>
									<div class="card-body">
                                        <?php 
                                            if ($errors==2) {
                                            echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Compte fournisseur activé avec succès.</li>
                                                </div>
                                                '; }
                                            if ($errors==3) {
                                                echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Compte du fournisseur desactivé avec succès.</li>
                                                </div>
                                                '; }
                                            if ($errors==4) {
                                                echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Compte fournisseur supprimer avec succès.</li>
                                                </div>
                                                '; }
                                        ?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
													<th>ID</th>
													<th>FOURNISSEUR</th>
													<th>TYPE</th>
                                                    <th>RESPONSABLE</th>
                                                    <th>COMPTE DEBIT</th>
                                                    <th>COMPTE CREDIT</th>
                                                    <th>SOLDE A PAYER</th>
													<th>STATUS</th>
												</tr>
											</thead>
											<tbody>
											<?php
                                                $reponse1 = $bdd->prepare('SELECT * FROM fournisseur_produit WHERE solde > ? ORDER BY id_fournisseur');
                                                $reponse1 -> execute([0]);
                                                while ($donnees1 = $reponse1->fetch())
                                                    { $status = $donnees1['status'];

                                                    echo' <tr>';
													if ($status!=3){
													echo'
                                                    <td>'.$donnees1['id_fournisseur'].'</td>
                                                    <td>'.$donnees1['fournisseur'].'</td>
                                                    <td>'.$donnees1['type_fournisseur'].'</td>
                                                    <td>'.$donnees1['responsable'].'</td>
                                                    <td>'.number_format($donnees1['debit']).' '.$devise.'</td>
                                                    <td>'.number_format($donnees1['credit']).' '.$devise.'</td>
                                                    <td>'.number_format($donnees1['solde']).' '.$devise.'</td>
                                                    <td><a href="payementfournisseurs.php?fournisseur='.$donnees1['id_fournisseur'].'" class="btn btn-sm btn-info" >proceder au paiement</a></td>
                                                    </tr>';
                                                        }
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
	