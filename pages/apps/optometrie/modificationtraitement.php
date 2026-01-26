<?php
include('../public/connect.php');
require_once('../public/fonction.php');
require_once('../public/medecin_ieu.php');

session_start();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['auth'])) {
    header('Location: ../login.php');
    exit;
}

$errors = '';
$success = '';

// PRG flash
if (!empty($_SESSION['flash_modif_traitement_success'])) {
    $success = (string)$_SESSION['flash_modif_traitement_success'];
    unset($_SESSION['flash_modif_traitement_success']);
}
if (!empty($_SESSION['flash_modif_traitement_error'])) {
    $errors = (string)$_SESSION['flash_modif_traitement_error'];
    unset($_SESSION['flash_modif_traitement_error']);
}

$affectationId = isset($_GET['affectation']) ? (int)$_GET['affectation'] : 0;
$patientId = isset($_GET['patient']) ? (int)$_GET['patient'] : 0;

function loadAffectation(PDO $bdd, int $affectationId): ?array {
    $st = $bdd->prepare(
        'SELECT a.*, p.nom_patient, p.responsable, t.nom_type
         FROM affectations a
         INNER JOIN patients p ON p.id_patient = a.id_patient
         LEFT JOIN traitements t ON t.id_type = a.type
         WHERE a.id_affectation = ?
         LIMIT 1'
    );
    $st->execute([$affectationId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function detectTraitementTable(PDO $bdd, int $affectationId): ?string {
    if (checkTraitementExisteConsultation($bdd, $affectationId)) return 'consultations';
    if (checkTraitementExisteExamen($bdd, $affectationId)) return 'examens';
    if (checkTraitementExisteControle($bdd, $affectationId)) return 'controles';
    if (checkTraitementExisteSoins($bdd, $affectationId)) return 'soins';
    if (checkTraitementExisteMesure($bdd, $affectationId)) return 'mesures';
    if (checkTraitementExisteRapport($bdd, $affectationId)) return 'rapportements';
    if (checkTraitementExisteChirurgie($bdd, $affectationId)) return 'chirurgies';
    return null;
}

function loadTraitement(PDO $bdd, string $table, int $affectationId): ?array {
    $st = $bdd->prepare("SELECT * FROM {$table} WHERE id_affectation = ? LIMIT 1");
    $st->execute([$affectationId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function loadAcquite(PDO $bdd, int $affectationId): ?array {
    $st = $bdd->prepare('SELECT * FROM acquitte_visuelle WHERE id_affectation = ? LIMIT 1');
    $st->execute([$affectationId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function renderEditFormHtml(array $aff, string $table, array $traitement, ?array $acq, int $affectationId, string $printUrl): string {
    $patientNom = htmlspecialchars((string)($aff['nom_patient'] ?? ''), ENT_QUOTES, 'UTF-8');
    $nomType = htmlspecialchars((string)($aff['nom_type'] ?? ''), ENT_QUOTES, 'UTF-8');
    $idAff = (int)$affectationId;
    $printUrlEsc = htmlspecialchars((string)$printUrl, ENT_QUOTES, 'UTF-8');

    $acq = $acq ?: [];

    ob_start();
    ?>
    <div id="editTraitementAlert"></div>

    <form class="form-horizontal" method="POST" action="modificationtraitement.php?affectation=<?php echo (int)$idAff; ?>" id="editTraitementForm">
        <input type="hidden" name="ajax_update" value="1">
        <input type="hidden" name="affectation" value="<?php echo (int)$idAff; ?>">

        <?php if (in_array($table, ['consultations','examens','controles','soins'], true)): ?>
            <h5 class="mb-2">Acuité visuelle</h5>
            <div class="row form-group pb-3">
                <div class="col-md-2">
                    <label class="col-form-label">AVLSC OD</label>
                    <input type="text" class="form-control" name="avlscod" required value="<?php echo htmlspecialchars((string)($acq['od_avlsc'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-2">
                    <label class="col-form-label">AVLSC OS</label>
                    <input type="text" class="form-control" name="avlscos" required value="<?php echo htmlspecialchars((string)($acq['os_avlsc'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-2">
                    <label class="col-form-label">AVC OD</label>
                    <input type="text" class="form-control" name="avcod" value="<?php echo htmlspecialchars((string)($acq['od_avc'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-2">
                    <label class="col-form-label">AVC OS</label>
                    <input type="text" class="form-control" name="avcos" value="<?php echo htmlspecialchars((string)($acq['os_avc'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-2">
                    <label class="col-form-label">TS OD</label>
                    <input type="text" class="form-control" name="tsod" value="<?php echo htmlspecialchars((string)($acq['od_ts'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-2">
                    <label class="col-form-label">TS OS</label>
                    <input type="text" class="form-control" name="tsos" value="<?php echo htmlspecialchars((string)($acq['os_ts'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-4 mt-2">
                    <label class="col-form-label">P</label>
                    <input type="text" class="form-control" name="p" value="<?php echo htmlspecialchars((string)($acq['p'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
        <?php endif; ?>

        <h5 class="mb-2">Données du traitement</h5>

        <?php if ($table === 'consultations'): ?>
            <div class="row form-group pb-3">
                <div class="col-md-4">
                    <label class="col-form-label">Diagnostic</label>
                    <textarea class="form-control" name="diagnostic" rows="3" required><?php echo htmlspecialchars((string)($traitement['diagnostic'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="col-md-3">
                    <label class="col-form-label">Bilan</label>
                    <textarea class="form-control" name="bilan" rows="3"><?php echo htmlspecialchars((string)($traitement['bilan'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="col-md-2">
                    <label class="col-form-label">Traitement</label>
                    <?php $cur = (string)($traitement['traitement'] ?? ''); ?>
                    <select name="traitement" class="form-control" required>
                        <option value="Ordonnance médicale" <?php echo $cur === 'Ordonnance médicale' ? 'selected' : ''; ?>>Ordonnance médicale</option>
                        <option value="Soins" <?php echo $cur === 'Soins' ? 'selected' : ''; ?>>Soins</option>
                        <option value="Chirurgie" <?php echo $cur === 'Chirurgie' ? 'selected' : ''; ?>>Chirurgie</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="col-form-label">Prescription</label>
                    <textarea class="form-control" name="prescription" rows="3" required><?php echo htmlspecialchars((string)($traitement['prescription'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>
        <?php elseif ($table === 'examens'): ?>
            <div class="row form-group pb-3">
                <div class="col-md-6">
                    <label class="col-form-label">Diagnostic</label>
                    <textarea class="form-control" name="diagnostic" rows="3" required><?php echo htmlspecialchars((string)($traitement['diagnostic'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="col-form-label">Résultat</label>
                    <textarea class="form-control" name="resultat" rows="3" required><?php echo htmlspecialchars((string)($traitement['resultat'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>
        <?php elseif ($table === 'controles'): ?>
            <div class="row form-group pb-3">
                <div class="col-md-6">
                    <label class="col-form-label">Diagnostic</label>
                    <textarea class="form-control" name="diagnostic" rows="3" required><?php echo htmlspecialchars((string)($traitement['diagnostic'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="col-md-3">
                    <label class="col-form-label">Traitement</label>
                    <input type="text" class="form-control" name="traitement" required value="<?php echo htmlspecialchars((string)($traitement['traitement'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-3">
                    <label class="col-form-label">Prescription</label>
                    <textarea class="form-control" name="prescription" rows="3"><?php echo htmlspecialchars((string)($traitement['prescription'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>
        <?php elseif ($table === 'soins'): ?>
            <div class="row form-group pb-3">
                <div class="col-md-4">
                    <label class="col-form-label">Diagnostic</label>
                    <textarea class="form-control" name="diagnostic" rows="4" required><?php echo htmlspecialchars((string)($traitement['diagnostic'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="col-form-label">Conduite tenue</label>
                    <textarea class="form-control" name="conduite" rows="4" required><?php echo htmlspecialchars((string)($traitement['conduite'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="col-form-label">Prescription</label>
                    <textarea class="form-control" name="prescription" rows="4" required><?php echo htmlspecialchars((string)($traitement['prescription'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>
        <?php elseif ($table === 'mesures'): ?>
            <div class="row form-group pb-3">
                <div class="col-md-3">
                    <label class="col-form-label">Type de réfraction</label>
                    <?php $refCur = (string)($traitement['refraction'] ?? 'Vision de loin'); ?>
                    <select name="refraction" class="form-control" required>
                        <option value="Vision de près" <?php echo $refCur === 'Vision de près' ? 'selected' : ''; ?>>Vision de près</option>
                        <option value="Vision de loin" <?php echo $refCur === 'Vision de loin' ? 'selected' : ''; ?>>Vision de loin</option>
                        <option value="Progressif" <?php echo $refCur === 'Progressif' ? 'selected' : ''; ?>>Progressif</option>
                    </select>
                </div>
            </div>
            <div class="row form-group pb-3">
                <div class="col-md-3">
                    <label class="col-form-label">Oeil droit (OD)</label>
                    <input type="text" class="form-control" name="od" required value="<?php echo htmlspecialchars((string)($traitement['od'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-3">
                    <label class="col-form-label">Oeil gauche (OS)</label>
                    <input type="text" class="form-control" name="os" required value="<?php echo htmlspecialchars((string)($traitement['os'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-3">
                    <label class="col-form-label">Addition</label>
                    <input type="text" class="form-control" name="addit" value="<?php echo htmlspecialchars((string)($traitement['addit'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-3">
                    <label class="col-form-label">EIP</label>
                    <input type="text" class="form-control" name="eip" value="<?php echo htmlspecialchars((string)($traitement['eip'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="row form-group pb-3">
                <div class="col-md-12">
                    <label class="col-form-label">Détails</label>
                    <textarea class="form-control" name="details" rows="5" required><?php echo htmlspecialchars((string)($traitement['details'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>
        <?php elseif ($table === 'rapportements'): ?>
            <div class="row form-group pb-3">
                <div class="col-md-12">
                    <label class="col-form-label">Rapport médical</label>
                    <textarea class="form-control" name="rapport" rows="10" required><?php echo htmlspecialchars((string)($traitement['rapport'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>
            <?php if (array_key_exists('pathologie', $traitement)): ?>
                <div class="row form-group pb-3">
                    <div class="col-md-6">
                        <label class="col-form-label">Photo pathologie (fichier)</label>
                        <input type="text" class="form-control" name="pathologie" value="<?php echo htmlspecialchars((string)($traitement['pathologie'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nom du fichier enregistré">
                        <small class="text-muted">Modification du fichier non gérée ici (édition du nom uniquement).</small>
                    </div>
                </div>
            <?php endif; ?>
        <?php elseif ($table === 'chirurgies'): ?>
            <div class="row form-group pb-3">
                <div class="col-md-3">
                    <label class="col-form-label">Glycémie</label>
                    <input type="text" class="form-control" name="glycemie" value="<?php echo htmlspecialchars((string)($acq['glycemie'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-4">
                    <label class="col-form-label">Date/heure chirurgie prévue</label>
                    <input type="datetime-local" class="form-control" name="date_chirurgie" value="<?php echo htmlspecialchars((string)($traitement['date_chirurgie'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="row form-group pb-3">
                <div class="col-md-6">
                    <label class="col-form-label">Diagnostic</label>
                    <textarea class="form-control" name="diagnostic" rows="4" required><?php echo htmlspecialchars((string)($traitement['diagnostic'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="col-form-label">Traitement</label>
                    <textarea class="form-control" name="traitement" rows="4" required><?php echo htmlspecialchars((string)($traitement['traitement'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>
            <div class="row form-group pb-3">
                <div class="col-md-6">
                    <label class="col-form-label">Protocole</label>
                    <textarea class="form-control" name="protocole" rows="4" required><?php echo htmlspecialchars((string)($traitement['protocole'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="col-form-label">Prescription</label>
                    <textarea class="form-control" name="prescription" rows="4" required><?php echo htmlspecialchars((string)($traitement['prescription'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            <?php if (!empty($printUrlEsc)): ?>
                <button type="button" class="btn btn-outline-primary" onclick="window.openPrintTraitementModal('<?php echo $printUrlEsc; ?>')">Imprimer</button>
            <?php endif; ?>
            <button type="submit" class="btn btn-success" id="btnEditTraitementSave">Enregistrer les modifications</button>
        </div>
    </form>
    <?php
    return (string)ob_get_clean();
}

function patientExists(PDO $bdd, int $patientId): bool {
    if ($patientId <= 0) return false;
    try {
        $st = $bdd->prepare('SELECT 1 FROM patients WHERE id_patient = ? LIMIT 1');
        $st->execute([$patientId]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function listTraitements30Jours(PDO $bdd, int $patientId, int $doctorId): array {
    if ($patientId <= 0 || $doctorId <= 0) return [];

    // Filtre date: datetraitement si présent, sinon date (affectation)
    $sql = "
        (SELECT
            'consultations' AS source_table,
            a.id_affectation,
            a.id_patient,
            a.type AS id_type,
            t.nom_type,
            COALESCE(a.datetraitement, a.date) AS date_traitement,
            c.traitant
         FROM consultations c
         INNER JOIN affectations a ON a.id_affectation = c.id_affectation
         LEFT JOIN traitements t ON t.id_type = a.type
         WHERE a.id_patient = ? AND c.traitant = ?
           AND COALESCE(a.datetraitement, a.date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        )
        UNION ALL
        (SELECT
            'examens' AS source_table,
            a.id_affectation,
            a.id_patient,
            a.type AS id_type,
            t.nom_type,
            COALESCE(a.datetraitement, a.date) AS date_traitement,
            e.traitant
         FROM examens e
         INNER JOIN affectations a ON a.id_affectation = e.id_affectation
         LEFT JOIN traitements t ON t.id_type = a.type
         WHERE a.id_patient = ? AND e.traitant = ?
           AND COALESCE(a.datetraitement, a.date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        )
        UNION ALL
        (SELECT
            'controles' AS source_table,
            a.id_affectation,
            a.id_patient,
            a.type AS id_type,
            t.nom_type,
            COALESCE(a.datetraitement, a.date) AS date_traitement,
            co.traitant
         FROM controles co
         INNER JOIN affectations a ON a.id_affectation = co.id_affectation
         LEFT JOIN traitements t ON t.id_type = a.type
         WHERE a.id_patient = ? AND co.traitant = ?
           AND COALESCE(a.datetraitement, a.date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
          )
          UNION ALL
          (SELECT
                'soins' AS source_table,
                a.id_affectation,
                a.id_patient,
                a.type AS id_type,
                t.nom_type,
                COALESCE(a.datetraitement, a.date) AS date_traitement,
                s.traitant
            FROM soins s
            INNER JOIN affectations a ON a.id_affectation = s.id_affectation
            LEFT JOIN traitements t ON t.id_type = a.type
            WHERE a.id_patient = ? AND s.traitant = ?
              AND COALESCE(a.datetraitement, a.date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
          )
          UNION ALL
          (SELECT
                'mesures' AS source_table,
                a.id_affectation,
                a.id_patient,
                a.type AS id_type,
                t.nom_type,
                COALESCE(a.datetraitement, a.date) AS date_traitement,
                m.traitant
            FROM mesures m
            INNER JOIN affectations a ON a.id_affectation = m.id_affectation
            LEFT JOIN traitements t ON t.id_type = a.type
            WHERE a.id_patient = ? AND m.traitant = ?
              AND COALESCE(a.datetraitement, a.date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
          )
          UNION ALL
          (SELECT
                'rapportements' AS source_table,
                a.id_affectation,
                a.id_patient,
                a.type AS id_type,
                t.nom_type,
                COALESCE(a.datetraitement, a.date) AS date_traitement,
                r.traitant
            FROM rapportements r
            INNER JOIN affectations a ON a.id_affectation = r.id_affectation
            LEFT JOIN traitements t ON t.id_type = a.type
            WHERE a.id_patient = ? AND r.traitant = ?
              AND COALESCE(a.datetraitement, a.date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
          )
          UNION ALL
          (SELECT
                'chirurgies' AS source_table,
                a.id_affectation,
                a.id_patient,
                a.type AS id_type,
                t.nom_type,
                COALESCE(a.datetraitement, a.date) AS date_traitement,
                ch.traitant
            FROM chirurgies ch
            INNER JOIN affectations a ON a.id_affectation = ch.id_affectation
            LEFT JOIN traitements t ON t.id_type = a.type
            WHERE a.id_patient = ? AND ch.traitant = ?
              AND COALESCE(a.datetraitement, a.date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
          )
        ORDER BY date_traitement DESC, id_affectation DESC
    ";

    try {
        $st = $bdd->prepare($sql);
        $st->execute([
            $patientId, $doctorId,
            $patientId, $doctorId,
            $patientId, $doctorId,
            $patientId, $doctorId,
            $patientId, $doctorId,
            $patientId, $doctorId,
            $patientId, $doctorId,
        ]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    } catch (Throwable $e) {
        error_log('[modificationtraitement:listTraitements30Jours] ' . $e->getMessage());
        return [];
    }
}

if (isset($_POST['do_search_patient'])) {
    $p = isset($_POST['patient']) ? (int)$_POST['patient'] : 0;
    if ($p <= 0) {
        $errors = "Veuillez saisir un numéro dossier patient valide.";
    } else {
        header('Location: modificationtraitement.php?patient=' . (int)$p);
        exit;
    }
}

// AJAX: charger le formulaire de modification dans un modal
if (isset($_GET['ajax_edit'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $a = isset($_GET['affectation']) ? (int)$_GET['affectation'] : 0;
    if ($a <= 0) {
        echo json_encode(['success' => false, 'message' => "Affectation invalide."]);
        exit;
    }

    try {
        $aff = loadAffectation($bdd, $a);
        if (!$aff) {
            throw new Exception('Affectation introuvable.');
        }
        $table = detectTraitementTable($bdd, $a);
        if (!$table) {
            throw new Exception("Aucun traitement enregistré pour cette affectation.");
        }
        $traitement = loadTraitement($bdd, $table, $a);
        if (!$traitement) {
            throw new Exception('Traitement introuvable.');
        }

        $traitant = isset($traitement['traitant']) ? (int)$traitement['traitant'] : 0;
        if ($traitant > 0 && $traitant !== (int)$_SESSION['auth']) {
            throw new Exception("Vous ne pouvez pas modifier ce traitement (réalisé par un autre médecin)." );
        }

        $acq = loadAcquite($bdd, $a);
        $printUrl = '';
        if ($table === 'consultations') {
            $printUrl = 'imprimer_consultation.php?affectation=' . urlencode((string)$a) . '&autoprint=0';
        } elseif ($table === 'examens') {
            $printUrl = 'imprimer_examen.php?affectation=' . urlencode((string)$a) . '&autoprint=0';
        } elseif ($table === 'controles') {
            $printUrl = 'imprimer_controle.php?affectation=' . urlencode((string)$a) . '&autoprint=0';
        } elseif ($table === 'soins') {
            $printUrl = '../medecinchef/imprimer_soins.php?affectation=' . urlencode((string)$a) . '&autoprint=0';
        } elseif ($table === 'mesures') {
            $printUrl = '../medecinchef/imprimer_mesure.php?affectation=' . urlencode((string)$a) . '&autoprint=0';
        } elseif ($table === 'rapportements') {
            $printUrl = 'imprimer_rapport.php?affectation=' . urlencode((string)$a) . '&autoprint=0';
        } elseif ($table === 'chirurgies') {
            $printUrl = 'imprimer_chirurgie.php?affectation=' . urlencode((string)$a) . '&autoprint=0';
        }

        $html = renderEditFormHtml($aff, $table, $traitement, $acq, $a, $printUrl);
        echo json_encode(['success' => true, 'html' => $html]);
        exit;
    } catch (Throwable $e) {
        error_log('[modificationtraitement ajax_edit] ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

if (isset($_POST['do_update'])) {
    $a = isset($_POST['affectation']) ? (int)$_POST['affectation'] : 0;
    if ($a <= 0) {
        $_SESSION['flash_modif_traitement_error'] = "Affectation invalide.";
        header('Location: modificationtraitement.php');
        exit;
    }

    try {
        $aff = loadAffectation($bdd, $a);
        if (!$aff) {
            throw new Exception('Affectation introuvable.');
        }

        $table = detectTraitementTable($bdd, $a);
        if (!$table) {
            throw new Exception("Aucun traitement enregistré pour cette affectation.");
        }

        $traitement = loadTraitement($bdd, $table, $a);
        if (!$traitement) {
            throw new Exception('Traitement introuvable.');
        }

        // Sécurité simple: n'autoriser que le médecin traitant d'origine à modifier
        $traitant = isset($traitement['traitant']) ? (int)$traitement['traitant'] : 0;
        if ($traitant > 0 && $traitant !== (int)$_SESSION['auth']) {
            throw new Exception("Vous ne pouvez pas modifier ce traitement (réalisé par un autre médecin)." );
        }

        $bdd->beginTransaction();
        try {
            // Mise à jour acquitte uniquement pour certains types
            if (in_array($table, ['consultations','examens','controles','soins'], true)) {
                $acq = loadAcquite($bdd, $a);
                $hasAcq = (bool)$acq;

                $avlscod = trim((string)($_POST['avlscod'] ?? ''));
                $avlscos = trim((string)($_POST['avlscos'] ?? ''));
                $avcod = trim((string)($_POST['avcod'] ?? ''));
                $avcos = trim((string)($_POST['avcos'] ?? ''));
                $tsod = trim((string)($_POST['tsod'] ?? ''));
                $tsos = trim((string)($_POST['tsos'] ?? ''));
                $p = trim((string)($_POST['p'] ?? ''));

                if ($avlscod === '' || $avlscos === '') {
                    throw new Exception('AVLSC OD et AVLSC OS sont obligatoires.');
                }

                if ($hasAcq) {
                    $stU = $bdd->prepare('UPDATE acquitte_visuelle SET od_avlsc = ?, os_avlsc = ?, od_avc = ?, os_avc = ?, od_ts = ?, os_ts = ?, p = ? WHERE id_affectation = ?');
                    $stU->execute([$avlscod, $avlscos, $avcod, $avcos, $tsod, $tsos, $p, $a]);
                } else {
                    $idPatient = (int)($aff['id_patient'] ?? 0);
                    $stI = $bdd->prepare('INSERT INTO acquitte_visuelle (id_patient, od_avlsc, os_avlsc, od_avc, os_avc, od_ts, os_ts, p, id_affectation) VALUES (?,?,?,?,?,?,?,?,?)');
                    $stI->execute([$idPatient, $avlscod, $avlscos, $avcod, $avcos, $tsod, $tsos, $p, $a]);
                }
            }

            // Mise à jour traitement selon table
            if ($table === 'consultations') {
                $diagnostic = trim((string)($_POST['diagnostic'] ?? ''));
                $bilan = trim((string)($_POST['bilan'] ?? ''));
                $traitementTxt = trim((string)($_POST['traitement'] ?? ''));
                $prescription = trim((string)($_POST['prescription'] ?? ''));

                if ($diagnostic === '' || $traitementTxt === '' || $prescription === '') {
                    throw new Exception('Diagnostic, Traitement et Prescription sont obligatoires.');
                }

                $st = $bdd->prepare('UPDATE consultations SET diagnostic = ?, bilan = ?, traitement = ?, prescription = ? WHERE id_affectation = ?');
                $st->execute([$diagnostic, $bilan, $traitementTxt, $prescription, $a]);
            } elseif ($table === 'examens') {
                $diagnostic = trim((string)($_POST['diagnostic'] ?? ''));
                $resultat = trim((string)($_POST['resultat'] ?? ''));

                if ($diagnostic === '' || $resultat === '') {
                    throw new Exception('Diagnostic et Résultat sont obligatoires.');
                }

                $st = $bdd->prepare('UPDATE examens SET diagnostic = ?, resultat = ? WHERE id_affectation = ?');
                $st->execute([$diagnostic, $resultat, $a]);
            } elseif ($table === 'controles') {
                $diagnostic = trim((string)($_POST['diagnostic'] ?? ''));
                $traitementTxt = trim((string)($_POST['traitement'] ?? ''));
                $prescription = trim((string)($_POST['prescription'] ?? ''));

                if ($diagnostic === '' || $traitementTxt === '') {
                    throw new Exception('Diagnostic et Traitement sont obligatoires.');
                }

                $st = $bdd->prepare('UPDATE controles SET diagnostic = ?, traitement = ?, prescription = ? WHERE id_affectation = ?');
                $st->execute([$diagnostic, $traitementTxt, $prescription, $a]);
            } elseif ($table === 'soins') {
                $diagnostic = trim((string)($_POST['diagnostic'] ?? ''));
                $conduite = trim((string)($_POST['conduite'] ?? ''));
                $prescription = trim((string)($_POST['prescription'] ?? ''));
                if ($diagnostic === '' || $conduite === '' || $prescription === '') {
                    throw new Exception('Diagnostic, Conduite tenue et Prescription sont obligatoires.');
                }
                $st = $bdd->prepare('UPDATE soins SET diagnostic = ?, conduite = ?, prescription = ? WHERE id_affectation = ?');
                $st->execute([$diagnostic, $conduite, $prescription, $a]);
            } elseif ($table === 'mesures') {
                $refraction = trim((string)($_POST['refraction'] ?? ''));
                $od = trim((string)($_POST['od'] ?? ''));
                $os = trim((string)($_POST['os'] ?? ''));
                $addit = trim((string)($_POST['addit'] ?? ''));
                $eip = trim((string)($_POST['eip'] ?? ''));
                $details = trim((string)($_POST['details'] ?? ''));
                if ($refraction === '' || $od === '' || $os === '' || $details === '') {
                    throw new Exception('Réfraction, OD, OS et Détails sont obligatoires.');
                }
                $st = $bdd->prepare('UPDATE mesures SET refraction = ?, od = ?, os = ?, addit = ?, eip = ?, details = ? WHERE id_affectation = ?');
                $st->execute([$refraction, $od, $os, ($addit !== '' ? $addit : null), ($eip !== '' ? $eip : null), $details, $a]);
            } elseif ($table === 'rapportements') {
                $rapport = trim((string)($_POST['rapport'] ?? ''));
                $pathologie = trim((string)($_POST['pathologie'] ?? ''));
                if ($rapport === '') {
                    throw new Exception('Le rapport est obligatoire.');
                }
                if (array_key_exists('pathologie', $traitement)) {
                    $st = $bdd->prepare('UPDATE rapportements SET rapport = ?, pathologie = ? WHERE id_affectation = ?');
                    $st->execute([$rapport, ($pathologie !== '' ? $pathologie : null), $a]);
                } else {
                    $st = $bdd->prepare('UPDATE rapportements SET rapport = ? WHERE id_affectation = ?');
                    $st->execute([$rapport, $a]);
                }
            } elseif ($table === 'chirurgies') {
                $diagnostic = trim((string)($_POST['diagnostic'] ?? ''));
                $traitementTxt = trim((string)($_POST['traitement'] ?? ''));
                $protocole = trim((string)($_POST['protocole'] ?? ''));
                $prescription = trim((string)($_POST['prescription'] ?? ''));
                $dateCh = trim((string)($_POST['date_chirurgie'] ?? ''));
                $glycemie = trim((string)($_POST['glycemie'] ?? ''));
                if ($diagnostic === '' || $traitementTxt === '' || $protocole === '' || $prescription === '') {
                    throw new Exception('Diagnostic, Traitement, Protocole et Prescription sont obligatoires.');
                }
                $st = $bdd->prepare('UPDATE chirurgies SET diagnostic = ?, traitement = ?, protocole = ?, prescription = ?, date_chirurgie = ? WHERE id_affectation = ?');
                $st->execute([$diagnostic, $traitementTxt, $protocole, $prescription, ($dateCh !== '' ? $dateCh : null), $a]);

                if ($glycemie !== '') {
                    try {
                        $bdd->prepare('UPDATE acquitte_visuelle SET glycemie = ? WHERE id_affectation = ?')->execute([$glycemie, $a]);
                    } catch (Throwable $e) {
                        // ignore
                    }
                }
            } else {
                throw new Exception('Type de traitement non supporté.');
            }

            $bdd->commit();
        } catch (Throwable $e) {
            $bdd->rollBack();
            throw $e;
        }

        $_SESSION['flash_modif_traitement_success'] = 'Traitement mis à jour avec succès.';
        header('Location: modificationtraitement.php?affectation=' . (int)$a);
        exit;
    } catch (Throwable $e) {
        error_log('[modificationtraitement] ' . $e->getMessage());
        $_SESSION['flash_modif_traitement_error'] = $e->getMessage();
        header('Location: modificationtraitement.php?affectation=' . (int)$a);
        exit;
    }
}

// AJAX: mise à jour depuis le modal
if (isset($_POST['ajax_update'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $a = isset($_POST['affectation']) ? (int)$_POST['affectation'] : 0;
    if ($a <= 0) {
        echo json_encode(['success' => false, 'message' => "Affectation invalide."]);
        exit;
    }

    try {
        // Réutiliser la logique existante en appelant le même bloc (duplique minimalement ici)
        $aff = loadAffectation($bdd, $a);
        if (!$aff) {
            throw new Exception('Affectation introuvable.');
        }

        $table = detectTraitementTable($bdd, $a);
        if (!$table) {
            throw new Exception("Aucun traitement enregistré pour cette affectation.");
        }

        $traitement = loadTraitement($bdd, $table, $a);
        if (!$traitement) {
            throw new Exception('Traitement introuvable.');
        }

        $traitant = isset($traitement['traitant']) ? (int)$traitement['traitant'] : 0;
        if ($traitant > 0 && $traitant !== (int)$_SESSION['auth']) {
            throw new Exception("Vous ne pouvez pas modifier ce traitement (réalisé par un autre médecin)." );
        }

        $bdd->beginTransaction();
        try {
            // Mise à jour acquitte uniquement pour certains types
            if (in_array($table, ['consultations','examens','controles','soins'], true)) {
                $acq = loadAcquite($bdd, $a);
                $hasAcq = (bool)$acq;

                $avlscod = trim((string)($_POST['avlscod'] ?? ''));
                $avlscos = trim((string)($_POST['avlscos'] ?? ''));
                $avcod = trim((string)($_POST['avcod'] ?? ''));
                $avcos = trim((string)($_POST['avcos'] ?? ''));
                $tsod = trim((string)($_POST['tsod'] ?? ''));
                $tsos = trim((string)($_POST['tsos'] ?? ''));
                $p = trim((string)($_POST['p'] ?? ''));

                if ($avlscod === '' || $avlscos === '') {
                    throw new Exception('AVLSC OD et AVLSC OS sont obligatoires.');
                }

                if ($hasAcq) {
                    $stU = $bdd->prepare('UPDATE acquitte_visuelle SET od_avlsc = ?, os_avlsc = ?, od_avc = ?, os_avc = ?, od_ts = ?, os_ts = ?, p = ? WHERE id_affectation = ?');
                    $stU->execute([$avlscod, $avlscos, $avcod, $avcos, $tsod, $tsos, $p, $a]);
                } else {
                    $idPatient = (int)($aff['id_patient'] ?? 0);
                    $stI = $bdd->prepare('INSERT INTO acquitte_visuelle (id_patient, od_avlsc, os_avlsc, od_avc, os_avc, od_ts, os_ts, p, id_affectation) VALUES (?,?,?,?,?,?,?,?,?)');
                    $stI->execute([$idPatient, $avlscod, $avlscos, $avcod, $avcos, $tsod, $tsos, $p, $a]);
                }
            }

            if ($table === 'consultations') {
                $diagnostic = trim((string)($_POST['diagnostic'] ?? ''));
                $bilan = trim((string)($_POST['bilan'] ?? ''));
                $traitementTxt = trim((string)($_POST['traitement'] ?? ''));
                $prescription = trim((string)($_POST['prescription'] ?? ''));
                if ($diagnostic === '' || $traitementTxt === '' || $prescription === '') {
                    throw new Exception('Diagnostic, Traitement et Prescription sont obligatoires.');
                }
                $st = $bdd->prepare('UPDATE consultations SET diagnostic = ?, bilan = ?, traitement = ?, prescription = ? WHERE id_affectation = ?');
                $st->execute([$diagnostic, $bilan, $traitementTxt, $prescription, $a]);
            } elseif ($table === 'examens') {
                $diagnostic = trim((string)($_POST['diagnostic'] ?? ''));
                $resultat = trim((string)($_POST['resultat'] ?? ''));
                if ($diagnostic === '' || $resultat === '') {
                    throw new Exception('Diagnostic et Résultat sont obligatoires.');
                }
                $st = $bdd->prepare('UPDATE examens SET diagnostic = ?, resultat = ? WHERE id_affectation = ?');
                $st->execute([$diagnostic, $resultat, $a]);
            } elseif ($table === 'controles') {
                $diagnostic = trim((string)($_POST['diagnostic'] ?? ''));
                $traitementTxt = trim((string)($_POST['traitement'] ?? ''));
                $prescription = trim((string)($_POST['prescription'] ?? ''));
                if ($diagnostic === '' || $traitementTxt === '') {
                    throw new Exception('Diagnostic et Traitement sont obligatoires.');
                }
                $st = $bdd->prepare('UPDATE controles SET diagnostic = ?, traitement = ?, prescription = ? WHERE id_affectation = ?');
                $st->execute([$diagnostic, $traitementTxt, $prescription, $a]);
            } elseif ($table === 'soins') {
                $diagnostic = trim((string)($_POST['diagnostic'] ?? ''));
                $conduite = trim((string)($_POST['conduite'] ?? ''));
                $prescription = trim((string)($_POST['prescription'] ?? ''));
                if ($diagnostic === '' || $conduite === '' || $prescription === '') {
                    throw new Exception('Diagnostic, Conduite tenue et Prescription sont obligatoires.');
                }
                $st = $bdd->prepare('UPDATE soins SET diagnostic = ?, conduite = ?, prescription = ? WHERE id_affectation = ?');
                $st->execute([$diagnostic, $conduite, $prescription, $a]);
            } elseif ($table === 'mesures') {
                $refraction = trim((string)($_POST['refraction'] ?? ''));
                $od = trim((string)($_POST['od'] ?? ''));
                $os = trim((string)($_POST['os'] ?? ''));
                $addit = trim((string)($_POST['addit'] ?? ''));
                $eip = trim((string)($_POST['eip'] ?? ''));
                $details = trim((string)($_POST['details'] ?? ''));
                if ($refraction === '' || $od === '' || $os === '' || $details === '') {
                    throw new Exception('Réfraction, OD, OS et Détails sont obligatoires.');
                }
                $st = $bdd->prepare('UPDATE mesures SET refraction = ?, od = ?, os = ?, addit = ?, eip = ?, details = ? WHERE id_affectation = ?');
                $st->execute([$refraction, $od, $os, ($addit !== '' ? $addit : null), ($eip !== '' ? $eip : null), $details, $a]);
            } elseif ($table === 'rapportements') {
                $rapport = trim((string)($_POST['rapport'] ?? ''));
                $pathologie = trim((string)($_POST['pathologie'] ?? ''));
                if ($rapport === '') {
                    throw new Exception('Le rapport est obligatoire.');
                }
                if (array_key_exists('pathologie', $traitement)) {
                    $st = $bdd->prepare('UPDATE rapportements SET rapport = ?, pathologie = ? WHERE id_affectation = ?');
                    $st->execute([$rapport, ($pathologie !== '' ? $pathologie : null), $a]);
                } else {
                    $st = $bdd->prepare('UPDATE rapportements SET rapport = ? WHERE id_affectation = ?');
                    $st->execute([$rapport, $a]);
                }
            } elseif ($table === 'chirurgies') {
                $diagnostic = trim((string)($_POST['diagnostic'] ?? ''));
                $traitementTxt = trim((string)($_POST['traitement'] ?? ''));
                $protocole = trim((string)($_POST['protocole'] ?? ''));
                $prescription = trim((string)($_POST['prescription'] ?? ''));
                $dateCh = trim((string)($_POST['date_chirurgie'] ?? ''));
                $glycemie = trim((string)($_POST['glycemie'] ?? ''));
                if ($diagnostic === '' || $traitementTxt === '' || $protocole === '' || $prescription === '') {
                    throw new Exception('Diagnostic, Traitement, Protocole et Prescription sont obligatoires.');
                }
                // date_chirurgie peut être vide selon les données existantes
                $st = $bdd->prepare('UPDATE chirurgies SET diagnostic = ?, traitement = ?, protocole = ?, prescription = ?, date_chirurgie = ? WHERE id_affectation = ?');
                $st->execute([$diagnostic, $traitementTxt, $protocole, $prescription, ($dateCh !== '' ? $dateCh : null), $a]);

                // Glycémie stockée dans acquitte_visuelle si colonne présente
                if ($glycemie !== '') {
                    try {
                        $bdd->prepare('UPDATE acquitte_visuelle SET glycemie = ? WHERE id_affectation = ?')->execute([$glycemie, $a]);
                    } catch (Throwable $e) {
                        // si la colonne n'existe pas, on ignore
                    }
                }
            } else {
                throw new Exception('Type de traitement non supporté.');
            }

            $bdd->commit();
        } catch (Throwable $e) {
            $bdd->rollBack();
            throw $e;
        }

        echo json_encode(['success' => true, 'message' => 'Traitement mis à jour avec succès.']);
        exit;
    } catch (Throwable $e) {
        error_log('[modificationtraitement ajax_update] ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

$aff = null;
$table = null;
$traitement = null;
$acq = null;
$printUrl = '';
$patientTraitements = [];
$patientNom = '';

if ($affectationId > 0) {
    try {
        $aff = loadAffectation($bdd, $affectationId);
        if ($aff) {
            $table = detectTraitementTable($bdd, $affectationId);
            if ($table) {
                $traitement = loadTraitement($bdd, $table, $affectationId);
            }
            $acq = loadAcquite($bdd, $affectationId);

            if ($table === 'consultations') {
                $printUrl = 'imprimer_consultation.php?affectation=' . urlencode((string)$affectationId) . '&autoprint=0';
            } elseif ($table === 'examens') {
                $printUrl = 'imprimer_examen.php?affectation=' . urlencode((string)$affectationId) . '&autoprint=0';
            } elseif ($table === 'controles') {
                $printUrl = 'imprimer_controle.php?affectation=' . urlencode((string)$affectationId) . '&autoprint=0';
            } elseif ($table === 'soins') {
                $printUrl = '../medecinchef/imprimer_soins.php?affectation=' . urlencode((string)$affectationId) . '&autoprint=0';
            } elseif ($table === 'mesures') {
                $printUrl = '../medecinchef/imprimer_mesure.php?affectation=' . urlencode((string)$affectationId) . '&autoprint=0';
            } elseif ($table === 'rapportements') {
                $printUrl = 'imprimer_rapport.php?affectation=' . urlencode((string)$affectationId) . '&autoprint=0';
            } elseif ($table === 'chirurgies') {
                $printUrl = 'imprimer_chirurgie.php?affectation=' . urlencode((string)$affectationId) . '&autoprint=0';
            }
        }
    } catch (Throwable $e) {
        $errors = $e->getMessage();
    }
}

if ($patientId > 0) {
    if (!patientExists($bdd, $patientId)) {
        $errors = "Le numéro dossier patient saisi n'existe pas dans le système.";
    } else {
        $patientNom = nom_patient($patientId);
        $patientTraitements = listTraitements30Jours($bdd, $patientId, (int)$_SESSION['auth']);
    }
}

include('../public/header.php');
?>

<body>
<section class="body">

<?php include('../public/navbarmenu.php'); ?>

<div class="inner-wrapper">
    <section role="main" class="content-body">
        <header class="page-header">
            <h2>Modifier un traitement</h2>
        </header>

        <div class="col-md-12">
            <section class="card">
                <div class="card-body">

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger"><?php echo h($errors); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo h($success); ?></div>
                    <?php endif; ?>

                    <?php if (!($patientId > 0 && !empty($patientNom))): ?>
                        <form class="form-horizontal" method="POST" action="modificationtraitement.php">
                            <input type="hidden" name="do_search_patient" value="1">
                            <div class="row form-group pb-3">
                                <div class="col-md-4">
                                    <label class="col-form-label">Numéro dossier patient</label>
                                    <input type="number" class="form-control" name="patient" value="<?php echo $patientId > 0 ? (int)$patientId : ''; ?>" required>
                                </div>
                            </div>
                            <footer class="card-footer text-end">
                                <button type="submit" class="btn btn-primary">Rechercher les traitements (30 jours)</button>
                            </footer>
                        </form>
                    <?php else: ?>
                        <div class="d-flex justify-content-end">
                            <a class="btn btn-outline-secondary" href="modificationtraitement.php">Rechercher un autre patient</a>
                        </div>
                    <?php endif; ?>

                </div>
            </section>
        </div>

        <?php if ($patientId > 0 && !empty($patientNom)): ?>
            <div class="col-md-12">
                <section class="card">
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Patient:</strong> <?php echo h((string)$patientNom); ?>
                            &nbsp;|&nbsp;
                            <strong>Dossier:</strong> <?php echo (int)$patientId; ?>
                        </div>

                        <?php if (empty($patientTraitements)): ?>
                            <div class="alert alert-warning mb-0">Aucun traitement trouvé pour ce patient, effectué par vous, dans les 30 derniers jours.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Traitement</th>
                                            <th>Affectation</th>
                                            <th>Type</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($patientTraitements as $r): ?>
                                            <?php
                                                $dt = (string)($r['date_traitement'] ?? '');
                                                $nomType = (string)($r['nom_type'] ?? '');
                                                $idAff = (int)($r['id_affectation'] ?? 0);
                                                $src = (string)($r['source_table'] ?? '');
                                            ?>
                                            <tr>
                                                <td><?php echo h($dt); ?></td>
                                                <td><?php echo h($nomType); ?></td>
                                                <td><?php echo (int)$idAff; ?></td>
                                                <td><?php echo h($src); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary" data-affectation="<?php echo (int)$idAff; ?>" onclick="openEditTraitementModal(this)">Modifier</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                    </div>
                </section>
            </div>
        <?php endif; ?>

        <?php if ($affectationId > 0 && $aff && !$table): ?>
            <div class="col-md-12">
                <div class="alert alert-warning">Aucun traitement n'est encore enregistré pour cette affectation.</div>
            </div>
        <?php elseif ($affectationId > 0 && !$aff): ?>
            <div class="col-md-12">
                <div class="alert alert-danger">Affectation introuvable.</div>
            </div>
        <?php endif; ?>

        <!-- Modal modification traitement -->
        <div class="modal fade" id="editTraitementModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Modifier un traitement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="editTraitementModalBody" class="text-muted">Chargement…</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal impression traitement -->
        <div class="modal fade" id="printTraitementModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Impression du traitement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" style="height: 75vh;">
                        <iframe id="printTraitementIframe" src="" style="width:100%;height:100%;border:0;"></iframe>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" onclick="window.printTraitementIframeNow()">Lancer l'impression</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function openEditTraitementModal(btnOrId) {
                try {
                    var affectationId = 0;
                    if (typeof btnOrId === 'number') {
                        affectationId = btnOrId;
                    } else if (btnOrId && btnOrId.getAttribute) {
                        affectationId = parseInt(btnOrId.getAttribute('data-affectation') || '0', 10) || 0;
                    }
                    if (!affectationId) return;

                    var modalEl = document.getElementById('editTraitementModal');
                    var bodyEl = document.getElementById('editTraitementModalBody');
                    if (!modalEl || !bodyEl || !window.bootstrap) return;

                    bodyEl.textContent = 'Chargement…';
                    var instance = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
                    instance.show();

                    fetch('modificationtraitement.php?ajax_edit=1&affectation=' + encodeURIComponent(String(affectationId)), {
                        headers: { 'Accept': 'application/json' }
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data || !data.success) {
                            bodyEl.innerHTML = '<div class="alert alert-danger">' + (data && data.message ? data.message : 'Erreur de chargement') + '</div>';
                            return;
                        }
                        bodyEl.innerHTML = data.html || '';

                        // Brancher soumission AJAX
                        var form = bodyEl.querySelector('#editTraitementForm');
                        if (form) {
                            form.addEventListener('submit', function (e) {
                                e.preventDefault();
                                var fd = new FormData(form);
                                var alertEl = bodyEl.querySelector('#editTraitementAlert');
                                var btnSave = bodyEl.querySelector('#btnEditTraitementSave');
                                if (btnSave) btnSave.disabled = true;
                                fetch('modificationtraitement.php', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
                                    .then(function (r) { return r.json(); })
                                    .then(function (resp) {
                                        if (!alertEl) return;
                                        if (resp && resp.success) {
                                            alertEl.innerHTML = '<div class="alert alert-success">' + (resp.message || 'Succès') + '</div>';
                                        } else {
                                            alertEl.innerHTML = '<div class="alert alert-danger">' + (resp && resp.message ? resp.message : 'Erreur') + '</div>';
                                        }
                                    })
                                    .catch(function () {
                                        if (alertEl) alertEl.innerHTML = '<div class="alert alert-danger">Erreur réseau.</div>';
                                    })
                                    .finally(function () {
                                        if (btnSave) btnSave.disabled = false;
                                    });
                            });
                        }
                    })
                    .catch(function () {
                        bodyEl.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement.</div>';
                    });
                } catch (e) {}
            }

            window.openPrintTraitementModal = function (url) {
                try {
                    var iframe = document.getElementById('printTraitementIframe');
                    var modalEl = document.getElementById('printTraitementModal');
                    if (!iframe || !modalEl || !window.bootstrap) return;
                    iframe.src = url || '';
                    var instance = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
                    instance.show();
                } catch (e) {}
            };

            window.printTraitementIframeNow = function () {
                try {
                    var iframe = document.getElementById('printTraitementIframe');
                    if (!iframe || !iframe.contentWindow) return;
                    iframe.contentWindow.print();
                } catch (e) {}
            };

            // Si la page est appelée avec ?affectation=..., ouvrir directement le modal
            document.addEventListener('DOMContentLoaded', function () {
                try {
                    var aff = <?php echo (int)$affectationId; ?>;
                    if (aff > 0) {
                        openEditTraitementModal(aff);
                    }
                } catch (e) {}
            });
        </script>

    </section>
</div>

<?php include('../public/footer.php'); ?>
</section>
</body>
</html>
