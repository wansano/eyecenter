<?php
include('../PUBLIC/connect.php');
session_start();
$errors=0;

if (isset($_POST['activer'])) 
{	
	$reponse1 = $bdd->prepare('SELECT * FROM taux WHERE id_taux = ?');
	$reponse1->execute([$_POST['activer']]);
	$donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC);
	$pour = $donnees1['taux_pour'];

		$reponse = $bdd->prepare('UPDATE taux SET status = ? WHERE taux_pour = ?');
		$reponse ->execute([0, $pour]);

		$reponse = $bdd->prepare('UPDATE taux SET status = ? WHERE id_taux = ?');
		$reponse ->execute([1, $_POST['activer']]);
		$errors=2;
}

if (isset($_POST['supprimer'])) 
{
    $reponse = $bdd->prepare('UPDATE taux SET status = ? WHERE id_taux = ?');
    $reponse ->execute([3, $_POST['supprimer']]);
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
						<h2>Liste et disponibilité des comptes.</h2>

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
										<h2 class="card-title">Liste des taux</h2>
									</header>
									<div class="card-body">
                                    	<?php 
                                            if ($errors==2) {
                                            echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Taux activé avec succès.</li>
                                                </div>
                                                '; }

                                            if ($errors==4) {
                                                echo '
                                                <div class="alert alert-danger">
                                                <li><strong>Succès !</strong>
                                                <br>Ce taux à été supprimer avec succès.</li>
                                                </div>
                                                '; }
                                        ?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
                                                    <th>DATE AJOUT</th>
                                                    <th>TAUX %</th>
													<th>TAUX AFFECTE</th>
                                                    <th>ACTION</th>
												</tr>
											</thead>
											<tbody>
											<?php
												$reponse1 = $bdd->prepare('SELECT * FROM taux WHERE status != ? ORDER BY id_taux');
												$reponse1 -> execute([3]);
												while ($donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC))
												{  $status = $donnees1['status']; $tauxpour = $donnees1['taux_pour'];
													
                                                    echo' 
													<tr>
													<td>'.$donnees1['date'].'</a></td>
                                                    <td>'.$donnees1['taux'].'</td>
													<td>';
													if ($tauxpour == 0) {
														echo 'Clinique </td>
													<td>';
													}
													if ($tauxpour == 1) {
														echo 'Boutique </td>
													<td>';
													}
                                                        if ($status==1) { 
                                                        echo '
                                                        <button type="submit" class="btn btn-sm btn-success"><i class="fa fa-checked"></i>actif actuellement</button>
                                                        ';}
                                                    
                                                        if ($status==0) {
                                                        echo'
                                                        <form action="listetaux.php?taxlist&id='.$donnees1['id_taux'].'" method="post">
                                                        <input type="hidden" name="activer" value="'.$donnees1['id_taux'].'">
                                                        <button type="submit" class="btn btn-sm btn-warning"><i class="fa fa-unlock-alt"></i> activé ce taux</button>
                                                        </form>
                                                        
                                                        <form action="listetaux.php?taxlist&id='.$donnees1['id_taux'].'" method="post">
                                                        <input type="hidden" name="supprimer" value="'.$donnees1['id_taux'].'">
                                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-times"></i> supprimer</button>
                                                        </form>
                                                        ';
                                                    }
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
			    </div>
            <?php include('../PUBLIC/footer.php');?>