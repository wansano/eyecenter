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
    $stmt = $bdd->prepare(' SELECT a.*, p.nom_patient, p.responsable FROM affectations a
        JOIN patients p ON a.id_patient = p.id_patient WHERE a.id_affectation = ? ');
    $stmt->execute([$affectation]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $id_patient = $data['id_patient'];

    $derniereDonnees = recupererDerniereAcquiteEtHistorique($bdd, $id_patient);

    if (!$data) {
        throw new Exception("Affectation non trouvée");
    }

    // Extraction des données
    extract($data);

    // Traitement du formulaire
    if (isset($_POST['ajouter'])) {
        // Vérification si déjà traité
        $existe = checkTraitementExisteChirurgie($bdd, $affectation);
        
        // Debug pour voir la valeur de $existe
        error_log("Debug checkTraitementExisteChirurgie - affectation: $affectation, existe: $existe");

        // Validation du fichier biométrie (obligatoire)
        if (!isset($_FILES['biometrie']) || $_FILES['biometrie']['error'] != UPLOAD_ERR_OK) {
            $errors = 5; // Erreur fichier biométrie manquant
        } else {
            $bdd->beginTransaction();
            
            try {
                // Création du dossier documents s'il n'existe pas
                $documentsDir = '../documents/';
                if (!is_dir($documentsDir)) {
                    mkdir($documentsDir, 0755, true);
                }

                // Upload de la kerato biométrie (obligatoire)
                $biometrieFileName = '';
                if ($_FILES['biometrie']['error'] == UPLOAD_ERR_OK) {
                    $extension = pathinfo($_FILES['biometrie']['name'], PATHINFO_EXTENSION);
                    $biometrieFileName = 'biometrie_' . $id_patient . '_' . $affectation . '_' . time() . '.' . $extension;
                    $biometriePath = $documentsDir . $biometrieFileName;
                    
                    if (!move_uploaded_file($_FILES['biometrie']['tmp_name'], $biometriePath)) {
                        throw new Exception("Erreur lors de l'upload de la biométrie");
                    }
                }

                // Upload de l'échographie (optionnelle)
                $echographieFileName = '';
                if (isset($_FILES['echographie']) && $_FILES['echographie']['error'] == UPLOAD_ERR_OK) {
                    $extension = pathinfo($_FILES['echographie']['name'], PATHINFO_EXTENSION);
                    $echographieFileName = 'echographie_' . $id_patient . '_' . $affectation . '_' . time() . '.' . $extension;
                    $echographiePath = $documentsDir . $echographieFileName;
                    
                    if (!move_uploaded_file($_FILES['echographie']['tmp_name'], $echographiePath)) {
                        throw new Exception("Erreur lors de l'upload de l'échographie");
                    }
                }

                // Préparer les données pour l'insertion
                $postData = $_POST;
                $postData['biometrie_file'] = $biometrieFileName;
                $postData['echographie_file'] = $echographieFileName;

                UpdateChirurgieKBECHO($bdd, $biometrieFileName, $echographieFileName, $affectation, $postData);
                updateAffectationStatusKBECHO($bdd, $affectation);

                $bdd->commit();
                $errors = 4;
            } catch (Exception $e) {
                $bdd->rollBack();
                error_log("Erreur lors du traitement de la biométrie : " . $e->getMessage());
                $errors = 1;
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
                    <h2>Chirurgie d'un patient</h2>
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <section class="card">
                        <div class="card-body">
                            <?php if ($errors == 4): ?>
                                <div class="alert alert-success">
                                    <strong>Succès!</strong> La biométrie a été enregistrée avec succès.
                                </div>
                            <?php elseif ($errors == 1): ?>
                                <div class="alert alert-danger">
                                    <strong>Erreur!</strong> Une erreur est survenue lors de l'enregistrement.
                                </div>
                            <?php elseif ($errors == 5): ?>
                                <div class="alert alert-danger">
                                    <strong>Erreur!</strong> Le fichier de kerato biométrie est obligatoire.
                                </div>
                            <?php elseif (isset($_POST['ajouter']) && $existe > 0): ?>
                                <div class="alert alert-warning">
                                    <strong>Attention!</strong> Ce patient a déjà été traité pour cette affectation. (Debug: existe = <?= $existe ?>)
                                </div>
                            <?php endif; ?>
                            
                            <!-- Formulaire de consultation -->
                                <?php include __DIR__ . '/../public/acquitehistorique.php'; ?>

                                <form class="form-horizontal" novalidate="novalidate" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?affectation=<?php echo $affectation; ?>" enctype="multipart/form-data" id="biometrieForm">
                                    <input type="hidden" name="ajouter" value="1">
                                    <div class="row form-group pb-3">                                    
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="col-form-label" for="biometrie">Kerato Biométrie <span class="text-danger">*</span></label>
                                                <input type="file" name="biometrie" id="biometrie" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" required>
                                                <small class="form-text text-muted">Formats acceptés: JPG, PNG, PDF, DOC, DOCX (obligatoire)</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="col-form-label" for="echographie">Échographie</label>
                                                <input type="file" name="echographie" id="echographie" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                                                <small class="form-text text-muted">Formats acceptés: JPG, PNG, PDF, DOC, DOCX (optionnel)</small>
                                            </div>
                                        </div>
                                    </div>
                                <footer class="card-footer text-end">
                                    <button class="btn btn-primary" type="submit" name="ajouter">Valider la biométrie</button>
                                </footer>
                            </form>
                            
                            <script>
                                document.getElementById('biometrieForm').addEventListener('submit', function(e) {
                                    const biometrieFile = document.getElementById('biometrie').files[0];
                                    
                                    if (!biometrieFile) {
                                        e.preventDefault();
                                        alert('Le fichier de kerato biométrie est obligatoire.');
                                        return false;
                                    }
                                    
                                    // Vérification de la taille du fichier (max 10MB)
                                    const maxSize = 10 * 1024 * 1024; // 10MB
                                    if (biometrieFile.size > maxSize) {
                                        e.preventDefault();
                                        alert('Le fichier de biométrie est trop volumineux (maximum 10MB).');
                                        return false;
                                    }
                                    
                                    const echographieFile = document.getElementById('echographie').files[0];
                                    if (echographieFile && echographieFile.size > maxSize) {
                                        e.preventDefault();
                                        alert('Le fichier d\'échographie est trop volumineux (maximum 10MB).');
                                        return false;
                                    }
                                });
                            </script>
                        <!-- Fin du formulaire de consultation -->
                    </section>
                </div>
            </div>
        <!-- end: page -->
    </section>
    </div>
        <?php if ($errors == 4 && $affectation): ?>
            <script>
                window.onload = function() {
                    window.open('imprimer_chirurgie.php?affectation=<?= $affectation ?>', '_blank');
                };
            </script>
        <?php endif; ?>
    <?php include('../PUBLIC/footer.php');?>
