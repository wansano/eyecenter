<?php
include('../PUBLIC/connect.php');
session_start();
$errors=0;

if (isset($_POST['activer'])) 
{
    $reponse = $bdd->prepare('UPDATE model_produits SET status=1 WHERE id_model=?');
    $reponse ->execute(array($_POST['activer']));
    $errors=2;
}

if (isset($_POST['desactiver'])) 
{
    $reponse = $bdd->prepare('UPDATE model_produits SET status=0 WHERE id_model=?');
    $reponse ->execute(array($_POST['desactiver']));
    $errors=3;
}

if (isset($_POST['supprimer'])) 
{
    $reponse = $bdd->prepare('UPDATE model_produits SET status=3 WHERE id_model=?');
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
										<h2 class="card-title">Liste des models des produits</h2>
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
													<th>MODEL</th>
													<th>DESCRIPTION</th>
													<th>DATE MODIFICATION</th>
													<th>ACTION</th>
												</tr>
											</thead>
											<tbody>
											<?php
                                                $reponse1 = $bdd->prepare('SELECT * FROM model_produits ORDER BY id_model');
                                                $reponse1 -> execute();
                                                while ($donnees1 = $reponse1->fetch())
                                                    { $status = $donnees1['status'];

                                                    echo' <tr>';
													if ($status!=3){
													echo'
                                                    <td>'.$donnees1['model'].'</td>
                                                    <td>'.$donnees1['description'].'</td>
                                                    <td>'.$donnees1['date_miseajour'].'</td>
                                                    <td>';
                                                    if ($status==0) { echo '
                                                        <form action="#?id='.$donnees1['id_model'].'" method="post">
                                                        <input type="hidden" name="activer" value="'.$donnees1['id_model'].'">
                                                        <button type="submit" class="btn btn-sm btn-warning"><i class="fa fa-unlock-alt"></i> activé</button>
                                                        </form>
                                                        <a href="editmodelproduct.php?idcategorie='.$donnees1['id_model'].'" class="btn btn-sm btn-info"><i class="fa fa-edit"></i> modifier</a>';}
                                                    
                                                        if ($status==1) {
                                                        echo' 
                                                        <form action="#?id='.$donnees1['id_model'].'" method="post">
                                                        <input type="hidden" name="desactiver" value="'.$donnees1['id_model'].'">
                                                        <button type="submit" class="btn btn-sm btn-success"><i class="fa fa-lock"></i> désactivé</button>
                                                        </form>';
                                                    };
                                                    echo '
                                                    </td>
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
		