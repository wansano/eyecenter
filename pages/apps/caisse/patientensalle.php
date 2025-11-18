<?php
include('../PUBLIC/connect.php');
session_start();
$errors = 0;

// Vérifier si l'utilisateur a déjà effectué sa preuve/clôture de caisse aujourd'hui
try {
    $proofStmt = $bdd->prepare('SELECT COUNT(*) FROM preuvedecaisse WHERE date_rapportement = ? AND id_user = ?');
    $proofStmt->execute([date('Y-m-d'), $_SESSION['auth']]);
    $hasClotureCaisse = $proofStmt->fetchColumn() > 0;
} catch (Exception $e) {
    error_log('Erreur vérification clôture caisse: ' . $e->getMessage());
    $hasClotureCaisse = false; // En cas d'erreur, on laisse l'accès pour éviter blocage
}

if (isset($_POST['annulation'])) {
    $reponse = $bdd->prepare('UPDATE affectations SET status = :statut, montant = :montant, taux = :taux, type_paiement = :paiement WHERE id_affectation = :affectation');
    $reponse->execute([
        'statut' => 5,
        'montant' => 0,
        'taux' => 0,
        'paiement' => 0,
        'affectation' => $_POST['annulation']
    ]);
    $errors = 7;
}                   

include('../PUBLIC/header.php');
?>

<?php if (!$hasClotureCaisse): ?>
<script>
    // Actualisation seulement si la clôture n'est pas encore effectuée
    setTimeout(function() { location.reload(); }, 60000);
</script>
<?php endif; ?>

<body>
    <section class="body">
        <?php require('../PUBLIC/navbarmenu.php'); ?>
        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2> Liste des patients en attente de paiement </h2>
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <?php if ($hasClotureCaisse): ?>
                        <div class="alert alert-info">
                            <strong>Information</strong><br>
                            La cloture de caisse est déjà éffectuée pour votre compte.
                        </div>
                    <?php else: ?>
                    <div class="row">
                        <div class="col">
                            <section class="card">
                                <div class="card-body">
                                <?php 
                                if (isset($types) && $types == "caisse" && $errors == 7) {
                                    echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Le paiement des frais de traitement a été annulé.</li></div>';
                                }
                                ?>
                                <table class="table table-bordered table-striped mb-0" id="datatable-default">
                                    <thead>
                                        <tr>
                                            <th>AFFECTATION</th>
                                            <th>PATIENT</th>
                                            <th>CONTACT</th>
                                            <th>EXAMEN</th>
                                            <th>MONTANT</th>
                                            <th>ACTION</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                        try {
                                            $sql = "SELECT id_affectation, id_patient, id_service, type, date, status FROM affectations WHERE status IN (6, 3) AND id_service IN (1, 2, 3, 4) ORDER BY id_affectation";
                                            $stmt = $bdd->prepare($sql);
                                            $stmt->execute();

                                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                $status  = (int)$row['status'];
                                                $patientInfo = getPatientInfo($row['id_patient']) ?: [
                                                    'nom_patient' => '—',
                                                    'phone'       => '—',
                                                ];
                                                $montant = (float) montant($row['type']);
                                                $modele  = model($row['type']);

                                                echo '<tr>';
                                                echo '<td>'.htmlspecialchars($row["date"], ENT_QUOTES, "UTF-8").'</td>';
                                                echo '<td>'.htmlspecialchars($patientInfo["nom_patient"], ENT_QUOTES, "UTF-8").'</td>';
                                                echo '<td>'.htmlspecialchars($patientInfo["phone"], ENT_QUOTES, "UTF-8").'</td>';
                                                echo '<td>'.htmlspecialchars($modele, ENT_QUOTES, "UTF-8").'</td>';
                                                echo '<td>'.number_format($montant, 0, ",", " ").' '.htmlspecialchars($devise ?? "", ENT_QUOTES, "UTF-8").'</td>';
                                                echo '<td>';
                                                if ($status === 6) {
                                                    $self = htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, "UTF-8");
                                                    $qs   = 'lpeap='.urlencode($row['type']);
                                                    $pay  = 'paiementdesfrais.php?id_patient='.urlencode($row['id_patient']).'&id_affectation='.urlencode($row['id_affectation']);
                                                    echo '<form action="'.$self.'?'.$qs.'" method="post"><a href="'.$pay.'" class="btn btn-sm btn-success"><i class="fa-regular fa-credit-card"></i> Paiement</a></form>';
                                                }
                                                echo '</td>';
                                                echo '</tr>';
                                            }
                                        } catch (PDOException $e) {
                                            error_log($e->getMessage());
                                            echo '<div class="alert alert-danger">Une erreur est survenue lors de la récupération des données.</div>';
                                        }
                                    ?>
                                    </tbody>
                                </table>
                                </div>
                            </section>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
        <?php include('../PUBLIC/footer.php'); ?>
</body>