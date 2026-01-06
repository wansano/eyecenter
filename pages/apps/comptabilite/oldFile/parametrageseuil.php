<?php
    include('../PUBLIC/connect.php');
	session_start();
    $errors=0; $existe=0;

    if (isset($_POST['modification'])) {
        // Nettoyage du montant pour éviter les erreurs SQL
        $montant = str_replace([' ', '\u00A0'], '', $_POST['montant']);
        $montant = str_replace(',', '.', $montant);
        if (!is_numeric($montant)) $montant = 0;
        $req = $bdd->prepare('UPDATE seuilstock SET seuilreserve=?, seuilrupture=?, consommationjournaliere=?, delaireapprovisionnement=?, prix_monture=? WHERE id_seuil = ?');
        $req->execute([
            $_POST['SeuilReserve'],
            $_POST['SeuilRupture'],
            $_POST['ConsommationJournaliere'],
            $_POST['DelaiReapprovisionnement'],
            $montant,
            $_POST['modification']
        ]);
        $errors = 2;
    }

  $reponse1 = $bdd->prepare('SELECT * FROM seuilstock WHERE id_seuil=?');
	$reponse1 -> execute(array($_GET['idseuil']));
	while ($donnees1 = $reponse1->fetch())
	{
		$seuilreserve=$donnees1['seuilreserve'];
		$seuilrupture=$donnees1['seuilrupture'];
		$ventejournaliere=$donnees1['consommationjournaliere'];
		$delaiappro=$donnees1['delaireapprovisionnement'];
		$prixmontures=$donnees1['prix_monture'];
	}
?>

<?php include('../PUBLIC/header.php'); ?>	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Paramètrage du seuil de stock produit et délais appros</h2>
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
                                                <li>Seuil de stock mis à jour avec succès !</li>
                                            </div>
                                            ';
                                                }
                                        if ($errors==3) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <li>Mise à jour seuil de stock non effectuée</li>.
                                            </div>
                                            ';}
                                    	?>
                                    <form class="form-horizontal" novalidate="novalidate" method="POST" action="parametrageseuil.php?idseuil=<?php echo $_GET['idseuil'];?>" enctype="multipart/form-data">
                                    <input type="hidden" name="modification" value="<?php echo $_GET['idseuil'];?>">
										<div class="row form-group pb-3">
											<div class="col-md-2">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Seuil reserve</label>
													<input type="number" name="SeuilReserve" min="1" step="1" class="form-control" value="<?php echo $seuilreserve; ?>" required>
												</div>
											</div>
											<div class="col-md-2">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Seuil rupture</label>
													<input type="number" name="SeuilRupture" min="1" step="1" class="form-control" value="<?php echo $seuilrupture; ?>" required>
												</div>
											</div>
											<div class="col-md-2">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Estimation de vente journalière</label>
													<input type="number" name="ConsommationJournaliere" min="1" step="1" class="form-control" value="<?php echo $ventejournaliere; ?>" required>
												</div>
											</div>
											<div class="col-md-2">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Délai appro en jour</label>
													<input type="number" name="DelaiReapprovisionnement" min="1" step="1" class="form-control" value="<?php echo $delaiappro; ?>" required>
												</div>
											</div>
										</div>
										<div class="row form-group pb-3">
											<div class="col-md-2">
												<div class="form-group">
													<label class="col-form-label" for="formGroupExampleInput">Prix des montures en <?= htmlspecialchars($devise); ?></label>
													<input type="text" name="montant" min="1" step="1" class="form-control" id="montant" value="<?php echo htmlspecialchars(number_format($prixmontures)); ?>" required>
												</div>
											</div>
										</div>	
										<footer class="card-footer text-end">
											<button class="btn btn-primary" type="submit">Mettre à jour le seuil</button>
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