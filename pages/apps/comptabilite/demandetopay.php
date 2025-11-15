<?php
include('../PUBLIC/connect.php');
require('../PUBLIC/fonction.php');
session_start();
$errors=0;

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
						<h2>Liste des depense en attente de paiement.</h2>

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
										<h2 class="card-title">liste de demande de paiement.</h2>
									</header>
									<div class="card-body">
										<table class="table table-bordered table-striped mb-0" id="datatable-default">

											<thead>
												<tr>
                                                    <th>DEMANDEUR</th>
                                                    <th>DESIGNATION</th>
                                                    <th>MONTANT DEMANDE</th>
                                                    <th>MONTANT PAYE</th>
                                                    <th>BUDGET</th>
                                                    <th>DATE DEBUT</th>
                                                    <th>DATE FIN</th>
                                                    <th>STATUS</th>
                                                  </tr>
                                                </thead>
                                                <tbody>
                                                <?php
                                                  $reponse1 = $bdd->prepare('SELECT d.*, b.nom_budget FROM depenses d 
                                                    LEFT JOIN budgets b ON d.id_budget = b.id_budget WHERE d.solde>= :solde
                                                    ORDER BY d.id_depense');
                                                  $reponse1 -> execute(['solde'=>0]);
                                                  while ($donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC)) {
                                                    $soldedepenseid = $donnees1['solde'];
                                                    $confirmation = $donnees1['confirmation'];
                                                      if ($soldedepenseid != 0 || $confirmation == 0) {
                                                      echo '<tr>
                                                              <td>' . htmlspecialchars(user($donnees1['id'])) . '</td>
                                                              <td>' . htmlspecialchars($donnees1['description']) . '</td>
                                                              <td>' . number_format($donnees1['montant']) . ' ' . htmlspecialchars($devise) . '</td>
                                                              <td>' . number_format($donnees1['montant_paye']) . ' ' . htmlspecialchars($devise) . '</td>
                                                              
                                                              <!--  Ajout du nom du budget récupéré -->
                                                              <td>' . htmlspecialchars($donnees1['nom_budget'] ?: 'Non défini') . '</td>
                                                              
                                                              <td>' . htmlspecialchars(date( $donnees1['datedebut'])) . '</td>
                                                              <td>' . htmlspecialchars(date( $donnees1['date_miseajour'])) . '</td>
                                                              <td>';
                                                              if ($soldedepenseid == 0 && $confirmation == 0) {
                                                                echo '<a class="btn btn-sm btn-warning">confirmation en attente</a>';
                                                              } 
                                                              if ($soldedepenseid != 0) { echo '<a href="paiementdepense.php?depense=' . htmlspecialchars($donnees1['id_depense']) . '" class="btn btn-sm btn-info">Procéder au paiement</a>';}
                                                               echo'   
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
