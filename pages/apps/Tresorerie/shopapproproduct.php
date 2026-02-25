<?php
include('../PUBLIC/connect.php');
session_start();
$commande = $_GET['commande'];
$errors=0; $existe=0;

$reponse1 = $bdd->prepare('SELECT * FROM approvisionnements WHERE no_commande=?');
$reponse1 -> execute(array($commande));
    while ($donnees1 = $reponse1->fetch())
    {
        $fournisseur=$donnees1['id_fournisseur'];
        $datecommande=$donnees1['date_commande'];
        $qtécommande=$donnees1['quantite_commande'];
        $datecommande=$donnees1['date_commande'];
        $descriptions=$donnees1['description'];
		$typecommande=$donnees1['type_commande'];
    }

if (isset($_POST['modification'])) {

    if ($qtécommande >= $_POST['quantite']) {

        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['fichier'])) {
            $uploadDir = "../DOCUMENTS/";
            $fileName = basename($_FILES['fichier']['name']);
            $filePath = $uploadDir . $fileName;
            $fileType = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            
            // Nettoyage du montant pour éviter l'erreur SQL
            $montantotal = str_replace([' ', '\u00A0'], '', $_POST['montantotal']); // supprime espaces et espaces insécables
            $montantotal = str_replace(',', '.', $montantotal); // remplace virgule par point si besoin
            if (!is_numeric($montantotal)) $montantotal = 0;
            
            if ($fileType === 'pdf') {

            	if (move_uploaded_file($_FILES['fichier']['tmp_name'], $filePath)) {
				$qtelivré = $_POST['quantite'];		
                $req = $bdd->prepare('UPDATE approvisionnements SET no_livraison=?, date_livraison=?, quantite_livre=?, quantite=?, montant_total=?, statut=?, description=?, fichier=? WHERE no_commande=?');
                $req->execute(array($_POST['nolivraison'], $_POST['datelivraison'], $_POST['quantite'], $qtelivré, $montantotal, 'livré', $_POST['description'], $fileName, $commande));
                $errors=4; 
			}

			if ($errors==4) {
				$reponse2 = $bdd->prepare('SELECT * FROM fournisseur_produit WHERE id_fournisseur=?');
				$reponse2->execute(array($fournisseur));
				while ($donnees2 = $reponse2->fetch()) {
					$debitactuel = $donnees2['debit'];
				}

				$reponse1 = $bdd->prepare('SELECT * FROM approvisionnements WHERE no_commande=?');
				$reponse1 -> execute(array($commande));
					while ($donnees1 = $reponse1->fetch())
					{$montanttotal=$donnees1['montant_total'];}

				$debitactuel +=$montanttotal;
				$req = $bdd->prepare('UPDATE fournisseur_produit SET debit=? WHERE id_fournisseur=?');
                $req->execute(array($debitactuel, $fournisseur));
				
				$errors=2;

						}
                	}
            	}
        	} else { $errors = 3; }
   	 	} 


    function fournisseur($nom){
        include('../PUBLIC/connect.php');
        $reponse1 = $bdd->prepare('SELECT * FROM fournisseur_produit WHERE id_fournisseur=?');
        $reponse1 -> execute(array($nom));
        $fournisseur=" ";
        while ($donnees1 = $reponse1->fetch())
        {
			$fournisseur=$donnees1['fournisseur'];
		}
		return $fournisseur;
     }

include('../PUBLIC/header.php'); ?>	

