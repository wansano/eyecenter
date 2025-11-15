<?php
include('../PUBLIC/connect.php');
require('../PUBLIC/fonction.php');
session_start();
$existe = 0;
if (isset($_POST['recherche'])) {
    try {
        
        // Vérification de la connexion à la base de données
        $stmt = $bdd->prepare('SELECT id_patient FROM patients WHERE id_patient = ?');
        $stmt->execute([$_POST['recherche']]);
        
        if ($stmt->rowCount() > 0) {
            echo '<script>';
            echo 'document.location.href="'.htmlspecialchars($_SERVER['PHP_SELF']).'?id_patient='.$_POST['recherche'].'"';
            echo '</script>';
        } else {
            $existe = 1;
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la recherche du patient : " . $e->getMessage());
        $errors = 4;
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
                    <h2>Transmission du patient à la caisse</h2>
                </header>

                <!-- start: page -->
                <?php 
                        if (!isset($_GET['id_patient'])) {
                        echo '
                        <div class="col-md-12">
                                <section class="card">
                                    <div class="card-body">';
                                        if ($existe==1) {
                                            echo '
                                                <div class="alert alert-danger">
                                                    <li>Les numéro dossier saisie n\'existe pas dans le système.</li>
                                                </div>
                                                ';
                                                } 
                                        echo'
                                        <form class="form-horizontal" novalidate="novalidate" method="POST" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'" enctype="multipart/form-data">
                                        <input type="hidden" name="recherche" value="1">
                                        <div class="row form-group pb-3">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="formGroupExampleInput">Saisir le numero dossier du patient</label>
                                                    <input type="text" class="form-control" name="recherche" id="formGroupExampleInput" required="">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <footer class="card-footer text-end">
                                        <button class="btn btn-primary" type="submit">continuer</button>
                                    </footer>
                                    </form>
                                </section>
                            </div>
                        </div>'; }
                        $id_patient = $_GET['id_patient'];
                        if (isset($id_patient)) {
                              $errors=0; $existe=0;
                            $patient = nom_patient($id_patient);
                            $telephone = return_phone($id_patient);
                            $adresse = return_adresse($id_patient);
                            $responsable = return_responsable($id_patient);
                            $profession = return_profession($id_patient);
                            $age = return_age($id_patient);
                            $sexe = return_sexe($id_patient);
                            $assure = return_assure($id_patient);
                            $assurance = return_assurance($id_patient);
                            
                            if (isset($_POST['transmettre'])) {

                                $req1 = $bdd->prepare('SELECT * FROM affectations WHERE id_patient=? AND type=? AND status IN (?, ?, ?)');
                                $req1->execute([$id_patient, $_POST['type'], 6, 1, 2]);
                                while ($dta = $req1->fetch(PDO::FETCH_ASSOC)) 
                                {
                                $existe=2;
                                }

                                if ($existe == 0) {
                                
                                $reponse1 = $bdd->prepare('SELECT * FROM traitements WHERE id_type = ?');
                                $reponse1->execute([$_POST['type']]);
                                    while ($donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC))
                                    {
                                    $model = $donnees1['id_organigramme'];
                                    }

                                /* $reponse8 = $bdd->prepare('SELECT * FROM organigramme WHERE id_organigramme=?');
                                $reponse8->execute([$model]);
                                    while ($donnees1 = $reponse8->fetch(PDO::FETCH_ASSOC))
                                    {
                                    $services = $donnees1['id_organigramme'];
                                    }  */
                            
                                $req = $bdd->prepare('INSERT INTO affectations (id_patient, id_service, type) VALUES(?,?,?)');
                                $req->execute([$id_patient, $model, $_POST['motif_id']]);

                                $errors=2; 
                                }
                            }
                        echo '
                        <div class="col-md-12">
							<section class="card">
								<div class="card-body">';
                                        if ($errors==2) {
                                        echo '
                                            <div class="alert alert-success">
                                            <strong>Succès</strong> <br/>  
                                            <li>Dossier patient transmis à la caisse pour paiement. Merci de rediriger le patient vers la caisse.</li>
                                            </div>
                                            ';
                                                }
                                        if ($errors==4) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <strong>Erreur</strong> <br/>  
                                                <li>Patient non transmis, merci de vérifier les informations saisies</li>.
                                            </div>
                                            ';}
                                        if ($existe==2) {
                                            echo '
                                                <div class="alert alert-danger">
                                                    <strong>Erreur</strong> <br/>  
                                                    <li>Ce patient est déjà transmis pour ce traitement de <strong>'.model($_POST['type']).'</strong>.</li>
                                                </div>
                                                ';}
                                    echo '
									<div class="row form-group pb-3">
										<div class="col-md-4">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Prénoms & Nom</label>
                                                <input type="text" class="form-control" value="'.$patient.'" disabled>
											</div>
										</div>
										<div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Genre</label>
												<select class="form-control populate" disabled>
                                                    <option value="'.$sexe.'">'.$sexe.'</option>';
                                                        if ($sexe=="Homme") {
                                                            echo '<option value="Feminin">Feminin</option>';
                                                        } else {
                                                            echo '<option value="Masculin">Masculin</option>';
                                                        }
                                                echo '</select>
											</div>
										</div>
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Date de naissance</label>
												<input type="date" class="form-control" id="formGroupExampleInput" value="'.$age.'" disabled>
											</div>
										</div>
										<div class="col-md-4">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Profession</label>
												<input type="text" class="form-control" id="formGroupExampleInput" value="'.$profession.'" disabled>
											</div>
										</div>
										<div class="col-md-4">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Adresse</label>
												<input type="text" class="form-control" id="formGroupExampleInput" value="'.(adress($adresse)?: $adresse).'" disabled>
											</div>
										</div>
										<div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Contact</label>
												<input type="number" class="form-control" maxlength="09" id="formGroupExampleInput" value="'.$telephone.'" disabled>
											</div>
										</div>
                                        <div class="col-md-2">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Type de patient</label>
												<input type="text" class="form-control" id="formGroupExampleInput" value="'.determinerStatutAssurance($assure).'" disabled>
											</div>
										</div>
                                        <div class="col-md-4">
											<div class="form-group">
												<label class="col-form-label" for="formGroupExampleInput">Responsable</label>
												<input type="text" class="form-control" id="formGroupExampleInput" value="'.$responsable.'" disabled>
											</div>
										</div>
									</div>
                                    <form class="form-horizontal" novalidate="novalidate" method="POST" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'?id_patient='.$_GET['id_patient'].'" enctype="multipart/form-data">
                                    <input type="hidden" value="'.$_GET['id_patient'].'"> 
                                        <div class="row form-group pb-3">
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="formGroupExampleInput">Departement concerné</label>
                                                    <select name="service" class="form-control populate" id="serviceSelect" onchange="updateMotifs()">
                                                        <option value=""> ------ Choisir ----- </option>';
                                                            $coll = $bdd->prepare('SELECT * FROM organigramme WHERE id_organigramme IN (?, ?, ?, ?, ?)');
                                                            $coll -> execute([1, 2, 3, 4, 14]);
                                                            while ($services = $coll->fetch(PDO::FETCH_ASSOC))
                                                            {
                                                                echo '<option value="'.$services['id_organigramme'].'">'.$services['celulle'].'</option>';
                                                            } echo '
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="formGroupExampleInput">Motif de présence</label>
                                                    <select class="form-control populate" id="motifSelect" name="type" onchange="fetchMotifPrice()" data-plugin-selectTwo data-plugin-options="{ "minimumInputLength": O }" required>
                                                    <option value=""> ------ Choisir un service ----- </option>
                                                    </select>
                                                    <input type="hidden" id="hiddenMotifId" name="motif_id" value="">
                                                </div>
                                            </div>
                                            <div class="col-md-2" id="productPrice"></div>
                                        </div>
                                    <footer class="card-footer text-end">
                                        <button class="btn btn-primary" type="submit" name="transmettre">Transmettre à la caisse</button>
                                    </footer>
                                </form>
							</section>
						</div>
					</div>';}
                    ?>
                <!-- end: page -->
            </section>
        </div>
        <?php include('../PUBLIC/footer.php');?>
