<?php
session_start();
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/header.php');
require_once('../PUBLIC/fonction.php');

$clinique = getSingleRow($bdd, 'profil_entreprise');
$infopublic = getSingleRow($bdd, 'infoacceuille');
?>

	<body>
		<section class="body">

			<?php require '../PUBLIC/navbarmenu.php'; ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Tableau de bord</h2>
					</header>

					<!-- start: page 

					<div class="row">
						<div class="col">
							<div class="accordion" id="accordion">
								<div class="accordion-item card card-default">
									<div class="card-header">
										<h4 class="card-title m-0">
											<a class="accordion-toggle" data-bs-toggle="collapse" data-bs-parent="#accordion" data-bs-target="#collapse1One">
												Presentation
											</a>
										</h4>
									</div>
									<div id="collapse1One" class="collapse show" data-bs-parent="#accordion">
										<div class="card-body">
											<p><?php echo $infopublic['presentation']; ?></p>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					-->

	<div class="row">
		<div class="col-lg-8">
			<div class="col" style="text-align:justify;">
				<div class="accordion" id="accordion">
					<div class="accordion-item card card-default">
						<div class="card-header">
							<h4 class="card-title m-0">
								<a class="accordion-toggle" data-bs-toggle="collapse" data-bs-parent="#accordion" data-bs-target="#collapse1One">
									Presentation
								</a>
							</h4>
						</div>
						<div id="collapse1One" class="collapse show" data-bs-parent="#accordion">
							<div class="card-body">
								<p><?php echo $infopublic['presentation']; ?></p>
							</div>
						</div>
					</div>
				</div>
			</div>
			<!-- Section Organigramme -->
			<div class="row mg-files" data-sort-destination data-sort-id="media-gallery">
				<div class="isotope-item document col-sm-12">
					<div class="thumbnail">
						<div class="thumb-preview">
							<a class="thumb-image" href="../img/previews/OrganigrammeEC.png">
								<img src="../img/previews/OrganigrammeEC.png" class="img-fluid" alt="organigramme">
							</a>
							<div class="mg-thumb-options">
								<div class="mg-zoom"><i class="bx bx-search"></i></div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

    	<div class="col-lg-4">
        <!-- Section Mission, Vision, Valeurs -->
        <div class="row" style="text-align:justify;">
            <div class="col-lg-12">
                <div class="accordion accordion-secondary" id="accordion2Secondary">
                    <div class="card card-default">
                        <div class="card-header">
                            <h4 class="card-title m-0">
                                <a class="accordion-toggle" data-bs-toggle="collapse" data-bs-parent="#accordion2Secondary" data-bs-target="#collapse2SecondaryOne">
                                    Mission
                                </a>
                            </h4>
                        </div>
                        <div id="collapse2SecondaryOne" class="collapse show" data-bs-parent="#accordion2Secondary">
                            <div class="card-body">
                                <p><?php echo $infopublic['mission']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="accordion accordion-tertiary" id="accordion2Tertiary">
                    <div class="card card-default">
                        <div class="card-header">
                            <h4 class="card-title m-0">
                                <a class="accordion-toggle" data-bs-toggle="collapse" data-bs-parent="#accordion2Tertiary" data-bs-target="#collapse2TertiaryOne">
                                    Vision
                                </a>
                            </h4>
                        </div>
                        <div id="collapse2TertiaryOne" class="collapse show" data-bs-parent="#accordion2Tertiary">
                            <div class="card-body">
                                <p><?php echo $infopublic['vision']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="accordion accordion-quaternary" id="accordion2Quaternary">
                    <div class="card card-default">
                        <div class="card-header">
                            <h4 class="card-title m-0">
                                <a class="accordion-toggle" data-bs-toggle="collapse" data-bs-parent="#accordion2Quaternary" data-bs-target="#collapse2QuaternaryOne">
                                    Valeurs
                                </a>
                            </h4>
                        </div>
                        <div id="collapse2QuaternaryOne" class="collapse show" data-bs-parent="#accordion2Quaternary">
                            <div class="card-body">
                                <p><?php echo $infopublic['valeur']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

					<!-- end: page -->
				</section>
			</div>
            <?php include('../PUBLIC/footer.php');?>