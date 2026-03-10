<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

$isModal = isset($_GET['modal']) && (string)$_GET['modal'] === '1';

// Ajout ville (AJAX) : renvoie JSON et stoppe l'exécution pour ne pas afficher toute la page.
if (isset($_POST['ajax_add_ville'])) {
    header('Content-Type: application/json; charset=UTF-8');

    $ville = isset($_POST['ville']) ? trim((string)$_POST['ville']) : '';
    $region = isset($_POST['region']) ? trim((string)$_POST['region']) : '';
    if ($ville === '') {
        echo json_encode(['success' => false, 'message' => 'Le nom de la ville est requis.']);
        exit;
    }
    if ($region === '') {
        echo json_encode(['success' => false, 'message' => 'La région est requise.']);
        exit;
    }

    $allowedRegions = ['Basse Guinée', 'Moyenne Guinée', 'Haute Guinée', 'Guinée Forestière'];
    if (!in_array($region, $allowedRegions, true)) {
        echo json_encode(['success' => false, 'message' => 'Région invalide.']);
        exit;
    }

    try {
        // Vérifier doublon (nom)
        $stExists = $bdd->prepare('SELECT id_ville, nom, region FROM adresses_villes WHERE nom = ? LIMIT 1');
        $stExists->execute([$ville]);
        $existing = $stExists->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            echo json_encode([
                'success' => true,
                'already_exists' => true,
                'id' => (int)$existing['id_ville'],
                'nom' => (string)$existing['nom'],
                'region' => (string)($existing['region'] ?? ''),
                'message' => 'Cette ville existe déjà.'
            ]);
            exit;
        }

        $cols = ['nom', 'region'];
        $vals = [$ville, $region];
        if (function_exists('dbTableHasColumn') && dbTableHasColumn($bdd, 'adresses_villes', 'status')) {
            $cols[] = 'status';
            $vals[] = 1;
        }

        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sql = 'INSERT INTO adresses_villes (' . implode(',', $cols) . ') VALUES (' . $placeholders . ')';
        $stIns = $bdd->prepare($sql);
        $stIns->execute($vals);
        $newId = (int)$bdd->lastInsertId();

        echo json_encode([
            'success' => true,
            'already_exists' => false,
            'id' => $newId,
            'nom' => $ville,
            'message' => 'Ville ajoutée.'
        ]);
        exit;
    } catch (Exception $e) {
        error_log('[AJOUT VILLE] ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => "Erreur lors de l'ajout de la ville."]);
        exit;
    }
}

// Ajout quartier (AJAX) : renvoie JSON et stoppe l'exécution pour ne pas afficher toute la page.
if (isset($_POST['ajax_add_quartier'])) {
    header('Content-Type: application/json; charset=UTF-8');

    $villeId = isset($_POST['ville_id']) ? (int)$_POST['ville_id'] : 0;
    $quartier = isset($_POST['quartier']) ? trim((string)$_POST['quartier']) : '';

    if ($villeId <= 0 || $quartier === '') {
        echo json_encode(['success' => false, 'message' => 'Ville et quartier sont requis.']);
        exit;
    }

    try {
        $bdd->beginTransaction();

        // Vérifier si la ville existe
        $stVille = $bdd->prepare('SELECT COUNT(*) FROM adresses_villes WHERE id_ville = ?');
        $stVille->execute([$villeId]);
        if ((int)$stVille->fetchColumn() <= 0) {
            throw new Exception('Ville invalide.');
        }

        // Vérifier doublon (même quartier dans la même ville)
        $stExists = $bdd->prepare('SELECT id_quartier FROM adresses_quartiers WHERE quartier = ? AND id_ville = ? LIMIT 1');
        $stExists->execute([$quartier, $villeId]);
        $existing = $stExists->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $bdd->rollBack();
            echo json_encode([
                'success' => true,
                'already_exists' => true,
                'id' => (int)$existing['id_quartier'],
                'nom' => $quartier,
                'message' => 'Ce quartier existe déjà.'
            ]);
            exit;
        }

        $stIns = $bdd->prepare('INSERT INTO adresses_quartiers (id_ville, quartier) VALUES (?, ?)');
        $stIns->execute([$villeId, $quartier]);
        $newId = (int)$bdd->lastInsertId();

        $bdd->commit();

        echo json_encode([
            'success' => true,
            'already_exists' => false,
            'id' => $newId,
            'nom' => $quartier,
            'message' => 'Quartier ajouté.'
        ]);
        exit;
    } catch (Exception $e) {
        if ($bdd->inTransaction()) {
            $bdd->rollBack();
        }
        error_log('[AJOUT QUARTIER] ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => "Erreur lors de l'ajout du quartier."]); 
        exit;
    }
}

$errors = 0;
$existe = 0;
$id_patient = null;
$error_messages = array();

