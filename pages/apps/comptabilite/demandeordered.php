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

    if (isset($_POST['accepter'])) 
    {
        $reponse = $bdd->prepare('UPDATE depenses SET status=1 WHERE id_depense=?');
        $reponse ->execute(array($_POST['accepter']));
        $errors=2;
    }
    
    if (isset($_POST['refuser'])) 
    {
        $reponse = $bdd->prepare('UPDATE depenses SET status=2 WHERE id_depense=?');
        $reponse ->execute(array($_POST['refuser']));
        $errors=3;
    }


    include('../PUBLIC/header.php');
  ?>

	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Les demandes de depenses validées.</h2>

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
										<h2 class="card-title">Liste des demandes de depenses validées</h2>
									</header>
									<div class="card-body">
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
                     
                      <thead>
												<tr>
                            <th>DEMANDEUR</th>
                            <th>MOTIF</th>
                            <th>MONTANT</th>
                            <th>DATE DEBUT</th>
                            <th>DATE FIN</th>
                            <th>STATUS</th>
                            <th>PAYE PAR</th>
												</tr>
											</thead>
											<tbody>
                        <?php
                        $reponse1 = $bdd->prepare('SELECT * FROM depenses WHERE status=4 ORDER BY id_depense');
                        $reponse1 -> execute();
                        while ($donnees1 = $reponse1->fetch())
                          {
                            echo' 
                            <tr>
                            <td>'.user($donnees1['id']).'</td>
                            <td>'.libeler($donnees1['id_depense']).'</td>
                            <td>'.number_format(montant($donnees1['id_depense'])).' GNF</td>
                            <td>'.date_debut($donnees1['id_depense']).'</td>
                            <td>'.date_fin($donnees1['id_depense']).'</td>
                            <td>
                            <button class="btn btn-sm btn-info">Payé</button>
                            </td>
                            <td>'.user($donnees1['payeur']).'</td>
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
		