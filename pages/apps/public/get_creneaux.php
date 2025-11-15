<?php
// get_creneaux.php
header('Content-Type: application/json');
include('../PUBLIC/connect.php');

$date = isset($_GET['date']) ? $_GET['date'] : null;
$medecin = isset($_GET['medecin']) ? $_GET['medecin'] : null;

if (!$date || !$medecin) {
    echo json_encode([]);
    exit;
}

// Exclure uniquement les dimanches (0)
$jour = date('w', strtotime($date));
if ($jour == 0) {
    echo json_encode([]);
    exit;
}

// Générer tous les créneaux de 30min entre 09:15 et 16:30
$creneaux = [];
$start = new DateTime($date . ' 09:15');
$end = new DateTime($date . ' 16:30');
$maxCreneaux = 20; // Limite à 10 créneaux par jour
$count = 0;
while ($start < $end && $count < $maxCreneaux) {
    $creneaux[] = $start->format('Y-m-d\TH:i');
    $start->modify('+15 minutes');
    $count++;
}

// Récupérer les créneaux déjà pris pour ce médecin ce jour-là
$stmt = $bdd->prepare('SELECT prochain_rdv FROM dmd_rendez_vous WHERE traitant = ? AND DATE(prochain_rdv) = ?');
$stmt->execute([$medecin, $date]);
$pris = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Filtrer les créneaux déjà pris
$pris_formates = array_map(function($dt) {
    $d = new DateTime($dt);
    return $d->format('Y-m-d\TH:i');
}, $pris);

$disponibles = array_values(array_diff($creneaux, $pris_formates));

echo json_encode($disponibles);
