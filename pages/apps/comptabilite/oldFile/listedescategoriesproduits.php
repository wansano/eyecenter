<?php
include('../PUBLIC/connect.php');
session_start();
$errors=0;

if (isset($_POST['activer'])) 
{
    $reponse = $bdd->prepare('UPDATE categorie_produits SET status=1 WHERE id_categorie=?');
    $reponse ->execute(array($_POST['activer']));
    $errors=2;
}

if (isset($_POST['desactiver'])) 
{
    $reponse = $bdd->prepare('UPDATE categorie_produits SET status=0 WHERE id_categorie=?');
    $reponse ->execute(array($_POST['desactiver']));
    $errors=3;
}

if (isset($_POST['supprimer'])) 
{
    $reponse = $bdd->prepare('UPDATE categorie_produits SET status=3 WHERE id_categorie=?');
    $reponse ->execute(array($_POST['supprimer']));
    $errors=4;
} 

?>

<?php include('../PUBLIC/header.php'); ?>

	<body>
		<section class="body">

			<?php require '../PUBLIC/navbarmenu.php'; ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Liste des catégories de verres</h2>
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
                                                <br>Catégorie lentilles activé avec succès.</li>
                                                </div>
                                                '; }
                                            if ($errors==3) {
                                                echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Catégorie lentilles desactivé avec succès.</li>
                                                </div>
                                                '; }
                                            if ($errors==4) {
                                                echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Catégorie lentilles supprimer avec succès.</li>
                                                </div>
                                                '; }
                                        ?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
													<th>CATEGORIE</th>
													<th>CODE</th>
                                                    <th>QTE</th>
													<th>PRIX ACHAT</th>
													<th>PRIX VENTE</th>
                                                    <th>PRIX MONTAGE</th>
                                                    <th>DESCRIPTION</th>
													<th>ACTION</th>
												</tr>
											</thead>
											<tbody>
											<?php
                                                $reponse1 = $bdd->prepare('SELECT * FROM categorie_produits ORDER BY id_categorie');
                                                $reponse1->execute();
                                                while ($donnees1 = $reponse1->fetch()) {
                                                    $status = $donnees1['status'];
                                                    if ($status != 3) {
                                                        echo '<tr>';
                                                        echo '<td>' . htmlspecialchars($donnees1['categorie']) . '</td>';
                                                        echo '<td>' . htmlspecialchars($donnees1['code_categorie']) . '</td>';
                                                        echo '<td>' . htmlspecialchars($donnees1['quantite']) . '</td>';
                                                        echo '<td>' . number_format((float)$donnees1['prix_achat'], 0, ',', ' ') . ' ' . htmlspecialchars($devise) . '</td>';
                                                        echo '<td>' . number_format((float)$donnees1['prix_vente'], 0, ',', ' ') . ' ' . htmlspecialchars($devise) . '</td>';
                                                        echo '<td>' . number_format((float)$donnees1['prix_montage'], 0, ',', ' ') . ' ' . htmlspecialchars($devise) . '</td>';
                                                        echo '<td>' . htmlspecialchars($donnees1['description']) . '</td>';
                                                        echo '<td>';
                                                        if ($status==0) { echo '
                                                        <form action="#?id='.$donnees1['id_categorie'].'" method="post">
                                                        <input type="hidden" name="activer" value="'.$donnees1['id_categorie'].'">
                                                        <button type="submit" class="btn btn-sm btn-warning"><i class="fa fa-unlock-alt"></i>activé</button>
                                                        </form>
                                                        <a href="editproductcategorie.php?idcategorie='.$donnees1['id_categorie'].'" class="btn btn-sm btn-info"><i class="fa fa-edit"></i> modifier</a>
                                                        <form action="#?id='.$donnees1['id_categorie'].'" method="post">
                                                        <input type="hidden" name="supprimer" value="'.$donnees1['id_categorie'].'">
                                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i> Supprimer</button>
                                                        </form>'
                                                        ;}
                                                    
                                                        if ($status==1) {
                                                        echo' 
                                                        <form action="#?id='.$donnees1['id_categorie'].'" method="post">
                                                        <input type="hidden" name="desactiver" value="'.$donnees1['id_categorie'].'">
                                                        <button type="submit" class="btn btn-sm btn-success"><i class="fa fa-lock"></i> désactivé</button>
                                                        </form>';
                                                        };
                                                        echo '</td>';
                                                        echo '</tr>';
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
