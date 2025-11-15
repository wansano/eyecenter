<?php
// Includes corrigés (dossier public en minuscules)
include('../public/connect.php');
require_once('../public/fonction.php');
include('../public/medecin_ieu.php');

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
    if (!$data) {
        throw new Exception("Affectation non trouvée");
    }
    $id_patient = isset($data['id_patient']) ? (int)$data['id_patient'] : 0;
    $rdv = isset($data['id_rdv']) ? (int)$data['id_rdv'] : 0;

    // Récupération des dernières données d'acuité visuelle et historique (si patient valide)
    $derniereDonnees = $id_patient ? recupererDerniereAcquiteEtHistorique($bdd, $id_patient) : null;

    // Extraction des données
    extract($data);

    // Traitement du formulaire (evolution devient facultatif)
    if ($_SERVER['REQUEST_METHOD'] === 'POST'
        && !empty($_POST['traitement'])
        && !empty($_POST['motif'])
        && !empty($_POST['diagnostic'])
        && !empty($_POST['prescription'])) {
        // Vérification si déjà traité
        $existe = checkTraitementExisteConsultation($bdd, $affectation);

        if ($existe == 0) {
            $bdd->beginTransaction();
            
            try {
                insertHistorique($bdd, $id_patient, $_POST);
                insertAcquitteVisuelle($bdd, $id_patient, $affectation, $_POST);
                insertConsultation($bdd, $id_patient, $type, $affectation, $_POST);
                updateAffectationStatus($bdd, $affectation);
                if ($rdv > 0) {
                    updateRendezvousStatus($bdd, $rdv);
                }
                $bdd->commit();
                $errors = 4;
            } catch (Exception $e) {
                $bdd->rollBack();
                error_log("Erreur lors du traitement de la consultation : " . $e->getMessage());
                $errors = 1;
            }
        }         
    }  
} catch (Exception $e) {
    $errors = $e->getMessage();
}

include('../public/header.php');   
?>

<body>
    <section class="body">

    <?php include('../public/navbarmenu.php'); ?>

        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Consultation d'un patient</h2>
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <section class="card">
                        <div class="card-body">
                            <?php
                                        if ($errors==4) {
                                            echo '
                                                <div class="alert alert-success">
                                                <strong>'.model($type).' de '.nom_patient($id_patient).' validé avec succès </strong> <br/> 
                                                <li>Les informations relatives au traitement ont été enregistrées avec succès dans l\'espace du patient. Il peut toujours le consulter dans son propre espace ou <a href="imprimer_consultation.php?affectation='.$affectation.'" target="_blank">imprimer les données</a></li>
                                                </div>
                                                ';
                                                    }
                                            if ($existe==1) {
                                            echo '
                                                <div class="alert alert-danger">
                                                    <strong>Erreur de validation de '.model($type).'  de '.nom_patient($id_patient).'</strong> <br/>  
                                                    <li>Cette '.model($type).' a déjà été approuvé ou veuillez vérifier les informations requises à fournir. Il est également possible <a href="imprimer_consultation.php?affectation='.$affectation.'" target="_blank">d\'imprimer les données</a></li>
                                                </div>
                                                ';}
                                    ?>
                            <?php include __DIR__ . '/../public/acquitehistorique.php'; ?>
                            <!-- Formulaire de consultation -->
                            <form class="form-horizontal" novalidate="novalidate" method="POST"
                                action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?affectation=<?php echo $affectation; ?>" enctype="multipart/form-data">
                                <input type="hidden" name="consulter" value="<?php echo htmlspecialchars($affectation, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="row form-group pb-3">                                    
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Motif de consultation</label>
                                            <input type="text" name="motif" class="form-control" placeholder="Obligatoire" required value="<?php echo getFormValue('motif'); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Evolution</label>
                                            <input type="text" name="evolution" class="form-control" placeholder="Facultatif" value="<?php echo getFormValue('evolution'); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Terrain</label>
                                            <input type="text" name="terrain" class="form-control" placeholder="Facultatif" value="<?php echo getFormValue('terrain'); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Antecedents</label>
                                            <input type="text" name="antecedents" class="form-control" placeholder="Facultatif" value="<?php echo getFormValue('antecedents'); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row form-group pb-3">                                    
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">AVLSC OD</label>
                                            <input type="text" maxlength="9" name="avlscod" class="form-control" placeholder="Obligatoire" required value="<?php echo getFormValue('avlscod'); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">AVLSC OS</label>
                                            <input type="text" maxlength="9" name="avlscos" class="form-control" placeholder="Obligatoire" required value="<?php echo getFormValue('avlscos'); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">AVC OD</label>
                                            <input type="text" name="avcod" class="form-control" placeholder="Facultatif" value="<?php echo getFormValue('avcod'); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">AVC OS</label>
                                            <input type="text" name="avcos" class="form-control" placeholder="Facultatif" value="<?php echo getFormValue('avcos'); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">TS OD</label>
                                            <input type="text" name="tsod" class="form-control" placeholder="Facultatif" value="<?php echo getFormValue('tsod'); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">TS OS</label>
                                            <input type="text" name="tsos" class="form-control" placeholder="Facultatif" value="<?php echo getFormValue('tsos'); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">P</label>
                                            <input type="text" name="p" class="form-control" placeholder="Facultatif" value="<?php echo getFormValue('p'); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row form-group pb-3">                                    
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Diagnostic</label>
                                            <textarea name="diagnostic" class="form-control" rows="3" placeholder="Obligatoire" required><?php echo getFormValue('diagnostic'); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Bilan</label>
                                            <textarea name="bilan" class="form-control" rows="3" placeholder="Facultatif"><?php echo getFormValue('bilan'); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-2 align-items-center h-100">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Traitement</label>
                                            <select name="traitement" id="" class="form-control" value="<?php echo getFormValue('traitement'); ?>">
                                                <option value="Ordonnance médicale">Ordonnance médicale</option>
                                                <option value="Soins">Soins</option>
                                                <option value="Chirurgie">Chirurgie</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Prescription</label>
                                            <textarea name="prescription" class="form-control" rows="3" placeholder="Obligatoire" required><?php echo getFormValue('prescription'); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            
                            <footer class="card-footer text-end">
                                <button class="btn btn-primary" type="submit" name="ajouter">Valider la consultation</button>
                            </footer>
                        </form>
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
                    window.open('imprimer_consultation.php?affectation=<?= $affectation ?>', '_blank');
                };
            </script>
        <?php endif; ?>
    <?php include('../public/footer.php');?>
