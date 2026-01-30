<?php
include('../PUBLIC/connect.php');
session_start();

// Flash messages (délivrance)
$flashSuccess = $_SESSION['flash_delivrance_success'] ?? '';
$flashError = $_SESSION['flash_delivrance_error'] ?? '';
unset($_SESSION['flash_delivrance_success'], $_SESSION['flash_delivrance_error']);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

    $errors=0; $existe=0;
    $openModal = false;
    $monture = null;

    if (!isset($devise) || trim((string)$devise) === '') {
        $devise = 'GNF';
    }

    // Soumission de recherche (PRG)
    if (isset($_POST['do_search'])) {
        $code = isset($_POST['code_produit']) ? trim((string)$_POST['code_produit']) : '';
        if ($code === '') {
            $existe = 1;
        } else {
            $req1 = $bdd->prepare('SELECT 1 FROM montures WHERE code_monture = ? LIMIT 1');
            $req1->execute([$code]);
            if ($req1->fetchColumn()) {
                header('Location: findingproduct.php?codeproduit=' . urlencode($code));
                exit();
            }
            $existe = 1;
        }
    }

    // Chargement produit via GET (ouvre le modal)
    if (isset($_GET['codeproduit'])) {
        $codeGet = trim((string)$_GET['codeproduit']);
        if ($codeGet !== '') {
            // Détection colonnes "délivrance" selon schéma
            $vpCols = [];
            try {
                $stCols = $bdd->query('SHOW COLUMNS FROM ventes_produits');
                while ($r = $stCols->fetch(PDO::FETCH_ASSOC)) {
                    if (isset($r['Field'])) {
                        $vpCols[(string)$r['Field']] = true;
                    }
                }
            } catch (Throwable $e) {
                $vpCols = [];
            }

            $selectVp = ', vp.id_vente AS vp_id_vente';
            if (isset($vpCols['delivre'])) {
                $selectVp .= ', vp.delivre AS vp_delivre';
            }
            if (isset($vpCols['date_delivrance'])) {
                $selectVp .= ', vp.date_delivrance AS vp_date_delivrance';
            }

            $st = $bdd->prepare(
                'SELECT m.*, ma.marque AS marque_nom, l.lentille AS lentille_nom'
                . $selectVp . ' '
                . 'FROM montures m '
                . 'LEFT JOIN marques ma ON ma.id_marque = m.id_marque '
                . 'LEFT JOIN lentilles l ON l.id_lentille = m.id_lentille '
                . 'LEFT JOIN ventes_produits vp '
                . '  ON vp.id_monture = m.id_monture '
                . ' AND vp.id_vente = (SELECT MAX(vp2.id_vente) FROM ventes_produits vp2 WHERE vp2.id_monture = m.id_monture) '
                . 'WHERE m.code_monture = ? LIMIT 1'
            );
            $st->execute([$codeGet]);
            $monture = $st->fetch(PDO::FETCH_ASSOC);
            if ($monture) {
                $openModal = true;
            } else {
                $existe = 1;
            }
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
                    <h2>Rechercher un produit dans la boutique</h2>
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <section class="card">
                        <div class="card-body">
                            <?php if ($existe==1): ?>
                                <div class="alert alert-danger">
                                    <li>Le code produit saisi n'existe pas dans le système.</li>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($flashError)): ?>
                                <div class="alert alert-danger">
                                    <?php echo h($flashError); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($flashSuccess)): ?>
                                <div class="alert alert-success">
                                    <?php echo h($flashSuccess); ?>
                                </div>
                            <?php endif; ?>

                            <form class="form-horizontal" novalidate="novalidate" method="POST" action="findingproduct.php" enctype="multipart/form-data">
                                <input type="hidden" name="do_search" value="1">
                                <div class="row form-group pb-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="code_produit">Saisir le code de la monture</label>
                                            <input type="text" class="form-control" name="code_produit" id="code_produit" required value="<?php echo isset($_GET['codeproduit']) ? h($_GET['codeproduit']) : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                <footer class="card-footer text-end">
                                    <button class="btn btn-primary" type="submit">Rechercher</button>
                                </footer>
                            </form>
                        </div>
                    </section>
                </div>

                <?php if ($monture): ?>
                    <?php
                        $codeMonture = (string)($monture['code_monture'] ?? '');
                        $marqueNom = (string)($monture['marque_nom'] ?? '');
                        $couleur = (string)($monture['couleur'] ?? '');
                        $typeMonture = (string)($monture['monture_pour'] ?? '');
                        $lentilleNom = (string)($monture['lentille_nom'] ?? '');
                        $vendu = (int)($monture['vendu'] ?? 0);
                        $retour = (int)($monture['retour'] ?? 0);
                        $prix = (float)($monture['prix'] ?? 0);

                        $isDelivre = false;
                        if (isset($monture['vp_delivre']) && (int)$monture['vp_delivre'] === 1) {
                            $isDelivre = true;
                        }
                        if (!$isDelivre && !empty($monture['vp_date_delivrance'])) {
                            $isDelivre = true;
                        }
                        if (!$isDelivre && isset($_GET['delivre']) && (string)$_GET['delivre'] === '1') {
                            $isDelivre = true;
                        }
                        if (!$isDelivre && !empty($flashSuccess)) {
                            // Cas PRG/redirect: on vient de délivrer
                            $isDelivre = true;
                        }

                        if ($vendu === 1) {
                            $statusLabel = 'Déjà vendu';
                            $statusClass = 'bg-danger';
                        } elseif ($retour === 1) {
                            $statusLabel = 'Retournée';
                            $statusClass = 'bg-warning';
                        } else {
                            $statusLabel = 'Disponible';
                            $statusClass = 'bg-success';
                        }
                    ?>

                    <div class="modal fade" id="productInfoModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Informations de la monture</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <?php if (!empty($flashError)): ?>
                                        <div class="alert alert-danger">
                                            <?php echo h($flashError); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($flashSuccess)): ?>
                                        <div class="alert alert-success">
                                            <?php echo h($flashSuccess); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="row form-group pb-3">
                                        <div class="col-md-4">
                                            <label class="col-form-label">Code monture</label>
                                            <input type="text" class="form-control" value="<?php echo h($codeMonture); ?>" disabled>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="col-form-label">Marque</label>
                                            <input type="text" class="form-control" value="<?php echo h($marqueNom); ?>" disabled>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="col-form-label">Couleur</label>
                                            <input type="text" class="form-control" value="<?php echo h($couleur); ?>" disabled>
                                        </div>
                                    </div>

                                    <div class="row form-group pb-3">
                                        <div class="col-md-4">
                                            <label class="col-form-label">Statut</label>
                                            <div style="background: #fff;">
                                                <span class="badge <?php echo h($statusClass); ?>"><?php echo h($statusLabel); ?></span>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="col-form-label">Prix de la monture</label>
                                            <input type="text" class="form-control" value="<?php echo h(number_format($prix, 0, ',', ' ') . ' ' . $devise); ?>" disabled>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="col-form-label">Type de monture</label>
                                            <input type="text" class="form-control" value="<?php echo h($typeMonture); ?>" disabled>
                                        </div>
                                    </div>

                                    <?php if ($vendu === 1): ?>
                                        <div class="row form-group pb-3">
                                            <div class="col-md-4">
                                                <label class="col-form-label">Lentille vendue avec la monture</label>
                                                <input type="text" class="form-control" value="<?php echo h($lentilleNom !== '' ? $lentilleNom : '-'); ?>" disabled>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="modal-footer">
                                    <?php if ($vendu === 1 && $retour !== 1 && !$isDelivre): ?>
                                        <a class="btn btn-success" href="delivrerlunette.php?id_monture=<?php echo (int)($monture['id_monture'] ?? 0); ?>&code=<?php echo urlencode($codeMonture); ?>">Délivrer la lunette</a>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        (function () {
                            var shouldOpen = <?php echo $openModal ? 'true' : 'false'; ?>;
                            if (!shouldOpen) return;
                            document.addEventListener('DOMContentLoaded', function () {
                                try {
                                    var el = document.getElementById('productInfoModal');
                                    if (!el || typeof bootstrap === 'undefined') return;
                                    new bootstrap.Modal(el).show();
                                } catch (e) {}
                            });
                        })();
                    </script>
                <?php endif; ?>
                <!-- end: page -->
            </section>
        </div>
        <?php include('../PUBLIC/footer.php');?>
