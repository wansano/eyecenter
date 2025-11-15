<?php
include('../PUBLIC/connect.php');
session_start();
$errors=0; 
	function libeler($nom){
			 include('../PUBLIC/connect.php');
       $reponse1 = $bdd->prepare('SELECT * FROM depenses WHERE id_depense=?');
       $reponse1 -> execute(array($nom));
       $description=" ";
                         while ($donnees1 = $reponse1->fetch())
                          {
                             $description=$donnees1['description'];

                            }
                            return $description;
                          }

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

    function montant($nom){
       include('../PUBLIC/connect.php');
       $reponse1 = $bdd->prepare('SELECT * FROM depenses WHERE id_depense=?');
       $reponse1 -> execute(array($nom));
       $montant=" ";
                        while ($donnees1 = $reponse1->fetch())
                            {
                             $montant=$donnees1['montant'];

                            }
                            return $montant;
                          }

    function date_debut($nom){
       include('../PUBLIC/connect.php');
       $reponse1 = $bdd->prepare('SELECT * FROM depenses WHERE id_depense=?');
       $reponse1 -> execute(array($nom));
       $date_debut=" ";
                        while ($donnees1 = $reponse1->fetch())
                            {
                             $date_debut=$donnees1['datedebut'];

                            }
                            return $date_debut;
                          }

    function date_fin($nom){
        include('../PUBLIC/connect.php');
        $reponse1 = $bdd->prepare('SELECT * FROM depenses WHERE id_depense=?');
        $reponse1 -> execute(array($nom));
        $date_fin=" ";
                        while ($donnees1 = $reponse1->fetch())
                            {
                            $date_fin=$donnees1['datefin'];

                            }
                            return $date_fin;
                        }

      if (isset($_POST['annuler'])) 
      {
          $reponse = $bdd->prepare('UPDATE depenses SET status=3 WHERE id_depense=?');
          $reponse ->execute(array($_POST['annuler']));
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
                            <th>MONTANT</th>
                            <th>DATE DEBUT</th>
                            <th>DATE FIN</th>
                            <th>STATUS</th>
                            <?php
                            $reponse1 = $bdd->prepare('SELECT * FROM depenses WHERE id=? ORDER BY id_depense DESC LIMIT 0,10');
                            $reponse1 -> execute(array($id_user));
                            while ($donnees1 = $reponse1->fetch())
                                { $status=$donnees1['status'];
                              if ($status==1 OR $status==4) { echo '<th>PAYEUR</th>';}
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
                                <td>'.libeler($donnees1['id_depense']).'</td>
                                <td>'.number_format(montant($donnees1['id_depense'])).' GNF</td>
                                <td>'.date_debut($donnees1['id_depense']).'</td>
                                <td>'.date_fin($donnees1['id_depense']).'</td>
                                <td>';
                                if ($status==1) {
                                echo 'Autorisé
                                <td>'.user($donnees1['payeur']).'</td>';
                                }

                                if ($status==0) {
                                echo'
                                encours
                                
                                <form action="mesdemandes.php?u='.$user.'" method="post">
                                <input type="hidden" name="annuler" value="'.$donnees1['id_depense'].'">
                                <button type="submit" class="btn btn-sm btn-default">Annuler</button>
                                </form>

                                ';
                                }

                                if ($status==2) {
                                echo'
                                Non autorisé';
                                }

                                if ($status==3) {
                                  echo'
                                  Demande annuler';
                                  }

                              if ($status==4) {
                                echo'
                                Payé';
                                }
                          echo '
                          </td>';
                          if ($status==4) {
                            echo '
                            <td>'.user($donnees1['payeur']).'</td>
                            ';
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
		