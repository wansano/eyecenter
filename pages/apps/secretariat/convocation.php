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
							<div class="row mb-3">
								<div class="col-sm-6 col-md-2">
									<button id="btnOpenAddRdv" class="btn btn-primary w-100" type="button">ajouter rendez-vous</button>
								</div>
								<div class="col-sm-6 col-md-2">
									<button id="btnOpenCheckRdv" class="btn btn-success w-100" type="button">vérifier rendez-vous</button>
								</div>
							</div>
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
									<button id="btnPrintRdvListe" class="btn btn-info w-100" type="button" style="display:none">liste rendez-vous</button>
								</div>
								<div class="col-sm-4 col-md-3">
									<button id="btnRapportRdv" class="btn btn-success w-100" type="button" style="display:none">rapport rendez-vous</button>
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
					<a id="rapportRdvPrint" class="btn btn-primary">Imprimer le PDF pour +détails</a>
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

	<!-- Modal: Ajouter un RDV -->
	<div class="modal fade" id="addRdvModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-xl modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Ajouter un rendez-vous</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body" id="addRdvModalBody">
					<div class="text-muted">Chargement…</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Modal: Vérifier un RDV (AJAX) -->
	<div class="modal fade" id="checkRdvModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-lg modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Vérifier un rendez-vous</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div id="checkRdvAlert" class="alert d-none" role="alert"></div>
					<div class="row g-2 align-items-end">
						<div class="col-md-4">
							<label class="col-form-label" for="checkRdvMode">Rechercher par</label>
							<select id="checkRdvMode" class="form-control">
								<option value="dossier">Numéro dossier</option>
								<option value="phone">Téléphone</option>
							</select>
						</div>
						<div class="col-md-8">
							<label class="col-form-label" id="checkRdvQueryLabel" for="checkRdvQuery">Numéro dossier</label>
							<input type="text" id="checkRdvQuery" class="form-control" placeholder="Ex: 123" autocomplete="off">
						</div>
						<div class="col-md-12 pt-2">
							<button type="button" class="btn btn-primary w-100" id="btnDoCheckRdv">Rechercher</button>
						</div>
					</div>
					<hr/>
					<div class="table-responsive">
						<table class="table table-bordered table-striped mb-0">
							<thead>
								<tr>
									<th>Dossier</th>
									<th>Date</th>
									<th>Service</th>
									<th>Motif</th>
									<th>Médecin</th>
									<th>Statut</th>
									<th>Action</th>
								</tr>
							</thead>
							<tbody id="checkRdvTbody">
								<tr><td colspan="8" class="text-muted">Saisissez un critère puis cliquez sur Rechercher.</td></tr>
							</tbody>
						</table>
					</div>
				</div>
				<div class="modal-footer">
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
				// Modals RDV: Ajouter / Vérifier
				var addModalEl = document.getElementById('addRdvModal');
				var addBodyEl = document.getElementById('addRdvModalBody');
				var btnOpenAdd = document.getElementById('btnOpenAddRdv');
				var btnOpenCheck = document.getElementById('btnOpenCheckRdv');
				var checkModalEl = document.getElementById('checkRdvModal');
				var checkModeEl = document.getElementById('checkRdvMode');
				var checkQueryEl = document.getElementById('checkRdvQuery');
				var checkLabelEl = document.getElementById('checkRdvQueryLabel');
				var checkBtnEl = document.getElementById('btnDoCheckRdv');
				var checkAlertEl = document.getElementById('checkRdvAlert');
				var checkTbodyEl = document.getElementById('checkRdvTbody');

				function showAlert(type, msg){
					if (!checkAlertEl) return;
					if (!msg){
						checkAlertEl.className = 'alert d-none';
						checkAlertEl.textContent = '';
						return;
					}
					checkAlertEl.className = 'alert alert-' + (type || 'danger');
					checkAlertEl.textContent = String(msg);
					checkAlertEl.classList.remove('d-none');
				}

				function openBsModal(el){
					if (!el) return;
					if (window.bootstrap && window.bootstrap.Modal){
						(window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el)).show();
						return;
					}
					if (window.jQuery && typeof jQuery(el).modal === 'function'){
						jQuery(el).modal('show');
					}
				}

				function execScripts(container){
					if (!container) return;
					var scripts = container.querySelectorAll('script');
					scripts.forEach(function(oldScript){
						var s = document.createElement('script');
						if (oldScript.src){
							// Ne pas recharger des libs déjà présentes (sinon freeze/dup handlers)
							var src = oldScript.getAttribute('src');
							if (src && !document.querySelector('script[src="' + src.replace(/"/g, '\\"') + '"]')){
								s.src = src;
								document.body.appendChild(s);
							}
						} else {
							s.text = oldScript.textContent || '';
							document.body.appendChild(s);
						}
						oldScript.parentNode.removeChild(oldScript);
					});
				}

				function initPlugins(container){
					try {
						if (!container || typeof window.jQuery === 'undefined') return;
						var $ = window.jQuery;
						var $dropdownParent = $(container).closest('.modal');
						if ($.isFunction($.fn['select2'])){
							$(container).find('[data-plugin-selectTwo]').each(function(){
								var $this = $(this);
								if ($this.hasClass('select2-hidden-accessible')) return;
								var opts = {};
								var pluginOptions = $this.data('plugin-options');
								if (pluginOptions){
									if (typeof pluginOptions === 'string'){
										try { opts = JSON.parse(pluginOptions); } catch(e){ opts = {}; }
									} else {
										opts = pluginOptions;
									}
								}
								if ($dropdownParent && $dropdownParent.length && !opts.dropdownParent){
									opts.dropdownParent = $dropdownParent;
								}
								if ($.isFunction($.fn.themePluginSelect2)){
									$this.themePluginSelect2(opts);
								} else if ($.isFunction($.fn.adminPluginSelect2)){
									$this.adminPluginSelect2(opts);
								} else {
									$this.select2(opts);
								}
							});
						}
					} catch(e) {}
				}

				function wireCreneaux(container){
					if (!container) return;
					var dateInput = container.querySelector('#dateRdvInput');
					var medecinSelect = container.querySelector('select[name="medecin"], #medecinSelect');
					var creneauSelect = container.querySelector('#creneauSelect');
					if (!dateInput || !medecinSelect || !creneauSelect) return;

					// éviter de binder plusieurs fois après rechargements
					if (dateInput.dataset && dateInput.dataset.apCreneauxWired === '1') return;
					if (dateInput.dataset) dateInput.dataset.apCreneauxWired = '1';

					function isSunday(dateStr){
						try {
							var d = new Date(String(dateStr) + 'T00:00:00');
							return !isNaN(d.getTime()) && d.getDay() === 0;
						} catch(e){ return false; }
					}

					function resetCreneaux(msg){
						creneauSelect.innerHTML = '<option value="">' + (msg || '-- Choisir un créneau disponible --') + '</option>';
						creneauSelect.disabled = true;
						try { if (window.jQuery && window.jQuery(creneauSelect).data('select2')) window.jQuery(creneauSelect).trigger('change'); } catch(e) {}
					}

					async function fallbackFetchCreneaux(date, medecinId){
						if (!date || !medecinId) {
							resetCreneaux('-- Choisir un créneau disponible --');
							return;
						}
						if (isSunday(date)) {
							creneauSelect.innerHTML = '<option value="">Pas de rendez-vous le dimanche</option>';
							creneauSelect.disabled = true;
							return;
						}
						creneauSelect.innerHTML = '<option value="">Chargement…</option>';
						creneauSelect.disabled = true;
						try {
							var url = '../public/getCreneaux.php?date=' + encodeURIComponent(date) + '&medecin=' + encodeURIComponent(medecinId) + '&format=simple';
							var resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
							var data = await resp.json();
							creneauSelect.innerHTML = '<option value="">-- Choisir un créneau disponible --</option>';
							if (!Array.isArray(data) || data.length === 0) {
								var o = document.createElement('option');
								o.value = '';
								o.textContent = 'Aucun créneau libre';
								o.disabled = true;
								creneauSelect.appendChild(o);
								creneauSelect.disabled = false;
								return;
							}
							data.forEach(function(creneau){
								var opt = document.createElement('option');
								var display = String(creneau || '');
								try {
									if (display.indexOf('T') !== -1) display = display.split('T')[1].slice(0,5);
									else if (display.indexOf(' ') !== -1) display = display.split(' ')[1].slice(0,5);
									else display = display.slice(0,5);
								} catch(e) {}
								opt.value = String(creneau || '');
								opt.textContent = display;
								creneauSelect.appendChild(opt);
							});
							creneauSelect.disabled = false;
							try { if (window.jQuery && window.jQuery(creneauSelect).data('select2')) window.jQuery(creneauSelect).trigger('change'); } catch(e) {}
						} catch(e){
							creneauSelect.innerHTML = '<option value="">Erreur de chargement</option>';
							creneauSelect.disabled = true;
						}
					}

					function update(){
						var date = String(dateInput.value || '').trim();
						var med = String(medecinSelect.value || '').trim();
						if (!date || !med) {
							resetCreneaux('-- Choisir médecin et date --');
							return;
						}
						if (typeof window.updateCreneauxGlobal === 'function') {
							window.updateCreneauxGlobal();
							return;
						}
						if (typeof window.genererCreneaux === 'function') {
							window.genererCreneaux(date, med);
							return;
						}
						fallbackFetchCreneaux(date, med);
					}

					dateInput.addEventListener('change', update);
					dateInput.addEventListener('input', update);
					medecinSelect.addEventListener('change', update);

					// initial
					update();
				}

				async function loadAddRdvForm(url){
					if (!addBodyEl) return;
					addBodyEl.innerHTML = '<div class="text-muted">Chargement…</div>';
					try {
						var resp = await fetch(url, { headers: { 'Accept': 'text/html' } });
						var html = await resp.text();
						addBodyEl.innerHTML = html;
						execScripts(addBodyEl);
						initPlugins(addBodyEl);
						wireCreneaux(addBodyEl);
						try { if (typeof window.apUpdateRdvFlowState === 'function') window.apUpdateRdvFlowState(); } catch(e) {}
					} catch(e){
						addBodyEl.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement.</div>';
					}
				}

				function syncCheckLabel(){
					if (!checkModeEl || !checkLabelEl || !checkQueryEl) return;
					if (checkModeEl.value === 'phone'){
						checkLabelEl.textContent = 'Téléphone';
						checkQueryEl.placeholder = 'Ex: 628 48 35 60';
					} else {
						checkLabelEl.textContent = 'Numéro dossier';
						checkQueryEl.placeholder = 'Ex: 123';
					}
				}

				async function doCheckRdv(){
					if (!checkModeEl || !checkQueryEl || !checkTbodyEl) return;
					showAlert(null, '');
					var mode = String(checkModeEl.value || '').trim();
					var q = String(checkQueryEl.value || '').trim();
					q = q.replace(/^PAT-?/i, '').trim();
					if (!q){
						showAlert('danger', 'Veuillez saisir une valeur.');
						return;
					}
					checkTbodyEl.innerHTML = '<tr><td colspan="8">Chargement…</td></tr>';
					try {
						var url = 'ajoutrdv.php?ajax_check_rdv=1&mode=' + encodeURIComponent(mode) + '&q=' + encodeURIComponent(q);
						var resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
						var data = await resp.json();
						if (!data || !data.success){
							checkTbodyEl.innerHTML = '<tr><td colspan="8">Erreur lors de la vérification.</td></tr>';
							showAlert('danger', (data && data.message) ? data.message : 'Erreur lors de la vérification.');
							return;
						}
						var rdvs = Array.isArray(data.rdvs) ? data.rdvs : [];
						if (!rdvs.length){
							checkTbodyEl.innerHTML = '<tr><td colspan="8" class="text-muted">Aucun rendez-vous à venir trouvé.</td></tr>';
							return;
						}
						checkTbodyEl.innerHTML = '';
						rdvs.forEach(function(r){
							var tr = document.createElement('tr');
							var rdvId = String(r.id_rdv ?? '');
							var openUrl = 'convocationdetails.php?rdv=' + encodeURIComponent(rdvId);
							tr.innerHTML =
								'<td>' + String(r.dossier_label ?? '') + '</td>' +
								'<td>' + String(r.prochain_rdv ?? '') + '</td>' +
								'<td>' + String(r.service ?? '') + '</td>' +
								'<td>' + String(r.motif ?? '') + '</td>' +
								'<td>' + String(r.medecin ?? '') + '</td>' +
								'<td>' + String(r.status_label ?? '') + '</td>' +
								'<td><a class="btn btn-sm btn-info" href="' + openUrl + '" rel="noopener">Afficher</a></td>';
							checkTbodyEl.appendChild(tr);
						});
					} catch(e){
						console.error('check rdv error', e);
						checkTbodyEl.innerHTML = '<tr><td colspan="8">Erreur lors de la vérification.</td></tr>';
						showAlert('danger', 'Erreur lors de la vérification.');
					}
				}

				if (btnOpenAdd) {
					btnOpenAdd.addEventListener('click', function(){
						loadAddRdvForm('ajoutrdv.php?modal=1&embed=1&t=' + Date.now());
						openBsModal(addModalEl);
					});
				}
				if (addModalEl) {
					addModalEl.addEventListener('hidden.bs.modal', function(){
						if (addBodyEl) addBodyEl.innerHTML = '<div class="text-muted">Chargement…</div>';
					});
					if (window.jQuery && typeof jQuery(addModalEl).on === 'function') {
						jQuery(addModalEl).on('hidden.bs.modal', function(){
							if (addBodyEl) addBodyEl.innerHTML = '<div class="text-muted">Chargement…</div>';
						});
					}
				}

				// Soumission AJAX du formulaire RDV dans le modal (évite la navigation)
				if (addBodyEl) {
					addBodyEl.addEventListener('submit', async function(e){
						var form = e.target;
						if (!form || form.tagName !== 'FORM') return;
						// Ne capter que le formulaire principal d'ajout RDV
						if (!form.querySelector('input[name="ajouter"]')) return;
						e.preventDefault();
						try {
							var fd = new FormData(form);
							var method = String((form.getAttribute('method') || 'POST')).toUpperCase();
							var action = String(form.getAttribute('action') || 'ajoutrdv.php?modal=1&embed=1');
							var resp = await fetch(action, { method: method, body: fd, headers: { 'Accept': 'text/html' } });
							var html = await resp.text();
							addBodyEl.innerHTML = html;
							execScripts(addBodyEl);
							initPlugins(addBodyEl);
							wireCreneaux(addBodyEl);
							try { if (typeof window.apUpdateRdvFlowState === 'function') window.apUpdateRdvFlowState(); } catch(e) {}
						} catch(err){
							addBodyEl.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger">Erreur lors de l\'envoi.</div>');
						}
					});
				}
				if (btnOpenCheck) {
					btnOpenCheck.addEventListener('click', function(){
						showAlert(null, '');
						if (checkModeEl) checkModeEl.value = 'dossier';
						if (checkQueryEl) checkQueryEl.value = '';
						syncCheckLabel();
						if (checkTbodyEl) checkTbodyEl.innerHTML = '<tr><td colspan="8" class="text-muted">Saisissez un critère puis cliquez sur Rechercher.</td></tr>';
						openBsModal(checkModalEl);
						setTimeout(function(){ try { if (checkQueryEl) checkQueryEl.focus(); } catch(e){} }, 50);
					});
				}
				if (checkModeEl) checkModeEl.addEventListener('change', syncCheckLabel);
				if (checkBtnEl) checkBtnEl.addEventListener('click', doCheckRdv);
				if (checkQueryEl) {
					checkQueryEl.addEventListener('keydown', function(e){
						if (e && e.key === 'Enter'){
							e.preventDefault();
							doCheckRdv();
						}
					});
				}
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
						if (typeof window.openPrintModal === 'function') {
							window.openPrintModal(url, 'Impression');
						}
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
					if (typeof window.openPrintModal === 'function') {
						window.openPrintModal(url, 'Impression');
					}
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
											'<th class="text-center">Vus médecin</th>'+
										'</tr>'+
									'</thead>'+
									'<tbody>'+
										'<tr>'+
											'<td class="text-center" style="font-size: 18px; font-weight: 700;">'+(data.total ?? 0)+'</td>'+
											'<td class="text-center" style="font-size: 18px; font-weight: 700;">'+(data.present ?? 0)+'</td>'+
											'<td class="text-center" style="font-size: 18px; font-weight: 700;">'+(data.absent ?? 0)+'</td>'+
											'<td class="text-center" style="font-size: 18px; font-weight: 700;">'+(data.paye ?? 0)+'</td>'+
											'<td class="text-center" style="font-size: 18px; font-weight: 700;">'+(data.non_paye ?? 0)+'</td>'+
											'<td class="text-center" style="font-size: 18px; font-weight: 700;">'+(data.vu ?? 0)+'</td>'+
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
