<?php
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['auth'])) {
    header('Location: ../login.php');
    exit;
}

// Devise (fallback)
$devise = 'GNF';
try {
    $stDev = $bdd->query('SELECT devise FROM profil_entreprise LIMIT 1');
    $dev = $stDev ? $stDev->fetchColumn() : null;
    if (is_string($dev) && trim($dev) !== '') {
        $devise = trim($dev);
    }
} catch (Throwable $e) {
    // noop
}

$userId = (int)($_SESSION['auth'] ?? 0);

function adjustCompteDebitAtomic(PDO $bdd, int $compteId, float $delta): void {
    if ($compteId <= 0) return;
    $stmt = $bdd->prepare('UPDATE comptes SET debit = COALESCE(debit,0) + ? WHERE id_compte = ?');
    $stmt->execute([$delta, $compteId]);
}

function resolveUserServiceIdForVente(PDO $bdd, int $userId): int {
    if ($userId <= 0) return 0;
    try {
        $stmt = $bdd->prepare('SELECT id_service FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return (int)($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        error_log('[modifier_vente_lunette] resolveUserServiceIdForVente: ' . $e->getMessage());
        return 0;
    }
}

function resolveVenteLunetteTraitementIdForVente(PDO $bdd): int {
    try {
        $stmt = $bdd->prepare("SELECT id_type FROM traitements WHERE LOWER(nom_type) LIKE '%lunet%' OR LOWER(nom_type) LIKE '%monture%' ORDER BY id_type ASC LIMIT 1");
        $stmt->execute();
        $id = (int)($stmt->fetchColumn() ?: 0);
        if ($id > 0) return $id;
    } catch (Throwable $e) {
        error_log('[modifier_vente_lunette] resolveVenteLunetteTraitementIdForVente(lunet/monture): ' . $e->getMessage());
    }

    try {
        $stmt = $bdd->prepare("SELECT id_type FROM traitements WHERE LOWER(nom_type) LIKE '%vente%' ORDER BY id_type ASC LIMIT 1");
        $stmt->execute();
        return (int)($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        error_log('[modifier_vente_lunette] resolveVenteLunetteTraitementIdForVente(vente): ' . $e->getMessage());
        return 0;
    }
}

function createAffectationForVenteLunette(PDO $bdd, int $patientId, int $userId): int {
    $patientId = (int)$patientId;
    $userId = (int)$userId;
    if ($patientId <= 0 || $userId <= 0) return 0;

    $idService = resolveUserServiceIdForVente($bdd, $userId);
    $idType = resolveVenteLunetteTraitementIdForVente($bdd);
    if ($idService <= 0 || $idType <= 0) return 0;

    try {
        $hasAffecterPar = true;
        if (function_exists('dbTableHasColumn')) {
            $hasAffecterPar = dbTableHasColumn($bdd, 'affectations', 'affecter_par');
        }
        if ($hasAffecterPar) {
            $stmt = $bdd->prepare('INSERT INTO affectations (id_patient, id_service, type, affecter_par, status, montant, taux, type_paiement) VALUES (?, ?, ?, ?, 1, 0, 0, 0)');
            $stmt->execute([$patientId, $idService, $idType, $userId]);
        } else {
            $stmt = $bdd->prepare('INSERT INTO affectations (id_patient, id_service, type, status, montant, taux, type_paiement) VALUES (?, ?, ?, 1, 0, 0, 0)');
            $stmt->execute([$patientId, $idService, $idType]);
        }
        return (int)$bdd->lastInsertId();
    } catch (Throwable $e) {
        error_log('[modifier_vente_lunette] createAffectationForVenteLunette: ' . $e->getMessage());
        return 0;
    }
}

$hasVpAff = true;
if (function_exists('dbTableHasColumn')) {
    $hasVpAff = dbTableHasColumn($bdd, 'ventes_produits', 'id_affectation');
}

$hasVpDelivre = false;
$hasVpDateDelivrance = false;
if (function_exists('dbTableHasColumn')) {
    $hasVpDelivre = dbTableHasColumn($bdd, 'ventes_produits', 'delivre');
    $hasVpDateDelivrance = dbTableHasColumn($bdd, 'ventes_produits', 'date_delivrance');
}

$hasVpDateVente = false;
if (function_exists('dbTableHasColumn')) {
    $hasVpDateVente = dbTableHasColumn($bdd, 'ventes_produits', 'date_vente');
}

$errors = '';
$success = '';

$idVente = isset($_GET['id_vente']) ? (int)$_GET['id_vente'] : 0;
$codeSearch = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
$dateDebut = isset($_GET['date_debut']) ? trim((string)$_GET['date_debut']) : '';
$dateFin = isset($_GET['date_fin']) ? trim((string)$_GET['date_fin']) : '';

// Filtre période: valider format YYYY-MM-DD
$dateDebutSql = '';
$dateFinSql = '';
if ($dateDebut !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $dateDebut);
    $ok = $dt && $dt->format('Y-m-d') === $dateDebut;
    if ($ok) {
        $dateDebutSql = $dateDebut;
    } else {
        $dateDebut = '';
    }
}
if ($dateFin !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $dateFin);
    $ok = $dt && $dt->format('Y-m-d') === $dateFin;
    if ($ok) {
        $dateFinSql = $dateFin;
    } else {
        $dateFin = '';
    }
}

$hasAffDateTraitement = false;
if (function_exists('dbTableHasColumn')) {
    $hasAffDateTraitement = dbTableHasColumn($bdd, 'affectations', 'datetraitement');
}

// Liste lentilles pour le select (on la charge une fois)
$lentillesOptions = [];
try {
    $stL = $bdd->prepare('SELECT id_lentille, lentille, prix_vente, quantite FROM lentilles WHERE status = ? ORDER BY lentille');
    $stL->execute([1]);
    while ($l = $stL->fetch(PDO::FETCH_ASSOC)) {
        $lentillesOptions[] = [
            'id' => (int)($l['id_lentille'] ?? 0),
            'label' => (string)($l['lentille'] ?? ''),
            'prix' => (float)($l['prix_vente'] ?? 0),
            'qte' => (int)($l['quantite'] ?? 0),
        ];
    }
} catch (Throwable $e) {
    $lentillesOptions = [];
}

