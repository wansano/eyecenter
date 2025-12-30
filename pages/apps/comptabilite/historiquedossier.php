<?php
session_start();
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');

// Endpoint AJAX: historique des passages (date, motif, montant payé)
if (isset($_GET['ajax_history'])) {
    header('Content-Type: application/json; charset=UTF-8');

    $idPatient = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($idPatient <= 0) {
        echo json_encode(['success' => false, 'message' => 'Numéro de dossier invalide.']);
        exit;
    }

    try {
        $stExists = $bdd->prepare('SELECT id_patient FROM patients WHERE id_patient = ? LIMIT 1');
        $stExists->execute([$idPatient]);
        if (!$stExists->fetchColumn()) {
            echo json_encode(['success' => false, 'message' => 'Patient introuvable.']);
            exit;
        }

        // Historique : une ligne par affectation (passage), total payé = somme paiements
        $sql = "
            SELECT
                a.id_affectation,
                COALESCE(MAX(p.datepaiement), a.date) AS date_passage,
                COALESCE(MAX(p.types), a.type) AS motif_id,
                COALESCE(SUM(COALESCE(p.montant_paye, p.montant)), 0) AS montant_paye
            FROM affectations a
            LEFT JOIN paiements p
                ON p.id_affectation = a.id_affectation
                AND (p.remboursement = 0 OR p.remboursement IS NULL)
            WHERE a.id_patient = ?
            GROUP BY a.id_affectation, a.date, a.type
            ORDER BY a.id_affectation DESC
            LIMIT 300
        ";

        $stmt = $bdd->prepare($sql);
        $stmt->execute([$idPatient]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        $totalPaye = 0.0;
        foreach ($rows as $r) {
            $motifId = (int)($r['motif_id'] ?? 0);
            $montant = (float)($r['montant_paye'] ?? 0);
            $totalPaye += $montant;
            $out[] = [
                'id_affectation' => (int)($r['id_affectation'] ?? 0),
                'date' => (string)($r['date_passage'] ?? ''),
                'motif_id' => $motifId,
                'motif' => (string)model($motifId),
                'montant_paye' => $montant,
                'montant_paye_label' => number_format($montant, 0, ',', ' '),
            ];
        }

        echo json_encode([
            'success' => true,
            'patient' => [
                'id_patient' => $idPatient,
                'nom_patient' => (string)nom_patient($idPatient),
            ],
            'total_paye' => $totalPaye,
            'total_paye_label' => number_format($totalPaye, 0, ',', ' '),
            'passages' => $out,
        ]);
        exit;
    } catch (Throwable $e) {
        error_log('[HISTO PASSAGES] ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération de l\'historique.']);
        exit;
    }
}

$prefillId = '';
if (isset($_POST['recherche'])) {
    $prefillId = trim((string)$_POST['recherche']);
}

include('../PUBLIC/header.php');
?>

<body>
    <section class="body">
        <?php require('../PUBLIC/navbarmenu.php'); ?>

        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Historique des passages du patient</h2>
                </header>

                <div class="col-md-12">
                    <section class="card">
                        <div class="card-body">
                            <form class="form-horizontal" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="historySearchForm">
                                <div class="row form-group pb-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label">Saisir le numéro de dossier</label>
                                            <input type="text" class="form-control" name="recherche" id="historySearchInput" required value="<?php echo htmlspecialchars($prefillId); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer text-end">
                                    <button class="btn btn-primary" type="submit" id="historySearchBtn">Rechercher</button>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
            </section>
        </div>

        <?php include('../PUBLIC/footer.php'); ?>
    </section>

    <!-- Modal: Historique des passages -->
    <div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="historyModalTitle">Historique</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="historyAlert" class="alert d-none" role="alert"></div>

                    <div class="d-flex justify-content-end mb-2">
                        <strong>Total payé :</strong>&nbsp;
                        <span id="historyTotal">0</span>&nbsp;<?= htmlspecialchars($devise); ?>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>PASSAGE</th>
                                    <th>DATE</th>
                                    <th>MOTIF</th>
                                    <th>MONTANT PAYÉ en <?= $devise; ?></th>
                                </tr>
                            </thead>
                            <tbody id="historyTbody">
                                <tr><td colspan="4">Saisissez un numéro de dossier.</td></tr>
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
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('historySearchForm');
        const input = document.getElementById('historySearchInput');
        const btn = document.getElementById('historySearchBtn');
        const modalEl = document.getElementById('historyModal');
        const titleEl = document.getElementById('historyModalTitle');
        const alertEl = document.getElementById('historyAlert');
        const tbodyEl = document.getElementById('historyTbody');
        const totalEl = document.getElementById('historyTotal');

        function setAlert(type, msg) {
            if (!alertEl) return;
            if (!msg) {
                alertEl.className = 'alert d-none';
                alertEl.textContent = '';
                return;
            }
            alertEl.className = 'alert alert-' + type;
            alertEl.textContent = msg;
            alertEl.classList.remove('d-none');
        }

        function setLoading() {
            if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="4">Chargement...</td></tr>';
        }

        function setEmpty() {
            if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="4">Aucun passage trouvé.</td></tr>';
            if (totalEl) totalEl.textContent = '0';
        }

        function showModal() {
            if (modalEl && window.bootstrap) {
                const instance = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
                instance.show();
            }
        }

        function renderRows(passages) {
            if (!tbodyEl) return;
            if (!Array.isArray(passages) || !passages.length) {
                setEmpty();
                return;
            }
            tbodyEl.innerHTML = '';
            for (const p of passages) {
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td>EC_AFF' + String(p.id_affectation ?? '') + '</td>' +
                    '<td>' + (p.date ?? '') + '</td>' +
                    '<td>' + (p.motif ?? '') + '</td>' +
                    '<td class="text-end">' + (p.montant_paye_label ?? '0') + '</td>';
                tbodyEl.appendChild(tr);
            }
        }

        async function fetchHistory(id) {
            const url = '<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>' + '?ajax_history=1&id=' + encodeURIComponent(id);
            const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
            return await resp.json();
        }

        async function onSearch(e) {
            if (e) e.preventDefault();
            if (!input) return;
            const id = input.value.trim();
            if (!id) return;

            setAlert(null, '');
            setLoading();
            if (totalEl) totalEl.textContent = '0';
            if (btn) btn.disabled = true;

            try {
                const data = await fetchHistory(id);
                if (!data || !data.success) {
                    setAlert('warning', (data && data.message) ? data.message : 'Erreur lors de la recherche.');
                    setEmpty();
                } else {
                    const patient = data.patient || {};
                    if (titleEl) {
                        titleEl.textContent = 'Historique - PAT-' + String(patient.id_patient || id) + (patient.nom_patient ? (' - ' + patient.nom_patient) : '');
                    }
                    if (totalEl) {
                        totalEl.textContent = (data.total_paye_label != null) ? String(data.total_paye_label) : '0';
                    }
                    renderRows(data.passages || []);
                }
                showModal();
            } catch (err) {
                console.error('Erreur historique:', err);
                setAlert('danger', 'Erreur lors de la récupération.');
                setEmpty();
                showModal();
            } finally {
                if (btn) btn.disabled = false;
            }
        }

        if (form) form.addEventListener('submit', onSearch);

        // Si la page est rechargée après un POST (fallback), ouvrir le modal automatiquement
        if (input && input.value.trim()) {
            // petit délai pour laisser les assets charger
            setTimeout(() => onSearch(null), 150);
        }
    });
    </script>
</body>
</html>
