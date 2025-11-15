<?php
include('../PUBLIC/connect.php');
include('../PUBLIC/fonction.php');
include('../PUBLIC/medecin_ieu.php');
session_start();

try {
    // Vérification des paramètres
    if (!isset($_GET['affectation'])) {
        throw new Exception("ID d'affectation manquant");
    }

    $existe = 0; 
    $errors = 0;
    $affectation = $_GET['affectation'];
    
    // Récupération des données en une seule requête
    $stmt = $bdd->prepare('
        SELECT a.*, p.nom_patient, p.responsable 
        FROM affectations a
        JOIN patients p ON a.id_patient = p.id_patient
        WHERE a.id_affectation = ?
    ');
    $stmt->execute([$affectation]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $id_patient = $data['id_patient'];

    // Récupération des dernières données d'acuité visuelle et historique en une seule fois
    $derniereDonnees = recupererDerniereAcquiteEtHistorique($bdd, $id_patient);

    if (!$data) {
        throw new Exception("Affectation non trouvée");
    }

    // Extraction des données
    extract($data);

    // Traitement du formulaire
    if (isset($_POST['ajouter']) && !empty($_POST['rapport']) && !empty($_FILES['pathologie']['name'])) {
        
        // Vérification si déjà traité
        $existe = checkTraitementExisteRapport($bdd, $affectation);

        if ($existe == 0) {
            // Validation du fichier photo pathologie (obligatoire)
            if (!isset($_FILES['pathologie']) || $_FILES['pathologie']['error'] != UPLOAD_ERR_OK) {
                $errors = 5; // Erreur fichier pathologie manquant
            } else {
                $bdd->beginTransaction();
                
                try {
                    // Création du dossier photo s'il n'existe pas
                    $photoDir = '../documents/photo/';
                    if (!is_dir($photoDir)) {
                        mkdir($photoDir, 0755, true);
                    }

                    // Upload de la photo pathologie (obligatoire)
                    $pathologieFileName = '';
                    if ($_FILES['pathologie']['error'] == UPLOAD_ERR_OK) {
                        $extension = pathinfo($_FILES['pathologie']['name'], PATHINFO_EXTENSION);
                        $pathologieFileName = 'IMGPath_' . $id_patient . '_' . $affectation . '_' . time() . '.' . $extension;
                        $pathologiePath = $photoDir . $pathologieFileName;
                        
                        if (!move_uploaded_file($_FILES['pathologie']['tmp_name'], $pathologiePath)) {
                            throw new Exception("Erreur lors de l'upload de la photo pathologie");
                        }
                    }

                    // Préparer les données pour l'insertion
                    $postData = $_POST;
                    $postData['pathologie_file'] = $pathologieFileName;

                    insertRapportementEvacuation($bdd, $id_patient, $type, $affectation, $postData);
                    updateAffectationStatus($bdd, $affectation);

                    $bdd->commit();
                    $errors = 4;
                } catch (Exception $e) {
                    $bdd->rollBack();
                    error_log("Erreur lors du traitement du rapport : " . $e->getMessage());
                    $errors = 1;
                }
            }
        }         
    }  
} catch (Exception $e) {
    $errors = $e->getMessage();
}

include('../PUBLIC/header.php');   
?>

<body>
    <section class="body">

        <?php include('../PUBLIC/navbarmenu.php'); ?>

        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2> Rapport medical d'évacuation.</h2>
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <section class="card">
                            <div class="card-body">
                            <?php
                                        if ($errors==4) {
                                        echo '
                                            <div class="alert alert-success">
                                            <strong>Rapport medical d\'évacuation du patient '.nom_patient($id_patient).' validé avec succès </strong> <br/> 
                                            <li>Les informations relatives au traitement ont été enregistrées avec succès dans l\'espace du patient. Il peut toujours le consulter dans son propre espace ou <a href="imprimer_rapport.php?affectation='.$affectation.'" target="_blank">imprimer les données</a></li>
                                            </div>
                                            ';
                                                }
                                        if ($existe==1) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <strong>Erreur de validation</strong> <br/>  
                                                <li>Ce '.model($types).' a déjà été approuvé ou veuillez vérifier les informations requises à fournir. Il est également possible <a href="imprimer_rapport.php?affectation='.$affectation.'" target="_blank">d\'imprimer les données</a></li>
                                            </div>
                                            ';}
                                        if ($errors==5) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <strong>Erreur de fichier</strong> <br/>  
                                                <li>La photo de pathologie est obligatoire. Veuillez sélectionner un fichier image (JPEG, PNG, GIF) de moins de 5 Mo.</li>
                                            </div>
                                            ';}
                                    ?>
                                    <?php include __DIR__ . '/../public/acquitehistorique.php'; ?>
                            </div>
                            <!-- forumalire de rapport medical -->
                            <form class="form-horizontal" novalidate="novalidate" method="POST"
                                action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?affectation=<?php echo $affectation; ?>" enctype="multipart/form-data">
                                <input type="hidden" name="consulter" value="<?php $id_affectation ?>">

                                <div class="row form-group pb-3">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Photo de la pathologie (Obligatoire)</label>
                                            <input type="file" name="pathologie" class="form-control" value="<?php echo getFormValue('pathologie'); ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="row form-group pb-3">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Rapportement</label>
                                            <textarea name="rapport" class="form-control" rows="10" placeholder="Obligatoire" required><?php echo getFormValue('rapport'); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            
                            <footer class="card-footer text-end">
                                <button class="btn btn-primary" type="submit" name="ajouter">Valider le rapport</button>
                            </footer>
                        </form>
                    </section>
                </div>
            </div>
        <!-- end: page -->
    </section>
    </div>
        <?php if ($errors == 4 && $affectation): ?>
            <script>
                window.onload = function() {
                    window.open('imprimer_rapport.php?affectation=<?= $affectation ?>', '_blank');
                };
            </script>
        <?php endif; ?>
        <?php include('../PUBLIC/footer.php');?>

    <script>
    document.querySelector('form').addEventListener('submit', function(e) {
        const pathologieFile = document.querySelector('input[name="pathologie"]');
        const rapport = document.querySelector('textarea[name="rapport"]');
        
        // Validation du rapport
        if (!rapport.value.trim()) {
            e.preventDefault();
            alert('Veuillez saisir le rapport médical');
            rapport.focus();
            return false;
        }
        
        // Validation de la photo pathologie (obligatoire)
        if (!pathologieFile.files.length) {
            e.preventDefault();
            alert('Veuillez sélectionner une photo de pathologie (obligatoire)');
            pathologieFile.focus();
            return false;
        }
        
        const file = pathologieFile.files[0];
        
        // Validation de la taille du fichier (5 Mo max)
        if (file.size > 5 * 1024 * 1024) {
            e.preventDefault();
            alert('La photo de pathologie ne doit pas dépasser 5 Mo');
            pathologieFile.focus();
            return false;
        }
        
        // Validation du type de fichier
        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
        if (!validTypes.includes(file.type.toLowerCase())) {
            e.preventDefault();
            alert('La photo de pathologie doit être au format JPEG, PNG ou GIF');
            pathologieFile.focus();
            return false;
        }
    });
    </script>
</body>
</html>
```
   