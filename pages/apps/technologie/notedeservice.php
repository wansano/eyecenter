<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function tableExists(PDO $bdd, string $table): bool
{
    try {
        $st = $bdd->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);
        return (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        error_log('[notedeservice] tableExists ' . $table . ': ' . $e->getMessage());
        return false;
    }
}

function getEmployesColumnMap(PDO $bdd): array
{
    $fields = [];
    try {
        $stmt = $bdd->query('SHOW COLUMNS FROM employes');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $r) {
            $f = (string) ($r['Field'] ?? '');
            if ($f !== '') {
                $fields[$f] = true;
            }
        }
    } catch (Throwable $e) {
        $fields = [];
    }

    $nameCol = isset($fields['nomEmploye']) ? 'nomEmploye' : (isset($fields['nom_employe']) ? 'nom_employe' : 'nomEmploye');
    $posteCol = isset($fields['poste']) ? 'poste' : (isset($fields['fonction']) ? 'fonction' : null);

    return [
        'name' => $nameCol,
        'poste' => $posteCol,
        'status' => isset($fields['status']) ? 'status' : null,
    ];
}

function employeIsActive(PDO $bdd, int $idEmploye, ?string $statusCol): bool
{
    if ($idEmploye <= 0) return false;
    if (!$statusCol) return true;
    try {
        $st = $bdd->prepare('SELECT `' . $statusCol . '` AS st FROM employes WHERE id_employe = ? LIMIT 1');
        $st->execute([$idEmploye]);
        $v = $st->fetchColumn();
        return ((int) $v) === 1;
    } catch (Throwable $e) {
        return true;
    }
}

function normalizeDateOrEmpty(string $raw): string
{
    $v = trim($raw);
    if ($v === '') return '';

    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $v)
        ?: DateTimeImmutable::createFromFormat('d/m/Y', $v)
        ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $v);

    return $dt ? $dt->format('Y-m-d') : '';
}

$alert = null;
$error = null;

if (!tableExists($bdd, 'notes_service')) {
    $error = 'La table notes_service est introuvable. Exécutez db/notes_service.sql.';
}

// PRG
if (isset($_GET['ok'])) {
    $alert = ['type' => 'success', 'message' => 'Opération effectuée avec succès.'];
}

$cols = getEmployesColumnMap($bdd);
$nameCol = $cols['name'];
$posteCol = $cols['poste'];
$statusCol = $cols['status'] ?? null;

