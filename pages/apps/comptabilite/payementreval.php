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
$existe = 0;

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

    // Vérification de la soumission du formulaire
    if (isset($_POST['payer'])) {
        $montant_remb = parse_amount($_POST['montant'] ?? '0');

        $req1 = $bdd->prepare('SELECT 1 FROM remboursements WHERE id_affectation = ? AND patient = ? AND montant_remboursse = ? AND compte = ? AND date_ajout = ?');
        $req1 -> execute([$affectation, $patient, $montant_remb, $_POST['compte'], $_POST['dateajout']]);
        while ($data = $req1->fetch(PDO::FETCH_ASSOC))
        { 
            $existe=1; 
        }
        
        if ($existe == 0) {
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
                // Insertion dans la table remboursements
                $paie = $bdd->prepare('INSERT INTO remboursements 
                    (paye_a, id_affectation, patient, types, montant_paye, montant_remboursse, compte, motif, date_ajout, payeur)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $paie->execute([
                    $_POST['payea'], 
                    $affectation, 
                    $patient, 
                    $typesc, 
                    $montant_remb, 
                    $montant_remb, 
                    $_POST['compte'],  
                    $_POST['motif'], 
                    $_POST['dateajout'],
                    $_SESSION['auth'],
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
                                        if ($errors==5) {
                                        echo '
                                            <div class="alert alert-success">
                                                <strong>Succès</strong><br/>  
                                                <li>Le remboursement de '.$_POST['montant'].' '.$devise.' à été éffectuer avec succès !</li>
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
                                        if ($existe==1) {
                                            echo '
                                                <div class="alert alert-danger">
                                                    <li>Erreur</li>
                                                    <li>Ce paiement à été déjà effectuée</li>
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
                                                <input type="text" name="montant" id="montant" class="form-control" min="1" step="1" required="">
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
                                                <input type="date" name="dateajout" class="form-control" required="">
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label class="col-form-label" for="formGroupExampleInput">Motif de remboursement</label>
                                                <textarea name="motif" class="form-control" rows="3" placeholder="Obligatoire" required=""></textarea>
                                            </div>
                                        </div>
								    </div>
                                    <footer class="card-footer text-end">
                                        <button class="btn btn-primary" type="submit">Payer</button>
                                    </footer>
                                </form>
							</section>
						</div>
					</div>
					<!-- end: page -->
				</section>
			</div>
            <?php include('../public/footer.php');?>
            <!-- Fonction JS pour afficher le solde du compte sélectionné -->
<script>

// Formatage du champ montant
    document.addEventListener('DOMContentLoaded', function() {
        const montantInput = document.getElementById('montant');
        if (montantInput) {
            montantInput.addEventListener('input', function(e) {
                let selectionStart = this.selectionStart;
                let oldLength = this.value.length;
                let value = this.value.replace(/\s/g, '');
                value = value.replace(/\D/g, '');
                if (value) {
                    let formatted = value.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                    this.value = formatted;
                    let newLength = formatted.length;
                    let diff = newLength - oldLength;
                    this.setSelectionRange(selectionStart + diff, selectionStart + diff);
                } else {
                    this.value = '';
                }
            });
        }
    });
</script>