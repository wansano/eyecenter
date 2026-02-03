<?php
session_start();
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');

class PatientManager {
    private $bdd;
    private $errors = [];
    private $success = false;

    public function __construct($bdd) {
        $this->bdd = $bdd;
    }

    public function validateInput($data) {
        $errors = [];
        
        if (empty($data['nom_patient'])) {
            $errors[] = "Le nom du patient est requis";
        }
        
        if (!empty($data['phone']) && !preg_match('/^\d{9}$/', $data['phone'])) {
            $errors[] = "Le numéro de téléphone doit contenir 9 chiffres";
        }
        
        if (!in_array($data['sexe'], ['Homme', 'Femme'])) {
            $errors[] = "Le genre spécifié n'est pas valide";
        }
        
        if (!empty($data['age'])) {
            $date = DateTime::createFromFormat('Y-m-d', $data['age']);
            if (!$date || $date->format('Y-m-d') !== $data['age']) {
                $errors[] = "La date de naissance n'est pas valide";
            }
        }
        
        return $errors;
    }

    public function searchPatient($id) {
        $stmt = $this->bdd->prepare('SELECT * FROM patients WHERE id_patient = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updatePatient($data) {
        try {
            $validationErrors = $this->validateInput($data);
            if (!empty($validationErrors)) {
                $this->errors = $validationErrors;
                return false;
            }

            $stmt = $this->bdd->prepare('
                UPDATE patients 
                SET nom_patient = :nom,
                    adresse = :id_quartier,
                    phone = :phone,
                    responsable = :responsable,
                    profession = :profession,
                    age = :age,
                    sexe = :sexe 
                WHERE id_patient = :id
            ');

            $success = $stmt->execute([
                ':nom' => trim(strip_tags($data['nom_patient'])),
                ':id_quartier' => trim(strip_tags($data['adresse'])),
                ':phone' => trim(strip_tags($data['phone'])),
                ':responsable' => trim(strip_tags($data['responsable'])),
                ':profession' => trim(strip_tags($data['profession'])),
                ':age' => $data['age'],
                ':sexe' => $data['sexe'],
                ':id' => $data['modif_in']
            ]);

            if ($success) {
                $this->success = true;
                return true;
            }

            $this->errors[] = "Erreur lors de la mise à jour";
            return false;

        } catch (PDOException $e) {
            $this->errors[] = "Erreur de base de données: " . $e->getMessage();
            return false;
        }
    }

    public function getErrors() {
        return $this->errors;
    }

    public function hasSuccess() {
        return $this->success;
    }
}

// Endpoint AJAX: récupérer les informations d'un patient (modal recherche)
if (isset($_GET['ajax_patient'])) {
    header('Content-Type: application/json; charset=UTF-8');

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Numéro de dossier invalide.']);
        exit;
    }

    try {
        $pm = new PatientManager($bdd);
        $p = $pm->searchPatient($id);
        if (!$p) {
            echo json_encode(['success' => false, 'message' => 'Patient introuvable.']);
            exit;
        }

        $adresseRaw = $p['adresse'] ?? '';
        $adresseLabel = '';
        if ($adresseRaw !== '' && ctype_digit((string)$adresseRaw)) {
            $adresseLabel = (string)quartier((int)$adresseRaw);
        }
        if ($adresseLabel === '') {
            $adresseLabel = (string)$adresseRaw;
        }

        echo json_encode([
            'success' => true,
            'patient' => [
                'id_patient' => (int)($p['id_patient'] ?? 0),
                'nom_patient' => (string)($p['nom_patient'] ?? ''),
                'sexe' => (string)($p['sexe'] ?? ''),
                'age' => (string)($p['age'] ?? ''),
                'profession' => (string)($p['profession'] ?? ''),
                'phone' => (string)($p['phone'] ?? ''),
                'responsable' => (string)($p['responsable'] ?? ''),
                'adresse' => $adresseLabel,
            ],
        ]);
        exit;
    } catch (Throwable $e) {
        error_log('[AJAX PATIENT] ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des informations.']);
        exit;
    }
}

// Initialisation
$patientManager = new PatientManager($bdd);
$searchResult = null;
$patientData = null;

// Traitement de la recherche
if (isset($_POST['recherche']) && !empty($_POST['recherche'])) {
    $searchResult = $patientManager->searchPatient($_POST['recherche']);
    if ($searchResult) {
        header('Location: editpatient.php?ep=default&id_patient=' . $_POST['recherche']);
        exit;
    }
}

