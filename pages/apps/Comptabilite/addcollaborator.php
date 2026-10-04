<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();
$errors = 0; $existe = 0;

function collaborateurExiste($bdd, $email, $telephone) {
    $req = $bdd->prepare('SELECT COUNT(*) FROM collaborateurs WHERE email=? OR telephone=?');
    $req->execute([$email, $telephone]);
    return $req->fetchColumn() > 0;
}

if (isset($_POST['ajouter'])) {
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $collaborateur = trim($_POST['collaborateur'] ?? '');
    $fonction = trim($_POST['fonction'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $collaborateurpour = $_POST['collaborateurpour'] ?? '';

    if (empty($email) || empty($telephone) || empty($collaborateur) || empty($fonction) || empty($adresse) || empty($collaborateurpour)) {
        $errors = 3;
    } elseif (collaborateurExiste($bdd, $email, $telephone)) {
        $existe = 1;
    } else {
        $req = $bdd->prepare('INSERT INTO collaborateurs (collaborateur_pour, nom_collaborateur, fonction, telephone, email, adresse) VALUES(?,?,?,?,?,?)');
        $req->execute([$collaborateurpour, $collaborateur, $fonction, $telephone, $email, $adresse]);
        $errors = 2;
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
                <h2>Ajouter un nouveau collaborateur</h2>
            </header>
            <!-- start: page -->
            <div class="col-md-12">
                <section class="card">
                    <div class="card-body">
                        <?php
                        if ($errors == 2) {
                            echo '<div class="alert alert-success"><strong>Succès</strong> <br/><li>Le collaborateur a été ajouté avec succès !</li></div>';
                        }
                        if ($errors == 3) {
                            echo '<div class="alert alert-danger"><li>Ajout du collaborateur non effectué, merci de vérifier les informations saisies</li>.</div>';
                        }
                        if ($existe == 1) {
                            echo '<div class="alert alert-warning"><li>Le collaborateur existe déjà dans le système.</li></div>';
                        }
                        ?>
                        <form class="form-horizontal" novalidate="novalidate" method="POST" action="addcollaborator.php" enctype="multipart/form-data">
                            <input type="hidden" name="ajouter" value="1">
                            <div class="row form-group pb-3">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label class="col-form-label" for="formGroupExampleInput">Type de collaborateur</label>
                                        <select class="form-control populate" name="collaborateurpour" required>
                                            <option value="1">Clinique</option>
                                            <option value="2">Boutique</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="col-form-label" for="formGroupExampleInput">Nom du collaborateur</label>
                                        <input type="text" name="collaborateur" class="form-control" placeholder="" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="col-form-label" for="formGroupExampleInput">Fonction du collaborateur</label>
                                        <input type="text" name="fonction" class="form-control" placeholder="" required>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label class="col-form-label" for="formGroupExampleInput">Téléphone</label>
                                        <input type="text" class="form-control" name="telephone" placeholder="" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="col-form-label" for="formGroupExampleInput">Courriel</label>
                                        <input type="email" class="form-control" name="email" placeholder="" required>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="col-form-label" for="formGroupExampleInput">Adresse</label>
                                        <input type="text" class="form-control" name="adresse" placeholder="" required>
                                    </div>
                                </div>
                            </div>
                            <footer class="card-footer text-end">
                                <button class="btn btn-primary" type="submit">Ajouter le collaborateur</button>
                            </footer>
                        </form>
                    </div>
                </section>
            </div>
            <!-- end: page -->
        </section>
    </div>
    <?php include('../PUBLIC/footer.php'); ?>
