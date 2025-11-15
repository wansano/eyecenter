<?php
include('../PUBLIC/connect.php');
session_start();
$errors=0; $existe=0;

if (isset($_POST['addtraitement'])) {

$req1 = $bdd->prepare('SELECT * FROM traitements WHERE nom=? AND model=?');
$req1->execute(array($_POST['nom_type'], $_POST['model']));
while ($dta = $req1->fetch()) 
{
  $existe=1;
}

if ($existe==0) 
    {

        $req = $bdd->prepare('INSERT INTO traitements (nom_type, montant, model) VALUES (?,?,?)');
        $req->execute(array($_POST['nom_type'], $_POST['montant'], $_POST['model']));
        $errors=2;
    }
}


include('../PUBLIC/header.php');
?>

<body>
    <section class="body">

        <?php include('../PUBLIC/navbarmenu.php'); ?>

        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Ajout d'un utilisateur</h2>

                    <div class="right-wrapper text-end">
                        <ol class="breadcrumbs">
                            <li>
                                <a href="welcome.php?profil=ecv2">
                                    <i class="bx bx-home-alt"></i>
                                </a>
                            </li>

                            <li><span>Acceuil</span></li>

                        </ol>

                        <a class="sidebar-right-toggle" data-open="sidebar-right"></a>
                    </div>
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <section class="card">
                        <header class="card-header">
                            <h2 class="card-title">Information sur le traitement</h2>
                        </header>
                        <div class="card-body">
                            <?php       
                                        if ($existe==1) {
                                        echo '
                                            <div class="alert alert-info">
                                            <strong>Info</strong> <br/>  
                                            <li>Ce traitement existe déjà pour ce service.</li> 
                                            </div>
                                            ';
                                        }
                                        if ($errors==2) {
                                        echo '
                                            <div class="alert alert-success">
                                            <strong>Succès</strong> <br/>  
                                            <li>Ajout traitement éffectué avec succès.</li> 
                                            </div>
                                            ';
                                                }
                                        if ($errors==3) {
                                        echo '
                                            <div class="alert alert-danger">
                                                <li>Enregistrement non effectué, merci de vérifier les informations saisies si elles sont correctes.</li>
                                            </div>
                                            ';}
                                    ?>
                            <form class="form-horizontal" validate="validate" method="POST"
                                action="addtreatement.php" enctype="multipart/form-data">
                                <input type="hidden" name="addtraitement" value="1">
                                <div class="row form-group pb-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Nom du traitement</label>
                                            <input type="text" name="nom_type" class="form-control" placeholder=""
                                                required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Montant</label>
                                            <input type="number" class="form-control" name="montant"
                                                id="formGroupExampleInput" placeholder="" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Service concerné</label>
                                            <select name="model" data-plugin-selectTwo class="form-control populate">
                                                <optgroup>
                                                    <?php
                                                    $type = $bdd->prepare('SELECT * FROM services WHERE status=1');
                                                    $type -> execute();
                                                    while ($services = $type->fetch())
                                                    {   
                                                            echo '<option value="'.$services['id_service'].'">'.$services['nom_service'].'</option>';
                                                    }
                                                    ?>
                                                </optgroup>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            <footer class="card-footer text-end">
                                <button class="btn btn-primary" type="submit">ajouter le traitement</button>
                            </footer>
                        </form>
                    </section>
                </div>
        <!-- end: page -->
    </section>
    </div>
    <?php include('../PUBLIC/footer.php');?>
    