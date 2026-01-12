<?php
include('../PUBLIC/connect.php');
include('../PUBLIC/fonction.php');
session_start();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$errors = 0;
$ok = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;

$code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
$monture = null;
$showModal = false;
$isLocked = false; // vendue => aucune modification
$isReturnLocked = false; // retournée => autres champs verrouillés (retour modifiable)

// Liste des marques (pour le select)
$marques = [];
try {
    $stmt = $bdd->prepare('SELECT id_marque, marque FROM marques WHERE status = 1 ORDER BY marque');
    $stmt->execute();
    $marques = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    error_log('editproduct.php marques error: ' . $e->getMessage());
}

// PRG: Afficher (recherche par code)
if (isset($_POST['afficher'])) {
    $searchCode = isset($_POST['code_monture']) ? trim((string)$_POST['code_monture']) : '';
    if ($searchCode === '') {
        $errors = 1;
    } else {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?code=' . urlencode($searchCode));
        exit;
    }
}

// Modifier
if (isset($_POST['modifier'])) {
    $idMonture = isset($_POST['id_monture']) ? (int)$_POST['id_monture'] : 0;
    $codeMonture = isset($_POST['code_monture']) ? trim((string)$_POST['code_monture']) : '';
    $idMarque = isset($_POST['id_marque']) ? (int)$_POST['id_marque'] : 0;
    $couleur = isset($_POST['couleur']) ? trim((string)$_POST['couleur']) : '';
    $prixRaw = isset($_POST['prix']) ? trim((string)$_POST['prix']) : '';
    $prix = ($prixRaw === '') ? null : (float)$prixRaw;
    $monturePour = isset($_POST['monture_pour']) ? trim((string)$_POST['monture_pour']) : '';
    $retour = isset($_POST['retour']) ? (int)$_POST['retour'] : 0;

    if ($idMonture <= 0) {
        $errors = 2;
    } else {
        try {
            // Recharger l'état actuel (verrouillage: vendu/retour)
            $stmt = $bdd->prepare('SELECT code_monture, id_marque, couleur, prix, monture_pour, vendu, retour FROM montures WHERE id_monture = ? LIMIT 1');
            $stmt->execute([$idMonture]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$current) {
                $errors = 4;
            } else {
                $currentCode = (string)($current['code_monture'] ?? '');
                $currentVendu = (int)($current['vendu'] ?? 0);
                $currentRetour = (int)($current['retour'] ?? 0);

                // Vendue => aucune modification (y compris retour)
                if ($currentVendu === 1) {
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?code=' . urlencode($currentCode) . '&ok=4');
                    exit;
                }

                // Sanitize retour
                $retour = ($retour === 1) ? 1 : 0;

                // Si déjà retournée: seules les infos de retour sont modifiables
                if ($currentRetour === 1) {
                    $stmt = $bdd->prepare('UPDATE montures SET retour = ? WHERE id_monture = ?');
                    $stmt->execute([$retour, $idMonture]);
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?code=' . urlencode($currentCode) . '&ok=2');
                    exit;
                }

                // Sinon (retour=0): on peut modifier les champs + retour
                if ($codeMonture === '' || $couleur === '') {
                    $errors = 2;
                } else {

            // Unicité du code (hors cette monture)
            $stmt = $bdd->prepare('SELECT 1 FROM montures WHERE code_monture = ? AND id_monture <> ? LIMIT 1');
            $stmt->execute([$codeMonture, $idMonture]);
            if ($stmt->fetchColumn()) {
                header('Location: ' . $_SERVER['PHP_SELF'] . '?code=' . urlencode($codeMonture) . '&ok=3');
                exit;
            }

            // no_livraison non modifiable sur cette page
                // vendu non modifiable sur cette page
                $stmt = $bdd->prepare('UPDATE montures SET code_monture = ?, id_marque = ?, couleur = ?, prix = ?, monture_pour = ?, retour = ? WHERE id_monture = ?');
            $stmt->execute([
                $codeMonture,
                    ($idMarque > 0 ? $idMarque : null),
                $couleur,
                $prix,
                ($monturePour === '' ? null : $monturePour),
                    $retour,
                    $idMonture,
            ]);

            header('Location: ' . $_SERVER['PHP_SELF'] . '?code=' . urlencode($codeMonture) . '&ok=2');
            exit;
                }
            }
        } catch (PDOException $e) {
            error_log('editproduct.php update error: ' . $e->getMessage());
            $errors = 4;
        }
    }
}

