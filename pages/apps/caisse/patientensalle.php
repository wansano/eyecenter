<?php
include('../PUBLIC/connect.php');
session_start();
$errors = 0;

// Vérifier les preuves de caisse du jour (par compte)
try {
    $proofStmt = $bdd->prepare('SELECT COUNT(*) FROM preuvedecaisse WHERE date_rapportement = ? AND id_user = ?');
    $proofStmt->execute([date('Y-m-d'), $_SESSION['auth']]);
    $closedCount = (int)$proofStmt->fetchColumn();

    $totalStmt = $bdd->prepare('SELECT COUNT(*) FROM comptes WHERE defaut = 1 AND compte_pour = ? AND status = 1');
    $totalStmt->execute([1]);
    $totalCount = (int)$totalStmt->fetchColumn();

    $hasClotureCaisse = ($closedCount > 0);
    $allComptesClotures = ($totalCount > 0) && ($closedCount >= $totalCount);
} catch (Exception $e) {
    error_log('Erreur vérification clôture caisse: ' . $e->getMessage());
    $hasClotureCaisse = false;
    $allComptesClotures = false;
}                 

include('../PUBLIC/header.php');
?>

<?php if (!$allComptesClotures): ?>
<script>
    // Actualisation seulement si tous les comptes ne sont pas clôturés
    setTimeout(function() { location.reload(); }, 60000);
</script>
<?php endif; ?>

