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
    public function hasErrors(): bool {
        return !empty($this->errors);
    }
    public function getErrors(): array {
        return $this->errors;
    }
    public function addError(string $error): void {
        $this->errors[] = $error;
    }
}

// Initialisation
$documentManager = new DocumentManager($bdd);
if (!empty($_POST['recherche'])) {
    $patientId = filter_input(INPUT_POST, 'recherche');
    if ($patientId) {
        // Vérifier si le dossier existe dans la base de données
        $stmt = $bdd->prepare('SELECT COUNT(*) FROM patients WHERE id_patient = ?');
        $stmt->execute([$patientId]);
        $exists = $stmt->fetchColumn();
        if ($exists) {
            echo '<script type="text/javascript">
                    window.open("imprimer_dossier.php?id_patient=' . urlencode($patientId) . '", "_blank");
                    window.location.href = "' . basename(__FILE__) . '";
                  </script>';
            exit;
        } else {
            $documentManager->addError('Ce n° dossier n\'existe pas.');
        }
    }
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
                <div class="col-md-12">
                    <section class="card">
                        <div class="card-body">
                            <?php if ($documentManager->hasErrors()): ?>
                                <div class="alert alert-danger">
                                    <ul>
                                        <?php foreach ($documentManager->getErrors() as $error): ?>
                                            <li><?= htmlspecialchars($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <form class="form-horizontal" method="POST" action="">
                                <div class="row form-group pb-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label">Saisir le n° du dossier</label>
                                            <input type="text" class="form-control" name="recherche" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer text-end">
                                    <button type="submit" class="btn btn-primary">Rechercher le dossier</button>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
            </section>
        </div>
    </section>
    <?php include('../PUBLIC/footer.php'); ?>
</body>
</html>