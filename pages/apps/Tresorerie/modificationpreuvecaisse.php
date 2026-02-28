<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

date_default_timezone_set('Africa/Abidjan');

function mp_int($v): int {
    if ($v === null) return 0;
    if (is_string($v)) {
        $v = str_replace([' ', ','], ['', '.'], trim($v));
    }
    if ($v === '' || $v === false) return 0;
    return (int)round((float)$v);
}

function mp_expected_total(array $p): int {
    $b0 = mp_int($p['b0'] ?? 0);
    $b1 = mp_int($p['b1'] ?? 0);
    $b2 = mp_int($p['b2'] ?? 0);
    $b5 = mp_int($p['b5'] ?? 0);
    $b10 = mp_int($p['b10'] ?? 0);
    $b20 = mp_int($p['b20'] ?? 0);
    return ($b0 * 500) + ($b1 * 1000) + ($b2 * 2000) + ($b5 * 5000) + ($b10 * 10000) + ($b20 * 20000);
}

function mp_entree_paiements(PDO $bdd, int $compteId, string $dateRapportement, ?int $userId = null): int {
    // Total réel encaissé le jour J sur ce compte pour un caissier donné.
    // Couvrir toute la journée (datepaiement peut être DATE ou DATETIME selon les environnements).
    $dateKey = substr((string)$dateRapportement, 0, 10);
    $debut = $dateKey . ' 00:00:00';
    $fin = $dateKey . ' 23:59:59';
    return mp_int(getEntreePaiements($compteId, $debut, $fin, $bdd, $userId !== null ? (int)$userId : null));
}

