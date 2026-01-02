<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function buildPlageConnexion($type) {
    $t = strtolower(trim((string)$type));
    $jours = ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche'];
    $parts = [];
    foreach ($jours as $j) {
        $parts[] = $j . ':' . $t;
    }
    return implode(';', $parts);
}

function resolveResponsableUserId(PDO $bdd, ?int $superieurEmployeId): int {
    if (!$superieurEmployeId || $superieurEmployeId <= 0) {
        return 0;
    }

    $stmt = $bdd->prepare('SELECT email FROM employes WHERE id_employe = ? LIMIT 1');
    $stmt->execute([$superieurEmployeId]);
    $email = (string)($stmt->fetchColumn() ?? '');
    $email = trim($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 0;
    }

    $stmt = $bdd->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    return (int)($stmt->fetchColumn() ?: 0);
}

function redirectWith($params) {
    $base = $_SERVER['PHP_SELF'];
    header('Location: ' . $base . (empty($params) ? '' : ('?' . http_build_query($params))));
    exit;
}

$alert = null; // ['type' => 'success|danger|warning|info', 'message' => '...']

// Feedback PRG
if (isset($_GET['ok']) && (int)$_GET['ok'] === 1) {
    if (isset($_SESSION['flash_new_user']) && is_array($_SESSION['flash_new_user'])) {
        $f = $_SESSION['flash_new_user'];
        unset($_SESSION['flash_new_user']);
        $alert = [
            'type' => 'success',
            'message' => "Employé ajouté et compte utilisateur créé. Email: " . ($f['email'] ?? '') . " | Mot de passe: " . ($f['password'] ?? '') . " | Code secret: " . ($f['code'] ?? '')
        ];
    } else {
        $alert = ['type' => 'success', 'message' => "Employé ajouté avec succès."];
    }
} elseif (isset($_GET['ok']) && (int)$_GET['ok'] === 2) {
    if (isset($_SESSION['flash_user_created_on_edit']) && is_array($_SESSION['flash_user_created_on_edit'])) {
        $f = $_SESSION['flash_user_created_on_edit'];
        unset($_SESSION['flash_user_created_on_edit']);
        $alert = [
            'type' => 'success',
            'message' => "Employé modifié et compte utilisateur créé. Email: " . ($f['email'] ?? '') . " | Mot de passe: " . ($f['password'] ?? '') . " | Code secret: " . ($f['code'] ?? '')
        ];
    } else {
        $alert = ['type' => 'success', 'message' => "Employé modifié avec succès."];
    }
} elseif (isset($_GET['err'])) {
    $err = (int)$_GET['err'];
    if ($err === 1) $alert = ['type' => 'danger', 'message' => "Le nom de l'employé est obligatoire."];
    if ($err === 2) $alert = ['type' => 'danger', 'message' => "Le format de l'email est invalide."];
    if ($err === 3) $alert = ['type' => 'danger', 'message' => "Cet email est déjà utilisé par un autre employé."];
    if ($err === 4) $alert = ['type' => 'danger', 'message' => "Le supérieur hiérarchique sélectionné est introuvable."];
    if ($err === 5) $alert = ['type' => 'danger', 'message' => "Le salaire doit être un nombre."];
    if ($err === 6) $alert = ['type' => 'danger', 'message' => "Photo invalide. Formats autorisés: JPG/PNG."];
    if ($err === 7) $alert = ['type' => 'danger', 'message' => "Impossible d'enregistrer la photo (vérifier les permissions)."];
    if ($err === 8) $alert = ['type' => 'danger', 'message' => "Erreur base de données."];
    if ($err === 9) $alert = ['type' => 'danger', 'message' => "Service invalide."];
    if ($err === 10) $alert = ['type' => 'danger', 'message' => "Le profil (type d'utilisateur) est obligatoire pour créer une session."];
    if ($err === 11) $alert = ['type' => 'danger', 'message' => "L'email est obligatoire pour créer une session."];
    if ($err === 12) $alert = ['type' => 'danger', 'message' => "Cet email existe déjà dans les utilisateurs."];
    if ($err === 13) $alert = ['type' => 'danger', 'message' => "Employé introuvable."];
    if ($err === 14) $alert = ['type' => 'danger', 'message' => "Cet email est déjà utilisé par un autre employé."];
    if ($err === 15) $alert = ['type' => 'danger', 'message' => "Cet email est déjà utilisé par un autre utilisateur."];
}

// Liste des supérieurs (pour le modal)
$superieurs = [];
try {
    $stmt = $bdd->prepare('SELECT id_employe, nom_employe FROM employes ORDER BY nom_employe');
    $stmt->execute();
    $superieurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('listeemployes.php superieurs: ' . $e->getMessage());
    $superieurs = [];
}

// Liste des services (organigramme) pour le modal
$servicesOrg = [];
try {
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
    $servicesOrg = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('listeemployes.php organigramme: ' . $e->getMessage());
    $servicesOrg = [];
}

