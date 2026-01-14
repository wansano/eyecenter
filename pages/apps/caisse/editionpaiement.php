<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

// ===================== AJAX: Recherche paiement =====================
if (isset($_GET['ajax_find'])) {
    header('Content-Type: application/json; charset=UTF-8');

    $code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
    if ($code === '') {
        echo json_encode(['success' => false, 'message' => 'Veuillez saisir le numéro de paiement.']);
        exit;
    }

    try {
        $stmt = $bdd->prepare(
            'SELECT p.id_paiement, p.code, p.id_affectation, p.types, p.montant, p.montant_paye, p.compte, p.patient, p.caisse, p.datepaiement,
                    pa.nom_patient,
                    a.type AS type_traitement
             FROM paiements p
             LEFT JOIN patients pa ON pa.id_patient = p.patient
             LEFT JOIN affectations a ON a.id_affectation = p.id_affectation
             WHERE p.code = ?
             ORDER BY p.id_paiement DESC
             LIMIT 1'
        );
        $stmt->execute([$code]);
        $pay = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pay) {
            echo json_encode(['success' => false, 'message' => 'Aucun paiement trouvé pour ce numéro.']);
            exit;
        }

        // Sécurité: paiement encaisse par la caisse (utilisateur connecté)
        if (!isset($_SESSION['auth']) || (string)$pay['caisse'] !== (string)$_SESSION['auth']) {
            echo json_encode(['success' => false, 'message' => 'Ce paiement n\'a pas été encaissé par votre caisse.']);
            exit;
        }

        $montant = (float)($pay['montant_paye'] ?: $pay['montant']);
        $compteId = (int)($pay['compte'] ?? 0);
        $typeTraitement = (int)($pay['type_traitement'] ?? $pay['types'] ?? 0);

        // Libellé compte
        $compteLabel = '';
        if ($compteId > 0) {
            try {
                $cst = $bdd->prepare('SELECT types FROM comptes WHERE id_compte = ? LIMIT 1');
                $cst->execute([$compteId]);
                $compteLabel = (string)($cst->fetchColumn() ?: '');
            } catch (Throwable $e) {
                $compteLabel = '';
            }
        }

        // Options comptes
        $comptes = [];
        $stC = $bdd->prepare('SELECT id_compte, types FROM comptes WHERE defaut=1 AND compte_pour=?');
        $stC->execute([1]);
        while ($c = $stC->fetch(PDO::FETCH_ASSOC)) {
            $comptes[] = ['id' => (int)$c['id_compte'], 'label' => (string)$c['types']];
        }

        echo json_encode([
            'success' => true,
            'paiement' => [
                'id_paiement' => (int)$pay['id_paiement'],
                'code' => (string)$pay['code'],
                'id_affectation' => (int)($pay['id_affectation'] ?? 0),
                'patient' => (string)($pay['nom_patient'] ?? ''),
                'examen' => (string)model($typeTraitement),
                'date' => (string)($pay['datepaiement'] ?? ''),
                'montant' => $montant,
                'montant_label' => number_format($montant, 0, ',', ' '),
                'compte_id' => $compteId,
                'compte_label' => $compteLabel,
            ],
            'options' => [
                'comptes' => $comptes,
            ],
        ]);
        exit;
    } catch (Throwable $e) {
        error_log('[EDITION PAIEMENT ajax_find] ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la recherche du paiement.']);
        exit;
    }
}