function mp_fetch_preuve(PDO $bdd, int $id_preuve): ?array {
    $st = $bdd->prepare('
        SELECT p.id_preuve, p.date_rapportement, p.compte, p.montant, p.b0, p.b1, p.b2, p.b5, p.b10, p.b20, p.montant_lettre, p.id_user,
               c.nom_compte, c.types,
               u.pseudo AS caissier_pseudo
        FROM preuvedecaisse p
        LEFT JOIN comptes c ON c.id_compte = p.compte
        LEFT JOIN users u ON u.id = p.id_user
        WHERE p.id_preuve = ?
        LIMIT 1
    ');
    $st->execute([$id_preuve]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

// ===================== AJAX: charger une preuve =====================
if (isset($_GET['ajax_preuve'])) {
    header('Content-Type: application/json; charset=UTF-8');

    $id = isset($_GET['id_preuve']) ? (int)$_GET['id_preuve'] : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID preuve invalide.']);
        exit;
    }

    try {
        $p = mp_fetch_preuve($bdd, $id);
        if (!$p) {
            echo json_encode(['success' => false, 'message' => 'Preuve introuvable.']);
            exit;
        }

        $montant = mp_int($p['montant'] ?? 0);
        $expected = mp_expected_total($p);
        $entreeJour = mp_entree_paiements(
            $bdd,
            (int)($p['compte'] ?? 0),
            (string)($p['date_rapportement'] ?? ''),
            isset($p['id_user']) ? (int)$p['id_user'] : null
        );
        $conformeBillets = ($montant === $expected);
        $conformeEntree = ($montant === $entreeJour);
        $conforme = ($conformeBillets && $conformeEntree);

        echo json_encode([
            'success' => true,
            'proof' => [
                'id_preuve' => (int)$p['id_preuve'],
                'date_rapportement' => (string)($p['date_rapportement'] ?? ''),
                'compte_id' => (int)($p['compte'] ?? 0),
                'compte_label' => (string)($p['nom_compte'] ?? ($p['types'] ?? '')),
                'id_user' => (int)($p['id_user'] ?? 0),
                'caissier_pseudo' => (string)($p['caissier_pseudo'] ?? ''),
                'montant' => $montant,
                'b0' => mp_int($p['b0'] ?? 0),
                'b1' => mp_int($p['b1'] ?? 0),
                'b2' => mp_int($p['b2'] ?? 0),
                'b5' => mp_int($p['b5'] ?? 0),
                'b10' => mp_int($p['b10'] ?? 0),
                'b20' => mp_int($p['b20'] ?? 0),
                'montant_lettre' => (string)($p['montant_lettre'] ?? ''),
                'expected_total' => $expected,
                'entree_total' => $entreeJour,
                'conforme_billets' => $conformeBillets,
                'conforme_entree' => $conformeEntree,
                'conforme' => $conforme,
            ],
        ]);
        exit;
    } catch (Throwable $e) {
        error_log('[modificationpreuvecaisse ajax_preuve] ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement.']);
        exit;
    }
}

// ===================== AJAX: modifier une preuve (si non conforme) =====================
if (isset($_POST['ajax_update'])) {
    header('Content-Type: application/json; charset=UTF-8');

    $id = isset($_POST['id_preuve']) ? (int)$_POST['id_preuve'] : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID preuve invalide.']);
        exit;
    }

    $montant = mp_int($_POST['montant'] ?? 0);
    $b0 = mp_int($_POST['b0'] ?? 0);
    $b1 = mp_int($_POST['b1'] ?? 0);
    $b2 = mp_int($_POST['b2'] ?? 0);
    $b5 = mp_int($_POST['b5'] ?? 0);
    $b10 = mp_int($_POST['b10'] ?? 0);
    $b20 = mp_int($_POST['b20'] ?? 0);
    $montant_lettre = trim((string)($_POST['montant_lettre'] ?? ''));

    try {
        $existing = mp_fetch_preuve($bdd, $id);
        if (!$existing) {
            echo json_encode(['success' => false, 'message' => 'Preuve introuvable.']);
            exit;
        }

        $existingMontant = mp_int($existing['montant'] ?? 0);
        $existingExpected = mp_expected_total($existing);
        $existingEntree = mp_entree_paiements(
            $bdd,
            (int)($existing['compte'] ?? 0),
            (string)($existing['date_rapportement'] ?? ''),
            isset($existing['id_user']) ? (int)$existing['id_user'] : null
        );
        $existingConforme = ($existingMontant === $existingExpected) && ($existingMontant === $existingEntree);

        if ($existingConforme) {
            echo json_encode(['success' => false, 'message' => "Preuve conforme : aucune modification n'est proposée."]); 
            exit;
        }

        $bdd->beginTransaction();
        try {
            $st = $bdd->prepare('UPDATE preuvedecaisse SET montant = ?, b0 = ?, b1 = ?, b2 = ?, b5 = ?, b10 = ?, b20 = ?, montant_lettre = ? WHERE id_preuve = ?');
            $st->execute([$montant, $b0, $b1, $b2, $b5, $b10, $b20, $montant_lettre, $id]);
            $bdd->commit();
        } catch (Throwable $txe) {
            $bdd->rollBack();
            throw $txe;
        }

        // Recharger et renvoyer l'état
        $p = mp_fetch_preuve($bdd, $id);
        $montant2 = mp_int($p['montant'] ?? 0);
        $expected2 = mp_expected_total($p);
        $entreeJour2 = mp_entree_paiements(
            $bdd,
            (int)($p['compte'] ?? 0),
            (string)($p['date_rapportement'] ?? ''),
            isset($p['id_user']) ? (int)$p['id_user'] : null
        );
        $conformeBillets2 = ($montant2 === $expected2);
        $conformeEntree2 = ($montant2 === $entreeJour2);
        $conforme2 = ($conformeBillets2 && $conformeEntree2);

        echo json_encode([
            'success' => true,
            'message' => $conforme2 ? 'Modification enregistrée. La preuve est maintenant conforme.' : 'Modification enregistrée. Attention: la preuve reste non conforme.',
            'proof' => [
                'id_preuve' => (int)$p['id_preuve'],
                'date_rapportement' => (string)($p['date_rapportement'] ?? ''),
                'compte_id' => (int)($p['compte'] ?? 0),
                'compte_label' => (string)($p['nom_compte'] ?? ($p['types'] ?? '')),
                'id_user' => (int)($p['id_user'] ?? 0),
                'caissier_pseudo' => (string)($p['caissier_pseudo'] ?? ''),
                'montant' => $montant2,
                'b0' => mp_int($p['b0'] ?? 0),
                'b1' => mp_int($p['b1'] ?? 0),
                'b2' => mp_int($p['b2'] ?? 0),
                'b5' => mp_int($p['b5'] ?? 0),
                'b10' => mp_int($p['b10'] ?? 0),
                'b20' => mp_int($p['b20'] ?? 0),
                'montant_lettre' => (string)($p['montant_lettre'] ?? ''),
                'expected_total' => $expected2,
                'entree_total' => $entreeJour2,
                'conforme_billets' => $conformeBillets2,
                'conforme_entree' => $conformeEntree2,
                'conforme' => $conforme2,
            ],
        ]);
        exit;
    } catch (Throwable $e) {
        error_log('[modificationpreuvecaisse ajax_update] ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la modification.']);
        exit;
    }
}

