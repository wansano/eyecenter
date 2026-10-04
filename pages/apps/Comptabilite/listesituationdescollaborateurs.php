<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();
$errors = 0;

function setCollaborateurStatus($bdd, $id, $status) {
    $reponse = $bdd->prepare('UPDATE collaborateurs SET status=? WHERE id_collaborateur=?');
    $reponse->execute([$status, $id]);
}

if (isset($_POST['activer'])) {
    setCollaborateurStatus($bdd, $_POST['activer'], 1);
    $errors = 2;
}
if (isset($_POST['desactiver'])) {
    setCollaborateurStatus($bdd, $_POST['desactiver'], 0);
    $errors = 3;
}
if (isset($_POST['supprimer'])) {
    setCollaborateurStatus($bdd, $_POST['supprimer'], 3);
    $errors = 4;
}

include('../PUBLIC/header.php');
?>
<body>
<section class="body">
    <?php require('../PUBLIC/navbarmenu.php'); ?>
    <div class="inner-wrapper">
        <section role="main" class="content-body">
            <header class="page-header">
                <h2>Situation des collaborateurs</h2>
            </header>
            <!-- start: page -->
            <div class="col-md-12">
                <div class="row">
                    <div class="col">
                        <section class="card">
                            <div class="card-body">
                                <?php 
                                    if ($errors == 2) {
                                        echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Compte collaborateur activé avec succès.</li></div>';
                                    }
                                    if ($errors == 3) {
                                        echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Compte du collaborateur désactivé avec succès.</li></div>';
                                    }
                                    if ($errors == 4) {
                                        echo '<div class="alert alert-success"><li><strong>Succès !</strong><br>Compte collaborateur supprimé avec succès.</li></div>';
                                    }
                                ?>
                                <table class="table table-bordered table-striped mb-0" id="datatable-default">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>COLLABORATEUR</th>
                                            <th>CONTACT</th>
                                            <th>SERVICE</th>
                                            <th>DERNIER PAIEMENT</th>
                                            <th>SOLDE A PAYER</th>
                                            <th>STATUS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    $reponse1 = $bdd->prepare('SELECT * FROM collaborateurs WHERE solde > ? AND statut != 3 ORDER BY id_collaborateur');
                                    $reponse1->execute([0]);
                                    while ($donnees1 = $reponse1->fetch()) {
                                        $pour = $donnees1['collaborateur_pour'];
                                        echo '<tr>';
                                        echo '<td>COLLAB' . $donnees1['id_collaborateur'] . '</td>';
                                        echo '<td>' . htmlspecialchars($donnees1['nom_collaborateur']) . '</td>';
                                        echo '<td>' . htmlspecialchars($donnees1['telephone']) . '</td>';
                                        echo '<td>' . ($pour == 2 ? 'Boutique' : 'Clinique') . '</td>';
                                        echo '<td>' . htmlspecialchars($donnees1['date_modification']) . '</td>';
                                        echo '<td>' . number_format($donnees1['solde']) . ' ' . htmlspecialchars($devise) . '</td>';
                                        echo '<td>';
                                        echo '<a href="payementcollaborateurs.php?collaborateur=' . urlencode($donnees1['id_collaborateur']) . '" class="btn btn-sm btn-info">Procéder au paiement</a>';
                                        echo '</td>';
                                        echo '</tr>';
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
