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
        echo json_encode([
            'success' => true,
            'patient' => [
                'id'   => $row['id_patient'],
                'nom'  => $row['nom_patient'] ?? '',
                'phone'=> $row['phone'] ?? ''
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Dossier introuvable']);
    }
} catch (Throwable $e) {
    if (function_exists('error_log')) error_log('checkPatient.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
