<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$ok = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;

// AJAX: comptes disponibles (pour paiement)
if (isset($_GET['ajax_comptes'])) {
    header('Content-Type: application/json; charset=UTF-8');
    try {
        $st = $bdd->prepare('SELECT id_compte, nom_compte, solde FROM comptes WHERE status = 1 ORDER BY nom_compte');
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        echo json_encode(['success' => true, 'comptes' => $rows]);
        exit;
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Impossible de charger les comptes.']);
        exit;
    }
}

// AJAX: enregistrer un paiement fournisseur (avec preuve)
if (isset($_GET['ajax_payer'])) {
    header('Content-Type: application/json; charset=UTF-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
        exit;
    }

    $idF = isset($_POST['id_fournisseur']) ? (int)$_POST['id_fournisseur'] : 0;
    $payeA = trim((string)($_POST['paye_a'] ?? ''));
    $compte = isset($_POST['compte']) ? (int)$_POST['compte'] : 0;
    $montant = isset($_POST['montant']) ? (float)$_POST['montant'] : 0.0;
    $dateAjout = trim((string)($_POST['date_ajout'] ?? ''));
    $motif = trim((string)($_POST['motif'] ?? ''));

    if ($idF <= 0 || $compte <= 0 || $montant <= 0 || $dateAjout === '' || $motif === '' || $payeA === '') {
        echo json_encode(['success' => false, 'message' => 'Merci de renseigner tous les champs obligatoires.']);
        exit;
    }

    // Upload preuve (obligatoire)
    $preuvePathRel = null;
    if (!isset($_FILES['preuve']) || !is_array($_FILES['preuve']) || ($_FILES['preuve']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        echo json_encode(['success' => false, 'message' => 'Merci d’ajouter la preuve de paiement.']);
        exit;
    }

    if (($_FILES['preuve']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l’upload de la preuve de paiement.']);
        exit;
    }

        $maxBytes = 10 * 1024 * 1024; // 10MB
        if (($_FILES['preuve']['size'] ?? 0) > $maxBytes) {
            echo json_encode(['success' => false, 'message' => 'Fichier trop volumineux (max 10MB).']);
            exit;
        }

        $original = (string)($_FILES['preuve']['name'] ?? 'preuve');
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($ext, $allowed, true)) {
            echo json_encode(['success' => false, 'message' => 'Format de preuve non autorisé (pdf, jpg, png).']);
            exit;
        }

        $uploadDirAbs = __DIR__ . '/uploads/paiements_fournisseurs';
        if (!is_dir($uploadDirAbs)) {
            @mkdir($uploadDirAbs, 0775, true);
        }

        $safeName = 'preuve_fournisseur_' . $idF . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destAbs = $uploadDirAbs . '/' . $safeName;
        if (!move_uploaded_file((string)$_FILES['preuve']['tmp_name'], $destAbs)) {
            echo json_encode(['success' => false, 'message' => 'Impossible d’enregistrer la preuve de paiement.']);
            exit;
        }

        $preuvePathRel = 'uploads/paiements_fournisseurs/' . $safeName;

    try {
        // Vérifier fournisseur
        $stF = $bdd->prepare('SELECT id_fournisseur, credit FROM fournisseur_produit WHERE id_fournisseur = ? LIMIT 1');
        $stF->execute([$idF]);
        $f = $stF->fetch(PDO::FETCH_ASSOC);
        if (!$f) {
            if ($preuvePathRel) @unlink(__DIR__ . '/' . $preuvePathRel);
            echo json_encode(['success' => false, 'message' => 'Fournisseur introuvable.']);
            exit;
        }

        // Anti-doublon (même fournisseur, compte, date, montant)
        $existe = false;
        try {
            $stX = $bdd->prepare('SELECT id_paie FROM paiements_fournisseurs WHERE id_fournisseur = ? AND compte = ? AND date_ajout = ? AND montant_paye = ? LIMIT 1');
            $stX->execute([$idF, $compte, $dateAjout, $montant]);
            $existe = (bool)$stX->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $existe = false;
        }

        if ($existe) {
            if ($preuvePathRel) @unlink(__DIR__ . '/' . $preuvePathRel);
            echo json_encode(['success' => false, 'message' => 'Ce paiement semble déjà enregistré.']);
            exit;
        }

        // Vérifier compte
        $stC = $bdd->prepare('SELECT solde, credit FROM comptes WHERE id_compte = ? LIMIT 1');
        $stC->execute([$compte]);
        $c = $stC->fetch(PDO::FETCH_ASSOC);
        if (!$c) {
            if ($preuvePathRel) @unlink(__DIR__ . '/' . $preuvePathRel);
            echo json_encode(['success' => false, 'message' => 'Compte invalide.']);
            exit;
        }

        $soldeCompte = (float)($c['solde'] ?? 0);
        if ($soldeCompte <= 0 || $montant > $soldeCompte) {
            if ($preuvePathRel) @unlink(__DIR__ . '/' . $preuvePathRel);
            echo json_encode(['success' => false, 'message' => 'Solde compte insuffisant.']);
            exit;
        }

        $bdd->beginTransaction();
        try {
            $payeur = $_SESSION['auth'] ?? null;

            $idPaie = 0;

            // Insertion paiement (preuve en colonne `fichier`, placée après `motif`)
            $stI = $bdd->prepare('INSERT INTO paiements_fournisseurs (id_fournisseur, paye_a, montant_paye, compte, motif, fichier, date_ajout, payeur) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stI->execute([$idF, $payeA, $montant, $compte, $motif, $preuvePathRel, $dateAjout, $payeur]);
            $idPaie = (int)$bdd->lastInsertId();

            // Mise à jour compte (crédit)
            $creditCompte = (float)($c['credit'] ?? 0);
            $creditCompte += $montant;
            $stU1 = $bdd->prepare('UPDATE comptes SET credit = ? WHERE id_compte = ?');
            $stU1->execute([$creditCompte, $compte]);

            // Mise à jour fournisseur (crédit)
            $creditF = (float)($f['credit'] ?? 0);
            $creditF += $montant;
            try {
                $stU2 = $bdd->prepare('UPDATE fournisseur_produit SET credit = ? WHERE id_fournisseur = ?');
                $stU2->execute([$creditF, $idF]);
            } catch (Throwable $e) {
                // ignore
            }

            $bdd->commit();

            $bonUrl = 'bondepaiementfournisseur.php?paiement=' . urlencode((string)$idPaie);
            $preuveUrl = $preuvePathRel ? $preuvePathRel : null;
            echo json_encode([
                'success' => true,
                'message' => 'Paiement enregistré avec succès.',
                'id_paie' => $idPaie,
                'bon_url' => $bonUrl,
                'preuve_url' => $preuveUrl,
            ]);
            exit;
        } catch (Throwable $e) {
            $bdd->rollBack();
            if ($preuvePathRel) @unlink(__DIR__ . '/' . $preuvePathRel);
            echo json_encode(['success' => false, 'message' => 'Erreur lors de l’enregistrement du paiement.']);
            exit;
        }
    } catch (Throwable $e) {
        if ($preuvePathRel) @unlink(__DIR__ . '/' . $preuvePathRel);
        echo json_encode(['success' => false, 'message' => 'Erreur lors du traitement.']);
        exit;
    }
}

// AJAX: situation fournisseur (facturation/paiements)
if (isset($_GET['ajax_situation'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $id = isset($_GET['id_fournisseur']) ? (int)$_GET['id_fournisseur'] : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Fournisseur invalide.']);
        exit;
    }

    try {
        $fournisseur = null;
        $resume = [
            'debit' => 0,
            'credit' => 0,
            'solde' => 0,
            'status' => null,
        ];

        // Résumé depuis fournisseur_produit (si colonnes existantes)
        try {
            $st = $bdd->prepare('SELECT id_fournisseur, fournisseur, type_fournisseur, responsable, telephone, email, adresse, debit, credit, solde, status FROM fournisseur_produit WHERE id_fournisseur = ? LIMIT 1');
            $st->execute([$id]);
            $fournisseur = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            // fallback si certaines colonnes (debit/credit/solde) n'existent pas
            $st = $bdd->prepare('SELECT id_fournisseur, fournisseur, type_fournisseur, responsable, telephone, email, adresse, status FROM fournisseur_produit WHERE id_fournisseur = ? LIMIT 1');
            $st->execute([$id]);
            $fournisseur = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if (!$fournisseur) {
            echo json_encode(['success' => false, 'message' => 'Fournisseur introuvable.']);
            exit;
        }

        $resume['status'] = isset($fournisseur['status']) ? (int)$fournisseur['status'] : null;

        $debit = array_key_exists('debit', (array)$fournisseur) ? (float)($fournisseur['debit'] ?? 0) : null;
        $credit = array_key_exists('credit', (array)$fournisseur) ? (float)($fournisseur['credit'] ?? 0) : null;
        $solde = array_key_exists('solde', (array)$fournisseur) ? (float)($fournisseur['solde'] ?? 0) : null;

        // Si debit/credit/solde non disponibles, recalcul depuis les tables sources
        if ($debit === null) {
            try {
                $stSumD = $bdd->prepare('SELECT COALESCE(SUM(montant_total), 0) AS total_debit FROM approvisionnements WHERE id_fournisseur = ?');
                $stSumD->execute([$id]);
                $rowD = $stSumD->fetch(PDO::FETCH_ASSOC) ?: [];
                $debit = (float)($rowD['total_debit'] ?? 0);
            } catch (Throwable $e) {
                $debit = 0.0;
            }
        }

        if ($credit === null) {
            try {
                $stSumC = $bdd->prepare('SELECT COALESCE(SUM(montant_paye), 0) AS total_credit FROM paiements_fournisseurs WHERE id_fournisseur = ?');
                $stSumC->execute([$id]);
                $rowC = $stSumC->fetch(PDO::FETCH_ASSOC) ?: [];
                $credit = (float)($rowC['total_credit'] ?? 0);
            } catch (Throwable $e) {
                $credit = 0.0;
            }
        }

        if ($solde === null) {
            $solde = $debit - $credit;
        }

        $resume['debit'] = $debit;
        $resume['credit'] = $credit;
        $resume['solde'] = $solde;

        // Derniers paiements (avec preuve en colonne `fichier`)
        $paiements = [];
        try {
            $stP = $bdd->prepare('SELECT id_paie, paye_a, montant_paye, compte, motif, date_ajout, payeur, fichier AS preuve FROM paiements_fournisseurs WHERE id_fournisseur = ? ORDER BY id_paie DESC LIMIT 20');
            $stP->execute([$id]);
            $paiements = $stP->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            try {
                $stP = $bdd->prepare('SELECT id_paie, paye_a, montant_paye, compte, motif, date_ajout, payeur FROM paiements_fournisseurs WHERE id_fournisseur = ? ORDER BY id_paie DESC LIMIT 20');
                $stP->execute([$id]);
                $paiements = $stP->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e2) {
                $paiements = [];
            }
        }

        // Derniers approvisionnements / commandes
        $appros = [];
        try {
            $stA = $bdd->prepare('SELECT id_appro, no_commande, date_commande, no_livraison, date_livraison, montant_total, statut FROM approvisionnements WHERE id_fournisseur = ? ORDER BY id_appro DESC LIMIT 20');
            $stA->execute([$id]);
            $appros = $stA->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $appros = [];
        }

        echo json_encode([
            'success' => true,
            'fournisseur' => [
                'id_fournisseur' => (int)($fournisseur['id_fournisseur'] ?? $id),
                'fournisseur' => (string)($fournisseur['fournisseur'] ?? ''),
                'type_fournisseur' => (string)($fournisseur['type_fournisseur'] ?? ''),
                'responsable' => (string)($fournisseur['responsable'] ?? ''),
                'telephone' => (string)($fournisseur['telephone'] ?? ''),
                'email' => (string)($fournisseur['email'] ?? ''),
                'adresse' => (string)($fournisseur['adresse'] ?? ''),
                'status' => (int)($fournisseur['status'] ?? 0),
            ],
            'resume' => $resume,
            'paiements' => $paiements,
            'approvisionnements' => $appros,
        ]);
        exit;
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => "Erreur lors du chargement de la situation."]);
        exit;
    }
}

function updateFournisseurStatus(PDO $bdd, int $id, int $status): bool
{
    $st = $bdd->prepare('UPDATE fournisseur_produit SET status = ? WHERE id_fournisseur = ?');
    return $st->execute([$status, $id]);
}

function insertFournisseur(PDO $bdd, array $data): int
{
    $st = $bdd->prepare(
        'INSERT INTO fournisseur_produit (fournisseur, type_fournisseur, responsable, telephone, email, adresse, status) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $st->execute([
        $data['fournisseur'],
        $data['type_fournisseur'],
        $data['responsable'],
        $data['telephone'],
        $data['email'],
        $data['adresse'],
        (int)$data['status'],
    ]);
    return (int)$bdd->lastInsertId();
}

function updateFournisseur(PDO $bdd, int $id, array $data): bool
{
    $st = $bdd->prepare(
        'UPDATE fournisseur_produit '
        . 'SET fournisseur = ?, type_fournisseur = ?, responsable = ?, telephone = ?, email = ?, adresse = ? '
        . 'WHERE id_fournisseur = ? AND status <> 3'
    );
    return $st->execute([
        $data['fournisseur'],
        $data['type_fournisseur'],
        $data['responsable'],
        $data['telephone'],
        $data['email'],
        $data['adresse'],
        $id,
    ]);
}

// PRG: traiter les actions puis rediriger (évite resubmit au refresh)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['ajouter_fournisseur'])) {
            $fournisseur = trim((string)($_POST['fournisseur'] ?? ''));
            $type = trim((string)($_POST['type_fournisseur'] ?? ''));
            $responsable = trim((string)($_POST['responsable'] ?? ''));
            $telephone = trim((string)($_POST['telephone'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $adresse = trim((string)($_POST['adresse'] ?? ''));

            if ($fournisseur === '') {
                header('Location: listedesfournisseurs.php?ok=0');
                exit;
            }

            insertFournisseur($bdd, [
                'fournisseur' => $fournisseur,
                'type_fournisseur' => $type,
                'responsable' => $responsable,
                'telephone' => $telephone,
                'email' => $email,
                'adresse' => $adresse,
                'status' => 1,
            ]);

            header('Location: listedesfournisseurs.php?ok=1');
            exit;
        }

        if (isset($_POST['modifier_fournisseur'])) {
            $id = (int)($_POST['id_fournisseur'] ?? 0);
            $fournisseur = trim((string)($_POST['fournisseur'] ?? ''));
            $type = trim((string)($_POST['type_fournisseur'] ?? ''));
            $responsable = trim((string)($_POST['responsable'] ?? ''));
            $telephone = trim((string)($_POST['telephone'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $adresse = trim((string)($_POST['adresse'] ?? ''));

            if ($id <= 0 || $fournisseur === '') {
                header('Location: listedesfournisseurs.php?ok=0');
                exit;
            }

            updateFournisseur($bdd, $id, [
                'fournisseur' => $fournisseur,
                'type_fournisseur' => $type,
                'responsable' => $responsable,
                'telephone' => $telephone,
                'email' => $email,
                'adresse' => $adresse,
            ]);

            header('Location: listedesfournisseurs.php?ok=5');
            exit;
        }

        if (isset($_POST['activer'])) {
            $id = (int)$_POST['activer'];
            if ($id > 0) updateFournisseurStatus($bdd, $id, 1);
            header('Location: listedesfournisseurs.php?ok=2');
            exit;
        }

        if (isset($_POST['desactiver'])) {
            $id = (int)$_POST['desactiver'];
            if ($id > 0) updateFournisseurStatus($bdd, $id, 0);
            header('Location: listedesfournisseurs.php?ok=3');
            exit;
        }

        if (isset($_POST['supprimer'])) {
            $id = (int)$_POST['supprimer'];
            if ($id > 0) updateFournisseurStatus($bdd, $id, 3);
            header('Location: listedesfournisseurs.php?ok=4');
            exit;
        }
    } catch (Throwable $e) {
        // fallback silencieux, on affiche une alerte générique
        header('Location: listedesfournisseurs.php?ok=0');
        exit;
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
						<h2>Liste des fournisseurs </h2>
					</header>

					<!-- start: page -->
					<div class="col-md-12">
						<div class="row">
							<div class="col">
								<section class="card">
									<div class="card-body">
										<div class="mb-3 text-end">
											<button type="button" class="btn btn-primary" id="btnOpenAddFournisseur"><i class="fa fa-plus"></i> Ajouter un fournisseur</button>
										</div>
                                        <?php if ($ok === 2): ?>
                                            <div class="alert alert-success">
                                                <strong>Succès !</strong><br>Compte fournisseur activé avec succès.
                                            </div>
                                        <?php elseif ($ok === 1): ?>
                                            <div class="alert alert-success">
                                                <strong>Succès !</strong><br>Fournisseur ajouté avec succès.
                                            </div>
                                        <?php elseif ($ok === 3): ?>
                                            <div class="alert alert-success">
                                                <strong>Succès !</strong><br>Compte du fournisseur désactivé avec succès.
                                            </div>
                                        <?php elseif ($ok === 5): ?>
                                            <div class="alert alert-success">
                                                <strong>Succès !</strong><br>Fournisseur modifié avec succès.
                                            </div>
                                        <?php elseif ($ok === 4): ?>
                                            <div class="alert alert-warning">
                                                <strong>Succès !</strong><br>Compte fournisseur archivé avec succès.
                                            </div>
                                        <?php elseif ($ok === 0 && isset($_GET['ok'])): ?>
                                            <div class="alert alert-danger">
                                                <strong>Erreur</strong><br>Impossible d'effectuer l'opération.
                                            </div>
                                        <?php endif; ?>
										<table class="table table-bordered table-striped mb-0" id="datatable-default">
											<thead>
												<tr>
													<th>ID</th>
													<th>FOURNISSEUR</th>
													<th>TYPE</th>
													<th>TELEPHONE</th>
													<th>COURRIEL</th>
                                                    <th>ADRESSE</th>
                                                    <th>STATUS</th>
                                                    <th>ACTION</th>
												</tr>
											</thead>
											<tbody>
											<?php
                                                $st = $bdd->prepare('SELECT id_fournisseur, fournisseur, type_fournisseur, responsable, telephone, email, adresse, status FROM fournisseur_produit WHERE status <> 3 ORDER BY id_fournisseur');
                                                $st->execute();
                                                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                                                foreach ($rows as $r) {
                                                    $id = (int)($r['id_fournisseur'] ?? 0);
                                                    $status = (int)($r['status'] ?? 1);
                                                    $adresseRaw = (string)($r['adresse'] ?? '');
                                                    $adresseAff = function_exists('adress') ? (adress($adresseRaw) ?: $adresseRaw) : $adresseRaw;

                                                    echo '<tr>';
                                                    echo '<td>ECFOUR' . h($id) . '</td>';
                                                    echo '<td>' . h($r['fournisseur'] ?? '') . '</td>';
                                                    echo '<td>' . h($r['type_fournisseur'] ?? '') . '</td>';
                                                    echo '<td>' . h($r['telephone'] ?? '') . '</td>';
                                                    echo '<td>' . h($r['email'] ?? '') . '</td>';
                                                    echo '<td>' . h($adresseAff) . '</td>';

                                                    if ($status === 1) {
                                                        echo '<td><span class="badge bg-success">Actif</span></td>';
                                                    } else {
                                                        echo '<td><span class="badge bg-secondary">Inactif</span></td>';
                                                    }

                                                    echo '<td>';
														if ($status === 0) {
                                                        echo '<form action="listedesfournisseurs.php" method="post" class="d-inline">';
                                                        echo '<input type="hidden" name="activer" value="' . h($id) . '">';
                                                        echo '<button type="submit" class="btn btn-sm btn-warning"><i class="fa fa-unlock-alt"></i> activer</button>';
                                                        echo '</form> ';

                                                        echo '<button type="button" class="btn btn-sm btn-primary me-1 js-situation-fournisseur" data-id="' . h($id) . '">'
                                                        . '<i class="fa fa-file-text-o"></i> situation</button> ';

                                                        echo '<button type="button" class="btn btn-sm btn-info me-1 js-edit-fournisseur" '
														. 'data-id="' . h($id) . '" '
														. 'data-fournisseur="' . h($r['fournisseur'] ?? '') . '" '
														. 'data-type="' . h($r['type_fournisseur'] ?? '') . '" '
														. 'data-responsable="' . h($r['responsable'] ?? '') . '" '
														. 'data-telephone="' . h($r['telephone'] ?? '') . '" '
														. 'data-email="' . h($r['email'] ?? '') . '" '
														. 'data-adresse="' . h($adresseAff) . '">' 
														. '<i class="fa fa-edit"></i> modifier</button> ';

                                                        echo '<form action="listedesfournisseurs.php" method="post" class="d-inline" onsubmit="return confirm(\'Archiver ce fournisseur ?\');">';
                                                        echo '<input type="hidden" name="supprimer" value="' . h($id) . '">';
                                                        echo '<button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-times"></i> archiver</button>';
                                                        echo '</form>';

                                                    } else {
                                                        echo '<button type="button" class="btn btn-sm btn-primary me-1 js-situation-fournisseur" data-id="' . h($id) . '">'
                                                        . '<i class="fa fa-file-text-o"></i> situation</button> ';

                                                        echo '<form action="listedesfournisseurs.php" method="post" class="d-inline">';
                                                        echo '<input type="hidden" name="desactiver" value="' . h($id) . '">';
                                                        echo '<button type="submit" class="btn btn-sm btn-success"><i class="fa fa-lock"></i> désactiver</button>';
                                                        echo '</form> ';
                                                    }
                                                    echo '</td>';
                                                    echo '</tr>';
                                                }
                                                ?>
											</tbody>
										</table>
									</div>
								</section>
							</div>
						</div>
                </div>
            </section>
        </div>

        <!-- Modal: Ajouter fournisseur -->
        <div class="modal fade" id="addFournisseurModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form method="post">
                        <div class="modal-header">
                            <h5 class="modal-title">Ajouter un fournisseur</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="ajouter_fournisseur" value="1">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Fournisseur</label>
                                    <input type="text" name="fournisseur" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Type</label>
                                    <input type="text" name="type_fournisseur" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Responsable</label>
                                    <input type="text" name="responsable" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Téléphone</label>
                                    <input type="text" name="telephone" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Courriel</label>
                                    <input type="email" name="email" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Adresse</label>
                                    <input type="text" name="adresse" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal: Modifier fournisseur -->
        <div class="modal fade" id="editFournisseurModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form method="post">
                        <div class="modal-header">
                            <h5 class="modal-title">Modifier un fournisseur</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="modifier_fournisseur" value="1">
                            <input type="hidden" name="id_fournisseur" id="edit_id_fournisseur" value="">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Fournisseur</label>
                                    <input type="text" name="fournisseur" id="edit_fournisseur" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Type</label>
                                    <input type="text" name="type_fournisseur" id="edit_type" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Responsable</label>
                                    <input type="text" name="responsable" id="edit_responsable" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Téléphone</label>
                                    <input type="text" name="telephone" id="edit_telephone" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Courriel</label>
                                    <input type="email" name="email" id="edit_email" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Adresse</label>
                                    <input type="text" name="adresse" id="edit_adresse" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal: Situation fournisseur (facturation/paiements) -->
        <div class="modal fade" id="situationFournisseurModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Situation du fournisseur</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <div id="situationAlert"></div>
                        <h5 class="mb-2">Informations fournisseur</h5>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered mb-0">
                                <tbody id="sit_infos">
                                    <tr><th style="width: 35%">Fournisseur</th><td class="text-muted">Chargement…</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <div class="card"><div class="card-body">
                                    <div class="text-muted">Débit</div>
                                    <div class="h4 mb-0" id="sit_debit">0</div>
                                </div></div>
                            </div>
                            <div class="col-md-4">
                                <div class="card"><div class="card-body">
                                    <div class="text-muted">Crédit (paiements)</div>
                                    <div class="h4 mb-0" id="sit_credit">0</div>
                                </div></div>
                            </div>
                            <div class="col-md-4">
                                <div class="card"><div class="card-body">
                                    <div class="text-muted">Solde à payer</div>
                                    <div class="h4 mb-0" id="sit_solde">0</div>
                                </div></div>
                            </div>
                        </div>

                        <h5 class="mb-2">Derniers paiements</h5>
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>Montant</th>
                                        <th>Compte</th>
                                        <th>Motif</th>
                                        <th>Payeur</th>
                                    </tr>
                                </thead>
                                <tbody id="sit_paiements">
                                    <tr><td colspan="6" class="text-muted">Chargement…</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <h5 class="mb-2">Derniers approvisionnements</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Commande</th>
                                        <th>Date commande</th>
                                        <th>Livraison</th>
                                        <th>Date livraison</th>
                                        <th>Montant total</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody id="sit_appros">
                                    <tr><td colspan="7" class="text-muted">Chargement…</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-bs-dismiss="modal">Fermer</button>
                        <button type="button" class="btn btn-primary" id="btnOpenPaiementFournisseur"><i class="fa fa-plus"></i> Enregistrer un paiement</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Enregistrer paiement fournisseur -->
        <div class="modal fade" id="paiementFournisseurModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form id="formPaiementFournisseur" enctype="multipart/form-data">
                        <div class="modal-header">
                            <h5 class="modal-title">Enregistrer un paiement fournisseur</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body">
                            <div id="paiementAlert"></div>
                            <input type="hidden" name="id_fournisseur" id="pay_id_fournisseur" value="">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Payé à</label>
                                    <input type="text" name="paye_a" id="pay_paye_a" class="form-control" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Compte</label>
                                    <select class="form-control" name="compte" id="pay_compte" required>
                                        <option value="">-- Choisir le compte --</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Montant</label>
                                    <input type="number" name="montant" id="pay_montant" class="form-control" min="1" step="1" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Date paiement</label>
                                    <input type="date" name="date_ajout" id="pay_date_ajout" class="form-control" required>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Motif</label>
                                    <textarea name="motif" id="pay_motif" class="form-control" rows="2" required></textarea>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Preuve de paiement (PDF/JPG/PNG)</label>
                                    <input type="file" name="preuve" id="pay_preuve" class="form-control" accept="application/pdf,image/jpeg,image/png" required>
                                    <small class="text-muted">Obligatoire.</small>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include('../PUBLIC/footer.php');?>
        <script>
        (function(){
            var addBtn = document.getElementById('btnOpenAddFournisseur');
            var addModalEl = document.getElementById('addFournisseurModal');
            var editModalEl = document.getElementById('editFournisseurModal');
            var situationModalEl = document.getElementById('situationFournisseurModal');
			var paiementModalEl = document.getElementById('paiementFournisseurModal');
			var btnOpenPaiement = document.getElementById('btnOpenPaiementFournisseur');
			var formPaiement = document.getElementById('formPaiementFournisseur');
			var currentFournisseurId = 0;

            function showModal(modalEl){
                if (!modalEl) return;
                if (window.bootstrap && window.bootstrap.Modal) {
                    var inst = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
                    inst.show();
                    return;
                }
                if (window.jQuery && typeof jQuery(modalEl).modal === 'function') {
                    jQuery(modalEl).modal('show');
                }
            }

			function hideModal(modalEl){
				if (!modalEl) return;
				if (window.bootstrap && window.bootstrap.Modal) {
					var inst = window.bootstrap.Modal.getInstance(modalEl);
					if (inst) inst.hide();
					return;
				}
				if (window.jQuery && typeof jQuery(modalEl).modal === 'function') {
					jQuery(modalEl).modal('hide');
				}
			}

            if (addBtn) {
                addBtn.addEventListener('click', function(){
                    if (addModalEl) {
                        var form = addModalEl.querySelector('form');
                        if (form) form.reset();
                    }
                    showModal(addModalEl);
                });
            }

            document.addEventListener('click', function(e){
                var btn = e.target && e.target.closest ? e.target.closest('.js-edit-fournisseur') : null;
                if (!btn) return;
                var id = btn.getAttribute('data-id') || '';
                var fournisseur = btn.getAttribute('data-fournisseur') || '';
                var type = btn.getAttribute('data-type') || '';
                var responsable = btn.getAttribute('data-responsable') || '';
                var telephone = btn.getAttribute('data-telephone') || '';
                var email = btn.getAttribute('data-email') || '';
                var adresse = btn.getAttribute('data-adresse') || '';

                var elId = document.getElementById('edit_id_fournisseur');
                var elF = document.getElementById('edit_fournisseur');
                var elT = document.getElementById('edit_type');
                var elR = document.getElementById('edit_responsable');
                var elTel = document.getElementById('edit_telephone');
                var elE = document.getElementById('edit_email');
                var elA = document.getElementById('edit_adresse');

                if (elId) elId.value = id;
                if (elF) elF.value = fournisseur;
                if (elT) elT.value = type;
                if (elR) elR.value = responsable;
                if (elTel) elTel.value = telephone;
                if (elE) elE.value = email;
                if (elA) elA.value = adresse;

                showModal(editModalEl);
            });

            function escHtml(s){
                return String(s == null ? '' : s)
                    .replace(/&/g,'&amp;')
                    .replace(/</g,'&lt;')
                    .replace(/>/g,'&gt;')
                    .replace(/"/g,'&quot;')
                    .replace(/'/g,'&#039;');
            }

            function fmtMoney(n){
                var x = Number(n || 0);
                try { return x.toLocaleString('fr-FR'); } catch(e) { return String(x); }
            }

            function setAlert(el, type, msg){
                if (!el) return;
                el.innerHTML = '<div class="alert alert-' + escHtml(type) + '">' + escHtml(msg) + '</div>';
            }

            function loadComptes(){
                var select = document.getElementById('pay_compte');
                if (!select) return;
                select.innerHTML = '<option value="">-- Choisir le compte --</option>';
                fetch('listedesfournisseurs.php?ajax_comptes=1', { headers: { 'Accept': 'application/json' } })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (!data || !data.success) return;
                    var comptes = Array.isArray(data.comptes) ? data.comptes : [];
                    select.innerHTML = '<option value="">-- Choisir le compte --</option>' + comptes.map(function(c){
                        var label = (c.nom_compte || ('Compte ' + c.id_compte));
                        return '<option value="' + escHtml(c.id_compte) + '">' + escHtml(label) + '</option>';
                    }).join('');
                })
                .catch(function(){});
            }

            function renderInfosFournisseur(f){
                var tbody = document.getElementById('sit_infos');
                if (!tbody) return;
                var statusTxt = (String(f.status) === '1') ? 'Actif' : 'Inactif';
                tbody.innerHTML = ''
                    + '<tr><th>ID</th><td>' + escHtml('ECFOUR' + String(f.id_fournisseur || '')) + '</td></tr>'
                    + '<tr><th>Fournisseur</th><td>' + escHtml(f.fournisseur || '') + '</td></tr>'
                    + '<tr><th>Type</th><td>' + escHtml(f.type_fournisseur || '') + '</td></tr>'
                    + '<tr><th>Responsable</th><td>' + escHtml(f.responsable || '') + '</td></tr>'
                    + '<tr><th>Téléphone</th><td>' + escHtml(f.telephone || '') + '</td></tr>'
                    + '<tr><th>Courriel</th><td>' + escHtml(f.email || '') + '</td></tr>'
                    + '<tr><th>Adresse</th><td>' + escHtml(f.adresse || '') + '</td></tr>'
                    + '<tr><th>Status</th><td>' + escHtml(statusTxt) + '</td></tr>';
            }

            function refreshSituation(id){
                var alertEl = document.getElementById('situationAlert');
                var debitEl = document.getElementById('sit_debit');
                var creditEl = document.getElementById('sit_credit');
                var soldeEl = document.getElementById('sit_solde');
                var payTbody = document.getElementById('sit_paiements');
                var apprTbody = document.getElementById('sit_appros');
                var infoTbody = document.getElementById('sit_infos');
                var btnPay = document.getElementById('btnOpenPaiementFournisseur');

                if (alertEl) alertEl.innerHTML = '';
                if (debitEl) debitEl.textContent = '0';
                if (creditEl) creditEl.textContent = '0';
                if (soldeEl) soldeEl.textContent = '0';
                if (btnPay) {
                    btnPay.disabled = true;
                    btnPay.title = 'Chargement…';
                }
                if (infoTbody) infoTbody.innerHTML = '<tr><th style="width: 35%">Fournisseur</th><td class="text-muted">Chargement…</td></tr>';
                if (payTbody) payTbody.innerHTML = '<tr><td colspan="6" class="text-muted">Chargement…</td></tr>';
                if (apprTbody) apprTbody.innerHTML = '<tr><td colspan="7" class="text-muted">Chargement…</td></tr>';

                return fetch('listedesfournisseurs.php?ajax_situation=1&id_fournisseur=' + encodeURIComponent(String(id)), {
                    headers: { 'Accept': 'application/json' }
                })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (!data || !data.success) {
                        setAlert(alertEl, 'danger', (data && data.message) ? data.message : 'Erreur');
                        if (payTbody) payTbody.innerHTML = '<tr><td colspan="6" class="text-muted">Aucune donnée</td></tr>';
                        if (apprTbody) apprTbody.innerHTML = '<tr><td colspan="7" class="text-muted">Aucune donnée</td></tr>';
                        return;
                    }

                    var f = data.fournisseur || {};
                    renderInfosFournisseur(f);

                    var resume = data.resume || {};
                    if (debitEl) debitEl.textContent = fmtMoney(resume.debit);
                    if (creditEl) creditEl.textContent = fmtMoney(resume.credit);
                    if (soldeEl) soldeEl.textContent = fmtMoney(resume.solde);

                    if (btnPay) {
                        var s = Number(resume.solde || 0);
                        if (isFinite(s) && s > 0) {
                            btnPay.disabled = false;
                            btnPay.title = '';
                        } else {
                            btnPay.disabled = true;
                            btnPay.title = 'Solde à payer = 0';
                        }
                    }

								var pays = Array.isArray(data.paiements) ? data.paiements : [];
                    if (payTbody) {
                        if (!pays.length) {
                            payTbody.innerHTML = '<tr><td colspan="6" class="text-muted">Aucun paiement trouvé.</td></tr>';
                        } else {
                            payTbody.innerHTML = pays.map(function(p){
                                var bon = p.id_paie ? ('<a target="_blank" href="bondepaiementfournisseur.php?paiement=' + encodeURIComponent(String(p.id_paie)) + '">Bon</a>') : '';
                                var preuve = p.preuve ? ('<a target="_blank" href="' + escHtml(String(p.preuve)) + '">Preuve</a>') : '';
                                return '<tr>'
                                    + '<td>' + escHtml(p.id_paie) + '</td>'
                                    + '<td>' + escHtml(p.date_ajout) + '</td>'
                                    + '<td>' + escHtml(fmtMoney(p.montant_paye)) + '</td>'
                                    + '<td>' + escHtml(p.compte) + '</td>'
                                    + '<td>' + escHtml(p.motif)
                                        + ((bon || preuve) ? ('<br><small>' + [bon, preuve].filter(Boolean).join(' | ') + '</small>') : '')
                                        + '</td>'
                                    + '<td>' + escHtml(p.payeur) + '</td>'
                                    + '</tr>';
                            }).join('');
                        }
                    }

                    var aps = Array.isArray(data.approvisionnements) ? data.approvisionnements : [];
                    if (apprTbody) {
                        if (!aps.length) {
                            apprTbody.innerHTML = '<tr><td colspan="7" class="text-muted">Aucun approvisionnement trouvé.</td></tr>';
                        } else {
                            apprTbody.innerHTML = aps.map(function(a){
                                return '<tr>'
                                    + '<td>' + escHtml(a.id_appro) + '</td>'
                                    + '<td>' + escHtml(a.no_commande) + '</td>'
                                    + '<td>' + escHtml(a.date_commande) + '</td>'
                                    + '<td>' + escHtml(a.no_livraison) + '</td>'
                                    + '<td>' + escHtml(a.date_livraison) + '</td>'
                                    + '<td>' + escHtml(fmtMoney(a.montant_total)) + '</td>'
                                    + '<td>' + escHtml(a.statut) + '</td>'
                                    + '</tr>';
                            }).join('');
                        }
                    }
                })
                .catch(function(){
                    setAlert(alertEl, 'danger', 'Erreur réseau.');
                    if (btnPay) {
                        btnPay.disabled = true;
                        btnPay.title = 'Erreur réseau.';
                    }
                    if (payTbody) payTbody.innerHTML = '<tr><td colspan="6" class="text-muted">Aucune donnée</td></tr>';
                    if (apprTbody) apprTbody.innerHTML = '<tr><td colspan="7" class="text-muted">Aucune donnée</td></tr>';
                });
            }

            document.addEventListener('click', function(e){
                var btn = e.target && e.target.closest ? e.target.closest('.js-situation-fournisseur') : null;
                if (!btn) return;
                var id = parseInt(btn.getAttribute('data-id') || '0', 10) || 0;
                if (!id) return;
				currentFournisseurId = id;
                showModal(situationModalEl);
				refreshSituation(id);
            });

			if (btnOpenPaiement) {
				btnOpenPaiement.addEventListener('click', function(){
                    if (btnOpenPaiement.disabled) return;
					var alertPay = document.getElementById('paiementAlert');
					if (alertPay) alertPay.innerHTML = '';
					var idEl = document.getElementById('pay_id_fournisseur');
					if (idEl) idEl.value = String(currentFournisseurId || '');
                    var dateEl = document.getElementById('pay_date_ajout');
                    if (dateEl && !dateEl.value) {
                        var d = new Date();
                        var mm = String(d.getMonth() + 1).padStart(2, '0');
                        var dd = String(d.getDate()).padStart(2, '0');
                        dateEl.value = d.getFullYear() + '-' + mm + '-' + dd;
                    }
                    var soldeTxt = (document.getElementById('sit_solde') || {}).textContent || '';
                    var montantEl = document.getElementById('pay_montant');
                    if (montantEl && !montantEl.value) {
                        var n = Number(String(soldeTxt).replace(/[^0-9.,-]/g, '').replace(',', '.'));
                        if (isFinite(n) && n > 0) montantEl.value = String(Math.floor(n));
                    }
					loadComptes();
					showModal(paiementModalEl);
				});
			}

			if (formPaiement) {
				formPaiement.addEventListener('submit', function(ev){
					ev.preventDefault();
					var alertPay = document.getElementById('paiementAlert');
					if (alertPay) alertPay.innerHTML = '';

					var fd = new FormData(formPaiement);
					fetch('listedesfournisseurs.php?ajax_payer=1', {
						method: 'POST',
						body: fd,
						headers: { 'Accept': 'application/json' }
					})
					.then(function(r){ return r.json(); })
					.then(function(data){
						if (!data || !data.success) {
							setAlert(alertPay, 'danger', (data && data.message) ? data.message : 'Erreur');
							return;
						}
						setAlert(alertPay, 'success', data.message || 'Paiement enregistré.');
						if (data.bon_url) {
							var a = '<a target="_blank" href="' + escHtml(data.bon_url) + '">Imprimer le bon de paiement</a>';
							alertPay.innerHTML += '<div class="mt-2">' + a + '</div>';
						}
						refreshSituation(currentFournisseurId);
					})
					.catch(function(){
						setAlert(alertPay, 'danger', 'Erreur réseau.');
					});
				});
			}
        })();
        </script>
    </body>
</html>
