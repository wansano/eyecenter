<?php
include('../PUBLIC/connect.php');
session_start();

// Fonction optimisée pour récupérer les données par type de traitement
function getTraitementsDuJourAvecNomType(string $date, int $userId, PDO $bdd): array {
    $tables = ['consultations', 'controles', 'examens', 'chirurgies', 'soins', 'mesures'];
    $queryParts = [];
    $params = [];

    foreach ($tables as $index => $table) {
        $userParam = ":userId{$index}";
        $dateParam = ":date{$index}";

        $queryParts[] = "
            SELECT id_type, COUNT(*) AS count 
            FROM {$table} 
            WHERE traitant = {$userParam} AND DATE(date_traitement) = {$dateParam}
            GROUP BY id_type
        ";

        $params[$userParam] = $userId;
        $params[$dateParam] = $date;
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


include('../public/header.php');
?>
<body>
    <section class="body">

        <?php require('../public/navbarmenu.php'); ?>

        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Prestation journalière</h2>
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <section class="panel panel-transparent">
                        <div class="row">
                            <header class="panel panel-transparent">
                                <h4 class="panel-title">Prestation du jour de <?= htmlspecialchars(traitant($_SESSION['auth'])) ?>.</h4>
                            </header>
                            <?php
                                $userId = $_SESSION['auth'];
                                    $today = date('Y-m-d');

                                    $traitements = getTraitementsDuJourAvecNomType($today, $userId, $bdd);
                                    
                                    // var_dump($traitements); // Pour vérifier les données récupérées
                                    // return; // Arrêter l'exécution pour le débogage

                                    foreach ($traitements as $row) {
                                        echo '
                                            <div class="col-md-2">
                                                <section class="card">
                                                    <div class="card-body text-center">
                                                        <div class="h4 font-weight-bold text-primary mb-1">' . htmlspecialchars($row['total']) . '</div>
                                                        <p class="text-xs text-muted mb-0">' . htmlspecialchars($row['nom_type']) . '</p>
                                                    </div>
                                                </section>
                                            </div>
                                        ';
                                    }

                            ?>
                        </div>
                    </section>
                </div>
                <!-- end: page -->
            </section>
        </div>
        <?php include('../public/footer.php'); ?>