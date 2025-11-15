<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();
$errors = 0;
$existe = 0;

// Récupération de l'ID du produit à éditer
$id = $_GET['id'] ?? null;
if (!$id) {
    echo '<div class="alert alert-danger">Aucun produit sélectionné.</div>';
    exit;
}

// Récupérer les infos du produit
$req = $bdd->prepare('SELECT * FROM produits WHERE id_produit=?');
$req->execute([$id]);
$produit = $req->fetch(PDO::FETCH_ASSOC);
if (!$produit) {
    echo '<div class="alert alert-danger">Produit introuvable.</div>';
    exit;
}

if (isset($_POST['modifier'])) {
    // Vérifier unicité du code produit (hors ce produit)
    $req1 = $bdd->prepare('SELECT COUNT(*) FROM produits WHERE code_produit=? AND id_produit!=?');
    $req1->execute([$_POST['codeproduit'], $id]);
    $existe = $req1->fetchColumn() > 0 ? 1 : 0;

    if ($existe == 0) {
        $req = $bdd->prepare('UPDATE produits SET code_produit=?, id_model=?, couleur=?, description=? WHERE id_produit=?');
        $req->execute([
            $_POST['codeproduit'],
            $_POST['model'],
            $_POST['couleur'],
            $_POST['description'],
            $id
        ]);
        $errors = 2;
        // On ne touche pas au stock ici
        // Redirection ou message de succès
    }
}

include('../PUBLIC/header.php');
?>
<body>
<section class="body">
    <?php require('../PUBLIC/navbarmenu.php'); ?>
    <div class="inner-wrapper">
        <section role="main" class="content-body">
            <header class="page-header">
                <h2>Modifier une monture</h2>
            </header>
            <div class="col-md-12">
                <section class="card">
                    <div class="card-body">
                        <?php
                        if ($errors == 2) {
                            echo '<div class="alert alert-success"><strong>Succès</strong> <br/><li>La monture a été modifiée avec succès !</li></div>';
                        }
                        if ($existe == 1) {
                            echo '<div class="alert alert-warning"><li>Le code monture existe déjà dans le système.</li></div>';
                        }
                        ?>
                        <form class="form-horizontal" novalidate="novalidate" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?id=<?php echo $id; ?>" enctype="multipart/form-data">
                            <input type="hidden" name="modifier" value="1">
                            <div class="row form-group pb-3">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="col-form-label" for="formGroupExampleInput">Code de la monture</label>
                                        <input type="text" pattern="^\S*$" oninput="this.value = this.value.replace(/\s/g, '')" name="codeproduit" class="form-control" required value="<?php echo htmlspecialchars($produit['code_produit']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="col-form-label" for="formGroupExampleInput">Choisir la marque</label>
                                        <select class="form-control populate" name="model" required>
                                            <option>---- choisir ----</option>
                                            <?php $type = $bdd->prepare('SELECT * FROM model_produits WHERE status=1');
                                            $type->execute();
                                            while ($model = $type->fetch()) {
                                                $actif = $model['status'];
                                                $selected = ($produit['id_model'] == $model['id_model']) ? 'selected' : '';
                                                if ($actif == 1) {
                                                    echo '<option value="' . $model['id_model'] . '" ' . $selected . '>' . $model['model'] . '</option>';
                                                }
                                            } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="col-form-label" for="formGroupExampleInput">Couleur</label>
                                        <input type="text" name="couleur" class="form-control" required value="<?php echo htmlspecialchars($produit['couleur']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="col-form-label" for="formGroupExampleInput">Description de la monture</label>
                                        <textarea class="form-control" rows="5" name="description" id="formGroupExampleInput"><?php echo htmlspecialchars($produit['description']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <footer class="card-footer text-end">
                                <button class="btn btn-primary" type="submit" name="modifier">Enregistrer les modifications</button>
                            </footer>
                        </form>
                    </div>
                </section>
            </div>
        </section>
    </div>
</section>
<?php include('../PUBLIC/footer.php'); ?>
