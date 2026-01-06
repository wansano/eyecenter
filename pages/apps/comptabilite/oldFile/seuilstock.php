<?php
include('../PUBLIC/connect.php');
session_start();
$errors=0;

if (isset($_POST['activer'])) 
{
    $reponse = $bdd->prepare('UPDATE SeuilStock SET status=1 WHERE id_Seuil=?');
    $reponse ->execute(array($_POST['activer']));
    $errors=2;
}

if (isset($_POST['desactiver'])) 
{
    $reponse = $bdd->prepare('UPDATE SeuilStock SET status=0 WHERE id_Seuil=?');
    $reponse ->execute(array($_POST['desactiver']));
    $errors=3;
}

if (isset($_POST['supprimer'])) 
{
    $reponse = $bdd->prepare('UPDATE SeuilStock SET status=3 WHERE id_Seuil=?');
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
						<h2>Seuil de stock paramètrage</h2>
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
                                                <br>Seuil de stock activé avec succès.</li>
                                                </div>
                                                '; }
                                            if ($errors==3) {
                                                echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Seuil de stock desactivé avec succès.</li>
                                                </div>
                                                '; }
                                        ?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
													<th>SEUIL RESERVE</th>
                                                    <th>SEUIL RUPTURE</th>
													<th>ESTIMATION VENTE</th>
													<th>ESTIMATION APPRO</th>
                                                    <th>PRIX MONTURES</th>
													<th>STATUS</th>
												</tr>
											</thead>
											<tbody>
											<?php
                                                $reponse1 = $bdd->prepare('SELECT * FROM seuilstock ORDER BY id_seuil');
                                                $reponse1->execute();
                                                while ($donnees1 = $reponse1->fetch()) {
                                                    $status = $donnees1['status'];
                                                    if ($status != 3) {
                                                        echo '<tr>';
                                                        echo '<td>Minimum ' . htmlspecialchars($donnees1['seuilreserve']) . ' articles restantes</td>';
                                                        echo '<td>Minimum ' . htmlspecialchars($donnees1['seuilrupture']) . ' articles restantes</td>';
                                                        echo '<td>' . htmlspecialchars($donnees1['consommationjournaliere']) . ' articles par jour</td>';
                                                        echo '<td>' . htmlspecialchars($donnees1['delaireapprovisionnement']) . ' jours</td>';
                                                        echo '<td>' . number_format((float)$donnees1['prix_monture'], 0, ',', ' ') . ' ' . htmlspecialchars($devise) . '</td>';
                                                        echo '<td>';
                                                        if ($status == 0) {
                                                            echo '<div class="d-flex gap-1">';
                                                            echo '<form action="#?id=' . urlencode($donnees1['id_seuil']) . '" method="post" style="display:inline;">';
                                                            echo '<input type="hidden" name="activer" value="' . htmlspecialchars($donnees1['id_seuil']) . '">';
                                                            echo '<button type="submit" class="btn btn-sm btn-warning" title="Activer"><i class="fa fa-unlock-alt"></i></button>';
                                                            echo '</form>';
                                                            echo '<a href="parametrageseuil.php?idseuil=' . urlencode($donnees1['id_seuil']) . '" class="btn btn-sm btn-info" title="Modifier"><i class="fa fa-edit"></i></a>';
                                                            echo '</div>';
                                                        }
                                                        if ($status == 1) {
                                                            echo '<form action="#?id=' . urlencode($donnees1['id_seuil']) . '" method="post" style="display:inline;">';
                                                            echo '<input type="hidden" name="desactiver" value="' . htmlspecialchars($donnees1['id_seuil']) . '">';
                                                            echo '<button type="submit" class="btn btn-sm btn-success" title="Désactiver"><i class="fa fa-lock"></i></button>';
                                                            echo '</form>';
                                                        }
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
