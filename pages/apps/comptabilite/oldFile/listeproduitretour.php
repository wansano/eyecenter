<?php
include('../PUBLIC/connect.php');
session_start();
$errors=0;


if (isset($_POST['supprimer'])) 
{
    $reponse = $bdd->prepare('UPDATE produits SET retour=1 WHERE id_produit=?');
    $reponse ->execute(array($_POST['supprimer']));
    $errors=4;
} 

$reponse1 = $bdd->prepare('SELECT * FROM seuilstock WHERE id_seuil=?');
$reponse1 -> execute(array(1));
    while ($donnees1 = $reponse1->fetch())
    {
        $seuilreserve=$donnees1['seuilreserve'];
        $delaisappro=$donnees1['delaireapprovisionnement'];
        $seuilrupture=$donnees1['seuilrupture'];
    }

?>

<?php include('../PUBLIC/header.php'); ?>

	<body>
		<section class="body">

			<?php require '../PUBLIC/navbarmenu.php'; ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Liste des Inventaires de produits à retourner</h2>
					</header>

					<!-- start: page -->
					<div class="col-md-12">
						<div class="row">
							<div class="col">
								<section class="card">
									<div class="card-body">
                                        <?php 
                                            if ($errors==2) {
                                            echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Produit supprimer avec succès.</li>
                                                </div>
                                                '; }
                                        ?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
													<th>CODE PRODUIT</th>
                                                    <th>MODEL</th>
                                                    <th>COULEUR</th>
													<th>BON LIVRAISON</th>
													<th>DATE AJOUT</th>
													<th>ACTION</th>
												</tr>
											</thead>
											<tbody>
											<?php
                                                $reponse1 = $bdd->prepare('SELECT p.* FROM produits p JOIN seuilstock s ON 1 = 1  
                                                WHERE p.vendu=0 AND retour=0 AND p.date_creation <= DATE_SUB(CURDATE(), INTERVAL s.delaireapprovisionnement DAY);
                                                ');
                                                $reponse1 -> execute([0]);
                                                $produits = $reponse1->fetchAll(PDO::FETCH_ASSOC);
                                                foreach ($produits as $produit) {
                                                        echo '<tr>
                                                            <td>'.$produit['code_produit'].'</td>
                                                            <td>'.model($produit['id_model']).'</td>
                                                            <td>'.$produit['couleur'].'</td>
                                                            <td>'.$produit['no_livraison'].'</td>
                                                            <td>'.$produit['date_creation'].'</td>
                                                            <td>
                                                            <form action="#" method="post">
                                                            <input type="hidden" name="supprimer" value="'.$produit['id_produit'].'">
                                                            <button type="submit" class="btn btn-sm btn-warning">Retourner</button>
                                                            </form>
                                                            </td>
                                                        </tr>';
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
		