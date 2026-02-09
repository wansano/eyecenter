<?php
include('../PUBLIC/connect.php');
include('../PUBLIC/fonction.php');
session_start();

// Feedback après POST (PRG pattern)
$errors = isset($_GET['ok']) ? (int) $_GET['ok'] : 0;

// Helper simple pour les attributs HTML
function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Enregistrer modification utilisateur (modal)
try {
    if (isset($_POST['edit_user_save'])) {
        $editId = isset($_POST['id_user']) ? (int) $_POST['id_user'] : 0;
        $pseudo = isset($_POST['pseudo']) ? trim((string) $_POST['pseudo']) : '';
        $email = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
        $idService = isset($_POST['id_service']) ? (int) $_POST['id_service'] : 0;
        $dateEngagement = isset($_POST['date_engagement']) ? trim((string) $_POST['date_engagement']) : '';
        $responsable = isset($_POST['responsable']) ? (int) $_POST['responsable'] : 0;
        $plageConnexion = isset($_POST['plage_connexion']) ? trim((string) $_POST['plage_connexion']) : '';
        $newPassword = isset($_POST['mdp']) ? (string) $_POST['mdp'] : '';

        if ($editId <= 0 || $pseudo === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=6');
            exit;
        }

        // Vérifier que l'utilisateur existe (et récupérer l'email courant pour garder la liaison employé)
        $stmt = $bdd->prepare('SELECT id, email FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$editId]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existingUser) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=7');
            exit;
        }

        $oldEmail = trim((string)($existingUser['email'] ?? ''));

        // Email unique (sauf pour l'utilisateur courant)
        $stmt = $bdd->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $stmt->execute([$email, $editId]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=8');
            exit;
        }

        // Mise à jour
        $bdd->beginTransaction();

        $stmt = $bdd->prepare('UPDATE users SET pseudo = ?, email = ?, id_service = ?, date_engagement = ?, responsable = ?, plage_connexion = ? WHERE id = ?');
        $stmt->execute([$pseudo, $email, $idService, $dateEngagement, $responsable, $plageConnexion, $editId]);

        // Garder la liaison employé<->user via email
        if ($oldEmail !== '' && strcasecmp($oldEmail, $email) !== 0) {
            $stmt = $bdd->prepare('UPDATE employes SET email = ? WHERE email = ?');
            $stmt->execute([$email, $oldEmail]);
        }

        // Synchroniser la hiérarchie: users.responsable -> employes.superieur_hierarchique
        // Règle: si cet utilisateur a une fiche employé (par email), alors on renseigne son supérieur via l'employé correspondant au responsable.
        $stmt = $bdd->prepare('SELECT id_employe FROM employes WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $empId = (int)($stmt->fetchColumn() ?: 0);

        if ($empId > 0) {
            $superieurEmployeId = null;
            if ($responsable > 0) {
                $stmt = $bdd->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$responsable]);
                $responsableEmail = trim((string)($stmt->fetchColumn() ?? ''));
                if ($responsableEmail !== '' && filter_var($responsableEmail, FILTER_VALIDATE_EMAIL)) {
                    $stmt = $bdd->prepare('SELECT id_employe FROM employes WHERE email = ? LIMIT 1');
                    $stmt->execute([$responsableEmail]);
                    $tmp = (int)($stmt->fetchColumn() ?: 0);
                    if ($tmp > 0) {
                        $superieurEmployeId = $tmp;
                    }
                }
            }

            // Éviter l'auto-référence
            if ($superieurEmployeId !== null && $superieurEmployeId === $empId) {
                $superieurEmployeId = null;
            }

            $stmt = $bdd->prepare('UPDATE employes SET superieur_hierarchique = ? WHERE id_employe = ?');
            $stmt->execute([$superieurEmployeId, $empId]);
        }

        if (trim($newPassword) !== '') {
            $stmt = $bdd->prepare('UPDATE users SET mdp = ? WHERE id = ?');
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $editId]);
        }

        $bdd->commit();
        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=5');
        exit;
    }

    // Ajouter un utilisateur (modal)
    if (isset($_POST['add_user_save'])) {
        $pseudo = isset($_POST['pseudo']) ? trim((string) $_POST['pseudo']) : '';
        $email = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
        $type = isset($_POST['type']) ? trim((string) $_POST['type']) : '';
        $idService = isset($_POST['id_service']) ? (int) $_POST['id_service'] : 0;
        $dateEngagement = isset($_POST['date_engagement']) ? trim((string) $_POST['date_engagement']) : '';
        $responsable = isset($_POST['responsable']) ? (int) $_POST['responsable'] : 0;
        $plageConnexion = isset($_POST['plage_connexion']) ? trim((string) $_POST['plage_connexion']) : '';
        $password = isset($_POST['mdp']) ? (string) $_POST['mdp'] : '';

        if ($pseudo === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $idService <= 0 || $dateEngagement === '' || trim($password) === '') {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=10');
            exit;
        }

        // Email unique
        $stmt = $bdd->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=9');
            exit;
        }

        // Insertion (status par défaut = 0 => à activer)
        $stmt = $bdd->prepare('INSERT INTO users (pseudo, email, type, id_service, date_engagement, responsable, plage_connexion, mdp) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $pseudo,
            $email,
            ($type === '' ? null : $type),
            $idService,
            $dateEngagement,
            $responsable,
            ($plageConnexion === '' ? null : $plageConnexion),
            password_hash($password, PASSWORD_DEFAULT),
        ]);

        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=1');
        exit;
    }
} catch (PDOException $e) {
    if (isset($bdd) && $bdd->inTransaction()) {
        $bdd->rollBack();
    }
    error_log('listeutilisateurs.php edit_user error: ' . $e->getMessage());
    $errors = 1;
}