include('../PUBLIC/header.php');
?>

<body>
<section class="body">
    <?php require('../PUBLIC/navbarmenu.php'); ?>

    <div class="inner-wrapper">
        <section role="main" class="content-body">
            <header class="page-header">
                <h2>Modification preuve de caisse</h2>
            </header>

            <div class="col-md-12">
                <section class="card">
                    <div class="card-body">
                        <form id="mpSearchForm" class="form-horizontal" novalidate="novalidate">
                            <div class="row form-group pb-3">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="col-form-label" for="mpIdPreuve">Saisir l'ID de la preuve PC_N°</label>
                                        <input type="number" class="form-control" id="mpIdPreuve" required min="1" inputmode="numeric" autocomplete="off">
                                    </div>
                                </div>
                            </div>
                            <div class="text-end">
                                <button class="btn btn-primary" type="submit">Continuer</button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </section>
    </div>

    <?php include('../PUBLIC/footer.php'); ?>
</section>

<!-- Modal Preuve -->
<div class="modal fade" id="mpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Informations de la preuve</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="mpAlert" class="alert d-none" role="alert"></div>
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <tbody>
                            <tr><th style="width: 35%">ID preuve</th><td id="mp_id">—</td></tr>
                            <tr><th>Date</th><td id="mp_date">—</td></tr>
                            <tr><th>Compte</th><td id="mp_compte">—</td></tr>
                            <tr><th>Caissier (ID)</th><td id="mp_user">—</td></tr>
                            <tr><th>Montant déclaré</th><td id="mp_montant">—</td></tr>
                            <tr><th>Total billets calculé</th><td id="mp_expected">—</td></tr>
                            <tr><th>Total entrées du jour</th><td id="mp_entree">—</td></tr>
                            <tr><th>Statut</th><td id="mp_status">—</td></tr>
                        </tbody>
                    </table>
                </div>

                <div id="mpEditSection" class="mt-3 d-none">
                    <h5 class="mb-2">Corriger la preuve (si non conforme)</h5>
                    <form id="mpEditForm" autocomplete="off">
                        <input type="hidden" name="ajax_update" value="1">
                        <input type="hidden" name="id_preuve" id="mp_edit_id" value="">

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label" for="mp_edit_montant">Montant déclaré</label>
                                <input type="number" class="form-control" id="mp_edit_montant" name="montant" min="0" required>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label" for="mp_edit_lettre">Montant en lettres</label>
                                <input type="text" class="form-control" id="mp_edit_lettre" name="montant_lettre" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-2 mb-3">
                                <label class="form-label" for="mp_b0">Billet 500</label>
                                <input type="number" class="form-control mp-bill" id="mp_b0" name="b0" min="0" required>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label" for="mp_b1">Billet 1 000</label>
                                <input type="number" class="form-control mp-bill" id="mp_b1" name="b1" min="0" required>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label" for="mp_b2">Billet 2 000</label>
                                <input type="number" class="form-control mp-bill" id="mp_b2" name="b2" min="0" required>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label" for="mp_b5">Billet 5 000</label>
                                <input type="number" class="form-control mp-bill" id="mp_b5" name="b5" min="0" required>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label" for="mp_b10">Billet 10 000</label>
                                <input type="number" class="form-control mp-bill" id="mp_b10" name="b10" min="0" required>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label" for="mp_b20">Billet 20 000</label>
                                <input type="number" class="form-control mp-bill" id="mp_b20" name="b20" min="0" required>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                            <button type="submit" class="btn btn-primary" id="mpSaveBtn">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchForm = document.getElementById('mpSearchForm');
    const idInput = document.getElementById('mpIdPreuve');

    const modalEl = document.getElementById('mpModal');
    const alertEl = document.getElementById('mpAlert');

    const editSection = document.getElementById('mpEditSection');
    const editForm = document.getElementById('mpEditForm');
    const saveBtn = document.getElementById('mpSaveBtn');

    function resetModalView() {
        setAlert(null, '');
        if (editSection) editSection.classList.add('d-none');
        if (saveBtn) saveBtn.disabled = true;
        setText('mp_id', '—');
        setText('mp_date', '—');
        setText('mp_compte', '—');
        setText('mp_user', '—');
        setText('mp_montant', '—');
        setText('mp_expected', '—');
        setText('mp_entree', '—');
        setText('mp_status', '—');
    }

    function setAlert(kind, msg) {
        if (!alertEl) return;
        if (!msg) {
            alertEl.className = 'alert d-none';
            alertEl.textContent = '';
            return;
        }
        alertEl.className = 'alert alert-' + (kind || 'info');
        alertEl.textContent = msg;
        alertEl.classList.remove('d-none');
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = (value === null || value === undefined || value === '') ? '—' : String(value);
    }

    function fmt(n) {
        const x = Number(n || 0);
        return x.toLocaleString('fr-FR');
    }

    function computeExpectedFromForm() {
        const v = (id) => {
            const el = document.getElementById(id);
            const n = Number(el && el.value ? el.value : 0);
            return isFinite(n) ? n : 0;
        };
        return (v('mp_b0') * 500) + (v('mp_b1') * 1000) + (v('mp_b2') * 2000) + (v('mp_b5') * 5000) + (v('mp_b10') * 10000) + (v('mp_b20') * 20000);
    }

    function fillEditForm(p) {
        document.getElementById('mp_edit_id').value = String(p.id_preuve || '');
        document.getElementById('mp_edit_montant').value = String(p.montant || 0);
        document.getElementById('mp_edit_lettre').value = String(p.montant_lettre || '');
        document.getElementById('mp_b0').value = String(p.b0 || 0);
        document.getElementById('mp_b1').value = String(p.b1 || 0);
        document.getElementById('mp_b2').value = String(p.b2 || 0);
        document.getElementById('mp_b5').value = String(p.b5 || 0);
        document.getElementById('mp_b10').value = String(p.b10 || 0);
        document.getElementById('mp_b20').value = String(p.b20 || 0);
    }

    async function loadProof(idPreuve) {
        resetModalView();
        setAlert('info', 'Chargement…');

        const url = 'modificationpreuvecaisse.php?ajax_preuve=1&id_preuve=' + encodeURIComponent(idPreuve);
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const data = await res.json();

        if (!data || !data.success || !data.proof) {
            setAlert('warning', (data && data.message) ? data.message : 'Preuve introuvable.');
            setText('mp_status', '—');
            return;
        }

        const p = data.proof;
        setText('mp_id', p.id_preuve);
        setText('mp_date', p.date_rapportement);
        setText('mp_compte', p.compte_label || p.compte_id);
        var cashierLabel = '';
        if (p.caissier_pseudo && String(p.caissier_pseudo).trim() !== '') {
            cashierLabel = String(p.caissier_pseudo).trim() + ' (' + String(p.id_user || '') + ')';
        } else {
            cashierLabel = String(p.id_user || '');
        }
        setText('mp_user', cashierLabel);
        setText('mp_montant', fmt(p.montant));
        setText('mp_expected', fmt(p.expected_total));
        setText('mp_entree', fmt(p.entree_total));

        if (p.conforme) {
            setText('mp_status', 'Conforme');
            setAlert('success', 'Preuve conforme.');
            if (editSection) editSection.classList.add('d-none');
        } else {
            setText('mp_status', 'Non conforme');
            var reasons = [];
            if (p.conforme_billets === false) reasons.push('montant ≠ total billets');
            if (p.conforme_entree === false) reasons.push('montant ≠ entrées du jour');
            setAlert('warning', 'Preuve non conforme : ' + (reasons.length ? reasons.join(' ; ') : 'écart détecté') + '.');
            if (editSection) editSection.classList.remove('d-none');
            fillEditForm(p);
            if (saveBtn) saveBtn.disabled = false;
        }
    }

    function openModal() {
        if (modalEl && window.bootstrap) {
            const instance = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
            instance.show();
        }
    }

    if (searchForm && idInput) {
        searchForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const id = String(idInput.value || '').trim();
            if (!id) return;
            resetModalView();
            openModal();
            loadProof(id).catch(function () {
                setAlert('danger', 'Erreur lors du chargement.');
            });
        });
    }

    // Mise à jour visuelle du total calculé quand on modifie les billets
    document.addEventListener('input', function (e) {
        if (!e.target || !e.target.classList || !e.target.classList.contains('mp-bill')) return;
        const expected = computeExpectedFromForm();
        setText('mp_expected', fmt(expected));
    });

    if (editForm) {
        editForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            setAlert(null, '');
            if (saveBtn) saveBtn.disabled = true;

            try {
                const fd = new FormData(editForm);
                const res = await fetch('modificationpreuvecaisse.php', {
                    method: 'POST',
                    body: fd,
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();

                if (!data || !data.success) {
                    setAlert('danger', (data && data.message) ? data.message : 'Modification échouée.');
                    if (saveBtn) saveBtn.disabled = false;
                    return;
                }

                setAlert('success', data.message || 'Modification enregistrée.');
                if (data.proof) {
                    // Rafraîchir l'affichage avec l'état renvoyé
                    const p = data.proof;
                    setText('mp_id', p.id_preuve);
                    setText('mp_date', p.date_rapportement);
                    setText('mp_compte', p.compte_label || p.compte_id);
                    var cashierLabel2 = '';
                    if (p.caissier_pseudo && String(p.caissier_pseudo).trim() !== '') {
                        cashierLabel2 = String(p.caissier_pseudo).trim() + ' (' + String(p.id_user || '') + ')';
                    } else {
                        cashierLabel2 = String(p.id_user || '');
                    }
                    setText('mp_user', cashierLabel2);
                    setText('mp_montant', fmt(p.montant));
                    setText('mp_expected', fmt(p.expected_total));
                    setText('mp_entree', fmt(p.entree_total));
                    setText('mp_status', p.conforme ? 'Conforme' : 'Non conforme');

                    if (p.conforme) {
                        if (editSection) editSection.classList.add('d-none');
                    } else {
                        if (editSection) editSection.classList.remove('d-none');
                        if (saveBtn) saveBtn.disabled = false;
                    }
                }
            } catch (err) {
                setAlert('danger', 'Erreur lors de la modification.');
                if (saveBtn) saveBtn.disabled = false;
            }
        });
    }

    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', function () {
            resetModalView();
        });
    }
});
</script>
