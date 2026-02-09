<?php
include('../PUBLIC/connect.php');
session_start();
$errors=0;

if (isset($_POST['annuler'])) 
{
    $reponse = $bdd->prepare('UPDATE approvisionnements SET statut=? WHERE id_appro=?');
    $reponse ->execute(array("annulé",$_POST['annuler']));
    $errors=2;
}

function fournisseur($nom){
    include('../PUBLIC/connect.php');
    $reponse1 = $bdd->prepare('SELECT * FROM fournisseur_produit WHERE id_fournisseur=?');
    $reponse1 -> execute(array($nom));
    $fournisseur=" ";
    while ($donnees1 = $reponse1->fetch())
    {
        $fournisseur=$donnees1['fournisseur'];

        }
        return $fournisseur;
 }
?>

<?php include('../PUBLIC/header.php'); ?>

	<body>
		<section class="body">

			<?php require '../PUBLIC/navbarmenu.php'; ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Liste des models de produits </h2>
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
						<div class="row">
							<div class="col">
								<section class="card">
									<header class="card-header">
										<h2 class="card-title">Liste des commandes des produits</h2>
									</header>
									<div class="card-body">
                                        <?php 
                                            if ($errors==2) {
                                            echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Model activé avec succès.</li>
                                                </div>
                                                '; }
                                            if ($errors==3) {
                                                echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Model desactivé avec succès.</li>
                                                </div>
                                                '; }
                                            if ($errors==4) {
                                                echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Model supprimer avec succès.</li>
                                                </div>
                                                '; }
                                        ?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
													<?php 
                                                    if ($_GET['commande']== "guessing") { echo'
                                                        <th>N°</th>
                                                        <th>DATE</th>
                                                        <th>TYPE </th>
                                                        <th>QUANTITE</th>
                                                        <th>FOURNISSEUR</th>
                                                        <th>DESCRIPTION</th>
                                                        <th>ACTION</th>';
                                                    }
                                                    if ($_GET['commande']== "cancelled") { echo'
                                                        <th>N°</th>
                                                        <th>DATE</th>
                                                        <th>TYPE </th>
                                                        <th>QUANTITE</th>
                                                        <th>FOURNISSEUR</th>
                                                        <th>DESCRIPTION</th>
                                                        <th>STATUS</th>';
                                                    }
                                                    if ($_GET['commande']== "delivred") { echo'
                                                        <th>N°</th>
                                                        <th>DATE</th>
                                                        <th>TYPE </th>
                                                        <th>QTE COMMANDE</th>
                                                        <th>QTE LIVRE</th>
                                                        <th>FOURNISSEUR</th>
                                                        <th>DESCRIPTION</th>
                                                        <th>STATUS</th>';
                                                    }
                                                    
                                                    ?>
												</tr>
											</thead>
											<tbody>
											<?php
                                                if ($_GET['commande']== "guessing") {
                                                $reponse1 = $bdd->prepare('SELECT * FROM approvisionnements WHERE statut=? ORDER BY id_appro');
                                                $reponse1 -> execute(array("en attente"));
                                                while ($donnees1 = $reponse1->fetch())
                                                    {
                                                        echo'
                                                        <tr>
                                                        <td>'.$donnees1['no_commande'].'</td>
                                                        <td>'.$donnees1['date_commande'].'</td>
                                                        <td>'.$donnees1['type_commande'].'</td>
                                                        <td>'.$donnees1['quantite_commande'].'</td>
                                                        <td>'.fournisseur($donnees1['id_fournisseur']).'</td>
                                                        <td>'.$donnees1['description'].'</td>
                                                        <td>
                                                            <form action="#?id='.$donnees1['id_appro'].'" method="post">
                                                            <input type="hidden" name="annuler" value="'.$donnees1['id_appro'].'">
                                                            <a href="editcommandproduct.php?idcommande='.$donnees1['id_appro'].'" class="btn btn-sm btn-info"><i class="fa fa-edit"></i> modifier</a>
                                                            <button type="submit" class="btn btn-sm btn-warning"><i class="fa fa-unlock-alt"></i> annuler</button>
                                                            </form>
                                                        </td>
                                                        </tr>';
                                                        }
													}
                                                if ($_GET['commande']== "cancelled") {
                                                    $reponse1 = $bdd->prepare('SELECT * FROM approvisionnements WHERE statut=? ORDER BY id_appro');
                                                    $reponse1 -> execute(array("annulé"));
                                                    while ($donnees1 = $reponse1->fetch())
                                                    {
                                                        echo'
                                                        <tr>
                                                        <td>'.$donnees1['no_commande'].'</td>
                                                        <td>'.$donnees1['date_commande'].'</td>
                                                        <td>'.$donnees1['type_commande'].'</td>
                                                        <td>'.$donnees1['quantite_commande'].'</td>
                                                        <td>'.fournisseur($donnees1['id_fournisseur']).'</td>
                                                        <td>'.$donnees1['description'].'</td>
                                                        <td><a class="btn btn-sm btn-danger">annulé</a></td>
                                                        </tr>';
                                                        }
                                                    }
                                                
                                                    if ($_GET['commande']== "delivred") {
                                                    $reponse1 = $bdd->prepare('SELECT * FROM approvisionnements WHERE statut=? ORDER BY id_appro');
                                                    $reponse1 -> execute(array("livré"));
                                                    while ($donnees1 = $reponse1->fetch())
                                                    {
                                                        echo'
                                                        <tr>
                                                        <td>'.$donnees1['no_commande'].'</td>
                                                        <td>'.$donnees1['date_commande'].'</td>
                                                        <td>'.$donnees1['type_commande'].'</td>
                                                        <td>'.$donnees1['quantite_commande'].'</td>
                                                        <td>'.$donnees1['quantite_livre'].'</td>
                                                        <td>'.fournisseur($donnees1['id_fournisseur']).'</td>
                                                        <td>'.$donnees1['description'].'</td>
                                                        <td><a class="btn btn-sm btn-success">livré</a></td>
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
        <?php include '../PUBLIC/footer.php';?>
		