// Actions
try {
    $action = null;
    $userId = 0;
    $newStatus = null;
    $okCode = 0;

    if (isset($_POST['activer'])) {
        $action = 'activer';
        $userId = (int) $_POST['activer'];
        $newStatus = 1;
        $okCode = 2;
    } elseif (isset($_POST['desactiver'])) {
        $action = 'desactiver';
        $userId = (int) $_POST['desactiver'];
        $newStatus = 0;
        $okCode = 3;
    } elseif (isset($_POST['supprimer'])) {
        $action = 'supprimer';
        $userId = (int) $_POST['supprimer'];
        $newStatus = 3;
        $okCode = 4;
    }

    if ($action !== null && $userId > 0 && $newStatus !== null) {
        $stmt = $bdd->prepare('UPDATE users SET status = ? WHERE id = ?');
        $stmt->execute([$newStatus, $userId]);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=' . $okCode);
        exit;
    }
} catch (PDOException $e) {
    error_log('listeutilisateurs.php update error: ' . $e->getMessage());
    $errors = 1;
}


include('../PUBLIC/header.php');

// Préparer les listes pour le modal (services + responsables)
$serviceOptionsHtml = '';
$responsableOptionsHtml = '';

// Profils autorisés pour la plage de connexion (stockés dans users.plage_connexion)
$plageProfiles = [
    'aucun' => 'Aucun',
    'caisse' => 'Caisse',
    'secretariat' => 'Secrétariat',
    'optalmologue' => 'Ophtalmologue',
    'optometriste' => 'Optométriste',
    'medecin' => 'Chirurgie',
    'logistique' => 'Logistique',
    'boutique' => 'Boutique',
    'comptabilite' => 'Comptabilité',
    'technologie' => 'Administrateur',
];

$joursConnexion = [
    'lundi' => 'Lundi',
    'mardi' => 'Mardi',
    'mercredi' => 'Mercredi',
    'jeudi' => 'Jeudi',
    'vendredi' => 'Vendredi',
    'samedi' => 'Samedi',
    'dimanche' => 'Dimanche',
];