// Traitement POST (création / modification)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_note') {
        $idNote = (int) ($_POST['id_note'] ?? 0);
        $idEmploye = (int) ($_POST['id_employe'] ?? 0);
        $ancienPoste = trim((string) ($_POST['ancien_poste'] ?? ''));
        $nouveauPoste = trim((string) ($_POST['nouveau_poste'] ?? ''));
        $typeChangement = ((string) ($_POST['type_changement'] ?? 'definitif')) === 'temporaire' ? 'temporaire' : 'definitif';
        $dateDebut = normalizeDateOrEmpty((string) ($_POST['date_debut'] ?? ''));
        $dateFin = $typeChangement === 'temporaire' ? normalizeDateOrEmpty((string) ($_POST['date_fin'] ?? '')) : '';
        $motif = trim((string) ($_POST['motif'] ?? ''));
        $signataireNom = trim((string) ($_POST['signataire_nom'] ?? ''));
        $signataireFonction = trim((string) ($_POST['signataire_fonction'] ?? ''));

        if ($idEmploye <= 0) {
            $error = 'Veuillez sélectionner un employé.';
        } elseif (!employeIsActive($bdd, $idEmploye, $statusCol)) {
            $error = 'Employé inactif : impossible de délivrer une note de service.';
        } elseif ($ancienPoste === '') {
            $error = 'Ancien poste manquant.';
        } elseif ($nouveauPoste === '') {
            $error = 'Veuillez saisir le nouveau poste.';
        } elseif ($dateDebut === '') {
            $error = 'Veuillez saisir une date de début valide.';
        } elseif ($typeChangement === 'temporaire' && $dateFin === '') {
            $error = 'Veuillez saisir une date de fin pour un changement temporaire.';
        } else {
            try {
                if ($typeChangement === 'definitif') {
                    $dateFin = '';
                }

                if ($dateDebut !== '' && $dateFin !== '') {
                    $d1 = new DateTimeImmutable($dateDebut);
                    $d2 = new DateTimeImmutable($dateFin);
                    if ($d2 < $d1) {
                        $tmp = $dateDebut;
                        $dateDebut = $dateFin;
                        $dateFin = $tmp;
                    }
                }

                if ($idNote > 0) {
                    $sql = 'UPDATE notes_service
                            SET id_employe = ?, ancien_poste = ?, nouveau_poste = ?, type_changement = ?, date_debut = ?, date_fin = ?, motif = ?, signataire_nom = ?, signataire_fonction = ?
                            WHERE id_note = ?';
                    $params = [
                        $idEmploye,
                        $ancienPoste,
                        $nouveauPoste,
                        $typeChangement,
                        $dateDebut,
                        ($dateFin !== '' ? $dateFin : null),
                        ($motif !== '' ? $motif : null),
                        ($signataireNom !== '' ? $signataireNom : null),
                        ($signataireFonction !== '' ? $signataireFonction : null),
                        $idNote,
                    ];
                } else {
                    $sql = 'INSERT INTO notes_service
                                (id_employe, ancien_poste, nouveau_poste, type_changement, date_debut, date_fin, motif, signataire_nom, signataire_fonction)
                            VALUES
                                (?, ?, ?, ?, ?, ?, ?, ?, ?)';
                    $params = [
                        $idEmploye,
                        $ancienPoste,
                        $nouveauPoste,
                        $typeChangement,
                        $dateDebut,
                        ($dateFin !== '' ? $dateFin : null),
                        ($motif !== '' ? $motif : null),
                        ($signataireNom !== '' ? $signataireNom : null),
                        ($signataireFonction !== '' ? $signataireFonction : null),
                    ];
                }

                $st = $bdd->prepare($sql);
                $st->execute($params);

                header('Location: notedeservice.php?ok=1');
                exit;
            } catch (PDOException $e) {
                error_log('[notedeservice] save: ' . $e->getMessage());
                $error = 'Une erreur est survenue lors de l\'enregistrement.';
            }
        }
    }
}

// Récupérer les employés (pour le modal)
$employes = [];
if (!$error) {
    try {
        $select = 'SELECT id_employe, `' . $nameCol . '` AS employe_nom';
        if ($posteCol) {
            $select .= ', `' . $posteCol . '` AS employe_poste';
        } else {
            $select .= ', NULL AS employe_poste';
        }
        $select .= ' FROM employes';
        if ($statusCol) {
            $select .= ' WHERE `' . $statusCol . '` = 1';
        }
        $select .= ' ORDER BY `' . $nameCol . '` ASC';
        $st = $bdd->query($select);
        $employes = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        error_log('[notedeservice] employes: ' . $e->getMessage());
        $employes = [];
    }
}