<body>
    <section class="body">
        <?php require('../PUBLIC/navbarmenu.php'); ?>
        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2> Liste des patients en attente de paiement </h2>
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <div class="row">
                        <div class="col">
                            <section class="card">
                                <div class="card-body">
                                <?php if ($hasClotureCaisse): ?>
                                    <div class="alert alert-info">
                                        <strong>Information</strong><br>
                                        Une ou plusieurs preuves de caisse ont déjà été effectuées aujourd'hui. Les comptes clôturés ne seront plus proposés pour de nouveaux paiements.
                                    </div>
                                <?php endif; ?>
                                <?php 
                                if (isset($types) && $types == "caisse" && $errors == 7) {
                                    echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Le paiement des frais de traitement a été annulé.</li></div>';
                                }
                                ?>
                                <table class="table table-bordered table-striped mb-0" id="datatable-default">
                                    <thead>
                                        <tr>
                                            <th>AFFECTATION</th>
                                            <th>PATIENT</th>
                                            <th>CONTACT</th>
                                            <th>EXAMEN</th>
                                            <th>MONTANT</th>
                                            <th>ACTION</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                        try {
                                            $sql = "SELECT id_affectation, id_patient, id_service, type, date, status FROM affectations WHERE status IN (6, 3) AND id_service IN (1, 2, 3, 4) ORDER BY id_affectation";
                                            $stmt = $bdd->prepare($sql);
                                            $stmt->execute();

                                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                $status  = (int)$row['status'];
                                                $patientInfo = getPatientInfo($row['id_patient']) ?: [
                                                    'nom_patient' => '—',
                                                    'phone'       => '—',
                                                ];
                                                $montant = (float) montant($row['type']);
                                                $modele  = model($row['type']);

                                                echo '<tr>';
                                                echo '<td>'.htmlspecialchars($row["date"], ENT_QUOTES, "UTF-8").'</td>';
                                                echo '<td>'.htmlspecialchars($patientInfo["nom_patient"], ENT_QUOTES, "UTF-8").'</td>';
                                                echo '<td>'.htmlspecialchars($patientInfo["phone"], ENT_QUOTES, "UTF-8").'</td>';
                                                echo '<td>'.htmlspecialchars($modele, ENT_QUOTES, "UTF-8").'</td>';
                                                echo '<td>'.number_format($montant, 0, ",", " ").' '.htmlspecialchars($devise ?? "", ENT_QUOTES, "UTF-8").'</td>';
                                                echo '<td>';
                                                if ($status === 6) {
                                                    echo '<button type="button" class="btn btn-sm btn-success js-open-paiement" '
                                                        .'data-patient="'.htmlspecialchars((string)$row['id_patient'], ENT_QUOTES, 'UTF-8').'" '
                                                        .'data-affectation="'.htmlspecialchars((string)$row['id_affectation'], ENT_QUOTES, 'UTF-8').'">'
                                                        .'<i class="fa-regular fa-credit-card"></i> Paiement</button>';
                                                }
                                                echo '</td>';
                                                echo '</tr>';
                                            }
                                        } catch (PDOException $e) {
                                            error_log($e->getMessage());
                                            echo '<div class="alert alert-danger">Une erreur est survenue lors de la récupération des données.</div>';
                                        }
                                    ?>
                                    </tbody>
                                </table>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <?php include('../PUBLIC/footer.php'); ?>

        <!-- Modal Paiement (formulaire) -->
        <div class="modal fade" id="paiementModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Effectuer le paiement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="paiementAlert" class="alert d-none" role="alert"></div>

                        <div class="mb-3">
                            <div><strong>Patient :</strong> <span id="paiementPatient">—</span></div>
                            <div><strong>Examen :</strong> <span id="paiementExamen">—</span></div>
                            <div><strong>Montant :</strong> <span id="paiementMontant">—</span></div>
                        </div>

                        <form id="paiementForm" autocomplete="off">
                            <input type="hidden" name="ajax_payment" value="1">
                            <input type="hidden" name="id_patient" id="paiementIdPatient" value="">
                            <input type="hidden" name="id_affectation" id="paiementIdAffectation" value="">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="paiementType">Mode de paiement</label>
                                    <select class="form-control" name="type_paiement" id="paiementType" required>
                                        <option value="">Chargement…</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="paiementTaux">Taux</label>
                                    <select class="form-control" name="taux" id="paiementTaux" required>
                                        <option value="0">Non Appliqué</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a id="paiementRecuBtn" href="#" target="_blank" class="btn btn-light d-none">Imprimer le reçu</a>
                                <button type="submit" id="paiementSubmit" class="btn btn-success">
                                    <i class="fa-regular fa-credit-card"></i> Valider le paiement
                                </button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalEl = document.getElementById('paiementModal');
            const alertEl = document.getElementById('paiementAlert');
            const formEl = document.getElementById('paiementForm');
            const submitEl = document.getElementById('paiementSubmit');
            const recuBtnEl = document.getElementById('paiementRecuBtn');

            const patientEl = document.getElementById('paiementPatient');
            const examenEl = document.getElementById('paiementExamen');
            const montantEl = document.getElementById('paiementMontant');
            const idPatientEl = document.getElementById('paiementIdPatient');
            const idAffectEl = document.getElementById('paiementIdAffectation');
            const typeEl = document.getElementById('paiementType');
            const tauxEl = document.getElementById('paiementTaux');

            if (!modalEl || !formEl || !submitEl || !typeEl || !tauxEl) return;

            function showAlert(message, kind) {
                if (!alertEl) return;
                alertEl.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info');
                alertEl.classList.add('alert-' + (kind || 'info'));
                alertEl.textContent = message || '';
            }

            function hideAlert() {
                if (!alertEl) return;
                alertEl.classList.add('d-none');
                alertEl.textContent = '';
            }

            function setSelectOptions(selectEl, items, placeholder) {
                selectEl.innerHTML = '';
                if (placeholder) {
                    const opt0 = document.createElement('option');
                    opt0.value = '';
                    opt0.textContent = placeholder;
                    selectEl.appendChild(opt0);
                }
                (items || []).forEach(function (it) {
                    const opt = document.createElement('option');
                    opt.value = String(it.id ?? it.value ?? '');
                    opt.textContent = String(it.label ?? '');
                    selectEl.appendChild(opt);
                });
            }

            async function loadPaymentForm(idPatient, idAffectation) {
                hideAlert();
                recuBtnEl.classList.add('d-none');
                recuBtnEl.setAttribute('href', '#');
                submitEl.disabled = true;
                submitEl.textContent = 'Chargement…';

                patientEl.textContent = '—';
                examenEl.textContent = '—';
                montantEl.textContent = '—';

                idPatientEl.value = String(idPatient || '');
                idAffectEl.value = String(idAffectation || '');

                setSelectOptions(typeEl, [], 'Chargement…');
                setSelectOptions(tauxEl, [{ value: 0, label: 'Non Appliqué' }], null);

                const url = 'paiementdesfrais.php?ajax_payment_form=1&id_patient=' + encodeURIComponent(idPatient) + '&id_affectation=' + encodeURIComponent(idAffectation);
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();

                if (!data || !data.success) {
                    showAlert((data && data.message) ? data.message : 'Erreur de chargement.', 'danger');
                    submitEl.disabled = true;
                    submitEl.textContent = 'Valider le paiement';
                    return;
                }

                const pat = data.patient || {};
                const aff = data.affectation || {};
                patientEl.textContent = pat.nom_patient || '—';
                examenEl.textContent = aff.motif || '—';
                montantEl.textContent = (aff.montant_label || '0') + ' <?php echo htmlspecialchars($devise ?? "", ENT_QUOTES, "UTF-8"); ?>';

                const comptes = (data.options && data.options.comptes) ? data.options.comptes : [];
                const taux = (data.options && data.options.taux) ? data.options.taux : [{ value: 0, label: 'Non Appliqué' }];

                setSelectOptions(typeEl, comptes.map(function (c) { return { id: c.id, label: c.label }; }), 'Sélectionner…');
                setSelectOptions(tauxEl, taux.map(function (t) { return { value: t.value, label: t.label }; }), null);

                if (data.blocked || comptes.length === 0) {
                    showAlert((data && data.blocked_message) ? data.blocked_message : "Aucun compte disponible.", 'warning');
                    submitEl.disabled = true;
                    submitEl.textContent = 'Valider le paiement';
                    return;
                }

                if (data.already_paid) {
                    showAlert('Paiement déjà effectué pour cette affectation.', 'warning');
                    submitEl.disabled = true;
                    if (aff.id_affectation) {
                        recuBtnEl.classList.remove('d-none');
                        recuBtnEl.setAttribute('href', 'imprimer_recu.php?affectation=' + encodeURIComponent(aff.id_affectation));
                    }
                } else {
                    submitEl.disabled = false;
                }

                submitEl.textContent = 'Valider le paiement';
            }

            document.addEventListener('click', function (e) {
                const btn = e.target.closest('.js-open-paiement');
                if (!btn) return;
                const idPatient = btn.getAttribute('data-patient');
                const idAffectation = btn.getAttribute('data-affectation');
                if (!idPatient || !idAffectation) return;

                if (window.bootstrap) {
                    const instance = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
                    instance.show();
                }
                loadPaymentForm(idPatient, idAffectation).catch(function () {
                    showAlert('Erreur lors du chargement du formulaire.', 'danger');
                    submitEl.disabled = true;
                    submitEl.textContent = 'Valider le paiement';
                });
            });

            formEl.addEventListener('submit', async function (e) {
                e.preventDefault();
                hideAlert();

                submitEl.disabled = true;
                const previousText = submitEl.textContent;
                submitEl.textContent = 'Traitement…';

                try {
                    const fd = new FormData(formEl);
                    const res = await fetch('paiementdesfrais.php', {
                        method: 'POST',
                        body: fd,
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await res.json();

                    if (!data || !data.success) {
                        showAlert((data && data.message) ? data.message : 'Paiement échoué.', 'danger');
                        if (data && data.receipt_url) {
                            recuBtnEl.classList.remove('d-none');
                            recuBtnEl.setAttribute('href', data.receipt_url);
                        }
                        submitEl.disabled = false;
                        submitEl.textContent = previousText;
                        return;
                    }

                    showAlert(data.message || 'Paiement effectué.', 'success');
                    if (data.receipt_url) {
                        recuBtnEl.classList.remove('d-none');
                        recuBtnEl.setAttribute('href', data.receipt_url);
                        window.open(data.receipt_url, '_blank');
                    }

                    // Rafraîchir la liste (statuts/montants) après succès
                    setTimeout(function () { window.location.reload(); }, 800);
                } catch (err) {
                    showAlert('Erreur lors du paiement.', 'danger');
                    submitEl.disabled = false;
                    submitEl.textContent = previousText;
                }
            });

            modalEl.addEventListener('hidden.bs.modal', function () {
                hideAlert();
                recuBtnEl.classList.add('d-none');
                recuBtnEl.setAttribute('href', '#');
                submitEl.disabled = false;
                submitEl.textContent = 'Valider le paiement';
                patientEl.textContent = '—';
                examenEl.textContent = '—';
                montantEl.textContent = '—';
                typeEl.innerHTML = '<option value="">Sélectionner…</option>';
                tauxEl.innerHTML = '<option value="0">Non Appliqué</option>';
            });
        });
        </script>
</body>