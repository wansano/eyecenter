<?php
include('../PUBLIC/connect.php');
include('../PUBLIC/fonction.php');
session_start();
    $errors = 0; $existe = 0;
    if (isset($_POST['recherche'])) {
        $recherche = trim($_POST['recherche']);
        if ($recherche === '') {
            $existe = 1;
        } else {
            $req1 = $bdd->prepare('SELECT 1 FROM affectations WHERE id_patient=? LIMIT 1');
            $req1->execute(array($recherche));
            if (!$req1->fetch()) {
                $existe = 1;
            } else {
                header('Location: reimpressiondocument.php?recherche=' . urlencode($recherche));
                exit();
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
                    <h2>Recherche documentation d'un patient</h2>
                </header>

                <!-- start: page -->

                <div class="col-md-12">
                    <section class="card">
                        <div class="card-body">
                            <?php
                                if ($existe==1) {
                                echo '
                                    <div class="alert alert-danger">
                                        <li>Aucun document trouvé dans le système pour cet identifiant saisie.</li>
                                    </div>
                                    ';
                                    } 
                            ?>
                            <form class="form-horizontal" novalidate method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
                                <div class="row form-group pb-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Saisir le n° du dossier du patient</label>
                                            <input type="text" class="form-control" name="recherche" id="formGroupExampleInput" placeholder="" required>
                                        </div>
                                    </div>
                                </div>
                                <footer class="card-footer text-end">
                                    <button class="btn btn-primary" type="submit">Rechercher</button>
                                </footer>
                            </form>
                    </section>
                </div> <br>

                <section>
                    <?php
                        if (isset($_GET['recherche'])) {
                            $recherche = $_GET['recherche'];
                            echo '
                        <div class="col-md-12">
                            <header class="card-header">
                                <h3 class="card-title">Documents pour ' . htmlspecialchars(nom_patient($recherche)) . '</h3>
                            </header>
                            <div class="card-body">
                                <table class="table table-responsive-md table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>DATE</th>
                                            <th>AFFECTATION</th>
                                            <th>MOTIF</th>
                                            <th>DOCUMENT</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
                                        $tables = [
                                            [
                                                'table' => 'mesures',
                                                'label' => 'Mesure',
                                                'date' => 'date_traitement',
                                                'id' => 'id_mesure',
                                                'file' => '../medecin/imprimer_mesure.php',
                                                'has_type' => true,
                                                'custom_query' => false
                                            ],
                                            [
                                                'table' => 'paiements',
                                                'label' => 'Paiement',
                                                'date' => 'datepaiement',
                                                'id' => 'id_paiement',
                                                'file' => '../caisse/imprimer_recu.php',
                                                'has_type' => false,
                                                'custom_query' => true
                                            ]
                                        ];
                                        $found = false;
                                        foreach ($tables as $meta) {
                                            $fields = $meta['has_type'] ? 'id_type' : 'types AS id_type';
                                            if ($meta['table'] === 'paiements') {
                                                // Correction : jointure avec affectations pour retrouver le patient et utiliser le champ 'types'
                                                $sql = "SELECT {$meta['id']} AS id_doc, {$meta['date']} AS date_doc, paiements.id_affectation, $fields FROM paiements INNER JOIN affectations ON paiements.id_affectation = affectations.id_affectation WHERE affectations.id_patient = ? ORDER BY {$meta['date']} DESC";
                                            } else {
                                                $sql = "SELECT {$meta['id']} AS id_doc, {$meta['date']} AS date_doc, id_affectation, $fields FROM {$meta['table']} WHERE id_patient = ? ORDER BY {$meta['date']} DESC";
                                            }
                                            $stmt = $bdd->prepare($sql);
                                            $stmt->execute([$recherche]);
                                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                $found = true;
                                                $motif = $meta['has_type'] ? model($row['id_type']) : model($row['id_type']);
                                                $url = $meta['file'] . '?affectation=' . urlencode($row['id_affectation']);
                                                echo '<tr>';
                                                echo '<td>' . htmlspecialchars($row['date_doc']) . '</td>';
                                                echo '<td>EC_AFF' . htmlspecialchars($row['id_affectation']) . '</td>';
                                                echo '<td>' . htmlspecialchars($motif) . '</td>';
                                                echo '<td><a href="' . htmlspecialchars($url) . '" target="_blank" class="btn btn-sm btn-info">Document ' . htmlspecialchars($motif) . '</a></td>';
                                                echo '</tr>';
                                            }
                                        }
                                        if (!$found) {
                                            echo '<tr><td colspan="4" class="text-center text-danger">Aucun document trouvé pour ce patient.</td></tr>';
                                        }
                                 echo '</tbody>
                                </table>
                            </div>';
                        }
                    ?>
                    <!-- end: page -->
                    </section>
        </div>

    </section>
    <?php include('../PUBLIC/footer.php');?>