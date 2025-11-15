<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();
$errors=0;

	include('../PUBLIC/header.php');
  ?>
	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Calendrier des rendez-vous</h2>
					</header>

					<!-- start: page -->
					<section class="card">
						<div class="card-body">
							<div class="row">
								<div class="col">
									<div id="calendarHello"></div>
								</div>
							</div>
						</section>
					<!-- end: page -->
				</section>
			</div>
	<?php include('../PUBLIC/footer.php');?>

<script>
(function($) {
	'use strict';
	var initCalendar = function() {
		var calendarEl = document.getElementById('calendarHello');
		var calendar = new FullCalendar.Calendar(calendarEl, {
			initialView: 'dayGridMonth',
			initialDate: new Date().toISOString().slice(0, 10),
			headerToolbar: {
				left: 'prev,next today',
				center: 'title',
				right: 'dayGridMonth,timeGridWeek,timeGridDay'
			},
			locale: 'fr', // Activation du français
			events: [
				<?php
				
					$reponse1 = $bdd->prepare('SELECT * FROM dmd_rendez_vous WHERE prochain_rdv >= :datejour AND status IN (0,1,2) AND traitant = :avec ORDER BY prochain_rdv');
					$reponse1->execute(['datejour' => date('Y-m-d'), 'avec' => $_SESSION['auth']]);
					while ($donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC)) {
						$status = $donnees1['status'];
						$color = ($status === 1) ? 'red' : (($status === 2) ? 'green' : 'blue');
						if ($status === 1) {
							$patientTitle = addslashes(nom_patient($donnees1['id_patient']));
							$start = $donnees1['prochain_rdv'];
							$rdvId = $donnees1['id_rdv'];
							echo "{\n\ttitle: '" . $patientTitle . "',\n\tstart: '" . $start . "',\n\turl: 'validationRDV.php?rdv=" . $rdvId . "',\n\tcolor: '" . $color . "',\n\t},\n";
						} elseif ($status === 2) {
							$patientTitle = addslashes(nom_patient($donnees1['id_patient']));
							$start = $donnees1['prochain_rdv'];
							$rdvId = $donnees1['id_rdv'];
							echo "{\n\ttitle: '" . $patientTitle . "',\n\tstart: '" . $start . "',\n\turl: 'validationRDV.php?rdv=" . $rdvId . "',\n\tcolor: '" . $color . "',\n\t},\n";
						} elseif ($status === 0) {
							$patientTitle = addslashes(nom_patient($donnees1['id_patient']));
							$start = $donnees1['prochain_rdv'];
							$rdvId = $donnees1['id_rdv'];
							echo "{\n\ttitle: '" . $patientTitle . "',\n\tstart: '" . $start . "',\n\turl: 'validationRDV.php?rdv=" . $rdvId . "',\n\tcolor: '" . $color . "',\n\t},\n";
						}
						
					}
				?>
				// autres événements statiques si besoin
			]
		});
		calendar.render();
	};

	$(function() {
		initCalendar();
	});

}).apply(this, [jQuery]);

    setTimeout(function() {
        location.reload();
    }, 60000); // Actualisation toutes les 60 secondes
</script>