// Charger la monture si code présent
if ($code !== '') {
    try {
        $stmt = $bdd->prepare('SELECT m.*, ma.marque AS marque_nom FROM montures m LEFT JOIN marques ma ON ma.id_marque = m.id_marque WHERE m.code_monture = ? LIMIT 1');
        $stmt->execute([$code]);
        $monture = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($monture) {
            $showModal = true;
            $isLocked = ((int)($monture['vendu'] ?? 0) === 1);
            $isReturnLocked = !$isLocked && ((int)($monture['retour'] ?? 0) === 1);
        }
    } catch (PDOException $e) {
        error_log('editproduct.php fetch monture error: ' . $e->getMessage());
        $errors = 4;
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
                    <h2>Modifier une monture</h2>
                </header>

                <div class="col-md-12">
                    <section class="card">
                        <div class="card-body">
                            <?php
                            if ($ok === 2) {
                                echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Monture modifiée avec succès.</li></div>';
                            }
                            if ($ok === 3) {
                                echo '<div class="alert alert-warning"><li>Modification non effectuée : ce code monture existe déjà.</li></div>';
                            }
                            if ($ok === 4) {
                                echo '<div class="alert alert-warning"><li>Modification impossible : la monture a déjà été vendue.</li></div>';
                            }
                            if ($errors === 1) {
                                echo '<div class="alert alert-warning"><li>Veuillez saisir le code de la monture.</li></div>';
                            }
                            if ($errors === 2) {
                                echo '<div class="alert alert-warning"><li>Champs invalides. Merci de vérifier les informations.</li></div>';
                            }
                            if ($errors === 4) {
                                echo '<div class="alert alert-danger"><li>Erreur technique. Réessayez, puis contactez l\'administrateur si besoin.</li></div>';
                            }
                            if ($code !== '' && !$monture && $errors === 0) {
                                echo '<div class="alert alert-danger"><li>Le code monture saisi n\'existe pas dans le système.</li></div>';
                            }
                            ?>

                            <form class="form-horizontal" novalidate="novalidate" method="POST" action="<?php echo h($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
                                <div class="row form-group pb-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="code_monture">Saisir le code de la monture</label>
                                            <input type="text" pattern="^\S*$" oninput="this.value = this.value.replace(/\s/g, '')" class="form-control" name="code_monture" id="code_monture" value="<?php echo h($code); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <footer class="card-footer text-end">
                                    <button class="btn btn-primary" type="submit" name="afficher" value="1">Afficher</button>
                                </footer>
                            </form>
                        </div>
                    </section>
                </div>

                <!-- Modal édition monture -->
                <div class="modal fade" id="editMontureModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <form method="POST" action="<?php echo h($_SERVER['PHP_SELF']); ?>">
                                <div class="modal-header">
                                    <h5 class="modal-title">Informations de la monture</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="modifier" value="1">
                                    <input type="hidden" name="id_monture" value="<?php echo h($monture['id_monture'] ?? ''); ?>">

                                    <?php
                                    if ($isLocked) {
                                        echo '<div class="alert alert-warning"><li>Cette monture est vendue : modification désactivée.</li></div>';
                                    } elseif ($isReturnLocked) {
                                        echo '<div class="alert alert-warning"><li>Cette monture est retournée : seuls le statut retour peut être modifié.</li></div>';
                                    }
                                    ?>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group pb-3">
                                                <label class="col-form-label" for="edit_code_monture">Code de la monture</label>
                                                <input type="text" pattern="^\S*$" oninput="this.value = this.value.replace(/\s/g, '')" class="form-control" name="code_monture" id="edit_code_monture" value="<?php echo h($monture['code_monture'] ?? ''); ?>" required <?php echo ($isLocked || $isReturnLocked) ? 'disabled' : ''; ?>>
                                                <?php if ($isLocked || $isReturnLocked) { echo '<input type="hidden" name="code_monture" value="' . h($monture['code_monture'] ?? '') . '">'; } ?>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group pb-3">
                                                <label class="col-form-label" for="edit_no_livraison">N° livraison</label>
                                                <input type="text" class="form-control" id="edit_no_livraison" value="<?php echo h($monture['no_livraison'] ?? ''); ?>" disabled>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group pb-3">
                                                <label class="col-form-label" for="edit_id_marque">Marque</label>
                                                <select class="form-control populate" name="id_marque" id="edit_id_marque" <?php echo ($isLocked || $isReturnLocked) ? 'disabled' : ''; ?>>
                                                    <option value="0">---- choisir ----</option>
                                                    <?php
                                                    $selectedMarque = (int)($monture['id_marque'] ?? 0);
                                                    foreach ($marques as $m) {
                                                        $idM = (int)($m['id_marque'] ?? 0);
                                                        $label = (string)($m['marque'] ?? '');
                                                        if ($idM <= 0) { continue; }
                                                        $sel = ($selectedMarque === $idM) ? ' selected' : '';
                                                        echo '<option value="' . h($idM) . '"' . $sel . '>' . h($label) . '</option>';
                                                    }
                                                    ?>
                                                </select>
                                                <?php if ($isLocked || $isReturnLocked) { echo '<input type="hidden" name="id_marque" value="' . h($monture['id_marque'] ?? 0) . '">'; } ?>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group pb-3">
                                                <label class="col-form-label" for="edit_couleur">Couleur</label>
                                                <input type="text" class="form-control" name="couleur" id="edit_couleur" value="<?php echo h($monture['couleur'] ?? ''); ?>" required <?php echo ($isLocked || $isReturnLocked) ? 'disabled' : ''; ?>>
                                                <?php if ($isLocked || $isReturnLocked) { echo '<input type="hidden" name="couleur" value="' . h($monture['couleur'] ?? '') . '">'; } ?>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group pb-3">
                                                <label class="col-form-label" for="edit_monture_pour">Type de la monture</label>
                                                <?php $mp = (string)($monture['monture_pour'] ?? ''); ?>
                                                <select class="form-control populate" name="monture_pour" id="edit_monture_pour" <?php echo ($isLocked || $isReturnLocked) ? 'disabled' : ''; ?>>
                                                    <option value="">---- choisir ----</option>
                                                    <option value="Adulte Homme" <?php echo ($mp === 'Adulte Homme') ? 'selected' : ''; ?>>Adulte Homme</option>
                                                    <option value="Adulte Femme" <?php echo ($mp === 'Adulte Femme') ? 'selected' : ''; ?>>Adulte Femme</option>
                                                    <option value="Enfant" <?php echo ($mp === 'Enfant') ? 'selected' : ''; ?>>Enfant</option>
                                                </select>
                                                <?php if ($isLocked || $isReturnLocked) { echo '<input type="hidden" name="monture_pour" value="' . h($monture['monture_pour'] ?? '') . '">'; } ?>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group pb-3">
                                                <label class="col-form-label" for="edit_prix">Prix</label>
                                                <input type="number" step="50000" class="form-control" name="prix" id="edit_prix" value="<?php echo h($monture['prix'] ?? ''); ?>" <?php echo ($isLocked || $isReturnLocked) ? 'disabled' : ''; ?>>
                                                <?php if ($isLocked || $isReturnLocked) { echo '<input type="hidden" name="prix" value="' . h($monture['prix'] ?? '') . '">'; } ?>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group pb-3">
                                                <label class="col-form-label" for="edit_vendu">Statut</label>
                                                <?php $venduVal = (int)($monture['vendu'] ?? 0); ?>
                                                <select class="form-control populate" id="edit_vendu" disabled>
                                                    <option value="0" <?php echo ($venduVal === 0) ? 'selected' : ''; ?>>Disponible</option>
                                                    <option value="1" <?php echo ($venduVal === 1) ? 'selected' : ''; ?>>Déjà vendu</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group pb-3">
                                                <label class="col-form-label" for="edit_retour">Retour</label>
                                                <?php $retourVal = (int)($monture['retour'] ?? 0); ?>
                                                <select class="form-control populate" name="retour" id="edit_retour" <?php echo $isLocked ? 'disabled' : ''; ?>>
                                                    <option value="0" <?php echo ($retourVal === 0) ? 'selected' : ''; ?>>Non</option>
                                                    <option value="1" <?php echo ($retourVal === 1) ? 'selected' : ''; ?>>Oui</option>
                                                </select>
                                                <?php if ($isLocked) { echo '<input type="hidden" name="retour" value="' . h($retourVal) . '">'; } ?>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group pb-3">
                                                <label class="col-form-label">Date création</label>
                                                <input type="text" class="form-control" value="<?php echo h($monture['date_creation'] ?? ''); ?>" disabled>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group pb-3">
                                                <label class="col-form-label">Date modification</label>
                                                <input type="text" class="form-control" value="<?php echo h($monture['date_modification'] ?? ''); ?>" disabled>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
                                    <?php if (!$isLocked) { echo '<button type="submit" class="btn btn-primary">Modifier</button>'; } ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </section>
        </div>
    </section>

    <script>
        (function () {
            var shouldOpen = <?php echo $showModal ? 'true' : 'false'; ?>;
            if (!shouldOpen) return;
            document.addEventListener('DOMContentLoaded', function () {
                try {
                    var el = document.getElementById('editMontureModal');
                    if (!el || typeof bootstrap === 'undefined') return;
                    var modal = new bootstrap.Modal(el);
                    modal.show();
                } catch (e) {
                    // noop
                }
            });
        })();
    </script>

    <?php include('../PUBLIC/footer.php'); ?>
