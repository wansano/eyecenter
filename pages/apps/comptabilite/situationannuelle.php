<?php
include('../PUBLIC/connect.php');
session_start();
	function nombrejour($nom){ {
	include('../PUBLIC/connect.php');
	 $reponse1 = $bdd->prepare('SELECT * FROM paiements WHERE types=? AND remboursement=0 AND YEAR(datepaiement)=?');
	 $reponse1 -> execute(array($nom, date("Y")));
	 $nombremensuel=$reponse1->rowcount();
	   } return $nombremensuel;  }


include("../PUBLIC/header.php");
?>
	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Prestation annuelle.</h2>

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
						<section class="panel panel-transparent">
							<div class="row">
							<header class="panel panel-transparent">
								<h4 class="panel-title">Prestation annuelle de <?php echo date("Y");?>
                                </h4>
							</header>
							<?php
								$total = $bdd->prepare('SELECT * FROM traitements ORDER BY id_type');
								$total -> execute();
								while ( $data = $total->fetch()) {

									if (nombrejour($data['id_type'])!=0) {
									echo '
										<div class="col-md-2">
												<section class="card">
													<div class="card-body">
														<div class="h5 text-bold mb-none">'.nombrejour($data['id_type']).'</div>
														<p class="text-xs text-muted mb-none">'.$data['nom_type'].'</p>
													</div>
												</section>
										</div>
										';	}
										}
							?>
							</div>
					</div>
					<!-- end: page -->
				</section>
			</div>
            <?php include('../PUBLIC/footer.php');?>
		