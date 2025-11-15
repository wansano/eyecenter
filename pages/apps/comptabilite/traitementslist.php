<?php
include('../PUBLIC/connect.php');
session_start();
$errors=0;

function service($nom){
	include('../PUBLIC/connect.php');
	$reponse1 = $bdd->prepare('SELECT * FROM services WHERE id_service=?');
	$reponse1 -> execute(array($nom));
	$service=" ";
		while ($donnees1 = $reponse1->fetch())
		{
			$service=$donnees1['nom_service'];
		}
		return $service;
	}


function status_service($nom){
	include('../PUBLIC/connect.php');
	$reponse1 = $bdd->prepare('SELECT * FROM services WHERE id_service=?');
	$reponse1 -> execute(array($nom));
	$status_service=" ";
		while ($donnees1 = $reponse1->fetch())
		{
			$status_service=$donnees1['status'];
		}
		return $status_service;

}

if (isset($_POST['activer'])) 
{
    $reponse = $bdd->prepare('UPDATE traitements SET status=1 WHERE id_type=?');
    $reponse ->execute(array($_POST['activer']));
    $errors=2;
}

if (isset($_POST['desactiver'])) 
{
    $reponse = $bdd->prepare('UPDATE traitements SET status=0 WHERE id_type=?');
    $reponse ->execute(array($_POST['desactiver']));
    $errors=3;
}

if (isset($_POST['supprimer'])) 
{
    $reponse = $bdd->prepare('UPDATE traitements SET status=3 WHERE id_type=?');
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
						<h2>Liste des traitements disponible.</h2>

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
                                            Liste des traitements disponible.
										</h2>
									</header>
									<div class="card-body">
                                    <a href="addtreatement.php" type="button" class="btn btn-sm btn-success"><i class="fa fa-plus"></i> ajouter un traitement</a> <br/> <br>
                                        <?php 
                                            if ($errors==2) {
                                            echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Ce type de traitement a été activer avec succès.</li>
                                                </div>
                                                '; }
                                            if ($errors==3) {
                                                echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Suite à votre propore decision, ce type de traitement à été desactiver avec succès.</li>
                                                </div>
                                                '; }
                                            if ($errors==4) {
                                                echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Type de traitement supprimer avec succès.</li>
                                                </div>
                                                '; }
                                        ?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
													<th>ID</th>
													<th>TRAITEMENT</th>
													<th>TYPE</th>
													<th>MONTANT</th>
													<th>STATUS</th>
												</tr>
											</thead>
											<tbody>
											<?php
                                                $reponse1 = $bdd->prepare('SELECT * FROM traitements ORDER BY id_type');
                                                $reponse1 -> execute();
                                                while ($donnees1 = $reponse1->fetch())
                                                    { $status = $donnees1['status'];
                                                      $actifservice = $donnees1['model'];
                                                    
                                                    if ((status_service($actifservice))==1) 
                                                    {
                                                    echo' <tr>
                                                    <td>'.strtoupper(substr($donnees1['nom_type'], 0, 2)), $donnees1['id_type'].'</td>
                                                    <td>'.$donnees1['nom_type'].'</td>
                                                    <td>'.service($donnees1['model']).'</td>
													<td>'.number_format($donnees1['montant']).' GNF</td>
                                                    <td>';
                                                    if ($status==0) { echo '
                                                        <form action="traitementslist.php?id='.$donnees1['id_type'].'" method="post">
                                                        <input type="hidden" name="activer" value="'.$donnees1['id_type'].'">
                                                        <button type="submit" class="btn btn-sm btn-warning"><i class="fa fa-unlock-alt"></i> activé</button>
                                                        </form>

                                                        <form action="traitementslist.php?id='.$donnees1['id_type'].'" method="post">
                                                        <input type="hidden" name="supprimer" value="'.$donnees1['id_type'].'">
                                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-times"></i> supprimer</button>
                                                        </form>

                                                        <a href="#.php?id_user='.$donnees1['id_type'].'" type="button" class="btn btn-sm btn-info"><i class="fa fa-edit"></i> modifier</a>
                                                        ';}
                                                    
                                                        if ($status==1) {
                                                        echo' 
                                                        <form action="traitementslist.php?id='.$donnees1['id_type'].'" method="post">
                                                        <input type="hidden" name="desactiver" value="'.$donnees1['id_type'].'">
                                                        <button type="submit" class="btn btn-sm btn-success"><i class="fa fa-lock"></i> désactivé</button>
                                                        </form>';
                                                        }
                                                    };
                                                    echo '
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
            <?php include('../PUBLIC/footer.php');?>
		