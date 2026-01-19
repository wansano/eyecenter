<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

$ref = isset($_GET['ref']) ? trim((string)$_GET['ref']) : '';

$patientId = 0;
$bonId = 0;

if ($ref !== '') {
    // Convention: BR<numero> => bon de remboursement, sinon => ID patient
    $upper = strtoupper($ref);
    if (str_starts_with($upper, 'BR')) {
        $raw = trim(substr($ref, 2));
        // accepte "BR123", "BR 123", "BR-123"
        $raw = preg_replace('/[^0-9]/', '', (string)$raw);
        $bonId = (int)$raw;
    } else {
        $raw = preg_replace('/[^0-9]/', '', (string)$ref);
        $patientId = (int)$raw;
    }
}

$errors = [];
$results = [];

if ($ref !== '' && $patientId <= 0 && $bonId <= 0) {
    $errors[] = "Référence invalide. Utilisez 'BR' + numéro pour un bon (ex: BR123) ou seulement l'ID patient (ex: 123).";
} elseif ($patientId > 0 || $bonId > 0) {
    try {
        if ($bonId > 0) {
            $st = $bdd->prepare('SELECT id_remboursement, id_affectation, patient, types, montant_paye, montant_remboursse, compte, motif, date_ajout FROM remboursements WHERE id_remboursement = ? AND (montant_paye > 0 OR montant_remboursse > 0) LIMIT 1');
            $st->execute([$bonId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $results[] = $row;
            } else {
                $errors[] = "Aucun bon de remboursement validé trouvé pour ce numéro.";
            }
        } else {
            $st = $bdd->prepare('SELECT id_remboursement, id_affectation, patient, types, montant_paye, montant_remboursse, compte, motif, date_ajout FROM remboursements WHERE patient = ? AND (montant_paye > 0 OR montant_remboursse > 0) ORDER BY id_remboursement DESC LIMIT 200');
            $st->execute([$patientId]);
            $results = $st->fetchAll(PDO::FETCH_ASSOC);
            if (!$results) {
                $errors[] = "Aucun bon de remboursement validé trouvé pour ce patient.";
            }
        }
    } catch (Throwable $e) {
        error_log('Erreur recherche bon remboursement: ' . $e->getMessage());
        $errors[] = "Une erreur est survenue lors de la recherche.";
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
                <h2>Réimpression bon de remboursement</h2>
            </header>

            <div class="col-md-12">
                <div class="row">
                    <div class="col">
                        <section class="card">
                            <div class="card-body">

                                <div class="alert alert-info">
                                    Saisissez la <strong>référence</strong> puis lancez la recherche.
                                    <br>
                                    - Bon de remboursement : préfixez par <strong>BR</strong> (ex: <strong>BR123</strong>)
                                    <br>
                                    - Patient : saisissez seulement l'<strong>ID</strong> (ex: <strong>123</strong>)
                                </div>

                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger">
                                        <strong>Erreur</strong>
                                        <ul class="mb-0">
                                            <?php foreach ($errors as $msg): ?>
                                                <li><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <form method="get" class="row g-3 align-items-end" novalidate>
                                    <div class="col-md-8">
                                        <label class="col-form-label" for="ref">Référence</label>
                                        <input type="text" class="form-control" name="ref" id="ref" value="<?php echo htmlspecialchars($ref, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ex: BR123 ou 123" autocomplete="off">
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-primary">Rechercher</button>
                                        <a href="imprimer_bonremboursement.php" class="btn btn-default">Réinitialiser</a>
                                    </div>
                                </form>

                                <hr>

                                <?php if (!empty($results)): ?>
                                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                                        <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>N° bon</th>
                                            <th>Dossier</th>
                                            <th>Patient</th>
                                            <th>Type</th>
                                            <th>Motif</th>
                                            <th>Montant</th>
                                            <th>Action</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($results as $r):
                                            $rid = (int)($r['id_remboursement'] ?? 0);
                                            $pid = (int)($r['patient'] ?? 0);
                                            $typeTxt = isset($r['types']) ? model($r['types']) : '';
                                            $motif = (string)($r['motif'] ?? '');
                                            $date = (string)($r['date_ajout'] ?? '');
                                            $montant = 0;
                                            if (isset($r['montant_remboursse']) && (float)$r['montant_remboursse'] > 0) {
                                                $montant = (float)$r['montant_remboursse'];
                                            } elseif (isset($r['montant_paye'])) {
                                                $montant = (float)$r['montant_paye'];
                                            }
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($date, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo $rid; ?></td>
                                                <td><?php echo $pid; ?></td>
                                                <td><?php echo htmlspecialchars(nom_patient($pid), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($typeTxt, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($motif, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo number_format($montant, 0, ',', ' ') . ' ' . htmlspecialchars($devise, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td>
                                                    <a class="btn btn-sm btn-info js-open-print" data-title="Bon de remboursement" href="imprimer_remboursement.php?remboursement=<?php echo $rid; ?>">
                                                        Imprimer
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>

                            </div>
                        </section>
                    </div>
                </div>
            </div>

        </section>
    </div>

    <!-- Modal impression (iframe) -->
    <div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="printModalTitle">Impression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="height:75vh;">
                    <iframe id="printModalFrame" src="about:blank" style="width:100%;height:100%;border:0;"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-bs-dismiss="modal">Fermer</button>
                    <button type="button" class="btn btn-primary" id="btnPrintModal"><i class="fa fa-print"></i> Imprimer</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            if (typeof window.openPrintModal !== 'function') {
                window.openPrintModal = function (url, title) {
                    try {
                        if (!window.bootstrap) {
                            window.open(url, '_blank');
                            return;
                        }

                        var modalEl = document.getElementById('printModal');
                        var frame = document.getElementById('printModalFrame');
                        if (!modalEl || !frame) {
                            window.open(url, '_blank');
                            return;
                        }

                        var titleEl = document.getElementById('printModalTitle');
                        if (titleEl && title) titleEl.textContent = title;
                        frame.setAttribute('src', url);

                        var modal = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
                        modal.show();
                    } catch (e) {
                        try { window.open(url, '_blank'); } catch (_) {}
                    }
                };
            }

            function printCurrentModalFrame() {
                try {
                    var frame = document.getElementById('printModalFrame');
                    if (!frame || !frame.contentWindow) return;
                    if (typeof frame.contentWindow.printPdf === 'function') {
                        frame.contentWindow.printPdf();
                    } else {
                        frame.contentWindow.print();
                    }
                } catch (e) {
                    // noop
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                document.addEventListener('click', function (e) {
                    var a = e.target && e.target.closest ? e.target.closest('a.js-open-print') : null;
                    if (!a) return;
                    e.preventDefault();
                    var url = a.getAttribute('href');
                    var title = a.getAttribute('data-title') || 'Impression';
                    window.openPrintModal(url, title);
                });

                var btnPrint = document.getElementById('btnPrintModal');
                if (btnPrint) btnPrint.addEventListener('click', printCurrentModalFrame);

                var modalEl = document.getElementById('printModal');
                if (modalEl) {
                    modalEl.addEventListener('hidden.bs.modal', function () {
                        var frame = document.getElementById('printModalFrame');
                        if (frame) frame.setAttribute('src', 'about:blank');
                    });
                }
            });
        })();
    </script>

    <?php include('../PUBLIC/footer.php'); ?>
