<?php
include('../PUBLIC/connect.php');
include('../PUBLIC/fonction.php');
session_start();
    $errors = 0; $existe = 0;
    if (isset($_POST['recherche'])) {
        $recherche = trim($_POST['recherche']);
        if ($recherche === '') {
            $existe = 1;
        } else {
            $req1 = $bdd->prepare('SELECT 1 FROM affectations WHERE id_patient=? LIMIT 1');
            $req1->execute(array($recherche));
            if (!$req1->fetch()) {
                $existe = 1;
            } else {
                header('Location: reimpressiondocument.php?recherche=' . urlencode($recherche));
                exit();
            }
        }
    }

    $patientSearchId = isset($_GET['recherche']) ? (int)$_GET['recherche'] : 0;
    $showResultsModal = false;
    $resultsRows = [];

    // Détecter le "type" de vente lunettes (pour filtrer les reçus lunettes)
    $venteLunetteTypeId = 0;
    try {
        $stType = $bdd->prepare("SELECT id_type FROM traitements WHERE LOWER(nom_type) LIKE '%lunet%' OR LOWER(nom_type) LIKE '%monture%' ORDER BY id_type ASC LIMIT 1");
        $stType->execute();
        $venteLunetteTypeId = (int)($stType->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        $venteLunetteTypeId = 0;
    }

    if ($patientSearchId > 0) {
        $showResultsModal = true;

        // 1) Ordonnance de lunettes (dernière)
        try {
            $stOrd = $bdd->prepare('SELECT id_affectation, date_traitement FROM mesures WHERE id_patient = ? ORDER BY date_traitement DESC, id_mesure DESC LIMIT 1');
            $stOrd->execute([$patientSearchId]);
            $ord = $stOrd->fetch(PDO::FETCH_ASSOC);
            if ($ord && (int)$ord['id_affectation'] > 0) {
                $affId = (int)$ord['id_affectation'];
                $resultsRows[] = [
                    'date' => (string)($ord['date_traitement'] ?? ''),
                    'type' => 'Ordonnance lunettes',
                    'affectation' => $affId,
                    'url' => '../optometrie/imprimer_mesure.php?affectation=' . $affId . '&autoprint=0',
                    'title' => 'Ordonnance des lunettes',
                ];
            }
        } catch (Throwable $e) {
            // noop
        }

        // 2) Reçus de paiement lunettes (tous les paiements associés)
        try {
            if ($venteLunetteTypeId > 0) {
                $stPay = $bdd->prepare(
                    'SELECT p.id_paiement, p.datepaiement, p.id_affectation, p.code '
                    . 'FROM paiements p INNER JOIN affectations a ON p.id_affectation = a.id_affectation '
                    . 'WHERE a.id_patient = ? AND a.type = ? '
                    . 'ORDER BY p.datepaiement DESC, p.id_paiement DESC'
                );
                $stPay->execute([$patientSearchId, $venteLunetteTypeId]);
            } else {
                // Fallback si le type lunettes n'est pas détectable: on liste tous les paiements du patient
                $stPay = $bdd->prepare(
                    'SELECT p.id_paiement, p.datepaiement, p.id_affectation, p.code '
                    . 'FROM paiements p INNER JOIN affectations a ON p.id_affectation = a.id_affectation '
                    . 'WHERE a.id_patient = ? '
                    . 'ORDER BY p.datepaiement DESC, p.id_paiement DESC'
                );
                $stPay->execute([$patientSearchId]);
            }

            while ($pay = $stPay->fetch(PDO::FETCH_ASSOC)) {
                $paiementId = (int)($pay['id_paiement'] ?? 0);
                $affId = (int)($pay['id_affectation'] ?? 0);
                if ($paiementId <= 0 || $affId <= 0) continue;
                $resultsRows[] = [
                    'date' => (string)($pay['datepaiement'] ?? ''),
                    'type' => 'Reçu paiement lunettes',
                    'affectation' => $affId,
                    'url' => '../caisse/imprimer_recu.php?affectation=' . $affId . '&paiement=' . $paiementId . '&autoprint=0',
                    'title' => 'Reçu de paiement lunettes',
                ];
            }
        } catch (Throwable $e) {
            // noop
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
                    <h2>Recherche documentation d'un patient</h2>
                </header>

                <!-- start: page -->

                <div class="col-md-12">
                    <section class="card">
                        <div class="card-body">
                            <?php
                                if ($existe==1) {
                                echo '
                                    <div class="alert alert-danger">
                                        <li>Aucun document trouvé dans le système pour cet identifiant saisie.</li>
                                    </div>
                                    ';
                                    } 
                            ?>
                            <form class="form-horizontal" novalidate method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
                                <div class="row form-group pb-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Saisir le n° du dossier du patient</label>
                                            <input type="text" class="form-control" name="recherche" id="formGroupExampleInput" placeholder="" required>
                                        </div>
                                    </div>
                                </div>
                                <footer class="card-footer text-end">
                                    <button class="btn btn-primary" type="submit">Rechercher</button>
                                </footer>
                            </form>
                    </section>
                </div> <br>

                <section>
                    <!-- Les résultats s'affichent désormais dans un modal -->
                    <!-- end: page -->
                    </section>
        </div>

    </section>

    <!-- Modal Document (aperçu + impression) -->
    <div class="modal fade" id="documentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="documentModalTitle">Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="height:80vh;">
                    <iframe id="documentFrame" src="about:blank" style="width:100%; height:100%;" frameborder="0"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" id="documentPrintBtn" class="btn btn-primary">Imprimer</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Résultats (liste des documents) -->
    <div class="modal fade" id="resultsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resultsModalTitle">Documents</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if ($patientSearchId > 0): ?>
                        <div class="mb-3">
                            <?php echo htmlspecialchars((string)nom_patient($patientSearchId)); ?>
                            <span class="text-muted"></span>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th style="width:18%">DATE</th>
                                    <th style="width:22%">TYPE</th>
                                    <th>DOCUMENT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($showResultsModal && !empty($resultsRows)): ?>
                                    <?php foreach ($resultsRows as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string)$r['date']); ?></td>
                                            <td><?php echo htmlspecialchars((string)$r['type']); ?></td>
                                            <td>
                                                <a href="<?php echo htmlspecialchars((string)$r['url']); ?>"
                                                   class="btn btn-sm btn-info js-open-document"
                                                   data-title="<?php echo htmlspecialchars((string)$r['title']); ?>">
                                                    Ouvrir
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-danger">Aucun document trouvé pour ce patient.</td>
                                    </tr>
                                <?php endif; ?>
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
        const resultsModalEl = document.getElementById('resultsModal');
        const modalEl = document.getElementById('documentModal');
        const frameEl = document.getElementById('documentFrame');
        const titleEl = document.getElementById('documentModalTitle');
        const printBtnEl = document.getElementById('documentPrintBtn');

        function openDocumentModal(url, title) {
            if (!url) return;
            if (!window.bootstrap || !modalEl || !frameEl) {
                alert('Impossible d\'ouvrir le document: Bootstrap indisponible.');
                return;
            }
            // Fermer le modal de résultats pour éviter l'empilement
            try {
                if (resultsModalEl) {
                    const inst = window.bootstrap.Modal.getInstance(resultsModalEl);
                    if (inst) inst.hide();
                }
            } catch (e) {
                // noop
            }
            if (titleEl) titleEl.textContent = title || 'Document';
            frameEl.src = url;
            const instance = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
            instance.show();
        }

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.js-open-document');
            if (!btn) return;
            const href = btn.getAttribute('href');
            if (!href || href === '#') return;
            e.preventDefault();
            const title = btn.getAttribute('data-title') || btn.textContent || 'Document';
            openDocumentModal(href, title.trim());
        });

        if (printBtnEl) {
            printBtnEl.addEventListener('click', function () {
                try {
                    const win = frameEl && frameEl.contentWindow ? frameEl.contentWindow : null;
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

        if (modalEl) {
            modalEl.addEventListener('hidden.bs.modal', function () {
                if (frameEl) frameEl.src = 'about:blank';
            });
        }

        // Ouvre automatiquement le modal des résultats après une recherche
        <?php if ($showResultsModal): ?>
        try {
            if (window.bootstrap && resultsModalEl) {
                const inst = window.bootstrap.Modal.getInstance(resultsModalEl) || new window.bootstrap.Modal(resultsModalEl);
                inst.show();
            }
        } catch (e) {
            // noop
        }
        <?php endif; ?>
    });
    </script>

    <?php include('../PUBLIC/footer.php');?>