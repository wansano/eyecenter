<?php
include('../PUBLIC/connect.php');
session_start();
$errors=0;
function client($nom){
     include('../PUBLIC/connect.php');
$reponse1 = $bdd->prepare('SELECT * FROM clients WHERE id_client=?');
$reponse1 -> execute(array($nom));
$clients=" ";
                 while ($donnees1 = $reponse1->fetch())
                  {
                     $clients=$donnees1['nom_client'];

                    }
                    return $clients;
                  }

if (isset($_POST['activer'])) 
{
    $reponse = $bdd->prepare('UPDATE clients SET status=1 WHERE id_client=?');
    $reponse ->execute(array($_POST['activer']));
    $errors=2;
}

if (isset($_POST['desactiver'])) 
{
    $reponse = $bdd->prepare('UPDATE clients SET status=0 WHERE id_client=?');
    $reponse ->execute(array($_POST['desactiver']));
    $errors=3;
}

if (isset($_POST['supprimer'])) 
{
    $reponse = $bdd->prepare('UPDATE clients SET status=3 WHERE id_client=?');
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
						<h2>Liste des clients </h2>
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
										<h2 class="card-title">Liste des clients</h2>
									</header>
									<div class="card-body">
                                        <?php 
                                            if ($errors==2) {
                                            echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Compte clients activé avec succès.</li>
                                                </div>
                                                '; }
                                            if ($errors==3) {
                                                echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Suite à votre propore decision, ce compte d\'clients à été desactivé avec succès.</li>
                                                </div>
                                                '; }
                                            if ($errors==4) {
                                                echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Compte clients supprimer avec succès.</li>
                                                </div>
                                                '; }
                                        ?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
													<th>ID CLIENT</th>
													<th>CLIENTS</th>
													<th>TYPE</th>
													<th>TELEPHONE</th>
													<th>COURRIEL</th>
                                                    <th>ADRESSE</th>
													<th>STATUS</th>
												</tr>
											</thead>
											<tbody>
											<?php
                                                $reponse1 = $bdd->prepare('SELECT * FROM clients ORDER BY id_client');
                                                $reponse1 -> execute();
                                                while ($donnees1 = $reponse1->fetch())
                                                    { $status = $donnees1['status'];

                                                    echo' <tr>';
													if ($status!=3){
													echo'
                                                    <td>CL-'.$donnees1['id_client'].'</td>
                                                    <td>'.$donnees1['nom_client'].'</td>
                                                    <td>'.$donnees1['type_client'].'</td>
                                                    <td>'.$donnees1['telephone'].'</td>
                                                    <td>'.$donnees1['email'].'</td>
                                                    <td>'.adress($donnees1['adresse']).'</td>
                                                    <td>';
                                                    if ($status==0) { echo '
                                                        <form action="listedesclients.php?id='.$donnees1['id_client'].'" method="post">
                                                        <input type="hidden" name="activer" value="'.$donnees1['id_client'].'">
                                                        <button type="submit" class="btn btn-sm btn-warning"><i class="fa fa-unlock-alt"></i>activé</button>
                                                        </form>';}
                                                    
                                                        if ($status==1) {
                                                        echo' 
                                                        <form action="listedesclients.php?id='.$donnees1['id_client'].'" method="post">
                                                        <input type="hidden" name="desactiver" value="'.$donnees1['id_client'].'">
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
            <?php include('../PUBLIC/footer.php');?>
	