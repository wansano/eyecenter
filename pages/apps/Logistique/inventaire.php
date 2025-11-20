<?php
session_start();
require_once('../public/connect.php');
require_once('../public/header.php');
require_once('../public/fonction.php');
require_once('fonctions_logistique.php');

$feedback = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action']==='add') {
    $nom = trim($_POST['nom'] ?? '');
    if ($nom === '') { $feedback = 'Nom requis.'; }
    else {
        $id = ajouterArticle($bdd, $_POST);
        $feedback = $id>0 ? 'Article ajouté.' : 'Erreur ajout.';
    }
}
$articles = listerArticles($bdd);
$alertes = articlesSousSeuil($bdd);
?>
<body>
<section class="body">
<?php include '../public/navbarmenu.php'; ?>
<div class="inner-wrapper">
<section role="main" class="content-body">
<header class="page-header"><h2>Inventaire Logistique</h2></header>
<?php if($feedback): ?><div class="alert alert-info"><?php echo htmlspecialchars($feedback); ?></div><?php endif; ?>
<div class="row">
<div class="col-lg-5">
<form method="post" class="card card-body">
<input type="hidden" name="action" value="add" />
<div class="mb-2"><label>Nom *</label><input type="text" name="nom" class="form-control" required></div>
<div class="mb-2"><label>Catégorie</label><input type="text" name="categorie" class="form-control"></div>
<div class="mb-2"><label>Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
<div class="mb-2"><label>Unité</label><input type="text" name="unite" class="form-control" value="pcs"></div>
<div class="mb-2"><label>Stock initial</label><input type="number" name="stock_initial" class="form-control" value="0" min="0"></div>
<div class="mb-2"><label>Seuil alerte</label><input type="number" name="seuil_alerte" class="form-control" value="0" min="0"></div>
<div class="mb-2"><label>Prix achat</label><input type="number" step="0.01" name="prix_achat" class="form-control" value="0"></div>
<div class="mb-2"><label>Prix vente</label><input type="number" step="0.01" name="prix_vente" class="form-control" value="0"></div>
<button class="btn btn-primary">Ajouter</button>
</form>
</div>
<div class="col-lg-7">
<h4>Liste des articles</h4>
<table class="table table-sm table-striped">
<thead><tr><th>Code</th><th>Nom</th><th>Catégorie</th><th>Stock</th><th>Seuil</th><th>Valeur</th></tr></thead>
<tbody>
<?php foreach($articles as $a): ?>
<tr <?php if($a['stock_actuel'] <= $a['seuil_alerte'] && $a['seuil_alerte']>0) echo 'class="table-warning"'; ?>>
<td><?php echo htmlspecialchars($a['code']); ?></td>
<td><?php echo htmlspecialchars($a['nom']); ?></td>
<td><?php echo htmlspecialchars($a['categorie']); ?></td>
<td><?php echo (int)$a['stock_actuel']; ?></td>
<td><?php echo (int)$a['seuil_alerte']; ?></td>
<td><?php echo number_format($a['stock_actuel'] * $a['prix_achat'],2,',',' '); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php if(count($alertes)>0): ?>
<div class="alert alert-warning">Articles sous seuil: <?php echo count($alertes); ?></div>
<?php endif; ?>
</div>
</div>
</section>
</div>
<?php include('../public/footer.php'); ?>
</section>
</body>
</html>