// Traitement de la mise à jour
if (isset($_POST['modif_in'])) {
    if ($patientManager->updatePatient($_POST)) {
        $patientData = $patientManager->searchPatient($_POST['modif_in']);
    }
}

// Récupération des données du patient pour l'affichage
if (isset($_GET['id_patient'])) {
    $patientData = $patientManager->searchPatient($_GET['id_patient']);
}

include('../PUBLIC/header.php');
?>

<body>
    <section class="body">
        <?php require('../PUBLIC/navbarmenu.php'); ?>
        
        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Modification des informations</h2>
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <section class="card">
                        <div class="card-body">
                            <?php
                            // Affichage des messages
                            if ($patientManager->hasSuccess()) {
                                echo '<div class="alert alert-success"><strong>Succès</strong><br/>Information du patient mise à jour</div>';
                            }
                            
                            if (!empty($patientManager->getErrors())) {
                                echo '<div class="alert alert-danger"><ul>';
                                foreach ($patientManager->getErrors() as $error) {
                                    echo '<li>' . htmlspecialchars($error) . '</li>';
                                }
                                echo '</ul></div>';
                            }

                            // Formulaire de recherche si pas d'ID patient
                            if (!isset($_GET['id_patient'])) {
                                ?>
                                <form class="form-horizontal" method="POST" action="editpatient.php" id="patientSearchForm">
                                    <div class="row form-group pb-3">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="col-form-label">Saisir le numéro de dossier</label>
                                                <input type="text" class="form-control" name="recherche" id="patientSearchInput" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer text-end">
                                        <button class="btn btn-primary" type="submit" id="patientSearchBtn">Rechercher</button>
                                    </div>
                                </form>
                                <?php
                            }

                            // Formulaire d'édition si patient trouvé
                            if ($patientData) {

                                // Déduire la ville à partir du quartier (adresse) actuel
                                $quartierIdActuel = 0;
                                $villeIdActuelle = 0;
                                if (isset($patientData['adresse']) && $patientData['adresse'] !== '' && ctype_digit((string)$patientData['adresse'])) {
                                    $quartierIdActuel = (int)$patientData['adresse'];
                                    if ($quartierIdActuel > 0) {
                                        $st = $bdd->prepare('SELECT id_ville FROM adresses_quartiers WHERE id_quartier = ? LIMIT 1');
                                        $st->execute([$quartierIdActuel]);
                                        $villeIdActuelle = (int)($st->fetchColumn() ?: 0);
                                    }
                                }
                                ?>
                                <form class="form-horizontal" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?ep=default&id_patient=<?php echo htmlspecialchars($_GET['id_patient']); ?>">
                                    <input type="hidden" name="modif_in" value="<?php echo htmlspecialchars($_GET['id_patient']); ?>">
                                    
                                    <div class="row form-group pb-3">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="col-form-label">Prénoms & Nom</label>
                                                <input type="text" name="nom_patient" class="form-control" value="<?php echo htmlspecialchars($patientData['nom_patient']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label">Genre</label>
                                                <select class="form-control" name="sexe" required>
                                                    <option value="Homme" <?php echo $patientData['sexe'] === 'Homme' ? 'selected' : ''; ?>>Homme</option>
                                                    <option value="Femme" <?php echo $patientData['sexe'] === 'Femme' ? 'selected' : ''; ?>>Femme</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label">Date de naissance</label>
                                                <input type="date" class="form-control" name="age" value="<?php echo htmlspecialchars($patientData['age']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="col-form-label">Profession</label>
                                                <input type="text" class="form-control" name="profession" value="<?php echo htmlspecialchars($patientData['profession']); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row form-group pb-3">
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Ville de residence</label>
                                                <select class="form-control populate" id="villeSelect" onchange="updateQuartier()" data-plugin-selectTwo data-plugin-options='{ "minimumInputLength": 0 }' required>
                                                    <option value="">--- Choisir la ville ---</option>
                                                        <?php
                                                        $coll = $bdd->prepare('SELECT id_ville, nom FROM adresses_villes');
                                                        $coll -> execute();
                                                        while ($ville = $coll->fetch(PDO::FETCH_ASSOC))
                                                        {
                                                            $selectedVille = ($villeIdActuelle > 0 && (int)$ville['id_ville'] === $villeIdActuelle) ? ' selected' : '';
                                                            echo '<option value="'.(int)$ville['id_ville'].'"'.$selectedVille.'>'.htmlspecialchars($ville['nom']).'</option>';
                                                        } 
                                                        ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Quartier</label>
                                                <select name="adresse" class="form-control populate" id="quartierSelect" data-plugin-selectTwo data-plugin-options='{ "minimumInputLength": 0 }' required>
                                                    <?php
                                                    if ($villeIdActuelle > 0) {
                                                        echo '<option value="">--- Choisir le quartier ---</option>';
                                                        $stQ = $bdd->prepare('SELECT id_quartier, quartier FROM adresses_quartiers WHERE id_ville = ? ORDER BY quartier ASC');
                                                        $stQ->execute([$villeIdActuelle]);
                                                        while ($q = $stQ->fetch(PDO::FETCH_ASSOC)) {
                                                            $selectedQ = ($quartierIdActuel > 0 && (int)$q['id_quartier'] === $quartierIdActuel) ? ' selected' : '';
                                                            echo '<option value="'.(int)$q['id_quartier'].'"'.$selectedQ.'>'.htmlspecialchars($q['quartier']).'</option>';
                                                        }
                                                    } else {
                                                        // Fallback : afficher la valeur actuelle (si non numérique ou ville introuvable)
                                                        $quartierValue = '';
                                                        if ($patientData && isset($patientData['adresse'])) {
                                                            $quartierNom = quartier($patientData['adresse']);
                                                            if (is_array($quartierNom)) {
                                                                $quartierValue = (string)$patientData['adresse'];
                                                            } elseif ($quartierNom) {
                                                                $quartierValue = (string)$quartierNom;
                                                            } else {
                                                                $quartierValue = (string)$patientData['adresse'];
                                                            }
                                                        }
                                                        echo '<option value="">'.htmlspecialchars($quartierValue).'</option>';
                                                    }
                                                    ?>
                                                </select>
                                                <input type="hidden" id="hiddenquartierId" name="quartier_id" value="">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label">Contact</label>
                                                <input type="tel" class="form-control" name="phone" maxlength="9" value="<?php echo htmlspecialchars($patientData['phone']); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-5">
                                            <div class="form-group">
                                                <label class="col-form-label">Responsable</label>
                                                <input type="text" class="form-control" name="responsable" value="<?php echo htmlspecialchars($patientData['responsable']); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card-footer text-end">
                                        <button class="btn btn-primary" type="submit">Mettre à jour</button>
                                    </div>
                                </form>
                                <?php
                            }
                            ?>
                        </div>
                    </section>
                </div>
                <!-- end: page -->
            </section>
        </div>
        <?php include('../PUBLIC/footer.php'); ?>
    </section>

    <!-- Modal: Résultat recherche patient -->
    <div class="modal fade" id="patientInfoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Informations du patient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="patientInfoAlert" class="alert d-none" role="alert"></div>

                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <tbody>
                                <tr><th style="width: 35%">Dossier</th><td id="pi_id">-</td></tr>
                                <tr><th>Nom</th><td id="pi_nom">-</td></tr>
                                <tr><th>Genre</th><td id="pi_sexe">-</td></tr>
                                <tr><th>Date de naissance</th><td id="pi_age">-</td></tr>
                                <tr><th>Profession</th><td id="pi_prof">-</td></tr>
                                <tr><th>Téléphone</th><td id="pi_phone">-</td></tr>
                                <tr><th>Adresse</th><td id="pi_adresse">-</td></tr>
                                <tr><th>Responsable</th><td id="pi_resp">-</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <a href="#" class="btn btn-primary" id="btnImprimerDossier" rel="noopener"> <i class="fa fa-print"></i> dossier</a>
                    <a href="#" class="btn btn-info" id="btnImprimerCarte" rel="noopener"> <i class="fa fa-print"></i> carte d'adhésion</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Impression (aperçu + impression) -->
    <div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="printModalTitle">Impression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="height:80vh;">
                    <iframe id="printFrame" src="about:blank" style="width:100%; height:100%;" frameborder="0"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" id="printBtn" class="btn btn-primary">Imprimer</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('patientSearchForm');
        const input = document.getElementById('patientSearchInput');
        const btn = document.getElementById('patientSearchBtn');

        const modalEl = document.getElementById('patientInfoModal');
        const alertEl = document.getElementById('patientInfoAlert');
        const btnDossier = document.getElementById('btnImprimerDossier');
        const btnCarte = document.getElementById('btnImprimerCarte');

        const printModalEl = document.getElementById('printModal');
        const printFrameEl = document.getElementById('printFrame');
        const printBtnEl = document.getElementById('printBtn');
        const printTitleEl = document.getElementById('printModalTitle');

        function setAlert(type, msg) {
            if (!alertEl) return;
            if (!msg) {
                alertEl.className = 'alert d-none';
                alertEl.textContent = '';
                return;
            }
            alertEl.className = 'alert alert-' + type;
            alertEl.textContent = msg;
            alertEl.classList.remove('d-none');
        }

        function setText(id, value) {
            const el = document.getElementById(id);
            if (el) el.textContent = value ? String(value) : '-';
        }

        async function fetchPatient(id) {
            const url = 'editpatient.php?ajax_patient=1&id=' + encodeURIComponent(id);
            const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
            return await resp.json();
        }

        async function onSearch(e) {
            // Si pas de JS (ou erreur), le POST classique reste possible.
            if (e) e.preventDefault();
            if (!input) return;

            const id = input.value.trim();
            if (!id) return;

            setAlert(null, '');
            if (btn) btn.disabled = true;

            // Reset champs
            setText('pi_id', '-');
            setText('pi_nom', '-');
            setText('pi_sexe', '-');
            setText('pi_age', '-');
            setText('pi_prof', '-');
            setText('pi_phone', '-');
            setText('pi_adresse', '-');
            setText('pi_resp', '-');

            try {
                const data = await fetchPatient(id);
                if (!data || !data.success || !data.patient) {
                    setAlert('warning', (data && data.message) ? data.message : 'Patient introuvable.');
                    if (btnDossier) btnDossier.href = '#';
                    if (btnCarte) btnCarte.href = '#';
                } else {
                    const p = data.patient;
                    const pid = p.id_patient || id;
                    setText('pi_id', 'PAT-' + String(p.id_patient || id));
                    setText('pi_nom', p.nom_patient);
                    setText('pi_sexe', p.sexe);
                    setText('pi_age', p.age);
                    setText('pi_prof', p.profession);
                    setText('pi_phone', p.phone);
                    setText('pi_adresse', p.adresse);
                    setText('pi_resp', p.responsable);

                    // Liens d'impression (dans le même module secrétariat)
                    if (btnDossier) btnDossier.href = 'imprimer_dossier.php?id_patient=' + encodeURIComponent(pid);
                    if (btnCarte) btnCarte.href = 'imprimer_carte.php?id_patient=' + encodeURIComponent(pid);
                }

                if (modalEl && window.bootstrap) {
                    const instance = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
                    instance.show();
                }
            } catch (err) {
                console.error('Erreur recherche patient:', err);
                setAlert('danger', 'Erreur lors de la recherche.');
                if (modalEl && window.bootstrap) {
                    const instance = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
                    instance.show();
                }
            } finally {
                if (btn) btn.disabled = false;
            }
        }

        if (form) form.addEventListener('submit', onSearch);

        function withAutoPrintDisabled(url) {
            if (!url) return url;
            return url + (url.indexOf('?') >= 0 ? '&' : '?') + 'autoprint=0';
        }

        function openPrintModal(url, titleText) {
            if (!url) return;
            if (!window.bootstrap || !printModalEl || !printFrameEl) {
                alert('Impossible d\'ouvrir le document: Bootstrap indisponible.');
                return;
            }
            if (printTitleEl) printTitleEl.textContent = titleText || 'Impression';
            printFrameEl.src = withAutoPrintDisabled(url);
            const instance = window.bootstrap.Modal.getInstance(printModalEl) || new window.bootstrap.Modal(printModalEl);
            instance.show();
        }

        if (btnDossier) {
            btnDossier.addEventListener('click', function (e) {
                const href = btnDossier.getAttribute('href');
                if (!href || href === '#') return;
                e.preventDefault();
                openPrintModal(href, 'Impression dossier');
            });
        }

        if (btnCarte) {
            btnCarte.addEventListener('click', function (e) {
                const href = btnCarte.getAttribute('href');
                if (!href || href === '#') return;
                e.preventDefault();
                openPrintModal(href, "Impression carte d'adhésion");
            });
        }

        if (printBtnEl) {
            printBtnEl.addEventListener('click', function () {
                try {
                    const win = printFrameEl && printFrameEl.contentWindow ? printFrameEl.contentWindow : null;
                    if (win && typeof win.printPdf === 'function') {
                        win.printPdf();
                        return;
                    }
                    if (win && typeof win.print === 'function') {
                        win.print();
                    }
                } catch (err) {
                    // noop
                }
            });
        }

        if (printModalEl) {
            printModalEl.addEventListener('hidden.bs.modal', function () {
                if (printFrameEl) printFrameEl.src = 'about:blank';
            });
        }
    });
    </script>
</body>
</html>
