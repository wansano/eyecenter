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
        'lieu_naissance' => isset($fields['lieuNaissance']) ? 'lieuNaissance' : (isset($fields['lieu_naissance']) ? 'lieu_naissance' : null),
        'nin' => isset($fields['nin']) ? 'nin' : (isset($fields['nni']) ? 'nni' : (isset($fields['NNI']) ? 'NNI' : null)),
        'expiration_nin' => isset($fields['expirationNin']) ? 'expirationNin' : (isset($fields['expiration_nin']) ? 'expiration_nin' : null),
        'nationalite' => isset($fields['nationalite']) ? 'nationalite' : (isset($fields['nationalite_employe']) ? 'nationalite_employe' : null),
        'engagement' => isset($fields['engagement']) ? 'engagement' : null,
        'type_contrat' => isset($fields['typeContrat']) ? 'typeContrat' : (isset($fields['type_contrat']) ? 'type_contrat' : null),
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
    if ($err === 16) $alert = ['type' => 'danger', 'message' => "La durée d'engagement doit être un nombre entier (en jours)."];
    if ($err === 17) $alert = ['type' => 'danger', 'message' => "La date d'expiration du NIN est invalide."];
    if ($err === 18) $alert = ['type' => 'danger', 'message' => "Le type de contrat est invalide."];
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
    $sexe = isset($_POST['sexe']) ? (int)$_POST['sexe'] : null;
    $dateNaissance = trim((string)($_POST['date_naissance'] ?? ''));
    $lieuNaissance = trim((string)($_POST['lieu_naissance'] ?? ''));
    $nin = trim((string)($_POST['nin'] ?? ''));
    $expirationNin = trim((string)($_POST['expiration_nin'] ?? ''));
    $nationalite = trim((string)($_POST['nationalite'] ?? ''));
    $engagement = trim((string)($_POST['engagement'] ?? ''));
    $typeContratRaw = trim((string)($_POST['type_contrat'] ?? ''));
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

    // Sexe (1 = Homme/Monsieur, 0 = Femme/Madame)
    if ($sexe !== null && $sexe !== 0 && $sexe !== 1) {
        $sexe = null;
    }

    // Service depuis organigramme (on stocke l'ID)
    try {
        if ($serviceId > 0) {
            $stmt = $bdd->prepare('SELECT celulle FROM organigramme WHERE id_organigramme = ? LIMIT 1');
            $stmt->execute([$serviceId]);
            $rowSvc = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rowSvc || trim((string)($rowSvc['celulle'] ?? '')) === '') {
                redirectWith(['err' => 9, 'edit' => $idEmploye]);
            }

            // On enregistre l'ID, pas le libellé
            $service = (string)$serviceId;
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

    // Champs contrat (optionnels selon schéma)
    $lieuNaissanceDb = ($lieuNaissance !== '' ? $lieuNaissance : null);
    $ninDb = ($nin !== '' ? $nin : null);

    $nationaliteDb = null;
    if ($nationalite !== '') {
        $nationaliteDb = function_exists('mb_substr') ? mb_substr($nationalite, 0, 100, 'UTF-8') : substr($nationalite, 0, 100);
    }

    $expirationNinDb = null;
    if ($expirationNin !== '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expirationNin)) {
            redirectWith(['err' => 17, 'edit' => $idEmploye]);
        }
        $expirationNinDb = $expirationNin;
    }

    $engagementDb = null;
    if ($engagement !== '') {
        if (!preg_match('/^\d+$/', $engagement)) {
            redirectWith(['err' => 16, 'edit' => $idEmploye]);
        }
        $engagementDb = (int)$engagement;
    }

    $typeContratDb = null;
    if ($typeContratRaw !== '') {
        if ($typeContratRaw !== '0' && $typeContratRaw !== '1') {
            redirectWith(['err' => 18, 'edit' => $idEmploye]);
        }
        $typeContratDb = (int)$typeContratRaw;
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

        if ($empCols['sexe'] !== null) {
            $sql .= ', `' . $empCols['sexe'] . '` = ?';
            $params[] = $sexe;
        }

        if ($empCols['lieu_naissance'] !== null) {
            $sql .= ', `' . $empCols['lieu_naissance'] . '` = ?';
            $params[] = $lieuNaissanceDb;
        }
        if ($empCols['nin'] !== null) {
            $sql .= ', `' . $empCols['nin'] . '` = ?';
            $params[] = $ninDb;
        }
        if ($empCols['expiration_nin'] !== null) {
            $sql .= ', `' . $empCols['expiration_nin'] . '` = ?';
            $params[] = $expirationNinDb;
        }
        if ($empCols['nationalite'] !== null) {
            $sql .= ', `' . $empCols['nationalite'] . '` = ?';
            $params[] = $nationaliteDb;
        }
        if ($empCols['engagement'] !== null) {
            $sql .= ', `' . $empCols['engagement'] . '` = ?';
            $params[] = $engagementDb;
        }

        if ($empCols['type_contrat'] !== null) {
            $sql .= ', `' . $empCols['type_contrat'] . '` = ?';
            $params[] = $typeContratDb;
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
    $lieuNaissance = trim((string)($_POST['lieu_naissance'] ?? ''));
    $nin = trim((string)($_POST['nin'] ?? ''));
    $expirationNin = trim((string)($_POST['expiration_nin'] ?? ''));
    $nationalite = trim((string)($_POST['nationalite'] ?? ''));
    $engagement = trim((string)($_POST['engagement'] ?? ''));
    $typeContratRaw = trim((string)($_POST['type_contrat'] ?? ''));
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

    // Utilisé dans les blocs catch (nettoyage), même si une exception survient tôt.
    $photoPath = null;

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

    // Champs contrat
    $lieuNaissanceDb = ($lieuNaissance !== '' ? $lieuNaissance : null);
    $ninDb = ($nin !== '' ? $nin : null);

    $nationaliteDb = null;
    if ($nationalite !== '') {
        $nationaliteDb = function_exists('mb_substr') ? mb_substr($nationalite, 0, 100, 'UTF-8') : substr($nationalite, 0, 100);
    }

    $expirationNinDb = null;
    if ($expirationNin !== '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expirationNin)) {
            redirectWith(['err' => 17, 'add' => 1]);
        }
        $expirationNinDb = $expirationNin;
    }

    $engagementDb = null;
    if ($engagement !== '') {
        if (!preg_match('/^\d+$/', $engagement)) {
            redirectWith(['err' => 16, 'add' => 1]);
        }
        $engagementDb = (int)$engagement;
    }

    $typeContratDb = null;
    if ($typeContratRaw !== '') {
        if ($typeContratRaw !== '0' && $typeContratRaw !== '1') {
            redirectWith(['err' => 18, 'add' => 1]);
        }
        $typeContratDb = (int)$typeContratRaw;
    }

    try {
        // Service depuis organigramme (on stocke l'ID dans employes.service)
        if ($serviceId > 0) {
            $stmt = $bdd->prepare('SELECT celulle FROM organigramme WHERE id_organigramme = ? LIMIT 1');
            $stmt->execute([$serviceId]);
            $rowSvc = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rowSvc || trim((string)($rowSvc['celulle'] ?? '')) === '') {
                redirectWith(['err' => 9, 'add' => 1]);
            }

            // On enregistre l'ID, pas le libellé
            $service = (string)$serviceId;
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

        if ($empCols['lieu_naissance'] !== null) {
            $columns[] = '`' . $empCols['lieu_naissance'] . '`';
            $placeholders[] = ':lieu_naissance';
            $paramsEmp[':lieu_naissance'] = $lieuNaissanceDb;
        }
        if ($empCols['nin'] !== null) {
            $columns[] = '`' . $empCols['nin'] . '`';
            $placeholders[] = ':nin';
            $paramsEmp[':nin'] = $ninDb;
        }
        if ($empCols['expiration_nin'] !== null) {
            $columns[] = '`' . $empCols['expiration_nin'] . '`';
            $placeholders[] = ':expiration_nin';
            $paramsEmp[':expiration_nin'] = $expirationNinDb;
        }
        if ($empCols['nationalite'] !== null) {
            $columns[] = '`' . $empCols['nationalite'] . '`';
            $placeholders[] = ':nationalite';
            $paramsEmp[':nationalite'] = $nationaliteDb;
        }
        if ($empCols['engagement'] !== null) {
            $columns[] = '`' . $empCols['engagement'] . '`';
            $placeholders[] = ':engagement';
            $paramsEmp[':engagement'] = $engagementDb;
        }

        if ($empCols['type_contrat'] !== null) {
            $columns[] = '`' . $empCols['type_contrat'] . '`';
            $placeholders[] = ':type_contrat';
            $paramsEmp[':type_contrat'] = $typeContratDb;
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
$filterService = isset($_GET['service']) ? (int) $_GET['service'] : 0;

// Options filtres (années + services)
$filterYears = [];
$filterServices = [];
try {
    $stY = $bdd->query('SELECT DISTINCT YEAR(date_embauche) AS y FROM employes WHERE date_embauche IS NOT NULL ORDER BY y DESC');
    $filterYears = $stY ? $stY->fetchAll(PDO::FETCH_COLUMN, 0) : [];
} catch (Throwable $e) {
    $filterYears = [];
}
foreach ($servicesOrg as $row) {
    $idOrg = (int)($row['id_organigramme'] ?? 0);
    $label = trim((string)($row['celulle'] ?? ''));
    if ($idOrg <= 0 || $label === '') { continue; }
    $filterServices[] = ['id' => $idOrg, 'label' => $label];
}

try {
    $nameCol = $empCols['name'];
    $salaryCol = $empCols['salary'];
    $sNameExpr = 's.`' . $nameCol . '`';
    $primeTransportExpr = $empCols['prime_transport'] !== null ? ('e.`' . $empCols['prime_transport'] . '`') : 'NULL';
    $primeLogementExpr = $empCols['prime_logement'] !== null ? ('e.`' . $empCols['prime_logement'] . '`') : 'NULL';
    $primeVieExpr = $empCols['prime_vie'] !== null ? ('e.`' . $empCols['prime_vie'] . '`') : 'NULL';
    $sexeExpr = $empCols['sexe'] !== null ? ('e.`' . $empCols['sexe'] . '`') : 'NULL';
    $lieuNaissanceExpr = $empCols['lieu_naissance'] !== null ? ('e.`' . $empCols['lieu_naissance'] . '`') : 'NULL';
    $ninExpr = $empCols['nin'] !== null ? ('e.`' . $empCols['nin'] . '`') : 'NULL';
    $expirationNinExpr = $empCols['expiration_nin'] !== null ? ('e.`' . $empCols['expiration_nin'] . '`') : 'NULL';
    $nationaliteExpr = $empCols['nationalite'] !== null ? ('e.`' . $empCols['nationalite'] . '`') : 'NULL';
    $engagementExpr = $empCols['engagement'] !== null ? ('e.`' . $empCols['engagement'] . '`') : 'NULL';
    $typeContratExpr = $empCols['type_contrat'] !== null ? ('e.`' . $empCols['type_contrat'] . '`') : 'NULL';

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
    if ($filterService > 0) {
        // Compat: ancien stockage (libellé) + nouveau (ID)
        $where[] = '(e.service = ? OR oName.id_org = ?)';
        $params[] = (string)$filterService;
        $params[] = (int)$filterService;
    }

    $sql = 'SELECT e.id_employe, e.`' . $nameCol . '` AS nom_employe,
                 ' . $sexeExpr . ' AS sexe,
                 e.date_naissance,
                 ' . $lieuNaissanceExpr . ' AS lieu_naissance,
                 ' . $ninExpr . ' AS nin,
                 ' . $expirationNinExpr . ' AS expiration_nin,
                 ' . $nationaliteExpr . ' AS nationalite,
                 ' . $engagementExpr . ' AS engagement,
                 ' . $typeContratExpr . ' AS type_contrat,
                 e.adresse, e.telephone, e.email, e.date_embauche, e.poste,
                     COALESCE(oId.celulle, oName.celulle, e.service) AS service,
                     COALESCE(oId.id_organigramme, oName.id_org, 0) AS service_id,
                     e.`' . $salaryCol . '` AS salaire, ' . $primeTransportExpr . ' AS prime_transport, ' . $primeLogementExpr . ' AS prime_logement, ' . $primeVieExpr . ' AS prime_vie,
                     e.status, e.superieur_hierarchique, e.notes, e.photo,
                     ' . $sNameExpr . ' AS superieur_nom
            FROM employes e
            LEFT JOIN employes s ON s.id_employe = e.superieur_hierarchique
            LEFT JOIN organigramme oId ON oId.id_organigramme = e.service
            LEFT JOIN (
                SELECT celulle, MIN(id_organigramme) AS id_org
                FROM organigramme
                GROUP BY celulle
            ) oName ON oName.celulle = e.service';
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
                                            <?php foreach ($filterServices as $s): $sid = (int)($s['id'] ?? 0); $sl = (string)($s['label'] ?? ''); ?>
                                                <?php if ($sid <= 0 || trim($sl) === '') continue; ?>
                                                <option value="<?php echo (int)$sid; ?>" <?php echo ((int)$filterService === (int)$sid) ? 'selected' : ''; ?>>
                                                    <?php echo h($sl); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-sm-2 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">Filtrer</button>
                                        <a href="listeemployes.php" class="btn btn-warning">Effacer</a>
                                    </div>
                                </form>

                                <div>
                                    <button type="button" class="btn btn-default" data-bs-toggle="modal" data-bs-target="#modalPrintEmployes" onclick="openPrintEmployes()"><i class="fa fa-print"></i> Imprimer la liste</button>
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

                                                $serviceIdRow = (int)($r['service_id'] ?? 0);
                                                $serviceLabel = '';
                                                if ($serviceIdRow > 0) {
                                                    $serviceLabel = (string)service($serviceIdRow);
                                                }
                                                if (trim($serviceLabel) === '') {
                                                    $serviceLabel = (string)($r['service'] ?? '—');
                                                }
                                            ?>
                                            <tr>
                                                <td><?php echo h($r['id_employe'] ?? ''); ?></td>
                                                <td><?php echo h($r['nom_employe'] ?? ''); ?></td>
                                                <td><?php echo h($r['telephone'] ?? '—'); ?></td>
                                                <td><?php echo h($r['email'] ?? '—'); ?></td>
                                                <td><?php echo h($r['poste'] ?? '—'); ?></td>
                                                <td><?php echo h($serviceLabel); ?></td>
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
                                                        data-sexe="<?php echo h($r['sexe'] ?? ''); ?>"
                                                        data-date_naissance="<?php echo h($r['date_naissance'] ?? ''); ?>"
                                                        data-lieu_naissance="<?php echo h($r['lieu_naissance'] ?? ''); ?>"
                                                        data-nin="<?php echo h($r['nin'] ?? ''); ?>"
                                                        data-expiration_nin="<?php echo h($r['expiration_nin'] ?? ''); ?>"
                                                        data-nationalite="<?php echo h($r['nationalite'] ?? ''); ?>"
                                                        data-engagement="<?php echo h($r['engagement'] ?? ''); ?>"
                                                        data-type_contrat="<?php echo h($r['type_contrat'] ?? ''); ?>"
                                                        data-adresse="<?php echo h($r['adresse'] ?? ''); ?>"
                                                        data-telephone="<?php echo h($r['telephone'] ?? ''); ?>"
                                                        data-email="<?php echo h($r['email'] ?? ''); ?>"
                                                        data-date_embauche="<?php echo h($r['date_embauche'] ?? ''); ?>"
                                                        data-poste="<?php echo h($r['poste'] ?? ''); ?>"
                                                        data-service_id="<?php echo h($r['service_id'] ?? '0'); ?>"
                                                        data-service="<?php echo h($serviceLabel); ?>"
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
                                        <th>Matricule</th>
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
                                        <th>Sexe</th>
                                        <td><span id="detail_sexe"></span></td>
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
                                        <th>Lieu de naissance</th>
                                        <td><span id="detail_lieu_naissance"></span></td>
                                    </tr>
                                    <tr>
                                        <th>NIN</th>
                                        <td><span id="detail_nin"></span></td>
                                    </tr>
                                    <tr>
                                        <th>Expiration NIN</th>
                                        <td><span id="detail_expiration_nin"></span></td>
                                    </tr>
                                    <tr>
                                        <th>Nationalité</th>
                                        <td><span id="detail_nationalite"></span></td>
                                    </tr>
                                    <tr>
                                        <th>Engagement (jours)</th>
                                        <td><span id="detail_engagement"></span></td>
                                    </tr>
                                    <tr>
                                        <th>Type de contrat</th>
                                        <td><span id="detail_type_contrat"></span></td>
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
                        <button type="button" class="btn btn-primary" id="btn_open_edit_from_details">Modifier</button>
                        <a class="btn btn-secondary" id="btn_open_docs_from_details" target="_blank" rel="noopener" href="#">Documents archivés</a>
                        <button type="button" class="btn btn-default" id="btn_open_badge_from_details" style="display:none;">Badge</button>
                        <button type="button" class="btn btn-dark" id="btn_open_contrat_from_details" style="display:none;">Engagement</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Impression liste employés -->
        <div class="modal fade" id="modalPrintEmployes" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Impression de la liste des employés</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" style="min-height:70vh;">
                        <iframe id="printEmployesFrame" title="Liste employés" style="width:100%; height:65vh; border:1px solid #e5e5e5;"></iframe>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-bs-dismiss="modal">Fermer</button>
                        <button type="button" class="btn btn-primary" id="btnPrintEmployes"><i class="fa fa-print"></i> Imprimer</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Contrat de travail (Engagement) -->
        <div class="modal fade" id="modalContratTravail" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Contrat de travail</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" style="min-height:70vh;">
                        <iframe id="contratTravailFrame" title="Contrat de travail" style="width:100%; height:65vh; border:1px solid #e5e5e5;"></iframe>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-bs-dismiss="modal">Fermer</button>
                        <button type="button" class="btn btn-primary" id="btnPrintContratTravail"><i class="fa fa-print"></i> Imprimer</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Badge employé (image) -->
        <div class="modal fade" id="modalBadgeEmploye" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Badge employé</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex justify-content-center">
                            <img id="badgeEmployeImg" alt="Badge employé" style="width: 520px; max-width: 100%; border:1px solid #ddd; background:#fff;" />
                        </div>
                        <div class="text-muted small mt-2 text-center">Format 85,60 × 53,98 mm.</div>
                    </div>
                    <div class="modal-footer">
                        <a class="btn btn-default" id="badgeEmployeOpen" target="_blank" rel="noopener">Aperçu</a>
                        <button type="button" class="btn btn-primary" id="btnPrintBadge"><i class="fa fa-print"></i> Imprimer</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Fermer</button>
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
                                <?php if ($empCols['sexe'] !== null): ?>
                                    <div class="col-md-4 mb-3">
                                        <label class="col-form-label">Sexe</label>
                                        <select name="sexe" class="form-control" id="edit_sexe">
                                            <option value="">—</option>
                                            <option value="1">Monsieur</option>
                                            <option value="0">Madame</option>
                                        </select>
                                    </div>
                                <?php else: ?>
                                    <div class="col-md-4 mb-3"></div>
                                <?php endif; ?>
                                <div class="col-md-4 mb-3">
                                    <label class="col-form-label">Date d'embauche</label>
                                    <input type="date" class="form-control" name="date_embauche" id="edit_date_embauche">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Lieu de naissance</label>
                                    <input type="text" class="form-control" name="lieu_naissance" id="edit_lieu_naissance" placeholder="ex: Conakry">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">NIN</label>
                                    <input type="text" class="form-control" name="nin" id="edit_nin" placeholder="Numéro d'identité">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Expiration NIN</label>
                                    <input type="date" class="form-control" name="expiration_nin" id="edit_expiration_nin">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Nationalité</label>
                                    <input type="text" class="form-control" name="nationalite" id="edit_nationalite" placeholder="ex: Guinéenne">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Engagement (jours)</label>
                                    <input type="number" min="0" step="1" class="form-control" name="engagement" id="edit_engagement" placeholder="ex: 90">
                                </div>
                                <?php if ($empCols['type_contrat'] !== null): ?>
                                    <div class="col-md-6 mb-3">
                                        <label class="col-form-label">Type de contrat</label>
                                        <select name="type_contrat" class="form-control" id="edit_type_contrat">
                                            <option value="">—</option>
                                            <option value="1">CDD (Durée déterminée)</option>
                                            <option value="0">CDI (Durée indéterminée)</option>
                                        </select>
                                    </div>
                                <?php else: ?>
                                    <div class="col-md-6 mb-3"></div>
                                <?php endif; ?>
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

                                <div class="col-md-4 mb-3">
                                    <label class="col-form-label">Téléphone</label>
                                    <input type="text" class="form-control" name="telephone" id="edit_telephone">
                                </div>
                                <div class="col-md-4 mb-3">
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

            function openContratTravailById(idEmploye) {
                var frame = document.getElementById('contratTravailFrame');
                if (!frame) return;
                frame.src = '../impression/_contrat_travail.php?id_employe=' + encodeURIComponent(idEmploye) + '&t=' + Date.now();
                var el = document.getElementById('modalContratTravail');
                if (el && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getOrCreateInstance(el).show();
                }
            }

            function openBadgeById(idEmploye) {
                var img = document.getElementById('badgeEmployeImg');
                var link = document.getElementById('badgeEmployeOpen');
                if (!img || !link) return;
                var url = '../impression/_badge_employe_image.php?id_employe=' + encodeURIComponent(idEmploye) + '&t=' + Date.now();
                img.src = url;
                link.href = url;
                var el = document.getElementById('modalBadgeEmploye');
                if (el && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getOrCreateInstance(el).show();
                }
            }

            function printBadgeImage(url) {
                var w = window.open('', '_blank');
                if (!w) {
                    window.open(url, '_blank');
                    return;
                }
                w.document.open();
                w.document.write('<!doctype html><html><head><title>Badge</title>'
                    + '<style>@page{size:85.60mm 53.98mm; margin:0;} body{margin:0; display:flex; align-items:center; justify-content:center;} img{width:85.60mm; height:53.98mm; object-fit:contain;}</style>'
                    + '</head><body>'
                    + '<img src="' + url.replace(/"/g, '') + '" />'
                    + '<script>window.onload=function(){window.focus();window.print();};<\/script>'
                    + '</body></html>');
                w.document.close();
            }

            window.openPrintEmployes = function () {
                var frame = document.getElementById('printEmployesFrame');
                if (!frame) return;
                var qs = window.location.search || '';
                frame.src = '../impression/_liste_employes.php' + qs;
            }

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

                function buildMatricule(idEmploye, dateEmbauche) {
                    var id = (idEmploye == null) ? '' : String(idEmploye).trim();
                    if (!id) return '';
                    var d = (dateEmbauche == null) ? '' : String(dateEmbauche).trim();
                    var year = '';
                    // Attendu: YYYY-MM-DD (ou YYYY/MM/DD). Fallback si invalide.
                    var m = d.match(/^(\d{4})[-\/]/);
                    if (m && m[1]) {
                        year = m[1];
                    } else {
                        // Tenter via Date (si navigateur arrive à parser)
                        try {
                            var dt = new Date(d);
                            if (!isNaN(dt.getTime())) {
                                year = String(dt.getFullYear());
                            }
                        } catch (e) {}
                    }
                    if (!year) {
                        year = String((new Date()).getFullYear());
                    }
                    return 'E' + year + 'C' + id;
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
                    setValue('edit_sexe', ds.sexe);
                    setValue('edit_date_naissance', ds.date_naissance);
                    setValue('edit_lieu_naissance', ds.lieu_naissance);
                    setValue('edit_nin', ds.nin);
                    setValue('edit_expiration_nin', ds.expiration_nin);
                    setValue('edit_nationalite', ds.nationalite);
                    setValue('edit_engagement', ds.engagement);
                    setValue('edit_type_contrat', ds.type_contrat);
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

                    setText('detail_id_employe', buildMatricule(ds.id_employe, ds.date_embauche));
                    setText('detail_nom_employe', ds.nom_employe);
                    var sx = (ds.sexe === '1' || ds.sexe === 1) ? 'Monsieur' : ((ds.sexe === '0' || ds.sexe === 0) ? 'Madame' : '—');
                    setText('detail_sexe', sx === '—' ? (ds.sexe || '') : sx);
                    setText('detail_date_naissance', ds.date_naissance);
                    setText('detail_lieu_naissance', ds.lieu_naissance);
                    setText('detail_nin', ds.nin);
                    setText('detail_expiration_nin', ds.expiration_nin);
                    setText('detail_nationalite', ds.nationalite);
                    setText('detail_engagement', ds.engagement);

                    var tc = ds.type_contrat;
                    var tcLabel = '';
                    if (tc === '1' || tc === 1) tcLabel = 'CDD (Durée déterminée)';
                    else if (tc === '0' || tc === 0) tcLabel = 'CDI (Durée indéterminée)';
                    setText('detail_type_contrat', tcLabel || (tc == null ? '' : tc));
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

                    var btnDocs = document.getElementById('btn_open_docs_from_details');
                    if (btnDocs) {
                        var idEmp = ds.id_employe == null ? '' : String(ds.id_employe).trim();
                        btnDocs.href = idEmp ? ('archivagedocuments.php?id_employe=' + encodeURIComponent(idEmp)) : 'archivagedocuments.php';
                    }

                    // Si employé inactif: pas de badge
                    var btnBadge = document.getElementById('btn_open_badge_from_details');
                    if (btnBadge) {
                        var actif = (ds.status === '1' || ds.status === 1);
                        btnBadge.style.display = actif ? '' : 'none';
                    }

                    var btnContrat = document.getElementById('btn_open_contrat_from_details');
                    if (btnContrat) {
                        var actif2 = (ds.status === '1' || ds.status === 1);
                        btnContrat.style.display = actif2 ? '' : 'none';
                    }

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
                                sexe: btn.getAttribute('data-sexe'),
                                date_naissance: btn.getAttribute('data-date_naissance'),
                                lieu_naissance: btn.getAttribute('data-lieu_naissance'),
                                nin: btn.getAttribute('data-nin'),
                                expiration_nin: btn.getAttribute('data-expiration_nin'),
                                nationalite: btn.getAttribute('data-nationalite'),
                                engagement: btn.getAttribute('data-engagement'),
                                type_contrat: btn.getAttribute('data-type_contrat'),
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

                var btnBadge = document.getElementById('btn_open_badge_from_details');
                if (btnBadge) {
                    btnBadge.addEventListener('click', function () {
                        if (!lastDetailsDataset) return;
                        openBadgeById(lastDetailsDataset.id_employe);
                    });
                }

                var btnContrat = document.getElementById('btn_open_contrat_from_details');
                if (btnContrat) {
                    btnContrat.addEventListener('click', function () {
                        if (!lastDetailsDataset || typeof bootstrap === 'undefined') return;
                        var detailsEl = document.getElementById('modalDetailEmploye');
                        if (detailsEl) {
                            bootstrap.Modal.getOrCreateInstance(detailsEl).hide();
                        }
                        openContratTravailById(lastDetailsDataset.id_employe);
                    });
                }

                var btnPrintBadge = document.getElementById('btnPrintBadge');
                if (btnPrintBadge) {
                    btnPrintBadge.addEventListener('click', function () {
                        var img = document.getElementById('badgeEmployeImg');
                        if (!img || !img.src) return;
                        printBadgeImage(img.src);
                    });
                }

                var btnPrintEmployes = document.getElementById('btnPrintEmployes');
                if (btnPrintEmployes) {
                    btnPrintEmployes.addEventListener('click', function () {
                        var frame = document.getElementById('printEmployesFrame');
                        if (!frame || !frame.src) return;
                        try {
                            if (frame.contentWindow) {
                                frame.contentWindow.focus();
                                frame.contentWindow.print();
                                return;
                            }
                        } catch (e) {}
                        window.open(frame.src, '_blank');
                    });
                }

                var btnPrintContrat = document.getElementById('btnPrintContratTravail');
                if (btnPrintContrat) {
                    btnPrintContrat.addEventListener('click', function () {
                        var frame = document.getElementById('contratTravailFrame');
                        if (!frame || !frame.src) return;
                        try {
                            if (frame.contentWindow) {
                                frame.contentWindow.focus();
                                frame.contentWindow.print();
                                return;
                            }
                        } catch (e) {}
                        window.open(frame.src, '_blank');
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
                                sexe: trigger.getAttribute('data-sexe'),
                                date_naissance: trigger.getAttribute('data-date_naissance'),
                                lieu_naissance: trigger.getAttribute('data-lieu_naissance'),
                                nin: trigger.getAttribute('data-nin'),
                                expiration_nin: trigger.getAttribute('data-expiration_nin'),
                                nationalite: trigger.getAttribute('data-nationalite'),
                                engagement: trigger.getAttribute('data-engagement'),
                                type_contrat: trigger.getAttribute('data-type_contrat'),
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
                                <?php if ($empCols['sexe'] !== null): ?>
                                    <div class="col-md-4 mb-3">
                                        <label class="col-form-label">Sexe</label>
                                        <select name="sexe" class="form-control">
                                            <option value="" selected>—</option>
                                            <option value="1">Monsieur</option>
                                            <option value="0">Madame</option>
                                        </select>
                                    </div>
                                <?php else: ?>
                                    <div class="col-md-4 mb-3"></div>
                                <?php endif; ?>
                                <div class="col-md-4 mb-3">
                                    <label class="col-form-label">Date d'embauche</label>
                                    <input type="date" class="form-control" name="date_embauche">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Lieu de naissance</label>
                                    <input type="text" class="form-control" name="lieu_naissance" placeholder="ex: Conakry">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">NIN</label>
                                    <input type="text" class="form-control" name="nin" placeholder="Numéro d'identité">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Expiration NIN</label>
                                    <input type="date" class="form-control" name="expiration_nin">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Nationalité</label>
                                    <input type="text" class="form-control" name="nationalite" placeholder="ex: Guinéenne">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="col-form-label">Engagement (jours)</label>
                                    <input type="number" min="0" step="1" class="form-control" name="engagement" placeholder="ex: 90">
                                </div>
                                <?php if ($empCols['type_contrat'] !== null): ?>
                                    <div class="col-md-6 mb-3">
                                        <label class="col-form-label">Type de contrat</label>
                                        <select name="type_contrat" class="form-control">
                                            <option value="" selected>—</option>
                                            <option value="1">CDD (Durée déterminée)</option>
                                            <option value="0">CDI (Durée indéterminée)</option>
                                        </select>
                                    </div>
                                <?php else: ?>
                                    <div class="col-md-6 mb-3"></div>
                                <?php endif; ?>
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

                                <div class="col-md-4 mb-3">
                                    <label class="col-form-label">Téléphone</label>
                                    <input type="text" class="form-control" name="telephone">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="col-form-label">Email *</label>
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
