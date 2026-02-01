<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

// Devise de l'entreprise (fallback)
if (!isset($devise) || trim((string)$devise) === '') {
    $devise = 'GNF';
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function parseMoneyToFloatOrNull(string $raw): ?float {
    $v = trim($raw);
    if ($v === '') {
        return null;
    }
    // Supporter 150 000, 150.000, 150000, 150000,00
    $v = str_replace([' ', '\u{00A0}'], '', $v);
    $v = str_replace(',', '.', $v);
    // Retirer séparateurs de milliers simples (ex: 150.000)
    if (substr_count($v, '.') > 1) {
        $v = str_replace('.', '', $v);
    }
    if (!is_numeric($v)) {
        return null;
    }
    return (float)$v;
}

function getEmployesColumnMap(PDO $bdd): array {
    $fields = [];
    try {
        $stmt = $bdd->query('SHOW COLUMNS FROM employes');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $r) {
            $f = (string)($r['Field'] ?? '');
            if ($f !== '') {
                $fields[$f] = true;
            }
        }
    } catch (PDOException $e) {
        // Fallback: on utilise les noms historiques
        $fields = [];
    }

    $nameCol = isset($fields['nomEmploye']) ? 'nomEmploye' : (isset($fields['nom_employe']) ? 'nom_employe' : 'nomEmploye');
    $salaryCol = isset($fields['salaireBase']) ? 'salaireBase' : (isset($fields['salaire']) ? 'salaire' : 'salaireBase');

    return [
        'name' => $nameCol,
        'salary' => $salaryCol,
        'sexe' => isset($fields['sexe']) ? 'sexe' : null,
        'prime_transport' => isset($fields['PrimeTransport']) ? 'PrimeTransport' : null,
        'prime_logement' => isset($fields['PrimeLogement']) ? 'PrimeLogement' : null,
        'prime_vie' => isset($fields['PrimeVie']) ? 'PrimeVie' : null,
    ];
}

function redirectWith($params) {
    $base = $_SERVER['PHP_SELF'];
    header('Location: ' . $base . (empty($params) ? '' : ('?' . http_build_query($params))));
    exit;
}

$alert = null; // ['type' => 'success|danger|warning|info', 'message' => '...']

$empCols = getEmployesColumnMap($bdd);

// Feedback PRG
if (isset($_GET['ok']) && (int)$_GET['ok'] === 1) {
    $alert = ['type' => 'success', 'message' => "Employé ajouté avec succès."];
} elseif (isset($_GET['ok']) && (int)$_GET['ok'] === 2) {
    $alert = ['type' => 'success', 'message' => "Employé modifié avec succès."];
} elseif (isset($_GET['err'])) {
    $err = (int)$_GET['err'];
    if ($err === 1) $alert = ['type' => 'danger', 'message' => "Le nom de l'employé est obligatoire."];
    if ($err === 2) $alert = ['type' => 'danger', 'message' => "Le format de l'email est invalide."];
    if ($err === 3) $alert = ['type' => 'danger', 'message' => "Cet email est déjà utilisé par un autre employé."];
    if ($err === 4) $alert = ['type' => 'danger', 'message' => "Le supérieur hiérarchique sélectionné est introuvable."];
    if ($err === 5) $alert = ['type' => 'danger', 'message' => "Le salaire / les primes doivent être des nombres."];
    if ($err === 6) $alert = ['type' => 'danger', 'message' => "Photo invalide. Formats autorisés: JPG/PNG."];
    if ($err === 7) $alert = ['type' => 'danger', 'message' => "Impossible d'enregistrer la photo (vérifier les permissions)."];
    if ($err === 8) $alert = ['type' => 'danger', 'message' => "Erreur base de données."];
    if ($err === 9) $alert = ['type' => 'danger', 'message' => "Service invalide."];
    // 10-12 réservés à l'ancienne création de compte utilisateur (désactivée)
    if ($err === 13) $alert = ['type' => 'danger', 'message' => "Employé introuvable."];
    if ($err === 14) $alert = ['type' => 'danger', 'message' => "Cet email est déjà utilisé par un autre employé."];
    // 15 réservé à l'ancienne création de compte utilisateur (désactivée)
}

