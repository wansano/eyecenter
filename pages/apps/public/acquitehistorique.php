 <?php 
 try {
    // Vérification des paramètres
    if (!isset($_GET['affectation'])) {
        throw new Exception("ID d'affectation manquant");
    }

    $existe = 0; $errors = 0;
    $affectation = $_GET['affectation'];

    // Récupération des dernières données d'acuité visuelle et historique en une seule fois
    $derniereDonnees = recupererDerniereAcquiteEtHistorique($bdd, $id_patient);

    if (!$data) {
        throw new Exception("Affectation non trouvée");
    }

    // Extraction des données
    extract($data);
 
        // Traitement du formulaire
        $derniereDonnees = recupererDerniereAcquiteEtHistorique($bdd, $id_patient);
    } catch (Exception $e) {
        $errors = $e->getMessage();
    }
 ?>
 
 <div class="row form-group pb-3 text-color-dark">  
    <!-- Affichage des dernières données historique. -->
     <div class="col-md-6">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">Motif</label>
            <input type="text" maxlength="9" name="avlscod" class="form-control" placeholder="Obligatoire" required value="<?php echo htmlspecialchars($derniereDonnees['motif']) ?: NULL; ?>" disabled>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">Evolution</label>
            <input type="text" maxlength="9" name="avlscos" class="form-control" placeholder="Obligatoire" required value="<?php echo htmlspecialchars($derniereDonnees['evolution']) ?: NULL; ?>" disabled>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">Terrain</label>
            <input type="text" name="avcod" class="form-control" placeholder="Facultatif" value="<?php echo htmlspecialchars($derniereDonnees['terrain']) ?: NULL; ?>" disabled>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">Antécédants</label>
            <input type="text" name="avcod" class="form-control" placeholder="Facultatif" value="<?php echo htmlspecialchars($derniereDonnees['antecedents']) ?: NULL; ?>" disabled>
        </div>
    </div>
    <!--
     Affichage des dernières données d'acuité visuelle.
     -->                              
    <div class="col-md-1">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">AVLSC OD</label>
            <input type="text" maxlength="9" name="avlscod" class="form-control" placeholder="Obligatoire" required value="<?php echo $derniereDonnees['od_avlsc']? : NULL ; ?>" disabled>
        </div>
    </div>
    <div class="col-md-1">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">AVLSC OS</label>
            <input type="text" maxlength="9" name="avlscos" class="form-control" placeholder="Obligatoire" required value="<?php echo $derniereDonnees['os_avlsc']? : NULL; ?>" disabled>
        </div>
    </div>
    <div class="col-md-1">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">AVC OD</label>
            <input type="text" name="avcod" class="form-control" placeholder="Facultatif" value="<?php echo $derniereDonnees['od_avc']? : NULL; ?>" disabled>
        </div>
    </div>
    <div class="col-md-1">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">AVC OS</label>
            <input type="text" name="avcos" class="form-control" placeholder="Facultatif" value="<?php echo $derniereDonnees['os_avc'] ?: NULL; ?>" disabled>
        </div>
    </div>
    <div class="col-md-1">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">TS OD</label>
            <input type="text" name="tsod" class="form-control" placeholder="Facultatif" value="<?php echo $derniereDonnees['od_ts'] ?: NULL; ?>" disabled>
        </div>
    </div>
    <div class="col-md-1">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">TS OS</label>
            <input type="text" name="tsos" class="form-control" placeholder="Facultatif" value="<?php echo $derniereDonnees['os_ts'] ?: NULL; ?>" disabled>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">P</label>
            <input type="text" name="p" class="form-control" placeholder="Facultatif" value="<?php echo $derniereDonnees['p'] ?: NULL; ?>" disabled>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">Resultat Glycémie</label>
            <input type="text" name="" class="form-control" placeholder="Facultatif" value="<?php echo $derniereDonnees['glycemie'] ?: 0; ?>" disabled>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput"><?= nom_patient($id_patient);?> </label>
            <button class="btn btn-info" name="voir_dossier" class="form-control"> voir le dossier </button>
        </div>
    </div>
</div>
<hr class="my-4">