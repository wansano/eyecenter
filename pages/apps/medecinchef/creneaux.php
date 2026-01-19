<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

$errors = 0;
$success = 0;

$medecinId = (int)($_SESSION['auth'] ?? 0);
if ($medecinId <= 0) {
    // Pas authentifié
    header('Location: ../../login.php');
    exit;
}

$jours = [
    1 => 'Lundi',
    2 => 'Mardi',
    3 => 'Mercredi',
    4 => 'Jeudi',
    5 => 'Vendredi',
    6 => 'Samedi',
];

// Grille par défaut (15 minutes) — utilisée pour proposer des cases à cocher
$plages = [
    '08:30:00','08:45:00','09:00:00','09:15:00','09:30:00','09:45:00','10:00:00','10:15:00','10:30:00','10:45:00',
    '11:00:00','11:15:00','11:30:00','11:45:00','12:00:00','12:15:00','12:30:00','12:45:00','13:00:00',
    '14:00:00','14:15:00','14:30:00','14:45:00','15:00:00','15:15:00','15:30:00','15:45:00','16:00:00','16:15:00','16:30:00','16:45:00'
];

$jourChoisi = isset($_GET['jour']) ? (int)$_GET['jour'] : 1;
if ($jourChoisi < 1 || $jourChoisi > 6) $jourChoisi = 1;

// Sauvegarde
if (isset($_POST['save_creneaux'])) {
    $jourChoisi = (int)($_POST['jour_semaine'] ?? 0);
    if ($jourChoisi < 1 || $jourChoisi > 6) {
        $errors = 1;
    } else {
        $selected = $_POST['creneaux'] ?? [];
        if (!is_array($selected)) $selected = [];

        // Normaliser HH:MM(:SS)
        $selectedNorm = [];
        foreach ($selected as $h) {
            $h = trim((string)$h);
            if ($h === '') continue;
            if (preg_match('/^\d{2}:\d{2}$/', $h)) $h .= ':00';
            if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $h)) continue;
            $selectedNorm[$h] = true;
        }
        $selectedNorm = array_keys($selectedNorm);

        try {
            $bdd->beginTransaction();

            // Remplacer la config du jour
            $del = $bdd->prepare('DELETE FROM creneaux_medecins WHERE id_medecin = ? AND jour_semaine = ?');
            $del->execute([$medecinId, $jourChoisi]);

            if (!empty($selectedNorm)) {
                $ins = $bdd->prepare('INSERT INTO creneaux_medecins (id_medecin, jour_semaine, heure, actif) VALUES (?, ?, ?, 1)');
                foreach ($selectedNorm as $h) {
                    $ins->execute([$medecinId, $jourChoisi, $h]);
                }
            }

            $bdd->commit();
            $success = 1;

            header('Location: creneaux.php?jour=' . $jourChoisi . '&success=1');
            exit;
        } catch (Throwable $e) {
            try { $bdd->rollBack(); } catch (Throwable $t) {}
            error_log('Erreur save creneaux: ' . $e->getMessage());
            $errors = 2;
        }
    }
}

// Chargement des créneaux existants
$creneauxActifs = [];
try {
    $stmt = $bdd->prepare('SELECT heure FROM creneaux_medecins WHERE id_medecin = ? AND jour_semaine = ? AND actif = 1 ORDER BY heure');
    $stmt->execute([$medecinId, $jourChoisi]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach (($rows ?: []) as $h) {
        $hh = (string)$h;
        if (preg_match('/^\d{2}:\d{2}$/', $hh)) $hh .= ':00';
        $creneauxActifs[$hh] = true;
    }
} catch (Throwable $e) {
    // Table pas encore créée
}

if (isset($_GET['success'])) {
    $success = 1;
}

include('../PUBLIC/header.php');
?>

<body>
<section class="body">

    <?php require('../PUBLIC/navbarmenu.php'); ?>

    <div class="inner-wrapper">
        <section role="main" class="content-body">
            <header class="page-header">
                <h2>Mes créneaux de disponibilité</h2>
            </header>

            <section class="card">
                <div class="card-body">

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <strong>Succès</strong><br>
                            Vos créneaux ont été enregistrés.
                        </div>
                    <?php endif; ?>

                    <?php if ($errors === 1): ?>
                        <div class="alert alert-danger">Jour de semaine invalide.</div>
                    <?php elseif ($errors === 2): ?>
                        <div class="alert alert-danger">Erreur lors de l'enregistrement. Vérifiez que la table <code>creneaux_medecins</code> existe.</div>
                    <?php endif; ?>

                    <form method="GET" class="row g-3" action="creneaux.php">
                        <div class="col-md-4">
                            <label class="col-form-label">Jour</label>
                            <select class="form-control" name="jour" onchange="this.form.submit()">
                                <?php foreach ($jours as $k => $label): ?>
                                    <option value="<?= (int)$k ?>" <?= $k === $jourChoisi ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>

                    <hr>

                    <form method="POST" action="creneaux.php?jour=<?= (int)$jourChoisi ?>">
                        <input type="hidden" name="jour_semaine" value="<?= (int)$jourChoisi ?>">
                        <input type="hidden" name="save_creneaux" value="1">

                        <div class="row">
                            <?php foreach ($plages as $h): ?>
                                <?php
                                    $checked = !empty($creneauxActifs[$h]);
                                    $display = substr($h, 0, 5);
                                ?>
                                <div class="col-md-2 col-sm-3 col-6 mb-2">
                                    <label style="display:flex;gap:8px;align-items:center;">
                                        <input type="checkbox" name="creneaux[]" value="<?= htmlspecialchars($h) ?>" <?= $checked ? 'checked' : '' ?>>
                                        <?= htmlspecialchars($display) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <footer class="card-footer text-end">
                            <button class="btn btn-primary" type="submit">Enregistrer</button>
                        </footer>
                    </form>

                    <p class="text-muted" style="margin-top:10px;">
                        Ces créneaux sont hebdomadaires. La prise de RDV continue d'exclure automatiquement les créneaux déjà occupés.
                    </p>

                </div>
            </section>
        </section>
    </div>

<?php include('../PUBLIC/footer.php'); ?>
