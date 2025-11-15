<?php
include('../PUBLIC/connect.php');
session_start();
$errors=0;

function client($nom)
{
    include('../PUBLIC/connect.php');
    $reponse1 = $bdd->prepare('SELECT * FROM patients WHERE id_patient=?');
    $reponse1->execute(array($nom));
    $client = " ";
    while ($donnees1 = $reponse1->fetch()) {
        $client = $donnees1['nom_patient'];
    }
    return $client;
}

function codemonture($nom)
{
    include('../PUBLIC/connect.php');
    $reponse1 = $bdd->prepare('SELECT * FROM produits WHERE id_produit=?');
    $reponse1->execute(array($nom));
    $codemonture = " ";
    while ($donnees1 = $reponse1->fetch()) {
        $codemonture = $donnees1['code_produit'];
    }
    return $codemonture;
}

function codelentille($nom)
{
    include('../PUBLIC/connect.php');
    $reponse1 = $bdd->prepare('SELECT * FROM categorie_produits WHERE id_categorie=?');
    $reponse1->execute(array($nom));
    $codelentille = " ";
    while ($donnees1 = $reponse1->fetch()) {
        $codelentille = $donnees1['code_categorie'];
    }
    return $codelentille;
}

?>

<?php include('../PUBLIC/header.php'); ?>

	<body>
		<section class="body">

			<?php require '../PUBLIC/navbarmenu.php'; ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Liste des dernières ventes</h2>
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
													<th>N°</th>
                                                    <th>DATE</th>
                                                    <th>TYPE DE VENTE</th>
                                                    <th>PRODUIT VENDUS</th>
                                                    <th>MONTANT</th>
                                                    <th>CLIENT</th>
												</tr>
											</thead>
											<tbody>
											<?php
                                                $query = $bdd->prepare('SELECT * FROM ventes_produits ORDER BY id_vente DESC LIMIT 0,50');
                                                $query->execute();
                                                $resultats = $query->fetchAll(PDO::FETCH_ASSOC);
                                                foreach ($resultats as $resultat) {
                                                    $produit  = $resultat['id_produit'];
                                                    $categorie = $resultat['id_produit'];
                                                    echo '<tr>
                                                    <td>ECV' . htmlspecialchars($resultat['id_vente']) . '</td>
                                                    <td>' . htmlspecialchars($resultat['date_vente']) . '</td>';
                                                    if ($produit>0 AND $categorie> 0) {
                                                        echo '<td> Lunettes </td>';
                                                    } 
                                                    if ($produit==0) {
                                                        echo '<td> Verres </td>';
                                                    }
                                                    if ($produit==0 && $categorie==0) {
                                                        echo '<td> Monture </td>';
                                                    }
                                                    if ($produit==0) {
                                                        echo '<td>' . htmlspecialchars(codelentille($resultat['id_categorie'])) . '</td>';
                                                    } else{
                                                        echo '<td>' . htmlspecialchars(codemonture($resultat['id_produit']).'-'.codelentille($resultat['id_categorie'])) . '</td>';
                                                    }
                                                    echo '
                                                    <td>' . htmlspecialchars(number_format($resultat['montant_total'])) . ' '.$devise.'</td>
                                                    <td>' . htmlspecialchars(client($resultat['id_patient'])) . '</td>
                                                    </tr>';
                                                }
                                                
                                                ?>
											</tbody>
										</table>
									</div>
								</section>
							</div>
						</div>
                    </div>
                    
				</section>
			</div>
        <?php include '../PUBLIC/footer.php';?>
		