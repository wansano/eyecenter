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
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($payment['datepaiement']); ?></td>
                                                    <td><?php echo htmlspecialchars(model($payment['types'])); ?></td>
                                                    <td>
                                                                          <a href="imprimer_recu.php?affectation=<?php echo urlencode($payment['id_affectation']); ?>" 
                                                           target="_blank" 
                                                           class="btn btn-sm btn-info">
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
</body>
</html>