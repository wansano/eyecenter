<?php
session_start();
require_once('../public/connect.php');
require_once('../public/header.php');
require_once('../public/fonction.php');
require_once('fonctions_logistique.php');

$msg='';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='add') {
 $nom=trim($_POST['nom']??'');
 if($nom==='') $msg='Nom fournisseur requis'; else { ajouterFournisseur($bdd, $_POST); $msg='Fournisseur ajouté'; }
}
$fournisseurs=listerFournisseurs($bdd);
?>
<body>
<section class="body">
<?php include '../public/navbarmenu.php'; ?>
<div class="inner-wrapper">
<section role="main" class="content-body">
<header class="page-header"><h2>Fournisseurs</h2></header>
<?php if($msg): ?><div class="alert alert-info"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
<div class="row">
<div class="col-lg-4">
<form method="post" class="card card-body">
<input type="hidden" name="action" value="add" />
<div class="mb-2"><label>Nom *</label><input type="text" name="nom" class="form-control" required></div>
<div class="mb-2"><label>Contact</label><input type="text" name="contact" class="form-control"></div>
<div class="mb-2"><label>Téléphone</label><input type="text" name="telephone" class="form-control"></div>
<div class="mb-2"><label>Email</label><input type="email" name="email" class="form-control"></div>
<div class="mb-2"><label>Adresse</label><textarea name="adresse" class="form-control" rows="2"></textarea></div>
<button class="btn btn-primary">Ajouter</button>
</form>
</div>
<div class="col-lg-8">
<h4>Liste</h4>
<table class="table table-sm table-bordered">
<thead><tr><th>Nom</th><th>Contact</th><th>Téléphone</th><th>Email</th></tr></thead>
<tbody>
<?php foreach($fournisseurs as $f): ?>
<tr>
<td><?php echo htmlspecialchars($f['nom']); ?></td>
<td><?php echo htmlspecialchars($f['contact']); ?></td>
<td><?php echo htmlspecialchars($f['telephone']); ?></td>
<td><?php echo htmlspecialchars($f['email']); ?></td>
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