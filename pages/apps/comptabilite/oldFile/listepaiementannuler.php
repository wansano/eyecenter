<?php
include('../PUBLIC/connect.php');
session_start();
$errors = 0;             

include('../PUBLIC/header.php');
?>


<body>
    <section class="body">
        <?php require('../PUBLIC/navbarmenu.php'); ?>
        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2> Liste des paiements annulés </h2>
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
                                            <th>AFFECTATION</th>
                                            <th>DOSSIER</th>
                                            <th>PATIENT</th>
                                            <th>CONTACT</th>
                                            <th>EXAMEN</th>
                                            <th>MONTANT</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                        try {
                                            // Récupérer la devise de l'entreprise (fallback si absent)
                                            $profilStmt = $bdd->prepare('SELECT devise FROM profil_entreprise LIMIT 1');
                                            $profilStmt->execute();
                                            $profilRow = $profilStmt->fetch(PDO::FETCH_ASSOC);
                                            $devise = $profilRow['devise'] ?? 'GNF';

                                            // Récupération optimisée : joindre la table patients
                                            $stmt = $bdd->prepare(
                                                'SELECT a.*, p.id_patient, p.nom_patient, p.phone 
                                                 FROM affectations a
                                                 LEFT JOIN patients p ON a.id_patient = p.id_patient
                                                 WHERE a.type_paiement = :annuler
                                                 ORDER BY a.id_affectation'
                                            );
                                            $stmt->execute(['annuler' => 0]);

                                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                            if (empty($rows)) {
                                                echo '<tr><td colspan="5">Aucun paiement annulé trouvé.</td></tr>';
                                            } else {
                                                foreach ($rows as $donnees1) {
                                                    $montant = montant($donnees1['type']);

                                                    echo '<tr>';
                                                    echo '<td>' . htmlspecialchars($donnees1['date'] ?? '') . '</td>';
                                                    echo '<td>' . htmlspecialchars($donnees1['id_patient'] ?? 'Sans Dossier') . '</td>';
                                                    echo '<td>' . htmlspecialchars($donnees1['nom_patient'] ?? 'Non renseigné') . '</td>';
                                                    echo '<td>' . htmlspecialchars($donnees1['phone'] ?? '') . '</td>';
                                                    echo '<td>' . htmlspecialchars(model($donnees1['type'])) . '</td>';
                                                    echo '<td>' . number_format($montant, 0, ',', ' ') . ' ' . htmlspecialchars($devise) . '</td>';
                                                    echo '</tr>';
                                                }
                                            }

                                        } catch (PDOException $e) {
                                            error_log($e->getMessage());
                                            echo '<tr><td colspan="5"><div class="alert alert-danger">Une erreur est survenue lors de la récupération des données.</div></td></tr>';
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