try {
    // Services depuis organigramme (comme addcustumuser.php)
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
        $serviceOptionsHtml .= '<option value="' . h((string)$idOrg) . '">' . h($label) . '</option>';
    }

    // Responsables actifs
    $responsableOptionsHtml .= '<option value="0">Pouvoir du gérant</option>';
    $stmt = $bdd->prepare('SELECT id, pseudo FROM users WHERE status = 1 ORDER BY id');
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $responsableOptionsHtml .= '<option value="' . h((string)$row['id']) . '">' . h((string)$row['pseudo']) . '</option>';
    }
} catch (PDOException $e) {
    error_log('listeutilisateurs.php modal lists error: ' . $e->getMessage());
}
?>
	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>Liste des utilisateurs du systeme</h2>
					</header>

					<!-- start: page -->
					<div class="col-md-12">
						<div class="row">
							<div class="col">
								<section class="card">
									<div class="card-body">
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="fa fa-plus"></i> ajouter un utilisateur</button> <br><br>
                                        <?php    if ($errors==2) {
                                            echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Compte utilisateur activé avec succès.</li>
                                                </div>
                                                '; }
                                            if ($errors==3) {
                                                echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Compte utilisateur desactivé avec succès.</li>
                                                </div>
                                                '; }
                                            if ($errors==4) {
                                                echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Compte utilisateur supprimer avec succès.</li>
                                                </div>
                                                '; }

                                            if ($errors==5) {
                                                echo '
                                                <div class="alert alert-success">
                                                <li><strong>Succès !</strong>
                                                <br>Utilisateur modifié avec succès.</li>
                                                </div>
                                                '; }

                                            if ($errors==1) {
                                                echo '
                                                <div class="alert alert-danger">
                                                <li>Une erreur est survenue. Réessayez.</li>
                                                </div>
                                                '; }

                                            if ($errors==9) {
                                                echo '
                                                <div class="alert alert-danger">
                                                <li>Enregistrement non effectué : cet email est déjà utilisé.</li>
                                                </div>
                                                '; }

                                            if ($errors==10) {
                                                echo '
                                                <div class="alert alert-danger">
                                                <li>Enregistrement non effectué : champs invalides.</li>
                                                </div>
                                                '; }
                                            if ($errors==6) {
                                                echo '
                                                <div class="alert alert-danger">
                                                <li>Modification non effectuée : champs invalides.</li>
                                                </div>
                                                '; }
                                            if ($errors==7) {
                                                echo '
                                                <div class="alert alert-danger">
                                                <li>Utilisateur introuvable.</li>
                                                </div>
                                                '; }
                                            if ($errors==8) {
                                                echo '
                                                <div class="alert alert-danger">
                                                <li>Modification non effectuée : cet email est déjà utilisé.</li>
                                                </div>
                                                '; }
                                        ?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
													<th>ID</th>
													<th>UTILISATEUR</th>
													<th>EMAIL</th>
													<th>SERVICE</th>
													<th>STATUS</th>
												</tr>
											</thead>
											<tbody>
											<?php

                                                    $reponse1 = $bdd->prepare('SELECT id, pseudo, email, id_service, status, date_engagement, responsable, plage_connexion FROM users ORDER BY id');
                                                $reponse1->execute();
                                                while ($donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC))
                                                    { $status = (int) $donnees1['status'];

                                                    echo' <tr>';
													if ($status!=3){
                                                    echo'
                                                        <td>EC'.htmlspecialchars($donnees1['id'], ENT_QUOTES, 'UTF-8').'</td>
                                                        <td>'.htmlspecialchars($donnees1['pseudo'], ENT_QUOTES, 'UTF-8').'</td>
                                                        <td>'.htmlspecialchars($donnees1['email'], ENT_QUOTES, 'UTF-8').'</td>
                                                        <td>'.htmlspecialchars(service((int)$donnees1['id_service']), ENT_QUOTES, 'UTF-8').'</td>
                                                    <td>';
                                                    if ($status==0) { echo '
                                                        <div class="d-flex gap-1 flex-wrap align-items-center">
                                                            <form class="m-0" action="listeutilisateurs.php?id='.$donnees1['id'].'" method="post">
                                                                <button type="submit" name="activer" value="'.$donnees1['id'].'" class="btn btn-sm btn-warning"><i class="fa fa-unlock-alt"></i> activé</button>
                                                            </form>

                                                            <form class="m-0" action="listeutilisateurs.php?id='.$donnees1['id'].'" method="post">
                                                                <input type="hidden" name="supprimer" value="'.$donnees1['id'].'">
                                                                <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-times"></i> supprimer</button>
                                                            </form>

                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-info js-edit-user"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#editUserModal"
                                                                data-id="'.h($donnees1['id']).'"
                                                                data-pseudo="'.h($donnees1['pseudo']).'"
                                                                data-email="'.h($donnees1['email']).'"
                                                                data-service="'.h($donnees1['id_service']).'"
                                                                data-date_engagement="'.h($donnees1['date_engagement']).'"
                                                                data-responsable="'.h($donnees1['responsable']).'"
                                                                data-plage_connexion="'.h($donnees1['plage_connexion']).'"
                                                            ><i class="fa fa-edit"></i> modifier</button>
                                                        </div>
                                                        ';}
                                                    
                                                        if ($status==1) {
                                                        echo' 
                                                        <div class="d-flex gap-1 flex-wrap align-items-center">
                                                            <form class="m-0" action="listeutilisateurs.php?id='.$donnees1['id'].'" method="post">
                                                                <input type="hidden" name="desactiver" value="'.$donnees1['id'].'">
                                                                <button type="submit" class="btn btn-sm btn-success"><i class="fa fa-lock"></i> désactivé</button>
                                                            </form>
                                                        </div>';
                                                    };
                                                    echo '
                                                    </td>
                                                    </tr>';
                                                        }
													}
                                            ?>
											</tbody>
										</table>


                                        <!-- Modal ajout utilisateur -->
                                        <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <form method="POST" action="<?php echo h($_SERVER['PHP_SELF']); ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Ajouter un utilisateur</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="add_user_save" value="1">

                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="add_pseudo">Prénoms et Nom</label>
                                                                        <input type="text" class="form-control" name="pseudo" id="add_pseudo" required>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="add_email">Courriel</label>
                                                                        <input type="email" class="form-control" name="email" id="add_email" required>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-6">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="add_date_engagement">Date engagement</label>
                                                                        <input type="date" class="form-control" name="date_engagement" id="add_date_engagement" required>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="add_id_service">Affecté au service</label>
                                                                        <select class="form-control populate" name="id_service" id="add_id_service" required>
                                                                            <?php echo $serviceOptionsHtml; ?>
                                                                        </select>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-6">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="add_responsable">Responsable</label>
                                                                        <select class="form-control populate" name="responsable" id="add_responsable" required>
                                                                            <?php echo $responsableOptionsHtml; ?>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="add_type">Type d'utilisateur (optionnel)</label>
                                                                        <select class="form-control populate" name="type" id="add_type">
                                                                            <option value="">------ Choisir ------</option>
                                                                            <option value="administrateur">Administrateur</option>
                                                                            <option value="caisse">Caisse</option>
                                                                            <option value="caisseoptic">Caisse Optique</option>
                                                                            <option value="modeservices">Medecin traitant</option>
                                                                            <option value="acceuil">Acceuil</option>
                                                                            <option value="comptabilité">Comptable</option>
                                                                            <option value="superviseur">Superviseur</option>
                                                                            <option value="secretariat">Secrétariat</option>
                                                                        </select>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-6">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="add_mdp">Mot de passe</label>
                                                                        <input type="password" class="form-control" name="mdp" id="add_mdp" required>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-12">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="add_plage_connexion">Plage de connexion</label>
                                            <input type="hidden" class="form-control" name="plage_connexion" id="add_plage_connexion" value="">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th style="width:120px;">Jour</th>
                                                            <th>Profil autorisé</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($joursConnexion as $jourKey => $jourLabel): ?>
                                                            <tr>
                                                                <td><strong><?php echo h($jourLabel); ?></strong></td>
                                                                <td>
                                                                    <div class="d-flex flex-wrap gap-2">
                                                                        <?php foreach ($plageProfiles as $pValue => $pLabel): ?>
                                                                            <label class="me-2" style="white-space:nowrap;">
                                                                                <input type="radio" name="add_plage_<?php echo h($jourKey); ?>" value="<?php echo h($pValue); ?>" <?php echo $pValue === 'aucun' ? 'checked' : ''; ?> onchange="syncPlageConnexion('add')">&nbsp;<?php echo h($pLabel); ?>
                                                                        </label>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <small class="text-muted">Enregistré en base sous la forme : lundi:caisse;mardi:secretariat;... (ou aucun).</small>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                                                            <button type="submit" class="btn btn-primary">Ajouter</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>


                                        <!-- Modal édition utilisateur -->
                                        <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <form method="POST" action="<?php echo h($_SERVER['PHP_SELF']); ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Modifier l'utilisateur</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="edit_user_save" value="1">
                                                            <input type="hidden" name="id_user" id="edit_id_user" value="">

                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="edit_pseudo">Prénoms et Nom</label>
                                                                        <input type="text" class="form-control" name="pseudo" id="edit_pseudo" required>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="edit_email">Courriel</label>
                                                                        <input type="email" class="form-control" name="email" id="edit_email" required>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="edit_date_engagement">Date engagement</label>
                                                                        <input type="date" class="form-control" name="date_engagement" id="edit_date_engagement" required>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="edit_id_service">Affecté au service</label>
                                                                        <select class="form-control populate" name="id_service" id="edit_id_service" required>
                                                                            <?php echo $serviceOptionsHtml; ?>
                                                                        </select>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-6">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="edit_responsable">Responsable</label>
                                                                        <select class="form-control populate" name="responsable" id="edit_responsable" required>
                                                                            <?php echo $responsableOptionsHtml; ?>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="edit_mdp">Nouveau mot de passe (optionnel)</label>
                                                                        <input type="password" class="form-control" name="mdp" id="edit_mdp" placeholder="Laisser vide pour ne pas changer">
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-12">
                                                                    <div class="form-group pb-3">
                                                                        <label class="col-form-label" for="edit_plage_connexion">Plage de connexion</label>
                                            <input type="hidden" class="form-control" name="plage_connexion" id="edit_plage_connexion" value="">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th style="width:120px;">Jour</th>
                                                            <th>Profil autorisé</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($joursConnexion as $jourKey => $jourLabel): ?>
                                                            <tr>
                                                                <td><strong><?php echo h($jourLabel); ?></strong></td>
                                                                <td>
                                                                    <div class="d-flex flex-wrap gap-2">
                                                                        <?php foreach ($plageProfiles as $pValue => $pLabel): ?>
                                                                            <label class="me-2" style="white-space:nowrap;">
                                                                                <input type="radio" name="edit_plage_<?php echo h($jourKey); ?>" value="<?php echo h($pValue); ?>" <?php echo $pValue === 'aucun' ? 'checked' : ''; ?> onchange="syncPlageConnexion('edit')">&nbsp;<?php echo h($pLabel); ?>
                                                                        </label>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <small class="text-muted">Choisissez un profil par jour (ou aucun).</small>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                                                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
									</div>
								</section>
							</div>
						</div>
				    </section>
			    </div>

                    <script>
                    (function () {
                        function parsePlage(str) {
                            var out = {};
                            if (!str) return out;
                            String(str).split(';').forEach(function (part) {
                                part = String(part || '').trim();
                                if (!part) return;
                                var idx = part.indexOf(':');
                                if (idx <= 0) return;
                                var day = part.slice(0, idx).trim().toLowerCase();
                                var val = part.slice(idx + 1).trim();
                                if (!day) return;
                                out[day] = val;
                            });
                            return out;
                        }

                        function setRadio(prefix, day, value) {
                            var name = prefix + '_plage_' + day;
                            var radios = document.querySelectorAll('input[type="radio"][name="' + name + '"]');
                            var found = false;
                            radios.forEach(function (r) {
                                if (r.value === value) {
                                    r.checked = true;
                                    found = true;
                                }
                            });
                            if (!found) {
                                radios.forEach(function (r) {
                                    if (r.value === 'aucun') r.checked = true;
                                });
                            }
                        }

                        window.syncPlageConnexion = function (prefix) {
                            var days = ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche'];
                            var parts = [];
                            days.forEach(function (day) {
                                var name = prefix + '_plage_' + day;
                                var checked = document.querySelector('input[type="radio"][name="' + name + '"]:checked');
                                var val = checked ? checked.value : 'aucun';
                                if (!val) val = 'aucun';
                                parts.push(day + ':' + val);
                            });
                            var hidden = document.getElementById(prefix + '_plage_connexion');
                            if (hidden) hidden.value = parts.join(';');
                        };

                        function applyPlage(prefix, str) {
                            var map = parsePlage(str);
                            ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche'].forEach(function (day) {
                                setRadio(prefix, day, map[day] || 'aucun');
                            });
                            window.syncPlageConnexion(prefix);
                        }

                        function setValue(id, value) {
                            var el = document.getElementById(id);
                            if (!el) return;
                            el.value = (value === undefined || value === null) ? '' : value;
                        }

                        function clearAddModal() {
                            setValue('add_pseudo', '');
                            setValue('add_email', '');
                            setValue('add_date_engagement', '');
                            setValue('add_id_service', '');
                            setValue('add_responsable', '0');
                            setValue('add_type', '');
                            setValue('add_mdp', '');
							applyPlage('add', 'lundi:aucun;mardi:aucun;mercredi:aucun;jeudi:aucun;vendredi:aucun;samedi:aucun;dimanche:aucun');
                        }

                        document.addEventListener('click', function (e) {
                            var btn = e.target.closest('.js-edit-user');
                            if (!btn) return;
                            setValue('edit_id_user', btn.getAttribute('data-id'));
                            setValue('edit_pseudo', btn.getAttribute('data-pseudo'));
                            setValue('edit_email', btn.getAttribute('data-email'));
                            setValue('edit_date_engagement', btn.getAttribute('data-date_engagement'));
                            setValue('edit_id_service', btn.getAttribute('data-service'));
                            setValue('edit_responsable', btn.getAttribute('data-responsable'));
							applyPlage('edit', btn.getAttribute('data-plage_connexion') || '');
                            setValue('edit_mdp', '');
                        });

                        var addModal = document.getElementById('addUserModal');
                        if (addModal) {
                            addModal.addEventListener('show.bs.modal', clearAddModal);
                        }

						// Init valeurs par défaut au chargement
						try {
							applyPlage('add', 'lundi:aucun;mardi:aucun;mercredi:aucun;jeudi:aucun;vendredi:aucun;samedi:aucun;dimanche:aucun');
						} catch (e) {}
                    })();
                    </script>
            <?php include('../PUBLIC/footer.php');?>