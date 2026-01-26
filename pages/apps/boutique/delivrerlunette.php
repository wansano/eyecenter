<?php
require_once('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['auth'])) {
    header('Location: ../login.php');
    exit;
}

$code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
$idMonture = isset($_GET['id_monture']) ? (int)$_GET['id_monture'] : 0;

if ($idMonture <= 0 && $code === '') {
    $_SESSION['flash_delivrance_error'] = "Monture invalide.";
    header('Location: findingproduct.php');
    exit;
}

try {
    // Résoudre la monture
    if ($idMonture <= 0) {
        $stM = $bdd->prepare('SELECT id_monture, code_monture, vendu FROM montures WHERE code_monture = ? LIMIT 1');
        $stM->execute([$code]);
        $m = $stM->fetch(PDO::FETCH_ASSOC);
        if (!$m) {
            throw new Exception("Monture introuvable.");
        }
        $idMonture = (int)($m['id_monture'] ?? 0);
        $code = (string)($m['code_monture'] ?? $code);
    } else {
        $stM = $bdd->prepare('SELECT id_monture, code_monture, vendu FROM montures WHERE id_monture = ? LIMIT 1');
        $stM->execute([$idMonture]);
        $m = $stM->fetch(PDO::FETCH_ASSOC);
        if (!$m) {
            throw new Exception("Monture introuvable.");
        }
        $code = (string)($m['code_monture'] ?? $code);
    }

    if ($idMonture <= 0) {
        throw new Exception('Monture invalide.');
    }

    // Trouver la dernière vente liée à cette monture
    $hasVpAff = true;
    if (function_exists('dbTableHasColumn')) {
        $hasVpAff = dbTableHasColumn($bdd, 'ventes_produits', 'id_affectation');
    }

    if (!$hasVpAff) {
        throw new Exception("Impossible de vérifier le paiement (colonne id_affectation manquante dans ventes_produits)." );
    }

    $stV = $bdd->prepare('SELECT id_vente, id_affectation FROM ventes_produits WHERE id_monture = ? ORDER BY id_vente DESC LIMIT 1');
    $stV->execute([$idMonture]);
    $vente = $stV->fetch(PDO::FETCH_ASSOC);
    if (!$vente) {
        throw new Exception("Aucune vente trouvée pour cette monture.");
    }

    $idAffectation = (int)($vente['id_affectation'] ?? 0);
    if ($idAffectation <= 0) {
        throw new Exception("Affectation invalide pour cette vente.");
    }

    // Vérifier le paiement (plusieurs lignes possibles dans paiements => somme)
    $stP = $bdd->prepare('SELECT COALESCE(MAX(montant),0) AS montant, COALESCE(SUM(COALESCE(montant_paye,0)),0) AS montant_paye FROM paiements WHERE id_affectation = ?');
    $stP->execute([$idAffectation]);
    $p = $stP->fetch(PDO::FETCH_ASSOC);
    if (!$p) {
        throw new Exception("Paiement introuvable pour cette vente.");
    }

    $montant = (float)($p['montant'] ?? 0);
    $montantPaye = (float)($p['montant_paye'] ?? 0);

    if ($montant > 0 && $montantPaye + 0.00001 < $montant) {
        // Pas totalement acquitté => rediriger vers formulaire ventes incomplètes
        header('Location: venteslunettes_incompletes.php?open=1&affectation=' . $idAffectation);
        exit;
    }

    // Totalement acquitté => marquer comme délivré si la colonne existe (sinon simple confirmation)
    $bdd->beginTransaction();
    try {
        // Quelques variantes possibles de colonnes selon schéma
        $cols = [];
        try {
            $stC = $bdd->query('SHOW COLUMNS FROM ventes_produits');
            while ($r = $stC->fetch(PDO::FETCH_ASSOC)) {
                if (isset($r['Field'])) {
                    $cols[(string)$r['Field']] = true;
                }
            }
        } catch (Throwable $e) {
            $cols = [];
        }

        if (isset($cols['delivre'])) {
            $bdd->prepare('UPDATE ventes_produits SET delivre = 1 WHERE id_affectation = ?')->execute([$idAffectation]);
        }
        if (isset($cols['date_delivrance'])) {
            $bdd->prepare('UPDATE ventes_produits SET date_delivrance = CURRENT_TIMESTAMP WHERE id_affectation = ?')->execute([$idAffectation]);
        }

        // Garder affectation en statut "payé/traité" (4) si applicable
        try {
            $bdd->prepare('UPDATE affectations SET status = 4 WHERE id_affectation = ?')->execute([$idAffectation]);
        } catch (Throwable $e) {
            // noop
        }

        $bdd->commit();
    } catch (Throwable $e) {
        $bdd->rollBack();
        throw $e;
    }

    $_SESSION['flash_delivrance_success'] = 'Lunette délivrée avec succès.';
    header('Location: findingproduct.php?codeproduit=' . urlencode($code) . '&delivre=1');
    exit;
} catch (Throwable $e) {
    error_log('[delivrerlunette] ' . $e->getMessage());
    $_SESSION['flash_delivrance_error'] = $e->getMessage();
    $back = 'findingproduct.php';
    if ($code !== '') {
        $back .= '?codeproduit=' . urlencode($code);
    }
    header('Location: ' . $back);
    exit;
}
