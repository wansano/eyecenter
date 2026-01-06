<?php
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');

session_start();

class DocumentManager {
    private $bdd;
    private $errors = [];
    
    public function __construct($bdd) {
        $this->bdd = $bdd;
    }
    
    public function searchPayments($patientId) {
        try {
            $stmt = $this->bdd->prepare('SELECT * FROM paiements WHERE patient = ? AND remboursement = 0 ORDER BY datepaiement DESC');
            $stmt->execute([$patientId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->errors[] = "Erreur lors de la recherche des paiements : " . $e->getMessage();
            return [];
        }
    }
    
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    public function getErrors() {
        return $this->errors;
    }
}

// Initialisation
$documentManager = new DocumentManager($bdd);
$searchResult = null;
$message = '';

// Traitement de la recherche
if (isset($_POST['recherche']) && !empty($_POST['recherche'])) {
    $patientId = filter_var($_POST['recherche'], FILTER_SANITIZE_STRING);
    header("Location: reimpressiondocument.php?recherche=" . urlencode($patientId));
    exit;
}

include('../PUBLIC/header.php');
?>

<body>
    <section class="body">
        <?php require('../PUBLIC/navbarmenu.php'); ?>

        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Recherche de document d'un patient</h2>
                </header>

                <!-- Formulaire de recherche -->
                <div class="col-md-12">
                    <section class="card">
                        <div class="card-body">
                            <?php if ($documentManager->hasErrors()): ?>
                                <div class="alert alert-danger">
                                    <ul>
                                        <?php foreach ($documentManager->getErrors() as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <form class="form-horizontal" method="POST" action="">
                                <div class="row form-group pb-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label">Saisir le n° du dossier patient</label>
                                            <input type="text" class="form-control" name="recherche" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer text-end">
                                    <button type="submit" class="btn btn-primary">Rechercher</button>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
                
                <br>

                <!-- Résultats de la recherche -->
                <?php if (isset($_GET['recherche'])): ?>
                    <div class="col-md-12">
                        <section class="card">
                            <header class="card-header">
                                 <h5 class="card-title mb">Documents pour <?php echo htmlspecialchars(nom_patient($_GET['recherche'])); ?></h5>
                            </header>
                            <div class="card-body">
                                <table class="table table-responsive-md table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>DATE</th>
                                            <th>MOTIF</th>
                                            <th>DOCUMENT</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Récupère et affiche les paiements sans filtrage par $types afin d'éviter un blocage d'affichage
                                        $payments = $documentManager->searchPayments($_GET['recherche']);

                                        if (!empty($payments)) {
                                            foreach ($payments as $payment) {
                                                $idAffectation = isset($payment['id_affectation']) ? (int)$payment['id_affectation'] : 0;
                                                $typeTraitement = isset($payment['types']) ? (int)$payment['types'] : 0;
                                                $needsConsent = function_exists('consentement') && ((int)consentement($typeTraitement) === 1);
                                                $printUrl = $needsConsent
                                                    ? ('imprimer_recu_consentement.php?affectation=' . urlencode((string)$idAffectation))
                                                    : ('imprimer_recu.php?affectation=' . urlencode((string)$idAffectation));
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($payment['datepaiement']); ?></td>
                                                    <td><?php echo htmlspecialchars(model($payment['types'])); ?></td>
                                                    <td>
                                                        <a href="<?php echo htmlspecialchars($printUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                                           class="btn btn-sm btn-info js-open-recu">
                                                            <i class="fa fa-file-pdf-o"></i> Reçu
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php
                                            }
                                        } else {
                                            ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">Aucun reçu de paiement trouvé pour ce dossier.</td>
                                            </tr>
                                            <?php
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </section>
    
    <?php include('../PUBLIC/footer.php'); ?>

    <!-- Modal Reçu (aperçu + impression) -->
    <div class="modal fade" id="recuModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reçu de paiement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="height:80vh;">
                    <iframe id="recuFrame" src="about:blank" style="width:100%; height:100%;" frameborder="0"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" id="recuPrintBtn" class="btn btn-primary">Imprimer</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const recuModalEl = document.getElementById('recuModal');
        const recuFrameEl = document.getElementById('recuFrame');
        const recuPrintBtnEl = document.getElementById('recuPrintBtn');

        function withAutoPrintDisabled(url) {
            if (!url) return url;
            return url + (url.indexOf('?') >= 0 ? '&' : '?') + 'autoprint=0';
        }

        function openReceiptModal(url) {
            if (!url) return;
            if (!window.bootstrap || !recuModalEl || !recuFrameEl) {
                // fallback si bootstrap indisponible
                window.open(url, '_blank');
                return;
            }
            recuFrameEl.src = withAutoPrintDisabled(url);
            const instance = window.bootstrap.Modal.getInstance(recuModalEl) || new window.bootstrap.Modal(recuModalEl);
            instance.show();
        }

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.js-open-recu');
            if (!btn) return;
            const href = btn.getAttribute('href');
            if (!href || href === '#') return;
            e.preventDefault();
            openReceiptModal(href);
        });

        if (recuPrintBtnEl) {
            recuPrintBtnEl.addEventListener('click', function () {
                try {
                    const win = recuFrameEl && recuFrameEl.contentWindow ? recuFrameEl.contentWindow : null;
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

        if (recuModalEl) {
            recuModalEl.addEventListener('hidden.bs.modal', function () {
                if (recuFrameEl) recuFrameEl.src = 'about:blank';
            });
        }
    });
    </script>
</body>
</html>