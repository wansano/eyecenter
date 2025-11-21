<?php
include('../PUBLIC/connect.php');
session_start();

function getTraitementsAvecNomTypeParPeriode(string $date_debut, string $date_fin, int $userId, PDO $bdd): array {
    $tables = ['consultations', 'controles', 'examens', 'chirurgies', 'soins', 'mesures'];
    $queryParts = [];
    $params = [];

    foreach ($tables as $index => $table) {
        $userParam = ":userId{$index}";
        $dateDebParam = ":dateDeb{$index}";
        $dateFinParam = ":dateFin{$index}";

        $queryParts[] = "
            SELECT id_type, COUNT(*) AS count 
            FROM {$table} 
            WHERE traitant = {$userParam} 
              AND DATE(date_traitement) BETWEEN {$dateDebParam} AND {$dateFinParam}
            GROUP BY id_type
        ";

        $params[$userParam]   = $userId;
        $params[$dateDebParam] = $date_debut;
        $params[$dateFinParam] = $date_fin;
    }

    $unionSql = implode(' UNION ALL ', $queryParts);

    $finalSql = "
        SELECT t.id_type, t.nom_type, SUM(sub.count) AS total
        FROM ({$unionSql}) AS sub
        JOIN traitements t ON t.id_type = sub.id_type
        GROUP BY t.id_type, t.nom_type
        ORDER BY t.id_type
    ";

    $stmt = $bdd->prepare($finalSql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Valeurs par défaut : médecin courant et date du jour
$userId = isset($_GET['medecin']) ? (int)$_GET['medecin'] : (int)$_SESSION['auth'];
$today = date('Y-m-d');
$date_debut = isset($_GET['date_debut']) && $_GET['date_debut'] !== '' ? $_GET['date_debut'] : $today;
$date_fin   = isset($_GET['date_fin'])   && $_GET['date_fin']   !== '' ? $_GET['date_fin']   : $today;

$traitements = getTraitementsAvecNomTypeParPeriode($date_debut, $date_fin, $userId, $bdd);

include('../public/header.php');
?>
<body>
    <section class="body">

        <?php require('../public/navbarmenu.php'); ?>

        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Interrogation des réalisations</h2>
                </header>

                <div class="col-md-12">
                    <section class="card">
                        <div class="card-body">
                            <form method="get" class="form-inline mb-3">
                                <div class="row">
                                    <div class="col-md-3 mb-2">
                                        <label class="col-form-label" for="medecin">Médecin</label>
                                        <select name="medecin" id="medecin" class="form-control populate" data-plugin-selectTwo>
                                            <?php
                                            $stmtMed = $bdd->prepare('SELECT id, pseudo FROM users WHERE type IN (1, 2, 3, 4, 15) AND status = 1 ORDER BY pseudo');
                                            $stmtMed->execute();
                                            while ($m = $stmtMed->fetch(PDO::FETCH_ASSOC)) {
                                                $selected = ($m['id'] == $userId) ? 'selected' : '';
                                                echo '<option value="'.$m['id'].'" '.$selected.'>'.$m['pseudo'].'</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="col-form-label" for="date_debut">Du</label>
                                        <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="col-form-label" for="date_fin">Au</label>
                                        <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>">
                                    </div>
                                    <div class="col-md-3 mb-2 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary mr-2">Rechercher</button>
                                        <?php if (!empty($traitements)): ?>
                                            <a href="<?='../impression/_realisationsmedecin.php?medecin=' . urlencode($userId) . '&debut=' . urlencode($date_debut) . '&fin=' . urlencode($date_fin);?>" target="_blank" class="btn btn-default">
                                                <i class="fa fa-file-pdf-o"></i> Imprimer PDF
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>

                            <hr>

                            <h5>Prestations de <?= htmlspecialchars(traitant($userId)) ?> du <?= htmlspecialchars($date_debut) ?> au <?= htmlspecialchars($date_fin) ?> :</h5>
                            <div class="row mt-3">
                                <?php if (empty($traitements)): ?>
                                    <div class="col-md-12">
                                        <div class="alert alert-info">Aucune prestation trouvée sur la période.</div>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($traitements as $row): ?>
                                        <div class="col-md-2 mb-3">
                                            <section class="card">
                                                <div class="card-body text-center">
                                                    <div class="h4 font-weight-bold text-primary mb-1"><?= htmlspecialchars($row['total']) ?></div>
                                                    <p class="text-xs text-muted mb-0"><?= htmlspecialchars($row['nom_type']) ?></p>
                                                </div>
                                            </section>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                </div>
            </section>
        </div>
        <?php include('../public/footer.php'); ?>
</body>
</html>
