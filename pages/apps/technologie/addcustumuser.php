<?php
include('../PUBLIC/connect.php');
session_start();
$errors=0; $existe=0;

if (isset($_POST['ajouter'])) {

$req1 = $bdd->prepare('SELECT * FROM users WHERE email=?');
$req1->execute(array($_POST['email']));
while ($dta = $req1->fetch()) 
{
  $existe=1;
}

if ($existe==0) 
    {
        $plage_connexion = isset($_POST['plage_connexion']) ? trim((string)$_POST['plage_connexion']) : '';
        $req = $bdd->prepare('INSERT INTO users (pseudo, email, type, id_service, date_engagement, responsable, plage_connexion, mdp) VALUES (?,?,?,?,?,?,?,?)');
        $req->execute(array($_POST['pseudo'], $_POST['email'], $_POST['type'], $_POST['service'], $_POST['date_engagement'],  $_POST['responsable'], $plage_connexion, password_hash($_POST['mdp'], PASSWORD_DEFAULT)));
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
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <section class="card">
                        <div class="card-body">
                            <?php
                                if ($errors==2) {
                                echo '
                                    <div class="alert alert-success">
                                    <strong>Succès</strong> <br/>  
                                    <li>Ajout de l\'utilisateur éffectué avec succès. Veuillez l\'activer</li> 
                                    </div>
                                    ';
                                }
                                if ($existe==1) {
                                echo '
                                    <div class="alert alert-danger">
                                        <li>Enregistrement non effectué, cet utilisateur existe déjà.</li>
                                    </div>
                                    ';
                                }
                            ?>
                            <form class="form-horizontal" novalidate="novalidate" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
                                <input type="hidden" name="ajouter" value="1">
                                <div class="row form-group pb-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Prénoms et Nom</label>
                                            <?php
                                                if ($types=="comptabilité")
                                                { echo'
                                                    <select name="pseudo" data-plugin-selectTwo class="form-control populate" required>';
                                                    $employe = $bdd->prepare('SELECT * FROM employes WHERE id_service=?');
                                                    $employe -> execute([$id_service]);
                                                        while ($employes = $employe->fetch(PDO::FETCH_ASSOC))
                                                            { 
                                                                echo ' <option value="'.$employes['id_employe'].'">'.$employes['nom_employe'].'</option>';
                                                            }
                                                    echo '</select>';
                                                } else {
                                                    echo '<input type="text" class="form-control" name="pseudo" id="formGroupExampleInput" placeholder="" required>';
                                                }
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Courriel</label>
                                            <input type="email" class="form-control" name="email" id="formGroupExampleInput" placeholder="" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Type d'utilisateur</label>
                                            <select class="form-control populate" name="type">
                                                <optgroup>
                                                <?php if ($types=="comptabilité") { echo '
                                                    <option value="caisse">Caisse Médical</option>
                                                    <option value="caisseoptic">Caisse Optique</option>';}
                                                    else { echo '
                                                    <option>------ Choisir ------</option>
                                                    <option value="administrateur">Administrateur</option>
                                                    <option value="caisse">Caisse</option>
                                                    <option value="caisseoptic">Caisse Optique</option>
                                                    <option value="modeservices">Medecin traitant</option>
                                                    <option value="acceuil">Acceuil</option>
                                                    <option value="comptabilité">Comptable</option>
                                                    <option value="superviseur">Superviseur</option>'; } ?>
                                                </optgroup>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Date engagement</label>
                                            <input type="date" name="date_engagement" class="form-control" placeholder="" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Affecté au service</label>
                                            <select name="service" data-plugin-selectTwo class="form-control populate">
                                                <optgroup>
                                                <?php
                                                    // Afficher les cellules (services) depuis l'organigramme : cohérent avec service($id_service)
                                                    $hasDepartement = false;
                                                    $statusCol = null;
                                                    try { $bdd->query('SELECT departement FROM organigramme LIMIT 1'); $hasDepartement = true; } catch (PDOException $e) { $hasDepartement = false; }
                                                    try { $bdd->query('SELECT status FROM organigramme LIMIT 1'); $statusCol = 'status'; } catch (PDOException $e) {}
                                                    if ($statusCol === null) {
                                                        try { $bdd->query('SELECT statuts FROM organigramme LIMIT 1'); $statusCol = 'statuts'; } catch (PDOException $e) {}
                                                    }

                                                    $cols = 'id_organigramme, celulle' . ($hasDepartement ? ', departement' : '');
                                                    $sql = 'SELECT ' . $cols . ' FROM organigramme';
                                                    if ($statusCol !== null) {
                                                        $sql .= ' WHERE ' . $statusCol . ' != 3';
                                                    }
                                                    $sql .= ' ORDER BY ' . ($hasDepartement ? 'departement, ' : '') . 'celulle';

                                                    $stmt = $bdd->prepare($sql);
                                                    $stmt->execute();
                                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                        $idOrg = (int) ($row['id_organigramme'] ?? 0);
                                                        $celulle = (string) ($row['celulle'] ?? '');
                                                        $departement = $hasDepartement ? (string) ($row['departement'] ?? '') : '';
                                                        if ($idOrg <= 0 || $celulle === '') { continue; }

                                                        $label = ($departement !== '') ? ($departement . ' - ' . $celulle) : $celulle;
                                                        echo '<option value="' . htmlspecialchars((string)$idOrg, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
                                                    }
                                                ?>
                                                </optgroup>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Responsable</label>
                                            <select name="responsable" data-plugin-selectTwo class="form-control populate" data-plugin-options="{minimumInputLength : 2 }" required>;
                                                <optgroup>
                                                            <?php
                                                            if ($types=="comptabilité"){
                                                                $type = $bdd->prepare('SELECT * FROM users WHERE status=1 AND id=? ');
                                                                $type -> execute(array($_SESSION['auth']));
                                                                while ($responsable = $type->fetch())
                                                                {   
                                                                    echo '<option value="'.$responsable['id'].'">'.$responsable['pseudo'].'</option>';
                                                                }
                                                            } else {
                                                                echo '<option>Choisir</option> <option value="0">Pouvoir du gérant</option>';
                                                                $type = $bdd->prepare('SELECT * FROM users WHERE status=1 ORDER BY id');
                                                                $type -> execute();
                                                                while ($responsable = $type->fetch())
                                                                {       
                                                                    echo '<option value="'.$responsable['id'].'">'.$responsable['pseudo'].'</option>';
                                                                }
                                                            }
															?>
                                                </optgroup>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="formGroupExampleInput">Mot de passe</label>
                                            <input type="password" class="form-control" name="mdp" id="formGroupExampleInput" required>
                                        </div>
                                    </div>
									<div class="col-md-12">
										<div class="form-group">
											<label class="col-form-label" for="plage_connexion">Plage de connexion</label>
											<input type="text" class="form-control" name="plage_connexion" id="plage_connexion" placeholder="ex: lundi:caisse;mardi:secretariat" value="<?php echo isset($_POST['plage_connexion']) ? htmlspecialchars((string)$_POST['plage_connexion'], ENT_QUOTES, 'UTF-8') : ''; ?>">
											<small class="text-muted">Format: jour:type séparés par ';' (ex: lundi:caisse;mardi:acceuil)</small>
										</div>
									</div>
                                </div>
                                <?php
                                    if ($types=="comptabilité"){
                                        echo '  <div class="col-md-12">
                                                    <p>Code confidentiel par defaut : HonneteteJURE</p>
                                                </div>';
                                    }
                                ?>
                                <footer class="card-footer text-end">
                                    <button class="btn btn-primary" type="submit" name="ajouter">Ajouter l'utlisateur</button>
                                </footer>
                            </form>
                    </section>
                </div>
            </div>
        <!-- end: page -->
    </section>
    </div>
    <?php include('../PUBLICfooter.php');?>
   