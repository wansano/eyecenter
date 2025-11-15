<?php
include('../PUBLIC/connect.php');
include_once('../PUBLIC/fonction.php');
session_start();

// Initialisation des variables
$message = '';
$messageType = '';

// Traitement de l'annulation
if (isset($_POST['annulation'])) {
    try {
        $bdd->beginTransaction();
        
        $stmt = $bdd->prepare('UPDATE affectations SET status = ?, montant = 0, taux = 0, type_paiement = 0 WHERE id_affectation = ?');
        if ($stmt->execute([5, $_POST['annulation']])) {
            $bdd->commit();
            $message = 'Processus de vente annulé avec succès.';
            $messageType = 'success';
        } else {
            throw new Exception("Échec de l'annulation");
        }
    } catch (Exception $e) {
        $bdd->rollBack();
        error_log("Erreur lors de l'annulation : " . $e->getMessage());
        $message = "Une erreur est survenue lors de l'annulation.";
        $messageType = 'danger';
    }
}

include('../PUBLIC/header.php');
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let reloadTimer;
    
    function setupAutoReload() {
        clearTimeout(reloadTimer);
        reloadTimer = setTimeout(function() {
            if (!document.hidden) {
                window.location.reload();
            }
        }, 60000);
    }
    
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            setupAutoReload();
        } else {
            clearTimeout(reloadTimer);
        }
    });
    
    setupAutoReload();
});
</script>

<body>
    <section class="body">
        <?php require('../PUBLIC/navbarmenu.php'); ?>
        
        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Liste des clients en salle</h2>
                    <div class="right-wrapper text-end">
                        <ol class="breadcrumbs">
                            <li>
                                <a href="welcome.php?profil=ecv2">
                                    <i class="bx bx-home-alt"></i>
                                </a>
                            </li>
                            <li><span>Accueil</span></li>
                        </ol>
                        <a class="sidebar-right-toggle" data-open="sidebar-right"></a>
                    </div>
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
                                            $stmt = $bdd->prepare('SELECT * FROM affectations WHERE status = ? ORDER BY id_affectation');
                                            $stmt->execute([6]); // Status EN_SALLE = 6
                                            
                                            while ($affectation = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                if (service($affectation['id_service']) == "Boutique" && $affectation['type'] != 0) {
                                                    $type_consultation = operation($affectation['type']);
                                                    $motif = model($affectation['type']);
                                                    $patient_nom = nom_patient($affectation['id_patient']);
                                                    $patient_contact = return_phone($affectation['id_patient']);
                                                    ?>
                                                    <tr>
                                                        <td>PAT-<?php echo htmlspecialchars($affectation['id_patient']); ?></td>
                                                        <td><?php echo htmlspecialchars($affectation['date']); ?></td>
                                                        <td><?php echo htmlspecialchars($patient_nom); ?></td>
                                                        <td><?php echo htmlspecialchars($patient_contact); ?></td>
                                                        <td><?php echo htmlspecialchars($motif); ?></td>
                                                        <td>
                                                            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="post" class="d-inline">
                                                                <input type="hidden" name="annulation" value="<?php echo $affectation['id_affectation']; ?>">
                                                                
                                                                <?php if ($type_consultation === 10): ?>
                                                                    <a href="ventedelunette.php?client=<?php echo $affectation['id_patient']; ?>&affectation=<?php echo $affectation['id_affectation']; ?>" 
                                                                       class="btn btn-sm btn-success">vente lunettes</a>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($type_consultation === 11): ?>
                                                                    <a href="ventedeverres.php?client=<?php echo $affectation['id_patient']; ?>&affectation=<?php echo $affectation['id_affectation']; ?>" 
                                                                       class="btn btn-sm btn-warning">vente de verres</a>
                                                                <?php endif; ?>

                                                                <?php if ($type_consultation === 12): ?>
                                                                    <a href="ventedemonture.php?client=<?php echo $affectation['id_patient']; ?>&affectation=<?php echo $affectation['id_affectation']; ?>" 
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