// Liste comptes (mode de règlement) pour la boutique
$comptesOptions = [];
try {
    $stC = $bdd->prepare(
        'SELECT c.id_compte, COALESCE(NULLIF(c.nom_compte,\'\'), c.types) AS label '
        . 'FROM comptes c '
        . 'WHERE c.status = 1 AND c.defaut = 1 AND c.compte_pour = ? '
        . 'AND NOT EXISTS (SELECT 1 FROM preuvedecaisse p WHERE p.date_rapportement = ? AND p.id_user = ? AND p.compte = c.id_compte) '
        . 'ORDER BY label'
    );
    $stC->execute([2, date('Y-m-d'), $userId]);
    while ($c = $stC->fetch(PDO::FETCH_ASSOC)) {
        $comptesOptions[] = [
            'id' => (int)($c['id_compte'] ?? 0),
            'label' => (string)($c['label'] ?? ''),
        ];
    }
} catch (Throwable $e) {
    $comptesOptions = [];
}

// Charger une vente lunette par id_vente
$vente = null;
$paiementMeta = null; // infos paiement pour l'UI (si affectation)
if ($idVente > 0) {
    try {
        $selectDate = $hasVpDateVente ? ', vp.date_vente' : '';
        $selectDel = '';
        if ($hasVpDelivre) $selectDel .= ', vp.delivre';
        if ($hasVpDateDelivrance) $selectDel .= ', vp.date_delivrance';

        $stmt = $bdd->prepare(
            'SELECT vp.id_vente, vp.id_monture, vp.id_lentille, vp.id_patient, vp.compte, vp.prix_monture, vp.prix_verre'
            . ($hasVpAff ? ', vp.id_affectation' : '')
            . $selectDate
            . $selectDel
            . ', m.code_monture, m.prix AS monture_prix, m.vendu, m.id_marque'
            . ', l.lentille AS lentille_nom, l.prix_vente AS lentille_prix'
            . ', p.nom_patient'
            . ' FROM ventes_produits vp'
            . ' LEFT JOIN montures m ON m.id_monture = vp.id_monture'
            . ' LEFT JOIN lentilles l ON l.id_lentille = vp.id_lentille'
            . ' LEFT JOIN patients p ON p.id_patient = vp.id_patient'
            . ' WHERE vp.id_vente = ? LIMIT 1'
        );
        $stmt->execute([$idVente]);
        $vente = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($vente && (int)($vente['id_monture'] ?? 0) <= 0) {
            $errors = 'Cette vente ne correspond pas à une vente de lunettes (monture manquante).';
            $vente = null;
        }

        // Charger infos paiement (pour cinématique total/partiel)
        if ($vente && $hasVpAff) {
            $idAffV = (int)($vente['id_affectation'] ?? 0);
            if ($idAffV > 0) {
                try {
                    $stP = $bdd->prepare('SELECT COUNT(*) AS cnt, COALESCE(SUM(COALESCE(montant_paye,0)),0) AS sum_paye FROM paiements WHERE id_affectation = ?');
                    $stP->execute([$idAffV]);
                    $agg = $stP->fetch(PDO::FETCH_ASSOC) ?: null;

                    $stOne = $bdd->prepare('SELECT id_paiement, montant, montant_paye, compte FROM paiements WHERE id_affectation = ? ORDER BY id_paiement ASC LIMIT 1');
                    $stOne->execute([$idAffV]);
                    $p0 = $stOne->fetch(PDO::FETCH_ASSOC) ?: null;

                    $paiementMeta = [
                        'id_affectation' => $idAffV,
                        'count' => (int)($agg['cnt'] ?? 0),
                        'sum_paye' => (float)($agg['sum_paye'] ?? 0),
                        'id_paiement' => (int)($p0['id_paiement'] ?? 0),
                        'montant' => (float)($p0['montant'] ?? 0),
                        'montant_paye' => (float)($p0['montant_paye'] ?? 0),
                        'compte' => (int)($p0['compte'] ?? 0),
                    ];
                } catch (Throwable $e) {
                    $paiementMeta = null;
                }
            }
        }
    } catch (Throwable $e) {
        $errors = 'Erreur lors du chargement de la vente.';
        error_log('[modifier_vente_lunette] load vente: ' . $e->getMessage());
        $vente = null;
    }
}