if (isset($_POST['ajouter'])) {
    // Vérification des champs requis
    if (empty($_POST['nom_patient'])) {
        $error_messages[] = "Le nom du patient est requis";
    }
    if (empty($_POST['age'])) {
        $error_messages[] = "La date de naissance est requise";
    }
    if (empty($_POST['phone'])) {
        $error_messages[] = "Le numéro de téléphone est requis";
    }
    if (empty($_POST['profession'])) {
        $error_messages[] = "La profession est requise";
    }
    if (empty($_POST['sexe'])) {
        $error_messages[] = "Le genre est requis";
    }
    if (empty($_POST['adresse'])) {
        $error_messages[] = "L'adresse est requise";
    }

    // Champs assurance si assuré
    $assurePost = isset($_POST['estAssure']) && (string)$_POST['estAssure'] === '1';
    if ($assurePost) {
        $entrepriseAssurance = (int)($_POST['entrepriseAssurance'] ?? 0);
        if ($entrepriseAssurance <= 0) {
            $error_messages[] = "Veuillez choisir l'assureur.";
        }

        $taux = trim((string)($_POST['tauxPrisecharge'] ?? ''));
        if ($taux !== '' && (!is_numeric($taux) || (float)$taux < 0 || (float)$taux > 100)) {
            $error_messages[] = "Le taux de prise en charge doit être compris entre 0 et 100.";
        }
    }

    // Si pas d'erreurs, procéder à l'insertion
    if (empty($error_messages)) {
        try {
            $bdd->beginTransaction();

            // Vérification de l'existence du patient
            $req1 = $bdd->prepare('SELECT id_patient FROM patients WHERE phone = ? AND profession = ? AND sexe = ? AND adresse = ? LIMIT 1');
            $req1->execute([
                $_POST['phone'],
                $_POST['profession'], 
                $_POST['sexe'], 
                $_POST['adresse']
            ]);

            if ($data = $req1->fetch()) {
                $existe = 1;
                $patientid = $data['id_patient'];
                $id_patient = (int)$patientid;
            } else {
                // Insertion du patient
                $assure = $assurePost ? 1 : 0;
                $responsable = !empty($_POST['responsable']) ? $_POST['responsable'] : null;

                $entrepriseAssurance = $assure ? (int)($_POST['entrepriseAssurance'] ?? 0) : 0;
                $carteAdhesion = $assure ? trim((string)($_POST['carteAdhesion'] ?? '')) : null;
                $tauxPrisecharge = $assure ? trim((string)($_POST['tauxPrisecharge'] ?? '')) : null;
                $dateExpiration = $assure ? (($_POST['dateExpiration'] ?? null) ?: null) : null;

                $cols = ['nom_patient','sexe','profession','age','adresse','phone','responsable','assure','assurance'];
                $vals = [
                    $_POST['nom_patient'],
                    $_POST['sexe'],
                    $_POST['profession'],
                    $_POST['age'],
                    $_POST['adresse'],
                    $_POST['phone'],
                    $responsable,
                    $assure,
                    $entrepriseAssurance,
                ];

                // Champs optionnels (uniquement si les colonnes existent en base)
                if (dbTableHasColumn($bdd, 'patients', 'carteAdhesion')) {
                    $cols[] = 'carteAdhesion';
                    $vals[] = $carteAdhesion;
                }
                if (dbTableHasColumn($bdd, 'patients', 'tauxPrisecharge')) {
                    $cols[] = 'tauxPrisecharge';
                    $vals[] = $tauxPrisecharge;
                }
                if (dbTableHasColumn($bdd, 'patients', 'dateExpiration')) {
                    $cols[] = 'dateExpiration';
                    $vals[] = $dateExpiration;
                }

                $placeholders = implode(',', array_fill(0, count($cols), '?'));
                $sql = 'INSERT INTO patients (' . implode(',', $cols) . ') VALUES (' . $placeholders . ')';
                $req = $bdd->prepare($sql);
                $req->execute($vals);

                $id_patient = (int)$bdd->lastInsertId();

                $errors = 2;
                
            }

            $bdd->commit();
        } catch (Exception $e) {
            $bdd->rollBack();
            $errors = 3;
            error_log("Erreur lors de l'insertion du patient: " . $e->getMessage());
            $error_messages[] = $e->getMessage();
        }
    }
}

