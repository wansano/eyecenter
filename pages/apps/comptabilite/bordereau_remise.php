<?php
require_once(__DIR__ . '/../public/connect.php');
require_once(__DIR__ . '/../public/fonction.php');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$idRemise = (int)($_GET['id'] ?? 0);
if ($idRemise <= 0) {
    http_response_code(400);
    echo 'Remise invalide.';
    exit;
}

try {
    $sql = "
        SELECT r.*, e.nomEmploye AS employe_nom,
               cdeb.nom_compte AS compte_debite_nom,
               ccre.nom_compte AS compte_credite_nom
        FROM remise_de_compte r
        LEFT JOIN employes e ON e.id_employe = r.id_employe
        LEFT JOIN comptes cdeb ON cdeb.id_compte = r.id_compte2
        LEFT JOIN comptes ccre ON ccre.id_compte = r.id_compte
        WHERE r.id_remise = ?
        LIMIT 1
    ";
    $st = $bdd->prepare($sql);
    $st->execute([$idRemise]);
    $remise = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[bordereau_remise] load => ' . $e->getMessage());
    $remise = null;
}

if (!$remise) {
    http_response_code(404);
    echo 'Remise introuvable.';
    exit;
}

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Bordereau de remise #<?php echo (int)$idRemise; ?></title>
    <link rel="stylesheet" href="../css/theme.css" />
    <link rel="stylesheet" href="../vendor/bootstrap/css/bootstrap.css" />
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
        }
        .ticket { max-width: 900px; margin: 24px auto; }
        .kv { display: grid; grid-template-columns: 220px 1fr; gap: 8px 16px; }
        .kv div { padding: 4px 0; }
        .kv .k { color: #444; font-weight: 600; }
        .kv .v { color: #111; }
    </style>
</head>
<body>
<div class="ticket card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h3 class="mb-1">Bordereau de remise</h3>
                <div class="text-muted">N° <?php echo (int)$remise['id_remise']; ?> — Créé le <?php echo h($remise['date_creation'] ?? ''); ?></div>
            </div>
            <div class="no-print">
                <button class="btn btn-primary" onclick="window.print()">Imprimer</button>
                <button class="btn btn-light" onclick="window.close()">Fermer</button>
            </div>
        </div>

        <hr />

        <div class="kv">
            <div class="k">Date de remise</div><div class="v"><?php echo h($remise['date_remise'] ?? ''); ?></div>
            <div class="k">Employé</div><div class="v"><?php echo h($remise['employe_nom'] ?? ''); ?></div>
            <div class="k">Montant</div><div class="v"><?php echo number_format((float)($remise['montant'] ?? 0), 0, ',', ' ') . ' GNF'; ?></div>
            <div class="k">Type remise</div><div class="v"><?php echo h($remise['type_remise'] ?? ''); ?></div>
            <div class="k">Mode paiement</div><div class="v"><?php echo h($remise['mode_paiement'] ?? ''); ?></div>
            <div class="k">Référence</div><div class="v"><?php echo h($remise['reference'] ?? ''); ?></div>
            <div class="k">Compte débité</div><div class="v"><?php echo h($remise['compte_debite_nom'] ?? ''); ?></div>
            <div class="k">Compte crédité</div><div class="v"><?php echo h($remise['compte_credite_nom'] ?? ''); ?></div>
        </div>

        <hr />

        <div>
            <div class="fw-bold mb-2">Notes</div>
            <div style="white-space: pre-wrap;"><?php echo h($remise['notes'] ?? ''); ?></div>
        </div>

        <hr />

        <div class="row">
            <div class="col-6">
                <div class="fw-bold">Signature remettant</div>
                <div style="height: 80px; border-bottom: 1px solid #bbb;"></div>
            </div>
            <div class="col-6">
                <div class="fw-bold">Signature réception</div>
                <div style="height: 80px; border-bottom: 1px solid #bbb;"></div>
            </div>
        </div>

    </div>
</div>
</body>
</html>