// Traitement modification
if ($vente && isset($_POST['update_vente'])) {
    $newLentilleId = isset($_POST['id_lentille']) ? (int)$_POST['id_lentille'] : 0;
    $newCompte = isset($_POST['compte']) ? (int)$_POST['compte'] : 0;
    $modePaiement = isset($_POST['mode_paiement']) ? (int)$_POST['mode_paiement'] : 0; // 0=total, 1=partiel
    $acompteN = str_replace(' ', '', (string)($_POST['acompte'] ?? '0'));
    $acompteN = str_replace(',', '.', $acompteN);
    $acompte = (float)$acompteN;

    if ($newLentilleId <= 0 || $newCompte <= 0) {
        $errors = 'Veuillez sélectionner une lentille et un compte de règlement valides.';
    } else {
        try {
            $bdd->beginTransaction();
            try {
                // Recharger et verrouiller la vente
                $selectDel = '';
                if ($hasVpDelivre) $selectDel .= ', delivre';
                if ($hasVpDateDelivrance) $selectDel .= ', date_delivrance';

                $lockSql = 'SELECT id_vente, id_monture, id_lentille, id_patient, compte, prix_monture, prix_verre'
                    . ($hasVpAff ? ', id_affectation' : '')
                    . $selectDel
                    . ' FROM ventes_produits WHERE id_vente = ? LIMIT 1 FOR UPDATE';
                $stLock = $bdd->prepare($lockSql);
                $stLock->execute([$idVente]);
                $vp = $stLock->fetch(PDO::FETCH_ASSOC);
                if (!$vp) {
                    throw new Exception('Vente introuvable.');
                }

                $oldLentilleId = (int)($vp['id_lentille'] ?? 0);
                $idMonture = (int)($vp['id_monture'] ?? 0);
                $oldCompte = (int)($vp['compte'] ?? 0);
                if ($idMonture <= 0) {
                    throw new Exception('Monture invalide pour cette vente.');
                }
                if ($oldCompte <= 0) {
                    throw new Exception('Compte de règlement invalide pour cette vente.');
                }

                // Interdire modification si déjà délivrée
                if ($hasVpDelivre && isset($vp['delivre']) && (int)$vp['delivre'] === 1) {
                    throw new Exception('Cette vente est déjà délivrée. Modification interdite.');
                }
                if ($hasVpDateDelivrance && !empty($vp['date_delivrance'])) {
                    throw new Exception('Cette vente est déjà délivrée. Modification interdite.');
                }

                $lentilleChanged = ($newLentilleId !== $oldLentilleId);
                $compteChanged = ($newCompte !== $oldCompte);
                $paiementChanged = false; // sera évalué plus loin (si affectation + paiement unique)

                // Récupérer id_affectation si disponible (nécessaire pour changement de compte et/ou mise à jour comptable)
                $idAff = 0;
                if ($hasVpAff) {
                    $idAff = (int)($vp['id_affectation'] ?? 0);
                }

                $affectationRepaired = false;
                $affectationRepairedOldId = 0;

                // Si la vente n'a pas d'affectation, on peut en créer une automatiquement (utile pour mettre à jour le montant à payer)
                // On ne le fait que si la colonne existe (sinon impossible de relier) et que l'utilisateur est connecté.
                if ($hasVpAff && $idAff <= 0) {
                    $newAff = createAffectationForVenteLunette($bdd, (int)($vp['id_patient'] ?? 0), $userId);
                    if ($newAff > 0) {
                        $bdd->prepare('UPDATE ventes_produits SET id_affectation = ? WHERE id_vente = ?')
                            ->execute([(int)$newAff, (int)$idVente]);
                        $idAff = (int)$newAff;
                        $affectationRepaired = true;
                        $affectationRepairedOldId = 0;
                    }
                }

                // Verrouiller monture
                $stM = $bdd->prepare('SELECT id_monture, id_lentille FROM montures WHERE id_monture = ? LIMIT 1 FOR UPDATE');
                $stM->execute([$idMonture]);
                $m = $stM->fetch(PDO::FETCH_ASSOC);
                if (!$m) {
                    throw new Exception('Monture introuvable.');
                }

                // Prix lentille (nouvelle ou actuelle)
                $newPrixVerre = (float)($vp['prix_verre'] ?? 0);
                if ($lentilleChanged) {
                    // Vérifier disponibilités lentilles
                    $stNew = $bdd->prepare('SELECT id_lentille, prix_vente, quantite FROM lentilles WHERE id_lentille = ? AND status = 1 LIMIT 1 FOR UPDATE');
                    $stNew->execute([$newLentilleId]);
                    $newL = $stNew->fetch(PDO::FETCH_ASSOC);
                    if (!$newL) {
                        throw new Exception('Lentille invalide.');
                    }
                    $newQte = (int)($newL['quantite'] ?? 0);
                    if ($newQte <= 0) {
                        throw new Exception('Cette lentille est en rupture de stock.');
                    }
                    $newPrixVerre = (float)($newL['prix_vente'] ?? 0);
                }

                // Lire l'ancienne lentille (pour stock + prix)
                $oldPrixVerre = (float)($vp['prix_verre'] ?? 0);
                if ($oldPrixVerre <= 0 && $oldLentilleId > 0) {
                    $stOld = $bdd->prepare('SELECT prix_vente FROM lentilles WHERE id_lentille = ? LIMIT 1');
                    $stOld->execute([$oldLentilleId]);
                    $oldPrixVerre = (float)($stOld->fetchColumn() ?: 0);
                }

                $prixMontureVente = (float)($vp['prix_monture'] ?? 0);
                $baseNew = $prixMontureVente + $newPrixVerre;

                // Si changement de compte: on exige une affectation valide (sinon on ne peut pas garantir la cohérence comptable)
                if ($compteChanged) {
                    if (!$hasVpAff) {
                        throw new Exception('Impossible de modifier le règlement: schéma ventes_produits sans id_affectation.');
                    }
                    if ($idAff <= 0) {
                        throw new Exception('Impossible de modifier le règlement: affectation manquante.');
                    }
                }

                $accountingUpdated = false;
                // Si la vente est rattachée à une affectation, tentative d'ajustement comptable (sinon, on laisse la vente modifiable côté stock)
                if ($hasVpAff && $idAff > 0) {
                    // Verrouiller affectation
                    $stAff = $bdd->prepare('SELECT id_affectation, montant, type_paiement FROM affectations WHERE id_affectation = ? LIMIT 1 FOR UPDATE');
                    $stAff->execute([$idAff]);
                    $aff = $stAff->fetch(PDO::FETCH_ASSOC);
                    if (!$aff) {
                        if ($compteChanged) {
                            throw new Exception('Affectation introuvable pour cette vente.');
                        }
                        // Tentative de réparation: l'affectation n'existe plus, on en recrée une et on bascule les paiements.
                        $affectationRepairedOldId = (int)$idAff;
                        $newAff = createAffectationForVenteLunette($bdd, (int)($vp['id_patient'] ?? 0), $userId);
                        if ($newAff > 0) {
                            // Basculer toutes les lignes liées à l'ancienne affectation vers la nouvelle
                            $bdd->prepare('UPDATE ventes_produits SET id_affectation = ? WHERE id_affectation = ?')
                                ->execute([(int)$newAff, (int)$affectationRepairedOldId]);
                            $bdd->prepare('UPDATE paiements SET id_affectation = ? WHERE id_affectation = ?')
                                ->execute([(int)$newAff, (int)$affectationRepairedOldId]);
                            $idAff = (int)$newAff;
                            $affectationRepaired = true;

                            // Re-verrouiller la nouvelle affectation
                            $stAff = $bdd->prepare('SELECT id_affectation, montant, type_paiement FROM affectations WHERE id_affectation = ? LIMIT 1 FOR UPDATE');
                            $stAff->execute([$idAff]);
                            $aff = $stAff->fetch(PDO::FETCH_ASSOC);
                        }

                        // Si la réparation échoue, on continue sans mise à jour comptable
                        if (!$aff) {
                            $aff = null;
                        }
                    }

                    // Verrouiller paiements (après éventuelle réparation)
                    $stPays = $bdd->prepare('SELECT id_paiement, montant, montant_paye, compte FROM paiements WHERE id_affectation = ? ORDER BY id_paiement ASC FOR UPDATE');
                    $stPays->execute([$idAff]);
                    $payments = $stPays->fetchAll(PDO::FETCH_ASSOC);

                    $countPays = count($payments);
                    $sumPaye = 0.0;
                    foreach ($payments as $p) {
                        $sumPaye += (float)($p['montant_paye'] ?? 0);
                    }

                    $epsilon = 0.00001;
                    $canAdjustMoney = false;
                    $oldMontantPayeOne = 0.0;
                    $comptePaiement = 0;
                    $paiementIdToUpdate = 0;

                    $isSinglePayment = ($countPays === 1);
                    $isSinglePaymentEditable = ($countPays === 1); // on n'édite le mode paiement que si une seule ligne

                    if ($countPays === 1) {
                        $p0 = $payments[0];
                        $oldMontantPayeOne = (float)($p0['montant_paye'] ?? 0);
                        $oldMontantOne = (float)($p0['montant'] ?? 0);
                        $paiementIdToUpdate = (int)($p0['id_paiement'] ?? 0);
                        $comptePaiement = (int)($p0['compte'] ?? 0);
                        if (abs($oldMontantPayeOne - $oldMontantOne) <= $epsilon) {
                            $canAdjustMoney = true;
                        }
                    }

                    // --- Calcul du total (avec éventuels frais mobile) ---
                    // Par défaut, on calcule les frais selon le compte cible (si on change de compte) sinon l'ancien.
                    $feeCompteId = $compteChanged ? $newCompte : ($comptePaiement > 0 ? $comptePaiement : $oldCompte);
                    $feeNew = 0.0;
                    $feeBase = $baseNew; // par défaut, frais calculés sur le total

                    // Cinématique paiement (comme la vente): si une seule ligne de paiement, on permet total/partiel
                    $newMontantPayeFinal = null;
                    if ($aff && $isSinglePaymentEditable) {
                        if ($modePaiement !== 0 && $modePaiement !== 1) {
                            throw new Exception('Mode de paiement invalide.');
                        }
                        if ($modePaiement === 1) {
                            if ($acompte <= 0) {
                                throw new Exception('Veuillez renseigner un acompte valide.');
                            }
                            if ($acompte - $baseNew > $epsilon) {
                                throw new Exception('L\'acompte ne peut pas dépasser le montant total.');
                            }
                            $feeBase = $acompte;
                        } else {
                            $feeBase = $baseNew;
                        }
                    }

                    $isMobileNew = (function_exists('IsPaiementElectronique') && $feeCompteId > 0 && IsPaiementElectronique($feeCompteId) === 1);
                    $tauxCompte = 0.0;
                    if ($isMobileNew && $feeCompteId > 0) {
                        $stT = $bdd->prepare('SELECT taux FROM comptes WHERE id_compte = ? LIMIT 1 FOR UPDATE');
                        $stT->execute([$feeCompteId]);
                        $tauxCompte = (float)($stT->fetchColumn() ?: 0);
                        if ($tauxCompte < 0) $tauxCompte = 0;
                        if ($tauxCompte > 100) $tauxCompte = 100;
                        if ($tauxCompte > 0) {
                            $feeNew = ($feeBase * $tauxCompte / 100);
                        }
                    }
                    $newTotalFinal = $baseNew + $feeNew;

                    if ($aff && $isSinglePaymentEditable) {
                        $paidBase = ($modePaiement === 1) ? $acompte : $baseNew;
                        $newMontantPayeFinal = $paidBase + $feeNew;
                    }

                    if ($aff) {
                        // --- Règles de modification selon état des paiements ---
                        // 1) Changement de compte: autorisé uniquement si paiement unique (sinon ambigu)
                        if ($compteChanged && !$isSinglePaymentEditable) {
                            throw new Exception('Impossible de changer le compte: la vente a plusieurs paiements.');
                        }

                        // 2) Si vente totalement réglée via plusieurs paiements, interdire toute modification qui change le total
                        if (!$canAdjustMoney && $countPays > 1) {
                            // On estime "soldé" si la somme payée est >= montant actuel de l'affectation
                            $oldMontantAff = (float)($aff['montant'] ?? 0);
                            if ($oldMontantAff > 0 && ($sumPaye + $epsilon) >= $oldMontantAff) {
                                if (abs($newTotalFinal - $sumPaye) > $epsilon) {
                                    throw new Exception('Vente déjà soldée (plusieurs paiements): modification du montant interdite.');
                                }
                            }
                        }

                        // Interdire si preuve de caisse déjà effectuée aujourd'hui sur les comptes concernés (pour ce caissier)
                        $comptesToCheck = [];
                        if ($compteChanged && $comptePaiement > 0) $comptesToCheck[] = $comptePaiement;
                        if ($newCompte > 0) $comptesToCheck[] = $newCompte;
                        $comptesToCheck = array_values(array_unique(array_filter($comptesToCheck)));
                        foreach ($comptesToCheck as $cid) {
                            $stClosed = $bdd->prepare('SELECT 1 FROM preuvedecaisse WHERE date_rapportement = ? AND id_user = ? AND compte = ? LIMIT 1');
                            $stClosed->execute([date('Y-m-d'), $userId, (int)$cid]);
                            if ($stClosed->fetchColumn()) {
                                throw new Exception("Compte clôturé aujourd'hui (preuve de caisse). Modification interdite.");
                            }
                        }

                        // --- Mise à jour affectation / paiements / comptes ---
                        // Déterminer si le paiement change (mode total/partiel + acompte)
                        if ($isSinglePaymentEditable && $aff && $newMontantPayeFinal !== null && $countPays === 1) {
                            $oldMode = ($oldMontantOne > 0 && ($oldMontantPayeOne + $epsilon) < $oldMontantOne) ? 1 : 0;
                            $paiementChanged = ($oldMode !== $modePaiement)
                                || (abs((float)$newMontantPayeFinal - (float)$oldMontantPayeOne) > $epsilon)
                                || (abs((float)$newTotalFinal - (float)$oldMontantOne) > $epsilon);
                        }

                        if (!$lentilleChanged && !$compteChanged && !$paiementChanged && !$affectationRepaired) {
                            throw new Exception('Aucune modification détectée.');
                        }

                        // Toujours: mettre à jour le total à payer (affectation.montant) + paiements.montant (sans toucher montant_paye)
                        $bdd->prepare('UPDATE affectations SET montant = ? WHERE id_affectation = ?')
                            ->execute([$newTotalFinal, $idAff]);
                        $bdd->prepare('UPDATE paiements SET montant = ? WHERE id_affectation = ?')
                            ->execute([$newTotalFinal, $idAff]);

                        // Mettre à jour le compte affiché côté vente/affectation
                        $bdd->prepare('UPDATE ventes_produits SET compte = ? WHERE id_vente = ?')
                            ->execute([$newCompte, $idVente]);
                        $bdd->prepare('UPDATE affectations SET type_paiement = ? WHERE id_affectation = ?')
                            ->execute([$newCompte, $idAff]);

                        // Cinématique paiement: si une seule ligne, on peut fixer total/partiel (montant_paye) et ajuster le débit
                        if ($isSinglePaymentEditable && $paiementIdToUpdate > 0 && $newMontantPayeFinal !== null) {
                            $bdd->prepare('UPDATE affectations SET status = 4 WHERE id_affectation = ?')
                                ->execute([$idAff]);
                            $bdd->prepare('UPDATE paiements SET montant_paye = ?, compte = ? WHERE id_paiement = ?')
                                ->execute([(float)$newMontantPayeFinal, (int)$newCompte, (int)$paiementIdToUpdate]);

                            if ($comptePaiement > 0 && $newCompte > 0) {
                                if ($newCompte !== $comptePaiement) {
                                    adjustCompteDebitAtomic($bdd, $comptePaiement, -1.0 * $oldMontantPayeOne);
                                    adjustCompteDebitAtomic($bdd, $newCompte, (float)$newMontantPayeFinal);
                                } else {
                                    $deltaDebit = (float)$newMontantPayeFinal - $oldMontantPayeOne;
                                    if (abs($deltaDebit) > $epsilon) {
                                        adjustCompteDebitAtomic($bdd, $comptePaiement, $deltaDebit);
                                    }
                                }
                            }
                        }

                        $accountingUpdated = true;
                    }
                }

                // Si pas d'affectation exploitable, on ne peut détecter un changement paiement: on valide uniquement lentille/compte/réparation.
                if (!$lentilleChanged && !$compteChanged && !$paiementChanged && !$affectationRepaired) {
                    throw new Exception('Aucune modification détectée.');
                }

                // Ajuster stock lentilles (ancienne +1, nouvelle -1)
                if ($lentilleChanged) {
                    if ($oldLentilleId > 0) {
                        $bdd->prepare('UPDATE lentilles SET quantite = quantite + 1 WHERE id_lentille = ?')
                            ->execute([$oldLentilleId]);
                    }
                    $bdd->prepare('UPDATE lentilles SET quantite = CASE WHEN quantite > 0 THEN quantite - 1 ELSE 0 END WHERE id_lentille = ?')
                        ->execute([$newLentilleId]);
                }

                // Mettre à jour la vente + la monture
                if ($lentilleChanged) {
                    $bdd->prepare('UPDATE ventes_produits SET id_lentille = ?, prix_verre = ? WHERE id_vente = ?')
                        ->execute([$newLentilleId, $newPrixVerre, $idVente]);
                    $bdd->prepare('UPDATE montures SET id_lentille = ?, date_modification = CURRENT_TIMESTAMP WHERE id_monture = ?')
                        ->execute([$newLentilleId, $idMonture]);
                }

                $bdd->commit();

                if ($accountingUpdated) {
                    $_SESSION['flash_modifier_vente_success'] = $affectationRepaired
                        ? 'Vente modifiée avec succès (affectation réparée automatiquement).'
                        : 'Vente modifiée avec succès.';
                } else {
                    $_SESSION['flash_modifier_vente_success'] = 'Vente modifiée avec succès (affectation introuvable: montant à payer non mis à jour).';
                }
                header('Location: modifier_vente_lunette.php?id_vente=' . (int)$idVente);
                exit;
            } catch (Throwable $txe) {
                $bdd->rollBack();
                throw $txe;
            }
        } catch (Throwable $e) {
            $errors = $e->getMessage();
            error_log('[modifier_vente_lunette] update: ' . $e->getMessage());
        }
    }
}

