<?php
session_start();
include('../public/connect.php');
require_once('../public/fonction.php');
include('../public/medecin_ieu.php');

include('../public/header.php');
?>
<body>
    <section class="body">

        <?php require('../public/navbarmenu.php'); ?>

        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Mes réalisations</h2>
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <section class="panel panel-transparent">
                        <div class="row">
                            <header class="panel panel-transparent">
                                <h4 class="panel-title text-black">Mes réalisations du jour :</h4>
                            </header>
                            <?php
                                $userId = (int)($_SESSION['auth']);
                                $today  = date('Y-m-d');

                                    $traitementsJour = getTraitementsAvecNomType($today, $userId, $bdd, 'day');

                                    foreach ($traitementsJour as $row) {
                                        echo '
                                            <div class="col-md-2">
                                                <section class="card">
                                                    <div class="card-body text-center">
                                                        <div class="h6 font-weight-bold text-primary mb-1">' . htmlspecialchars($row['total']) . '</div>
                                                        <p class="h6 text-xs text-muted mb-0">' . htmlspecialchars($row['nom_type']) . '</p>
                                                    </div>
                                                </section>
                                            </div>
                                        ';
                                    }

                            ?>
                        </div>
                    </section>
                </div>
                
                <hr />

                <div class="col-md-12">
                    <section class="panel panel-transparent">
                        <div class="row">
                            <header class="panel panel-transparent">
                                <h4 class="panel-title text-black">Mes réalisations du mois :</h4>
                            </header>
                            <?php
                                    $userId = (int)($_SESSION['auth']);
                                    $month = date('Y-m');

                                    $traitementsMois = getTraitementsAvecNomType($month, $userId, $bdd, 'month');

                                    foreach ($traitementsMois as $row) {
                                        echo '
                                            <div class="col-md-2">
                                                <section class="card">
                                                    <div class="card-body text-center">
                                                        <div class="h6 font-weight-bold text-primary mb-1">' . htmlspecialchars($row['total']) . '</div>
                                                        <p class="h6 text-xs text-muted mb-0">' . htmlspecialchars($row['nom_type']) . '</p>
                                                    </div>
                                                </section>
                                            </div>
                                        ';
                                    }

                            ?>
                        </div>
                    </section>
                </div>

                <hr />

                <div class="col-md-12">
                    <section class="panel panel-transparent">
                        <div class="row">
                            <header class="panel panel-transparent">
                                <h4 class="panel-title text-black">Mes réalisations de l'année :</h4>
                            </header>
                            <?php
                                    $userId = (int)($_SESSION['auth']);
                                    $year = date('Y');

                                    $traitementsAnnee = getTraitementsAvecNomType($year, $userId, $bdd, 'year');

                                    foreach ($traitementsAnnee as $row) {
                                        echo '
                                            <div class="col-md-2">
                                                <section class="card">
                                                    <div class="card-body text-center">
                                                        <div class="h6 font-weight-bold text-primary mb-1">' . htmlspecialchars($row['total']) . '</div>
                                                        <p class=" h6 text-xs text-muted mb-0">' . htmlspecialchars($row['nom_type']) . '</p>
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