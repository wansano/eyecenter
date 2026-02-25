<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

$message = '';
$messageType = '';

if (isset($_POST['annulation'])) {
    $id_affectation = (int)($_POST['annulation'] ?? 0);

    if ($id_affectation <= 0) {
        $message = "Affectation invalide.";
        $messageType = 'danger';
    } else {
        try {
            $bdd->beginTransaction();

            $stmt = $bdd->prepare('SELECT id_affectation, status FROM affectations WHERE id_affectation = ? FOR UPDATE');
            $stmt->execute([$id_affectation]);
            $aff = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$aff) {
                throw new Exception("Affectation introuvable.");
            }

            if ((int)$aff['status'] !== 6) {
                throw new Exception("Cette affectation n'est pas en attente de paiement (status=6 requis).");
            }

            $stmt = $bdd->prepare('SELECT COUNT(*) FROM paiements WHERE id_affectation = ?');
            $stmt->execute([$id_affectation]);
            $paiementsCount = (int)$stmt->fetchColumn();

            if ($paiementsCount > 0) {
                throw new Exception("Un paiement existe déjà pour cette affectation. Annulation refusée.");
            }

            $stmt = $bdd->prepare('UPDATE affectations SET status = :statut, montant = :montant, taux = :taux, type_paiement = :paiement WHERE id_affectation = :affectation');
            $stmt->execute([
                'statut' => 5,
                'montant' => 0,
                'taux' => 0,
                'paiement' => 0,
                'affectation' => $id_affectation,
            ]);

            $bdd->commit();

            $message = 'Paiement en attente annulé avec succès.';
            $messageType = 'success';
        } catch (Exception $e) {
            if ($bdd->inTransaction()) {
                $bdd->rollBack();
            }
            error_log('Erreur annulation paiement caisse: ' . $e->getMessage());
            $message = $e->getMessage();
            $messageType = 'danger';
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
                    <h2>Annulation paiement caisse</h2>
                </header>

                <div class="col-md-12">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <section class="card">
                        <div class="card-body">
                            <table class="table table-bordered table-striped mb-0" id="datatable-default">
                                <thead>
                                    <tr>
                                        <th>AFFECTATION</th>
                                        <th>DOSSIER</th>
                                        <th>PATIENT</th>
                                        <th>CONTACT</th>
                                        <th>EXAMEN</th>
                                        <th>MONTANT</th>
                                        <th>ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                try {
                                    $stmt = $bdd->prepare(
                                        'SELECT a.id_affectation, a.id_patient, a.type, a.date, a.id_service, p.nom_patient, p.phone
                                         FROM affectations a
                                         LEFT JOIN patients p ON a.id_patient = p.id_patient
                                         WHERE a.status = 6 AND a.id_service IN (1,2,3,4)
                                         ORDER BY a.id_affectation'
                                    );
                                    $stmt->execute();
                                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    if (empty($rows)) {
                                        echo '<tr><td colspan="7">Aucun paiement en attente trouvé.</td></tr>';
                                    } else {
                                        foreach ($rows as $row) {
                                            $montant = (float)montant($row['type']);
                                            $modele = (string)model($row['type']);

                                            echo '<tr>';
                                            echo '<td>' . htmlspecialchars($row['date'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                                            echo '<td>' . htmlspecialchars($row['id_patient'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                                            echo '<td>' . htmlspecialchars($row['nom_patient'] ?? '—', ENT_QUOTES, 'UTF-8') . '</td>';
                                            echo '<td>' . htmlspecialchars($row['phone'] ?? '—', ENT_QUOTES, 'UTF-8') . '</td>';
                                            echo '<td>' . htmlspecialchars($modele, ENT_QUOTES, 'UTF-8') . '</td>';
                                            echo '<td>' . number_format($montant, 0, ',', ' ') . ' ' . htmlspecialchars($devise, ENT_QUOTES, 'UTF-8') . '</td>';
                                            echo '<td>';
                                            echo '<form method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" onsubmit="return confirm(\'Confirmer l\\\'annulation de ce paiement en attente ?\');" class="d-inline">';
                                            echo '<input type="hidden" name="annulation" value="' . (int)$row['id_affectation'] . '">';
                                            echo '<button type="submit" class="btn btn-sm btn-danger">Annuler</button>';
                                            echo '</form>';
                                            echo '</td>';
                                            echo '</tr>';
                                        }
                                    }
                                } catch (PDOException $e) {
                                    error_log($e->getMessage());
                                    echo '<tr><td colspan="7"><div class="alert alert-danger">Une erreur est survenue lors de la récupération des données.</div></td></tr>';
                                }
                                ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </section>
        </div>

        <?php include('../PUBLIC/footer.php'); ?>
</body>
