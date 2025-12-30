<?php
include('../PUBLIC/connect.php');
session_start();

$errors = [];
$resultats = [];
$recherche = '';

if (isset($_POST['recherche'])) {
    $recherche = trim($_POST['recherche']);
    
    // Vérification de la longueur minimale de la recherche
    if (strlen($recherche) < 2) {
        $errors[] = "Veuillez saisir au moins 2 caractères pour la recherche.";
    } else {        try {
            // Requête avec tous les champs de recherche possibles
            $req = $bdd->prepare('SELECT * FROM patients WHERE 
                phone LIKE ? 
                OR nom_patient LIKE ? 
                OR profession LIKE ?
                OR responsable LIKE ?
                OR adresse LIKE ?');
                
            $searchTerm = '%' . $recherche . '%';
            $req->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            $resultats = $req->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($resultats)) {
                $errors[] = "Aucune information relative à la saisie '" . htmlspecialchars($recherche) . "' n'a été trouvée.";
            }
        } catch (PDOException $e) {
            error_log("Erreur de recherche patient : " . $e->getMessage());
            $errors[] = "Une erreur est survenue lors de la recherche : " . $e->getMessage();
        }
    }
}

require('../PUBLIC/header.php');
?>
<body>
    <section class="body">
        <?php require('../PUBLIC/navbarmenu.php'); ?>

        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Rechercher une information liée à un patient</h2>
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <section class="card">
                        <div class="card-body">
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <form class="form-horizontal" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                <div class="row form-group pb-3">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label class="col-form-label" for="recherche">
                                                Saisir : un prénom, un nom, un numéro de téléphone, une profession, une adresse ou le nom du responsable (minimum 2 caractères)
                                            </label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   name="recherche" 
                                                   id="recherche" 
                                                   value="<?php echo htmlspecialchars($recherche); ?>" 
                                                   minlength="2"
                                                   placeholder="Entrez au moins 2 caractères"
                                                   required>
                                        </div>
                                    </div>
                                </div>
                                <footer class="card-footer text-end">
                                    <button class="btn btn-primary" type="submit">Rechercher</button>
                                </footer>
                            </form>
                        </div>
                    </section>
                </div>
                <!-- end: page -->
            </section>
        </div>

        <?php if (!empty($resultats)): ?>
        <!-- Modal: Résultats de recherche -->
        <div class="modal fade" id="resultatsRechercheModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Résultats de la recherche</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-responsive-md table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>DOSSIER</th>
                                        <th>PATIENT</th>
                                        <th>PROFESSION</th>
                                        <th>CONTACT</th>
                                        <th>ADRESSE</th>
                                        <th>RESPONSABLE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resultats as $patient): ?>
                                        <tr>
                                            <td>PAT-<?php echo htmlspecialchars($patient['id_patient']); ?></td>
                                            <td><?php echo htmlspecialchars($patient['nom_patient']); ?></td>
                                            <td><?php echo htmlspecialchars($patient['profession']); ?></td>
                                            <td><?php echo htmlspecialchars($patient['phone']); ?></td>
                                            <td><?php echo htmlspecialchars(adress($patient['adresse']) ?: $patient['adresse']); ?></td>
                                            <td><?php echo htmlspecialchars($patient['responsable']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalEl = document.getElementById('resultatsRechercheModal');
            if (modalEl && window.bootstrap) {
                const instance = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
                instance.show();
            }
        });
        </script>
        <?php endif; ?>
        <?php include('../PUBLIC/footer.php'); ?>