// Flash success
if (!empty($_SESSION['flash_modifier_vente_success'])) {
    $success = (string)$_SESSION['flash_modifier_vente_success'];
    unset($_SESSION['flash_modifier_vente_success']);
}

// Charger liste des ventes (si pas d'id_vente)
$ventesList = [];
if ($idVente <= 0) {
    try {
        $where = 'WHERE vp.id_monture > 0';
        $params = [];
        if ($codeSearch !== '') {
            $where .= ' AND m.code_monture = ?';
            $params[] = $codeSearch;
        }

        // Filtre période: prioriser vp.date_vente, sinon fallback sur affectations.datetraitement
        $dateExpr = '';
        $joinAff = '';
        $selectDate = '';
        if ($hasVpDateVente) {
            $selectDate = ', vp.date_vente';
            $dateExpr = 'DATE(vp.date_vente)';
        } elseif ($hasVpAff && $hasAffDateTraitement) {
            $joinAff = ' LEFT JOIN affectations a ON a.id_affectation = vp.id_affectation ';
            $selectDate = ', a.datetraitement AS date_vente';
            $dateExpr = 'DATE(a.datetraitement)';
        }

        if ($dateExpr !== '' && $dateDebutSql !== '') {
            $where .= ' AND ' . $dateExpr . ' >= ?';
            $params[] = $dateDebutSql;
        }
        if ($dateExpr !== '' && $dateFinSql !== '') {
            $where .= ' AND ' . $dateExpr . ' <= ?';
            $params[] = $dateFinSql;
        }

        $hasAnyFilter = ($codeSearch !== '' || $dateDebutSql !== '' || $dateFinSql !== '');
        $limit = $hasAnyFilter ? 50 : 5;

        $sql =
            'SELECT vp.id_vente, vp.id_monture, vp.id_lentille, vp.id_patient, vp.prix_monture, vp.prix_verre'
            . ($hasVpAff ? ', vp.id_affectation' : '')
            . $selectDate
            . ', m.code_monture'
            . ', l.lentille AS lentille_nom'
            . ', p.nom_patient'
            . ' FROM ventes_produits vp'
            . ' LEFT JOIN montures m ON m.id_monture = vp.id_monture'
            . $joinAff
            . ' LEFT JOIN lentilles l ON l.id_lentille = vp.id_lentille'
            . ' LEFT JOIN patients p ON p.id_patient = vp.id_patient '
            . $where
            . ' ORDER BY vp.id_vente DESC LIMIT ' . (int)$limit;

        $st = $bdd->prepare($sql);
        $st->execute($params);
        $ventesList = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $ventesList = [];
        error_log('[modifier_vente_lunette] load list: ' . $e->getMessage());
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
                <h2>Modifier une vente de lunettes</h2>
            </header>

            <div class="col-md-12">
                <section class="card">
                    <div class="card-body">

                        <?php if ($success !== ''): ?>
                            <div class="alert alert-success">
                                <?php echo h($success); ?>
                                <?php if ($vente && $hasVpAff && (int)($vente['id_affectation'] ?? 0) > 0): ?>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-success" id="btnImprimerRecu" data-affectation="<?php echo (int)($vente['id_affectation'] ?? 0); ?>">
                                            <i class="fa fa-file-pdf-o"></i> Imprimer le reçu
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($errors !== ''): ?>
                            <div class="alert alert-danger">
                                <?php echo h($errors); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($vente): ?>
                            <?php
                                $codeMonture = (string)($vente['code_monture'] ?? '');
                                $patientNom = (string)($vente['nom_patient'] ?? '');
                                $lentilleNom = (string)($vente['lentille_nom'] ?? '');
                                $prixMonture = (float)($vente['prix_monture'] ?? 0);
                                $prixVerre = (float)($vente['prix_verre'] ?? 0);
                                $montantTotal = $prixMonture + $prixVerre;

                                $isDelivre = false;
                                if ($hasVpDelivre && isset($vente['delivre']) && (int)$vente['delivre'] === 1) $isDelivre = true;
                                if ($hasVpDateDelivrance && !empty($vente['date_delivrance'])) $isDelivre = true;
                            ?>

                            <div class="table-responsive mb-3">
                                <table class="table table-bordered table-striped mb-0">
                                    <tbody>
                                        <tr><th style="width:30%">N° Vente</th><td><?php echo 'ECV' . h($vente['id_vente'] ?? ''); ?></td></tr>
                                        <tr><th>Patient</th><td><?php echo h($patientNom !== '' ? $patientNom : '-'); ?></td></tr>
                                        <tr><th>Code monture</th><td><?php echo h($codeMonture !== '' ? $codeMonture : '-'); ?></td></tr>
                                        <tr><th>Lentille actuelle</th><td><?php echo h($lentilleNom !== '' ? $lentilleNom : '-'); ?></td></tr>
                                        <tr><th>Prix monture</th><td><?php echo h(number_format($prixMonture, 0, ',', ' ')) . ' ' . h($devise); ?></td></tr>
                                        <tr><th>Prix lentille</th><td><span id="prixLentilleDisplay"><?php echo h(number_format($prixVerre, 0, ',', ' ')) . ' ' . h($devise); ?></span></td></tr>
                                        <tr><th>Montant</th><td class="fw-bold text-success"><span id="montantDisplay"><?php echo h(number_format($montantTotal, 0, ',', ' ')) . ' ' . h($devise); ?></span></td></tr>
                                        <tr><th>Statut</th><td>
                                            <?php if ($isDelivre): ?>
                                                <span class="badge bg-success">Délivrée</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Non délivrée</span>
                                            <?php endif; ?>
                                        </td></tr>
                                    </tbody>
                                </table>
                            </div>

                            <form method="POST" action="modifier_vente_lunette.php?id_vente=<?php echo (int)$idVente; ?>" novalidate="novalidate">
                                <input type="hidden" name="update_vente" value="1">
                                <input type="hidden" id="prixMontureValue" value="<?php echo h((string)$prixMonture); ?>">
                                <input type="hidden" id="deviseValue" value="<?php echo h($devise); ?>">

                                <div class="row form-group pb-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="id_lentille">Nouvelle lentille</label>
                                            <select class="form-control" name="id_lentille" id="id_lentille" <?php echo $isDelivre ? 'disabled' : 'required'; ?> >
                                                <option value="">--- Choisir les verres ---</option>
                                                <?php foreach ($lentillesOptions as $opt): ?>
                                                    <?php
                                                        $oid = (int)($opt['id'] ?? 0);
                                                        $selected = ($oid > 0 && $oid === (int)($vente['id_lentille'] ?? 0)) ? 'selected' : '';
                                                        $disabled = ((int)($opt['qte'] ?? 0) <= 0 && $selected === '') ? 'disabled' : '';
                                                        $label = (string)($opt['label'] ?? '');
                                                        $prix = (float)($opt['prix'] ?? 0);
                                                    ?>
                                                    <option value="<?php echo (int)$oid; ?>" data-prix="<?php echo h((string)$prix); ?>" <?php echo $selected; ?> <?php echo $disabled; ?>>
                                                        <?php echo h($label); ?> (<?php echo h(number_format($prix, 0, ',', ' ')); ?> <?php echo h($devise); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if ($isDelivre): ?>
                                                <small class="text-muted">Modification désactivée: vente déjà délivrée.</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="compte">Compte de règlement</label>
                                            <select class="form-control" name="compte" id="compte" <?php echo $isDelivre ? 'disabled' : 'required'; ?> >
                                                <option value="">--- Choisir un compte ---</option>
                                                <?php
                                                    $currentCompte = (int)($vente['compte'] ?? 0);
                                                    $seen = [];
                                                    foreach ($comptesOptions as $copt) {
                                                        $cid = (int)($copt['id'] ?? 0);
                                                        if ($cid <= 0) continue;
                                                        $seen[$cid] = true;
                                                        $sel = ($cid === $currentCompte) ? 'selected' : '';
                                                        echo '<option value="' . (int)$cid . '" ' . $sel . '>' . h($copt['label'] ?? '') . '</option>';
                                                    }
                                                    if ($currentCompte > 0 && empty($seen[$currentCompte])) {
                                                        // Afficher le compte courant même s'il n'est plus "disponible" (clôturé / non defaut)
                                                        try {
                                                            $stOne = $bdd->prepare('SELECT COALESCE(NULLIF(nom_compte,\'\'), types) AS label FROM comptes WHERE id_compte = ? LIMIT 1');
                                                            $stOne->execute([$currentCompte]);
                                                            $lbl = (string)($stOne->fetchColumn() ?: 'Compte #' . $currentCompte);
                                                        } catch (Throwable $e) {
                                                            $lbl = 'Compte #' . $currentCompte;
                                                        }
                                                        echo '<option value="' . (int)$currentCompte . '" selected>' . h($lbl) . '</option>';
                                                    }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <?php
                                    $paiementEditableUI = false;
                                    $modePaiementDef = 0;
                                    $acompteDef = '';
                                    if ($paiementMeta && (int)($paiementMeta['count'] ?? 0) === 1) {
                                        $paiementEditableUI = true;
                                        $m = (float)($paiementMeta['montant'] ?? 0);
                                        $mp = (float)($paiementMeta['montant_paye'] ?? 0);
                                        if ($m > 0 && $mp + 0.00001 < $m) {
                                            $modePaiementDef = 1;
                                            $acompteDef = (string)$mp;
                                        } else {
                                            $modePaiementDef = 0;
                                            $acompteDef = '';
                                        }
                                    }
                                ?>

                                <div class="row form-group pb-3">
                                    <div class="col-md-4">
                                        <label class="col-form-label d-block">Mode de paiement</label>
                                        <?php if ($paiementEditableUI && !$isDelivre): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="mode_paiement" id="mode_total" value="0" <?php echo ($modePaiementDef === 0 ? 'checked' : ''); ?>>
                                                <label class="form-check-label" for="mode_total">Paiement total</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="mode_paiement" id="mode_partiel" value="1" <?php echo ($modePaiementDef === 1 ? 'checked' : ''); ?>>
                                                <label class="form-check-label" for="mode_partiel">Paiement partiel</label>
                                            </div>
                                            <small class="text-muted">Disponible uniquement si la vente a un seul paiement.</small>
                                        <?php else: ?>
                                            <div class="text-muted">Mode de paiement non modifiable (vente avec plusieurs paiements ou affectation manquante).</div>
                                            <input type="hidden" name="mode_paiement" value="0">
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="acompte">Montant avance</label>
                                            <input type="text" class="form-control" name="acompte" id="acompte" value="<?php echo h($acompteDef); ?>" placeholder="Ex: 50000" <?php echo (!$paiementEditableUI || $isDelivre) ? 'disabled' : ''; ?>>
                                        </div>
                                    </div>
                                </div>

                                <footer class="card-footer text-end">
                                    <a class="btn btn-light" href="modifier_vente_lunette.php">Retour</a>
                                    <button class="btn btn-primary" type="submit" <?php echo $isDelivre ? 'disabled' : ''; ?>>Enregistrer</button>
                                </footer>
                            </form>

                        <?php else: ?>

                            <form method="GET" action="modifier_vente_lunette.php" class="mb-3">
                                <div class="row form-group pb-3">
                                    <div class="col-md-4">
                                        <label class="col-form-label" for="code">Code monture</label>
                                        <input type="text" class="form-control" name="code" id="code" value="<?php echo h($codeSearch); ?>" placeholder="Ex: MT-0001">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="col-form-label" for="date_debut">Date début</label>
                                        <input type="date" class="form-control" name="date_debut" id="date_debut" value="<?php echo h($dateDebut); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="col-form-label" for="date_fin">Date fin</label>
                                        <input type="date" class="form-control" name="date_fin" id="date_fin" value="<?php echo h($dateFin); ?>">
                                    </div>
                                </div>
                                <footer class="card-footer text-end">
                                    <button class="btn btn-primary" type="submit">Rechercher</button>
                                </footer>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>N° Vente</th>
                                            <?php if ($hasVpDateVente || ($hasVpAff && $hasAffDateTraitement)): ?>
                                                <th>Date</th>
                                            <?php endif; ?>
                                            <th>Monture</th>
                                            <th>Lentille</th>
                                            <th>Client</th>
                                            <th>Montant</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($ventesList)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">Aucune vente trouvée.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($ventesList as $row): ?>
                                                <?php
                                                    $id = (int)($row['id_vente'] ?? 0);
                                                    $dateV = (string)($row['date_vente'] ?? '');
                                                    $code = (string)($row['code_monture'] ?? '');
                                                    $lent = (string)($row['lentille_nom'] ?? '');
                                                    $client = (string)($row['nom_patient'] ?? '');
                                                    $pm = (float)($row['prix_monture'] ?? 0);
                                                    $pv = (float)($row['prix_verre'] ?? 0);
                                                    $tot = $pm + $pv;
                                                ?>
                                                <tr>
                                                    <td><?php echo 'ECV' . h($id); ?></td>
                                                    <?php if ($hasVpDateVente || ($hasVpAff && $hasAffDateTraitement)): ?>
                                                        <td><?php echo $dateV !== '' ? h(date('d/m/Y', strtotime($dateV))) : '-'; ?></td>
                                                    <?php endif; ?>
                                                    <td><?php echo h($code !== '' ? $code : '-'); ?></td>
                                                    <td><?php echo h($lent !== '' ? $lent : '-'); ?></td>
                                                    <td><?php echo h($client !== '' ? $client : '-'); ?></td>
                                                    <td><?php echo h(number_format($tot, 0, ',', ' ')) . ' ' . h($devise); ?></td>
                                                    <td>
                                                        <a class="btn btn-sm btn-info" href="modifier_vente_lunette.php?id_vente=<?php echo (int)$id; ?>">Modifier</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php endif; ?>

                    </div>
                </section>
            </div>

        </section>
    </div>
</section>

<?php if ($vente): ?>
    <!-- Modal impression reçu -->
    <div class="modal fade" id="recuPaiementModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl" style="max-width: 1100px; height: 90vh;">
            <div class="modal-content" style="height: 90vh;">
                <div class="modal-header">
                    <h5 class="modal-title">Reçu de paiement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="height: calc(90vh - 120px);">
                    <iframe id="recuIframe" src="about:blank" style="width:100%;height:100%;border:0;"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="button" class="btn btn-primary" id="btnPrintRecuModal">Imprimer</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            function parseNumber(val) {
                if (val === null || val === undefined) return 0;
                var s = String(val).trim();
                if (!s) return 0;
                s = s.replace(/\s+/g, '').replace(',', '.');
                var n = parseFloat(s);
                return isNaN(n) ? 0 : n;
            }

            function formatGNF(val) {
                var n = Math.round(val);
                return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            }

            function updateMontantFromLentille() {
                var sel = document.getElementById('id_lentille');
                if (!sel) return;

                var opt = sel.options[sel.selectedIndex];
                if (!opt) return;

                var prixLentille = parseNumber(opt.getAttribute('data-prix'));
                var prixMonture = parseNumber(document.getElementById('prixMontureValue') ? document.getElementById('prixMontureValue').value : 0);
                var devise = document.getElementById('deviseValue') ? document.getElementById('deviseValue').value : 'GNF';
                var total = prixMonture + prixLentille;

                var elPrix = document.getElementById('prixLentilleDisplay');
                if (elPrix) elPrix.textContent = formatGNF(prixLentille) + ' ' + devise;
                var elTot = document.getElementById('montantDisplay');
                if (elTot) elTot.textContent = formatGNF(total) + ' ' + devise;
            }

            document.addEventListener('change', function(e) {
                if (e.target && e.target.id === 'id_lentille') {
                    updateMontantFromLentille();
                }
            });
            updateMontantFromLentille();

            // Mode paiement: activer/désactiver acompte
            function refreshAcompteState() {
                var partiel = document.getElementById('mode_partiel');
                var acompte = document.getElementById('acompte');
                if (!partiel || !acompte) return;
                acompte.disabled = !partiel.checked;
            }
            document.addEventListener('change', function(e) {
                if (!e.target) return;
                if (e.target.id === 'mode_total' || e.target.id === 'mode_partiel') {
                    refreshAcompteState();
                }
            });
            refreshAcompteState();

            // Impression reçu
            var btn = document.getElementById('btnImprimerRecu');
            if (btn) {
                btn.addEventListener('click', function () {
                    if (!window.bootstrap) {
                        alert('Impossible d\'ouvrir le reçu: Bootstrap indisponible.');
                        return;
                    }
                    var aff = btn.getAttribute('data-affectation');
                    if (!aff) return;
                    var iframe = document.getElementById('recuIframe');
                    if (iframe) {
                        iframe.src = '../caisse/imprimer_recu.php?affectation=' + encodeURIComponent(aff) + '&autoprint=0';
                    }
                    var el = document.getElementById('recuPaiementModal');
                    if (el) {
                        var instance = window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el);
                        instance.show();
                    }
                });
            }

            var btnPrint = document.getElementById('btnPrintRecuModal');
            if (btnPrint) {
                btnPrint.addEventListener('click', function () {
                    var iframe = document.getElementById('recuIframe');
                    if (iframe && iframe.contentWindow) {
                        try { iframe.contentWindow.print(); } catch (e) {}
                    }
                });
            }

            var modalEl = document.getElementById('recuPaiementModal');
            if (modalEl) {
                modalEl.addEventListener('hidden.bs.modal', function () {
                    var iframe = document.getElementById('recuIframe');
                    if (iframe) iframe.src = 'about:blank';
                });
            }
        })();
    </script>
<?php endif; ?>

<?php include('../PUBLIC/footer.php'); ?>
