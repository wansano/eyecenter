<?php
include('../PUBLIC/connect.php');
include('../PUBLIC/fonction.php');
session_start();

// Feedback après POST (PRG pattern)
$errors = isset($_GET['ok']) ? (int) $_GET['ok'] : 0;

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
?>
	<body>
		<section class="body">

			<?php require('../PUBLIC/navbarmenu.php'); ?>

			<div class="inner-wrapper">
				<section role="main" class="content-body">
					<header class="page-header">
						<h2>
						<?php 
							if ($types=="comptabilité") { echo 'Liste des caissiers.';} 
							else { echo 'Liste des utilisateurs du systeme.'; }
						?>
						</h2>

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
						<div class="row">
							<div class="col">
								<section class="card">
									<div class="card-body">
                                    <?php 
											if ($types=="comptabilité") { echo 'Liste des caissiers.';} 
											else { echo 'Liste des utilisateurs du systeme.
                                                <br>
                                                    <a href="addcustumuser.php" type="button" class="btn btn-sm btn-success"><i class="fa fa-plus"></i> ajouter un utilisateur</a><br/> 
                                                <br>'; }

                                            if ($errors==2) {
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
                                        ?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
													<th>ID</th>
													<th>UTILISATEUR</th>
													<th>TYPE</th>
													<th>EMAIL</th>
													<th>SERVICE</th>
													<th>STATUS</th>
												</tr>
											</thead>
											<tbody>
											<?php

                                                $reponse1 = $bdd->prepare('SELECT id, pseudo, type, email, id_service, status FROM users ORDER BY id');
                                                $reponse1->execute();
                                                while ($donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC))
                                                    { $status = (int) $donnees1['status'];

                                                    echo' <tr>';
													if ($status!=3){
                                                    echo'
                                                        <td>EC'.htmlspecialchars($donnees1['id'], ENT_QUOTES, 'UTF-8').'</td>
                                                        <td>'.htmlspecialchars($donnees1['pseudo'], ENT_QUOTES, 'UTF-8').'</td>
                                                        <td>'.htmlspecialchars($donnees1['type'], ENT_QUOTES, 'UTF-8').'</td>
                                                        <td>'.htmlspecialchars($donnees1['email'], ENT_QUOTES, 'UTF-8').'</td>
                                                        <td>'.htmlspecialchars(service((int)$donnees1['id_service']), ENT_QUOTES, 'UTF-8').'</td>
                                                    <td>';
                                                    if ($status==0) { echo '
                                                        <form action="listeutilisateurs.php?id='.$donnees1['id'].'" method="post">
                                                        <input type="hidden" name="activer" value="'.$donnees1['id'].'">
                                                        <button type="submit" class="btn btn-sm btn-warning"><i class="fa fa-unlock-alt"></i> activé</button>
                                                        </form>

                                                        <form action="listeutilisateurs.php?id='.$donnees1['id'].'" method="post">
                                                        <input type="hidden" name="supprimer" value="'.$donnees1['id'].'">
                                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-times"></i> supprimer</button>
                                                        </form>

                                                        <a href="edit_user.php?id_user='.$donnees1['id'].'" type="button" class="btn btn-sm btn-info"><i class="fa fa-edit"></i> modifier</a>
                                                        ';}
                                                    
                                                        if ($status==1) {
                                                        echo' 
                                                        <form action="listeutilisateurs.php?id='.$donnees1['id'].'" method="post">
                                                        <input type="hidden" name="desactiver" value="'.$donnees1['id'].'">
                                                        <button type="submit" class="btn btn-sm btn-success"><i class="fa fa-lock"></i> désactivé</button>
                                                        </form>';
                                                    };
                                                    echo '
                                                    </td>
                                                    </tr>';
                                                        }
													}
                                            ?>
											</tbody>
										</table>
									</div>
								</section>
							</div>
						</div>
				    </section>
			    </div>
            <?php include('../PUBLIC/footer.php');?>