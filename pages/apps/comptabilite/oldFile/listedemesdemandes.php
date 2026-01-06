<?php
include('../PUBLIC/connect.php');
session_start();
$errors=0; 

    function user($nom){
       include('../PUBLIC/connect.php');
       $reponse1 = $bdd->prepare('SELECT * FROM users WHERE id=?');
       $reponse1 -> execute(array($nom));
       $user=" ";
                         while ($donnees1 = $reponse1->fetch())
                          {
                             $user=$donnees1['pseudo'];

                            }
                            return $user;
                          }

    function compte($nom){
       include('../PUBLIC/connect.php');
       $reponse1 = $bdd->prepare('SELECT * FROM comptes WHERE id_compte=?');
       $reponse1 -> execute(array($nom));
       $compte=" ";
                         while ($donnees1 = $reponse1->fetch())
                          {
                             $compte=$donnees1['nom_compte'];

                            }
                            return $compte;
                          }

      if (isset($_POST['annuler'])) 
      {
          $reponse = $bdd->prepare('UPDATE depenses SET status=3 WHERE id_depense=?');
          $reponse ->execute([$_POST['annuler']]);
          $errors=2;
      }

      if (isset($_POST['confirmer'])) 
      {
          $reponse = $bdd->prepare('UPDATE depenses SET confirmation=1 WHERE id_depense=?');
          $reponse ->execute([$_POST['confirmer']]);
          $errors=2;
      }

      include('../PUBLIC/header.php');
  ?>

	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Mes demandes de depenses.</h2>

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
										<h2 class="card-title">Ma liste de demande de depense</h2>
									</header>
									<div class="card-body">
                    <?php 
                        if ($errors==2) {
                        echo '
                            <div class="alert alert-success">
                            <li><strong>Succès !</strong>
                            <br>Votre demande de depense à été annuler.</li>
                            </div>
                            '; }
                      ?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">

											<thead>
												<tr>
                            <th>DESIGNATION</th>
                            <th>MONTANT DEMANDE</th>
                            <th>MONTANT RECU</th>
                            <th>DATE DEBUT</th>
                            <th>DATE FIN</th>
                            <th>ETAT</th>
                            <?php
                            $reponse1 = $bdd->prepare('SELECT * FROM depenses WHERE id=? ORDER BY id_depense DESC LIMIT 0,10');
                            $reponse1 -> execute(array($id_user));
                            while ($donnees1 = $reponse1->fetch())
                                { $status=$donnees1['status'];
                                  $confimation=$donnees1['confirmation'];
                              if ($status==1 OR $status==4) { echo '<th>PAYE PAR</th>';}
                              if ($confimation==0 || $confimation==1) { echo '<th>ACTION</th>';}
                            } ?>
												</tr>
											</thead>
											<tbody>
											    <?php
                            $reponse1 = $bdd->prepare('SELECT * FROM depenses WHERE id=? ORDER BY id_depense');
                            $reponse1 -> execute(array($id_user));
                            while ($donnees1 = $reponse1->fetch())
                                { $status=$donnees1['status'];

                                  echo' <tr>
                                <td>'.$donnees1['description'].'</td>
                                <td>'.number_format($donnees1['montant']).' '.$devise.'</td>
                                <td>'.number_format($donnees1['montant_paye']).' '.$devise.'</td>
                                <td>'.$donnees1['datedebut'].'</td>
                                <td>'.$donnees1['date_miseajour'].'</td>
                                <td>';
                                if ($status==1) {
                                echo 'Autorisé
                                <td>'.user($donnees1['payeur']).'</td>';
                                }

                                if ($status==0) {
                                echo'
                                encours
                                
                                <form action="listedemesdemandes.php?u='.$user.'" method="post">
                                <input type="hidden" name="annuler" value="'.$donnees1['id_depense'].'">
                                <button type="submit" class="btn btn-sm btn-default">Annuler</button>
                                </form>

                                ';
                                }

                                if ($status==3) {
                                  echo'
                                  Demande annulée';
                                  }

                              if ($status==4) {
                                echo' Payé ';
                                }
                          echo '
                          </td>';
                          if ($status==4) {
                            echo '
                            <td>'.user($donnees1['payeur']).'</td>
                            <td>';
                            if ($confimation == 0) {
                                echo'
                                  <form action="listedemesdemandes.php?u='.$user.'" method="post">
                                    <input type="hidden" name="confirmer" value="'.$donnees1['id_depense'].'">
                                    <button type="submit" class="btn btn-sm btn-info">Je confirme</button>
                                  </form>
                                ';
                                }
                            if ($confimation == 1) {
                              echo' Montant reçus';
                              }
                            '</td>';
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
		