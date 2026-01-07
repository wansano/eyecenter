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
									<input type="date" id="datePrintInput" class="form-control" value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d', strtotime('-7 days')); ?>">
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
								<div class="col-sm-4 col-md-2">
									<button id="btnPrintRdvListe" class="btn btn-info w-100" type="button" style="display:none">imprimer la liste</button>
								</div>
								<div class="col-sm-4 col-md-3">
									<button id="btnRapportRdv" class="btn btn-success w-100" type="button" style="display:none">rapport rendez-vous global</button>
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

	<!-- Modal rapport RDV du jour -->
	<div class="modal fade" id="rapportRdvModal" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Rapport des rendez-vous du jour</h5>
					<!-- <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button> -->
				</div>
				<div class="modal-body">
					<div id="rapportRdvContent">
						Chargement...
					</div>
				</div>
				<div class="modal-footer">
					<a id="rapportRdvPrint" class="btn btn-primary" target="_blank" rel="noopener">Imprimer le PDF pour +détails</a>
					<button type="button" class="btn btn-default" data-dismiss="modal" data-bs-dismiss="modal">Fermer</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Modal Impression (aperçu + impression) -->
	<div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-xl modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="printModalTitle">Impression</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body p-0" style="height:80vh;">
					<iframe id="printFrame" src="about:blank" style="width:100%; height:100%;" frameborder="0"></iframe>
				</div>
				<div class="modal-footer">
					<button type="button" id="printBtn" class="btn btn-primary">Imprimer</button>
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
				</div>
			</div>
		</div>
	</div>

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
				
					$hasIdDemande = dbTableHasColumn($bdd, 'dmd_rendez_vous', 'id_demande');
					$reponse1 = $bdd->prepare('SELECT * FROM dmd_rendez_vous WHERE prochain_rdv >= :datejour AND status IN (0,1,2) ORDER BY prochain_rdv');
					$reponse1->execute(['datejour' => date('Y-m-d')]);
					while ($donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC)) {
						$status = $donnees1['status'];
						$color = ($status === 1) ? 'red' : (($status === 2) ? 'green' : '');
						$idPatient = (int)($donnees1['id_patient'] ?? 0);
						$patientTitle = '';
						if ($idPatient > 0) {
							$patientTitle = addslashes(nom_patient($idPatient));
						} else {
							$patientTitle = 'Externe en attente';
							if ($hasIdDemande && !empty($donnees1['id_demande'])) {
								$stN = $bdd->prepare('SELECT nom_patient FROM dossier_en_attente WHERE id_demande = ? LIMIT 1');
								$stN->execute([(int)$donnees1['id_demande']]);
								$nm = $stN->fetchColumn();
								if ($nm) {
									$patientTitle = addslashes($nm . ' (attente)');
								}
							}
						}
						if ($status === 1) {
							$start = $donnees1['prochain_rdv'];
							$rdvId = $donnees1['id_rdv'];
							echo "{\n\ttitle: '" . $patientTitle . "',\n\tstart: '" . $start . "',\n\turl: 'convocationdetails.php?rdv=" . $rdvId . "',\n\tcolor: '" . $color . "',\n\t},\n";
						} elseif ($status === 2) {
							$start = $donnees1['prochain_rdv'];
							$rdvId = $donnees1['id_rdv'];
							echo "{\n\ttitle: '" . $patientTitle . "',\n\tstart: '" . $start . "',\n\turl: 'convocationdetails.php?rdv=" . $rdvId . "',\n\tcolor: '" . $color . "',\n\t},\n";
						} else {
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

	$(function() {
				initCalendar();
				// Modal d'impression (iframe)
				var printModalEl = document.getElementById('printModal');
				var printFrameEl = document.getElementById('printFrame');
				var printBtnEl = document.getElementById('printBtn');
				var printTitleEl = document.getElementById('printModalTitle');

				function withAutoPrintDisabled(url) {
					if (!url) return url;
					return url + (url.indexOf('?') >= 0 ? '&' : '?') + 'autoprint=0';
				}

				function openPrintModal(url, titleText) {
					if (!url) return;
					if (printTitleEl) printTitleEl.textContent = titleText || 'Impression';
					if (!printModalEl || !printFrameEl) {
						window.open(url, '_blank');
						return;
					}
					printFrameEl.src = withAutoPrintDisabled(url);
					if (window.bootstrap && window.bootstrap.Modal) {
						var instance = window.bootstrap.Modal.getInstance(printModalEl) || new window.bootstrap.Modal(printModalEl);
						instance.show();
						return;
					}
					if (window.jQuery && typeof jQuery(printModalEl).modal === 'function') {
						jQuery(printModalEl).modal('show');
						return;
					}
					window.open(url, '_blank');
				}

				if (printBtnEl) {
					printBtnEl.addEventListener('click', function () {
						try {
							var win = printFrameEl && printFrameEl.contentWindow ? printFrameEl.contentWindow : null;
							if (win && typeof win.printPdf === 'function') {
								win.printPdf();
								return;
							}
							if (win && typeof win.print === 'function') {
								win.print();
							}
						} catch (err) {
							// noop
						}
					});
				}

				if (printModalEl) {
					printModalEl.addEventListener('hidden.bs.modal', function () {
						if (printFrameEl) printFrameEl.src = 'about:blank';
					});
					if (window.jQuery && typeof jQuery(printModalEl).on === 'function') {
						jQuery(printModalEl).on('hidden.bs.modal', function () {
							if (printFrameEl) printFrameEl.src = 'about:blank';
						});
					}
				}

				// Impression RDV du jour par médecin
				var btn = document.getElementById('btnPrintRdvListe');
				var btnRapport = document.getElementById('btnRapportRdv');
				var sel = document.getElementById('medecinPrintSelect');
				var dateInput = document.getElementById('datePrintInput');
				if (btn && sel) {
					btn.addEventListener('click', function(){
						var med = sel.value;
						if (!med) { alert('Veuillez choisir un médecin.'); return; }
						var d = (dateInput && dateInput.value) ? dateInput.value : new Date().toISOString().slice(0,10);
						var url = 'imprimer_listerdv.php?date='+encodeURIComponent(d)+'&medecin='+encodeURIComponent(med);
						openPrintModal(url, 'Impression liste des rendez-vous');
					});
				}

				// Rapport RDV du jour (uniquement si le bouton existe)
				function openModal(el){
					if (window.bootstrap && window.bootstrap.Modal){
						new bootstrap.Modal(el).show();
						return;
					}
					if (window.jQuery && typeof jQuery(el).modal === 'function'){
						jQuery(el).modal('show');
					}
				}
				if (btnRapport){
					btnRapport.addEventListener('click', async function(){
						var selectedDate = (dateInput && dateInput.value) ? dateInput.value : new Date().toISOString().slice(0,10);
						var modalEl = document.getElementById('rapportRdvModal');
						var contentEl = document.getElementById('rapportRdvContent');
						var printEl = document.getElementById('rapportRdvPrint');
						if (!modalEl || !contentEl || !printEl) return;

						contentEl.textContent = 'Chargement...';
						try {
							const resp = await fetch('../public/getRapportRdvDuJour.php?date='+encodeURIComponent(selectedDate), { headers: { 'Accept': 'application/json' } });
							if (!resp.ok) throw new Error('HTTP '+resp.status);
							const data = await resp.json();
							if (!data || !data.success){
								throw new Error((data && data.message) ? data.message : 'Erreur');
							}
							if ((data.total ?? 0) <= 0){
								contentEl.textContent = 'Aucun rendez-vous pour cette date.';
								printEl.href = 'imprimer_rapport_rdv.php?date=' + encodeURIComponent(selectedDate);
								openModal(modalEl);
								return;
							}
							var reportDate = (data.date || selectedDate || '');
							contentEl.innerHTML =
								'<div class="mb-2"><strong>Date :</strong> '+reportDate+'</div>'+
								'<table class="table table-bordered table-sm mb-0">'+
									'<thead>'+
										'<tr>'+
											'<th class="text-center">Total RDV</th>'+
											'<th class="text-center">Présents</th>'+
											'<th class="text-center">Absents</th>'+
											'<th class="text-center">Ont payé</th>'+
											'<th class="text-center">N\'ont pas payé</th>'+
										'</tr>'+
									'</thead>'+
									'<tbody>'+
										'<tr>'+
											'<td class="text-center" style="font-size: 18px; font-weight: 700;">'+(data.total ?? 0)+'</td>'+
											'<td class="text-center" style="font-size: 18px; font-weight: 700;">'+(data.present ?? 0)+'</td>'+
											'<td class="text-center" style="font-size: 18px; font-weight: 700;">'+(data.absent ?? 0)+'</td>'+
											'<td class="text-center" style="font-size: 18px; font-weight: 700;">'+(data.paye ?? 0)+'</td>'+
											'<td class="text-center" style="font-size: 18px; font-weight: 700;">'+(data.non_paye ?? 0)+'</td>'+
										'</tr>'+
									'</tbody>'+
								'</table>';

							printEl.href = 'imprimer_rapport_rdv.php?date=' + encodeURIComponent(data.date || selectedDate);
							openModal(modalEl);
						} catch(e){
							console.error('Erreur rapport RDV:', e);
							contentEl.textContent = 'Impossible de charger le rapport.';
							printEl.href = 'imprimer_rapport_rdv.php?date=' + encodeURIComponent(selectedDate);
							openModal(modalEl);
						}
					});
				}

				// Impression du PDF du rapport via le modal d'impression
				var rapportPrintEl = document.getElementById('rapportRdvPrint');
				if (rapportPrintEl) {
					rapportPrintEl.addEventListener('click', function (e) {
						var href = rapportPrintEl.getAttribute('href');
						if (!href) return;
						e.preventDefault();
						openPrintModal(href, 'Impression rapport rendez-vous');
					});
				}

				// Afficher/masquer le bouton rapport selon la date sélectionnée
				async function refreshRapportAvailability(){
					if (!dateInput) return;
					var d = dateInput.value || new Date().toISOString().slice(0,10);
					try {
						const resp = await fetch('../public/getRapportRdvDuJour.php?date='+encodeURIComponent(d), { headers: { 'Accept': 'application/json' } });
						if (!resp.ok) throw new Error('HTTP '+resp.status);
						const data = await resp.json();
						var hasRdv = (data && data.success && (data.total ?? 0) > 0);
						if (btnRapport) btnRapport.style.display = hasRdv ? '' : 'none';
						if (btn) btn.style.display = hasRdv ? '' : 'none';
					} catch(e){
						if (btnRapport) btnRapport.style.display = 'none';
						if (btn) btn.style.display = 'none';
					}
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
					if (!sel || !dateInput) return;
					var d = dateInput.value || new Date().toISOString().slice(0,10);
					resetSelect(sel, '-- Choisir un médecin --');
					try {
						const resp = await fetch('../public/getMedecinsRdvByDate.php?date='+encodeURIComponent(d));
						if (!resp.ok) throw new Error('HTTP '+resp.status);
						const data = await resp.json();
						if (data && data.success && Array.isArray(data.medecins)){
							for (const m of data.medecins){
								const o = document.createElement('option');
								o.value = m.id;
								o.textContent = m.pseudo || ('#'+m.id);
								sel.appendChild(o);
							}
						}
					} catch(e){ console.error('Erreur medecins/date:', e); }
				}
				if (dateInput){
					dateInput.addEventListener('change', refreshMedecinsByDate);
					dateInput.addEventListener('change', refreshRapportAvailability);
					// Charger la liste dès l'arrivée sur la page
					refreshMedecinsByDate();
					refreshRapportAvailability();
				}
	});

}).apply(this, [jQuery]);
</script>