<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Enregistrement d'une livraison</h2>
					</header>

					<!-- start: page -->
                    <div class="col-md-12">
							<section class="card">
								<div class="card-body">
                                    <?php
                                        if ($errors==2) {
                                        echo '
                                            <div class="alert alert-success">
                                                <strong>Succès</strong> <br/>  
                                                <li>L\'approvisionnement à été ajouter avec succès !</li>
                                            </div>
                                            ';
                                                }
                                        if ($errors==3) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <li>Ajout L\'approvisionnement non effectué, merci de vérifier les quantités saisies.</li>
                                            </div>
                                            ';}

                                        if ($existe==1) {
                                        echo '
                                            <div class="alert alert-warning">
                                                <li>L\'approvisionnement existe déjà dans le système.</li>
                                            </div>
                                            ';
                                        }
                                    ?>
										<div class="row form-group pb-3">
											<div class="col-md-2">
												<div class="form-group">
													<label class="col-form-label" for="commande">N° commande</label>
													<input type="text" class="form-control" value="<?php echo htmlspecialchars($commande); ?>" disabled>
												</div>
											</div>
                                            <div class="col-md-2">
												<div class="form-group">
													<label class="col-form-label" for="datecommande">Date commande</label>
													<input type="date" class="form-control" value="<?php echo htmlspecialchars($datecommande); ?>" disabled>
												</div>
											</div>
											<div class="col-md-2">
												<div class="form-group">
													<label class="col-form-label" for="qtcommande">Quantité commandée</label>
													<input type="number" class="form-control" min="1" step="1" value="<?php echo htmlspecialchars($qtécommande); ?>" disabled>
												</div>
											</div>
                                            <div class="col-md-4">
												<div class="form-group">
													<label class="col-form-label" for="fournisseur">Fournisseur</label>
													<input type="text" class="form-control" value="<?php echo htmlspecialchars(fournisseur($fournisseur)); ?>" disabled>
												</div>
											</div>
                                            <div class="col-md-2">
												<div class="form-group">
													<label class="col-form-label" for="typecommande">Type de commande</label>
													<input type="text" class="form-control" value="<?php echo htmlspecialchars($typecommande); ?>" disabled>
												</div>
											</div>
										</div>
									<form class="form-horizontal" novalidate method="POST" action="shopapproproduct.php?commande=<?php echo urlencode($commande); ?>" enctype="multipart/form-data">
										<input type="hidden" name="modification" value="<?php echo htmlspecialchars($commande); ?>">
										<div class="row form-group pb-3">
                                            <div class="col-md-2">
												<div class="form-group">
													<label class="col-form-label" for="nolivraison">N° bon de livraison</label>
													<input type="text" name="nolivraison" class="form-control" required>
												</div>
											</div>
											<div class="col-md-2">
												<div class="form-group">
													<label class="col-form-label" for="datelivraison">Date livraison</label>
													<input type="date" class="form-control" name="datelivraison" required>
												</div>
											</div>
											<div class="col-md-2">
												<div class="form-group">
													<label class="col-form-label" for="quantite">Quantité totale livrée</label>
													<input type="number" class="form-control" min="1" step="1" name="quantite" required>
												</div>
											</div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label class="col-form-label" for="montantotal">Montant facture</label>
                                                    <input type="text" min="0" step="0" class="form-control populate" name="montantotal" id="montantotal" required="">
                                                </div>
											</div>
                                            <div class="col-md-4">
												<div class="form-group">
													<label class="col-form-label" for="fichier">Justificatif (PDF seulement)</label>
													<input type="file" class="form-control" name="fichier" accept="application/pdf" required>
												</div>
											</div>
											<div class="col-md-12">
												<div class="form-group">
													<label class="col-form-label" for="description">Description</label>
													<textarea class="form-control" rows="4" name="description" placeholder="Décrire les produits livrés" required><?php echo htmlspecialchars($descriptions); ?></textarea>
												</div>
											</div>
										</div>
										<footer class="card-footer text-end">
											<button class="btn btn-primary" type="submit">Enregistrer</button>
										</footer>
                                	</form>
								</section>
							</div>
						</div>
					<!-- end: page -->
				</section>
			</div>
            <?php include('../PUBLIC/footer.php');?>
		<script>
            document.addEventListener('DOMContentLoaded', function() {
            const montantInput = document.getElementById('montantotal');
            if (montantInput) {
                montantInput.addEventListener('input', function(e) {
            let selectionStart = this.selectionStart;
            let oldLength = this.value.length;
            let value = this.value.replace(/\s/g, '');
            value = value.replace(/\D/g, '');
            if (value) {
                let formatted = value.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                this.value = formatted;
                // Ajuster la position du curseur
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