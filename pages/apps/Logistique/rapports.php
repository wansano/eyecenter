<?php
session_start();
require_once('../public/connect.php');
require_once('../public/header.php');
require_once('../public/fonction.php');
require_once('fonctions_logistique.php');

$valeur = valeurStockActuel($bdd);
$rotation = rotationArticles($bdd,30);
$alertes = articlesSousSeuil($bdd);
?>
<body>
<section class="body">
<?php include '../public/navbarmenu.php'; ?>
<div class="inner-wrapper">
<section role="main" class="content-body">
<header class="page-header d-flex justify-content-between align-items-center">
		<h2 class="m-0">Rapports Logistique</h2>
</header>
<div class="row">
<div class="col-lg-4">
<div class="card card-body">
<h5>Valeur stock</h5>
<p><strong><?php echo number_format($valeur,2,',',' '); ?> <?php echo htmlspecialchars($devise ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></p>
<h6>Articles sous seuil: <?php echo count($alertes); ?></h6>
</div>
<a class="btn btn-sm btn-secondary" href="_rapport_logistique.php?jours=30" target="_blank">Imprimer PDF</a>
</div>
<div class="col-lg-8">
<h5>Rotation (en 30 jours)</h5>
<table class="table table-sm">
<thead><tr><th>Article</th><th>Mouvements</th><th>Stock actuel</th></tr></thead>
<tbody>
<?php foreach($rotation as $r): ?>
<tr>
<td><?php echo htmlspecialchars($r['nom']); ?></td>
<td><?php echo (int)$r['mouvements']; ?></td>
<td><?php echo (int)$r['stock_actuel']; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</section>
</div>
<?php include('../public/footer.php'); ?>
</section>
</body>
</html>