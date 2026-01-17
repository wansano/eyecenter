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
    $rdv = $data['id_rdv'];

    if (!$data) {
        throw new Exception("Affectation non trouvée");
    }

    // Extraction des données
    extract($data);

    // Récupération des dernières données d'acuité visuelle et historique en une seule fois
    $derniereDonnees = recupererDerniereAcquiteEtHistorique($bdd, $id_patient);

    // Traitement du formulaire
    if (isset($_POST['consulter']) && !empty($_POST['refraction']) && !empty($_POST['od']) && !empty($_POST['os'])) {
        // Vérification si déjà traité
        $existe = checkTraitementExisteMesure($bdd, $affectation);

        if ($existe == 0) {
            $bdd->beginTransaction();
            
            try {
                insertMesures($bdd, $id_patient, $type, $affectation, $_POST);
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

include('../PUBLIC/header.php');   
?>

<body>
    <section class="body">

        <?php include('../PUBLIC/navbarmenu.php'); ?>

        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Examen d'un patient</h2>
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
                                                <li>Les informations relatives au traitement ont été enregistrées avec succès dans l\'espace du patient. Il peut toujours le consulter dans son propre espace ou <button type="button" class="btn btn-success btn-sm" id="btnImprimerOrdonnance"><i class="fa fa-file-pdf-o"></i> Imprimer l\'ordonnance</button></li>
                                                </div>
                                                ';
                                                    }
                                            if ($existe==1) {
                                            echo '
                                                <div class="alert alert-danger">
                                                    <strong>Erreur de validation de '.model($type).'  de '.nom_patient($id_patient).'</strong> <br/>  
                                                    <li>Cette '.model($type).' a déjà été approuvé ou veuillez vérifier les informations requises à fournir. Il est également possible <button type="button" class="btn btn-default btn-sm" id="btnImprimerOrdonnanceAlt"><i class="fa fa-file-pdf-o"></i> Imprimer l\'ordonnance</button></li>
                                                </div>
                                                ';}
                                    ?>
                            <?php include __DIR__ . '/../public/acquitehistorique.php'; ?>
                            <!-- Formulaire de consultation -->
                            <form class="form-horizontal" novalidate="novalidate" method="POST"
                                action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?affectation=<?php echo $affectation; ?>" enctype="multipart/form-data">
                                <input type="hidden" name="consulter" value="<?php echo $id_ffectation; ?>">
                                <div class="row form-group pb-3">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Type de réfraction</label>
                                            <select name="refraction" data-plugin-selectTwo class="form-control populate" data-plugin-options="{ "minimumInputLength": 2 }" value="<?php echo getFormValue('refraction'); ?>" required>
                                                <optgroup label="Choisir le type de réfraction">
                                                    <option value="Vision de près">Vision de près</option>
                                                    <option value="Vision de loin" selected>Vision de loin</option>
                                                    <option value="Progressif">Progressif</option>
                                                </optgroup>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row form-group pb-3">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Oeil droit</label>
                                            <input type="text" name="od" class="form-control" placeholder="Obligatoire" value="<?php echo getFormValue('od'); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Oeil gauche</label>
                                            <input type="text" name="os" class="form-control" placeholder="Optionnel" value="<?php echo getFormValue('os'); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Addition</label>
                                            <input type="text" name="addit" class="form-control" placeholder="Optionnel" value="<?php echo getFormValue('addit'); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">EIP</label>
                                            <input type="text" name="eip" class="form-control" placeholder="Optionnel" value="<?php echo getFormValue('eip'); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row form-group pb-3">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Details concernant les verres</label>
                                            <select name="details" data-plugin-selectTwo class="form-control populate" data-plugin-options="{ "minimumInputLength": 2 }" value="<?php echo getFormValue('refraction'); ?>" required>
                                                <optgroup label="Choisir les details concernant les verres">
                                                    <option value="Antireflet">Antireflet</option>
                                                    <option value="Antireflet Photochromique" selected>Antireflet Photochromique</option>
                                                    <option value="Antireflet Photochromique Progressif">Antireflet Photochromique Progressif</option>
                                                </optgroup>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <footer class="card-footer text-end">
                                    <button class="btn btn-primary" type="submit" name="ajouter">valider l'ordonnance</button>
                                </footer>
                            </form>
                        <!-- Fin du formulaire de consultation -->
                    </section>
                </div>
            </div>
        <!-- end: page -->
    </section>
    </div>

        <!-- Modal impression ordonnance -->
        <?php if (!empty($affectation)): ?>
        <div class="modal fade" id="ordonnanceModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ordonnanceModalTitle">Impression ordonnance</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" style="height:75vh;">
                        <iframe id="ordonnanceIframe" src="about:blank" style="width:100%;height:100%;border:0;"></iframe>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-bs-dismiss="modal">Fermer</button>
                        <button type="button" class="btn btn-primary" id="btnPrintOrdonnance">
                            <i class="fa fa-print"></i> Imprimer
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <script>
            function printOrdonnanceModal() {
                try {
                    var frame = document.getElementById('ordonnanceIframe');
                    if (!frame || !frame.contentWindow) return;

                    // Si la page imprimée expose printPdf(), on l'utilise; sinon print() standard
                    if (typeof frame.contentWindow.printPdf === 'function') {
                        frame.contentWindow.printPdf();
                    } else {
                        frame.contentWindow.print();
                    }
                } catch (e) {
                    try {
                        var frame2 = document.getElementById('ordonnanceIframe');
                        if (frame2 && frame2.contentWindow) frame2.contentWindow.print();
                    } catch (_) {
                        // noop
                    }
                }
            }

            function openOrdonnanceModal() {
                try {
                    if (!window.bootstrap) return;
                    var el = document.getElementById('ordonnanceModal');
                    if (!el) return;

                    // Charger le document au moment d'ouvrir le modal (évite l'auto-print au chargement de la page)
                    var frame = document.getElementById('ordonnanceIframe');
                    if (frame) {
                        var desiredUrl = 'imprimer_mesure.php?affectation=<?php echo (int)$affectation; ?>&autoprint=0';
                        if (!frame.getAttribute('src') || frame.getAttribute('src') === 'about:blank') {
                            frame.setAttribute('src', desiredUrl);
                        }
                    }

                    var modal = window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el);
                    modal.show();
                } catch (e) {
                    // noop
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                var btn = document.getElementById('btnImprimerOrdonnance');
                if (btn) btn.addEventListener('click', openOrdonnanceModal);
                var btnAlt = document.getElementById('btnImprimerOrdonnanceAlt');
                if (btnAlt) btnAlt.addEventListener('click', openOrdonnanceModal);

                var btnPrint = document.getElementById('btnPrintOrdonnance');
                if (btnPrint) btnPrint.addEventListener('click', printOrdonnanceModal);

                var autoOpen = <?php echo (!empty($affectation) && (int)$errors === 4) ? 'true' : 'false'; ?>;
                if (autoOpen) {
                    openOrdonnanceModal();
                }

                var modalEl = document.getElementById('ordonnanceModal');
                if (modalEl) {
                    modalEl.addEventListener('hidden.bs.modal', function () {
                        var frame = document.getElementById('ordonnanceIframe');
                        if (frame) frame.setAttribute('src', 'about:blank');
                    });
                }
            });
        </script>

    <?php include('../PUBLIC/footer.php');?>
