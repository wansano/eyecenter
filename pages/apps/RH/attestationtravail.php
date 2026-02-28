<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function tableExists(PDO $bdd, string $table): bool
{
    try {
        $st = $bdd->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);
        return (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        error_log('[attestationtravail] tableExists ' . $table . ': ' . $e->getMessage());
        return false;
    }
}

function getEmployesColumnMap(PDO $bdd): array
{
    $fields = [];
    try {
        $stmt = $bdd->query('SHOW COLUMNS FROM employes');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $r) {
            $f = (string) ($r['Field'] ?? '');
            if ($f !== '') {
                $fields[$f] = true;
            }
        }
    } catch (Throwable $e) {
        $fields = [];
    }

    $nameCol = isset($fields['nomEmploye']) ? 'nomEmploye' : (isset($fields['nom_employe']) ? 'nom_employe' : 'nomEmploye');
    $posteCol = isset($fields['poste']) ? 'poste' : (isset($fields['fonction']) ? 'fonction' : null);
    $dateEmbaucheCol = isset($fields['date_embauche']) ? 'date_embauche' : (isset($fields['dateEmbauche']) ? 'dateEmbauche' : null);

    return [
        'name' => $nameCol,
        'poste' => $posteCol,
        'date_embauche' => $dateEmbaucheCol,
        'status' => isset($fields['status']) ? 'status' : null,
    ];
}

function employeIsActive(PDO $bdd, int $idEmploye, ?string $statusCol): bool
{
    if ($idEmploye <= 0) return false;
    if (!$statusCol) return true;
    try {
        $st = $bdd->prepare('SELECT `' . $statusCol . '` AS st FROM employes WHERE id_employe = ? LIMIT 1');
        $st->execute([$idEmploye]);
        $v = $st->fetchColumn();
        return ((int) $v) === 1;
    } catch (Throwable $e) {
        return true;
    }
}

function getAttestationColumnMap(PDO $bdd): array
{
    $fields = [];
    $dateFinNullable = null;
    try {
        $stmt = $bdd->query('SHOW COLUMNS FROM attestations_travail');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $r) {
            $f = (string) ($r['Field'] ?? '');
            if ($f !== '') {
                $fields[$f] = true;
            }

            if ($f === 'date_fin') {
                $dateFinNullable = (($r['Null'] ?? '') === 'YES');
            }
        }
    } catch (Throwable $e) {
        $fields = [];
    }

    return [
        'type_attestation' => isset($fields['type_attestation']),
        'date_fin_nullable' => (bool) $dateFinNullable,
    ];
}

function attestation_get_employe_info(PDO $bdd, int $idEmploye, array $empCols): array
{
    if ($idEmploye <= 0) return ['poste' => '', 'date_embauche' => ''];

    $posteCol = $empCols['poste'] ?? null;
    $dateEmbaucheCol = $empCols['date_embauche'] ?? null;

    $select = 'SELECT id_employe';
    if ($posteCol) {
        $select .= ', `' . $posteCol . '` AS poste';
    } else {
        $select .= ', NULL AS poste';
    }
    if ($dateEmbaucheCol) {
        $select .= ', `' . $dateEmbaucheCol . '` AS date_embauche';
    } else {
        $select .= ', NULL AS date_embauche';
    }
    $select .= ' FROM employes WHERE id_employe = ? LIMIT 1';

    try {
        $st = $bdd->prepare($select);
        $st->execute([$idEmploye]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'poste' => trim((string) ($r['poste'] ?? '')),
            'date_embauche' => trim((string) ($r['date_embauche'] ?? '')),
        ];
    } catch (Throwable $e) {
        error_log('[attestationtravail] employe_info: ' . $e->getMessage());
        return ['poste' => '', 'date_embauche' => ''];
    }
}

function normalizeDateOrEmpty(string $raw): string
{
    $v = trim($raw);
    if ($v === '') return '';

    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $v)
        ?: DateTimeImmutable::createFromFormat('d/m/Y', $v)
        ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $v);

    return $dt ? $dt->format('Y-m-d') : '';
}