// Modifier employé (modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_employe'])) {
    $idEmploye = isset($_POST['id_employe']) ? (int)$_POST['id_employe'] : 0;

    $nom = trim((string)($_POST['nom_employe'] ?? ''));
    $dateNaissance = trim((string)($_POST['date_naissance'] ?? ''));
    $adresse = trim((string)($_POST['adresse'] ?? ''));
    $telephone = trim((string)($_POST['telephone'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $oldEmail = trim((string)($_POST['old_email'] ?? ''));
    $userType = trim((string)($_POST['user_type'] ?? ''));
    $dateEmbauche = trim((string)($_POST['date_embauche'] ?? ''));
    $poste = trim((string)($_POST['poste'] ?? ''));
    $serviceId = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
    $service = '';
    $salaire = trim((string)($_POST['salaire'] ?? ''));
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    $superieur = isset($_POST['superieur_hierarchique']) ? (int)$_POST['superieur_hierarchique'] : 0;
    $notes = trim((string)($_POST['notes'] ?? ''));

    if ($idEmploye <= 0) {
        redirectWith(['err' => 13]);
    }
    if ($nom === '') {
        redirectWith(['err' => 1, 'edit' => $idEmploye]);
    }
    if ($email === '') {
        redirectWith(['err' => 11, 'edit' => $idEmploye]);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirectWith(['err' => 2, 'edit' => $idEmploye]);
    }
    if ($userType === '') {
        redirectWith(['err' => 10, 'edit' => $idEmploye]);
    }

    // Service depuis organigramme (on stocke la cellule)
    try {
        if ($serviceId > 0) {
            $stmt = $bdd->prepare('SELECT celulle FROM organigramme WHERE id_organigramme = ? LIMIT 1');
            $stmt->execute([$serviceId]);
            $rowSvc = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rowSvc || trim((string)($rowSvc['celulle'] ?? '')) === '') {
                redirectWith(['err' => 9, 'edit' => $idEmploye]);
            }
            $service = trim((string)$rowSvc['celulle']);
        } else {
            $service = '';
        }
    } catch (PDOException $e) {
        error_log('listeemployes.php edit service: ' . $e->getMessage());
        redirectWith(['err' => 8, 'edit' => $idEmploye]);
    }

    // Normaliser salaire
    $salaireDb = null;
    if ($salaire !== '') {
        $salaireNorm = str_replace([' ', ','], ['', '.'], $salaire);
        if (!is_numeric($salaireNorm)) {
            redirectWith(['err' => 5, 'edit' => $idEmploye]);
        }
        $salaireDb = (float)$salaireNorm;
    }

    // Upload photo (optionnel)
    $newPhotoPath = null;
    if (isset($_FILES['photo']) && is_array($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $errUp = (int)($_FILES['photo']['error'] ?? UPLOAD_ERR_OK);
        if ($errUp !== UPLOAD_ERR_OK) {
            redirectWith(['err' => 6, 'edit' => $idEmploye]);
        }
        $tmp = (string)($_FILES['photo']['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            redirectWith(['err' => 6, 'edit' => $idEmploye]);
        }
        $ext = strtolower(pathinfo((string)($_FILES['photo']['name'] ?? ''), PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];
        if (!in_array($ext, $allowed, true)) {
            redirectWith(['err' => 6, 'edit' => $idEmploye]);
        }

        $uploadDir = realpath(__DIR__ . '/../documents/photoemployes');
        if ($uploadDir === false) {
            $uploadDir = __DIR__ . '/../documents/photoemployes';
        }
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }
        @chmod($uploadDir, 0777);
        if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
            redirectWith(['err' => 7, 'edit' => $idEmploye]);
        }

        $filename = 'emp_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destAbs = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        if (!@move_uploaded_file($tmp, $destAbs)) {
            redirectWith(['err' => 7, 'edit' => $idEmploye]);
        }
        $newPhotoPath = 'documents/photoemployes/' . $filename;
    }

    try {
        // Vérifier existence employé + récupérer photo actuelle
        $stmt = $bdd->prepare('SELECT id_employe, email, photo FROM employes WHERE id_employe = ? LIMIT 1');
        $stmt->execute([$idEmploye]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            if (!empty($newPhotoPath)) {
                $abs = realpath(__DIR__ . '/../' . $newPhotoPath);
                if ($abs && is_file($abs)) { @unlink($abs); }
            }
            redirectWith(['err' => 13]);
        }

        $emailBefore = $oldEmail !== '' ? $oldEmail : (string)($existing['email'] ?? '');

        // Unicité email employes (hors courant)
        $stmt = $bdd->prepare('SELECT id_employe FROM employes WHERE email = ? AND id_employe <> ? LIMIT 1');
        $stmt->execute([$email, $idEmploye]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($newPhotoPath)) {
                $abs = realpath(__DIR__ . '/../' . $newPhotoPath);
                if ($abs && is_file($abs)) { @unlink($abs); }
            }
            redirectWith(['err' => 14, 'edit' => $idEmploye]);
        }

        // Trouver user lié par l'ancien email (fallback: email courant)
        $stmt = $bdd->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$emailBefore]);
        $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $userId = $userRow ? (int)$userRow['id'] : 0;

        // Unicité email users (hors courant)
        $stmt = $bdd->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $stmt->execute([$email, $userId > 0 ? $userId : -1]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($newPhotoPath)) {
                $abs = realpath(__DIR__ . '/../' . $newPhotoPath);
                if ($abs && is_file($abs)) { @unlink($abs); }
            }
            redirectWith(['err' => 15, 'edit' => $idEmploye]);
        }

        // Supérieur valide
        $superieurDb = null;
        if ($superieur > 0) {
            if ($superieur === $idEmploye) {
                if (!empty($newPhotoPath)) {
                    $abs = realpath(__DIR__ . '/../' . $newPhotoPath);
                    if ($abs && is_file($abs)) { @unlink($abs); }
                }
                redirectWith(['err' => 4, 'edit' => $idEmploye]);
            }
            $stmt = $bdd->prepare('SELECT id_employe FROM employes WHERE id_employe = ? LIMIT 1');
            $stmt->execute([$superieur]);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($newPhotoPath)) {
                    $abs = realpath(__DIR__ . '/../' . $newPhotoPath);
                    if ($abs && is_file($abs)) { @unlink($abs); }
                }
                redirectWith(['err' => 4, 'edit' => $idEmploye]);
            }
            $superieurDb = $superieur;
        }

        $responsableUserId = resolveResponsableUserId($bdd, $superieurDb);

        $bdd->beginTransaction();

        // Update employe
        $sql = 'UPDATE employes SET nom_employe = ?, date_naissance = ?, adresse = ?, telephone = ?, email = ?, date_embauche = ?, poste = ?, service = ?, salaire = ?, status = ?, superieur_hierarchique = ?, notes = ?';
        $params = [
            $nom,
            ($dateNaissance !== '' ? $dateNaissance : null),
            ($adresse !== '' ? $adresse : null),
            ($telephone !== '' ? $telephone : null),
            $email,
            ($dateEmbauche !== '' ? $dateEmbauche : null),
            ($poste !== '' ? $poste : null),
            ($service !== '' ? $service : null),
            $salaireDb,
            $status,
            $superieurDb,
            ($notes !== '' ? $notes : null),
        ];

        $oldPhotoPath = (string)($existing['photo'] ?? '');
        if ($newPhotoPath !== null) {
            $sql .= ', photo = ?';
            $params[] = $newPhotoPath;
        }

        $sql .= ' WHERE id_employe = ?';
        $params[] = $idEmploye;

        $stmt = $bdd->prepare($sql);
        $stmt->execute($params);

        // Update ou create user
        $plageConnexion = buildPlageConnexion($userType);
        if ($userId > 0) {
            $stmtU = $bdd->prepare('UPDATE users SET pseudo = ?, email = ?, type = ?, id_service = ?, date_engagement = ?, responsable = ?, plage_connexion = ?, status = ? WHERE id = ?');
            $stmtU->execute([
                $nom,
                $email,
                strtolower($userType),
                $serviceId > 0 ? $serviceId : 0,
                ($dateEmbauche !== '' ? $dateEmbauche : date('Y-m-d')),
                $responsableUserId,
                $plageConnexion,
                ($status === 1 ? 1 : 0),
                $userId,
            ]);
        } else {
            $plainPassword = bin2hex(random_bytes(4));
            $plainCode = (string)random_int(100000, 999999);

            // Pseudo unique
            $basePseudo = trim($nom);
            $pseudo = $basePseudo;
            $suffix = 1;
            while (true) {
                $check = $bdd->prepare('SELECT COUNT(*) FROM users WHERE pseudo = ?');
                $check->execute([$pseudo]);
                if ((int)$check->fetchColumn() === 0) {
                    break;
                }
                $suffix++;
                $pseudo = $basePseudo . ' ' . $suffix;
                if ($suffix > 50) {
                    $pseudo = $basePseudo . ' ' . $idEmploye;
                    break;
                }
            }

            $stmtU = $bdd->prepare('INSERT INTO users (pseudo, email, type, id_service, date_engagement, responsable, plage_connexion, mdp, token, status) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $stmtU->execute([
                $pseudo,
                $email,
                strtolower($userType),
                $serviceId > 0 ? $serviceId : 0,
                ($dateEmbauche !== '' ? $dateEmbauche : date('Y-m-d')),
                $responsableUserId,
                $plageConnexion,
                password_hash($plainPassword, PASSWORD_DEFAULT),
                password_hash($plainCode, PASSWORD_DEFAULT),
                ($status === 1 ? 1 : 0),
            ]);

            $_SESSION['flash_user_created_on_edit'] = [
                'email' => $email,
                'password' => $plainPassword,
                'code' => $plainCode,
            ];
        }

        $bdd->commit();

        // Supprimer l'ancienne photo si remplacée
        if ($newPhotoPath !== null && $oldPhotoPath !== '') {
            $absOld = realpath(__DIR__ . '/../' . $oldPhotoPath);
            if ($absOld && is_file($absOld)) {
                @unlink($absOld);
            }
        }

        redirectWith(['ok' => 2]);
    } catch (PDOException $e) {
        if (isset($bdd) && $bdd->inTransaction()) {
            $bdd->rollBack();
        }
        if (!empty($newPhotoPath)) {
            $abs = realpath(__DIR__ . '/../' . $newPhotoPath);
            if ($abs && is_file($abs)) { @unlink($abs); }
        }
        error_log('listeemployes.php edit PDO: ' . $e->getMessage());
        redirectWith(['err' => 8, 'edit' => $idEmploye]);
    } catch (Exception $e) {
        if (isset($bdd) && $bdd->inTransaction()) {
            $bdd->rollBack();
        }
        if (!empty($newPhotoPath)) {
            $abs = realpath(__DIR__ . '/../' . $newPhotoPath);
            if ($abs && is_file($abs)) { @unlink($abs); }
        }
        error_log('listeemployes.php edit ex: ' . $e->getMessage());
        redirectWith(['err' => 8, 'edit' => $idEmploye]);
    }
}

