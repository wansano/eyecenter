<?php
include('connect.php');
require_once('fonction.php');
session_start();

header('Content-Type: application/json; charset=utf-8');

try {
    $date = isset($_GET['date']) ? (string)$_GET['date'] : date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    $stmt = $bdd->prepare('SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status IN (1,2) THEN 1 ELSE 0 END) AS present,
        SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) AS absent,
        SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS paye,
        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS non_paye
        FROM dmd_rendez_vous
        WHERE DATE(prochain_rdv) = :d AND status IN (0,1,2)'
    );
    $stmt->execute(['d' => $date]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'date' => $date,
        'total' => isset($row['total']) ? (int)$row['total'] : 0,
        'present' => isset($row['present']) ? (int)$row['present'] : 0,
        'absent' => isset($row['absent']) ? (int)$row['absent'] : 0,
        'paye' => isset($row['paye']) ? (int)$row['paye'] : 0,
        'non_paye' => isset($row['non_paye']) ? (int)$row['non_paye'] : 0,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('getRapportRdvDuJour error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur'
    ], JSON_UNESCAPED_UNICODE);
}
