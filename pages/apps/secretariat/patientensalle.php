<?php
include('../public/connect.php');
session_start();                

include('../public/header.php');
?>

<script>
    // Actualisation toutes les 60 secondes, sans fermer un modal en cours.
    setInterval(function() {
        // Ne pas rafraîchir si un modal est ouvert.
        if (document.querySelector('.modal.show')) {
            return;
        }
        location.reload();
    }, 60000);
</script>

<body>
    <section class="body">
        <?php require('../public/navbarmenu.php'); ?>
        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2> 
                        Liste des patients en salle d'acceuil
                    </h2>
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <div class="row">
                        <div class="col">
                            <section class="card">
                                    
                                <div class="card-body">
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <button type="button" class="btn btn-sm btn-danger" onclick="openGestionPatientsModal('historique')">Historique</button>
                                        <button type="button" class="btn btn-sm btn-warning" onclick="openGestionPatientsModal('recherche')">Recherche</button>
                                        <button type="button" class="btn btn-sm btn-success" onclick="openGestionPatientsModal('ajout')">Nouveau</button>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="openGestionPatientsModal('modification')">Modification</button>
                                        <button type="button" class="btn btn-sm btn-dark" onclick="openGestionPatientsModal('affectation')">Transmettre</button>
                                    </div>

                                <table class="table table-bordered table-striped mb-0" id="datatable-default">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>AFFECTATION</th>
                                            <th>DATE</th>
                                            <th>PATIENT</th>
                                            <th>ADRESSE</th>
                                            <th>CONTACT</th>
                                            <th>CELULLE</th>
                                            <th>EXAMEN</th>
                                            <th>STATUS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php 
                                        try {
                                            $stmt = $bdd->prepare('SELECT * FROM affectations WHERE status IN (1, 2, 6, 99, 7, 8, 9) AND affecter_par = ? ORDER BY id_affectation');
                                            $stmt->execute([$id_user]);
                                            while ($donnees1 = $stmt->fetch(PDO::FETCH_ASSOC)) {                                                
                                                $status = $donnees1['status'];
                                                $patientInfo = getPatientInfo($donnees1['id_patient']);
                                                if (!is_array($patientInfo)) {
                                                    error_log("patientInfo n'est pas un tableau pour l'ID: " . $donnees1['id_patient']);
                                                    $patientInfo = [
                                                        'nom_patient' => 'Non disponible',
                                                        'adresse' => 'Non disponible',
                                                        'phone' => 'Non disponible'
                                                    ];
                                                }
                                                echo '<tr>
                                                    <td>PAT-'.htmlspecialchars($donnees1['id_patient']).'</td>
                                                    <td>AFF-'.htmlspecialchars($donnees1['id_affectation']).'</td>
                                                    <td>'.htmlspecialchars($donnees1['date']).'</td>
                                                    <td>'.htmlspecialchars($patientInfo['nom_patient'] ?: 'Non renseigné').'</td>
                                                    <td>'.htmlspecialchars(adress($patientInfo['adresse']) ?: $patientInfo['adresse']).'</td>
                                                    <td>'.htmlspecialchars($patientInfo['phone'] ?: 'Non renseigné').'</td>
                                                    <td>'.htmlspecialchars(service($donnees1['id_service'])).'</td>
                                                    <td>'.htmlspecialchars(model($donnees1['type'])).'</td>
                                                    <td>';
                                                
                                                if ($status == 6 ) {
                                                    echo '<button type="button" class="btn btn-sm btn-danger">à la caisse</button>
                                                    <button type="button" class="btn btn-sm btn-info" onclick="gpOpenEditAffectation(' . (int)$donnees1['id_affectation'] . ')">modifier l\'affectation</button>';
                                                }
                                                 elseif ($status == 2) {
                                                    echo '<button class="btn btn-sm btn-info">en traitement</button>';
                                                } elseif ($status == 1) {
                                                    echo '<button class="btn btn-sm btn-warning">a payé</button>';
                                                } elseif ($status == 99) {
                                                    echo '<button class="btn btn-sm btn-dark">à rembourser</button>';
                                                } elseif ($status == 7 || $status == 8 || $status == 9) {
                                                    echo '<button class="btn btn-sm btn-dark">au bloc</button>';
                                                }                                                
                                                echo '</td></tr>';
                                            }
                                        } catch (PDOException $e) {
                                            // Log l'erreur avec plus de détails
                                            error_log('Erreur PDO dans patientensalle.php : ' . $e->getMessage());
                                            error_log('Code erreur : ' . $e->getCode());
                                            echo '<div class="alert alert-danger">
                                                <strong>Erreur :</strong> Une erreur est survenue lors de la récupération des données.<br>
                                                Veuillez contacter l\'administrateur système si le problème persiste.
                                            </div>';
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

        <!-- Modal: Gestion Patients (formulaires + résultats, sans iframe) -->
        <div class="modal fade" id="gestionPatientsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="gestionPatientsModalTitle">Gestion Patients</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="gestionPatientsModalBody">
                        <div class="text-muted">Chargement…</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Impression (réutilisable) -->
        <div class="modal fade" id="gpPrintModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="gpPrintModalTitle">Impression</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" style="min-height:70vh;">
                        <iframe id="gpPrintFrame" src="about:blank" style="width:100%; height:65vh; border:0;"></iframe>
                    </div>
                    <div class="modal-footer gap-2">
                        <button type="button" class="btn btn-primary" id="gpPrintBtn">Imprimer</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function gpGetModalEls() {
                return {
                    modalEl: document.getElementById('gestionPatientsModal'),
                    titleEl: document.getElementById('gestionPatientsModalTitle'),
                    bodyEl: document.getElementById('gestionPatientsModalBody')
                };
            }

            function gpOpenPrintModal(url, title) {
                try {
                    if (typeof bootstrap === 'undefined') return;
                    var modalEl = document.getElementById('gpPrintModal');
                    var titleEl = document.getElementById('gpPrintModalTitle');
                    var frameEl = document.getElementById('gpPrintFrame');
                    if (!modalEl || !frameEl) return;

                    if (titleEl) titleEl.textContent = title || 'Impression';
                    frameEl.src = url || 'about:blank';

                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                } catch (e) {
                    // noop
                }
            }

            // Exposer globalement pour le HTML injecté (addpatient modal, etc.)
            window.gpOpenPrintModal = gpOpenPrintModal;

            (function () {
                var btn = document.getElementById('gpPrintBtn');
                if (btn) {
                    btn.addEventListener('click', function () {
                        var frameEl = document.getElementById('gpPrintFrame');
                        if (!frameEl || !frameEl.src) return;
                        try {
                            if (frameEl.contentWindow) {
                                frameEl.contentWindow.focus();

                                // Si la page wrapper expose printPdf(), c'est plus fiable que contentWindow.print()
                                if (typeof frameEl.contentWindow.printPdf === 'function') {
                                    frameEl.contentWindow.printPdf();
                                } else {
                                    frameEl.contentWindow.print();
                                }

                                // Notifier le reste de l'UI qu'une impression a été demandée (pour débloquer Transmettre)
                                try {
                                    window.dispatchEvent(new CustomEvent('gp:printRequested', {
                                        detail: { src: String(frameEl.src || '') }
                                    }));
                                } catch (e2) {}
                                return;
                            }
                        } catch (e) {}
                        window.open(frameEl.src, '_blank');
                    });
                }

                var modalEl = document.getElementById('gpPrintModal');
                if (modalEl) {
                    modalEl.addEventListener('hidden.bs.modal', function () {
                        var frameEl = document.getElementById('gpPrintFrame');
                        if (frameEl) frameEl.src = 'about:blank';
                    });
                }
            })();

            function gpShowModal(title) {
                var els = gpGetModalEls();
                if (!els.modalEl || !els.bodyEl || typeof bootstrap === 'undefined') return null;
                if (els.titleEl) els.titleEl.textContent = title || 'Gestion Patients';
                els.bodyEl.innerHTML = '<div class="text-muted">Chargement…</div>';
                return bootstrap.Modal.getOrCreateInstance(els.modalEl);
            }

            function gpExecuteScripts(container) {
                if (!container) return;
                var scripts = container.querySelectorAll('script');
                scripts.forEach(function (oldScript) {
                    var newScript = document.createElement('script');
                    if (oldScript.src) {
                        newScript.src = oldScript.src;
                    } else {
                        newScript.text = oldScript.textContent || '';
                    }
                    document.body.appendChild(newScript);
                    oldScript.parentNode.removeChild(oldScript);
                });
            }

            function gpInitPlugins(container) {
                try {
                    if (!container || typeof window.jQuery === 'undefined') return;
                    var $ = window.jQuery;

                    var $dropdownParent = $(container).closest('.modal');

                    // Select2 (même logique que theme.init.js, mais scoppée)
                    if ($.isFunction($.fn['select2'])) {
                        $(container).find('[data-plugin-selectTwo]').each(function () {
                            var $this = $(this);
                            if ($this.hasClass('select2-hidden-accessible')) return;

                            var opts = {};
                            var pluginOptions = $this.data('plugin-options');
                            if (pluginOptions) {
                                if (typeof pluginOptions === 'string') {
                                    try {
                                        opts = JSON.parse(pluginOptions);
                                    } catch (e) {
                                        opts = {};
                                    }
                                } else {
                                    opts = pluginOptions;
                                }
                            }

                            // Important: permet la saisie dans un modal Bootstrap
                            if ($dropdownParent && $dropdownParent.length && !opts.dropdownParent) {
                                opts.dropdownParent = $dropdownParent;
                            }

                            if ($.isFunction($.fn.themePluginSelect2)) {
                                $this.themePluginSelect2(opts);
                            } else if ($.isFunction($.fn.adminPluginSelect2)) {
                                $this.adminPluginSelect2(opts);
                            } else {
                                $this.select2(opts);
                            }
                        });
                    }
                } catch (e) {
                    // noop
                }
            }

            function gpInjectCloseButtons(container) {
                try {
                    if (!container) return;
                    var footers = container.querySelectorAll('footer.card-footer, .card-footer, .modal-footer');
                    if (!footers || !footers.length) return;

                    footers.forEach(function (footer) {
                        if (!footer || footer.querySelector('[data-gp-close-btn="1"]')) return;

                        // Si un bouton Fermer (dismiss) existe déjà, ne rien faire.
                        if (footer.querySelector('[data-bs-dismiss="modal"]')) return;

                        // N'ajouter que s'il y a déjà au moins un bouton d'action dans ce footer.
                        var hasActionBtn = !!footer.querySelector('button, a.btn');
                        if (!hasActionBtn) return;

                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'btn btn-danger ms-2';
                        btn.setAttribute('data-bs-dismiss', 'modal');
                        btn.setAttribute('data-gp-close-btn', '1');
                        btn.textContent = 'Fermer';
                        footer.appendChild(btn);
                    });
                } catch (e) {
                    // noop
                }
            }

            async function gpLoadHtml(url, title) {
                var modal = gpShowModal(title);
                var els = gpGetModalEls();
                if (!modal || !els.bodyEl) return;
                modal.show();
                try {
                    var resp = await fetch(url, { headers: { 'Accept': 'text/html' } });
                    var html = await resp.text();
                    els.bodyEl.innerHTML = html;
                    gpExecuteScripts(els.bodyEl);
                    gpInitPlugins(els.bodyEl);
                    gpInjectCloseButtons(els.bodyEl);
                } catch (e) {
                    els.bodyEl.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement.</div>';
                }
            }

            function gpRenderAffectationStart() {
                var modal = gpShowModal('Transmission');
                var els = gpGetModalEls();
                if (!modal || !els.bodyEl) return;
                els.bodyEl.innerHTML = ''
                    + '<div class="col-md-12">'
                    + '  <section class="card">'
                    + '    <div class="card-body">'
                    + '      <div class="row form-group pb-3">'
                    + '        <div class="col-md-6">'
                    + '          <label class="col-form-label">Numéro dossier patient</label>'
                    + '          <input type="text" class="form-control" id="gpAffectPatientId" placeholder="Ex: 123">'
                    + '        </div>'
                    + '      </div>'
                    + '    </div>'
                    + '    <footer class="card-footer text-end">'
                    + '      <button type="button" class="btn btn-primary" id="gpBtnLoadAffect">Continuer</button>'
                    + '      <button type="button" class="btn btn-danger ms-2" data-bs-dismiss="modal" data-gp-close-btn="1">Fermer</button>'
                    + '    </footer>'
                    + '  </section>'
                    + '</div>';
                modal.show();

                var btn = document.getElementById('gpBtnLoadAffect');
                if (btn) {
                    btn.addEventListener('click', gpLoadAffectation, { once: true });
                }
            }

            function gpRenderHistoriqueStart() {
                var modal = gpShowModal('Historique des passages');
                var els = gpGetModalEls();
                if (!modal || !els.bodyEl) return;
                els.bodyEl.innerHTML = ''
                    + '<div class="col-md-12">'
                    + '  <section class="card">'
                    + '    <div class="card-body">'
                    + '      <div class="row form-group pb-3">'
                    + '        <div class="col-md-6">'
                    + '          <label class="col-form-label">Numéro dossier patient</label>'
                    + '          <input type="text" class="form-control" id="gpHistoryPatientId" placeholder="Ex: 123">'
                    + '        </div>'
                    + '      </div>'
                    + '    </div>'
                    + '    <footer class="card-footer text-end">'
                    + '      <button type="button" class="btn btn-primary" id="gpBtnLoadHistory">Rechercher</button>'
                    + '      <button type="button" class="btn btn-danger ms-2" data-bs-dismiss="modal" data-gp-close-btn="1">Fermer</button>'
                    + '    </footer>'
                    + '  </section>'
                    + '</div>'
                    + '<div class="col-md-12 mt-3" id="gpHistoryResult" style="display:none;">'
                    + '  <section class="card">'
                    + '    <div class="card-body">'
                    + '      <div class="table-responsive">'
                    + '        <table class="table table-bordered table-striped mb-0">'
                    + '          <thead><tr><th>PASSAGE</th><th>DATE</th><th>MOTIF</th><th>MONTANT PAYÉ</th></tr></thead>'
                    + '          <tbody id="gpHistoryTbody"><tr><td colspan="4">Saisissez un numéro de dossier.</td></tr></tbody>'
                    + '        </table>'
                    + '      </div>'
                    + '    </div>'
                    + '  </section>'
                    + '</div>';
                modal.show();

                var btn = document.getElementById('gpBtnLoadHistory');
                if (btn) {
                    btn.addEventListener('click', gpLoadHistory, { once: true });
                }
            }

            async function gpLoadHistory() {
                var els = gpGetModalEls();
                if (!els.bodyEl) return;
                var input = document.getElementById('gpHistoryPatientId');
                var pid = input ? String(input.value || '').trim() : '';
                pid = pid.replace(/^PAT-?/i, '').trim();
                if (!pid) {
                    els.bodyEl.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger">Veuillez saisir un numéro dossier.</div>');
                    return;
                }

                var tbody = document.getElementById('gpHistoryTbody');
                var box = document.getElementById('gpHistoryResult');
                var title = document.getElementById('gpHistoryTitle');
                if (tbody) tbody.innerHTML = '<tr><td colspan="4">Chargement…</td></tr>';
                if (box) box.style.display = '';

                try {
                    var resp = await fetch('historiquedossier.php?ajax_history=1&id=' + encodeURIComponent(pid), {
                        headers: { 'Accept': 'application/json' }
                    });
                    var data = await resp.json();
                    if (!data || !data.success) {
                        if (tbody) tbody.innerHTML = '<tr><td colspan="4">' + ((data && data.message) ? data.message : 'Erreur lors du chargement.') + '</td></tr>';
                        return;
                    }
                    if (title) {
                        title.textContent = 'Historique - PAT-' + String(data.patient && data.patient.id_patient ? data.patient.id_patient : pid) + ' ' + String(data.patient && data.patient.nom_patient ? data.patient.nom_patient : '');
                    }

                    var passages = Array.isArray(data.passages) ? data.passages : [];
                    if (!tbody) return;
                    if (!passages.length) {
                        tbody.innerHTML = '<tr><td colspan="4">Aucun passage trouvé.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = '';
                    passages.forEach(function (p) {
                        var tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td>N° ' + String(p.id_affectation ?? '') + '</td>' +
                            '<td>' + String(p.date ?? '') + '</td>' +
                            '<td>' + String(p.motif ?? '') + '</td>' +
                            '<td>' + String(p.montant_paye_label ?? '') + '</td>';
                        tbody.appendChild(tr);
                    });
                } catch (e) {
                    if (tbody) tbody.innerHTML = '<tr><td colspan="4">Erreur lors du chargement.</td></tr>';
                }
            }

            async function gpLoadAffectation() {
                var els = gpGetModalEls();
                if (!els.bodyEl) return;
                var input = document.getElementById('gpAffectPatientId');
                var pid = input ? String(input.value || '').trim() : '';
                pid = pid.replace(/^PAT-?/i, '').trim();
                if (!pid) {
                    els.bodyEl.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger">Veuillez saisir un numéro dossier.</div>');
                    return;
                }

                els.bodyEl.innerHTML = '<div class="text-muted">Chargement…</div>';
                try {
                    var resp = await fetch('transmission-caisse.php?ajax_modal=1&id_patient=' + encodeURIComponent(pid), {
                        headers: { 'Accept': 'application/json' }
                    });
                    var data = await resp.json();
                    if (!data || !data.success || !data.html) {
                        els.bodyEl.innerHTML = '<div class="alert alert-danger">' + ((data && data.message) ? data.message : 'Erreur lors du chargement.') + '</div>';
                        return;
                    }
                    els.bodyEl.innerHTML = data.html;
                    gpExecuteScripts(els.bodyEl);
                    gpInitPlugins(els.bodyEl);
                    gpInjectCloseButtons(els.bodyEl);
                } catch (e) {
                    els.bodyEl.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement.</div>';
                }
            }

            async function gpOpenEditAffectation(idAffectation) {
                var modal = gpShowModal('Modifier l\'affectation');
                var els = gpGetModalEls();
                if (!modal || !els.bodyEl) return;
                modal.show();
                try {
                    var resp = await fetch('modifier-affectation.php?ajax_modal=1&id_affectation=' + encodeURIComponent(idAffectation), {
                        headers: { 'Accept': 'application/json' }
                    });
                    var data = await resp.json();
                    if (!data || !data.success || !data.html) {
                        els.bodyEl.innerHTML = '<div class="alert alert-danger">' + ((data && data.message) ? data.message : 'Erreur lors du chargement.') + '</div>';
                        return;
                    }
                    els.bodyEl.innerHTML = data.html;
                    gpExecuteScripts(els.bodyEl);
                    gpInitPlugins(els.bodyEl);
                    gpInjectCloseButtons(els.bodyEl);
                } catch (e) {
                    els.bodyEl.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement.</div>';
                }
            }

            window.gpOpenEditAffectation = gpOpenEditAffectation;

            function openGestionPatientsModal(action) {
                var a = String(action || '').trim();
                if (!a) return;
                if (a === 'ajout') {
                    gpLoadHtml('addpatient.php?ap=default&modal=1&embed=1&t=' + Date.now(), 'Nouveau');
                    return;
                }
                if (a === 'recherche') {
                    gpLoadHtml('rechercheinformation.php?modal=1&embed=1&t=' + Date.now(), 'Recherche');
                    return;
                }
                if (a === 'affectation') {
                    gpRenderAffectationStart();
                    return;
                }
                if (a === 'modification') {
                    gpLoadHtml('editpatient.php?ep=default&modal=1&embed=1&t=' + Date.now(), 'Modification informations');
                    return;
                }
                if (a === 'historique') {
                    gpRenderHistoriqueStart();
                    return;
                }
            }

            // Soumission AJAX dans le modal: HTML partiel ou JSON (affectation)
            (function () {
                var els = gpGetModalEls();
                if (!els.bodyEl) return;
                els.bodyEl.addEventListener('submit', async function (e) {
                    var form = e.target;
                    if (!form || form.tagName !== 'FORM') return;
                    e.preventDefault();

                    var fd = new FormData(form);
                    var method = String((form.getAttribute('method') || 'POST')).toUpperCase();
                    var action = String(form.getAttribute('action') || window.location.href);

                    // Garantir le mode modal sur les formulaires HTML classiques.
                    try {
                        var u = new URL(action, window.location.href);
                        if (!fd.has('ajax_transmettre')) {
                            u.searchParams.set('modal', '1');
                            u.searchParams.set('embed', '1');
                        }
                        action = u.toString();
                    } catch (err) {
                        // noop
                    }

                    // Affectation / modification d'affectation: endpoint JSON
                    if (fd.has('ajax_transmettre') || fd.has('ajax_edit_affectation')) {
                        try {
                            var r = await fetch(action, {
                                method: method,
                                body: fd,
                                headers: { 'Accept': 'application/json' }
                            });
                            var data = await r.json();
                            if (data && data.html) {
                                els.bodyEl.innerHTML = data.html;
                                gpExecuteScripts(els.bodyEl);
                                gpInitPlugins(els.bodyEl);
                                gpInjectCloseButtons(els.bodyEl);
                                if (data && data.success && data.reload) {
                                    setTimeout(function () { location.reload(); }, 700);
                                }
                            } else {
                                var msg = (data && data.message) ? String(data.message) : 'Erreur lors de l\'opération.';
                                els.bodyEl.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger">' + msg + '</div>');
                            }
                        } catch (err2) {
                            els.bodyEl.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger">Erreur lors de l\'opération.</div>');
                        }
                        return;
                    }

                    // HTML partiel
                    try {
                        var resp = await fetch(action, { method: method, body: fd, headers: { 'Accept': 'text/html' } });
                        var html = await resp.text();
                        els.bodyEl.innerHTML = html;
                        gpExecuteScripts(els.bodyEl);
                        gpInitPlugins(els.bodyEl);
                        gpInjectCloseButtons(els.bodyEl);
                    } catch (err3) {
                        els.bodyEl.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger">Erreur lors de l\'envoi.</div>');
                    }
                });
            })();

            // Fonctions requises par le HTML affectation (injecté)
            function tcUpdateMotifs(serviceSelectEl) {
                try {
                    var form = serviceSelectEl && serviceSelectEl.closest ? serviceSelectEl.closest('form') : null;
                    var motifSelect = form ? form.querySelector('select[name="type"]') : null;
                    if (!motifSelect) return;

                    var serviceId = (serviceSelectEl && serviceSelectEl.value) ? String(serviceSelectEl.value) : '';
                    if (!serviceId) {
                        motifSelect.innerHTML = '<option value=""> ------ Choisir le motif ----- </option>';
                        var hidden = form ? form.querySelector('input[name="motif_id"]') : null;
                        if (hidden) hidden.value = '';
                        return;
                    }

                    motifSelect.innerHTML = '<option value="">Chargement...</option>';
                    fetch('../PUBLIC/getMotifs.php?service=' + encodeURIComponent(serviceId))
                        .then(function (r) {
                            if (!r.ok) throw new Error('HTTP ' + r.status);
                            return r.json();
                        })
                        .then(function (data) {
                            motifSelect.innerHTML = '<option value=""> ------ Choisir le motif ----- </option>';
                            if (data && data.success && Array.isArray(data.motifs)) {
                                data.motifs.forEach(function (motif) {
                                    var opt = document.createElement('option');
                                    opt.value = motif.id;
                                    opt.textContent = motif.nom;
                                    motifSelect.appendChild(opt);
                                });
                            }
                        })
                        .catch(function () {
                            motifSelect.innerHTML = '<option value="">Erreur lors du chargement</option>';
                        });
                } catch (e) {
                    // noop
                }
            }

            function tcOnMotifChange(motifSelectEl) {
                try {
                    var form = motifSelectEl && motifSelectEl.closest ? motifSelectEl.closest('form') : null;
                    var motifId = (motifSelectEl && motifSelectEl.value) ? String(motifSelectEl.value) : '';
                    var hidden = form ? form.querySelector('input[name="motif_id"]') : null;
                    if (hidden) hidden.value = motifId ? motifId : '';
                } catch (e) {
                    // noop
                }
            }

            function tcContinueDespiteSuggestion() {
                try {
                    var els = gpGetModalEls();
                    var form = els.bodyEl ? els.bodyEl.querySelector('#tcAffectationForm') : null;
                    if (!form) return;
                    var force = form.querySelector('#tcForceContinue');
                    if (force) force.value = '1';
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                } catch (e) {
                    // noop
                }
            }

            function tcSetConsultationAndSubmit(serviceId, motifId) {
                try {
                    var els = gpGetModalEls();
                    var form = els.bodyEl ? els.bodyEl.querySelector('#tcAffectationForm') : null;
                    if (!form) return;

                    var serviceSelect = form.querySelector('#tcServiceSelect');
                    var motifSelect = form.querySelector('#tcMotifSelect');
                    var hiddenMotif = form.querySelector('#tcHiddenMotifId');
                    if (!serviceSelect || !motifSelect || !hiddenMotif) return;

                    if (serviceId) {
                        serviceSelect.value = String(serviceId);
                        tcUpdateMotifs(serviceSelect);
                    }

                    var tries = 0;
                    var maxTries = 30;
                    var timer = setInterval(function () {
                        tries++;
                        var opt = motifSelect.querySelector('option[value="' + String(motifId) + '"]');
                        if (opt) {
                            motifSelect.value = String(motifId);
                            hiddenMotif.value = String(motifId);
                            clearInterval(timer);
                            if (typeof form.requestSubmit === 'function') {
                                form.requestSubmit();
                            } else {
                                form.submit();
                            }
                        }
                        if (tries >= maxTries) {
                            clearInterval(timer);
                        }
                    }, 150);
                } catch (e) {
                    // noop
                }
            }
        </script>
        <?php include('../PUBLIC/footer.php'); ?>
</body>