// Ajout employé (modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_employe'])) {
    $nom = trim((string)($_POST['nom_employe'] ?? ''));
    $dateNaissance = trim((string)($_POST['date_naissance'] ?? ''));
    $adresse = trim((string)($_POST['adresse'] ?? ''));
    $telephone = trim((string)($_POST['telephone'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $userType = trim((string)($_POST['user_type'] ?? ''));
    $dateEmbauche = trim((string)($_POST['date_embauche'] ?? ''));
    $poste = trim((string)($_POST['poste'] ?? ''));
    $serviceId = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
    $service = '';
    $salaire = trim((string)($_POST['salaire'] ?? ''));
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    $superieur = isset($_POST['superieur_hierarchique']) ? (int)$_POST['superieur_hierarchique'] : 0;
    $notes = trim((string)($_POST['notes'] ?? ''));

    if ($nom === '') {
        redirectWith(['err' => 1, 'add' => 1]);
    }
    if ($email === '') {
        redirectWith(['err' => 11, 'add' => 1]);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirectWith(['err' => 2, 'add' => 1]);
    }
    if ($userType === '') {
        redirectWith(['err' => 10, 'add' => 1]);
    }

    try {
        // Service depuis organigramme (on stocke la cellule dans employes.service)
        if ($serviceId > 0) {
            $stmt = $bdd->prepare('SELECT celulle FROM organigramme WHERE id_organigramme = ? LIMIT 1');
            $stmt->execute([$serviceId]);
            $rowSvc = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rowSvc || trim((string)($rowSvc['celulle'] ?? '')) === '') {
                redirectWith(['err' => 9, 'add' => 1]);
            }
            $service = trim((string)$rowSvc['celulle']);
        } else {
            $service = '';
        }

        // Email unique (employes)
        $stmt = $bdd->prepare('SELECT id_employe FROM employes WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            redirectWith(['err' => 3, 'add' => 1]);
        }

        // Email unique (users)
        $stmt = $bdd->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            redirectWith(['err' => 12, 'add' => 1]);
        }

        // Supérieur existe
        $superieurDb = null;
        if ($superieur > 0) {
            $stmt = $bdd->prepare('SELECT id_employe FROM employes WHERE id_employe = ? LIMIT 1');
            $stmt->execute([$superieur]);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                redirectWith(['err' => 4, 'add' => 1]);
            }
            $superieurDb = $superieur;
        }

        // Salaire
        $salaireDb = null;
        if ($salaire !== '') {
            $salaireNorm = str_replace([' ', ','], ['', '.'], $salaire);
            if (!is_numeric($salaireNorm)) {
                redirectWith(['err' => 5, 'add' => 1]);
            }
            $salaireDb = (float)$salaireNorm;
        }

        // Upload photo
        $photoPath = null;
        if (isset($_FILES['photo']) && is_array($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $errUp = (int)($_FILES['photo']['error'] ?? UPLOAD_ERR_OK);
            if ($errUp !== UPLOAD_ERR_OK) {
                redirectWith(['err' => 6, 'add' => 1]);
            }

            $tmp = (string)($_FILES['photo']['tmp_name'] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                redirectWith(['err' => 6, 'add' => 1]);
            }

            $ext = strtolower(pathinfo((string)($_FILES['photo']['name'] ?? ''), PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png'];
            if (!in_array($ext, $allowed, true)) {
                redirectWith(['err' => 6, 'add' => 1]);
            }

            $uploadDir = realpath(__DIR__ . '/../documents/photoemployes');
            if ($uploadDir === false) {
                // fallback: chemin relatif sans realpath
                $uploadDir = __DIR__ . '/../documents/photoemployes';
            }
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            // Assurer permissions d'écriture (en environnement XAMPP cela évite les erreurs "permissions")
            @chmod($uploadDir, 0777);

            if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
                redirectWith(['err' => 7, 'add' => 1]);
            }

            $filename = 'emp_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $destAbs = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

            if (!@move_uploaded_file($tmp, $destAbs)) {
                redirectWith(['err' => 7, 'add' => 1]);
            }

            // Stocker un chemin relatif utilisable dans l'app
            $photoPath = 'documents/photoemployes/' . $filename;
        }

        // Supérieur valide
        $superieurDb = null;
        if ($superieur > 0) {
            $stmt = $bdd->prepare('SELECT id_employe FROM employes WHERE id_employe = ? LIMIT 1');
            $stmt->execute([$superieur]);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                redirectWith(['err' => 4, 'add' => 1]);
            }
            $superieurDb = $superieur;
        }

        $responsableUserId = resolveResponsableUserId($bdd, $superieurDb);

        // Transaction: employe + user
        $bdd->beginTransaction();

        $stmt = $bdd->prepare(
            'INSERT INTO employes (
                nom_employe, date_naissance, adresse, telephone, email,
                date_embauche, poste, service, salaire, status,
                superieur_hierarchique, notes, photo
            ) VALUES (
                :nom, :date_naissance, :adresse, :telephone, :email,
                :date_embauche, :poste, :service, :salaire, :status,
                :superieur, :notes, :photo
            )'
        );
        $stmt->execute([
            ':nom' => $nom,
            ':date_naissance' => ($dateNaissance !== '' ? $dateNaissance : null),
            ':adresse' => ($adresse !== '' ? $adresse : null),
            ':telephone' => ($telephone !== '' ? $telephone : null),
            ':email' => ($email !== '' ? $email : null),
            ':date_embauche' => ($dateEmbauche !== '' ? $dateEmbauche : null),
            ':poste' => ($poste !== '' ? $poste : null),
            ':service' => ($service !== '' ? $service : null),
            ':salaire' => $salaireDb,
            ':status' => $status,
            ':superieur' => $superieurDb,
            ':notes' => ($notes !== '' ? $notes : null),
            ':photo' => $photoPath,
        ]);

        $newEmpId = (int)$bdd->lastInsertId();

        // Générer identifiants user
        $plainPassword = bin2hex(random_bytes(4)); // 8 chars
        $plainCode = (string)random_int(100000, 999999);
        $plageConnexion = buildPlageConnexion($userType);

        // Pseudo unique
        $basePseudo = trim($nom);
        $pseudo = $basePseudo;
        $suffix = 1;
        while (true) {
            $check = $bdd->prepare('SELECT COUNT(*) FROM users WHERE pseudo = ?');
            $check->execute([$pseudo]);
            if ((int)$check->fetchColumn() === 0) {
                break;
            }
            $suffix++;
            $pseudo = $basePseudo . ' ' . $suffix;
            if ($suffix > 50) {
                $pseudo = $basePseudo . ' ' . $newEmpId;
                break;
            }
        }

        $stmtU = $bdd->prepare('INSERT INTO users (pseudo, email, type, id_service, date_engagement, responsable, plage_connexion, mdp, token, status) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $stmtU->execute([
            $pseudo,
            $email,
            strtolower($userType),
            $serviceId > 0 ? $serviceId : 0,
            ($dateEmbauche !== '' ? $dateEmbauche : date('Y-m-d')),
            $responsableUserId,
            $plageConnexion,
            password_hash($plainPassword, PASSWORD_DEFAULT),
            password_hash($plainCode, PASSWORD_DEFAULT),
            1,
        ]);

        $bdd->commit();

        $_SESSION['flash_new_user'] = [
            'email' => $email,
            'password' => $plainPassword,
            'code' => $plainCode,
        ];

        redirectWith(['ok' => 1]);
    } catch (PDOException $e) {
        if (isset($bdd) && $bdd->inTransaction()) {
            $bdd->rollBack();
        }
        // Nettoyer la photo si elle a été déplacée
        if (!empty($photoPath)) {
            $abs = realpath(__DIR__ . '/../' . $photoPath);
            if ($abs && is_file($abs)) {
                @unlink($abs);
            }
        }
        error_log('listeemployes.php insert: ' . $e->getMessage());
        redirectWith(['err' => 8, 'add' => 1]);
    } catch (Exception $e) {
        if (isset($bdd) && $bdd->inTransaction()) {
            $bdd->rollBack();
        }
        if (!empty($photoPath)) {
            $abs = realpath(__DIR__ . '/../' . $photoPath);
            if ($abs && is_file($abs)) {
                @unlink($abs);
            }
        }
        error_log('listeemployes.php insert ex: ' . $e->getMessage());
        redirectWith(['err' => 8, 'add' => 1]);
    }
}

