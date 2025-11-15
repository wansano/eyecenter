<?php
header('Content-Type: application/json; charset=utf-8');
include('connect.php');

try {
    $medecinId = isset($_GET['medecin']) ? (int) $_GET['medecin'] : 0;
    $date = isset($_GET['date']) ? $_GET['date'] : '';
    $rdvExclu = isset($_GET['rdv_exclu']) ? (int) $_GET['rdv_exclu'] : 0; // Pour exclure un RDV lors de la mise à jour
    $format = isset($_GET['format']) ? $_GET['format'] : 'simple'; // Format de retour

    if ($medecinId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        if ($format === 'simple') {
            echo json_encode([]);
        } else {
            echo json_encode(['success'=>false,'message'=>'Paramètres invalides','creneaux'=>[]]);
        }
        exit;
    }

    // Vérifier que la date n'est pas dans le passé
    if ($date < date('Y-m-d')) {
        if ($format === 'simple') {
            echo json_encode([]);
        } else {
            echo json_encode(['success'=>false,'message'=>'Impossible de prendre un rendez-vous dans le passé','creneaux'=>[]]);
        }
        exit;
    }

    // Créneaux déjà occupés pour ce médecin ce jour-là
    $query = "SELECT TIME(prochain_rdv) AS h FROM dmd_rendez_vous WHERE traitant = ? AND DATE(prochain_rdv) = ?";
    $params = [$medecinId, $date];
    
    // Exclure un RDV spécifique (utile pour la mise à jour)
    if ($rdvExclu > 0) {
        $query .= " AND id_rdv != ?";
        $params[] = $rdvExclu;
    }
    
    $stmt = $bdd->prepare($query);
    $stmt->execute($params);
    $pris = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $occ = array_flip($pris ?: []);

    // Génération d'une grille de créneaux (modifiable selon vos besoins)
    $plages = [
         ['09:15:00','09:30:00'], ['09:30:00','09:45:00'], ['09:45:00','10:00:00'], 
        ['10:00:00','10:15:00'], ['10:15:00','10:30:00'], ['10:30:00','10:45:00'], 
        ['10:45:00','11:00:00'], ['11:00:00','11:15:00'], ['11:30:00','11:45:00'], 
        ['11:45:00','12:00:00'], ['12:00:00','12:15:00'], ['12:15:00','12:30:00'], 
        ['12:30:00','12:45:00'], ['12:45:00','13:00:00'], ['13:00:00','13:15:00'], 
        ['13:15:00','13:30:00'], ['13:30:00','13:45:00'], ['13:45:00','14:00:00'], 
        ['14:00:00','14:15:00'], ['14:15:00','14:30:00'], ['14:30:00','14:45:00'], 
        ['14:45:00','15:00:00'], ['15:00:00','15:15:00'], ['15:15:00','15:30:00'], 
        ['15:30:00','15:45:00'], ['15:45:00','16:00:00'], ['16:00:00','16:15:00'], 
        ['16:15:00','16:30:00'], ['16:30:00','16:45:00']
    ];

    $libres = [];
    foreach ($plages as $p) {
        $start = $p[0];
        if (!isset($occ[$start])) { // créneau libre si heure de départ non occupée
            if ($format === 'simple') {
                // Format compatible avec la fonction genererCreneaux() de custom.js
                $libres[] = $date . 'T' . $start;
            } else {
                // Format détaillé pour d'autres usages
                $libres[] = [
                    'creneau' => $start,
                    'libelle' => substr($start, 0, 5),
                    'datetime' => $date . ' ' . $start
                ];
            }
        }
    }

    // Retourner le format approprié
    if ($format === 'simple') {
        // Format simple pour custom.js : tableau de chaînes
        echo json_encode($libres);
    } else {
        // Format détaillé avec métadonnées
        echo json_encode([
            'success' => true,
            'count' => count($libres),
            'creneaux' => $libres,
            'debug' => [
                'medecin' => $medecinId,
                'date' => $date,
                'rdv_exclu' => $rdvExclu,
                'creneaux_occupes' => array_keys($occ)
            ]
        ]);
    }
} catch (Throwable $e) {
    error_log('getCreneaux error: '.$e->getMessage());
    http_response_code(500);
    if ($format === 'simple') {
        echo json_encode([]);
    } else {
        echo json_encode(['success'=>false,'message'=>'Erreur serveur','creneaux'=>[]]);
    }
}
?>