// ===================== AJAX: Modifier type paiement =====================
if (isset($_POST['ajax_update'])) {
    header('Content-Type: application/json; charset=UTF-8');

    $idPaiement = isset($_POST['id_paiement']) ? (int)$_POST['id_paiement'] : 0;
    $newCompte = isset($_POST['new_compte']) ? (int)$_POST['new_compte'] : 0;

    if ($idPaiement <= 0 || $newCompte <= 0) {
        echo json_encode(['success' => false, 'message' => 'Paramètres invalides.']);
        exit;
    }

    try {
        // Transaction + verrous
        $bdd->beginTransaction();
        try {
            $stmt = $bdd->prepare(
                'SELECT id_paiement, id_affectation, montant, montant_paye, compte, caisse
                 FROM paiements
                 WHERE id_paiement = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute([$idPaiement]);
            $pay = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pay) {
                throw new Exception('Paiement introuvable.');
            }

            if (!isset($_SESSION['auth']) || (string)$pay['caisse'] !== (string)$_SESSION['auth']) {
                throw new Exception('Ce paiement n\'a pas été encaissé par votre caisse.');
            }

            $oldCompte = (int)($pay['compte'] ?? 0);
            if ($oldCompte <= 0) {
                throw new Exception('Ancien type de paiement invalide.');
            }

            if ($oldCompte === $newCompte) {
                throw new Exception('Le type de paiement sélectionné est identique.');
            }

            $oldMontant = (float)($pay['montant_paye'] ?: $pay['montant']);
            if ($oldMontant <= 0) {
                throw new Exception('Montant du paiement invalide.');
            }

            // Verrouiller les deux comptes (+ taux pour frais électronique)
            $stmt = $bdd->prepare('SELECT id_compte, debit, taux FROM comptes WHERE id_compte IN (?, ?) FOR UPDATE');
            $stmt->execute([$oldCompte, $newCompte]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $comptesMap = [];
            foreach ($rows as $r) {
                $cid = (int)$r['id_compte'];
                $comptesMap[$cid] = [
                    'debit' => (float)($r['debit'] ?? 0),
                    'taux' => (float)($r['taux'] ?? 0),
                ];
            }
            if (!array_key_exists($oldCompte, $comptesMap) || !array_key_exists($newCompte, $comptesMap)) {
                throw new Exception('Compte(s) introuvable(s).');
            }

            $oldIsElec = (IsPaiementElectronique($oldCompte) === 1);
            $newIsElec = (IsPaiementElectronique($newCompte) === 1);
            $oldTaux = $comptesMap[$oldCompte]['taux'];
            $newTaux = $comptesMap[$newCompte]['taux'];

            // Reconstituer le montant de base (sans frais) puis recalculer selon le nouveau compte
            $baseMontant = $oldMontant;
            if ($oldIsElec) {
                $den = 1 + ($oldTaux / 100);
                if ($den <= 0) {
                    throw new Exception('Taux du compte électronique invalide.');
                }
                $baseMontant = $oldMontant / $den;
            }

            $newMontant = $baseMontant;
            if ($newIsElec) {
                $newMontant = $baseMontant * (1 + ($newTaux / 100));
            }

            if ($newMontant <= 0) {
                throw new Exception('Nouveau montant invalide.');
            }

            // Transfert: retirer l'ancien montant (tel qu'encaisse) de l'ancien compte, ajouter le nouveau montant
            $newDebitOld = $comptesMap[$oldCompte]['debit'] - $oldMontant;
            $newDebitNew = $comptesMap[$newCompte]['debit'] + $newMontant;

            $stmt = $bdd->prepare('UPDATE comptes SET debit = ? WHERE id_compte = ?');
            $stmt->execute([$newDebitOld, $oldCompte]);

            $stmt = $bdd->prepare('UPDATE comptes SET debit = ? WHERE id_compte = ?');
            $stmt->execute([$newDebitNew, $newCompte]);

            // Mettre à jour le paiement (compte + montant recalculé)
            $stmt = $bdd->prepare('UPDATE paiements SET compte = ?, montant = ?, montant_paye = ? WHERE id_paiement = ?');
            $stmt->execute([$newCompte, $newMontant, $newMontant, $idPaiement]);

            // Garder cohérent avec l'affectation si présente
            $idAff = (int)($pay['id_affectation'] ?? 0);
            if ($idAff > 0) {
                $stmt = $bdd->prepare('UPDATE affectations SET type_paiement = ?, montant = ? WHERE id_affectation = ?');
                $stmt->execute([$newCompte, $newMontant, $idAff]);
            }

            $bdd->commit();

            $receiptUrl = '';
            if ($idAff > 0) {
                $receiptUrl = 'imprimer_recu.php?affectation=' . urlencode((string)$idAff);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Type de paiement modifié avec succès.',
                'receipt_url' => $receiptUrl,
                'new_montant' => $newMontant,
                'new_montant_label' => number_format($newMontant, 0, ',', ' '),
            ]);
            exit;
        } catch (Throwable $txe) {
            $bdd->rollBack();
            throw $txe;
        }
    } catch (Throwable $e) {
        error_log('[EDITION PAIEMENT ajax_update] ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage() ?: 'Erreur lors de la modification.']);
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
                    <h2>Editer un paiement</h2>
                </header>

                <div class="row">
                    <div class="col-md-12">
                        <section class="card">
                            <div class="card-body">
                                <form id="recherchePaiementForm" class="form-inline" autocomplete="off">
                                    <div class="row align-items-end">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label" for="codePaiement">Numéro de paiement</label>
                                            <input type="text" class="form-control" id="codePaiement" placeholder="Ex: 2025-000123" required>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <button type="submit" class="btn btn-primary" id="btnRechercher">Rechercher</button>
                                        </div>
                                    </div>
                                </form>

                                <div id="pageAlert" class="alert d-none" role="alert"></div>
                            </div>
                        </section>
                    </div>
                </div>

            </section>
        </div>

        <?php include('../PUBLIC/footer.php'); ?>

        <!-- Modal paiement trouvé -->
        <div class="modal fade" id="paiementEditModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Informations du paiement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="modalAlert" class="alert d-none" role="alert"></div>

                        <a id="btnImprimerNouveauRecu" href="#" class="btn btn-info btn-sm d-none mb-3">
                            <i class="fa fa-file-pdf-o"></i> Imprimer le nouveau reçu
                        </a>

                        <div class="mb-3">
                            <div><strong>Numéro :</strong> <span id="mCode">—</span></div>
                            <div><strong>Patient :</strong> <span id="mPatient">—</span></div>
                            <div><strong>Examen :</strong> <span id="mExamen">—</span></div>
                            <div><strong>Date :</strong> <span id="mDate">—</span></div>
                            <div><strong>Montant :</strong> <span id="mMontant">—</span></div>
                            <div><strong>Type actuel :</strong> <span id="mCompteActuel">—</span></div>
                        </div>

                        <form id="editPaiementForm" autocomplete="off">
                            <input type="hidden" name="ajax_update" value="1">
                            <input type="hidden" name="id_paiement" id="mIdPaiement" value="">

                            <div class="mb-3">
                                <label class="form-label" for="mNewCompte">Nouveau type de paiement</label>
                                <select class="form-control" name="new_compte" id="mNewCompte" required>
                                    <option value="">Sélectionner…</option>
                                </select>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <button type="submit" class="btn btn-success" id="btnModifier">Modifier</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const pageAlert = document.getElementById('pageAlert');
            const modalEl = document.getElementById('paiementEditModal');
            const modalAlert = document.getElementById('modalAlert');
            const btnImprimer = document.getElementById('btnImprimerNouveauRecu');

            const rechercheForm = document.getElementById('recherchePaiementForm');
            const codeInput = document.getElementById('codePaiement');
            const btnRechercher = document.getElementById('btnRechercher');

            const mIdPaiement = document.getElementById('mIdPaiement');
            const mCode = document.getElementById('mCode');
            const mPatient = document.getElementById('mPatient');
            const mExamen = document.getElementById('mExamen');
            const mDate = document.getElementById('mDate');
            const mMontant = document.getElementById('mMontant');
            const mCompteActuel = document.getElementById('mCompteActuel');
            const mNewCompte = document.getElementById('mNewCompte');

            const editForm = document.getElementById('editPaiementForm');
            const btnModifier = document.getElementById('btnModifier');

            function showAlert(el, message, kind) {
                if (!el) return;
                el.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info');
                el.classList.add('alert-' + (kind || 'info'));
                el.textContent = message || '';
                el.classList.remove('d-none');
            }

            function hideAlert(el) {
                if (!el) return;
                el.classList.add('d-none');
                el.textContent = '';
            }

            function hidePrintButton() {
                if (!btnImprimer) return;
                btnImprimer.classList.add('d-none');
                btnImprimer.setAttribute('href', '#');
            }

            function setSelectOptions(selectEl, options, placeholder) {
                selectEl.innerHTML = '';
                if (placeholder) {
                    const o = document.createElement('option');
                    o.value = '';
                    o.textContent = placeholder;
                    selectEl.appendChild(o);
                }
                (options || []).forEach(function (it) {
                    const opt = document.createElement('option');
                    opt.value = String(it.id);
                    opt.textContent = String(it.label);
                    selectEl.appendChild(opt);
                });
            }

            async function rechercherPaiement(code) {
                hideAlert(pageAlert);
                hideAlert(modalAlert);
                hidePrintButton();

                btnRechercher.disabled = true;
                const oldText = btnRechercher.textContent;
                btnRechercher.textContent = 'Recherche…';

                try {
                    const url = 'editionpaiement.php?ajax_find=1&code=' + encodeURIComponent(code);
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();

                    if (!data || !data.success) {
                        showAlert(pageAlert, (data && data.message) ? data.message : 'Recherche impossible.', 'danger');
                        return;
                    }

                    const p = data.paiement;
                    mIdPaiement.value = String(p.id_paiement || '');
                    mCode.textContent = p.code || '—';
                    mPatient.textContent = p.patient || '—';
                    mExamen.textContent = p.examen || '—';
                    mDate.textContent = p.date || '—';
                    mMontant.textContent = (p.montant_label || '0') + ' <?php echo htmlspecialchars($devise ?? "", ENT_QUOTES, "UTF-8"); ?>';
                    mCompteActuel.textContent = p.compte_label || (p.compte_id ? ('#' + p.compte_id) : '—');

                    const comptes = (data.options && data.options.comptes) ? data.options.comptes : [];
                    setSelectOptions(mNewCompte, comptes, 'Sélectionner…');
                    if (p.compte_id) {
                        mNewCompte.value = String(p.compte_id);
                    }

                    if (window.bootstrap) {
                        const instance = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
                        instance.show();
                    }
                } catch (e) {
                    showAlert(pageAlert, 'Erreur lors de la recherche.', 'danger');
                } finally {
                    btnRechercher.disabled = false;
                    btnRechercher.textContent = oldText;
                }
            }

            rechercheForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const code = (codeInput.value || '').trim();
                if (!code) {
                    showAlert(pageAlert, 'Veuillez saisir le numéro de paiement.', 'warning');
                    return;
                }
                rechercherPaiement(code);
            });

            editForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                hideAlert(modalAlert);
                hidePrintButton();

                btnModifier.disabled = true;
                const oldText = btnModifier.textContent;
                btnModifier.textContent = 'Modification…';

                try {
                    const fd = new FormData(editForm);
                    const res = await fetch('editionpaiement.php', {
                        method: 'POST',
                        body: fd,
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await res.json();

                    if (!data || !data.success) {
                        showAlert(modalAlert, (data && data.message) ? data.message : 'Modification impossible.', 'danger');
                        btnModifier.disabled = false;
                        btnModifier.textContent = oldText;
                        return;
                    }

                    showAlert(modalAlert, data.message || 'Modifié.', 'success');

                    if (data.receipt_url) {
                        btnImprimer.classList.remove('d-none');
                        btnImprimer.setAttribute('href', data.receipt_url);
                    }

                    // Met à jour l'affichage du montant si fourni
                    if (data.new_montant_label) {
                        mMontant.textContent = (data.new_montant_label || '0') + ' <?php echo htmlspecialchars($devise ?? "", ENT_QUOTES, "UTF-8"); ?>';
                    }

                    // Laisser l'utilisateur imprimer avant de rafraîchir
                    btnModifier.disabled = false;
                    btnModifier.textContent = oldText;
                } catch (err) {
                    showAlert(modalAlert, 'Erreur lors de la modification.', 'danger');
                    btnModifier.disabled = false;
                    btnModifier.textContent = oldText;
                }
            });

            modalEl.addEventListener('hidden.bs.modal', function () {
                hideAlert(modalAlert);
                hidePrintButton();
                mIdPaiement.value = '';
                mCode.textContent = '—';
                mPatient.textContent = '—';
                mExamen.textContent = '—';
                mDate.textContent = '—';
                mMontant.textContent = '—';
                mCompteActuel.textContent = '—';
                mNewCompte.innerHTML = '<option value="">Sélectionner…</option>';
            });
        });
        </script>
    </section>
</body>
