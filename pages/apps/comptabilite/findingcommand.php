<?php
include('../PUBLIC/connect.php');
session_start();

    $errors=0; $existe=0;

    if (isset($_POST['recherche']))
    {
            
        $req1 = $bdd->prepare('SELECT * FROM approvisionnements WHERE no_commande= ? AND statut=?');
        $req1 -> execute(array($_POST['recherche'],"en attente"));

        while ($data = $req1->fetch())
        {       
                echo '<script>';
                echo 'document.location.href="shopapproproduct.php?commande='.$_POST['recherche'].'"';
                echo '</script>';
        }
		if ($existe==0) {$existe=1;}
    }
     
     include('../PUBLIC/header.php');
 ?>

<body>
    <section class="body">

        <?php require('../PUBLIC/navbarmenu.php'); ?>

        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Approvisionnement du stock</h2>

                    <div class="right-wrapper text-end">
                        <ol class="breadcrumbs">
                            <li>
                                <a href="#">
                                    <i class="bx bx-home-alt"></i>
                                </a>
                            </li>

                            <li><span>Acceuil</span></li>

                        </ol>

                        <a class="sidebar-right-toggle" data-open="sidebar-right"></a>
                    </div>
                </header>

                <!-- start: page -->
                <?php

                        if (!isset($_GET['commande'])) {
                        echo '
                            <div class="col-md-12">
                                <section class="card">
                                    <header class="card-header">
                                        <h2 class="card-title">Touver une commande active pour approvisionnement</h2>
                                    </header>
                                    <div class="card-body">';
                                            if ($existe==1) {
                                            echo '
                                                <div class="alert alert-danger">
                                                    <li>Le n° commande saisie à été déjà traité ou n\'existe pas dans le système.</li>
                                                </div>
                                                ';
                                                } 
                                        echo'
                                        <form class="form-horizontal" novalidate="novalidate" method="POST" action="findingcommand.php" enctype="multipart/form-data">
                                        <input type="hidden" name="recherche" value="1">
                                        <div class="row form-group pb-3">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="formGroupExampleInput">Saisir le numero de la commande</label>
                                                    <input type="text" class="form-control" name="recherche" id="formGroupExampleInput" placeholder="" require>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <footer class="card-footer text-end">
                                        <button class="btn btn-primary" type="submit">Suivant</button>
                                    </footer>
                                    </form>
                                </section>
                            </div>';
                        
                        }

                    ?>
                <!-- end: page -->
            </section>
        </div>
        <?php include('../PUBLIC/footer.php');?>
    