// Mode modal: renvoyer uniquement le bloc formulaire + messages (sans layout, sans modals imbriqués)
if ($isModal) {
    $apSuccess = ($errors == 2 && !empty($id_patient));
    ?>
    <div class="col-md-12">
        <section class="card">
            <div class="card-body">
                <?php if ($errors == 2 && $id_patient): ?>
                    <div class="alert alert-success" id="apSuccessBox" data-new-patient-id="<?= (int)$id_patient ?>">
                        <strong>Succès</strong><br/>
                        <li>Enregistrement du patient effectué avec succès. Le dossier est ouvert sous le numéro <strong>PAT-<?= (int)$id_patient ?></strong>.</li>
                    </div>
                <?php elseif ($errors == 3): ?>
                    <div class="alert alert-danger">
                        <li>Enregistrement non effectué, merci de vérifier les informations saisies.</li>
                    </div>
                <?php elseif ($existe == 1): ?>
                    <div class="alert alert-warning">
                        <li>Ce patient est déjà enregistré dans le système et possède le numéro dossier N° <strong>PAT-<?= htmlspecialchars($patientid ?? '') ?></strong>.</li>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_messages)): ?>
                    <div class="alert alert-danger">
                        <strong>Erreurs :</strong><br/>
                        <?php foreach($error_messages as $message): ?>
                            <li><?php echo htmlspecialchars($message); ?></li>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form class="form-horizontal" id="apAddPatientForm" novalidate="novalidate" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?ap=default&amp;modal=1&amp;embed=1" enctype="multipart/form-data">
                    <input type="hidden" name="ajouter" value="1">
                    <div class="row form-group pb-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="col-form-label" for="formGroupExampleInput">Prénoms & Nom</label>
                                <input type="text" name="nom_patient" class="form-control" placeholder="" value="<?php echo isset($_POST['nom_patient']) ? htmlspecialchars($_POST['nom_patient']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="col-form-label" for="formGroupExampleInput">Genre</label>
                                <select class="form-control populate" name="sexe" required="">
                                    <option value="Masculin" <?php echo (isset($_POST['sexe']) && $_POST['sexe'] == 'Masculin') ? 'selected' : ''; ?>>Masculin</option>
                                    <option value="Feminin" <?php echo (isset($_POST['sexe']) && $_POST['sexe'] == 'Feminin') ? 'selected' : ''; ?>>Feminin</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="col-form-label" for="formGroupExampleInput">Date de naissance</label>
                                <input type="date" class="form-control" name="age" id="formGroupExampleInput" value="<?php echo isset($_POST['age']) ? htmlspecialchars($_POST['age']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="col-form-label" for="formGroupExampleInput">Profession</label>
                                <input type="text" class="form-control" name="profession" id="formGroupExampleInput" value="<?php echo isset($_POST['profession']) ? htmlspecialchars($_POST['profession']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="col-form-label" for="formGroupExampleInput">Contact</label>
                                <input type="number" class="form-control" maxlength="" name="phone" id="formGroupExampleInput" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="col-form-label" for="formGroupExampleInput">Ville de residence</label>
                                <select class="form-control populate" id="villeSelect" onchange="updateQuartier()" data-plugin-selectTwo data-plugin-options='{ "minimumInputLength": 0 }' required>
                                    <option value="">--- Choisir la ville ---</option>';
                                        <?php
                                        $coll = $bdd->prepare('SELECT id_ville, nom FROM adresses_villes');
                                        $coll -> execute();
                                        while ($ville = $coll->fetch(PDO::FETCH_ASSOC))
                                        {
                                            echo '<option value="'.$ville['id_ville'].'">'.$ville['nom'].'</option>';
                                        }
                                        ?>
                                    </option>
                                </select>
                                <div class="mt-1">
                                    <a href="#" class="small" id="apAddVilleLink">Ajouter une ville</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="col-form-label" for="formGroupExampleInput">Quartier</label>
                                <select name="adresse" class="form-control populate" id="quartierSelect" data-plugin-selectTwo data-plugin-options='{ "minimumInputLength": 0 }' required>
                                    <option value="">-- vous devez choisir une ville --</option>
                                </select>
                                <div class="mt-1">
                                    <a href="#" class="small" id="apAddQuartierLink">Ajouter un quartier</a>
                                </div>
                                <input type="hidden" id="hiddenquartierId" name="quartier_id" value="">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="col-form-label" for="formGroupExampleInput">Autre personne à contacter</label>
                                <input type="text" class="form-control" name="responsable" id="formGroupExampleInput" value="<?php echo isset($_POST['responsable']) ? htmlspecialchars($_POST['responsable']) : ''; ?>" placeholder="">
                            </div>
                        </div>
                    </div>
                    <div class="row form-group pb-3">
                        <div class="col-md-2">
                            <div class="form-group">
                                <input type="radio" name="estAssure" value="0" onclick="toggleAssuranceField()" <?php echo (!isset($_POST['estAssure']) || $_POST['estAssure'] == '0') ? 'checked' : ''; ?>> non assuré
                            </div>
                        </div>
                        <div class="col-md-10">
                            <div class="form-group">
                                <input type="radio" name="estAssure" value="1" onclick="toggleAssuranceField()" <?php echo (isset($_POST['estAssure']) && $_POST['estAssure'] == '1') ? 'checked' : ''; ?>> assuré
                            </div>
                        </div>
                    </div>
                    <div id="assuranceField" style="display: <?php echo (isset($_POST['estAssure']) && (string)$_POST['estAssure'] === '1') ? 'block' : 'none'; ?>;">
                        <div class="row form-group pb-3">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="col-form-label" for="formGroupExampleInput">Assureur</label>
                                    <select class="form-control populate" name="entrepriseAssurance" id="entrepriseAssurance" data-plugin-selectTwo data-plugin-options='{ "minimumInputLength": 0 }' >
                                        <option value="">-------- Choisir l'assureur --------</option>
                                        <?php
                                        $client = $bdd->prepare('SELECT * FROM assurances WHERE status= ?');
                                        $client -> execute([1]);
                                        while ($clients = $client->fetch(PDO::FETCH_ASSOC))
                                        {
                                            $selected = (isset($_POST['entrepriseAssurance']) && $_POST['entrepriseAssurance'] == $clients['id_assurance']) ? 'selected' : '';
                                            echo '<option value="'.$clients['id_assurance'].'" '.$selected.'>'.$clients['assurance'].'</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label class="col-form-label" for="formGroupExampleInput">N° Carte d'adhesion</label>
                                    <input type="text" class="form-control" name="carteAdhesion" id="formGroupExampleInput" value="<?php echo isset($_POST['carteAdhesion']) ? htmlspecialchars($_POST['carteAdhesion']) : ''; ?>" placeholder="">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="col-form-label" for="formGroupExampleInput">Taux de prise en charge %</label>
                                    <input type="number" class="form-control" name="tauxPrisecharge" step="0.01" min="0" max="100" id="formGroupExampleInput" value="<?php echo isset($_POST['tauxPrisecharge']) ? htmlspecialchars($_POST['tauxPrisecharge']) : ''; ?>" placeholder="">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="col-form-label" for="formGroupExampleInput">Date expiration carte</label>
                                    <input type="date" class="form-control" name="dateExpiration" id="formGroupExampleInput" value="<?php echo isset($_POST['dateExpiration']) ? htmlspecialchars($_POST['dateExpiration']) : ''; ?>" placeholder="">
                                </div>
                            </div>
                        </div>
                    </div>
                    <footer class="card-footer text-end">
                        <button class="btn btn-primary" type="submit" name="ajouter" id="apBtnAjouter" style="<?php echo $apSuccess ? 'display:none;' : ''; ?>">Ajouter</button>

                        <button class="btn btn-warning" type="button" id="apBtnTransmit" data-patient-id="<?= (int)($id_patient ?? 0) ?>" style="<?php echo $apSuccess ? '' : 'display:none;'; ?>">
                            Transmettre
                        </button>
                    </footer>
                </form>
            </div>
        </section>
    </div>

    <!-- Modal: Ajout ville (dans l'app, pas prompt OS) -->
    <div class="modal fade" id="apAjoutVilleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter une ville</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="apVilleModalAlert" class="alert d-none" role="alert"></div>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="col-form-label">Nom de la ville</label>
                            <input type="text" class="form-control" id="apVilleNomModal" placeholder="Ex: Pita" autocomplete="off">
                        </div>
                        <div class="col-md-12">
                            <label class="col-form-label">Région</label>
                            <select class="form-control" id="apVilleRegionModal" required>
                                <option value="">--- Choisir la région ---</option>
                                <option value="Basse Guinée">Basse Guinée</option>
                                <option value="Moyenne Guinée">Moyenne Guinée</option>
                                <option value="Haute Guinée">Haute Guinée</option>
                                <option value="Guinée Forestière">Guinée Forestière</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="button" class="btn btn-primary" id="apBtnSaveVille">Ajouter</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Ajout quartier (dans l'app, pas prompt OS) -->
    <div class="modal fade" id="apAjoutQuartierModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un quartier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="apQuartierModalAlert" class="alert d-none" role="alert"></div>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="col-form-label">Ville</label>
                            <select class="form-control" id="apVilleQuartierModal" required>
                                <option value="">--- Choisir la ville ---</option>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="col-form-label">Nom du quartier</label>
                            <input type="text" class="form-control" id="apQuartierNomModal" placeholder="Ex: Wansan" autocomplete="off" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="button" class="btn btn-primary" id="apBtnSaveQuartier">Ajouter</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleAssuranceField() {
            var assuranceField = document.getElementById("assuranceField");
            var r = document.querySelector('input[name="estAssure"]:checked');
            var estAssure = r ? r.value : '0';
            if (assuranceField) assuranceField.style.display = estAssure === "1" ? "block" : "none";
        }

        function apResetFormUi() {
            var form = document.getElementById('apAddPatientForm');
            if (!form) return;

            // Attention: après un POST succès, les attributs value="..." contiennent encore les valeurs saisies.
            // Un simple form.reset() remet donc les mêmes valeurs. Ici on vide explicitement.
            try {
                // Inputs texte/num/date
                Array.prototype.forEach.call(form.querySelectorAll('input'), function (input) {
                    if (!input || !input.name) return;
                    var type = String(input.type || '').toLowerCase();
                    if (type === 'hidden' || type === 'submit' || type === 'button') return;
                    if (type === 'radio' || type === 'checkbox') return;
                    input.value = '';
                });
            } catch (e) {}

            // Selects: remettre sur option vide si possible, sinon 1ère option
            try {
                Array.prototype.forEach.call(form.querySelectorAll('select'), function (sel) {
                    if (!sel) return;
                    var emptyOpt = sel.querySelector('option[value=""]');
                    if (emptyOpt) {
                        sel.value = '';
                    } else if (sel.options && sel.options.length) {
                        sel.selectedIndex = 0;
                    }
                });
            } catch (e) {}

            // Radios (assurance): repasser sur "non assuré" par défaut
            try {
                var r0 = form.querySelector('input[name="estAssure"][value="0"]');
                if (r0) r0.checked = true;
            } catch (e) {}

            // Réinitialiser selects + select2
            try {
                if (typeof window.jQuery !== 'undefined') {
                    var $ = window.jQuery;
                    $(form).find('[data-plugin-selectTwo]').each(function () {
                        try {
                            // Select2 accepte souvent null; on force aussi le change
                            $(this).val(null).trigger('change');
                        } catch (e) {}
                    });
                }
            } catch (e) {}

            // Réinitialiser quartier (dépend de ville)
            try {
                var ville = document.getElementById('villeSelect');
                if (ville) ville.value = '';
                if (typeof window.updateQuartier === 'function') window.updateQuartier();
            } catch (e) {}

            // Masquer le bloc assurance
            try { toggleAssuranceField(); } catch (e) {}

            // Masquer message succès et remettre le bouton Ajouter
            var successBox = document.getElementById('apSuccessBox');
            if (successBox) successBox.style.display = 'none';
            var btnAdd = document.getElementById('apBtnAjouter');
            if (btnAdd) btnAdd.style.display = '';
            var btnTransmit = document.getElementById('apBtnTransmit');
            if (btnTransmit) btnTransmit.style.display = 'none';
        }

        function apOpenPrint(url, title) {
            try {
                if (typeof window.gpOpenPrintModal === 'function') {
                    window.gpOpenPrintModal(url, title || 'Impression');
                    return true;
                }
            } catch (e) {}

            try {
                var w = window.open(url, '_blank');
                return !!w;
            } catch (e2) {
                return false;
            }
        }

        (function () {
            var btnTransmit = document.getElementById('apBtnTransmit');
            var successBox = document.getElementById('apSuccessBox');

            function getPid(btn) {
                var pid = btn ? String(btn.getAttribute('data-patient-id') || '').trim() : '';
                var n = parseInt(pid, 10);
                return isFinite(n) && n > 0 ? n : 0;
            }

            // Après succès: ouvrir automatiquement le modal d'impression du dossier.
            // IMPORTANT: on n'appelle pas print() ici; l'utilisateur clique sur le bouton "Imprimer" du modal.
            try {
                var pidSuccess = 0;
                if (successBox) {
                    pidSuccess = parseInt(String(successBox.getAttribute('data-new-patient-id') || ''), 10);
                }
                if (isFinite(pidSuccess) && pidSuccess > 0) {
                    window.__apAutoPrintDone = window.__apAutoPrintDone || {};
                    if (!window.__apAutoPrintDone[String(pidSuccess)]) {
                        window.__apAutoPrintDone[String(pidSuccess)] = true;
                        setTimeout(function () {
                            apOpenPrint('imprimer_dossier.php?id_patient=' + encodeURIComponent(String(pidSuccess)) + '&autoprint=0', 'Impression dossier');
                        }, 50);
                    }
                }
            } catch (e) {}

            if (btnTransmit) {
                btnTransmit.addEventListener('click', function () {
                    var pid = getPid(btnTransmit);
                    if (!pid) return;

                    // Vider le formulaire uniquement au moment de transmettre (anti-doublon)
                    try { apResetFormUi(); } catch (e0) {}

                    // Basculer le contenu du modal vers l'affectation et charger automatiquement le patient
                    try {
                        if (typeof window.openGestionPatientsModal === 'function') {
                            window.openGestionPatientsModal('affectation');
                        }
                    } catch (e) {}

                    setTimeout(function () {
                        try {
                            var input = document.getElementById('gpAffectPatientId');
                            if (input) input.value = String(pid);
                            if (typeof window.gpLoadAffectation === 'function') {
                                window.gpLoadAffectation();
                            } else {
                                var btn = document.getElementById('gpBtnLoadAffect');
                                if (btn) btn.click();
                            }
                        } catch (e2) {
                            // noop
                        }
                    }, 50);
                });
            }
        })();

        var apSelf = <?php echo json_encode($_SERVER['PHP_SELF']); ?>;

        window.updateQuartier = function () {
            const villeId = document.getElementById('villeSelect') ? document.getElementById('villeSelect').value : '';
            const quartierSelect = document.getElementById('quartierSelect');
            const hiddenId = document.getElementById('hiddenquartierId');
            if (!quartierSelect) return;

            if (!villeId) {
                quartierSelect.innerHTML = '<option value="">-- vous devez choisir une ville --</option>';
                if (hiddenId) hiddenId.value = '';
                try {
                    if (window.$ && $(quartierSelect).data('select2')) $(quartierSelect).val('').trigger('change');
                } catch (e0) {}
                return;
            }

            quartierSelect.innerHTML = '<option value="">Chargement...</option>';
            fetch(`../public/getQuartiers.php?ville=${encodeURIComponent(villeId)}`)
                .then(r => r.json())
                .then(data => {
                    if (!data || !data.success || !Array.isArray(data.quartier)) {
                        quartierSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                        return;
                    }
                    quartierSelect.innerHTML = '<option value="">-- Choisir le quartier --</option>';
                    for (const q of data.quartier) {
                        const opt = document.createElement('option');
                        opt.value = q.id;
                        opt.textContent = q.nom;
                        quartierSelect.appendChild(opt);
                    }

                    if (window.__pendingQuartierSelectId) {
                        quartierSelect.value = String(window.__pendingQuartierSelectId);
                        if (hiddenId) hiddenId.value = String(window.__pendingQuartierSelectId);
                        try {
                            if (window.$ && $(quartierSelect).data('select2')) $(quartierSelect).val(String(window.__pendingQuartierSelectId)).trigger('change');
                        } catch (e1) {}
                        window.__pendingQuartierSelectId = null;
                    } else {
                        if (hiddenId) hiddenId.value = quartierSelect.value || '';
                        try {
                            if (window.$ && $(quartierSelect).data('select2')) $(quartierSelect).trigger('change');
                        } catch (e2) {}
                    }
                })
                .catch(() => {
                    quartierSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                });
        };

        (function initAddPatientModal() {
            try { toggleAssuranceField(); } catch (e) {}
            var quartierSelect = document.getElementById('quartierSelect');
            var hiddenId = document.getElementById('hiddenquartierId');
            if (quartierSelect && hiddenId) {
                quartierSelect.addEventListener('change', function () {
                    hiddenId.value = quartierSelect.value || '';
                });
            }

            function apShowInlineAlert(msg) {
                try {
                    if (!msg) return;
                    var form = document.getElementById('apAddPatientForm');
                    if (!form) return;

                    var existing = document.getElementById('apInlineAlert');
                    if (!existing) {
                        existing = document.createElement('div');
                        existing.id = 'apInlineAlert';
                        existing.className = 'alert alert-danger';
                        form.prepend(existing);
                    }
                    existing.className = 'alert alert-danger';
                    existing.textContent = String(msg);
                } catch (e) {}
            }

            function apModalAlert(alertEl, type, msg) {
                try {
                    if (!alertEl) return;
                    if (!msg) {
                        alertEl.className = 'alert d-none';
                        alertEl.textContent = '';
                        return;
                    }
                    var t = String(type || 'danger');
                    alertEl.className = 'alert alert-' + t;
                    alertEl.textContent = String(msg);
                    alertEl.classList.remove('d-none');
                } catch (e) {}
            }

            function apGetModal(id) {
                try {
                    if (typeof window.bootstrap === 'undefined') return null;
                    var el = document.getElementById(id);
                    if (!el) return null;
                    return window.bootstrap.Modal.getOrCreateInstance(el);
                } catch (e) {
                    return null;
                }
            }

            function apFillVilleOptionsInto(selectEl) {
                try {
                    if (!selectEl) return;
                    var main = document.getElementById('villeSelect');
                    if (!main) return;
                    var current = String(selectEl.value || '');
                    selectEl.innerHTML = '';
                    selectEl.appendChild(new Option('--- Choisir la ville ---', ''));
                    for (var i = 0; i < main.options.length; i++) {
                        var opt = main.options[i];
                        if (!opt) continue;
                        var val = String(opt.value || '');
                        if (val === '') continue;
                        selectEl.appendChild(new Option(String(opt.text || ''), val));
                    }
                    if (current) selectEl.value = current;
                } catch (e) {}
            }

            function apEnsureVilleOption(id, label) {
                var villeSelect = document.getElementById('villeSelect');
                if (!villeSelect) return;
                var exists = false;
                for (var i = 0; i < villeSelect.options.length; i++) {
                    if (String(villeSelect.options[i].value) === String(id)) {
                        exists = true;
                        break;
                    }
                }
                if (!exists) {
                    var opt = document.createElement('option');
                    opt.value = String(id);
                    opt.textContent = String(label || id);
                    villeSelect.appendChild(opt);
                }
            }

            async function apSubmitAddVille() {
                var btn = document.getElementById('apBtnSaveVille');
                var input = document.getElementById('apVilleNomModal');
                var regionSelect = document.getElementById('apVilleRegionModal');
                var alertEl = document.getElementById('apVilleModalAlert');
                var villeSelect = document.getElementById('villeSelect');
                if (!input || !regionSelect || !villeSelect) return;
                var nom = String(input.value || '').trim();
                var region = String(regionSelect.value || '').trim();
                if (!nom) {
                    apModalAlert(alertEl, 'danger', 'Veuillez saisir le nom de la ville.');
                    return;
                }
                if (!region) {
                    apModalAlert(alertEl, 'danger', 'Veuillez choisir la région.');
                    return;
                }

                try {
                    apModalAlert(alertEl, null, '');
                    if (btn) btn.disabled = true;
                    var fd = new FormData();
                    fd.append('ajax_add_ville', '1');
                    fd.append('ville', nom);
                    fd.append('region', region);
                    var resp = await fetch(apSelf, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
                    var data = await resp.json();
                    if (!data || !data.success) {
                        apModalAlert(alertEl, 'danger', (data && data.message) ? data.message : "Erreur lors de l'ajout de la ville.");
                        return;
                    }

                    var id = String(data.id || '');
                    var label = String(data.nom || nom);
                    if (!id) return;
                    apEnsureVilleOption(id, label);

                    villeSelect.value = id;
                    try {
                        if (window.$ && $(villeSelect).data('select2')) $(villeSelect).val(id).trigger('change');
                    } catch (e1) {}
                    try { window.updateQuartier(); } catch (e2) {}

                    var m = apGetModal('apAjoutVilleModal');
                    if (m) m.hide();
                } catch (e3) {
                    apModalAlert(alertEl, 'danger', "Erreur lors de l'ajout de la ville.");
                } finally {
                    if (btn) btn.disabled = false;
                }
            }

            async function apSubmitAddQuartier() {
                var btn = document.getElementById('apBtnSaveQuartier');
                var villeModal = document.getElementById('apVilleQuartierModal');
                var input = document.getElementById('apQuartierNomModal');
                var alertEl = document.getElementById('apQuartierModalAlert');
                var villeSelect = document.getElementById('villeSelect');
                if (!villeModal || !input || !villeSelect) return;

                var villeId = String(villeModal.value || '').trim();
                var nom = String(input.value || '').trim();
                if (!villeId) {
                    apModalAlert(alertEl, 'danger', 'Veuillez choisir une ville.');
                    return;
                }
                if (!nom) {
                    apModalAlert(alertEl, 'danger', 'Veuillez saisir le nom du quartier.');
                    return;
                }

                try {
                    apModalAlert(alertEl, null, '');
                    if (btn) btn.disabled = true;
                    var fd = new FormData();
                    fd.append('ajax_add_quartier', '1');
                    fd.append('ville_id', villeId);
                    fd.append('quartier', nom);
                    var resp = await fetch(apSelf, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
                    var data = await resp.json();
                    if (!data || !data.success) {
                        apModalAlert(alertEl, 'danger', (data && data.message) ? data.message : "Erreur lors de l'ajout du quartier.");
                        return;
                    }

                    villeSelect.value = villeId;
                    try {
                        if (window.$ && $(villeSelect).data('select2')) $(villeSelect).val(villeId).trigger('change');
                    } catch (e1) {}

                    window.__pendingQuartierSelectId = data.id;
                    try { window.updateQuartier(); } catch (e2) {}

                    var m = apGetModal('apAjoutQuartierModal');
                    if (m) m.hide();
                } catch (e3) {
                    apModalAlert(alertEl, 'danger', "Erreur lors de l'ajout du quartier.");
                } finally {
                    if (btn) btn.disabled = false;
                }
            }

            var addVilleLink = document.getElementById('apAddVilleLink');
            if (addVilleLink) {
                addVilleLink.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (addVilleLink.getAttribute('aria-disabled') === 'true') return;
                    var alertEl = document.getElementById('apVilleModalAlert');
                    apModalAlert(alertEl, null, '');
                    var input = document.getElementById('apVilleNomModal');
                    if (input) input.value = '';
                    var regionSelect = document.getElementById('apVilleRegionModal');
                    if (regionSelect) regionSelect.value = '';
                    var m = apGetModal('apAjoutVilleModal');
                    if (m) {
                        m.show();
                        setTimeout(function () { try { if (input) input.focus(); } catch (e2) {} }, 50);
                    }
                });
            }

            var addQuartierLink = document.getElementById('apAddQuartierLink');
            if (addQuartierLink) {
                addQuartierLink.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (addQuartierLink.getAttribute('aria-disabled') === 'true') return;
                    var villeSelect = document.getElementById('villeSelect');
                    var villeId = villeSelect ? String(villeSelect.value || '').trim() : '';
                    if (!villeId) {
                        apShowInlineAlert('Veuillez d\'abord choisir une ville.');
                        return;
                    }
                    var alertEl = document.getElementById('apQuartierModalAlert');
                    apModalAlert(alertEl, null, '');
                    var villeModal = document.getElementById('apVilleQuartierModal');
                    apFillVilleOptionsInto(villeModal);
                    if (villeModal) villeModal.value = villeId;
                    var input = document.getElementById('apQuartierNomModal');
                    if (input) input.value = '';
                    var m = apGetModal('apAjoutQuartierModal');
                    if (m) {
                        m.show();
                        setTimeout(function () { try { if (input) input.focus(); } catch (e2) {} }, 50);
                    }
                });
            }

            var btnSaveVille = document.getElementById('apBtnSaveVille');
            if (btnSaveVille) {
                btnSaveVille.addEventListener('click', function () {
                    apSubmitAddVille();
                });
            }

            var btnSaveQuartier = document.getElementById('apBtnSaveQuartier');
            if (btnSaveQuartier) {
                btnSaveQuartier.addEventListener('click', function () {
                    apSubmitAddQuartier();
                });
            }

            // Entrée: valider au Enter dans les champs
            try {
                var vInput = document.getElementById('apVilleNomModal');
                if (vInput) {
                    vInput.addEventListener('keydown', function (ev) {
                        if (ev && ev.key === 'Enter') {
                            ev.preventDefault();
                            apSubmitAddVille();
                        }
                    });
                }
            } catch (e) {}
            try {
                var qInput = document.getElementById('apQuartierNomModal');
                if (qInput) {
                    qInput.addEventListener('keydown', function (ev) {
                        if (ev && ev.key === 'Enter') {
                            ev.preventDefault();
                            apSubmitAddQuartier();
                        }
                    });
                }
            } catch (e2) {}
        })();
    </script>
    <?php
    exit;
}

