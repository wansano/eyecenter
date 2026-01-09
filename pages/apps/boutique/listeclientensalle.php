<?php
include('../PUBLIC/connect.php');
include_once('../PUBLIC/fonction.php');
session_start();

// Initialisation des variables
$message = '';
$messageType = '';

// Flash message (pattern PRG)
if (!empty($_SESSION['flash_message'])) {
    $message = (string)$_SESSION['flash_message'];
    $messageType = !empty($_SESSION['flash_message_type']) ? (string)$_SESSION['flash_message_type'] : 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_message_type']);
}

// Traitement de l'annulation
if (isset($_POST['annulation'])) {
    try {
        $idAffectation = filter_input(INPUT_POST, 'annulation', FILTER_VALIDATE_INT);
        if (!$idAffectation) {
            throw new Exception("Identifiant d'annulation invalide");
        }

        $stmt = $bdd->prepare('UPDATE affectations SET status = ?, montant = 0, taux = 0, type_paiement = 0 WHERE id_affectation = ?');
        $stmt->execute([5, $idAffectation]);

        if ($stmt->rowCount() < 1) {
            throw new Exception("Aucune ligne modifiée (affectation introuvable ou déjà mise à jour)");
        }

        $_SESSION['flash_message'] = 'Processus de vente annulé avec succès.';
        $_SESSION['flash_message_type'] = 'success';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        error_log("Erreur lors de l'annulation : " . $e->getMessage());
        $_SESSION['flash_message'] = "Une erreur est survenue lors de l'annulation.";
        $_SESSION['flash_message_type'] = 'danger';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

include('../PUBLIC/header.php');
?>

<script>
    // Même comportement que caisse/patientensalle.php
    // Rechargement toutes les 60s, mais jamais pendant qu'un modal est ouvert.
    setInterval(function() {
        if (document.querySelector('.modal.show')) return;
        location.reload();
    }, 60000);
</script>

<body>
    <section class="body">
        <?php require('../PUBLIC/navbarmenu.php'); ?>
        
        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Liste des clients en salle</h2>
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <div class="row">
                        <div class="col">
                            <section class="card">
                                <div class="card-body">
                                    <?php if ($message): ?>
                                        <div class="alert alert-<?php echo $messageType; ?>">
                                            <?php echo htmlspecialchars($message); ?>
                                        </div>
                                    <?php endif; ?>

                                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                                        <thead>
                                            <tr>
                                                <th>N°</th>
                                                <th>AFFECTATION</th>
                                                <th>PATIENT</th>
                                                <th>CONTACT</th>
                                                <th>MOTIF</th>
                                                <th>ACTION</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        try {
                                            // Filtrage côté SQL pour éviter des appels inutiles et accélérer l'affichage
                                            $sql = "SELECT id_affectation, id_patient, id_service, type, date, status
                                                    FROM affectations
                                                    WHERE status IN (6, 3)
                                                      AND id_service = 14
                                                      AND type <> 0
                                                    ORDER BY id_affectation";
                                            $stmt = $bdd->prepare($sql);
                                            $stmt->execute();
                                            
                                            while ($affectation = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                $typeId = (int)$affectation['type'];
                                                $patientId = (int)$affectation['id_patient'];
                                                $affectationId = (int)$affectation['id_affectation'];

                                                // operation() renvoie souvent une chaîne: on caste pour que les comparaisons strictes (===) fonctionnent
                                                $type_consultation = (int)operation($typeId);
                                                $motif = model($typeId);
                                                $patient_nom = nom_patient($patientId);
                                                $patient_contact = return_phone($patientId);
                                                    ?>
                                                    <tr>
                                                        <td>PAT-<?php echo htmlspecialchars((string)$patientId, ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars((string)$affectation['date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars((string)$patient_nom, ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars((string)$patient_contact, ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars((string)$motif, ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td>
                                                            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="post" class="d-inline">
                                                                <input type="hidden" name="annulation" value="<?php echo htmlspecialchars((string)$affectationId, ENT_QUOTES, 'UTF-8'); ?>">
                                                                
                                                                <?php if ($type_consultation === 10): ?>
                                                                    <a href="ventedelunette.php?client=<?php echo urlencode((string)$patientId); ?>&affectation=<?php echo urlencode((string)$affectationId); ?>" 
                                                                       class="btn btn-sm btn-success">vente lunettes</a>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($type_consultation === 11): ?>
                                                                    <a href="ventedeverres.php?client=<?php echo urlencode((string)$patientId); ?>&affectation=<?php echo urlencode((string)$affectationId); ?>" 
                                                                       class="btn btn-sm btn-warning">vente de verres</a>
                                                                <?php endif; ?>

                                                                <?php if ($type_consultation === 12): ?>
                                                                    <a href="ventedemonture.php?client=<?php echo urlencode((string)$patientId); ?>&affectation=<?php echo urlencode((string)$affectationId); ?>" 
                                                                       class="btn btn-sm btn-info">vente monture</a>
                                                                <?php endif; ?>
                                                                
                                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                                        onclick="return confirm('Êtes-vous sûr de vouloir annuler ?');">
                                                                    annuler
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                    <?php
                                            }
                                        } catch (PDOException $e) {
                                            error_log("Erreur SQL : " . $e->getMessage());
                                            echo '<div class="alert alert-danger">Une erreur est survenue lors du chargement des données. Veuillez réessayer plus tard.</div>';
                                        } catch (Exception $e) {
                                            error_log("Erreur générale : " . $e->getMessage());
                                            echo '<div class="alert alert-danger">Une erreur inattendue est survenue. Veuillez réessayer plus tard.</div>';
                                        }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
                <!-- end: page -->
            </section>
        </div>
        <?php include('../PUBLIC/footer.php'); ?>