// Liste des notes
$notes = [];
if (!$error) {
    try {
        $sql = 'SELECT n.*, e.`' . $nameCol . '` AS nomEmploye
                    FROM notes_service n
                    JOIN employes e ON e.id_employe = n.id_employe
                    ORDER BY n.created_at DESC, n.id_note DESC
                    LIMIT 200';
        $st = $bdd->query($sql);
        $notes = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        error_log('[notedeservice] list: ' . $e->getMessage());
        $notes = [];
        $error = 'Une erreur est survenue lors de la récupération des notes.';
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
                    <h2>Note de service</h2>
                </header>

                <div class="col-md-12">
                    <?php if ($alert): ?>
                        <div class="alert alert-<?php echo h($alert['type']); ?>">
                            <?php echo h($alert['message']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo h($error); ?></div>
                    <?php endif; ?>

                    <section class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div></div>
                                <div>
                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNote" onclick="openCreateNote()">Nouvelle note</button>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped mb-0" id="datatable-default">
                                    <thead>
                                        <tr>
                                            <th>Employé</th>
                                            <th>Ancien poste</th>
                                            <th>Nouveau poste</th>
                                            <th>Type</th>
                                            <th>Début</th>
                                            <th>Fin</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($notes)): ?>
                                            <tr>
                                                <td colspan="7">Aucune note trouvée.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($notes as $n): ?>
                                                <tr>
                                                    <td><?php echo h($n['nomEmploye'] ?? ''); ?></td>
                                                    <td><?php echo h($n['ancien_poste'] ?? ''); ?></td>
                                                    <td><?php echo h($n['nouveau_poste'] ?? ''); ?></td>
                                                    <td><?php echo h(($n['type_changement'] ?? '') === 'temporaire' ? 'Temporaire' : 'Définitif'); ?></td>
                                                    <td><?php echo h($n['date_debut'] ?? ''); ?></td>
                                                    <td><?php echo h(($n['date_fin'] ?? '') ?: '—'); ?></td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalNote"
                                                            data-note='<?php echo h(json_encode($n, JSON_UNESCAPED_UNICODE)); ?>'
                                                            onclick="openEditNote(this)">Modifier</button>
                                                        <button type="button" class="btn btn-sm btn-default" data-bs-toggle="modal" data-bs-target="#modalPrintNote" onclick="openPrintNote(<?php echo (int) ($n['id_note'] ?? 0); ?>)">Imprimer</button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Modal impression note (PDF) -->
                <div class="modal fade" id="modalPrintNote" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Impression de la note de service</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body" style="min-height:70vh;">
                                <iframe id="printNoteFrame" title="Note de service" style="width:100%; height:65vh; border:1px solid #e5e5e5;"></iframe>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-bs-dismiss="modal">Fermer</button>
                                <button type="button" class="btn btn-primary" id="btnPrintNote"><i class="fa fa-print"></i> Imprimer</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal note (création / modification) -->
                <div class="modal fade" id="modalNote" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <form method="post" id="noteForm">
                                <input type="hidden" name="action" value="save_note">
                                <input type="hidden" name="id_note" id="id_note" value="">

                                <div class="modal-header">
                                    <h5 class="modal-title" id="noteModalTitle">Nouvelle note de service</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>

                                <div class="modal-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Employé</label>
                                            <select class="form-select" name="id_employe" id="id_employe" required onchange="fillPosteEmploye()">
                                                <option value="">— Sélectionner —</option>
                                                <?php foreach ($employes as $e): ?>
                                                    <option
                                                        value="<?php echo h($e['id_employe'] ?? ''); ?>"
                                                        data-poste="<?php echo h($e['employe_poste'] ?? ''); ?>">
                                                        <?php echo h($e['employe_nom'] ?? ''); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Ancien poste</label>
                                            <input type="text" class="form-control" name="ancien_poste" id="ancien_poste" <?php echo $posteCol ? 'readonly' : ''; ?> required>
                                            <?php if (!$posteCol): ?>
                                                <small class="text-muted">Note: la colonne poste/fonction n'existe pas dans la table employés, saisie manuelle requise.</small>
                                            <?php endif; ?>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Nouveau poste</label>
                                            <input type="text" class="form-control" name="nouveau_poste" id="nouveau_poste" required>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Type de changement</label>
                                            <select class="form-select" name="type_changement" id="type_changement" required onchange="toggleDateFin()">
                                                <option value="definitif">Définitif</option>
                                                <option value="temporaire">Temporaire</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Date début</label>
                                            <input type="date" class="form-control" name="date_debut" id="date_debut" required>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Date fin</label>
                                            <input type="date" class="form-control" name="date_fin" id="date_fin" disabled>
                                        </div>

                                        <div class="col-md-12">
                                            <label class="form-label">Motif (optionnel)</label>
                                            <textarea class="form-control" name="motif" id="motif" rows="2"></textarea>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Signataire</label>
                                            <input type="text" class="form-control" name="signataire_nom" id="signataire_nom" placeholder="Ex: Directeur RH">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Fonction du signataire</label>
                                            <input type="text" class="form-control" name="signataire_fonction" id="signataire_fonction" placeholder="Ex: Directeur Général">
                                        </div>
                                    </div>
                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-default" data-bs-dismiss="modal">Annuler</button>
                                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </section>
        </div>
    </section>

    <?php include('../PUBLIC/footer.php'); ?>

    <script>
        function fillPosteEmploye() {
            var sel = document.getElementById('id_employe');
            if (!sel) return;
            var opt = sel.selectedOptions && sel.selectedOptions.length ? sel.selectedOptions[0] : null;
            var poste = opt ? (opt.getAttribute('data-poste') || '') : '';
            var ancien = document.getElementById('ancien_poste');
            if (ancien) {
                var isReadonly = ancien.hasAttribute('readonly');
                if (isReadonly || String(ancien.value || '').trim() === '') {
                    ancien.value = poste;
                }
            }
        }

        function toggleDateFin() {
            var type = document.getElementById('type_changement');
            var dateFin = document.getElementById('date_fin');
            if (!type || !dateFin) return;
            var isTemp = (type.value === 'temporaire');
            dateFin.disabled = !isTemp;
            if (!isTemp) {
                dateFin.value = '';
                dateFin.removeAttribute('required');
            } else {
                dateFin.setAttribute('required', 'required');
            }
        }

        function openCreateNote() {
            document.getElementById('noteModalTitle').textContent = 'Nouvelle note de service';
            document.getElementById('id_note').value = '';
            document.getElementById('id_employe').value = '';
            document.getElementById('ancien_poste').value = '';
            document.getElementById('nouveau_poste').value = '';
            document.getElementById('type_changement').value = 'definitif';
            document.getElementById('date_debut').value = '';
            document.getElementById('date_fin').value = '';
            document.getElementById('motif').value = '';
            document.getElementById('signataire_nom').value = '';
            document.getElementById('signataire_fonction').value = '';
            toggleDateFin();
        }

        function openEditNote(btn) {
            const raw = btn.getAttribute('data-note') || '{}';
            let n = {};
            try { n = JSON.parse(raw); } catch (e) { n = {}; }

            document.getElementById('noteModalTitle').textContent = 'Modifier note de service';
            document.getElementById('id_note').value = n.id_note || '';
            document.getElementById('id_employe').value = n.id_employe || '';
            document.getElementById('ancien_poste').value = n.ancien_poste || '';
            document.getElementById('nouveau_poste').value = n.nouveau_poste || '';
            document.getElementById('type_changement').value = (n.type_changement === 'temporaire' ? 'temporaire' : 'definitif');
            document.getElementById('date_debut').value = (n.date_debut || '').slice(0, 10);
            document.getElementById('date_fin').value = (n.date_fin || '').slice(0, 10);
            document.getElementById('motif').value = n.motif || '';
            document.getElementById('signataire_nom').value = n.signataire_nom || '';
            document.getElementById('signataire_fonction').value = n.signataire_fonction || '';

            toggleDateFin();
        }

        function openPrintNote(idNote) {
            const id = parseInt(idNote || 0, 10);
            const url = '../impression/_note_service.php?id=' + encodeURIComponent(id);
            const frame = document.getElementById('printNoteFrame');
            if (frame) frame.src = url;
        }

        document.addEventListener('DOMContentLoaded', function () {
            toggleDateFin();

            const modalEl = document.getElementById('modalPrintNote');
            if (modalEl) {
                modalEl.addEventListener('hidden.bs.modal', function () {
                    const frame = document.getElementById('printNoteFrame');
                    if (frame) frame.src = '';
                });
            }

            const btnPrint = document.getElementById('btnPrintNote');
            if (btnPrint) {
                btnPrint.addEventListener('click', function () {
                    const frame = document.getElementById('printNoteFrame');
                    if (!frame) return;
                    try {
                        if (frame.contentWindow) {
                            frame.contentWindow.focus();
                            frame.contentWindow.print();
                        }
                    } catch (e) { }
                });
            }
        });
    </script>
</body>