function makeReference(int $idEmploye): string
{
    // Exemple: AT-20260131-15
    return 'AT-' . date('Ymd') . '-' . (int) $idEmploye;
}

$alert = null;
$error = null;

if (!tableExists($bdd, 'attestations_travail')) {
    $error = 'La table attestations_travail est introuvable. Exécutez db/attestations_travail.sql.';
}

// Devise/Profil (pour récupérer un éventuel lieu par défaut si dispo)
$profil = getSingleRow($bdd, 'profil_entreprise') ?: [];
$defaultLieu = 'Conakry';

// Traitement POST (création / modification)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_attestation') {
        $idAttestation = (int) ($_POST['id_attestation'] ?? 0);
        $idEmploye = (int) ($_POST['id_employe'] ?? 0);

        $typeAttestation = trim((string) ($_POST['type_attestation'] ?? 'travail'));
        if ($typeAttestation !== 'travail' && $typeAttestation !== 'stage') {
            $typeAttestation = 'travail';
        }

        $reference = trim((string) ($_POST['reference'] ?? ''));

        // Poste + Date début proviennent automatiquement de la table employes
        $empCols = getEmployesColumnMap($bdd);
        $empInfo = attestation_get_employe_info($bdd, $idEmploye, $empCols);
        $poste = trim((string) ($empInfo['poste'] ?? ''));
        $dateDebut = normalizeDateOrEmpty((string) ($empInfo['date_embauche'] ?? ''));

        $dateFin = normalizeDateOrEmpty((string) ($_POST['date_fin'] ?? ''));

        $dateDelivrance = normalizeDateOrEmpty((string) ($_POST['date_delivrance'] ?? ''));
        if ($dateDelivrance === '') {
            $dateDelivrance = date('Y-m-d');
        }

        $lieu = trim((string) ($_POST['lieu'] ?? ''));
        if ($lieu === '') $lieu = $defaultLieu;

        $signataireNom = trim((string) ($_POST['signataire_nom'] ?? ''));
        $signataireFonction = trim((string) ($_POST['signataire_fonction'] ?? ''));

        $empCols = getEmployesColumnMap($bdd);
        $statusCol = $empCols['status'] ?? null;

        $attCols = getAttestationColumnMap($bdd);
        $dateFinNullable = (bool) ($attCols['date_fin_nullable'] ?? false);

        if ($idEmploye <= 0) {
            $error = 'Veuillez sélectionner un employé.';
        } elseif (!employeIsActive($bdd, $idEmploye, $statusCol)) {
            $error = 'Employé inactif : impossible de délivrer une attestation.';
        } elseif ($dateDebut === '') {
            $error = 'La date de début est introuvable dans la fiche employé (date embauche).';
        } elseif ($typeAttestation === 'stage' && $dateFin === '') {
            $error = 'Veuillez saisir la date de fin (stage).';
        } elseif ($typeAttestation === 'travail' && $dateFin === '' && !$dateFinNullable) {
            $error = 'Votre base ne permet pas une attestation sans date de fin. Exécutez l\'ALTER dans db/attestations_travail.sql (date_fin NULL).';
        } else {
            try {
                if ($reference === '') {
                    $reference = makeReference($idEmploye);
                }

                // Si dates inversées, on corrige
                if ($dateDebut !== '' && $dateFin !== '') {
                    $d1 = new DateTimeImmutable($dateDebut);
                    $d2 = new DateTimeImmutable($dateFin);
                    if ($d2 < $d1) {
                        $tmp = $dateDebut;
                        $dateDebut = $dateFin;
                        $dateFin = $tmp;
                    }
                }

                $hasType = (bool) ($attCols['type_attestation'] ?? false);

                if ($idAttestation > 0) {
                    if ($hasType) {
                        $sql = 'UPDATE attestations_travail
                                SET id_employe = ?, type_attestation = ?, reference = ?, poste = ?,
                                    date_debut = ?, date_fin = ?, date_delivrance = ?, lieu = ?,
                                    signataire_nom = ?, signataire_fonction = ?
                                WHERE id_attestation = ?';
                        $params = [
                            $idEmploye,
                            $typeAttestation,
                            $reference,
                            ($poste !== '' ? $poste : null),
                            $dateDebut,
                            ($dateFin !== '' ? $dateFin : null),
                            ($dateDelivrance !== '' ? $dateDelivrance : null),
                            ($lieu !== '' ? $lieu : null),
                            ($signataireNom !== '' ? $signataireNom : null),
                            ($signataireFonction !== '' ? $signataireFonction : null),
                            $idAttestation,
                        ];
                    } else {
                        $sql = 'UPDATE attestations_travail
                                SET id_employe = ?, reference = ?, poste = ?,
                                    date_debut = ?, date_fin = ?, date_delivrance = ?, lieu = ?,
                                    signataire_nom = ?, signataire_fonction = ?
                                WHERE id_attestation = ?';
                        $params = [
                            $idEmploye,
                            $reference,
                            ($poste !== '' ? $poste : null),
                            $dateDebut,
                            ($dateFin !== '' ? $dateFin : null),
                            ($dateDelivrance !== '' ? $dateDelivrance : null),
                            ($lieu !== '' ? $lieu : null),
                            ($signataireNom !== '' ? $signataireNom : null),
                            ($signataireFonction !== '' ? $signataireFonction : null),
                            $idAttestation,
                        ];
                    }

                    $st = $bdd->prepare($sql);
                    $st->execute($params);
                } else {
                    if ($hasType) {
                        $sql = 'INSERT INTO attestations_travail
                                    (id_employe, type_attestation, reference, poste, date_debut, date_fin, date_delivrance, lieu, signataire_nom, signataire_fonction)
                                VALUES
                                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                        $params = [
                            $idEmploye,
                            $typeAttestation,
                            $reference,
                            ($poste !== '' ? $poste : null),
                            $dateDebut,
                            ($dateFin !== '' ? $dateFin : null),
                            ($dateDelivrance !== '' ? $dateDelivrance : null),
                            ($lieu !== '' ? $lieu : null),
                            ($signataireNom !== '' ? $signataireNom : null),
                            ($signataireFonction !== '' ? $signataireFonction : null),
                        ];
                    } else {
                        $sql = 'INSERT INTO attestations_travail
                                    (id_employe, reference, poste, date_debut, date_fin, date_delivrance, lieu, signataire_nom, signataire_fonction)
                                VALUES
                                    (?, ?, ?, ?, ?, ?, ?, ?, ?)';
                        $params = [
                            $idEmploye,
                            $reference,
                            ($poste !== '' ? $poste : null),
                            $dateDebut,
                            ($dateFin !== '' ? $dateFin : null),
                            ($dateDelivrance !== '' ? $dateDelivrance : null),
                            ($lieu !== '' ? $lieu : null),
                            ($signataireNom !== '' ? $signataireNom : null),
                            ($signataireFonction !== '' ? $signataireFonction : null),
                        ];
                    }

                    $st = $bdd->prepare($sql);
                    $st->execute($params);
                }

                header('Location: attestationtravail.php?ok=1');
                exit;
            } catch (PDOException $e) {
                error_log('[attestationtravail] save: ' . $e->getMessage());
                $error = 'Une erreur est survenue lors de l\'enregistrement de l\'attestation.';
            }
        }
    }
}

