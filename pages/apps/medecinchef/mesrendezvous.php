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
				
					$reponse1 = $bdd->prepare('SELECT * FROM dmd_rendez_vous WHERE prochain_rdv >= :datejour AND status = 1 AND traitant = :avec ORDER BY prochain_rdv');
					$reponse1->execute(['datejour' => date('Y-m-d'), 'avec' => $_SESSION['auth']]);
					while ($donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC)) {
						echo "{\n	title: '".addslashes(nom_patient($donnees1['id_patient']))."',
						\n	start: '".$donnees1['prochain_rdv']."',
						\n  url : 'validation.php?rdv=".$donnees1['id_rdv']."',
						\n	end: ''\n},\n";
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
</script>
