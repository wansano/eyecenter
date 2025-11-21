<?php
include('../public/connect.php');
require_once('../public/fonction.php');
session_start();

// Affichage des erreurs en dev pour mieux diagnostiquer
if (function_exists('ini_set')) {
    @ini_set('display_errors', '1');
    @ini_set('display_startup_errors', '1');
}
error_reporting(E_ALL);

// Utilitaires
function parse_amount($str) {
    // Supprime les espaces (y compris insécables), remplace la virgule par un point et caste en float
    if ($str === null) return 0.0;
    $s = (string)$str;
    $s = str_replace("\xC2\xA0", '', $s); // NBSP UTF-8
    $s = str_replace(' ', '', $s);
    $s = str_replace(',', '.', $s);
    return (float)$s;
}

$affectation = filter_input(INPUT_GET, 'id_affectation', FILTER_VALIDATE_INT);
$errors = 0;
$existe = 0; // obsolète avec la mise à jour, conservé pour compat

// Récupérer les informations de paiement
try {
    $reponse1 = $bdd->prepare('SELECT * FROM affectations WHERE id_affectation = ?');
    $reponse1->execute([$affectation]);
    $donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC);

    if (!$donnees1) {
        throw new Exception("Affectation introuvable");
    }

    $patient = $donnees1['id_patient'];
    $montant_du = (float)$donnees1['montant'];
    $typesc = $donnees1['type'];

    // Récupérer le remboursement créé lors du refus pour afficher le motif (clé: id_affectation)
    $motif_initial = '';
    try {
        $rembSelInit = $bdd->prepare('SELECT id_remboursement, motif FROM remboursements WHERE id_affectation = ? ORDER BY id_remboursement DESC LIMIT 1');
        $rembSelInit->execute([$affectation]);
        $rembInit = $rembSelInit->fetch(PDO::FETCH_ASSOC);
        if ($rembInit) { $motif_initial = (string)$rembInit['motif']; }
    } catch (Exception $ie) {
        // ignore affichage motif si indisponible
    }

    // Vérification de la soumission du formulaire
    if (isset($_POST['payer']) && $errors == 0) {
        $montant_remb = parse_amount($_POST['montant'] ?? '0');
        
        // Récupérer le remboursement existant (créé lors du refus) — clé: id_affectation
        $rembSel = $bdd->prepare('SELECT id_remboursement FROM remboursements WHERE id_affectation = ? ORDER BY id_remboursement DESC LIMIT 1');
        $rembSel->execute([$affectation]);
        $remb = $rembSel->fetch(PDO::FETCH_ASSOC);
        if (!$remb) {
            // Aucun remboursement à mettre à jour
            $errors = 4;
        } else {
        // Récupérer le solde du compte
        $req0 = $bdd->prepare('SELECT solde, credit FROM comptes WHERE id_compte = ?');
        $req0->execute([$_POST['compte']]);
        $donnes = $req0->fetch(PDO::FETCH_ASSOC);

        if (!$donnes) {
            throw new Exception('Compte introuvable');
        }

        $solde = (float)$donnes['solde'];
        $credit = (float)$donnes['credit'];

        // Vérification des conditions avant l'insertion
        if ($montant_remb <= 0) {
            $errors = 3; // Montant invalide
        } elseif ($montant_remb > $montant_du) {
            $errors = 3; // Supérieur à la redevance
        } elseif ($solde < $montant_remb) {
            $errors = 2; // Solde insuffisant
        } else {

            // Démarrer la transaction pour garantir la cohérence des données
            $bdd->beginTransaction();

            try {
                // Mise à jour du remboursement existant
                $upd = $bdd->prepare('UPDATE remboursements 
                    SET paye_a = ?, montant_paye = ?, montant_remboursse = ?, compte = ?, date_ajout = ?, payeur = ?, types = ?
                    WHERE id_remboursement = ?');
                $upd->execute([
                    $_POST['payea'],
                    $montant_remb,
                    $montant_remb,
                    $_POST['compte'],
                    $_POST['dateajout'],
                    $_SESSION['auth'],
                    $typesc,
                    (int)$remb['id_remboursement'],
                ]);

                // Mise à jour du crédit et du solde du compte
                $credit += $montant_remb;
                $req3 = $bdd->prepare('UPDATE comptes SET credit = :credit WHERE id_compte = :compte');
                $req3->execute([
                    'credit' => $credit,
                    'compte' => $_POST['compte']
                ]);

                // Mise à jour du montant dans paiements
                $nouveau_montant = max(0, $montant_du - $montant_remb);

                $req = $bdd->prepare('UPDATE paiements SET montant=?, remboursement = ? WHERE id_affectation = ?');
                $req->execute([$nouveau_montant, ($nouveau_montant == 0 ? 1 : 0), $affectation]);

                // Mise à jour du statut et montant dans affectations
                
                $req = $bdd->prepare('UPDATE affectations SET status = ?, montant = ?, datetraitement=? WHERE id_affectation = ?');
                $req->execute([0, $nouveau_montant, $_POST['dateajout'], $affectation]);

                // Valider la transaction
                $bdd->commit();
                $errors = 5;
                // Empêcher la resoumission du formulaire (PRG pattern)
                header('Location: payementreval.php?id_affectation='.$affectation.'&success=1');
                exit;
            } catch (Exception $e) {
                // Journaliser l'erreur réelle et retourner un code générique
                error_log('Erreur remboursement: '. $e->getMessage());
                $errors = 2;
                try { $bdd->rollBack(); } catch (Throwable $t) { /* ignore */ }
            }
        }
        }
    }
} catch (PDOException $e) {
     error_log('PDOException: '.$e->getMessage());
}