require('../PUBLIC/header.php');
?>
<body>
    <section class="body">
        <?php require('../PUBLIC/navbarmenu.php'); ?>
        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Ajouter un nouveau patient</h2>
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <section class="card">
                        <div class="card-body">
                            <?php if ($errors == 2 && $id_patient): ?>
                                <div class="alert alert-success">
                                    <strong>Succès</strong><br/>  
                                    <li>Enregistrement du patient effectué avec succès. Le dossier est ouvert sous le numéro <strong>PAT-<?= $id_patient ?></strong>.</li>
                                </div>
                            <?php elseif ($errors == 3): ?>
                                <div class="alert alert-danger">
                                    <li>Enregistrement non effectué, merci de vérifier les informations saisies.</li>
                                </div>
                            <?php elseif ($existe == 1): ?>
                                <div class="alert alert-warning">
                                    <li>Ce patient est déjà enregistré dans le système et possède le numéro dossier N° <strong>PAT-<?= $patientid ?></strong>.</li>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($error_messages)): ?>
                                <div class="alert alert-danger">
                                    <strong>Erreurs :</strong><br/>
                                    <?php foreach($error_messages as $message): ?>
                                        <li><?php echo htmlspecialchars($message); ?></li>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <form class="form-horizontal" novalidate="novalidate" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?ap=default" enctype="multipart/form-data">
                                <input type="hidden" name="ajouter" value="1">
                                <div class="row form-group pb-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Prénoms & Nom</label>
                                            <input type="text" name="nom_patient" class="form-control" placeholder="" value="<?php echo isset($_POST['nom_patient']) ? htmlspecialchars($_POST['nom_patient']) : ''; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Genre</label>
                                            <select class="form-control populate" name="sexe" required="">
                                                <option value="Masculin" <?php echo (isset($_POST['sexe']) && $_POST['sexe'] == 'Masculin') ? 'selected' : ''; ?>>Masculin</option>
                                                <option value="Feminin" <?php echo (isset($_POST['sexe']) && $_POST['sexe'] == 'Feminin') ? 'selected' : ''; ?>>Feminin</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Date de naissance</label>
                                            <input type="date" class="form-control" name="age" id="formGroupExampleInput" value="<?php echo isset($_POST['age']) ? htmlspecialchars($_POST['age']) : ''; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Profession</label>
                                            <input type="text" class="form-control" name="profession" id="formGroupExampleInput" value="<?php echo isset($_POST['profession']) ? htmlspecialchars($_POST['profession']) : ''; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Contact</label>
                                            <input type="number" class="form-control" maxlength="" name="phone" id="formGroupExampleInput" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Ville de residence</label>
                                            <select class="form-control populate" id="villeSelect" onchange="updateQuartier()" data-plugin-selectTwo data-plugin-options='{ "minimumInputLength": 0 }' required>
                                                <option value="">--- Choisir la ville ---</option>';
                                                    <?php
                                                    $coll = $bdd->prepare('SELECT id_ville, nom FROM adresses_villes');
                                                    $coll -> execute();
                                                    while ($ville = $coll->fetch(PDO::FETCH_ASSOC))
                                                    {
                                                        echo '<option value="'.$ville['id_ville'].'">'.$ville['nom'].'</option>';
                                                    } 
                                                    ?>
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Quartier</label>
                                            <select name="adresse" class="form-control populate" id="quartierSelect" data-plugin-selectTwo data-plugin-options='{ "minimumInputLength": 0 }' required>
                                                <option value="">-- vous devez choisir une ville --</option>
                                            </select>
                                            <input type="hidden" id="hiddenquartierId" name="quartier_id" value="">
                                        </div>
                                        <a href="#" onclick="return false;" data-bs-toggle="modal" data-bs-target="#ajoutQuartierModal">Quartier manquant ? ajouter</a>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Autre personne à contacter</label>
                                            <input type="text" class="form-control" name="responsable" id="formGroupExampleInput" value="<?php echo isset($_POST['responsable']) ? htmlspecialchars($_POST['responsable']) : ''; ?>" placeholder="">
                                        </div>
                                    </div>
                                </div>
                                <div class="row form-group pb-3">
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <input type="radio" name="estAssure" value="0" onclick="toggleAssuranceField()" <?php echo (!isset($_POST['estAssure']) || $_POST['estAssure'] == '0') ? 'checked' : ''; ?>> non assuré
                                        </div>
                                    </div>
                                    <div class="col-md-10">
                                        <div class="form-group">
                                            <input type="radio" name="estAssure" value="1" onclick="toggleAssuranceField()" <?php echo (isset($_POST['estAssure']) && $_POST['estAssure'] == '1') ? 'checked' : ''; ?>> assuré
                                        </div>
                                    </div>
                                </div>
                                <div id="assuranceField" style="display: <?php echo (isset($_POST['estAssure']) && (string)$_POST['estAssure'] === '1') ? 'block' : 'none'; ?>;">
                                    <div class="row form-group pb-3">
                                        <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Assureur</label>
                                            <select class="form-control populate" name="entrepriseAssurance" id="entrepriseAssurance" data-plugin-selectTwo data-plugin-options='{ "minimumInputLength": 0 }' >
                                                <option value="">-------- Choisir l'assureur --------</option>
                                                <?php 
                                                    $client = $bdd->prepare('SELECT * FROM assurances WHERE status= ?');
                                                    $client -> execute([1]);
                                                    while ($clients = $client->fetch(PDO::FETCH_ASSOC))
                                                    {
                                                        $selected = (isset($_POST['entrepriseAssurance']) && $_POST['entrepriseAssurance'] == $clients['id_assurance']) ? 'selected' : '';
                                                        echo '<option value="'.$clients['id_assurance'].'" '.$selected.'>'.$clients['assurance'].'</option>';
                                                    } 
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">N° Carte d'adhesion</label>
                                            <input type="text" class="form-control" name="carteAdhesion" id="formGroupExampleInput" value="<?php echo isset($_POST['carteAdhesion']) ? htmlspecialchars($_POST['carteAdhesion']) : ''; ?>" placeholder="">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Taux de prise en charge %</label>
                                            <input type="number" class="form-control" name="tauxPrisecharge" step="0.01" min="0" max="100" id="formGroupExampleInput" value="<?php echo isset($_POST['tauxPrisecharge']) ? htmlspecialchars($_POST['tauxPrisecharge']) : ''; ?>" placeholder="">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Date expiration carte</label>
                                            <input type="date" class="form-control" name="dateExpiration" id="formGroupExampleInput" value="<?php echo isset($_POST['dateExpiration']) ? htmlspecialchars($_POST['dateExpiration']) : ''; ?>" placeholder="">
                                        </div>
                                    </div>
                                </div>
                                </div>
                                <footer class="card-footer text-end">
                                    <button class="btn btn-primary" type="submit" name="ajouter">Ajouter</button>
                                </footer>
                            </form>
                        </div>
                    </section>
                </div>
            </section>
        </div>
        <script>
            function toggleAssuranceField() {
                var assuranceField = document.getElementById("assuranceField");
                var estAssure = document.querySelector('input[name="estAssure"]:checked').value;
                assuranceField.style.display = estAssure === "1" ? "block" : "none";
            }

            document.addEventListener('DOMContentLoaded', function () {
                try { toggleAssuranceField(); } catch (e) {}
            });
        </script>

        <!-- Modal: Ajout quartier (formulaire intégré) -->
        <div class="modal fade" id="ajoutQuartierModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Ajouter un quartier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="quartierModalAlert" class="alert d-none" role="alert"></div>

                        <form id="ajoutQuartierForm">
                            <input type="hidden" name="ajax_add_quartier" value="1">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="col-form-label">Ville</label>
                                    <select class="form-control" name="ville_id" id="villeQuartierModal" required>
                                        <option value="">--- Choisir la ville ---</option>
                                        <?php
                                        $collModal = $bdd->prepare('SELECT id_ville, nom FROM adresses_villes');
                                        $collModal->execute();
                                        while ($ville = $collModal->fetch(PDO::FETCH_ASSOC)) {
                                            echo '<option value="'.(int)$ville['id_ville'].'">'.htmlspecialchars($ville['nom']).'</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="col-form-label">Nom du quartier</label>
                                    <input type="text" class="form-control" name="quartier" id="quartierNomModal" required>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                        <button type="button" class="btn btn-primary" id="btnSaveQuartier">Ajouter</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        // Définir updateQuartier si absent (certaines pages l'ont via scripts globaux)
        window.updateQuartier = window.updateQuartier || function () {
            const villeId = document.getElementById('villeSelect') ? document.getElementById('villeSelect').value : '';
            const quartierSelect = document.getElementById('quartierSelect');
            const hiddenId = document.getElementById('hiddenquartierId');
            if (!quartierSelect) return;

            if (!villeId) {
                quartierSelect.innerHTML = '<option value="">-- vous devez choisir une ville --</option>';
                if (hiddenId) hiddenId.value = '';
                if (window.$ && $(quartierSelect).data('select2')) $(quartierSelect).val('').trigger('change');
                return;
            }

            quartierSelect.innerHTML = '<option value="">Chargement...</option>';
            fetch(`../public/getQuartiers.php?ville=${encodeURIComponent(villeId)}`)
                .then(r => r.json())
                .then(data => {
                    if (!data || !data.success || !Array.isArray(data.quartier)) {
                        quartierSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                        return;
                    }
                    quartierSelect.innerHTML = '<option value="">-- Choisir le quartier --</option>';
                    for (const q of data.quartier) {
                        const opt = document.createElement('option');
                        opt.value = q.id;
                        opt.textContent = q.nom;
                        quartierSelect.appendChild(opt);
                    }

                    if (window.__pendingQuartierSelectId) {
                        quartierSelect.value = String(window.__pendingQuartierSelectId);
                        if (hiddenId) hiddenId.value = String(window.__pendingQuartierSelectId);
                        if (window.$ && $(quartierSelect).data('select2')) $(quartierSelect).val(String(window.__pendingQuartierSelectId)).trigger('change');
                        window.__pendingQuartierSelectId = null;
                    } else {
                        if (hiddenId) hiddenId.value = quartierSelect.value || '';
                        if (window.$ && $(quartierSelect).data('select2')) $(quartierSelect).trigger('change');
                    }
                })
                .catch(() => {
                    quartierSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                });
        };

        document.addEventListener('DOMContentLoaded', function () {
            const quartierSelect = document.getElementById('quartierSelect');
            const hiddenId = document.getElementById('hiddenquartierId');
            if (quartierSelect && hiddenId) {
                quartierSelect.addEventListener('change', function () {
                    hiddenId.value = quartierSelect.value || '';
                });
            }

            // Pré-remplir la ville du modal à l'ouverture
            const modalEl = document.getElementById('ajoutQuartierModal');
            if (modalEl) {
                modalEl.addEventListener('show.bs.modal', function () {
                    const villeSelect = document.getElementById('villeSelect');
                    const villeModal = document.getElementById('villeQuartierModal');
                    const qNom = document.getElementById('quartierNomModal');
                    const alertEl = document.getElementById('quartierModalAlert');
                    if (alertEl) {
                        alertEl.className = 'alert d-none';
                        alertEl.textContent = '';
                    }
                    if (villeModal && villeSelect && villeSelect.value) {
                        villeModal.value = villeSelect.value;
                    }
                    if (qNom) qNom.value = '';
                });
            }

            // Submit AJAX ajout quartier
            const btn = document.getElementById('btnSaveQuartier');
            const form = document.getElementById('ajoutQuartierForm');
            const alertEl = document.getElementById('quartierModalAlert');
            if (btn && form) {
                btn.addEventListener('click', async function () {
                    btn.disabled = true;
                    try {
                        const fd = new FormData(form);
                        const resp = await fetch('<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>', {
                            method: 'POST',
                            body: fd
                        });
                        const data = await resp.json();
                        if (!data || !data.success) {
                            if (alertEl) {
                                alertEl.className = 'alert alert-danger';
                                alertEl.textContent = (data && data.message) ? data.message : "Erreur lors de l'ajout.";
                                alertEl.classList.remove('d-none');
                            }
                            return;
                        }

                        const villeMain = document.getElementById('villeSelect');
                        const villeModal = document.getElementById('villeQuartierModal');
                        if (villeMain && villeModal && villeModal.value && villeMain.value !== villeModal.value) {
                            villeMain.value = villeModal.value;
                            if (window.$ && $(villeMain).data('select2')) $(villeMain).val(villeModal.value).trigger('change');
                        }

                        window.__pendingQuartierSelectId = data.id;
                        window.updateQuartier();

                        const modalEl = document.getElementById('ajoutQuartierModal');
                        if (modalEl && window.bootstrap) {
                            const instance = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
                            instance.hide();
                        }
                    } catch (e) {
                        if (alertEl) {
                            alertEl.className = 'alert alert-danger';
                            alertEl.textContent = "Erreur lors de l'ajout du quartier.";
                            alertEl.classList.remove('d-none');
                        }
                    } finally {
                        btn.disabled = false;
                    }
                });
            }
        });
        </script>

        <!-- Modal Impression dossier (aperçu + impression) -->
        <div class="modal fade" id="dossierPrintModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="dossierPrintModalTitle">Impression carte d'adhesion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0" style="height:80vh;">
                        <iframe id="dossierPrintFrame" src="about:blank" style="width:100%; height:100%;" frameborder="0"></iframe>
                    </div>
                    <div class="modal-footer">
                        <button type="button" id="dossierPrintBtn" class="btn btn-primary">Imprimer</button>
                        <button type="button" id="dossierTransmitBtn" class="btn btn-success">Transmettre</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function () {
            var modalEl = document.getElementById('dossierPrintModal');
            var frameEl = document.getElementById('dossierPrintFrame');
            var titleEl = document.getElementById('dossierPrintModalTitle');
            var printBtnEl = document.getElementById('dossierPrintBtn');
            var transmitBtnEl = document.getElementById('dossierTransmitBtn');

            var currentPatientId = null;

            function withAutoPrintDisabled(url) {
                if (!url) return url;
                return url + (url.indexOf('?') >= 0 ? '&' : '?') + 'autoprint=0';
            }

            function showModal() {
                if (window.bootstrap && window.bootstrap.Modal) {
                    var instance = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
                    instance.show();
                    return true;
                }
                if (window.jQuery && modalEl && typeof jQuery(modalEl).modal === 'function') {
                    jQuery(modalEl).modal('show');
                    return true;
                }
                return false;
            }

            function openDossierPrintModal(url, idPatient, title) {
                currentPatientId = idPatient ? String(idPatient) : null;

                if (titleEl) {
                    titleEl.textContent = title ? String(title) : "Impression";
                }

                if (!modalEl || !frameEl) {
                    // Fallback sans modal
                    if (url && typeof window.openPrintModal === 'function') window.openPrintModal(url, title ? String(title) : 'Impression');
                    return;
                }

                frameEl.src = withAutoPrintDisabled(url);
                if (!showModal()) {
                    if (typeof window.openPrintModal === 'function') window.openPrintModal(url, title ? String(title) : 'Impression');
                }
            }

            // Bouton Imprimer
            if (printBtnEl) {
                printBtnEl.addEventListener('click', function () {
                    try {
                        var win = frameEl && frameEl.contentWindow ? frameEl.contentWindow : null;
                        if (win && typeof win.printPdf === 'function') {
                            win.printPdf();
                            return;
                        }
                        if (win && typeof win.print === 'function') {
                            if (typeof win.focus === 'function') win.focus();
                            win.print();
                        }
                    } catch (e) {
                        // noop
                    }
                });
            }

            // Bouton Transmettre : redirige vers transmission-caisse en gardant l'id_patient (la page ouvrira son modal)
            if (transmitBtnEl) {
                transmitBtnEl.addEventListener('click', function () {
                    if (!currentPatientId) return;
                    window.location.href = 'transmission-caisse.php?id_patient=' + encodeURIComponent(currentPatientId);
                });
            }

            // Reset iframe à la fermeture
            if (modalEl) {
                modalEl.addEventListener('hidden.bs.modal', function () {
                    if (frameEl) frameEl.src = 'about:blank';
                });
                if (window.jQuery && typeof jQuery(modalEl).on === 'function') {
                    jQuery(modalEl).on('hidden.bs.modal', function () {
                        if (frameEl) frameEl.src = 'about:blank';
                    });
                }
            }

            // Expose pour le script PHP ci-dessous
            window.openDossierPrintModal = openDossierPrintModal;
        })();
        </script>

        <?php if ($errors == 2 && $id_patient): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (typeof window.openDossierPrintModal !== 'function') return;
                    window.openDossierPrintModal('imprimer_dossier.php?id_patient=<?= (int)$id_patient ?>', <?= (int)$id_patient ?>, "Impression carte d'adhesion");
                });
            </script>
        <?php endif; ?>
        <?php include('../PUBLIC/footer.php');?>