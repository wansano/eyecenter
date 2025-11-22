<?php 
$derniereDonnees = [];
$errors = 0;

try {
    // Vérification des paramètres
    if (!isset($_GET['affectation'])) {
        throw new Exception("ID d'affectation manquant");
    }

    $affectation = $_GET['affectation'];

    // Récupération des dernières données d'acuité visuelle et historique en une seule fois
    $derniereDonnees = recupererDerniereAcquiteEtHistorique($bdd, $id_patient);

    if (!$derniereDonnees || $derniereDonnees === false) {
        throw new Exception("Affectation non trouvée");
    }

} catch (Exception $e) {
    $errors = $e->getMessage();
    $derniereDonnees = []; // Initialiser comme tableau vide en cas d'erreur
}

// Fonction helper pour accéder aux valeurs de manière sécurisée
function getValue($array, $key, $default = '') {
    if (!is_array($array)) {
        return $default;
    }
    return isset($array[$key]) && $array[$key] !== null ? htmlspecialchars((string)$array[$key], ENT_QUOTES, 'UTF-8') : $default;
}
?>
 
 <div class="row form-group pb-3 text-color-dark">  
    <!-- Affichage des dernières données historique. -->
     <div class="col-md-6">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">Motif</label>
            <input type="text" maxlength="9" name="avlscod" class="form-control" placeholder="Obligatoire" required value="<?php echo getValue($derniereDonnees, 'motif'); ?>" disabled>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">Evolution</label>
            <input type="text" maxlength="9" name="avlscos" class="form-control" placeholder="Obligatoire" required value="<?php echo getValue($derniereDonnees, 'evolution'); ?>" disabled>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">Terrain</label>
            <input type="text" name="avcod" class="form-control" placeholder="Facultatif" value="<?php echo getValue($derniereDonnees, 'terrain'); ?>" disabled>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">Antécédants</label>
            <input type="text" name="avcod" class="form-control" placeholder="Facultatif" value="<?php echo getValue($derniereDonnees, 'antecedents'); ?>" disabled>
        </div>
    </div>
    <!--
     Affichage des dernières données d'acuité visuelle.
     -->                              
    <div class="col-md-1">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">AVLSC OD</label>
            <input type="text" maxlength="9" name="avlscod" class="form-control" placeholder="Obligatoire" required value="<?php echo getValue($derniereDonnees, 'od_avlsc'); ?>" disabled>
        </div>
    </div>
    <div class="col-md-1">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">AVLSC OS</label>
            <input type="text" maxlength="9" name="avlscos" class="form-control" placeholder="Obligatoire" required value="<?php echo getValue($derniereDonnees, 'os_avlsc'); ?>" disabled>
        </div>
    </div>
    <div class="col-md-1">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">AVC OD</label>
            <input type="text" name="avcod" class="form-control" placeholder="Facultatif" value="<?php echo getValue($derniereDonnees, 'od_avc'); ?>" disabled>
        </div>
    </div>
    <div class="col-md-1">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">AVC OS</label>
            <input type="text" name="avcos" class="form-control" placeholder="Facultatif" value="<?php echo getValue($derniereDonnees, 'os_avc'); ?>" disabled>
        </div>
    </div>
    <div class="col-md-1">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">TS OD</label>
            <input type="text" name="tsod" class="form-control" placeholder="Facultatif" value="<?php echo getValue($derniereDonnees, 'od_ts'); ?>" disabled>
        </div>
    </div>
    <div class="col-md-1">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">TS OS</label>
            <input type="text" name="tsos" class="form-control" placeholder="Facultatif" value="<?php echo getValue($derniereDonnees, 'os_ts'); ?>" disabled>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">P</label>
            <input type="text" name="p" class="form-control" placeholder="Facultatif" value="<?php echo getValue($derniereDonnees, 'p'); ?>" disabled>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput">Resultat Glycémie</label>
            <input type="text" name="" class="form-control" placeholder="Facultatif" value="<?php echo getValue($derniereDonnees, 'glycemie', '0'); ?>" disabled>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label class="col-form-label" for="formGroupExampleInput"><?= nom_patient($id_patient);?> </label>
            <a class="btn btn-info" target="_blank" href="../impression/_historique_traitements.php?id_patient=<?php echo (int)$id_patient; ?>">voir historique dossier</a>
        </div>
    </div>
</div>
<hr class="my-4">