$rows = [];
$error = null;

try {
    $stmt = $bdd->prepare(
        'SELECT e.id_employe, e.nom_employe, e.date_naissance, e.adresse, e.telephone, e.email, e.date_embauche, e.poste,
                e.service, e.salaire, e.status, e.superieur_hierarchique, e.notes, e.photo,
                s.nom_employe AS superieur_nom,
                o.id_org AS service_id,
                u.type AS user_type
         FROM employes e
         LEFT JOIN employes s ON s.id_employe = e.superieur_hierarchique
         LEFT JOIN (
            SELECT celulle, MIN(id_organigramme) AS id_org
            FROM organigramme
            GROUP BY celulle
         ) o ON o.celulle = e.service
         LEFT JOIN users u ON u.email = e.email
         ORDER BY e.id_employe DESC'
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('listeemployes.php select: ' . $e->getMessage());
    $error = "Une erreur est survenue lors de la récupération des employés.";
}

include('../PUBLIC/header.php');
?>

<body>
    <section class="body">
        <?php require('../PUBLIC/navbarmenu.php'); ?>

        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Liste des employés</h2>
                </header>

                <div class="col-md-12">
                    <?php if ($alert): ?>
                        <div class="alert alert-<?php echo h($alert['type']); ?>">
                            <?php echo h($alert['message']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo h($error); ?></div>
                    <?php endif; ?>

                    <section class="card">
                        <div class="card-body">
                            <div class="mb-3 text-end">
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddEmploye">Ajouter un employé</button>
                            </div>

                            <table class="table table-bordered table-striped mb-0" id="datatable-default">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom</th>
                                        <th>Téléphone</th>
                                        <th>Email</th>
                                        <th>Poste</th>
                                        <th>Service</th>
                                        <th>Date embauche</th>
                                        <th>Supérieur</th>
                                        <th>Statut</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rows)): ?>
                                        <tr>
                                            <td colspan="10">Aucun employé trouvé.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($rows as $r): ?>
                                            <?php
                                                $status = isset($r['status']) ? (int)$r['status'] : 0;
                                                $statusLabel = ($status === 1) ? 'Actif' : 'Inactif';
                                                $badge = ($status === 1) ? 'success' : 'secondary';
                                            ?>
                                            <tr>
                                                <td><?php echo h($r['id_employe'] ?? ''); ?></td>
                                                <td><?php echo h($r['nom_employe'] ?? ''); ?></td>
                                                <td><?php echo h($r['telephone'] ?? '—'); ?></td>
                                                <td><?php echo h($r['email'] ?? '—'); ?></td>
                                                <td><?php echo h($r['poste'] ?? '—'); ?></td>
                                                <td><?php echo h($r['service'] ?? '—'); ?></td>
                                                <td><?php echo h($r['date_embauche'] ?? '—'); ?></td>
                                                <td><?php echo h($r['superieur_nom'] ?? '—'); ?></td>
                                                <td><span class="badge bg-<?php echo h($badge); ?>"><?php echo h($statusLabel); ?></span></td>
                                                <td>
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-primary btn-edit-emp"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#modalEditEmploye"
                                                        data-id_employe="<?php echo h($r['id_employe'] ?? ''); ?>"
                                                        data-nom_employe="<?php echo h($r['nom_employe'] ?? ''); ?>"
                                                        data-date_naissance="<?php echo h($r['date_naissance'] ?? ''); ?>"
                                                        data-adresse="<?php echo h($r['adresse'] ?? ''); ?>"
                                                        data-telephone="<?php echo h($r['telephone'] ?? ''); ?>"
                                                        data-email="<?php echo h($r['email'] ?? ''); ?>"
                                                        data-date_embauche="<?php echo h($r['date_embauche'] ?? ''); ?>"
                                                        data-poste="<?php echo h($r['poste'] ?? ''); ?>"
                                                        data-service_id="<?php echo h($r['service_id'] ?? '0'); ?>"
                                                        data-salaire="<?php echo h($r['salaire'] ?? ''); ?>"
                                                        data-status="<?php echo h($r['status'] ?? '1'); ?>"
                                                        data-superieur_hierarchique="<?php echo h($r['superieur_hierarchique'] ?? '0'); ?>"
                                                        data-notes="<?php echo h($r['notes'] ?? ''); ?>"
                                                        data-user_type="<?php echo h($r['user_type'] ?? ''); ?>"
                                                    >
                                                        Modifier
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </section>
        </div>

        <!-- Modal: Modifier un employé -->
        <div class="modal fade" id="modalEditEmploye" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" action="<?php echo h($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
                        <div class="modal-header">
                            <h5 class="modal-title">Modifier un employé</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="edit_employe" value="1">
                            <input type="hidden" name="id_employe" id="edit_id_employe" value="">
                            <input type="hidden" name="old_email" id="edit_old_email" value="">

                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="col-form-label">Nom de l'employé *</label>
                                    <input type="text" class="form-control" name="nom_employe" id="edit_nom_employe" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="col-form-label">Statut</label>
                                    <select name="status" class="form-control" id="edit_status">
                                        <option value="1">Actif</option>
                                        <option value="0">Inactif</option>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="col-form-label">Date de naissance</label>
                                    <input type="date" class="form-control" name="date_naissance" id="edit_date_naissance">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="col-form-label">Date d'embauche</label>
                                    <input type="date" class="form-control" name="date_embauche" id="edit_date_embauche">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="col-form-label">Salaire</label>
                                    <input type="text" class="form-control" name="salaire" id="edit_salaire" placeholder="ex: 150000">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Téléphone</label>
                                    <input type="text" class="form-control" name="telephone" id="edit_telephone">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" id="edit_email" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Profil (type d'utilisateur) *</label>
                                    <select name="user_type" class="form-control populate" data-plugin-selectTwo required id="edit_user_type">
                                        <option value="">— Choisir —</option>
                                        <option value="technologie">Technologie</option>
                                        <option value="secretariat">Secrétariat</option>
                                        <option value="caisse">Caisse</option>
                                        <option value="boutique">Boutique</option>
                                        <option value="comptabilite">Comptabilité</option>
                                        <option value="logistique">Logistique</option>
                                        <option value="ophtalmologue">Ophtalmologue</option>
                                        <option value="infirmier">Infirmier</option>
                                        <option value="optometriste">Optométriste</option>
                                        <option value="medecin">Médecin</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3"></div>

                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Poste</label>
                                    <input type="text" class="form-control" name="poste" id="edit_poste">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Service</label>
                                    <select name="service_id" class="form-control populate" data-plugin-selectTwo id="edit_service_id">
                                        <option value="0">— Choisir —</option>
                                        <?php
                                            foreach ($servicesOrg as $row) {
                                                $idOrg = (int)($row['id_organigramme'] ?? 0);
                                                $celulle = (string)($row['celulle'] ?? '');
                                                $departement = (string)($row['departement'] ?? '');
                                                if ($idOrg <= 0 || trim($celulle) === '') { continue; }
                                                $label = trim($departement) !== '' ? (trim($departement) . ' - ' . trim($celulle)) : trim($celulle);
                                                echo '<option value="' . h((string)$idOrg) . '">' . h($label) . '</option>';
                                            }
                                        ?>
                                    </select>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label class="col-form-label">Adresse</label>
                                    <input type="text" class="form-control" name="adresse" id="edit_adresse">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Supérieur hiérarchique</label>
                                    <select name="superieur_hierarchique" class="form-control" data-plugin-selectTwo id="edit_superieur_hierarchique">
                                        <option value="0">— Aucun —</option>
                                        <?php foreach ($superieurs as $s): ?>
                                            <?php
                                                $sid = (int)($s['id_employe'] ?? 0);
                                                $slabel = (string)($s['nom_employe'] ?? '');
                                                if ($sid <= 0 || $slabel === '') { continue; }
                                            ?>
                                            <option value="<?php echo h((string)$sid); ?>"><?php echo h($slabel); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Photo d'identité</label>
                                    <input type="file" class="form-control" name="photo" accept="image/png,image/jpeg">
                                    <small class="text-muted">Laisser vide pour conserver l'ancienne.</small>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label class="col-form-label">Notes</label>
                                    <textarea class="form-control" name="notes" id="edit_notes" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-bs-dismiss="modal">Fermer</button>
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            (function () {
                function setValue(id, value) {
                    var el = document.getElementById(id);
                    if (!el) return;
                    el.value = value == null ? '' : String(value);
                }

                document.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('.btn-edit-emp').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            setValue('edit_id_employe', btn.getAttribute('data-id_employe'));
                            setValue('edit_nom_employe', btn.getAttribute('data-nom_employe'));
                            setValue('edit_date_naissance', btn.getAttribute('data-date_naissance'));
                            setValue('edit_adresse', btn.getAttribute('data-adresse'));
                            setValue('edit_telephone', btn.getAttribute('data-telephone'));
                            setValue('edit_email', btn.getAttribute('data-email'));
                            setValue('edit_old_email', btn.getAttribute('data-email'));
                            setValue('edit_date_embauche', btn.getAttribute('data-date_embauche'));
                            setValue('edit_poste', btn.getAttribute('data-poste'));
                            setValue('edit_service_id', btn.getAttribute('data-service_id') || '0');
                            setValue('edit_salaire', btn.getAttribute('data-salaire'));
                            setValue('edit_status', btn.getAttribute('data-status') || '1');
                            setValue('edit_superieur_hierarchique', btn.getAttribute('data-superieur_hierarchique') || '0');
                            setValue('edit_notes', btn.getAttribute('data-notes'));
                            setValue('edit_user_type', btn.getAttribute('data-user_type'));
                        });
                    });

                    // Ouvrir automatiquement le modal d'édition si ?edit=<id>
                    var url = new URL(window.location.href);
                    var editId = url.searchParams.get('edit');
                    if (editId && typeof bootstrap !== 'undefined') {
                        var trigger = document.querySelector('.btn-edit-emp[data-id_employe="' + editId.replace(/"/g, '') + '"]');
                        if (trigger) {
                            trigger.click();
                            var el = document.getElementById('modalEditEmploye');
                            if (el) {
                                bootstrap.Modal.getOrCreateInstance(el).show();
                            }
                        }
                    }
                });
            })();
        </script>

        <!-- Modal: Ajouter un employé -->
        <div class="modal fade" id="modalAddEmploye" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" action="<?php echo h($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
                        <div class="modal-header">
                            <h5 class="modal-title">Ajouter un employé</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="ajouter_employe" value="1">

                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="col-form-label">Nom de l'employé *</label>
                                    <input type="text" class="form-control" name="nom_employe" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="col-form-label">Statut</label>
                                    <select name="status" class="form-control">
                                        <option value="1" selected>Actif</option>
                                        <option value="0">Inactif</option>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="col-form-label">Date de naissance</label>
                                    <input type="date" class="form-control" name="date_naissance">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="col-form-label">Date d'embauche</label>
                                    <input type="date" class="form-control" name="date_embauche">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="col-form-label">Salaire</label>
                                    <input type="text" class="form-control" name="salaire" placeholder="ex: 150000">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Téléphone</label>
                                    <input type="text" class="form-control" name="telephone">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Email</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Profil (type d'utilisateur) *</label>
                                    <select name="user_type" class="form-control populate" data-plugin-selectTwo required>
                                        <option value="">— Choisir —</option>
                                        <option value="technologie">Technologie</option>
                                        <option value="secretariat">Secrétariat</option>
                                        <option value="caisse">Caisse</option>
                                        <option value="boutique">Boutique</option>
                                        <option value="comptabilite">Comptabilité</option>
                                        <option value="logistique">Logistique</option>
                                        <option value="ophtalmologue">Ophtalmologue</option>
                                        <option value="infirmier">Infirmier</option>
                                        <option value="optometriste">Optométriste</option>
                                        <option value="medecin">Médecin</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3"></div>

                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Poste</label>
                                    <input type="text" class="form-control" name="poste">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Service</label>
                                    <select name="service_id" class="form-control populate" data-plugin-selectTwo>
                                        <option value="0">— Choisir —</option>
                                        <?php
                                            foreach ($servicesOrg as $row) {
                                                $idOrg = (int)($row['id_organigramme'] ?? 0);
                                                $celulle = (string)($row['celulle'] ?? '');
                                                $departement = (string)($row['departement'] ?? '');
                                                if ($idOrg <= 0 || trim($celulle) === '') { continue; }
                                                $label = trim($departement) !== '' ? (trim($departement) . ' - ' . trim($celulle)) : trim($celulle);
                                                echo '<option value="' . h((string)$idOrg) . '">' . h($label) . '</option>';
                                            }
                                        ?>
                                    </select>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label class="col-form-label">Adresse</label>
                                    <input type="text" class="form-control" name="adresse">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Supérieur hiérarchique</label>
                                    <select name="superieur_hierarchique" class="form-control" data-plugin-selectTwo>
                                        <option value="0">— Aucun —</option>
                                        <?php foreach ($superieurs as $s): ?>
                                            <?php
                                                $sid = (int)($s['id_employe'] ?? 0);
                                                $slabel = (string)($s['nom_employe'] ?? '');
                                                if ($sid <= 0 || $slabel === '') { continue; }
                                            ?>
                                            <option value="<?php echo h((string)$sid); ?>"><?php echo h($slabel); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Photo d'identité</label>
                                    <input type="file" class="form-control" name="photo" accept="image/png,image/jpeg">
                                    <small class="text-muted">Formats: JPG/PNG</small>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label class="col-form-label">Notes</label>
                                    <textarea class="form-control" name="notes" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-bs-dismiss="modal">Fermer</button>
                            <button type="submit" class="btn btn-primary">Ajouter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['add']) && (int)$_GET['add'] === 1): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var el = document.getElementById('modalAddEmploye');
                    if (!el || typeof bootstrap === 'undefined') return;
                    var modal = bootstrap.Modal.getOrCreateInstance(el);
                    modal.show();
                });
            </script>
        <?php endif; ?>

        <?php include('../PUBLIC/footer.php'); ?>
</body>
