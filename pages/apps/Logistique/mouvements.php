<?php
session_start();
require_once('../public/connect.php');
require_once('../public/header.php');
require_once('../public/fonction.php');
require_once('fonctions_logistique.php');

$msg='';
$articles=listerArticles($bdd);
if(isset($_POST['action']) && $_POST['action']==='mvt') {
    $idA=(int)($_POST['id_article']??0); $type=$_POST['type']??''; $q=(int)($_POST['quantite']??0);
    if($idA>0 && in_array($type,['entrée','sortie','ajustement']) && $q>0) {
        enregistrerMouvementStock($bdd,$idA,$type,'manuel','-', $q);
        $msg='Mouvement enregistré';
    } else { $msg='Données invalides'; }
}
$mouvements=listerMouvements($bdd,300);
?>
<body>
<section class="body">
<?php include '../public/navbarmenu.php'; ?>
<div class="inner-wrapper">
<section role="main" class="content-body">
<header class="page-header"><h2>Mouvements de stock</h2></header>
<?php if($msg): ?><div class="alert alert-info"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
<div class="row">
<div class="col-lg-4">
<form method="post" class="card card-body">
<input type="hidden" name="action" value="mvt">
<div class="mb-2"><label>Article *</label><select name="id_article" class="form-control" required><option value="">Choisir</option><?php foreach($articles as $a){echo '<option value="'.$a['id_article'].'">'.htmlspecialchars($a['nom']).'</option>'; } ?></select></div>
<div class="mb-2"><label>Type *</label><select name="type" class="form-control" required><option value="entrée">Entrée</option><option value="sortie">Sortie</option><option value="ajustement">Ajustement</option></select></div>
<div class="mb-2"><label>Quantité *</label><input type="number" name="quantite" min="1" class="form-control" required></div>
<button class="btn btn-primary">Valider</button>
</form>
</div>
<div class="col-lg-8">
<table class="table table-sm table-hover">
<thead><tr><th>Date</th><th>Article</th><th>Type</th><th>Qty</th><th>Avant</th><th>Après</th><th>Origine</th></tr></thead>
<tbody>
<?php foreach($mouvements as $m): ?>
<tr>
<td><?php echo htmlspecialchars($m['date_mouvement']); ?></td>
<td><?php echo htmlspecialchars($m['article']); ?></td>
<td><?php echo htmlspecialchars($m['type']); ?></td>
<td><?php echo (int)$m['quantite']; ?></td>
<td><?php echo (int)$m['stock_avant']; ?></td>
<td><?php echo (int)$m['stock_apres']; ?></td>
<td><?php echo htmlspecialchars($m['origine']); ?></td>
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