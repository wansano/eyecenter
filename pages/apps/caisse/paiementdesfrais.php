<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

$errors = 0;
$existe = 0;
$id_patient = isset($_GET['id_patient']) ? (int)$_GET['id_patient'] : 0;
$affectation = isset($_GET['id_affectation']) ? (int)$_GET['id_affectation'] : 0;

// Récupération des informations du patient
try {
    $stmt = $bdd->prepare('SELECT nom_patient, phone, adresse, responsable, profession, age, sexe FROM patients WHERE id_patient = ?');
    $stmt->execute([$id_patient]);
    $patient_info = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération du patient : " . $e->getMessage());
    $patient_info = [];
}

// Initialisation des variables par défaut
$motif = 0;
$recommendeur = 0;
$mont = 0;
$model = '';

// ============== MODE AJAX (formulaire + paiement) ==============
// Récupérer les données nécessaires pour afficher le formulaire dans un modal
if (isset($_GET['ajax_payment_form'])) {
    header('Content-Type: application/json; charset=UTF-8');

    $pid = isset($_GET['id_patient']) ? (int)$_GET['id_patient'] : 0;
    $aff = isset($_GET['id_affectation']) ? (int)$_GET['id_affectation'] : 0;
    if ($pid <= 0 || $aff <= 0) {
        echo json_encode(['success' => false, 'message' => 'Paramètres invalides.']);
        exit;
    }

    try {
        // Patient
        $stP = $bdd->prepare('SELECT nom_patient, phone, adresse, responsable, profession, age, sexe FROM patients WHERE id_patient = ?');
        $stP->execute([$pid]);
        $p = $stP->fetch(PDO::FETCH_ASSOC);
        if (!$p) {
            echo json_encode(['success' => false, 'message' => 'Patient introuvable.']);
            exit;
        }

        // Affectation + motif + montant
        $stA = $bdd->prepare('SELECT id_affectation, id_patient, type, id_rdv, status FROM affectations WHERE id_affectation = ? LIMIT 1');
        $stA->execute([$aff]);
        $a = $stA->fetch(PDO::FETCH_ASSOC);
        if (!$a || (int)$a['id_patient'] !== $pid) {
            echo json_encode(['success' => false, 'message' => 'Affectation introuvable.']);
            exit;
        }

        $motifId = (int)($a['type'] ?? 0);
        $rdvId = (int)($a['id_rdv'] ?? 0);

        $montant = 0.0;
        if ($motifId > 0) {
            $stT = $bdd->prepare('SELECT montant FROM traitements WHERE id_type = ? LIMIT 1');
            $stT->execute([$motifId]);
            $montant = (float)($stT->fetchColumn() ?: 0);
        }

        // Options de paiement
        $comptes = [];
                // Ne pas proposer un compte déjà clôturé (preuve de caisse effectuée aujourd'hui pour ce compte)
                $stC = $bdd->prepare('
                        SELECT c.id_compte, c.types
                        FROM comptes c
                        WHERE c.defaut = 1 AND c.compte_pour = ?
                            AND NOT EXISTS (
                                SELECT 1 FROM preuvedecaisse p
                                WHERE p.date_rapportement = ?
                                    AND p.id_user = ?
                                    AND p.compte = c.id_compte
                            )
                ');
                $stC->execute([1, date('Y-m-d'), $_SESSION['auth']]);
        while ($c = $stC->fetch(PDO::FETCH_ASSOC)) {
            $comptes[] = ['id' => (int)$c['id_compte'], 'label' => (string)$c['types']];
        }

        $tauxList = [];
        $stR = $bdd->prepare('SELECT taux FROM taux WHERE status=1 AND taux_pour = ?');
        $stR->execute([0]);
        while ($r = $stR->fetch(PDO::FETCH_ASSOC)) {
            $tauxVal = (float)($r['taux'] ?? 0);
            if ($tauxVal === 0.0) {
                $tauxList[] = ['value' => 0, 'label' => 'Non Appliqué'];
            } elseif ($tauxVal !== 3.0) {
                $tauxList[] = ['value' => $tauxVal, 'label' => $tauxVal . '%'];
            }
        }
        if (empty($tauxList)) {
            $tauxList[] = ['value' => 0, 'label' => 'Non Appliqué'];
        }

        // Déjà payé ?
        $stPaid = $bdd->prepare('SELECT COUNT(*) FROM paiements WHERE id_affectation = ?');
        $stPaid->execute([$aff]);
        $alreadyPaid = ((int)$stPaid->fetchColumn() > 0);

        echo json_encode([
            'success' => true,
            'already_paid' => $alreadyPaid,
            'blocked' => empty($comptes),
            'blocked_message' => empty($comptes) ? "Aucun compte de paiement disponible : la preuve de caisse a déjà été effectuée aujourd'hui pour ce(s) compte(s)." : null,
            'patient' => [
                'id_patient' => $pid,
                'nom_patient' => (string)($p['nom_patient'] ?? ''),
                'phone' => (string)($p['phone'] ?? ''),
                'profession' => (string)($p['profession'] ?? ''),
            ],
            'affectation' => [
                'id_affectation' => $aff,
                'motif_id' => $motifId,
                'motif' => (string)model($motifId),
                'montant' => $montant,
                'montant_label' => number_format($montant, 0, ',', ' '),
                'rdv' => $rdvId,
            ],
            'options' => [
                'comptes' => $comptes,
                'taux' => $tauxList,
            ],
        ]);
        exit;
    } catch (Throwable $e) {
        error_log('[AJAX PAYMENT FORM] ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement du formulaire.']);
        exit;
    }
}

