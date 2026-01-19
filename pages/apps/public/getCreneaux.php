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

    // Dimanche : pas de prise de RDV
    $jourSemaine = (int)date('N', strtotime($date)); // 1=lundi..7=dimanche
    if ($jourSemaine === 7) {
        if ($format === 'simple') {
            echo json_encode([]);
        } else {
            echo json_encode(['success'=>false,'message'=>'Pas de rendez-vous le dimanche','creneaux'=>[]]);
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

    // Créneaux disponibles configurés par le médecin (hebdomadaire)
    // Table: creneaux_medecins (jour_semaine: 1=lundi..6=samedi)
    $plages = [];
    try {
        $stmtCfg = $bdd->prepare('SELECT heure FROM creneaux_medecins WHERE id_medecin = ? AND jour_semaine = ? AND actif = 1 ORDER BY heure');
        $stmtCfg->execute([$medecinId, $jourSemaine]);
        $heures = $stmtCfg->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($heures)) {
            foreach ($heures as $h) {
                // On garde la structure [debut, fin] pour compat (fin non utilisée ici)
                $hh = (string)$h;
                if (strlen($hh) === 5) $hh .= ':00';
                $plages[] = [$hh, $hh];
            }
        }
    } catch (Throwable $e) {
        // Si la table n'existe pas encore ou autre erreur SQL, on revient à la grille par défaut
        $plages = [];
    }
    
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