<?php
include('../public/connect.php');
require('../public/fonction.php');
session_start();

header('Content-Type: application/json; charset=UTF-8');

function ma_json(array $payload): void {
    echo json_encode($payload);
    exit;
}

function ma_render_form(PDO $bdd, array $affectation, array $state = []): string {
    $idAffectation = (int)($affectation['id_affectation'] ?? 0);
    $idPatient = (string)($affectation['id_patient'] ?? '');
    $selectedService = (int)($state['service'] ?? ($affectation['id_service'] ?? 0));
    $selectedType = (int)($state['type'] ?? ($affectation['type'] ?? 0));
    $success = (int)($state['success'] ?? 0) === 1;
    $message = (string)($state['message'] ?? '');

    ob_start();
    ?>
    <div class="col-md-12">
        <section class="card mb-0">
            <div class="card-body">
                <?php if ($message !== ''): ?>
                    <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?>">
                        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <div class="table-responsive mb-3">
                    <table class="table table-bordered mb-0">
                        <tbody>
                            <tr><th style="width:35%">Affectation</th><td><?php echo htmlspecialchars('AFF-' . $idAffectation, ENT_QUOTES, 'UTF-8'); ?></td></tr>
                            <tr><th>Patient</th><td><?php echo htmlspecialchars('PAT-' . $idPatient . ' ' . nom_patient($idPatient), ENT_QUOTES, 'UTF-8'); ?></td></tr>
                        </tbody>
                    </table>
                </div>

                <form id="maEditAffectationForm" method="POST" action="modifier-affectation.php">
                    <input type="hidden" name="ajax_edit_affectation" value="1">
                    <input type="hidden" name="id_affectation" value="<?php echo (int)$idAffectation; ?>">

                    <div class="row form-group pb-3">
                        <div class="col-md-4">
                            <label class="col-form-label">Departement concerné</label>
                            <select name="service" class="form-control populate" id="maServiceSelect" onchange="maUpdateMotifs(this)" data-plugin-selectTwo data-plugin-options='{ "minimumInputLength": 0 }' required>
                                <option value=""> ------ Choisir ----- </option>
                                <?php
                                $stServices = $bdd->prepare('SELECT id_organigramme, celulle FROM organigramme WHERE id_organigramme IN (?, ?, ?, ?) ORDER BY celulle ASC');
                                $stServices->execute([1, 2, 3, 4]);
                                while ($srv = $stServices->fetch(PDO::FETCH_ASSOC)) {
                                    $sid = (int)($srv['id_organigramme'] ?? 0);
                                    $sel = ($selectedService > 0 && $selectedService === $sid) ? ' selected' : '';
                                    echo '<option value="' . htmlspecialchars((string)$sid, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>' . htmlspecialchars((string)($srv['celulle'] ?? ''), ENT_QUOTES, 'UTF-8') . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-5">
                            <label class="col-form-label">Motif de présence</label>
                            <select name="type" class="form-control populate" id="maMotifSelect" data-plugin-selectTwo data-plugin-options='{ "minimumInputLength": 0 }' required>
                                <?php if ($selectedService > 0): ?>
                                    <option value=""> ------ Choisir ----- </option>
                                    <?php
                                    $stMotifs = $bdd->prepare('SELECT id_type, nom_type FROM traitements WHERE id_organigramme = ? AND status = 1 ORDER BY nom_type ASC');
                                    $stMotifs->execute([$selectedService]);
                                    while ($motif = $stMotifs->fetch(PDO::FETCH_ASSOC)) {
                                        $mid = (int)($motif['id_type'] ?? 0);
                                        $sel = ($selectedType > 0 && $selectedType === $mid) ? ' selected' : '';
                                        echo '<option value="' . htmlspecialchars((string)$mid, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>' . htmlspecialchars((string)($motif['nom_type'] ?? ''), ENT_QUOTES, 'UTF-8') . '</option>';
                                    }
                                    ?>
                                <?php else: ?>
                                    <option value=""> ------ Choisir un service d\'abord ----- </option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-primary">Enregistrer la modification</button>
                        <button type="button" class="btn btn-danger ms-2" data-bs-dismiss="modal">Fermer</button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <script>
    function maUpdateMotifs(serviceSelectEl) {
        try {
            var form = serviceSelectEl && serviceSelectEl.closest ? serviceSelectEl.closest('form') : null;
            var motifSelect = form ? form.querySelector('#maMotifSelect') : null;
            if (!motifSelect) return;

            var serviceId = String((serviceSelectEl && serviceSelectEl.value) || '').trim();
            if (!serviceId) {
                motifSelect.innerHTML = '<option value=""> ------ Choisir ----- </option>';
                return;
            }

            motifSelect.innerHTML = '<option value="">Chargement...</option>';
            fetch('../PUBLIC/getMotifs.php?service=' + encodeURIComponent(serviceId))
                .then(function (r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(function (data) {
                    motifSelect.innerHTML = '<option value=""> ------ Choisir ----- </option>';
                    if (!data || !data.success || !Array.isArray(data.motifs)) return;
                    data.motifs.forEach(function (m) {
                        var opt = document.createElement('option');
                        opt.value = m.id;
                        opt.textContent = m.nom;
                        motifSelect.appendChild(opt);
                    });
                })
                .catch(function () {
                    motifSelect.innerHTML = '<option value="">Erreur lors du chargement</option>';
                });
        } catch (e) {
            // noop
        }
    }
    </script>
    <?php
    return (string)ob_get_clean();
}

try {
    if (isset($_GET['ajax_modal'])) {
        $idAffectation = (int)($_GET['id_affectation'] ?? 0);
        if ($idAffectation <= 0) {
            ma_json(['success' => false, 'message' => 'Affectation invalide.']);
        }

        $st = $bdd->prepare('SELECT id_affectation, id_patient, id_service, type, status FROM affectations WHERE id_affectation = ? LIMIT 1');
        $st->execute([$idAffectation]);
        $affectation = $st->fetch(PDO::FETCH_ASSOC);
        if (!$affectation) {
            ma_json(['success' => false, 'message' => 'Affectation introuvable.']);
        }
        if ((int)$affectation['status'] !== 6) {
            ma_json(['success' => false, 'message' => 'Seules les affectations en caisse sont modifiables.']);
        }

        ma_json([
            'success' => true,
            'html' => ma_render_form($bdd, $affectation),
        ]);
    }

    if (isset($_POST['ajax_edit_affectation'])) {
        $idAffectation = (int)($_POST['id_affectation'] ?? 0);
        $serviceId = (int)($_POST['service'] ?? 0);
        $typeId = (int)($_POST['type'] ?? 0);

        if ($idAffectation <= 0 || $serviceId <= 0 || $typeId <= 0) {
            ma_json(['success' => false, 'message' => 'Informations incomplètes.']);
        }

        $st = $bdd->prepare('SELECT id_affectation, id_patient, id_service, type, status FROM affectations WHERE id_affectation = ? LIMIT 1');
        $st->execute([$idAffectation]);
        $affectation = $st->fetch(PDO::FETCH_ASSOC);
        if (!$affectation) {
            ma_json(['success' => false, 'message' => 'Affectation introuvable.']);
        }
        if ((int)$affectation['status'] !== 6) {
            ma_json(['success' => false, 'message' => 'Cette affectation ne peut plus être modifiée.']);
        }

        $stMotif = $bdd->prepare('SELECT 1 FROM traitements WHERE id_type = ? AND id_organigramme = ? AND status = 1 LIMIT 1');
        $stMotif->execute([$typeId, $serviceId]);
        if (!(bool)$stMotif->fetchColumn()) {
            ma_json(['success' => false, 'message' => 'Le motif sélectionné ne correspond pas au service.']);
        }

        $upd = $bdd->prepare('UPDATE affectations SET id_service = ?, type = ? WHERE id_affectation = ? LIMIT 1');
        $upd->execute([$serviceId, $typeId, $idAffectation]);

        $st->execute([$idAffectation]);
        $fresh = $st->fetch(PDO::FETCH_ASSOC) ?: $affectation;

        ma_json([
            'success' => true,
            'message' => 'Affectation modifiée avec succès.',
            'reload' => true,
            'html' => ma_render_form($bdd, $fresh, [
                'success' => 1,
                'message' => 'Affectation modifiée avec succès.',
                'service' => $serviceId,
                'type' => $typeId,
            ]),
        ]);
    }

    ma_json(['success' => false, 'message' => 'Requête invalide.']);
} catch (Throwable $e) {
    error_log('[modifier-affectation] ' . $e->getMessage());
    ma_json(['success' => false, 'message' => 'Erreur serveur lors de la modification.']);
}