// Effectuer le paiement via AJAX
if (isset($_POST['ajax_payment'])) {
    header('Content-Type: application/json; charset=UTF-8');

    $pid = isset($_POST['id_patient']) ? (int)$_POST['id_patient'] : 0;
    $aff = isset($_POST['id_affectation']) ? (int)$_POST['id_affectation'] : 0;
    $type_paiement_ajax = isset($_POST['type_paiement']) ? (int)$_POST['type_paiement'] : 0;
    $taux_ajax = isset($_POST['taux']) ? (float)$_POST['taux'] : 0.0;

    if ($pid <= 0 || $aff <= 0 || $type_paiement_ajax <= 0) {
        echo json_encode(['success' => false, 'message' => 'Paramètres invalides.']);
        exit;
    }

    try {
        // Interdire un paiement sur un compte déjà clôturé aujourd'hui par cet utilisateur
        $stClosed = $bdd->prepare('SELECT 1 FROM preuvedecaisse WHERE date_rapportement = ? AND id_user = ? AND compte = ? LIMIT 1');
        $stClosed->execute([date('Y-m-d'), $_SESSION['auth'], $type_paiement_ajax]);
        if ($stClosed->fetchColumn()) {
            echo json_encode([
                'success' => false,
                'message' => "Ce compte est déjà clôturé (preuve de caisse effectuée aujourd'hui). Veuillez choisir un autre compte.",
            ]);
            exit;
        }

        // Vérifier l'affectation
        $stA = $bdd->prepare('SELECT id_patient, type, id_rdv FROM affectations WHERE id_affectation = ? LIMIT 1');
        $stA->execute([$aff]);
        $a = $stA->fetch(PDO::FETCH_ASSOC);
        if (!$a || (int)$a['id_patient'] !== $pid) {
            echo json_encode(['success' => false, 'message' => 'Affectation introuvable.']);
            exit;
        }

        $motifAjax = (int)($a['type'] ?? 0);
        $rdvAjax = (int)($a['id_rdv'] ?? 0);

        // Montant du traitement
        $montAjax = 0.0;
        if ($motifAjax > 0) {
            $stT = $bdd->prepare('SELECT montant FROM traitements WHERE id_type = ? LIMIT 1');
            $stT->execute([$motifAjax]);
            $montAjax = (float)($stT->fetchColumn() ?: 0);
        }

        // Déjà payé ?
        $stPaid = $bdd->prepare('SELECT COUNT(*) FROM paiements WHERE id_affectation = ?');
        $stPaid->execute([$aff]);
        $alreadyPaid = ((int)$stPaid->fetchColumn() > 0);
        if ($alreadyPaid) {
            echo json_encode([
                'success' => false,
                'already_paid' => true,
                'message' => 'Paiement déjà effectué.',
                'receipt_url' => 'imprimer_recu.php?affectation=' . urlencode((string)$aff),
            ]);
            exit;
        }

        $code = genererNumeroPaiement();

        $bdd->beginTransaction();
        try {
            $taux_appli = ($montAjax * $taux_ajax / 100);
            $montant_base = $montAjax - $taux_appli;

            $stmt = $bdd->prepare('SELECT debit, taux FROM comptes WHERE id_compte = ?');
            $stmt->execute([$type_paiement_ajax]);
            $compte_info = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$compte_info) {
                throw new Exception('Compte de paiement invalide.');
            }
            $debit_dispo = (float)($compte_info['debit'] ?? 0);
            $taux_compte = (float)($compte_info['taux'] ?? 0);

            $is_mobile = IsPaiementElectronique($type_paiement_ajax) === 1;
            if ($is_mobile) {
                $frais = ($montant_base * $taux_compte / 100);
                $montant_final = $montant_base + $frais;
            } else {
                $montant_final = $montant_base;
            }

            $stmt = $bdd->prepare('UPDATE affectations SET status = 1, montant = ?, taux = ?, type_paiement = ? WHERE id_affectation = ?');
            $stmt->execute([$montant_final, $taux_ajax, $type_paiement_ajax, $aff]);

            $stmt = $bdd->prepare('INSERT INTO paiements (id_affectation, code, types, montant, montant_paye, compte, patient, caisse) VALUES (?,?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$aff, $code, $motifAjax, $montant_final, $montant_final, $type_paiement_ajax, $pid, $_SESSION['auth']]);

            $nouveau_debit = $debit_dispo + $montant_final;
            $stmt = $bdd->prepare('UPDATE comptes SET debit = ? WHERE id_compte = ?');
            $stmt->execute([$nouveau_debit, $type_paiement_ajax]);

            if ($rdvAjax > 0) {
                $stmt = $bdd->prepare('UPDATE dmd_rendez_vous SET status = 2 WHERE id_rdv = ?');
                $stmt->execute([$rdvAjax]);
            }

            $bdd->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Paiement effectué.',
                'receipt_url' => 'imprimer_recu.php?affectation=' . urlencode((string)$aff),
            ]);
            exit;
        } catch (Throwable $txe) {
            $bdd->rollBack();
            throw $txe;
        }
    } catch (Throwable $e) {
        error_log('[AJAX PAYMENT] ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur lors du paiement.']);
        exit;
    }
}

