<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/connect.php';
    
    $dossier = isset($_GET['dossier']) ? trim($_GET['dossier']) : (isset($_POST['dossier']) ? trim($_POST['dossier']) : '');
    if ($dossier === '') {
        echo json_encode(['success' => false, 'message' => 'Paramètre dossier manquant']);
        exit;
    }

    // Optionnel: ne garder que chiffres
    // $dossier = preg_replace('/\D+/', '', $dossier);

    $stmt = $bdd->prepare('SELECT id_patient, nom_patient, phone FROM patients WHERE id_patient = ? LIMIT 1');
    $stmt->execute([$dossier]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // Dernier rendez-vous (si existe)
        $stmtRdv = $bdd->prepare('SELECT id_rdv, prochain_rdv, status, motif, id_service, traitant FROM dmd_rendez_vous WHERE id_patient = ? ORDER BY prochain_rdv DESC LIMIT 1');
        $stmtRdv->execute([$row['id_patient']]);
        $lastRdv = $stmtRdv->fetch(PDO::FETCH_ASSOC);

        $lastRdvPayload = null;
        if ($lastRdv) {
            $status = isset($lastRdv['status']) ? (int)$lastRdv['status'] : 0;
            $dateStr = (string)($lastRdv['prochain_rdv'] ?? '');

            $state = 'inconnu';
            $stateLabel = 'Inconnu';
            try {
                if ($dateStr !== '') {
                    $rdvDate = new DateTimeImmutable($dateStr);
                    $now = new DateTimeImmutable('now');

                    if ($status >= 1) {
                        // Le RDV a été pris en charge (transmis/payant/traité)
                        $state = 'respecte';
                        $stateLabel = 'honoré';
                    } elseif ($rdvDate < $now) {
                        // RDV passé resté au statut initial
                        $state = 'non_respecte';
                        $stateLabel = 'non honoré';
                    } else {
                        $state = 'a_venir';
                        $stateLabel = 'à venir';
                    }
                }
            } catch (Throwable $e) {
                // ignore parsing error
            }

            $lastRdvPayload = [
                'id' => $lastRdv['id_rdv'],
                'date' => $dateStr,
                'status' => $status,
                'state' => $state,
                'state_label' => $stateLabel,
                'motif' => isset($lastRdv['motif']) ? (int)$lastRdv['motif'] : null,
                'service' => isset($lastRdv['id_service']) ? (int)$lastRdv['id_service'] : null,
                'medecin' => isset($lastRdv['traitant']) ? (int)$lastRdv['traitant'] : null,
            ];
        }

        echo json_encode([
            'success' => true,
            'patient' => [
                'id'   => $row['id_patient'],
                'nom'  => $row['nom_patient'] ?? '',
                'phone'=> $row['phone'] ?? ''
            ],
            'last_rdv' => $lastRdvPayload
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Dossier introuvable']);
    }
} catch (Throwable $e) {
    if (function_exists('error_log')) error_log('checkPatient.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
