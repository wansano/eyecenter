<?php
include('../PUBLIC/connect.php');
require('../PUBLIC/fonction.php');
session_start();
$existe = 0;

include('../PUBLIC/header.php'); 

?>

<body>
    <section class="body">

        <?php require('../PUBLIC/navbarmenu.php'); ?>

        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Vérification du prix de prestation</h2>
                </header>

                <!-- start: page -->
                        <div class="col-md-12">
							<section class="card">
								<div class="card-body">
                                    <form class="form-horizontal" novalidate="novalidate" method="POST" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'?id_patient='.$_GET['id_patient'].'" enctype="multipart/form-data">
                                    <input type="hidden" value="'.$_GET['id_patient'].'"> 
                                        <div class="row form-group pb-3">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="formGroupExampleInput">Departement concerné</label>
                                                    <select name="service" class="form-control populate" id="serviceSelect" onchange="updateMotifs()">
                                                        <option value=""> ------ Choisir ----- </option>
                                                        <?php   
                                                        // Fetching services from the database
                                                            $coll = $bdd->prepare('SELECT * FROM organigramme WHERE id_organigramme IN (?, ?, ?, ?)');
                                                            $coll -> execute([1, 2, 3, 4]);
                                                            while ($services = $coll->fetch(PDO::FETCH_ASSOC))
                                                            {
                                                                echo '<option value="'.$services['id_organigramme'].'">'.$services['celulle'].'</option>';
                                                            }
                                                        ?>
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="formGroupExampleInput">Prestation</label>
                                                    <select class="form-control populate" id="motifSelect" name="type" onchange="fetchMotifPrice()" data-plugin-selectTwo data-plugin-options="{ "minimumInputLength": O }" required>
                                                    <option value=""> ------ Choisir un service ----- </option>
                                                    </select>
                                                    <input type="hidden" id="hiddenMotifId" name="motif_id" value="">
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="productPrice">Prix en <?php echo $devise; ?></label>
                                                    <input type="text" class="form-control" id="productPrice" style="background-color:#64F584;" disabled>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
							</section>
						</div>
					</div>
                <!-- end: page -->
            </section>
        </div>
        <?php include('../PUBLIC/footer.php');?>
