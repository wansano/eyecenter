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

// Détection colonne prix_assurance (selon schéma DB)
$traitementsHasPrixAssurance = false;
try {
    $bdd->query('SELECT prix_assurance FROM traitements LIMIT 1');
    $traitementsHasPrixAssurance = true;
} catch (PDOException $e) {
    $traitementsHasPrixAssurance = false;
}

if (!function_exists('appec_isCardValid')) {
    function appec_isCardValid($dateStr): bool
    {
        $s = trim((string)$dateStr);
        if ($s === '') return false;

        $tryFormats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'Y/m/d', 'd-m-Y', 'm-d-Y'];
        $dt = null;
        foreach ($tryFormats as $fmt) {
            $tmp = DateTimeImmutable::createFromFormat($fmt, $s);
            if ($tmp instanceof DateTimeImmutable) {
                $errors = DateTimeImmutable::getLastErrors();
                if (empty($errors['warning_count']) && empty($errors['error_count'])) {
                    $dt = $tmp;
                    break;
                }
            }
        }

        if (!$dt) {
            $ts = strtotime($s);
            if ($ts === false) return false;
            $dt = (new DateTimeImmutable())->setTimestamp($ts);
        }

        $expiryEnd = $dt->setTime(23, 59, 59);
        $now = new DateTimeImmutable();
        return $expiryEnd >= $now;
    }
}

if (!function_exists('appec_toFloat')) {
    function appec_toFloat($value): float
    {
        if ($value === null) return 0.0;
        if (is_float($value) || is_int($value)) return (float)$value;
        $s = trim((string)$value);
        if ($s === '') return 0.0;
        $s = str_replace([' ', ','], ['', '.'], $s);
        return (float)$s;
    }
}
?>

