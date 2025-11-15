<?php
include('../public/connect.php');
session_start();                

include('../public/header.php');
?>

<script>
    setTimeout(function() {
        location.reload();
    }, 60000); // Actualisation toutes les 60 secondes
</script>

<body>
    <section class="body">
        <?php require('../public/navbarmenu.php'); ?>
        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2> 
                        Liste des patients en salle d'acceuil
                    </h2>
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <div class="row">
                        <div class="col">
                            <section class="card">
                                <div class="card-body">

                                <table class="table table-bordered table-striped mb-0" id="datatable-default">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>AFFECTATION</th>
                                            <th>PATIENT</th>
                                            <th>ADRESSE</th>
                                            <th>CONTACT</th>
                                            <th>CELULLE</th>
                                            <th>EXAMEN</th>
                                            <th>STATUS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php                                    
                                    if ($types == 7) {
                                        try {
                                            $stmt = $bdd->prepare('SELECT * FROM affectations WHERE status IN (1, 2, 6, 99, 7, 8, 9) ORDER BY id_affectation');
                                            $stmt->execute();
                                            while ($donnees1 = $stmt->fetch(PDO::FETCH_ASSOC)) {                                                
                                                $status = $donnees1['status'];
                                                $patientInfo = getPatientInfo($donnees1['id_patient']);
                                                if (!is_array($patientInfo)) {
                                                    error_log("patientInfo n'est pas un tableau pour l'ID: " . $donnees1['id_patient']);
                                                    $patientInfo = [
                                                        'nom_patient' => 'Non disponible',
                                                        'adresse' => 'Non disponible',
                                                        'phone' => 'Non disponible'
                                                    ];
                                                }
                                                echo '<tr>
                                                    <td>PAT-'.htmlspecialchars($donnees1['id_patient']).'</td>
                                                    <td>AFF-'.htmlspecialchars($donnees1['id_affectation']).'</td>
                                                    <td>'.htmlspecialchars($patientInfo['nom_patient'] ?: 'Non renseigné').'</td>
                                                    <td>'.htmlspecialchars(adress($patientInfo['adresse']) ?: $patientInfo['adresse']).'</td>
                                                    <td>'.htmlspecialchars($patientInfo['phone'] ?: 'Non renseigné').'</td>
                                                    <td>'.htmlspecialchars(service($donnees1['id_service'])).'</td>
                                                    <td>'.htmlspecialchars(model($donnees1['type'])).'</td>
                                                    <td>';
                                                
                                                if ($status == 6 ) {
                                                    echo '<button class="btn btn-sm btn-danger">en attente de paiement</button>';
                                                }
                                                 elseif ($status == 2) {
                                                    echo '<button class="btn btn-sm btn-info">accepté en attente d\'être vu</button>';
                                                } elseif ($status == 1) {
                                                    echo '<button class="btn btn-sm btn-warning">payé en attente d\'être vu</button>';
                                                } elseif ($status == 99) {
                                                    echo '<button class="btn btn-sm btn-dark">reféré est à rembourser</button>';
                                                } elseif ($status == 7 || $status == 8 || $status == 9) {
                                                    echo '<button class="btn btn-sm btn-dark">en procedure de chirurgie</button>';
                                                }                                                
                                                echo '</td></tr>';
                                            }
                                        } catch (PDOException $e) {
                                            // Log l'erreur avec plus de détails
                                            error_log('Erreur PDO dans patientensalle.php : ' . $e->getMessage());
                                            error_log('Code erreur : ' . $e->getCode());
                                            echo '<div class="alert alert-danger">
                                                <strong>Erreur :</strong> Une erreur est survenue lors de la récupération des données.<br>
                                                Veuillez contacter l\'administrateur système si le problème persiste.
                                            </div>';
                                        }
                                    }
                                    ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </div>
            </section>
        </div>
        <?php include('../PUBLIC/footer.php'); ?>
</body>