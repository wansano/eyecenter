<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();
$errors = 0;

if (isset($_POST['annuler'])) {
    $reponse = $bdd->prepare('UPDATE affectations SET status=1 WHERE id_affectation=?');
    $reponse->execute([$_POST['annuler']]);
    $errors = 3;
}

include('../PUBLIC/header.php');
?>
<body>
<section class="body">
    <?php require('../PUBLIC/navbarmenu.php'); ?>
    <div class="inner-wrapper">
        <section role="main" class="content-body">
            <header class="page-header">
                <h2>Situation des remboursements à faire</h2>
            </header>
            <!-- start: page -->
            <div class="col-md-12">
                <div class="row">
                    <div class="col">
                        <section class="card">
                            <div class="card-body">
                                <?php if ($errors == 3): ?>
                                    <div class="alert alert-success">
                                        <strong>Succès !</strong> <br/>
                                        <li>Annulation du remboursement effectué avec succès.</li>
                                        <li>Le dossier du patient à été transmis au service concerné. Merci de rediriger le patient vers le service traitant.</li>
                                    </div>
                                <?php endif; ?>
                                <table class="table table-bordered table-striped mb-0" id="datatable-default">
                                    <thead>
                                        <tr>
                                            <th>DATE</th>
                                            <th>N° PAIEMENT</th>
                                            <th>DOSSIER</th>
                                            <th>PATIENT</th>
                                            <th>CONTACT</th>
                                            <th>EXAMEN</th>
                                            <th>MONTANT A PAYE</th>
                                            <th>STATUS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    $reponse1 = $bdd->prepare('SELECT * FROM affectations WHERE (status=? OR status=?) AND montant > ?');
                                    $reponse1->execute([99, 0, 0]);
                                    while ($donnees1 = $reponse1->fetch()) {
                                        $status = $donnees1['status'];
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($donnees1['date']) . '</td>';
                                        echo '<td>' . htmlspecialchars(getNumeroPaiement($bdd, $donnees1['id_affectation'])) . '</td>';
                                        echo '<td>' . htmlspecialchars($donnees1['id_patient']) . '</td>';
                                        echo '<td>' . htmlspecialchars(nom_patient($donnees1['id_patient'])) . '</td>';
                                        echo '<td>' . htmlspecialchars(return_phone($donnees1['id_patient'])) . '</td>';
                                        echo '<td>' . htmlspecialchars(model($donnees1['type'])) . '</td>';
                                        echo '<td>' . number_format($donnees1['montant']) . ' ' . htmlspecialchars($devise) . '</td>';
                                        echo '<td>';
                                        if ($status == 99 || $status == 0) {
                                            echo '<form action="remboursement.php?profil=' . htmlspecialchars($types) . '" method="post">';
                                            echo '<a href="payementreval.php?id_affectation=' . urlencode($donnees1['id_affectation']) . '" class="btn btn-sm btn-success">proceder</a> ';
                                            echo '<input type="hidden" name="annuler" value="' . htmlspecialchars($donnees1['id_affectation']) . '">';
                                            echo '<button type="submit" class="btn btn-sm btn-danger">annuler</button>';
                                            echo '</form>';
                                        }
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