<?php if (!$allComptesClotures): ?>
<script>
    // Actualisation seulement si tous les comptes ne sont pas clôturés
    setInterval(function() {
        // Ne pas recharger pendant qu'un modal est ouvert (sinon on perd l'impression)
        if (document.querySelector('.modal.show')) return;
        location.reload();
    }, 60000);
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
                                            <th>N° PAT</th>
                                            <th>PATIENT</th>
                                            <th>CONTACT</th>
                                            <th>EXAMEN</th>
                                            <th>MONTANT</th>
                                            <th>ACTION</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                        $prixAssuranceCache = [];
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
                                                $montantTotal = (float) montant($row['type']);

                                                // Si assuré + carte valide => le montant total peut être prix_assurance (si disponible)
                                                $assureFlag = (int)($patientInfo['assure'] ?? 0);
                                                $dateExp = (string)($patientInfo['dateExpiration'] ?? '');
                                                $carteValide = ($assureFlag === 1) ? appec_isCardValid($dateExp) : false;

                                                if ($traitementsHasPrixAssurance && $assureFlag === 1 && $carteValide) {
                                                    $typeId = (int)($row['type'] ?? 0);
                                                    if ($typeId > 0) {
                                                        if (!array_key_exists($typeId, $prixAssuranceCache)) {
                                                            $stPA = $bdd->prepare('SELECT prix_assurance FROM traitements WHERE id_type = ? LIMIT 1');
                                                            $stPA->execute([$typeId]);
                                                            $prixAssuranceCache[$typeId] = (float)($stPA->fetchColumn() ?: 0);
                                                        }
                                                        if (($prixAssuranceCache[$typeId] ?? 0) > 0) {
                                                            $montantTotal = (float)$prixAssuranceCache[$typeId];
                                                        }
                                                    }
                                                }

                                                // Montant affiché = part patient (reste) si assuré + carte valide + taux de prise en charge
                                                $montant = $montantTotal;
                                                $tauxPrise = appec_toFloat($patientInfo['tauxPrisecharge'] ?? 0);
                                                if ($tauxPrise < 0) $tauxPrise = 0;
                                                if ($tauxPrise > 100) $tauxPrise = 100;
                                                if ($assureFlag === 1 && $carteValide && $tauxPrise > 0) {
                                                    $partAssurance = ($montantTotal * $tauxPrise / 100);
                                                    $montant = $montantTotal - $partAssurance;
                                                    if ($montant < 0) $montant = 0.0;
                                                }
                                                $modele  = model($row['type']);

                                                $typePatient = $row["type"];

                                                echo '<tr>';
                                                echo '<td>'.htmlspecialchars($row["date"], ENT_QUOTES, "UTF-8").'</td>';
                                                echo '<td>'.htmlspecialchars($row["id_patient"], ENT_QUOTES, "UTF-8").'</td>';
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

                        <div class="table-responsive mb-3">
                            <table class="table table-bordered table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <th style="width: 35%;">Patient</th>
                                        <td id="paiementPatientNom">—</td>
                                    </tr>
                                    <tr>
                                        <th>Contact</th>
                                        <td id="paiementPatientPhone">—</td>
                                    </tr>
                                    <tr>
                                        <th>Profession</th>
                                        <td id="paiementPatientProfession">—</td>
                                    </tr>
                                    <tr>
                                        <th>Examen</th>
                                        <td id="paiementExamen">—</td>
                                    </tr>
                                    <tr>
                                        <th>Part patient</th>
                                        <td id="paiementMontant">—</td>
                                    </tr>
                                    <tr class="paiementSplitRow d-none">
                                        <th>Part assurance</th>
                                        <td id="paiementPartAssurance">—</td>
                                    </tr>
                                    <tr>
                                        <th>Assuré</th>
                                        <td id="paiementPatientAssure">—</td>
                                    </tr>

                                    <tr class="paiementAssuranceRow d-none">
                                        <th>Assurance</th>
                                        <td id="paiementAssuranceNom">—</td>
                                    </tr>
                                    <tr class="paiementAssuranceRow d-none">
                                        <th>N° Carte</th>
                                        <td id="paiementAssuranceCarte">—</td>
                                    </tr>
                                    <tr class="paiementAssuranceRow d-none">
                                        <th>Taux prise en charge</th>
                                        <td id="paiementAssuranceTaux">—</td>
                                    </tr>
                                    <tr class="paiementAssuranceRow d-none">
                                        <th>Expiration</th>
                                        <td id="paiementAssuranceExpiration">—</td>
                                    </tr>
                                </tbody>
                            </table>
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
                                    <label class="form-label" for="paiementTaux">Remise</label>
                                    <select class="form-control" name="taux" id="paiementTaux" required>
                                        <option value="0">Non Appliqué</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a id="paiementRecuBtn" href="#" class="btn btn-light d-none">Voir / Imprimer</a>
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

        <!-- Modal Reçu (aperçu + impression) -->
        <div class="modal fade" id="recuModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Reçu de paiement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0" style="height:80vh;">
                        <iframe id="recuFrame" src="about:blank" style="width:100%; height:100%;" frameborder="0"></iframe>
                    </div>
                    <div class="modal-footer">
                        <button type="button" id="recuPrintBtn" class="btn btn-primary">Imprimer</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
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

            const recuModalEl = document.getElementById('recuModal');
            const recuFrameEl = document.getElementById('recuFrame');
            const recuPrintBtnEl = document.getElementById('recuPrintBtn');

            let pendingReload = false;

            function tryReloadSoon(delayMs) {
                const delay = typeof delayMs === 'number' ? delayMs : 500;
                setTimeout(function () {
                    // Attendre la fermeture des modals pour ne pas interrompre l'impression
                    if (document.querySelector('.modal.show')) {
                        pendingReload = true;
                        return;
                    }
                    if (pendingReload) {
                        pendingReload = false;
                    }
                    window.location.reload();
                }, delay);
            }

            const patientNomEl = document.getElementById('paiementPatientNom');
            const patientPhoneEl = document.getElementById('paiementPatientPhone');
            const patientProfessionEl = document.getElementById('paiementPatientProfession');
            const patientAssureEl = document.getElementById('paiementPatientAssure');
            const examenEl = document.getElementById('paiementExamen');
            const montantEl = document.getElementById('paiementMontant');

            const splitRowEls = Array.prototype.slice.call(document.querySelectorAll('.paiementSplitRow'));
            const partAssuranceEl = document.getElementById('paiementPartAssurance');

            const assuranceRowEls = Array.prototype.slice.call(document.querySelectorAll('.paiementAssuranceRow'));
            const assuranceNomEl = document.getElementById('paiementAssuranceNom');
            const assuranceCarteEl = document.getElementById('paiementAssuranceCarte');
            const assuranceTauxEl = document.getElementById('paiementAssuranceTaux');
            const assuranceExpEl = document.getElementById('paiementAssuranceExpiration');
            const idPatientEl = document.getElementById('paiementIdPatient');
            const idAffectEl = document.getElementById('paiementIdAffectation');
            const typeEl = document.getElementById('paiementType');
            const tauxEl = document.getElementById('paiementTaux');

            if (!modalEl || !formEl || !submitEl || !typeEl || !tauxEl) return;

            function withAutoPrintDisabled(url) {
                if (!url) return url;
                return url + (url.indexOf('?') >= 0 ? '&' : '?') + 'autoprint=0';
            }

            function openReceiptModal(receiptUrl) {
                if (!recuModalEl || !recuFrameEl || !receiptUrl || !window.bootstrap) return;
                recuFrameEl.src = withAutoPrintDisabled(receiptUrl);
                const instance = window.bootstrap.Modal.getInstance(recuModalEl) || new window.bootstrap.Modal(recuModalEl);
                instance.show();
            }

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

            function formatAmount(value) {
                const n = Number(value);
                if (!isFinite(n)) return '0';
                return Math.round(n).toLocaleString('fr-FR');
            }

            async function loadPaymentForm(idPatient, idAffectation) {
                hideAlert();
                recuBtnEl.classList.add('d-none');
                recuBtnEl.setAttribute('href', '#');
                submitEl.disabled = true;
                submitEl.textContent = 'Chargement…';

                if (patientNomEl) patientNomEl.textContent = '—';
                if (patientPhoneEl) patientPhoneEl.textContent = '—';
                if (patientProfessionEl) patientProfessionEl.textContent = '—';
                if (patientAssureEl) patientAssureEl.textContent = '—';
                examenEl.textContent = '—';
                montantEl.textContent = '—';

                splitRowEls.forEach(function (row) { row.classList.add('d-none'); });
                if (partAssuranceEl) partAssuranceEl.textContent = '—';

                assuranceRowEls.forEach(function (row) { row.classList.add('d-none'); });
                if (assuranceNomEl) assuranceNomEl.textContent = '—';
                if (assuranceCarteEl) assuranceCarteEl.textContent = '—';
                if (assuranceTauxEl) assuranceTauxEl.textContent = '—';
                if (assuranceExpEl) assuranceExpEl.textContent = '—';

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
                if (patientNomEl) patientNomEl.textContent = pat.nom_patient || '—';
                if (patientPhoneEl) patientPhoneEl.textContent = pat.phone || '—';
                if (patientProfessionEl) patientProfessionEl.textContent = pat.profession || '—';
                examenEl.textContent = aff.motif || '—';
                montantEl.textContent = (aff.montant_label || '0') + ' <?php echo htmlspecialchars($devise ?? "", ENT_QUOTES, "UTF-8"); ?>';

                const partAssurance = (aff && aff.montant_assurance !== undefined && aff.montant_assurance !== null)
                    ? Number(aff.montant_assurance)
                    : 0;

                const hasValidCard = !!(aff && aff.carte_valide);
                const isAssure = String(pat.assure || '0') === '1' && hasValidCard;

                if (patientAssureEl) {
                    if (String(pat.assure || '0') === '1' && !hasValidCard) {
                        patientAssureEl.textContent = 'Non (carte expirée)';
                    } else {
                        patientAssureEl.textContent = isAssure ? 'Oui' : 'Non';
                    }
                }

                if (isAssure) {
                    if (partAssuranceEl) {
                        partAssuranceEl.textContent = (partAssurance > 0 ? formatAmount(partAssurance) : '0') + ' <?php echo htmlspecialchars($devise ?? "", ENT_QUOTES, "UTF-8"); ?>';
                    }
                    splitRowEls.forEach(function (row) { row.classList.remove('d-none'); });

                    assuranceRowEls.forEach(function (row) { row.classList.remove('d-none'); });
                    if (assuranceNomEl) assuranceNomEl.textContent = pat.assurance_nom || '—';
                    if (assuranceCarteEl) assuranceCarteEl.textContent = pat.carte_adhesion || '—';
                    if (assuranceExpEl) assuranceExpEl.textContent = pat.date_expiration || '—';

                    var tauxVal = (pat.taux_prisecharge !== undefined && pat.taux_prisecharge !== null) ? String(pat.taux_prisecharge).trim() : '';
                    if (assuranceTauxEl) assuranceTauxEl.textContent = tauxVal !== '' ? (tauxVal + '%') : '—';
                } else {
                    splitRowEls.forEach(function (row) { row.classList.add('d-none'); });
                    if (partAssuranceEl) partAssuranceEl.textContent = '—';
                    assuranceRowEls.forEach(function (row) { row.classList.add('d-none'); });
                }

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
                    if (data.receipt_url) {
                        recuBtnEl.classList.remove('d-none');
                        recuBtnEl.setAttribute('href', data.receipt_url);
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
                        openReceiptModal(data.receipt_url);

                        // Recharger après fermeture du reçu (pour ne pas perdre l'impression)
                        pendingReload = true;
                    } else {
                        // Pas de reçu à afficher : reload normal
                        tryReloadSoon(800);
                    }
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
                if (patientNomEl) patientNomEl.textContent = '—';
                if (patientPhoneEl) patientPhoneEl.textContent = '—';
                if (patientProfessionEl) patientProfessionEl.textContent = '—';
                if (patientAssureEl) patientAssureEl.textContent = '—';
                examenEl.textContent = '—';
                montantEl.textContent = '—';

                splitRowEls.forEach(function (row) { row.classList.add('d-none'); });
                if (partAssuranceEl) partAssuranceEl.textContent = '—';

                assuranceRowEls.forEach(function (row) { row.classList.add('d-none'); });
                if (assuranceNomEl) assuranceNomEl.textContent = '—';
                if (assuranceCarteEl) assuranceCarteEl.textContent = '—';
                if (assuranceTauxEl) assuranceTauxEl.textContent = '—';
                if (assuranceExpEl) assuranceExpEl.textContent = '—';
                typeEl.innerHTML = '<option value="">Sélectionner…</option>';
                tauxEl.innerHTML = '<option value="0">Non Appliqué</option>';

                // Si un reload était en attente (après impression), le faire après fermeture du modal paiement
                if (pendingReload) {
                    tryReloadSoon(100);
                }
            });

            // Ouvrir le reçu dans un modal (sans nouvelle fenêtre)
            recuBtnEl.addEventListener('click', function (e) {
                if (!recuBtnEl || recuBtnEl.classList.contains('d-none')) return;
                e.preventDefault();
                const href = recuBtnEl.getAttribute('href');
                if (href && href !== '#') {
                    openReceiptModal(href);
                }
            });

            // Impression depuis le modal reçu
            if (recuPrintBtnEl) {
                recuPrintBtnEl.addEventListener('click', function () {
                    try {
                        const win = recuFrameEl && recuFrameEl.contentWindow ? recuFrameEl.contentWindow : null;
                        if (win && typeof win.printPdf === 'function') {
                            win.printPdf();
                            return;
                        }
                        if (win && typeof win.print === 'function') {
                            win.print();
                        }
                    } catch (e) {
                        // fallback silencieux
                    }
                });
            }

            // Nettoyer l'iframe à la fermeture
            if (recuModalEl) {
                recuModalEl.addEventListener('hidden.bs.modal', function () {
                    if (recuFrameEl) recuFrameEl.src = 'about:blank';

                    // Fermer aussi le modal de paiement pour revenir à la liste
                    if (window.bootstrap) {
                        const payInstance = window.bootstrap.Modal.getInstance(modalEl);
                        if (payInstance) {
                            payInstance.hide();
                        }
                    }

                    // Si un reload est en attente, le faire maintenant
                    if (pendingReload) {
                        tryReloadSoon(100);
                    }
                });
            }
        });
        </script>
</body>