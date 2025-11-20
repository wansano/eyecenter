<?php
session_start();
require_once('../public/connect.php');
require_once('../public/header.php');
require_once('../public/fonction.php');
require_once('fonctions_logistique.php');

if(isset($_POST['action']) && $_POST['action']==='traiter' && isset($_POST['id'])) { marquerAlerteTraitee($bdd,(int)$_POST['id']); }
$alertes=listerAlertes($bdd,false);
?>
<body>
<section class="body">
<?php include '../public/navbarmenu.php'; ?>
<div class="inner-wrapper">
<section role="main" class="content-body">
<header class="page-header"><h2>Alertes de stock</h2></header>
<table class="table table-sm table-bordered">
<thead><tr><th>Date</th><th>Article</th><th>Type</th><th>Message</th><th>Statut</th><th>Action</th></tr></thead>
<tbody>
<?php foreach($alertes as $al): ?>
<tr class="<?php echo $al['traitee']? 'table-success':'table-danger'; ?>">
<td><?php echo htmlspecialchars($al['date_alerte']); ?></td>
<td><?php echo htmlspecialchars($al['article']); ?></td>
<td><?php echo htmlspecialchars($al['type']); ?></td>
<td><?php echo htmlspecialchars($al['message']); ?></td>
<td><?php echo $al['traitee']? 'Traité':'À traiter'; ?></td>
<td>
<?php if(!$al['traitee']): ?>
<form method="post" style="display:inline;">
<input type="hidden" name="action" value="traiter">
<input type="hidden" name="id" value="<?php echo (int)$al['id_alerte']; ?>">
<button class="btn btn-xs btn-primary">Marquer traité</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</section>
</div>
<?php include('../public/footer.php'); ?>
</section>
</body>
</html>