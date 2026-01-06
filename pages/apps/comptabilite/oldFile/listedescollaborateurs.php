<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();
$errors=0;

if (isset($_POST['activer'])) 
{
    $reponse = $bdd->prepare('UPDATE collaborateurs SET status=1 WHERE id_collaborateur=?');
    $reponse ->execute([$_POST['activer']]);
    $errors=2;
}

if (isset($_POST['desactiver'])) 
{
    $reponse = $bdd->prepare('UPDATE collaborateurs SET status=0 WHERE id_collaborateur=?');
    $reponse ->execute([$_POST['desactiver']]);
    $errors=3;
}

if (isset($_POST['supprimer'])) 
{
    $reponse = $bdd->prepare('UPDATE collaborateurs SET status=3 WHERE id_collaborateur=?');
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
						<h2>Liste des collaborateurs </h2>
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
                                                <br>Compte collaborateur activé avec succès.</li>
                                                </div>
                                                '; }
                                            if ($errors==3) {
                                                echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Compte du collaborateur desactivé avec succès.</li>
                                                </div>
                                                '; }
                                            if ($errors==4) {
                                                echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Compte collaborateur supprimer avec succès.</li>
                                                </div>
                                                '; }
                                        ?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
													<th>ID</th>
													<th>COLLABORATEURS</th>
													<th>TYPE</th>
													<th>TELEPHONE</th>
													<th>COURRIEL</th>
                                                    <th>ADRESSE</th>
													<th>STATUS</th>
												</tr>
											</thead>
											<tbody>
											<?php
                                                $reponse1 = $bdd->prepare('SELECT * FROM collaborateurs ORDER BY id_collaborateur');
                                                $reponse1 -> execute();
                                                while ($donnees1 = $reponse1->fetch())
                                                    { $status = $donnees1['statut'];

                                                    echo' <tr>';
													if ($status!=3){
													echo'
                                                    <td>COLLAB'.$donnees1['id_collaborateur'].'</td>
                                                    <td>'.$donnees1['nom_collaborateur'].'</td>
                                                    <td>';
                                                    if ($donnees1['collaborateur_pour'] == 1) {
                                                        echo 'Clinique';
                                                    } 
                                                    if ($donnees1['collaborateur_pour'] == 2) {
                                                        echo 'Boutique';
                                                    } 
                                                    if ($donnees1['collaborateur_pour'] == 12) {
                                                        echo 'Clinique et Boutique';
                                                    } 
                                                    echo'</td>
                                                    <td>'.$donnees1['telephone'].'</td>
                                                    <td>'.$donnees1['email'].'</td>
                                                    <td>'.$donnees1['adresse'].'</td>
                                                    <td>
                                                        <div class="d-flex gap-1">';
                                                        if ($status == 0) {
                                                        echo '
                                                            <form action="listedescollaborateurs.php?id='.urlencode($donnees1['id_collaborateur']).'" method="post" style="display:inline;">
                                                            <input type="hidden" name="activer" value="'.htmlspecialchars($donnees1['id_collaborateur']).'">
                                                            <button type="submit" class="btn btn-sm btn-warning" title="Activer"><i class="fa fa-unlock-alt"></i></button>
                                                            </form>';}
                                                    
                                                        if ($status == 1) {
                                                        echo' 
                                                            <form action="listedescollaborateurs.php?id='.urlencode($donnees1['id_collaborateur']).'" method="post" style="display:inline;">
                                                            <input type="hidden" name="desactiver" value="'.htmlspecialchars($donnees1['id_collaborateur']).'">
                                                            <button type="submit" class="btn btn-sm btn-success" title="Désactiver"><i class="fa fa-lock"></i></button>
                                                            </form>';
                                                    };
                                                    echo '
                                                    </div>
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