// Récupération des informations d'affectation
try {
    $stmt = $bdd->prepare('SELECT * FROM affectations WHERE id_affectation = ?');
    $stmt->execute([$affectation]);
    $affectation_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($affectation_info) {
        $motif = $affectation_info['type'] ?? 0;
        $rdv = $affectation_info['id_rdv'];
        
        // Récupération des informations de traitement
        if ($motif > 0) {
            $stmt = $bdd->prepare('SELECT montant, id_organigramme FROM traitements WHERE id_type = ?');
            $stmt->execute([$motif]);
            $traitement_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($traitement_info) {
                $mont = $traitement_info['montant'] ?? 0;
                $model = $traitement_info['id_organigramme'] ?? '';
            }
        }
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération de l'affectation : " . $e->getMessage());
    $affectation_info = [];
}

// Traitement du paiement
if (isset($_POST['validationpaiement'])) {
    try {
        // Vérification si le paiement existe déjà
        $stmt = $bdd->prepare('SELECT COUNT(*) FROM paiements WHERE id_affectation = ?');
        $stmt->execute([$affectation]);
        $existe = (int)$stmt->fetchColumn() > 0;
        
        if (!$existe && isset($_POST['type_paiement'], $_POST['taux'])) {
            $code = genererNumeroPaiement();
            $type_paiement = $_POST['type_paiement'];
            $taux = (float)$_POST['taux'];
            
            // Début de la transaction
            $bdd->beginTransaction();
            
            try {
                $taux_appli = ($mont * $taux / 100);
                $montant_base = $mont - $taux_appli;
                
                // Récupération des informations du compte
                $stmt = $bdd->prepare('SELECT debit, taux FROM comptes WHERE id_compte = ?');
                $stmt->execute([$type_paiement]);
                $compte_info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $debit_dispo = $compte_info['debit'] ?? 0;
                $taux_compte = $compte_info['taux'] ?? 0;
                $electronique = $compte_info['electronique'] ?? 0;

                // Calcul du montant final selon le type de paiement
                $is_mobile = IsPaiementElectronique($type_paiement) === 1;
                if ($is_mobile) {
                    $frais = ($montant_base * $taux_compte / 100);
                    $montant_final = $montant_base + $frais;
                } else {
                    $montant_final = $montant_base;
                }
                
                // Mise à jour de l'affectation
                $stmt = $bdd->prepare('UPDATE affectations SET status = 1, montant = ?, taux = ?, type_paiement = ? WHERE id_affectation = ?');
                $stmt->execute([$montant_final, $taux, $type_paiement, $affectation]);
                
                // Insertion du paiement
                $stmt = $bdd->prepare('INSERT INTO paiements (id_affectation, code, types, montant, montant_paye, compte, patient, caisse) VALUES (?,?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$affectation, $code, $motif, $montant_final, $montant_final, $type_paiement, $id_patient, $_SESSION['auth']]);
                
                // Mise à jour du compte
                $nouveau_debit = $debit_dispo + $montant_final;
                $stmt = $bdd->prepare('UPDATE comptes SET debit = ? WHERE id_compte = ?');
                $stmt->execute([$nouveau_debit, $type_paiement]);

                // mise à jour du rdv
                $stmt = $bdd->prepare('UPDATE dmd_rendez_vous SET status = 2 WHERE id_rdv = ?');
                $stmt->execute([$rdv]);

                $bdd->commit();
                $errors = 3; // Succès
                
            } catch (PDOException $e) {
                $bdd->rollBack();
                error_log("Erreur lors du paiement : " . $e->getMessage());
                $errors = 1; // Erreur
            }
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification du paiement : " . $e->getMessage());
        $errors = 1;
    }
}

require('../PUBLIC/header.php');
?>

<body>
    <section class="body">

        <?php require('../PUBLIC/navbarmenu.php'); ?>

        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Paiement des frais de traitements</h2>
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <section class="card">
                        <div class="card-body">
                            <?php
                                        if ($errors==3) {
                                        echo '
                                            <div class="alert alert-success">
                                            <strong>Succès paiement éffectué !</strong> <br/>  
                                            <li>Le dossier du patient à été transmis au service concerné. Merci de rediriger le patient vers le service concerné.</li>
                                            <li>Ré-imprimer le reçu de paiement en cliquant sur <a href="imprimer_recu_patient.php?affectation='.$_GET['id_affectation'].'" target="_blank"><i class="fa fa-file-pdf-o"></i> Reçu de paiement</a>.</li>
                                            </div>
                                            ';
                                                }
                                        if ($existe==1) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <strong>Erreur de Paiement !</strong> <br/>  
                                                <li>Paiement déjà éffectué par le patient merci de bien rediriger le patient vers le service.</li>
                                                <li>Vous pouvez ré-imprimer le reçu de paiement en cliquant sur <a href="imprimer_recu_patient.php?affectation='.$_GET['id_affectation'].'" target="_blank"><i class="fa fa-file-pdf-o"></i> Reçu de paiement</a>.</li>
                                            </div>
                                            ';}
                                    ?>
                            <form class="form-horizontal" novalidate="novalidate" method="POST"
                                action="paiementdesfrais.php?<?php echo 'id_patient='.$_GET['id_patient'].'&id_affectation='.$_GET['id_affectation']; ?>"
                                enctype="multipart/form-data">
                                <input type="hidden" name="validationpaiement" value="<?php $id_affectation ?>">
                                <div class="row form-group pb-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Prénoms &
                                                Nom</label>
                                            <input type="text" class="form-control" placeholder=""
                                                value="<?php echo $patient_info['nom_patient'] ?? '';?>" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Genre</label>
                                            <input type="text" class="form-control" placeholder=""
                                                value="<?php echo $patient_info['sexe'] ?? '';?>" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Contact</label>
                                            <input type="number" class="form-control" maxlength="09"
                                                id="formGroupExampleInput" placeholder=""
                                                value="<?php echo $patient_info['phone'] ?? '';?>" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Profession</label>
                                            <input type="text" class="form-control" id="formGroupExampleInput"
                                                placeholder="" value="<?php echo $patient_info['profession'] ?? ''; ?>" disabled>
                                        </div>
                                    </div>
                                </div>
                                <div class="row form-group pb-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Adresse</label>
                                            <input type="text" class="form-control" id="formGroupExampleInput"
                                                placeholder="" value="<?php echo (adress($patient_info['adresse']) ?: $patient_info['adresse']); ?>" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label"
                                                for="formGroupExampleInput">Responsable</label>
                                            <input type="text" class="form-control" id="formGroupExampleInput"
                                                placeholder="" value="<?php echo $patient_info['responsable'] ?? '';?>" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Recommandé par </label>
                                            <?php
                                                if ($recommendeur==0){ echo'
                                                    <input type="text" class="form-control" value="Non recommandé" disabled="">';
                                                    } else { echo '
                                                    <input type="text" class="form-control" value="'.collaborateur($recommendeur).'" disabled="">';  
                                                    }
                                                ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="row form-group pb-3">                                    
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Motif de présence</label>
                                            <input type="text" class="form-control" id="formGroupExampleInput"
                                                placeholder="" value="<?php echo model($motif);?>" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Type de
                                                reglement</label>
                                            <select class="form-control" name="type_paiement" required="">
                                                <?php 
                                                    $type = $bdd->prepare('SELECT id_compte, types FROM comptes WHERE defaut=1 AND compte_pour=?');
                                                    $type -> execute([1]);
                                                    while ($type_paiement = $type->fetch(PDO::FETCH_ASSOC))
                                                    {
                                                        echo '<option value="'.$type_paiement['id_compte'].'">'.$type_paiement['types'].'</option>';
                                                    } 
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Remise/Ristourne</label>
                                            <select name="taux" id="" class="form-control">
                                                <?php 
                                                    $rabais = $bdd->prepare('SELECT * FROM taux WHERE status=1 AND taux_pour = ?');
                                                    $rabais -> execute([0]);
                                                    while ($taux = $rabais->fetch(PDO::FETCH_ASSOC))
                                                    { $status = $taux['taux'];
                                                        if ($status==0) {
                                                            echo '<option value="0">Non Appliqué</option>';
                                                        }
                                                        if (($status!=0) AND ($status!=3)) {
                                                        echo '<option value="'.$taux['taux'].'">'.$taux['taux'].'%</option>';
                                                        }
                                                    } 
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                        </div>
                        <footer class="card-footer text-end">
                            <button class="btn btn-primary" type="submit">valider le paiement</button>
                        </footer>
                        </form>
                    </section>
                </div>
        </div>
        <!-- end: page -->
    </section>
    </div>
    <?php if ($errors == 3 && $affectation): ?>
        <script>
            window.onload = function() {
                window.open('imprimer_recu.php?affectation=<?= $affectation ?>', '_blank');
            };
        </script>
    <?php endif; ?>
    <?php include('../PUBLIC/footer.php');?>