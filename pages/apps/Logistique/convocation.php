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
					<section class="card mb-2">
						<div class="card-body">
							<form class="row g-2 align-items-end" onsubmit="return false;">
								<div class="col-sm-6 col-md-2">
									<label class="col-form-label" for="datePrintInput">Choisir la date</label>
									<input type="date" id="datePrintInput" class="form-control" value="<?php echo date('Y-m-d'); ?>">
								</div>
								<div class="col-sm-6 col-md-3">
									<label class="col-form-label" for="medecinPrintSelect">Médecin</label>
									<select id="medecinPrintSelect" class="form-control">
										<option value="">-- Choisir un médecin --</option>
										<?php
										try {
											$today = date('Y-m-d');
											$st = $bdd->prepare("SELECT DISTINCT traitant FROM dmd_rendez_vous WHERE DATE(prochain_rdv) = :datejour AND status IN (0,1,2) ORDER BY traitant");
											$st->execute(['datejour' => $today]);
											while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
												$idMed = (int)$row['traitant'];
												$label = htmlspecialchars(traitant($idMed) ?: ('#'.$idMed), ENT_QUOTES, 'UTF-8');
												echo '<option value="'.$idMed.'">'.$label.'</option>';
											}
										} catch (Exception $e) {
											// silencieux
										}
										?>
									</select>
								</div>
								<div class="col-sm-3 col-md-2">
									<button id="btnPrintRdv" class="btn btn-primary w-100" type="button">Imprimer les RDV du jour</button>
								</div>
							</form>
						</div>
					</section>
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
// Forcer l'exécution du JavaScript quand le DOM est entièrement chargé
document.addEventListener('DOMContentLoaded', function() {
	'use strict';
	
	var initCalendar = function() {
		var calendarEl = document.getElementById('calendarHello');
		if (!calendarEl) {
			console.warn('Calendrier non trouvé');
			return;
		}
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
				
					$reponse1 = $bdd->prepare('SELECT * FROM dmd_rendez_vous WHERE prochain_rdv >= :datejour AND status IN (0,1,2) ORDER BY prochain_rdv');
					$reponse1->execute(['datejour' => date('Y-m-d')]);
					while ($donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC)) {
						$status = $donnees1['status'];
						$color = ($status === 1) ? 'red' : (($status === 2) ? 'green' : '');
						if ($status === 1) {
							$patientTitle = addslashes(nom_patient($donnees1['id_patient']));
							$start = $donnees1['prochain_rdv'];
							$rdvId = $donnees1['id_rdv'];
							echo "{\n\ttitle: '" . $patientTitle . "',\n\tstart: '" . $start . "',\n\turl: 'convocationdetails.php?rdv=" . $rdvId . "',\n\tcolor: '" . $color . "',\n\t},\n";
						} elseif ($status === 2) {
							$patientTitle = addslashes(nom_patient($donnees1['id_patient']));
							$start = $donnees1['prochain_rdv'];
							$rdvId = $donnees1['id_rdv'];
							echo "{\n\ttitle: '" . $patientTitle . "',\n\tstart: '" . $start . "',\n\turl: 'convocationdetails.php?rdv=" . $rdvId . "',\n\tcolor: '" . $color . "',\n\t},\n";
						} else {
							$patientTitle = addslashes(nom_patient($donnees1['id_patient']));
							$start = $donnees1['prochain_rdv'];
							$rdvId = $donnees1['id_rdv'];
							echo "{\n\ttitle: '" . $patientTitle . "',\n\tstart: '" . $start . "',\n\turl: 'convocationdetails.php?rdv=" . $rdvId . "',\n\tcolor: '" . $color . "',\n\t},\n";
						}
						
					}
				?>
				// autres événements statiques si besoin
			]
		});
		calendar.render();
	};

	// Initialiser le calendrier
	initCalendar();
	
	// Impression RDV du jour par médecin
	var btn = document.getElementById('btnPrintRdv');
	var sel = document.getElementById('medecinPrintSelect');
	var dateInput = document.getElementById('datePrintInput');
	
	console.log('Éléments trouvés:', {btn: !!btn, sel: !!sel, dateInput: !!dateInput});
	
	if (btn && sel) {
		btn.addEventListener('click', function(){
			var med = sel.value;
			if (!med) { alert('Veuillez choisir un médecin.'); return; }
			var d = (dateInput && dateInput.value) ? dateInput.value : new Date().toISOString().slice(0,10);
			var url = 'imprimer_listerdv.php?date='+encodeURIComponent(d)+'&medecin='+encodeURIComponent(med);
			window.open(url, '_blank');
		});
	}

	// Mise à jour de la liste des médecins en fonction de la date choisie
	function resetSelect(el, placeholder){
		if (!el) return;
		el.innerHTML = '';
		var opt = document.createElement('option');
		opt.value = '';
		opt.textContent = placeholder || '-- Choisir --';
		el.appendChild(opt);
	}
	
	async function refreshMedecinsByDate(){
		if (!sel || !dateInput) {
			console.warn('sel ou dateInput non disponible');
			return;
		}
		var d = dateInput.value || new Date().toISOString().slice(0,10);
		console.log('Rechargement des médecins pour la date:', d);
		resetSelect(sel, '-- Choisir un médecin --');
		
		// Essayer plusieurs chemins possibles
		var possibleUrls = [
			'../public/getMedecinsRdvByDate.php?date='+encodeURIComponent(d),
			'../../public/getMedecinsRdvByDate.php?date='+encodeURIComponent(d),
			'/APPECv3PHP/pages/apps/public/getMedecinsRdvByDate.php?date='+encodeURIComponent(d)
		];
		
		var lastError = null;
		for (var url of possibleUrls) {
			try {
				console.log('Essai URL:', url);
				const resp = await fetch(url);
				console.log('Réponse reçue:', resp.status, resp.statusText);
				
				const text = await resp.text();
				console.log('Réponse brute:', text);
				
				if (!resp.ok) {
					console.warn('Erreur HTTP:', resp.status, text);
					lastError = 'HTTP '+resp.status;
					continue;
				}
				
				const data = JSON.parse(text);
				console.log('Données reçues:', data);
				
				if (data && data.success && Array.isArray(data.medecins)){
					console.log('Nombre de médecins:', data.medecins.length);
					for (const m of data.medecins){
						const o = document.createElement('option');
						o.value = m.id;
						o.textContent = m.pseudo || ('#'+m.id);
						sel.appendChild(o);
					}
					return; // Succès, sortir de la boucle
				} else {
					console.warn('Format de réponse inattendu:', data);
					lastError = 'Format inattendu';
				}
			} catch(e){ 
				console.error('Erreur avec URL ' + url + ':', e.message);
				lastError = e.message;
			}
		}
		
		// Si on arrive ici, toutes les URLs ont échoué
		console.error('Impossible de charger les médecins avec aucun chemin');
		alert('Erreur lors du chargement des médecins: ' + lastError);
	}
	
	if (dateInput){
		dateInput.addEventListener('change', refreshMedecinsByDate);
		// Charger la liste dès l'arrivée sur la page
		refreshMedecinsByDate();
	}
});
</script>
