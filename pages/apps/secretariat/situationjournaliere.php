<?php
session_start();
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
include('../PUBLIC/header.php');
?>

	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Prestation journalière</h2>
					</header>

					<!-- start: page -->
                    <div class="col-md-12">
						<section class="panel panel-transparent">
							<div class="row">
							<header class="panel panel-transparent">
								<h4 class="panel-title">Prestation du jour : </h4>
							</header>
							<?php
								$total = $bdd->query('SELECT * FROM  traitements ORDER BY id_type');
								while ($data = $total->fetch()) {
									$nbJour = nombrejour($bdd, $data['id_type']);
									if ($nbJour > 0) {
										echo '<div class="col-md-2">
												<section class="card">
													<div class="card-body">
														<div class="h5 text-bold mb-none">'.$nbJour.'</div>
														<p class="text-xs text-muted mb-none">'.htmlspecialchars($data['nom_type']).'</p>
													</div>
												</section>
										</div>';
									}
								}
							?>
							</div>
					</div>
					<!-- end: page -->
				</section>
			</div>
            <?php include('../PUBLIC/footer.php');?>