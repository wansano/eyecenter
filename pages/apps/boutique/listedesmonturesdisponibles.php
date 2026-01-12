<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$errors = 0;
$existe = 0;
$openAddModal = false;
$ok = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;

// Ajout monture via modal
if (isset($_POST['ajouter_monture'])) {
    $openAddModal = true;
    $codeMonture = isset($_POST['codeMonture']) ? trim((string)$_POST['codeMonture']) : '';
    $noLivraison = isset($_POST['nolivraison']) ? trim((string)$_POST['nolivraison']) : '';
    $idMarque = isset($_POST['marque']) ? (int)$_POST['marque'] : 0;
    $prixMonture = isset($_POST['prixmonture']) ? (float)$_POST['prixmonture'] : 0;
    $couleur = isset($_POST['couleur']) ? trim((string)$_POST['couleur']) : '';
    $typeMonture = isset($_POST['typeMonture']) ? trim((string)$_POST['typeMonture']) : '';

    if ($codeMonture === '' || $noLivraison === '' || $idMarque <= 0 || $couleur === '') {
        $errors = 8;
    } else {
        try {
            $req1 = $bdd->prepare('SELECT 1 FROM montures WHERE code_monture = ? LIMIT 1');
            $req1->execute([$codeMonture]);
            $existe = $req1->fetchColumn() ? 1 : 0;

            if ($existe == 0) {
                $reponse2 = $bdd->prepare('SELECT quantite FROM approvisionnements WHERE no_livraison = ? LIMIT 1');
                $reponse2->execute([$noLivraison]);
                $donnees2 = $reponse2->fetch(PDO::FETCH_ASSOC);

                if (!$donnees2) {
                    $errors = 6;
                } else {
                    $qteAppro = (int)($donnees2['quantite'] ?? 0);
                    if ($qteAppro < 1) {
                        $errors = 5;
                    } else {
                        $reponse1 = $bdd->prepare('SELECT quantite FROM marques WHERE id_marque = ? LIMIT 1');
                        $reponse1->execute([$idMarque]);
                        $quantiteModeleRaw = $reponse1->fetchColumn();
                        if ($quantiteModeleRaw === false) {
                            $errors = 7;
                        } else {
                            $quantiteModele = (int)$quantiteModeleRaw;

                            $bdd->beginTransaction();
                            $req = $bdd->prepare('INSERT INTO montures (code_monture, no_livraison, id_marque, couleur, prix, monture_pour) VALUES(?,?,?,?,?,?)');
                            $req->execute([$codeMonture, $noLivraison, $idMarque, $couleur, $prixMonture, ($typeMonture === '' ? null : $typeMonture)]);

                            $reqQ = $bdd->prepare('UPDATE marques SET quantite = ? WHERE id_marque = ?');
                            $reqQ->execute([$quantiteModele + 1, $idMarque]);

                            $reqA = $bdd->prepare('UPDATE approvisionnements SET quantite = quantite - 1 WHERE no_livraison = ? AND quantite > 0');
                            $reqA->execute([$noLivraison]);
                            if ($reqA->rowCount() < 1) {
                                throw new Exception('Quantité approvisionnement insuffisante');
                            }

                            $bdd->commit();
                            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=2');
                            exit;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            if ($bdd->inTransaction()) {
                $bdd->rollBack();
            }
            error_log('listedemonturesdisponibles add error: ' . $e->getMessage());
            $errors = 9;
        }
    }
}

include('../PUBLIC/header.php'); ?>

	<body>
		<section class="body">

			<?php require '../PUBLIC/navbarmenu.php'; ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Liste globals des montures en stock</h2>
					</header>

					<!-- start: page -->
					<div class="col-md-12">
						<div class="row">
							<div class="col">
								<section class="card">
                                    <header class="card-header">
                                        <h3 class="card-title" style="text-transform:inherit; font-weight:lighter; ">
                                            <button class="btn btn-info btn-sm" onclick="exportAllDataToExcel('datatable-default', 'liste_des_montures_en_stock')">Exporter au format excel</button>
                                            <button class="btn btn-success btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addMontureModal">Ajouter une monture</button>
                                        </h3>
                                    </header>
									<div class="card-body">
                                        <?php
                                        if ($ok === 2) {
                                            echo '<div class="alert alert-success"><li><strong>Succès</strong><br>La monture a été ajoutée avec succès.</li></div>';
                                        }
                                        if ($errors == 5) {
                                            echo '<div class="alert alert-danger"><li>Ajout non effectué : quantité insuffisante.</li></div>';
                                        }
                                        if ($errors == 6) {
                                            echo '<div class="alert alert-warning"><li>Bon de livraison introuvable. Vérifiez le N° sélectionné.</li></div>';
                                        }
                                        if ($errors == 7) {
                                            echo '<div class="alert alert-warning"><li>Marque introuvable. Merci de sélectionner une marque valide.</li></div>';
                                        }
                                        if ($errors == 8) {
                                            echo '<div class="alert alert-warning"><li>Veuillez remplir tous les champs obligatoires (code, livraison, marque, couleur).</li></div>';
                                        }
                                        if ($errors == 9) {
                                            echo '<div class="alert alert-danger"><li>Erreur technique lors de l\'ajout. Réessayez, puis contactez l\'administrateur si le problème persiste.</li></div>';
                                        }
                                        if ($existe == 1) {
                                            echo '<div class="alert alert-warning"><li>La monture existe déjà dans le système.</li></div>';
                                        }
                                        ?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
													<th>CODE MONTURE</th>
                                                    <th>MARQUE</th>
                                                    <th>COULEUR</th>
                                                    <th>MONTURE POUR </th>
                                                    <th>PRIX</th>
                                                    <th>DATE AJOUT</th>
												</tr>
											</thead>
											<tbody>
											    <?php
                                                    $reponse = $bdd->prepare('SELECT m.*, ma.marque AS marque_nom FROM montures m LEFT JOIN marques ma ON ma.id_marque = m.id_marque WHERE m.retour = 0 AND m.vendu = 0 ORDER BY m.id_monture');
													$reponse->execute();
													while ($donnees1 = $reponse->fetch(PDO::FETCH_ASSOC)) {
														echo '<tr>';
                                                        echo '<td>' . strtoupper(h($donnees1['code_monture'] ?? '')) . '</td>';
                                                        echo '<td>' . h($donnees1['marque_nom'] ?? '') . '</td>';
                                                        echo '<td>' . h($donnees1['couleur'] ?? '') . '</td>';
                                                    echo '<td>' . h($donnees1['monture_pour'] ?? '') . '</td>';
                                                    echo '<td>' . h($donnees1['prix'] ?? '') . '</td>';
                                                    echo '<td>' . h(date('d/m/Y', strtotime((string)($donnees1['date_creation'] ?? 'now')))) . '</td>';
														echo '</tr>';
													}
												?>
												</tbody>
										</table>
									</div>
								</section>
							</div>
						</div>
                    </div>
                    <br>

				</section>
			</div>

        <!-- Modal ajout monture -->
        <div class="modal fade" id="addMontureModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form class="form-horizontal" novalidate="novalidate" method="POST" action="<?php echo h($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
                        <div class="modal-header">
                            <h5 class="modal-title">Ajouter une monture</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="ajouter_monture" value="1">
                            <div class="row form-group pb-3">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="col-form-label">N° de la monture</label>
                                        <input type="text" pattern="^\S*$" oninput="this.value = this.value.replace(/\s/g, '')" name="codeMonture" class="form-control" required value="<?php echo isset($_POST['codeMonture']) ? h($_POST['codeMonture']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="col-form-label">Du bon de livraison N°</label>
                                        <select class="form-control populate" name="nolivraison" required>
                                            <option value="">---- choisir ----</option>
                                            <?php
                                            $type = $bdd->prepare('SELECT no_livraison FROM approvisionnements WHERE quantite > 0 AND statut = ? AND (type_commande = ? OR type_commande = ?)');
                                            $type->execute(['livré', 'montures', 'montures et lentilles']);
                                            $selectedLiv = isset($_POST['nolivraison']) ? trim((string)$_POST['nolivraison']) : '';
                                            while ($livraison = $type->fetch(PDO::FETCH_ASSOC)) {
                                                $no = (string)($livraison['no_livraison'] ?? '');
                                                if ($no === '') { continue; }
                                                $sel = ($selectedLiv !== '' && $selectedLiv === $no) ? ' selected' : '';
                                                echo '<option value="' . h($no) . '"' . $sel . '>' . h($no) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="col-form-label">De la marque</label>
                                        <select class="form-control populate" name="marque" required>
                                            <option value="">---- choisir ----</option>
                                            <?php
                                            $type = $bdd->prepare('SELECT id_marque, marque FROM marques WHERE status = 1 ORDER BY marque');
                                            $type->execute();
                                            $selectedMarque = isset($_POST['marque']) ? (int)$_POST['marque'] : 0;
                                            while ($model = $type->fetch(PDO::FETCH_ASSOC)) {
                                                $id = (int)($model['id_marque'] ?? 0);
                                                $label = (string)($model['marque'] ?? '');
                                                if ($id <= 0 || $label === '') { continue; }
                                                $sel = ($selectedMarque > 0 && $selectedMarque === $id) ? ' selected' : '';
                                                echo '<option value="' . h($id) . '"' . $sel . '>' . h($label) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="col-form-label">Couleur</label>
                                        <input type="text" name="couleur" class="form-control" required value="<?php echo isset($_POST['couleur']) ? h($_POST['couleur']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="col-form-label">Type de la monture</label>
                                        <?php $selType = isset($_POST['typeMonture']) ? (string)$_POST['typeMonture'] : ''; ?>
                                        <select class="form-control populate" name="typeMonture">
                                            <option value="">---- choisir ----</option>
                                            <option value="Adulte Homme" <?php echo ($selType === 'Adulte Homme') ? 'selected' : ''; ?>>Adulte Homme</option>
                                            <option value="Adulte Femme" <?php echo ($selType === 'Adulte Femme') ? 'selected' : ''; ?>>Adulte Femme</option>
                                            <option value="Enfant" <?php echo ($selType === 'Enfant') ? 'selected' : ''; ?>>Enfant</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="col-form-label">Prix de la monture</label>
                                        <input type="number" step="50000" name="prixmonture" class="form-control" value="<?php echo isset($_POST['prixmonture']) ? h($_POST['prixmonture']) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                            <button class="btn btn-primary" type="submit">Ajouter la monture</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include '../PUBLIC/footer.php';?>

        <script>
                $(document).ready(function() {
                    if (!$.fn.DataTable.isDataTable('#datatable-default')) {
                        $('#datatable-default').DataTable({
                            paging: true,
                            pageLength: 10
                        });
                    }
                });
                function exportAllDataToExcel(tableID, filename = '') {
                    var table = $('#' + tableID).DataTable();
                    table.page.len(-1).draw();
                    exportTableToExcel(tableID, filename);
                    table.page.len(10).draw();
                }
                function exportTableToExcel(tableID, filename = ''){
                    var downloadLink;
                    var dataType = 'application/vnd.ms-excel';
                    var tableSelect = document.getElementById(tableID);
                    var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
                    filename = filename ? filename + '.xls' : 'liste_des_produits_en_stock.xls';
                    downloadLink = document.createElement("a");
                    document.body.appendChild(downloadLink);
                    downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
                    downloadLink.download = filename;
                    downloadLink.click();
                    document.body.removeChild(downloadLink);
                }
            </script>
        <script>
            (function () {
                var shouldOpen = <?php echo $openAddModal ? 'true' : 'false'; ?>;
                if (!shouldOpen) return;
                document.addEventListener('DOMContentLoaded', function () {
                    try {
                        var el = document.getElementById('addMontureModal');
                        if (!el || typeof bootstrap === 'undefined') return;
                        var modal = new bootstrap.Modal(el);
                        modal.show();
                    } catch (e) {
                        // noop
                    }
                });
            })();
        </script>