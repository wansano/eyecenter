<?php
session_start();
require_once('../public/connect.php');
require_once('../public/header.php');
require_once('../public/fonction.php');
require_once('fonctions_logistique.php');

$message='';
$fournisseurs = listerFournisseurs($bdd);
$articles = listerArticles($bdd);

if(isset($_POST['action']) && $_POST['action']==='create' && isset($_POST['id_fournisseur'])) {
    $idF=(int)$_POST['id_fournisseur'];
    if($idF>0){ $idCmd = creerCommandeAchat($bdd,$idF,$_POST['date_prevue']??null); $message='Commande créée (#'.$idCmd.')'; }
}
if(isset($_POST['action']) && $_POST['action']==='addline' && isset($_POST['id_commande'])) {
    $idC=(int)$_POST['id_commande']; $idA=(int)$_POST['id_article']; $q=(int)$_POST['quantite']; $pu=(float)$_POST['prix_unitaire'];
    if($idC>0 && $idA>0 && $q>0){ ajouterLigneCommande($bdd,$idC,$idA,$q,$pu); $message='Ligne ajoutée'; }
}
if(isset($_POST['action']) && $_POST['action']==='receive' && isset($_POST['id_commande'])) {
    $idC=(int)$_POST['id_commande']; if(recevoirCommande($bdd,$idC)) $message='Commande reçue'; else $message='Erreur réception';
}
$commandes = listerCommandes($bdd);
?>
<body>
<section class="body">
<?php include '../public/navbarmenu.php'; ?>
<div class="inner-wrapper">
<section role="main" class="content-body">
<header class="page-header"><h2>Commandes d'achat</h2></header>
<?php if($message): ?><div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<div class="row">
<div class="col-lg-4">
<h5>Nouvelle commande</h5>
<form method="post" class="card card-body mb-3">
<input type="hidden" name="action" value="create">
<div class="mb-2"><label>Fournisseur *</label><select name="id_fournisseur" class="form-control" required><option value="">Choisir</option><?php foreach($fournisseurs as $f){echo '<option value="'.$f['id_fournisseur'].'">'.htmlspecialchars($f['nom']).'</option>'; } ?></select></div>
<div class="mb-2"><label>Date réception prévue</label><input type="date" name="date_prevue" class="form-control"></div>
<button class="btn btn-primary">Créer</button>
</form>
<h5>Ajouter ligne</h5>
<form method="post" class="card card-body mb-3">
<input type="hidden" name="action" value="addline">
<div class="mb-2"><label>ID Commande *</label><input type="number" name="id_commande" class="form-control" required></div>
<div class="mb-2"><label>Article *</label><select name="id_article" class="form-control" required><option value="">Choisir</option><?php foreach($articles as $a){echo '<option value="'.$a['id_article'].'">'.htmlspecialchars($a['nom']).'</option>'; } ?></select></div>
<div class="mb-2"><label>Quantité *</label><input type="number" name="quantite" class="form-control" min="1" required></div>
<div class="mb-2"><label>Prix unitaire</label><input type="number" step="0.01" name="prix_unitaire" class="form-control" value="0"></div>
<button class="btn btn-secondary">Ajouter ligne</button>
</form>
<h5>Réception commande</h5>
<form method="post" class="card card-body">
<input type="hidden" name="action" value="receive">
<div class="mb-2"><label>ID Commande *</label><input type="number" name="id_commande" class="form-control" required></div>
<button class="btn btn-success">Réceptionner</button>
</form>
</div>
<div class="col-lg-8">
<h4>Commandes</h4>
<table class="table table-sm table-striped">
<thead><tr><th>ID</th><th>Numéro</th><th>Fournisseur</th><th>Date</th><th>Statut</th><th>Total estimé</th><th>Prévue</th><th>Réception</th></tr></thead>
<tbody>
<?php foreach($commandes as $c): ?>
<tr>
<td><?php echo (int)$c['id_commande']; ?></td>
<td><?php echo htmlspecialchars($c['numero']); ?></td>
<td><?php echo htmlspecialchars($c['fournisseur']); ?></td>
<td><?php echo htmlspecialchars($c['date_commande']); ?></td>
<td><?php echo htmlspecialchars($c['statut']); ?></td>
<td><?php echo number_format($c['total_estime'],2,',',' '); ?></td>
<td><?php echo htmlspecialchars($c['date_reception_prevue']); ?></td>
<td><?php echo htmlspecialchars($c['date_reception_effective']); ?></td>
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