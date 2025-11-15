<?php
// Includes dossier public corrigés
include('../public/connect.php');
include('../public/fonction.php');
session_start();

$errors = 0; 
$existe = 0; // indicateur "aucun document" ou erreur saisie

// Traitement de la recherche
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $recherche = filter_input(INPUT_POST, 'recherche', FILTER_VALIDATE_INT);
        if ($recherche !== false && $recherche !== null) {
                // Vérifier existence d'au moins une affectation pour ce patient (requête rapide)
                $req1 = $bdd->prepare('SELECT 1 FROM affectations WHERE id_patient = ? LIMIT 1');
                $req1->execute([$recherche]);
                if ($req1->fetch()) {
                        // Redirection propre (évite echo <script>)
                        header('Location: reimpressiondocument.php?recherche=' . $recherche);
                        exit;
                } else {
                        $existe = 1; // aucun document
                }
        } else {
                $existe = 2; // saisie invalide
        }
}

include('../public/header.php');
?>

<body>
    <section class="body">

    <?php require('../public/navbarmenu.php'); ?>

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
                                if ($existe === 1) {
                                    echo '<div class="alert alert-warning"><li>Aucun document trouvé pour cet identifiant.</li></div>';
                                } elseif ($existe === 2) {
                                    echo '<div class="alert alert-danger"><li>Identifiant patient invalide. Saisir un nombre entier.</li></div>';
                                }
                            ?>
                            <form class="form-horizontal" novalidate="novalidate" method="POST"
                                action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>"
                                enctype="multipart/form-data">
                                <div class="row form-group pb-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Saisir
                                                le n° du dossier du patient</label>
                                            <input type="number" min="1" class="form-control" name="recherche"
                                                id="formGroupExampleInput" placeholder="Ex: 42" required>
                                        </div>
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
                            $patientId = filter_input(INPUT_GET, 'recherche', FILTER_VALIDATE_INT);
                            if ($patientId) {
                                echo '<div class="col-md-12">';
                                echo '<header class="card-header">';
                                echo '<h3 class="card-title">Documents pour ' . htmlspecialchars(nom_patient($patientId)) . '</h3>';
                                echo '</header>';
                                echo '<div class="card-body">';
                                echo '<table class="table table-responsive-md table-striped mb-0">';
                                echo '<thead><tr><th>DATE</th><th>AFFECTATION</th><th>MOTIF</th><th>TRAITANT</th><th>DOCUMENT</th></tr></thead><tbody>';
                                $tables = [
                                    ['table' => 'consultations', 'label' => 'Consultation', 'date' => 'date_traitement', 'id' => 'id_consultation', 'file' => 'imprimer_consultation.php'],
                                    ['table' => 'chirurgies', 'label' => 'Chirurgie', 'date' => 'date_traitement', 'id' => 'id_chirurgie', 'file' => 'imprimer_chirurgie.php'],
                                    ['table' => 'soins', 'label' => 'Soin', 'date' => 'date_traitement', 'id' => 'id_soins', 'file' => 'imprimer_soins.php'],
                                    ['table' => 'examens', 'label' => 'Examen', 'date' => 'date_traitement', 'id' => 'id_examen', 'file' => 'imprimer_examen.php'],
                                    ['table' => 'controles', 'label' => 'Contrôle', 'date' => 'date_traitement', 'id' => 'id_controle', 'file' => 'imprimer_controle.php'],
                                    ['table' => 'mesures', 'label' => 'Mesure', 'date' => 'date_traitement', 'id' => 'id_mesure', 'file' => 'imprimer_mesure.php'],
                                    ['table' => 'rapportements', 'label' => 'Rapport', 'date' => 'date_traitement', 'id' => 'id_rapport', 'file' => 'imprimer_rapport.php'],
                                ];
                                $found = false;
                                foreach ($tables as $meta) {
                                    $sql = "SELECT {$meta['id']} AS id_doc, {$meta['date']} AS date_doc, id_affectation, id_type, traitant FROM {$meta['table']} WHERE id_patient = ? ORDER BY {$meta['date']} DESC";
                                    $stmt = $bdd->prepare($sql);
                                    $stmt->execute([$patientId]);
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $found = true;
                                        $motif = model($row['id_type']);
                                        $url = $meta['file'] . '?affectation=' . $row['id_affectation'];
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($row['date_doc']) . '</td>';
                                        echo '<td>EC_AFF' . htmlspecialchars($row['id_affectation']) . '</td>';
                                        echo '<td>' . htmlspecialchars($motif) . '</td>';
                                        echo '<td>' . htmlspecialchars(traitant($row['traitant'])) . '</td>';
                                        echo '<td><a href="' . htmlspecialchars($url) . '" target="_blank" class="btn btn-sm btn-info">Document ' . htmlspecialchars($motif) . '</a></td>';
                                        echo '</tr>';
                                    }
                                }
                                if (!$found) {
                                    echo '<tr><td colspan="5" class="text-center text-danger">Aucun document trouvé pour ce patient.</td></tr>';
                                }
                                echo '</tbody></table></div></div>';
                            } else {
                                echo '<div class="alert alert-danger">Identifiant patient invalide.</div>';
                            }
                        }
                    ?>
                    <!-- end: page -->
                    </section>
        </div>

    </section>
    <?php include('../public/footer.php');?>