// Liste des supérieurs (pour le modal)
$superieurs = [];
try {
    $nameCol = $empCols['name'];
    $stmt = $bdd->prepare('SELECT id_employe, `' . $nameCol . '` AS nom_employe FROM employes ORDER BY `' . $nameCol . '`');
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
    $dateEmbauche = trim((string)($_POST['date_embauche'] ?? ''));
    $poste = trim((string)($_POST['poste'] ?? ''));
    $serviceId = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
    $service = '';
    $salaire = trim((string)($_POST['salaire'] ?? ''));
    $primeTransport = trim((string)($_POST['prime_transport'] ?? ''));
    $primeLogement = trim((string)($_POST['prime_logement'] ?? ''));
    $primeVie = trim((string)($_POST['prime_vie'] ?? ''));
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    $superieur = isset($_POST['superieur_hierarchique']) ? (int)$_POST['superieur_hierarchique'] : 0;
    $notes = trim((string)($_POST['notes'] ?? ''));

    if ($idEmploye <= 0) {
        redirectWith(['err' => 13]);
    }
    if ($nom === '') {
        redirectWith(['err' => 1, 'edit' => $idEmploye]);
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirectWith(['err' => 2, 'edit' => $idEmploye]);
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

    // Normaliser salaire/primes
    $salaireDb = null;
    if ($salaire !== '') {
        $parsed = parseMoneyToFloatOrNull($salaire);
        if ($parsed === null) {
            redirectWith(['err' => 5, 'edit' => $idEmploye]);
        }
        $salaireDb = $parsed;
    }

    $primeTransportDb = null;
    if ($empCols['prime_transport'] !== null && $primeTransport !== '') {
        $parsed = parseMoneyToFloatOrNull($primeTransport);
        if ($parsed === null) {
            redirectWith(['err' => 5, 'edit' => $idEmploye]);
        }
        $primeTransportDb = $parsed;
    }

    $primeLogementDb = null;
    if ($empCols['prime_logement'] !== null && $primeLogement !== '') {
        $parsed = parseMoneyToFloatOrNull($primeLogement);
        if ($parsed === null) {
            redirectWith(['err' => 5, 'edit' => $idEmploye]);
        }
        $primeLogementDb = $parsed;
    }

    $primeVieDb = null;
    if ($empCols['prime_vie'] !== null && $primeVie !== '') {
        $parsed = parseMoneyToFloatOrNull($primeVie);
        if ($parsed === null) {
            redirectWith(['err' => 5, 'edit' => $idEmploye]);
        }
        $primeVieDb = $parsed;
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

        // Unicité email employes (hors courant) si l'email est renseigné
        if ($email !== '') {
            $stmt = $bdd->prepare('SELECT id_employe FROM employes WHERE email = ? AND id_employe <> ? LIMIT 1');
            $stmt->execute([$email, $idEmploye]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($newPhotoPath)) {
                    $abs = realpath(__DIR__ . '/../' . $newPhotoPath);
                    if ($abs && is_file($abs)) { @unlink($abs); }
                }
                redirectWith(['err' => 14, 'edit' => $idEmploye]);
            }
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

        $bdd->beginTransaction();

        // Update employe
        $nameCol = $empCols['name'];
        $salaryCol = $empCols['salary'];
        $sql = 'UPDATE employes SET `' . $nameCol . '` = ?, date_naissance = ?, adresse = ?, telephone = ?, email = ?, date_embauche = ?, poste = ?, service = ?, `' . $salaryCol . '` = ?';
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
        ];

        if ($empCols['prime_transport'] !== null) {
            $sql .= ', `' . $empCols['prime_transport'] . '` = ?';
            $params[] = $primeTransportDb;
        }
        if ($empCols['prime_logement'] !== null) {
            $sql .= ', `' . $empCols['prime_logement'] . '` = ?';
            $params[] = $primeLogementDb;
        }
        if ($empCols['prime_vie'] !== null) {
            $sql .= ', `' . $empCols['prime_vie'] . '` = ?';
            $params[] = $primeVieDb;
        }

        $sql .= ', status = ?, superieur_hierarchique = ?, notes = ?';
        $params[] = $status;
        $params[] = $superieurDb;
        $params[] = ($notes !== '' ? $notes : null);

        $oldPhotoPath = (string)($existing['photo'] ?? '');
        if ($newPhotoPath !== null) {
            $sql .= ', photo = ?';
            $params[] = $newPhotoPath;
        }

        $sql .= ' WHERE id_employe = ?';
        $params[] = $idEmploye;

        $stmt = $bdd->prepare($sql);
        $stmt->execute($params);

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
    $sexe = isset($_POST['sexe']) ? (int)$_POST['sexe'] : null;
    $dateNaissance = trim((string)($_POST['date_naissance'] ?? ''));
    $adresse = trim((string)($_POST['adresse'] ?? ''));
    $telephone = trim((string)($_POST['telephone'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $dateEmbauche = trim((string)($_POST['date_embauche'] ?? ''));
    $poste = trim((string)($_POST['poste'] ?? ''));
    $serviceId = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
    $service = '';
    $salaire = trim((string)($_POST['salaire'] ?? ''));
    $primeTransport = trim((string)($_POST['prime_transport'] ?? ''));
    $primeLogement = trim((string)($_POST['prime_logement'] ?? ''));
    $primeVie = trim((string)($_POST['prime_vie'] ?? ''));
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    $superieur = isset($_POST['superieur_hierarchique']) ? (int)$_POST['superieur_hierarchique'] : 0;
    $notes = trim((string)($_POST['notes'] ?? ''));

    if ($nom === '') {
        redirectWith(['err' => 1, 'add' => 1]);
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirectWith(['err' => 2, 'add' => 1]);
    }

    // Sexe (1 = Homme, 0 = Femme)
    if ($sexe !== null && $sexe !== 0 && $sexe !== 1) {
        $sexe = null;
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

        // Email unique (employes) si renseigné
        if ($email !== '') {
            $stmt = $bdd->prepare('SELECT id_employe FROM employes WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                redirectWith(['err' => 3, 'add' => 1]);
            }
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

        // Salaire/primes
        $salaireDb = null;
        if ($salaire !== '') {
            $parsed = parseMoneyToFloatOrNull($salaire);
            if ($parsed === null) {
                redirectWith(['err' => 5, 'add' => 1]);
            }
            $salaireDb = $parsed;
        }

        $primeTransportDb = null;
        if ($empCols['prime_transport'] !== null && $primeTransport !== '') {
            $parsed = parseMoneyToFloatOrNull($primeTransport);
            if ($parsed === null) {
                redirectWith(['err' => 5, 'add' => 1]);
            }
            $primeTransportDb = $parsed;
        }
        $primeLogementDb = null;
        if ($empCols['prime_logement'] !== null && $primeLogement !== '') {
            $parsed = parseMoneyToFloatOrNull($primeLogement);
            if ($parsed === null) {
                redirectWith(['err' => 5, 'add' => 1]);
            }
            $primeLogementDb = $parsed;
        }
        $primeVieDb = null;
        if ($empCols['prime_vie'] !== null && $primeVie !== '') {
            $parsed = parseMoneyToFloatOrNull($primeVie);
            if ($parsed === null) {
                redirectWith(['err' => 5, 'add' => 1]);
            }
            $primeVieDb = $parsed;
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

        // Transaction: employe
        $bdd->beginTransaction();

        $nameCol = $empCols['name'];
        $salaryCol = $empCols['salary'];

        $columns = [
            '`' . $nameCol . '`',
            // sexe est optionnel selon le schéma
            'date_naissance',
            'adresse',
            'telephone',
            'email',
            'date_embauche',
            'poste',
            'service',
            '`' . $salaryCol . '`',
        ];
        $placeholders = [
            ':nom',
            ':date_naissance',
            ':adresse',
            ':telephone',
            ':email',
            ':date_embauche',
            ':poste',
            ':service',
            ':salaire',
        ];
        $paramsEmp = [
            ':nom' => $nom,
            ':date_naissance' => ($dateNaissance !== '' ? $dateNaissance : null),
            ':adresse' => ($adresse !== '' ? $adresse : null),
            ':telephone' => ($telephone !== '' ? $telephone : null),
            ':email' => ($email !== '' ? $email : null),
            ':date_embauche' => ($dateEmbauche !== '' ? $dateEmbauche : null),
            ':poste' => ($poste !== '' ? $poste : null),
            ':service' => ($service !== '' ? $service : null),
            ':salaire' => $salaireDb,
        ];

        if ($empCols['sexe'] !== null) {
            array_splice($columns, 1, 0, ['`' . $empCols['sexe'] . '`']);
            array_splice($placeholders, 1, 0, [':sexe']);
            $paramsEmp[':sexe'] = $sexe;
        }

        if ($empCols['prime_transport'] !== null) {
            $columns[] = '`' . $empCols['prime_transport'] . '`';
            $placeholders[] = ':prime_transport';
            $paramsEmp[':prime_transport'] = $primeTransportDb;
        }
        if ($empCols['prime_logement'] !== null) {
            $columns[] = '`' . $empCols['prime_logement'] . '`';
            $placeholders[] = ':prime_logement';
            $paramsEmp[':prime_logement'] = $primeLogementDb;
        }
        if ($empCols['prime_vie'] !== null) {
            $columns[] = '`' . $empCols['prime_vie'] . '`';
            $placeholders[] = ':prime_vie';
            $paramsEmp[':prime_vie'] = $primeVieDb;
        }

        $columns[] = 'status';
        $placeholders[] = ':status';
        $paramsEmp[':status'] = $status;

        $columns[] = 'superieur_hierarchique';
        $placeholders[] = ':superieur';
        $paramsEmp[':superieur'] = $superieurDb;

        $columns[] = 'notes';
        $placeholders[] = ':notes';
        $paramsEmp[':notes'] = ($notes !== '' ? $notes : null);

        $columns[] = 'photo';
        $placeholders[] = ':photo';
        $paramsEmp[':photo'] = $photoPath;

        $stmt = $bdd->prepare('INSERT INTO employes (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')');
        $stmt->execute($paramsEmp);

        $bdd->commit();

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

// Filtres (GET)
$filterYear = isset($_GET['annee_emploi']) ? (int) $_GET['annee_emploi'] : 0;
$filterStatus = isset($_GET['statut']) ? trim((string) $_GET['statut']) : '';
$filterService = isset($_GET['service']) ? trim((string) $_GET['service']) : '';

// Options filtres (années + services)
$filterYears = [];
$filterServices = [];
try {
    $stY = $bdd->query('SELECT DISTINCT YEAR(date_embauche) AS y FROM employes WHERE date_embauche IS NOT NULL ORDER BY y DESC');
    $filterYears = $stY ? $stY->fetchAll(PDO::FETCH_COLUMN, 0) : [];
} catch (Throwable $e) {
    $filterYears = [];
}
try {
    $stS = $bdd->query("SELECT DISTINCT service FROM employes WHERE service IS NOT NULL AND TRIM(service) <> '' ORDER BY service ASC");
    $filterServices = $stS ? $stS->fetchAll(PDO::FETCH_COLUMN, 0) : [];
} catch (Throwable $e) {
    $filterServices = [];
}

try {
    $nameCol = $empCols['name'];
    $salaryCol = $empCols['salary'];
    $sNameExpr = 's.`' . $nameCol . '`';
    $primeTransportExpr = $empCols['prime_transport'] !== null ? ('e.`' . $empCols['prime_transport'] . '`') : 'NULL';
    $primeLogementExpr = $empCols['prime_logement'] !== null ? ('e.`' . $empCols['prime_logement'] . '`') : 'NULL';
    $primeVieExpr = $empCols['prime_vie'] !== null ? ('e.`' . $empCols['prime_vie'] . '`') : 'NULL';

    $where = [];
    $params = [];

    if ($filterYear > 0) {
        $where[] = 'YEAR(e.date_embauche) = ?';
        $params[] = $filterYear;
    }
    if ($filterStatus !== '' && ($filterStatus === '1' || $filterStatus === '0')) {
        $where[] = 'e.status = ?';
        $params[] = (int) $filterStatus;
    }
    if ($filterService !== '') {
        $where[] = 'e.service = ?';
        $params[] = $filterService;
    }

    $sql = 'SELECT e.id_employe, e.`' . $nameCol . '` AS nom_employe, e.date_naissance, e.adresse, e.telephone, e.email, e.date_embauche, e.poste,
                e.service, e.`' . $salaryCol . '` AS salaire, ' . $primeTransportExpr . ' AS prime_transport, ' . $primeLogementExpr . ' AS prime_logement, ' . $primeVieExpr . ' AS prime_vie,
                e.status, e.superieur_hierarchique, e.notes, e.photo,
                ' . $sNameExpr . ' AS superieur_nom,
                     o.id_org AS service_id
         FROM employes e
         LEFT JOIN employes s ON s.id_employe = e.superieur_hierarchique
         LEFT JOIN (
            SELECT celulle, MIN(id_organigramme) AS id_org
            FROM organigramme
            GROUP BY celulle
         ) o ON o.celulle = e.service';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY e.id_employe DESC';

    $stmt = $bdd->prepare($sql);
    $stmt->execute($params);
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
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <form method="get" class="row g-2 align-items-end" style="margin:0;">
                                    <div class="col-sm-3">
                                        <label class="form-label">Année d'embauche</label>
                                        <select name="annee_emploi" class="form-control">
                                            <option value="">Toutes</option>
                                            <?php foreach ($filterYears as $y): $yy = (int)$y; ?>
                                                <option value="<?php echo (int)$yy; ?>" <?php echo ((int)$filterYear === (int)$yy) ? 'selected' : ''; ?>>
                                                    <?php echo (int)$yy; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-sm-3">
                                        <label class="form-label">Statut</label>
                                        <select name="statut" class="form-control">
                                            <option value="" <?php echo ($filterStatus === '') ? 'selected' : ''; ?>>Tous</option>
                                            <option value="1" <?php echo ($filterStatus === '1') ? 'selected' : ''; ?>>Actif</option>
                                            <option value="0" <?php echo ($filterStatus === '0') ? 'selected' : ''; ?>>Inactif</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label">Service</label>
                                        <select name="service" class="form-control">
                                            <option value="">Tous</option>
                                            <?php foreach ($filterServices as $s): $sv = (string)$s; ?>
                                                <option value="<?php echo h($sv); ?>" <?php echo ($filterService !== '' && $filterService === $sv) ? 'selected' : ''; ?>>
                                                    <?php echo h($sv); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-sm-2 d-flex gap-2">
                                        <button type="submit" class="btn btn-default">Filtrer</button>
                                        <a href="listeemployes.php" class="btn btn-light">Reset</a>
                                    </div>
                                </form>

                                <div>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddEmploye">Ajouter un employé</button>
                                </div>
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
                                                        class="btn btn-sm btn-info btn-details-emp"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#modalDetailEmploye"
                                                        data-id_employe="<?php echo h($r['id_employe'] ?? ''); ?>"
                                                        data-nom_employe="<?php echo h($r['nom_employe'] ?? ''); ?>"
                                                        data-date_naissance="<?php echo h($r['date_naissance'] ?? ''); ?>"
                                                        data-adresse="<?php echo h($r['adresse'] ?? ''); ?>"
                                                        data-telephone="<?php echo h($r['telephone'] ?? ''); ?>"
                                                        data-email="<?php echo h($r['email'] ?? ''); ?>"
                                                        data-date_embauche="<?php echo h($r['date_embauche'] ?? ''); ?>"
                                                        data-poste="<?php echo h($r['poste'] ?? ''); ?>"
                                                        data-service_id="<?php echo h($r['service_id'] ?? '0'); ?>"
                                                        data-service="<?php echo h($r['service'] ?? ''); ?>"
                                                        data-salaire="<?php echo h($r['salaire'] ?? ''); ?>"
                                                        data-prime_transport="<?php echo h($r['prime_transport'] ?? ''); ?>"
                                                        data-prime_logement="<?php echo h($r['prime_logement'] ?? ''); ?>"
                                                        data-prime_vie="<?php echo h($r['prime_vie'] ?? ''); ?>"
                                                        data-status="<?php echo h($r['status'] ?? '1'); ?>"
                                                        data-superieur_hierarchique="<?php echo h($r['superieur_hierarchique'] ?? '0'); ?>"
                                                        data-superieur_nom="<?php echo h($r['superieur_nom'] ?? ''); ?>"
                                                        data-notes="<?php echo h($r['notes'] ?? ''); ?>"
                                                        data-photo="<?php echo h($r['photo'] ?? ''); ?>"
                                                    >
                                                        Détails
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
        <div class="modal fade" id="modalDetailEmploye" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Détails de l'employé</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3 text-center">
                            <img id="detail_photo" src="" alt="Photo employé" class="img-thumbnail" style="max-width: 140px; max-height: 140px; object-fit: cover; display:none;" />
                            <div id="detail_photo_placeholder" class="text-muted" style="display:none;">Aucune photo</div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <th>ID</th>
                                        <td><span id="detail_id_employe"></span></td>
                                    </tr>
                                    <tr>
                                        <th>Statut</th>
                                        <td><span id="detail_status"></span></td>
                                    </tr>
                                    <tr>
                                        <th>Nom</th>
                                        <td><span id="detail_nom_employe"></span></td>
                                    </tr>
                                    <tr>
                                        <th>Téléphone</th>
                                        <td><span id="detail_telephone"></span></td>
                                    </tr>
                                    <tr>
                                        <th>Email</th>
                                        <td><span id="detail_email"></span></td>
                                    </tr>
                                    <tr>
                                        <th>Poste</th>
                                        <td><span id="detail_poste"></span></td>
                                    </tr>
                                    <tr>
                                        <th>Service</th>
                                        <td><span id="detail_service"></span></td>
                                    </tr>
                                    <tr>
                                        <th>Date embauche</th>
                                        <td><span id="detail_date_embauche"></span></td>
                                    </tr>
                                    <tr>
                                        <th>Date naissance</th>
                                        <td><span id="detail_date_naissance"></span></td>
                                    </tr>
                                    <tr>
                                        <th>Supérieur</th>
                                        <td><span id="detail_superieur_nom"></span></td>
                                    </tr>
                                    <tr>
                                        <th>Adresse</th>
                                        <td><span id="detail_adresse"></span></td>
                                    </tr>
                                    <tr>
                                        <th>Salaire de base</th>
                                        <td><span id="detail_salaire"></span></td>
                                    </tr>
                                    <tr>
                                        <th>Prime transport</th>
                                        <td><span id="detail_prime_transport"></span></td>
                                    </tr>
                                    <tr>
                                        <th>Prime logement</th>
                                        <td><span id="detail_prime_logement"></span></td>
                                    </tr>
                                    <tr>
                                        <th>Prime vie</th>
                                        <td><span id="detail_prime_vie"></span></td>
                                    </tr>
                                    <tr>
                                        <th>Notes</th>
                                        <td><span id="detail_notes"></span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-bs-dismiss="modal">Fermer</button>
                        <button type="button" class="btn btn-primary" id="btn_open_edit_from_details">Modifier</button>
                    </div>
                </div>
            </div>
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
                                    <label class="col-form-label">Salaire de base</label>
                                    <input type="text" class="form-control" name="salaire" id="edit_salaire" placeholder="ex: 150000">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="col-form-label">Prime transport</label>
                                    <input type="text" class="form-control" name="prime_transport" id="edit_prime_transport" placeholder="ex: 10000">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="col-form-label">Prime logement</label>
                                    <input type="text" class="form-control" name="prime_logement" id="edit_prime_logement" placeholder="ex: 20000">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="col-form-label">Prime vie</label>
                                    <input type="text" class="form-control" name="prime_vie" id="edit_prime_vie" placeholder="ex: 5000">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Téléphone</label>
                                    <input type="text" class="form-control" name="telephone" id="edit_telephone">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" id="edit_email" required>
                                </div>

                                <div class="col-md-6 mb-3"></div>
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
                var lastDetailsDataset = null;
                var companyCurrency = '<?php echo h($devise ?? ''); ?>';

                function setValue(id, value) {
                    var el = document.getElementById(id);
                    if (!el) return;
                    el.value = value == null ? '' : String(value);
                }

                function setText(id, value) {
                    var el = document.getElementById(id);
                    if (!el) return;
                    el.textContent = value == null || value === '' ? '—' : String(value);
                }

                function formatAmount(value) {
                    if (value == null) return '';
                    var raw = String(value).trim();
                    if (!raw || raw === '—') return '';
                    // tolérer "150 000" / "150.000" / "150000,50"
                    raw = raw.replace(/\s+/g, '').replace(/\u00A0/g, '').replace(',', '.');
                    if ((raw.match(/\./g) || []).length > 1) {
                        raw = raw.replace(/\./g, '');
                    }
                    var n = Number(raw);
                    if (!isFinite(n)) return '';
                    var hasDecimals = Math.abs(n % 1) > 0;
                    try {
                        var formatted = new Intl.NumberFormat('fr-FR', {
                            minimumFractionDigits: hasDecimals ? 2 : 0,
                            maximumFractionDigits: hasDecimals ? 2 : 0
                        }).format(n);
                        return formatted + (companyCurrency ? ' ' + companyCurrency : '');
                    } catch (e) {
                        return String(n) + (companyCurrency ? ' ' + companyCurrency : '');
                    }
                }

                function normalizePhotoUrl(path) {
                    var p = (path || '').trim();
                    if (!p) return '';
                    if (/^(https?:)?\/\//i.test(p) || p.startsWith('/')) return p;
                    // Dans ce module, les photos sont stockées dans ../documents/...
                    if (p.startsWith('../')) return p;
                    if (p.startsWith('documents/')) return '../' + p;
                    return '../' + p;
                }

                function fillEditFromDataset(ds) {
                    if (!ds) return;
                    setValue('edit_id_employe', ds.id_employe);
                    setValue('edit_nom_employe', ds.nom_employe);
                    setValue('edit_date_naissance', ds.date_naissance);
                    setValue('edit_adresse', ds.adresse);
                    setValue('edit_telephone', ds.telephone);
                    setValue('edit_email', ds.email);
                    setValue('edit_old_email', ds.email);
                    setValue('edit_date_embauche', ds.date_embauche);
                    setValue('edit_poste', ds.poste);
                    setValue('edit_service_id', ds.service_id || '0');
                    setValue('edit_salaire', ds.salaire);
                    setValue('edit_prime_transport', ds.prime_transport);
                    setValue('edit_prime_logement', ds.prime_logement);
                    setValue('edit_prime_vie', ds.prime_vie);
                    setValue('edit_status', ds.status || '1');
                    setValue('edit_superieur_hierarchique', ds.superieur_hierarchique || '0');
                    setValue('edit_notes', ds.notes);
                }

                function fillDetailsFromDataset(ds) {
                    if (!ds) return;

                    setText('detail_id_employe', ds.id_employe);
                    setText('detail_nom_employe', ds.nom_employe);
                    setText('detail_date_naissance', ds.date_naissance);
                    setText('detail_adresse', ds.adresse);
                    setText('detail_telephone', ds.telephone);
                    setText('detail_email', ds.email);
                    setText('detail_date_embauche', ds.date_embauche);
                    setText('detail_poste', ds.poste);
                    setText('detail_service', ds.service);
                    setText('detail_superieur_nom', ds.superieur_nom);
                    setText('detail_salaire', formatAmount(ds.salaire));
                    setText('detail_prime_transport', formatAmount(ds.prime_transport));
                    setText('detail_prime_logement', formatAmount(ds.prime_logement));
                    setText('detail_prime_vie', formatAmount(ds.prime_vie));
                    setText('detail_notes', ds.notes);

                    var st = (ds.status === '1' || ds.status === 1) ? 'Actif' : 'Inactif';
                    setText('detail_status', st);

                    var img = document.getElementById('detail_photo');
                    var ph = document.getElementById('detail_photo_placeholder');
                    var url = normalizePhotoUrl(ds.photo);
                    if (img && ph) {
                        if (url) {
                            img.src = url;
                            img.style.display = '';
                            ph.style.display = 'none';
                        } else {
                            img.src = '';
                            img.style.display = 'none';
                            ph.style.display = '';
                        }
                    }
                }

                document.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('.btn-details-emp').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            lastDetailsDataset = {
                                id_employe: btn.getAttribute('data-id_employe'),
                                nom_employe: btn.getAttribute('data-nom_employe'),
                                date_naissance: btn.getAttribute('data-date_naissance'),
                                adresse: btn.getAttribute('data-adresse'),
                                telephone: btn.getAttribute('data-telephone'),
                                email: btn.getAttribute('data-email'),
                                date_embauche: btn.getAttribute('data-date_embauche'),
                                poste: btn.getAttribute('data-poste'),
                                service_id: btn.getAttribute('data-service_id'),
                                service: btn.getAttribute('data-service'),
                                salaire: btn.getAttribute('data-salaire'),
                                prime_transport: btn.getAttribute('data-prime_transport'),
                                prime_logement: btn.getAttribute('data-prime_logement'),
                                prime_vie: btn.getAttribute('data-prime_vie'),
                                status: btn.getAttribute('data-status'),
                                superieur_hierarchique: btn.getAttribute('data-superieur_hierarchique'),
                                superieur_nom: btn.getAttribute('data-superieur_nom'),
                                notes: btn.getAttribute('data-notes'),
                                photo: btn.getAttribute('data-photo')
                            };
                            fillDetailsFromDataset(lastDetailsDataset);
                        });
                    });

                    var btnOpenEdit = document.getElementById('btn_open_edit_from_details');
                    if (btnOpenEdit) {
                        btnOpenEdit.addEventListener('click', function () {
                            if (!lastDetailsDataset || typeof bootstrap === 'undefined') return;
                            fillEditFromDataset(lastDetailsDataset);
                            var detailsEl = document.getElementById('modalDetailEmploye');
                            if (detailsEl) {
                                bootstrap.Modal.getOrCreateInstance(detailsEl).hide();
                            }
                            var editEl = document.getElementById('modalEditEmploye');
                            if (editEl) {
                                bootstrap.Modal.getOrCreateInstance(editEl).show();
                            }
                        });
                    }

                    // Ouvrir automatiquement le modal d'édition si ?edit=<id>
                    var url = new URL(window.location.href);
                    var editId = url.searchParams.get('edit');
                    if (editId && typeof bootstrap !== 'undefined') {
                        var trigger = document.querySelector('.btn-details-emp[data-id_employe="' + editId.replace(/"/g, '') + '"]');
                        if (trigger) {
                            // Remplir & ouvrir directement le modal d'édition
                            lastDetailsDataset = {
                                id_employe: trigger.getAttribute('data-id_employe'),
                                nom_employe: trigger.getAttribute('data-nom_employe'),
                                date_naissance: trigger.getAttribute('data-date_naissance'),
                                adresse: trigger.getAttribute('data-adresse'),
                                telephone: trigger.getAttribute('data-telephone'),
                                email: trigger.getAttribute('data-email'),
                                date_embauche: trigger.getAttribute('data-date_embauche'),
                                poste: trigger.getAttribute('data-poste'),
                                service_id: trigger.getAttribute('data-service_id'),
                                service: trigger.getAttribute('data-service'),
                                salaire: trigger.getAttribute('data-salaire'),
                                prime_transport: trigger.getAttribute('data-prime_transport'),
                                prime_logement: trigger.getAttribute('data-prime_logement'),
                                prime_vie: trigger.getAttribute('data-prime_vie'),
                                status: trigger.getAttribute('data-status'),
                                superieur_hierarchique: trigger.getAttribute('data-superieur_hierarchique'),
                                superieur_nom: trigger.getAttribute('data-superieur_nom'),
                                notes: trigger.getAttribute('data-notes'),
                                photo: trigger.getAttribute('data-photo')
                            };
                            fillEditFromDataset(lastDetailsDataset);
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
                                    <label class="col-form-label">Salaire de base</label>
                                    <input type="text" class="form-control" name="salaire" placeholder="ex: 150000">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="col-form-label">Prime transport</label>
                                    <input type="text" class="form-control" name="prime_transport" placeholder="ex: 10000">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="col-form-label">Prime logement</label>
                                    <input type="text" class="form-control" name="prime_logement" placeholder="ex: 20000">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="col-form-label">Prime vie</label>
                                    <input type="text" class="form-control" name="prime_vie" placeholder="ex: 5000">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Téléphone</label>
                                    <input type="text" class="form-control" name="telephone">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Email</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>

                                <div class="col-md-6 mb-3"></div>
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
