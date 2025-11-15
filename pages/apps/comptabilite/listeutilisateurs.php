<?php
include('../PUBLIC/connect.php');
session_start();
$errors=0;
function utilisateur($nom){
     include('../PUBLIC/connect.php');
$reponse1 = $bdd->prepare('SELECT * FROM users WHERE id=?');
$reponse1 -> execute(array($nom));
$utilisateur=" ";
                 while ($donnees1 = $reponse1->fetch())
                  {
                     $utilisateur=$donnees1['pseudo'];

                    }
                    return $utilisateur;
                  }

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

if (isset($_POST['activer'])) 
{
    $reponse = $bdd->prepare('UPDATE users SET status=1 WHERE id=?');
    $reponse ->execute(array($_POST['activer']));
    $errors=2;
}

if (isset($_POST['desactiver'])) 
{
    $reponse = $bdd->prepare('UPDATE users SET status=0 WHERE id=?');
    $reponse ->execute(array($_POST['desactiver']));
    $errors=3;
}

if (isset($_POST['supprimer'])) 
{
    $reponse = $bdd->prepare('UPDATE users SET status=3 WHERE id=?');
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
						<h2>
						<?php 
							if ($types=="comptabilité") { echo 'Liste des caissiers.';} 
							else { echo 'Liste des utilisateurs du systeme.'; }
						?>
						</h2>

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
										<?php 
											if ($types=="comptabilité") { echo 'Liste des caissiers.';} 
											else { echo 'Liste des utilisateurs du systeme.'; }
										?>
										</h2>
									</header>
									<div class="card-body">
                                    <?php 
											if ($types=="comptabilité") { echo 'Liste des caissiers.';} 
											else { echo 'Liste des utilisateurs du systeme.
                                                <br>
                                                    <a href="addcustumuser.php" type="button" class="btn btn-sm btn-success"><i class="fa fa-plus"></i> ajouter un utilisateur</a><br/> 
                                                <br>'; }

                                            if ($errors==2) {
                                            echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Compte utilisateur activé avec succès.</li>
                                                </div>
                                                '; }
                                            if ($errors==3) {
                                                echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Compte utilisateur desactivé avec succès.</li>
                                                </div>
                                                '; }
                                            if ($errors==4) {
                                                echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Compte utilisateur supprimer avec succès.</li>
                                                </div>
                                                '; }
                                        ?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
													<th>ID</th>
													<th>UTILISATEUR</th>
													<th>TYPE</th>
													<th>EMAIL</th>
													<th>SERVICE</th>
													<th>STATUS</th>
												</tr>
											</thead>
											<tbody>
											<?php
											if ($types=="comptabilité") 
											{
                                                $reponse1 = $bdd->prepare('SELECT * FROM users WHERE type=? OR type=? ORDER BY id');
                                                $reponse1 -> execute(array('caisse','caisseoptic'));
                                                while ($donnees1 = $reponse1->fetch())
                                                    { $status = $donnees1['status'];

                                                    echo' <tr>';
													if ($status!=3){
													echo'
                                                    <td>EC'.$donnees1['id'].'</td>
                                                    <td>'.$donnees1['pseudo'].'</td>
                                                    <td>'.$donnees1['type'].'</td>
                                                    <td>'.$donnees1['email'].'</td>
                                                    <td>'.service($donnees1['id_service']).'</td>
                                                    <td>';
                                                    if ($status==0) { echo '
                                                        <form action="listeutilisateurs.php?id='.$donnees1['id'].'" method="post">
                                                        <input type="hidden" name="activer" value="'.$donnees1['id'].'">
                                                        <button type="submit" class="btn btn-sm btn-warning"><i class="fa fa-unlock-alt"></i>activé</button>
                                                        </form>';}
                                                    
                                                        if ($status==1) {
                                                        echo' 
                                                        <form action="listeutilisateurs.php?id='.$donnees1['id'].'" method="post">
                                                        <input type="hidden" name="desactiver" value="'.$donnees1['id'].'">
                                                        <button type="submit" class="btn btn-sm btn-success"><i class="fa fa-lock"></i> désactivé</button>
                                                        </form>';
                                                    };
                                                    echo '
                                                    </td>
                                                    </tr>';
                                                        }
													}
											} else {

												$reponse1 = $bdd->prepare('SELECT * FROM users ORDER BY id');
                                                $reponse1 -> execute();
                                                while ($donnees1 = $reponse1->fetch())
                                                    { $status = $donnees1['status'];

                                                    echo' <tr>';
													if ($status!=3){
													echo'
                                                    <td>EC'.$donnees1['id'].'</td>
                                                    <td>'.$donnees1['pseudo'].'</td>
                                                    <td>'.$donnees1['type'].'</td>
                                                    <td>'.$donnees1['email'].'</td>
                                                    <td>'.service($donnees1['id_service']).'</td>
                                                    <td>';
                                                    if ($status==0) { echo '
                                                        <form action="listeutilisateurs.php?id='.$donnees1['id'].'" method="post">
                                                        <input type="hidden" name="activer" value="'.$donnees1['id'].'">
                                                        <button type="submit" class="btn btn-sm btn-warning"><i class="fa fa-unlock-alt"></i> activé</button>
                                                        </form>

                                                        <form action="listeutilisateurs.php?id='.$donnees1['id'].'" method="post">
                                                        <input type="hidden" name="supprimer" value="'.$donnees1['id'].'">
                                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-times"></i> supprimer</button>
                                                        </form>

                                                        <a href="edit_user.php?id_user='.$donnees1['id'].'" type="button" class="btn btn-sm btn-info"><i class="fa fa-edit"></i> modifier</a>
                                                        ';}
                                                    
                                                        if ($status==1) {
                                                        echo' 
                                                        <form action="listeutilisateurs.php?id='.$donnees1['id'].'" method="post">
                                                        <input type="hidden" name="desactiver" value="'.$donnees1['id'].'">
                                                        <button type="submit" class="btn btn-sm btn-success"><i class="fa fa-lock"></i> désactivé</button>
                                                        </form>';
                                                    };
                                                    echo '
                                                    </td>
                                                    </tr>';
                                                        }
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