if (isset($_GET['ok'])) {
    $alert = ['type' => 'success', 'message' => 'Opération effectuée avec succès.'];
}

$cols = getEmployesColumnMap($bdd);
$nameCol = $cols['name'];
$posteCol = $cols['poste'];
$dateEmbaucheCol = $cols['date_embauche'];
$statusCol = $cols['status'] ?? null;
$attCols = getAttestationColumnMap($bdd);
$hasTypeAttestation = (bool) ($attCols['type_attestation'] ?? false);

// Filtre liste: attestation vs certificat
$filtreDoc = trim((string)($_GET['filtre_doc'] ?? ''));
if ($filtreDoc !== 'attestation' && $filtreDoc !== 'certificat') {
    $filtreDoc = '';
}

// Filtre liste: date de délivrance (bornes)
$dateDelivranceDebut = normalizeDateOrEmpty((string) ($_GET['date_delivrance_debut'] ?? ''));
$dateDelivranceFin = normalizeDateOrEmpty((string) ($_GET['date_delivrance_fin'] ?? ''));
if ($dateDelivranceDebut !== '' && $dateDelivranceFin !== '') {
    try {
        $d1 = new DateTimeImmutable($dateDelivranceDebut);
        $d2 = new DateTimeImmutable($dateDelivranceFin);
        if ($d2 < $d1) {
            $tmp = $dateDelivranceDebut;
            $dateDelivranceDebut = $dateDelivranceFin;
            $dateDelivranceFin = $tmp;
        }
    } catch (Throwable $e) {
        // ignore
    }
}

