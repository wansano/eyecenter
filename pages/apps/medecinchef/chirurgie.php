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

    if (!$data) {
        throw new Exception("Affectation non trouvée");
    }

    // Extraction des données
    extract($data);

    // Traitement du formulaire
    if (isset($_POST['consulter']) && !empty($_POST['traitement']) && !empty($_POST['protocole']) && !empty($_POST['diagnostic']) && !empty($_POST['prescription']) && !empty($_POST['avlscod']) && !empty($_POST['avlscos'])) {
        // Vérification si déjà traité
        $existe = checkTraitementExisteChirurgie($bdd, $affectation);

        if ($existe == 0) {
            $bdd->beginTransaction();
            
            try {
                insertAcquitteVisuelle($bdd, $id_patient, $affectation, $_POST);
                insertChirurgie($bdd, $id_patient, $type, $affectation, $_POST);
                updateAffectationStatus($bdd, $affectation);

                if (!empty($_POST['prochain_rdv'])) {
                    insererRendezVousInterne($bdd, $id_patient, $_POST['service'], $_POST['type'], $_POST['medecin'], $_POST['prochain_rdv']);
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
                            <?php
                                        if ($errors==4) {
                                            echo '
                                                <div class="alert alert-success">
                                                <strong>'.model($type).' de '.nom_patient($id_patient).' validé avec succès </strong> <br/> 
                                                <li>Les informations relatives au traitement ont été enregistrées avec succès dans l\'espace du patient. Il peut toujours le consulter dans son propre espace ou <a href="traitementdata.php?affectation='.$affectation.'" target="_blank">imprimer les données</a></li>
                                                </div>
                                                ';
                                                    }
                                            if ($existe==1) {
                                            echo '
                                                <div class="alert alert-danger">
                                                    <strong>Erreur de validation de '.model($type).'  de '.nom_patient($id_patient).'</strong> <br/>  
                                                    <li>Cette '.model($type).' a déjà été approuvé ou veuillez vérifier les informations requises à fournir. Il est également possible <a href="traitementdata.php?affectation='.$affectation.'" target="_blank">d\'imprimer les données</a></li>
                                                </div>
                                                ';}
                                    ?>
                                <div class="row form-group pb-3">
                                <div class="col-md-6">
                                    <div class="alert alert-info">
                                        <?php 
                                        $accu = $bdd->prepare('SELECT * FROM acquitte_visuelle WHERE id_patient=? ORDER BY id_acquitte DESC LIMIT 0,1');
                                        $accu->execute(array($id_patient));
                                            while ($accuite = $accu->fetch(PDO::FETCH_ASSOC))
                                            { 
                                                $hist = $bdd->prepare('SELECT * FROM historique WHERE id_patient=? ORDER BY id_historique DESC LIMIT 0,1');
                                                $hist->execute(array($id_patient));
                                                while ($historique = $hist->fetch(PDO::FETCH_ASSOC))
                                                    {
                                        echo '<li><strong>Dernière accuité visuelle : </strong> <br/> <b>AVLSC : </b>   OD='.($accuite['od_avlsc'] ?: '........').'   OS='.($accuite['os_avlsc'] ?: '........').'       <b>AVC : </b>  OD='.($accuite['od_avc'] ?: '........').'   OS='.($accuite['os_avc'] ?: '........').'        <b>TS : </b>  OD='.($accuite['od_ts'] ?: '........').'   OS='.($accuite['os_ts'] ?: '........').'       <b>P : </b> '.($accuite['p'] ?: '........').'</li>';
                                        }
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="alert alert-info">
                                        <?php
                                        $accu = $bdd->prepare('SELECT * FROM acquitte_visuelle WHERE id_patient=? ORDER BY id_acquitte DESC LIMIT 0,1');
                                        $accu->execute(array($id_patient));
                                            while ($accuite = $accu->fetch(PDO::FETCH_ASSOC))
                                            { 
                                                $hist = $bdd->prepare('SELECT * FROM historique WHERE id_patient=? ORDER BY id_historique DESC LIMIT 0,1');
                                                $hist->execute(array($id_patient));
                                                while ($historique = $hist->fetch(PDO::FETCH_ASSOC))
                                                    { echo '<strong>Motif : </strong>'.$historique['motif'].'; <strong>Evolution : </strong>'.$historique['evolution'].'; <br/> <strong>Terrain :</strong> '.$historique['terrain'].'; <strong>Antecedents : </strong>'.$historique['antecedents'].''; } 
                                            }   
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <!-- Formulaire de consultation -->
                            <form class="form-horizontal" novalidate="novalidate" method="POST"
                                action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?affectation=<?php echo $affectation; ?>" enctype="multipart/form-data">
                                <input type="hidden" name="consulter" value="<?php $id_affectation ?>">
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
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Diagnostic</label>
                                            <textarea name="diagnostic" class="form-control" rows="3" placeholder="Obligatoire" required><?php echo getFormValue('diagnostic'); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Traitement</label>
                                            <textarea name="traitement" class="form-control" rows="3" placeholder="Facultatif" required><?php echo getFormValue('traitement'); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Protocole</label>
                                            <textarea name="protocole" class="form-control" rows="3" placeholder="Facultatif" required><?php echo getFormValue('protocole'); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Prescription</label>
                                            <textarea name="prescription" class="form-control" rows="3" placeholder="Obligatoire" required><?php echo getFormValue('prescription'); ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="row form-group pb-3">
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Service</label>
                                            <select name="service" class="form-control populate" id="serviceSelect" onchange="updateMotifs()">
                                                <option value=""> ------ service ----- </option>';
                                                    <?php $coll = $bdd->prepare('SELECT * FROM services WHERE status = ?');
                                                    $coll -> execute([1]);
                                                    while ($services = $coll->fetch(PDO::FETCH_ASSOC))
                                                    {
                                                        echo '<option value="'.$services['id_service'].'">'.$services['nom_service'].'</option>';
                                                    } ?>
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Motif </label>
                                            <select class="form-control populate" id="motifSelect" name="type" onchange="fetchMotifPrice()" data-plugin-selectTwo data-plugin-options='{ "minimumInputLength": 0 }' required>
                                                <option value=""> ------ Choisir un service d'abord ----- </option>
                                            </select>
                                            <input type="hidden" id="hiddenMotifId" name="motif_id" value="">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Medecin</label>
                                            <select name="medecin" data-plugin-selectTwo class="form-control populate" data-plugin-options='{ "minimumInputLength": 0 }'>
                                                <optgroup label="Choisir le medecin">
                                                    <option value=""> --- Choisir le medecin --- </option>
                                                    <?php
                                                    $med = $bdd->prepare('SELECT id, pseudo FROM users WHERE type="medecin" AND status=1');
                                                    $med->execute();
                                                    while ($medecin = $med->fetch(PDO::FETCH_ASSOC)) {
                                                        $selected = (isset($_POST['medecin']) && $_POST['medecin'] == $medecin['id']) ? 'selected' : '';
                                                        echo '<option value="' . $medecin['id'] . '" ' . $selected . '>' . htmlspecialchars($medecin['pseudo']) . '</option>';
                                                    }
                                                    ?>
                                                </optgroup>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Date prochain rendez-vous</label>
                                            <input type="date" class="form-control mb-2" name="date_rdv" id="dateRdvInput" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Créneau disponible</label>
                                            <select name="prochain_rdv" class="form-control" id="creneauSelect" required>
                                                <option value="">-- Choisir un créneau disponible --</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                            <footer class="card-footer text-end">
                                <button class="btn btn-primary" type="submit" name="ajouter">Valider la chirurgie</button>
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
                    window.open('imprimer_chirurgie.php?affectation=<?= $affectation ?>', '_blank');
                };
            </script>
        <?php endif; ?>
    <?php include('../PUBLIC/footer.php');?>