include('../public/header.php');
?>

	<body>
		<section class="body">

            <?php require('../public/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Remboursement d'un patient</h2>
					</header>

					<!-- start: page -->
                    <div class="col-md-12">
							<section class="card">
								<div class="card-body">
                                    <?php
                                        if ($errors==5 || isset($_GET['success'])) {
                                            // Montant réellement remboursé lors de l'opération
                                            $montantAffiche = isset($montant_remb) && $errors==5
                                                ? number_format($montant_remb)
                                                : number_format($montant_du - $donnees1['montant']);
                                        echo '
                                            <div class="alert alert-success">
                                                <strong>Succès</strong><br/>  
                                                <li>Le remboursement de '.$montantAffiche.' '.$devise.' à été éffectuer avec succès !</li>
                                                <li>Vous pouvez imprimer le reçu de remboursement en cliquant sur <a href="bonderemboursement.php?affectation='.$affectation.'" target="_blank"><i class="fa fa-file-pdf-o"></i> Reçu de remboursement</a>.</li>
                                            </div>
                                            ';
                                                }
                                        if ($errors==3) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <li>Erreur</li>
                                                <li>Le montant de remboursement est superieur à la redevance, merci de vérifier le montant saisie</li>
                                            </div>
                                            ';}
                                        if ($errors==2) {
                                            echo '
                                                <div class="alert alert-danger">
                                                    <li>Erreur</li>
                                                    <li>Solde compte insuffisant, merci d\'approvisonner le compte</li>
                                                </div>
                                            ';}
                                        if ($errors==4) {
                                            echo '
                                                <div class="alert alert-danger">
                                                    <li>Erreur</li>
                                                    <li>Aucun remboursement en attente à mettre à jour pour cette affectation.</li>
                                                </div>
                                                ';}
                                    ?>
                                <div class="row form-group pb-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="productPrice">Patient</label>
                                            <input type="text" class="form-control" value="<?php echo nom_patient($patient); ?>" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="productPrice">Traitement</label>
                                            <input type="text" class="form-control" value="<?php echo model($typesc); ?>" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="productPrice">Montant à remboursser</label>
                                            <input type="text" class="form-control" value="<?php echo number_format($montant_du).' '.$devise; ?>" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="col-form-label" for="productPrice">Disponibilité dans le compte </label> <?= $devise ?>
                                            <input type="text" class="form-control" id="soldeCompte" name="price_display" style="background-color:#64F584;" disabled>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!isset($_GET['success'])): ?>
                                <form class="form-horizontal" novalidate="novalidate" method="POST" action="payementreval.php?id_affectation=<?php echo $affectation;?>" enctype="multipart/form-data">
                                <input type="hidden" name="payer" value="<?php echo $affectation;?>">
                                    <div class="row form-group pb-3">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Payé à</label>
                                                <input type="text" name="payea" class="form-control" required="">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Montant</label>
                                                <input type="text" name="montant" id="montant" class="form-control" min="1" step="1" required="" data-max="<?php echo (int)$montant_du; ?>" placeholder="Max <?php echo number_format($montant_du); ?>">
                                                <small class="text-muted" id="montantHelp">Ne pas dépasser <?php echo number_format($montant_du).' '.$devise; ?>.</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Choisir le mode de règlement</label>
                                                <select class="form-control" name="compte" id="compteSelect" onchange="soldeCompte()" required="">
                                                    <option value=""> ------ Choisir le compte ----- </option>
                                                <?php 
                                                    $type = $bdd->prepare('SELECT * FROM comptes WHERE status=? AND solde>=?');
                                                    $type -> execute([1, $montant_du]);
                                                    while ($type_paiement = $type->fetch())
                                                    {
                                                        echo '<option value="'.$type_paiement['id_compte'].'">'.$type_paiement['nom_compte'].'</option>';
                                                    } 
                                                ?>
                                            </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Date paiement</label>
                                                <input type="date" name="dateajout" class="form-control" required="" max="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Motif du remboursement</label>
                                                <textarea class="form-control" rows="3" disabled><?php echo htmlspecialchars($motif_initial); ?></textarea>
                                            </div>
                                        </div>
								    </div>
                                    <footer class="card-footer text-end">
                                        <button class="btn btn-primary" type="submit">Payer</button>
                                    </footer>
                                </form>
                                <?php endif; ?>
							</section>
						</div>
					</div>
					<!-- end: page -->
				</section>
            </div>
            <?php if (isset($_GET['success']) && $affectation): ?>
                <script>
                    window.onload = function() {
                        window.open('imprimer_remboursement.php?affectation=<?= (int)$affectation ?>', '_blank');
                    };
                </script>
            <?php endif; ?>
            <?php include('../public/footer.php');?>
            <!-- Fonction JS pour afficher le solde du compte sélectionné -->
<script>

// Formatage + limitation du champ montant
document.addEventListener('DOMContentLoaded', function() {
    const montantInput = document.getElementById('montant');
    const help = document.getElementById('montantHelp');
    if (!montantInput) return;
    const max = parseInt(montantInput.getAttribute('data-max'), 10) || 0;

    function format(val) {
        return val.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    montantInput.addEventListener('input', function() {
        // Nettoyage chiffres uniquement
        let raw = this.value.replace(/\s/g,'').replace(/\D/g,'');
        if (!raw) { this.value=''; return; }
        let num = parseInt(raw,10);
        if (num > max) {
            num = max; // clamp
            if (help) {
                help.classList.remove('text-muted');
                help.classList.add('text-danger');
                help.textContent = 'Montant limité à ' + format(String(max)) + ' <?php echo $devise; ?>';
            }
        } else if (help) {
            help.classList.remove('text-danger');
            help.classList.add('text-muted');
            help.textContent = 'Ne pas dépasser ' + format(String(max)) + ' <?php echo $devise; ?>.';
        }
        this.value = format(String(num));
    });
});
</script>