// Liste employés (pour le modal)
$employes = [];
if (!$error) {
    try {
        $select = 'SELECT id_employe, `' . $nameCol . '` AS employe_nom';
        if ($posteCol) {
            $select .= ', `' . $posteCol . '` AS employe_poste';
        } else {
            $select .= ', NULL AS employe_poste';
        }
        if ($dateEmbaucheCol) {
            $select .= ', `' . $dateEmbaucheCol . '` AS employe_date_embauche';
        } else {
            $select .= ', NULL AS employe_date_embauche';
        }
        $select .= ' FROM employes';
        if ($statusCol) {
            $select .= ' WHERE `' . $statusCol . '` = 1';
        }
        $select .= ' ORDER BY `' . $nameCol . '` ASC';
        $st = $bdd->query($select);
        $employes = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        error_log('[attestationtravail] employes: ' . $e->getMessage());
        $employes = [];
    }
}

// Liste attestations
$attestations = [];
if (!$error) {
    try {
        $whereClauses = [];
        $params = [];

        // Attestation de travail = pas de date_fin ; Certificat = date_fin renseignée
        if ($filtreDoc === 'attestation') {
            $whereClauses[] = "(a.date_fin IS NULL OR a.date_fin = '' OR a.date_fin = '0000-00-00')";
        } elseif ($filtreDoc === 'certificat') {
            $whereClauses[] = "(a.date_fin IS NOT NULL AND a.date_fin <> '' AND a.date_fin <> '0000-00-00')";
        }

        // Filtre par date de délivrance (sur la partie DATE si date_delivrance contient une heure)
        if ($dateDelivranceDebut !== '') {
            $whereClauses[] = 'DATE(a.date_delivrance) >= ?';
            $params[] = $dateDelivranceDebut;
        }
        if ($dateDelivranceFin !== '') {
            $whereClauses[] = 'DATE(a.date_delivrance) <= ?';
            $params[] = $dateDelivranceFin;
        }

        $where = '';
        if (!empty($whereClauses)) {
            $where = 'WHERE ' . implode(' AND ', $whereClauses);
        }

        $sql = 'SELECT a.*, e.`' . $nameCol . '` AS employe_nom
                FROM attestations_travail a
                JOIN employes e ON e.id_employe = a.id_employe
                ' . $where . '
                ORDER BY a.created_at DESC, a.id_attestation DESC
                LIMIT 200';
        $st = $bdd->prepare($sql);
        $st->execute($params);
        $attestations = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        error_log('[attestationtravail] list: ' . $e->getMessage());
        $attestations = [];
        $error = 'Une erreur est survenue lors de la récupération des attestations.';
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
                    <h2>Attestation de travail</h2>
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
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <form method="GET" class="d-flex align-items-center" style="gap:10px;">
                                            <label class="mb-0" for="filtre_doc">Filtrer</label>
                                            <select class="form-select" name="filtre_doc" id="filtre_doc" style="min-width:220px;">
                                                <option value="" <?php echo $filtreDoc === '' ? 'selected' : ''; ?>>Tous</option>
                                                <option value="attestation" <?php echo $filtreDoc === 'attestation' ? 'selected' : ''; ?>>Attestation de travail</option>
                                                <option value="certificat" <?php echo $filtreDoc === 'certificat' ? 'selected' : ''; ?>>Certificat de travail</option>
                                            </select>

                                            <label class="mb-0" for="date_delivrance_debut">Délivrée du</label>
                                            <input type="date" class="form-control" name="date_delivrance_debut" id="date_delivrance_debut" value="<?php echo h($dateDelivranceDebut); ?>">

                                            <label class="mb-0" for="date_delivrance_fin">au</label>
                                            <input type="date" class="form-control" name="date_delivrance_fin" id="date_delivrance_fin" value="<?php echo h($dateDelivranceFin); ?>">

                                            <button type="submit" class="btn btn-primary">Filtrer</button>
                                        </form>
                                    </div>
                                <div>
                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAttestation" onclick="openCreateAttestation()">Nouvelle attestation</button>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped mb-0" id="datatable-default">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Réf</th>
                                            <th>Employé</th>
                                            <th>Poste</th>
                                            <th>Début</th>
                                            <th>Fin</th>
                                            <th>Date délivrance</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($attestations)): ?>
                                            <tr>
                                                <td colspan="8">Aucune attestation trouvée.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($attestations as $a): ?>
                                                <tr>
                                                    <td>
                                                        <?php
                                                            $t = (string)($a['type_attestation'] ?? 'travail');
                                                            if ($t !== 'stage') $t = 'travail';
                                                            if ($t === 'stage') {
                                                                echo h('Attestation de stage');
                                                            } else {
                                                                $fin = trim((string)($a['date_fin'] ?? ''));
                                                                echo h($fin === '' ? 'Attestation de Travail' : 'Certificat de travail');
                                                            }
                                                        ?>
                                                    </td>
                                                    <td><?php echo h($a['reference'] ?? ''); ?></td>
                                                    <td><?php echo h($a['employe_nom'] ?? ''); ?></td>
                                                    <td><?php echo h($a['poste'] ?? '—'); ?></td>
                                                    <td><?php echo h($a['date_debut'] ?? ''); ?></td>
                                                    <td><?php echo h($a['date_fin'] ?? ''); ?></td>
                                                    <td><?php echo h($a['date_delivrance'] ?? '—'); ?></td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAttestation"
                                                            data-attestation='<?php echo h(json_encode($a, JSON_UNESCAPED_UNICODE)); ?>'
                                                            onclick="openEditAttestation(this)">Modifier</button>
                                                        <button type="button" class="btn btn-sm btn-default" data-bs-toggle="modal" data-bs-target="#modalPrintAttestation" onclick="openPrintAttestation(<?php echo (int)($a['id_attestation'] ?? 0); ?>)">Imprimer</button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Modal impression attestation (PDF) -->
                <div class="modal fade" id="modalPrintAttestation" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Impression de l'attestation</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body" style="min-height:70vh;">
                                <iframe id="printAttestationFrame" title="Attestation de travail" style="width:100%; height:65vh; border:1px solid #e5e5e5;"></iframe>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-bs-dismiss="modal">Fermer</button>
                                <button type="button" class="btn btn-primary" id="btnPrintAttestation"><i class="fa fa-print"></i> Imprimer</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal attestation (création / modification) -->
                <div class="modal fade" id="modalAttestation" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <form method="post" id="attestationForm">
                                <input type="hidden" name="action" value="save_attestation">
                                <input type="hidden" name="id_attestation" id="id_attestation" value="">

                                <div class="modal-header">
                                    <h5 class="modal-title" id="attestationModalTitle">Nouvelle attestation</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>

                                <div class="modal-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Employé</label>
                                            <select class="form-select" name="id_employe" id="id_employe" required onchange="fillPosteFromEmploye()">
                                                <option value="">— Sélectionner —</option>
                                                <?php foreach ($employes as $e): ?>
                                                    <option
                                                        value="<?php echo h($e['id_employe'] ?? ''); ?>"
                                                        data-poste="<?php echo h($e['employe_poste'] ?? ''); ?>"
                                                        data-date_embauche="<?php echo h($e['employe_date_embauche'] ?? ''); ?>">
                                                        <?php echo h($e['employe_nom'] ?? ''); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Type d'attestation</label>
                                            <select class="form-control" name="type_attestation" id="type_attestation" required>
                                                <option value="travail">Travail</option>
                                                <option value="stage">Stage</option>
                                            </select>
                                            <?php if (!$hasTypeAttestation): ?>
                                                <small class="text-muted">Note: exécutez l'ALTER dans db/attestations_travail.sql pour enregistrer le type.</small>
                                            <?php endif; ?>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Référence</label>
                                            <input type="text" class="form-control" name="reference" id="reference" placeholder="(auto si vide)">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Poste / Fonction</label>
                                            <input type="text" class="form-control" name="poste" id="poste" placeholder="(auto)" readonly>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">Début</label>
                                            <input type="hidden" name="date_debut" id="date_debut" value="">
                                            <input type="date" class="form-control" id="date_debut_display" disabled>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">Fin</label>
                                            <input type="date" class="form-control" name="date_fin" id="date_fin">
                                            <small class="text-muted" id="dateFinHelp">(Laissez vide si l'employé travaille toujours)</small>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Lieu</label>
                                            <input type="text" class="form-control" name="lieu" id="lieu" value="<?php echo h($defaultLieu); ?>">
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Date délivrance</label>
                                            <input type="date" class="form-control" name="date_delivrance" id="date_delivrance" value="<?php echo h(date('Y-m-d')); ?>">
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Signataire</label>
                                            <input type="text" class="form-control" name="signataire_nom" id="signataire_nom" placeholder="Ex: Dr BAH Thierno Madjou">
                                        </div>

                                        <div class="col-md-12">
                                            <label class="form-label">Fonction du signataire</label>
                                            <input type="text" class="form-control" name="signataire_fonction" id="signataire_fonction" placeholder="Ex: Directeur Général">
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

            </section>
        </div>
    </section>

    <script>
        function updateDateFinRequirement() {
            var typeEl = document.getElementById('type_attestation');
            var finEl = document.getElementById('date_fin');
            var helpEl = document.getElementById('dateFinHelp');
            if (!typeEl || !finEl) return;

            var t = (typeEl.value === 'stage') ? 'stage' : 'travail';
            if (t === 'stage') {
                finEl.required = true;
                if (helpEl) helpEl.textContent = '(Obligatoire pour une attestation de stage)';
            } else {
                finEl.required = false;
                if (helpEl) helpEl.textContent = "(Laissez vide si l'employé travaille toujours ; renseignez pour un certificat de travail)";
            }
        }

        function fillPosteFromEmploye(){
            var sel = document.getElementById('id_employe');
            var poste = document.getElementById('poste');
            var dateDebutHidden = document.getElementById('date_debut');
            var dateDebutDisplay = document.getElementById('date_debut_display');
            if (!sel || !poste) return;
            if (!sel.value) return;
            var opt = sel.selectedOptions && sel.selectedOptions.length ? sel.selectedOptions[0] : null;
            var p = opt ? (opt.getAttribute('data-poste') || '') : '';
            var d = opt ? (opt.getAttribute('data-date_embauche') || '') : '';

            if (p.trim() !== '') {
                poste.value = p;
            }
            if (dateDebutHidden && dateDebutDisplay) {
                // Date d'embauche -> date début
                if (d && d.length >= 10) {
                    var dd = String(d).slice(0, 10);
                    dateDebutHidden.value = dd;
                    dateDebutDisplay.value = dd;
                }
            }
        }

        function openCreateAttestation(){
            document.getElementById('attestationModalTitle').textContent = 'Nouvelle attestation';
            document.getElementById('id_attestation').value = '';
            document.getElementById('id_employe').value = '';
            document.getElementById('type_attestation').value = 'travail';
            document.getElementById('reference').value = '';
            document.getElementById('poste').value = '';
            document.getElementById('date_debut').value = '';
            var dd = document.getElementById('date_debut_display');
            if (dd) dd.value = '';
            document.getElementById('date_fin').value = '';
            document.getElementById('lieu').value = <?php echo json_encode($defaultLieu); ?>;
            document.getElementById('date_delivrance').value = <?php echo json_encode(date('Y-m-d')); ?>;
            document.getElementById('signataire_nom').value = '';
            document.getElementById('signataire_fonction').value = '';

            updateDateFinRequirement();
        }

        function openEditAttestation(btn){
            const raw = btn.getAttribute('data-attestation') || '{}';
            let a = {};
            try { a = JSON.parse(raw); } catch (e) { a = {}; }

            document.getElementById('attestationModalTitle').textContent = 'Modifier attestation';
            document.getElementById('id_attestation').value = a.id_attestation || '';
            document.getElementById('id_employe').value = a.id_employe || '';
            document.getElementById('type_attestation').value = (a.type_attestation === 'stage' ? 'stage' : 'travail');
            document.getElementById('reference').value = a.reference || '';
            document.getElementById('poste').value = a.poste || '';
            document.getElementById('date_debut').value = (a.date_debut || '').slice(0,10);
            var dd = document.getElementById('date_debut_display');
            if (dd) dd.value = (a.date_debut || '').slice(0,10);
            document.getElementById('date_fin').value = (a.date_fin || '').slice(0,10);
            document.getElementById('date_delivrance').value = (a.date_delivrance || '').slice(0,10) || <?php echo json_encode(date('Y-m-d')); ?>;
            document.getElementById('lieu').value = a.lieu || <?php echo json_encode($defaultLieu); ?>;
            document.getElementById('signataire_nom').value = a.signataire_nom || '';
            document.getElementById('signataire_fonction').value = a.signataire_fonction || '';

            // Re-synchroniser à partir de l'employé (poste + date embauche) si possible
            fillPosteFromEmploye();

            updateDateFinRequirement();
        }

        function openPrintAttestation(idAttestation){
            const id = parseInt(idAttestation || 0, 10);
            const url = '../impression/_attestation_travail.php?id=' + encodeURIComponent(id);
            const frame = document.getElementById('printAttestationFrame');
            if (frame) frame.src = url;
        }

        document.addEventListener('DOMContentLoaded', function(){
            var typeEl = document.getElementById('type_attestation');
            if (typeEl) {
                typeEl.addEventListener('change', updateDateFinRequirement);
            }
            updateDateFinRequirement();

            const modalEl = document.getElementById('modalPrintAttestation');
            if (modalEl) {
                modalEl.addEventListener('hidden.bs.modal', function(){
                    const frame = document.getElementById('printAttestationFrame');
                    if (frame) frame.src = '';
                });
            }

            const btnPrint = document.getElementById('btnPrintAttestation');
            if (btnPrint) {
                btnPrint.addEventListener('click', function(){
                    const frame = document.getElementById('printAttestationFrame');
                    if (!frame) return;
                    try {
                        if (frame.contentWindow) {
                            frame.contentWindow.focus();
                            frame.contentWindow.print();
                        }
                    } catch (e) {}
                });
            }
        });
    </script>

    <?php include('../PUBLIC/footer.php'); ?>
