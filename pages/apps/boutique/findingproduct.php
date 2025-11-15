<?php
include('../PUBLIC/connect.php');
session_start();

    $errors=0; $existe=0;

    if (isset($_POST['recherche'])) {
        $code = trim($_POST['recherche']);
        $req1 = $bdd->prepare('SELECT 1 FROM produits WHERE code_produit=?');
        $req1->execute([$code]);
        if ($req1->fetch()) {
            header('Location: findingproduct.php?codeproduit=' . urlencode($code));
            exit();
        } else {
            $existe = 1;
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
                    <h2>Rechercher un produit dans la boutique</h2>
                </header>

                <!-- start: page -->
                <?php

                        if (!isset($_GET['codeproduit'])) {
                        echo '
                            <div class="col-md-12">
                                <section class="card">
                                    <div class="card-body">';
                                            if ($existe==1) {
                                            echo '
                                                <div class="alert alert-danger">
                                                    <li>Les code produit saisie n\'existe pas dans le système.</li>
                                                </div>
                                                ';
                                                } 
                                        echo'
                                        <form class="form-horizontal" novalidate="novalidate" method="POST" action="findingproduct.php" enctype="multipart/form-data">
                                        <input type="hidden" name="recherche" value="1">
                                        <div class="row form-group pb-3">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="formGroupExampleInput">Saisir le code du produit</label>
                                                    <input type="text" class="form-control" name="recherche" id="formGroupExampleInput" placeholder="" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <footer class="card-footer text-end">
                                        <button class="btn btn-primary" type="submit">Rechercher le produit</button>
                                    </footer>
                                    </form>
                                </section>
                            </div>';
                        
                        }
                    
                        if (isset($_GET['codeproduit'])) {
                            $reponse1 = $bdd->prepare('SELECT * FROM produits WHERE code_produit=?');
                            $reponse1->execute([$_GET['codeproduit']]);
                            $donnees1 = $reponse1->fetch();
                            if ($donnees1) {
                                $codeproduit = $donnees1['code_produit'];
                                $models = $donnees1['id_model'];
                                $couleurs = $donnees1['couleur'];
                                $descriptions = $donnees1['description'];
                                $status = $donnees1['vendu'];
                                $prix = $donnees1['prix'];
                        echo '
                        <div class="col-md-12">
							<section class="card">
								<div class="card-body">
                                    <form class="form-horizontal" novalidate="novalidate" enctype="multipart/form-data">
										
										<div class="row form-group pb-3">
											<div class="col-md-3">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Code monture</label>
													<input type="text" class="form-control" value="'.$codeproduit.'" disabled>
												</div>
											</div>
											<div class="col-md-3">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Marque</label>
                                                    <input type="text" class="form-control" value="'.model_produits( $models).'" disabled>
												</div>
											</div>
											<div class="col-md-2">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Couleur</label>
													<input type="text" class="form-control" value="'.$couleurs.'" disabled>
												</div>
											</div>
                                            <div class="col-md-2">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Statuts</label>
                                                    '; if ($status==0) {
                                                        echo '<input type="text" style="background-color:#64F584;" class="form-control" value="Disponible" disabled>';
                                                    } else {
                                                        echo '<input type="text" style="background-color:#F4807A;" class="form-control" value="Déjà vendu" disabled>';
                                                    }
                                                    echo'
												</div>
											</div>';
                                            if ($status==0) {
                                                echo '<div class="col-md-2">
                                                        <div class="form-group">
                                                            <label class="col-form-label" for="formGroupExampleInput">Prix de la monture</label>
                                                              <input type="text" class="form-control" value="'.number_format($prix).' '.$devise.'" disabled>
                                                        </div>
                                                    </div>';}
											echo' <div class="col-md-12">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Description de la catégorie</label>
													<textarea class="form-control" rows="5" id="formGroupExampleInput" disabled>'.$descriptions.'</textarea>
												</div>
											</div>
										</div>
                                	</form>
                                </div>
							</section>
						</div>
					</div>';}
                    else {
                                echo '<div class="alert alert-danger">Produit introuvable.</div>';
                            }
                        }

                    ?>
                <!-- end: page -->
            </section>
        </div>
        <?php include('../PUBLIC/footer.php');?>
