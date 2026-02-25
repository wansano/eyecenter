<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();
$errors = 0;

$mode = isset($_GET['mode']) ? (string)$_GET['mode'] : 'afaire';
if ($mode !== 'effectues') {
    $mode = 'afaire';
}

$filterErrorsFutureDate = 0;
$filterErrorsRange = 0;
$debut = isset($_GET['debut']) ? (string)$_GET['debut'] : '';
$fin = isset($_GET['fin']) ? (string)$_GET['fin'] : '';
$compteFiltre = isset($_GET['compte']) ? (int)$_GET['compte'] : 0;

if (isset($_POST['annuler'])) {
    $reponse = $bdd->prepare('UPDATE affectations SET status=1 WHERE id_affectation=?');
    $reponse->execute([$_POST['annuler']]);
    $errors = 3;
}

if ($mode === 'effectues' && $debut && $fin) {
    $today = date('Y-m-d');
    if ($debut > $today || $fin > $today) {
        $filterErrorsFutureDate = 1;
    } elseif ($debut > $fin) {
        $filterErrorsRange = 1;
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
                <h2><?php echo ($mode === 'effectues') ? 'Historique des remboursements effectués' : 'Situation des remboursements à faire'; ?></h2>
            </header>
            <!-- start: page -->
            <div class="col-md-12">
                <div class="row">
                    <div class="col">
                        <section class="card">
                            <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="btn-group" role="group" aria-label="Navigation remboursements">
                                    <a href="remboursement.php?mode=afaire" class="btn btn-sm <?php echo ($mode === 'afaire') ? 'btn-primary' : 'btn-outline-primary'; ?>">À faire</a>
                                    <a href="remboursement.php?mode=effectues" class="btn btn-sm <?php echo ($mode === 'effectues') ? 'btn-primary' : 'btn-outline-primary'; ?>">Effectués</a>
                                </div>
                                <?php if ($mode === 'afaire'): ?>
                                    <a class="btn btn-sm btn-info" href="remboursement.php?mode=effectues">Voir l'historique</a>
                                <?php endif; ?>
                            </div>

                            <?php if ($mode === 'effectues'): ?>
                            <form class="form-horizontal" method="GET" action="remboursement.php">
                                <input type="hidden" name="mode" value="effectues">
                                <div class="row form-group pb-2">
                                    <div class="col-md-3">
                                        <label class="col-form-label">Date début</label>
                                        <input type="date" name="debut" class="form-control" required max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($debut); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="col-form-label">Date fin</label>
                                        <input type="date" name="fin" class="form-control" required max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($fin); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="col-form-label">Compte (optionnel)</label>
                                        <select name="compte" class="form-control">
                                            <option value="0">Tous les comptes</option>
                                            <?php
                                                $st = $bdd->prepare('SELECT id_compte, nom_compte FROM comptes WHERE status = 1 ORDER BY nom_compte');
                                                $st->execute();
                                                while ($c = $st->fetch(PDO::FETCH_ASSOC)) {
                                                    $sel = ((int)$c['id_compte'] === (int)$compteFiltre) ? 'selected' : '';
                                                    echo '<option value="'.(int)$c['id_compte'].'" '.$sel.'>'.htmlspecialchars($c['nom_compte']).'</option>';
                                                }
                                        ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button class="btn btn-primary w-100" type="submit">Filtrer</button>
                                    </div>
                                </div>
                                <?php if ($filterErrorsFutureDate==1): ?>
                                    <div class="alert alert-danger"><strong>Erreur:</strong> Dates futures interdites.</div>
                                <?php endif; ?>
                                <?php if ($filterErrorsRange==1): ?>
                                    <div class="alert alert-danger"><strong>Erreur:</strong> La date de début ne peut pas être supérieure à la date de fin.</div>
                                <?php endif; ?>
                            </form>
                            <?php endif; ?>

                                <?php if ($errors == 3): ?>
                                    <div class="alert alert-success">
                                        <strong>Succès !</strong> <br/>
                                        <li>Annulation du remboursement effectué avec succès.</li>
                                        <li>Le dossier du patient à été transmis au service concerné. Merci de rediriger le patient vers le service traitant.</li>
                                    </div>
                                <?php endif; ?>
                                <table class="table table-bordered table-striped mb-0" id="datatable-default">
                                    <thead>
                                        <tr>
                                        <?php if ($mode === 'effectues'): ?>
                                            <th>DATE</th>
                                            <th>N° PAIEMENT</th>
                                            <th>DOSSIER</th>
                                            <th>PATIENT</th>
                                            <th>CONTACT</th>
                                            <th>EXAMEN</th>
                                            <th>COMPTE</th>
                                            <th>MONTANT REMBOURSÉ</th>
                                            <th>ACTION</th>
                                        <?php else: ?>
                                            <th>DATE</th>
                                            <th>N° PAIEMENT</th>
                                            <th>DOSSIER</th>
                                            <th>PATIENT</th>
                                            <th>CONTACT</th>
                                            <th>EXAMEN</th>
                                            <th>MONTANT A PAYE</th>
                                            <th>STATUS</th>
                                        <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                        if ($mode === 'effectues') {
                                            if (!$debut || !$fin) {
                                                echo '<tr><td colspan="9"><div class="alert alert-info mb-0">Choisissez une période pour afficher l\'historique.</div></td></tr>';
                                            } elseif ($filterErrorsFutureDate || $filterErrorsRange) {
                                                echo '<tr><td colspan="9"><div class="alert alert-danger mb-0">Veuillez corriger la période.</div></td></tr>';
                                            } else {
                                                $paiementsAmountCol = 'montant';
                                                try {
                                                    $stCols = $bdd->query('SHOW COLUMNS FROM paiements');
                                                    if ($stCols) {
                                                        while ($c = $stCols->fetch(PDO::FETCH_ASSOC)) {
                                                            if (($c['Field'] ?? '') === 'montant_paye') {
                                                                $paiementsAmountCol = 'montant_paye';
                                                                break;
                                                            }
                                                        }
                                                    }
                                                } catch (Throwable $e) {
                                                    $paiementsAmountCol = 'montant';
                                                }

                                                $sql = 'SELECT p.id_affectation, p.datepaiement, p.compte, p.`' . $paiementsAmountCol . '` AS montant_remb, a.id_patient, a.type
                                                        FROM paiements p
                                                        LEFT JOIN affectations a ON a.id_affectation = p.id_affectation
                                                        WHERE p.remboursement = 1 AND p.datepaiement BETWEEN :debut AND :fin';
                                                $params = [':debut' => $debut, ':fin' => $fin];
                                                if ((int)$compteFiltre !== 0) {
                                                    $sql .= ' AND p.compte = :compte';
                                                    $params[':compte'] = (int)$compteFiltre;
                                                }
                                                $sql .= ' ORDER BY p.datepaiement DESC, p.id_affectation DESC';
                                                $st = $bdd->prepare($sql);
                                                $st->execute($params);
                                                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                                                if (!$rows) {
                                                    echo '<tr><td colspan="9"><div class="alert alert-info mb-0">Aucun remboursement sur cette période.</div></td></tr>';
                                                } else {
                                                    foreach ($rows as $r) {
                                                        $idAffectation = (int)($r['id_affectation'] ?? 0);
                                                        $idPatient = isset($r['id_patient']) ? (int)$r['id_patient'] : 0;
                                                        $idCompte = isset($r['compte']) ? (int)$r['compte'] : 0;
                                                        $montantRemb = isset($r['montant_remb']) ? (float)$r['montant_remb'] : 0.0;
                                                        $typeExamen = isset($r['type']) ? (string)$r['type'] : '';
                                                        echo '<tr>';
                                                        echo '<td>' . htmlspecialchars((string)($r['datepaiement'] ?? '')) . '</td>';
                                                        echo '<td>' . htmlspecialchars(function_exists('getNumeroPaiement') ? (string)getNumeroPaiement($bdd, $idAffectation) : '') . '</td>';
                                                        echo '<td>' . htmlspecialchars((string)$idPatient) . '</td>';
                                                        echo '<td>' . htmlspecialchars(function_exists('nom_patient') ? (string)nom_patient($idPatient) : '') . '</td>';
                                                        echo '<td>' . htmlspecialchars(function_exists('return_phone') ? (string)return_phone($idPatient) : '') . '</td>';
                                                        echo '<td>' . htmlspecialchars(function_exists('model') ? (string)model($typeExamen) : $typeExamen) . '</td>';
                                                        echo '<td>' . htmlspecialchars(function_exists('compte') ? (string)compte($idCompte) : (string)$idCompte) . '</td>';
                                                        echo '<td>' . number_format($montantRemb, 0, '', ' ') . ' ' . htmlspecialchars($devise) . '</td>';
                                                        echo '<td>';
                                                        if ($idAffectation > 0) {
                                                            echo '<a href="imprimer_remboursement.php?affectation=' . urlencode((string)$idAffectation) . '" class="btn btn-sm btn-secondary js-open-pdf" data-title="Reçu de remboursement">reçu</a>';
                                                        }
                                                        echo '</td>';
                                                        echo '</tr>';
                                                    }
                                                }
                                            }
                                        } else {
                                            $reponse1 = $bdd->prepare('SELECT * FROM affectations WHERE (status=? OR status=?) AND montant > ?');
                                            $reponse1->execute([99, 0, 0]);
                                            while ($donnees1 = $reponse1->fetch()) {
                                                $status = $donnees1['status'];
                                                echo '<tr>';
                                                echo '<td>' . htmlspecialchars($donnees1['date']) . '</td>';
                                                echo '<td>' . htmlspecialchars(getNumeroPaiement($bdd, $donnees1['id_affectation'])) . '</td>';
                                                echo '<td>' . htmlspecialchars($donnees1['id_patient']) . '</td>';
                                                echo '<td>' . htmlspecialchars(nom_patient($donnees1['id_patient'])) . '</td>';
                                                echo '<td>' . htmlspecialchars(return_phone($donnees1['id_patient'])) . '</td>';
                                                echo '<td>' . htmlspecialchars(model($donnees1['type'])) . '</td>';
                                                echo '<td>' . number_format($donnees1['montant']) . ' ' . htmlspecialchars($devise) . '</td>';
                                                echo '<td>';
                                                if ($status == 99 || $status == 0) {
                                                    echo '<form action="remboursement.php?profil=' . htmlspecialchars($types) . '" method="post">';
                                                    echo '<a href="payementreval.php?id_affectation=' . urlencode($donnees1['id_affectation']) . '" class="btn btn-sm btn-success">proceder</a> ';
                                                    echo '<input type="hidden" name="annuler" value="' . htmlspecialchars($donnees1['id_affectation']) . '">';
                                                    echo '<button type="submit" class="btn btn-sm btn-danger">annuler</button>';
                                                    echo '</form>';
                                                }
                                                echo '</td>';
                                                echo '</tr>';
                                            }
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

        <!-- Modal Impression (aperçu + impression) -->
        <div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Impression</h5>
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

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalEl = document.getElementById('printModal');
            const frameEl = document.getElementById('printFrame');
            const titleEl = modalEl ? modalEl.querySelector('.modal-title') : null;
            const printBtnEl = document.getElementById('printBtn');

            function withAutoPrintDisabled(url) {
                if (!url) return url;
                return url + (url.indexOf('?') >= 0 ? '&' : '?') + 'autoprint=0';
            }

            function openPrintModal(url, title) {
                if (!url) return;
                if (!window.bootstrap || !modalEl || !frameEl) {
                    window.open(url, '_blank');
                    return;
                }
                if (titleEl) titleEl.textContent = title || 'Impression';
                frameEl.src = withAutoPrintDisabled(url);
                const instance = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
                instance.show();
            }

            document.addEventListener('click', function (e) {
                const btn = e.target.closest('.js-open-pdf');
                if (!btn) return;
                const href = btn.getAttribute('href');
                if (!href || href === '#') return;
                e.preventDefault();
                openPrintModal(href, btn.getAttribute('data-title') || 'Impression');
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
        });
        </script>

        <?php include('../PUBLIC/footer.php'); ?>