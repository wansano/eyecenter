<?php
/**
 * Vérifie si un traitement existe déjà pour une affectation de consultation
 */
function checkTraitementExisteConsultation($bdd, $affectation) {
    $stmt = $bdd->prepare('SELECT COUNT(*) FROM consultations WHERE id_affectation = ?');
    $stmt->execute([$affectation]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Vérifie si un traitement existe déjà pour une affectation de chirurgie
 */
function checkTraitementExisteChirurgie($bdd, $affectation) {
    $stmt = $bdd->prepare('SELECT COUNT(*) FROM chirurgies WHERE id_affectation = ?');
    $stmt->execute([$affectation]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Vérifie si un traitement existe déjà pour une affectation de rapport medical.
 */
function checkTraitementExisteRapport($bdd, $affectation) {
    $stmt = $bdd->prepare('SELECT COUNT(*) FROM rapportements WHERE id_affectation = ?');
    $stmt->execute([$affectation]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Vérifie si un traitement existe déjà pour une affectation d'examen
 */
function checkTraitementExisteExamen($bdd, $affectation) {
    $stmt = $bdd->prepare('SELECT COUNT(*) FROM examens WHERE id_affectation = ?');
    $stmt->execute([$affectation]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Vérifie si un traitement existe déjà pour une affectation de controle
 */
function checkTraitementExisteControle($bdd, $affectation) {
    $stmt = $bdd->prepare('SELECT COUNT(*) FROM controles WHERE id_affectation = ?');
    $stmt->execute([$affectation]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Vérifie si un traitement existe déjà pour une affectation de soins
 */
function checkTraitementExisteSoins($bdd, $affectation) {
    $stmt = $bdd->prepare('SELECT COUNT(*) FROM soins WHERE id_affectation = ?');
    $stmt->execute([$affectation]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Vérifie si un traitement existe déjà pour une affectation de refraction
 */
function checkTraitementExisteMesure($bdd, $affectation) {
    $stmt = $bdd->prepare('SELECT COUNT(*) FROM mesures WHERE id_affectation = ?');
    $stmt->execute([$affectation]);
    return $stmt->fetchColumn() > 0;
}


/**
 * Insère les données d'historique
 */
function insertHistorique($bdd, $id_patient, $data) {
    $stmt = $bdd->prepare('INSERT INTO historique (motif, evolution, terrain, antecedents, id_patient) VALUES (?,?,?,?,?)');
    $stmt->execute([
        $data['motif'],
        $data['evolution'],
        $data['terrain'] ?? '',
        $data['antecedents'] ?? '',
        $id_patient
    ]);
}

/**
 * Insère les données d'acquitte visuelle
 */
function insertAcquitteVisuelle($bdd, $id_patient, $affectation, $data) {
    $stmt = $bdd->prepare('INSERT INTO acquitte_visuelle (id_patient, od_avlsc, os_avlsc, od_avc, os_avc, od_ts, os_ts, p, id_affectation) 
                          VALUES (?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $id_patient,
        $data['avlscod'],
        $data['avlscos'],
        $data['avcod'],
        $data['avcos'],
        $data['tsod'],
        $data['tsos'],
        $data['p'],
        $affectation
    ]);
}

/**
 * Insère le traitement de consultation
 */
function insertConsultation($bdd, $id_patient, $type, $affectation, $data) {
    $stmt = $bdd->prepare('INSERT INTO consultations (id_patient, id_type, id_affectation, diagnostic, bilan, traitement, prescription, traitant) 
                          VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $id_patient,
        $type,
        $affectation,
        $data['diagnostic'],
        $data['bilan'],
        $data['traitement'],
        $data['prescription'],
        $_SESSION['auth']
    ]);
}

/**
 * Insère le traitement de chirurgie
 */
function insertChirurgie($bdd, $id_patient, $type, $affectation, $data) {
    $stmt = $bdd->prepare('INSERT INTO chirurgies (id_patient, id_type, id_affectation, diagnostic, traitement, protocole, prescription, traitant) 
                          VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $id_patient,
        $type,
        $affectation,
        $data['diagnostic'],
        $data['traitement'],
        $data['protocole'],
        $data['prescription'],
        $_SESSION['auth']
    ]);
}


function UpdateChirurgieKBECHO($bdd, $biometrie, $echographie, $affectation, $postData) {
    $stmt = $bdd->prepare('UPDATE chirurgies SET biometrie = ?, echographie = ? WHERE id_affectation = ?');
    $stmt->execute([
        $biometrie,
        $echographie,
        $affectation
    ]);
}

function insertGlycemie($bdd, $affectation, $data) {
    $stmt = $bdd->prepare('UPDATE acquitte_visuelle SET glycemie = ? WHERE id_affectation = ?');
    $stmt->execute([
        $data['glycemie'],
        $affectation
    ]);
}

// fonction pour la nommenclature de la reference de documentation de rapport medicaux.

/**
 * Génère une référence automatique du type :
 * COEC/DG/DC/25/10/01
 *
 * - "COEC/DG/DC/" est un préfixe fixe
 * - "25" = année en cours
 * - "10" = mois en cours
 * - "01" = incrément automatique selon le dernier enregistrement du mois
 *
 * @param PDO $bdd Connexion PDO à la base de données
 * @return string  La nouvelle référence générée
 */

/**
 * Insère le traitement de rapport médical
 */
function insertRapportement($bdd, $id_patient, $type, $affectation, $data) {
    // Génération automatique de la référence
    require_once('fonction.php');
    $reference = genererReferenceRapportement($bdd);
    
    $stmt = $bdd->prepare('INSERT INTO rapportements (reference, id_patient, id_type, id_affectation, rapport, traitant) 
                          VALUES (?,?,?,?,?,?)');
    $stmt->execute([
        $reference,
        $id_patient,
        $type,
        $affectation,
        $data['rapport'],
        $_SESSION['auth']
    ]);
}

function insertRapportementEvacuation($bdd, $id_patient, $type, $affectation, $data) {
    // Génération automatique de la référence
    require_once('fonction.php');
    $reference = genererReferenceRapportement($bdd);
    
    $stmt = $bdd->prepare('INSERT INTO rapportements (reference, id_patient, id_type, id_affectation, rapport, traitant, pathologie) 
                          VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([
        $reference,
        $id_patient,
        $type,
        $affectation,
        $data['rapport'],
        $_SESSION['auth'],
        $data['pathologie_file']
    ]);
}

/**
 * Insère le traitement Examen
 */
function insertExamen($bdd, $id_patient, $type, $affectation, $data) {
    $stmt = $bdd->prepare('INSERT INTO examens (id_patient, id_type, id_affectation, diagnostic, resultat, traitant) 
                          VALUES (?,?,?,?,?,?)');
    $stmt->execute([
        $id_patient,
        $type,
        $affectation,
        $data['diagnostic'],
        $data['resultat'],
        $_SESSION['auth']
    ]);
}

/**
 * Insère le traitement Soins
 */
function insertSoins($bdd, $id_patient, $type, $affectation, $data) {
    $stmt = $bdd->prepare('INSERT INTO soins (id_patient, id_type, id_affectation, diagnostic, conduite, prescription, traitant) 
                          VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([
        $id_patient,
        $type,
        $affectation,
        $data['diagnostic'],
        $data['conduite'],
        $data['prescription'],
        $_SESSION['auth']
    ]);
}

/**
 * Insère le traitement Mesures (Refraction)
 */
function insertMesures($bdd, $id_patient, $type, $affectation, $data) {
    $stmt = $bdd->prepare('INSERT INTO mesures (id_patient, id_type, id_affectation, refraction, od, os, addit, eip, details, traitant) 
                          VALUES (?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $id_patient,
        $type,
        $affectation,
        $data['refraction'],
        $data['od'],
        $data['os'],
        $data['addit'] ?: NULL,
        $data['eip'] ?: NULL,
        $data['details'] ?: NULL,
        $_SESSION['auth']
    ]);
}

/**
 * Insère le traitement controle
 */
function insertControle($bdd, $id_patient, $type, $affectation, $data) {
    $stmt = $bdd->prepare('INSERT INTO controles (id_patient, id_type, id_affectation, diagnostic, traitement, prescription, traitant) 
                          VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([
        $id_patient,
        $type,
        $affectation,
        $data['diagnostic'],
        $data['traitement'],
        $data['prescription'],
        $_SESSION['auth']
    ]);
}

/**
 * Met à jour le statut de l'affectation
 */
function updateAffectationStatus($bdd, $affectation) {
    $stmt = $bdd->prepare('UPDATE affectations SET status = 4, datetraitement = ? WHERE id_affectation = ?');
    $stmt->execute([date('Y-m-d'), $affectation]);
}

function updateAffectationPourChirurgie($bdd, $affectation) {
    $stmt = $bdd->prepare('UPDATE affectations SET status = 7 WHERE id_affectation = ?');
    $stmt->execute([$affectation]);
}

function updateAffectationStatusKBECHO($bdd, $affectation) {
    $stmt = $bdd->prepare('UPDATE affectations SET status = 8 WHERE id_affectation = ?');
    $stmt->execute([$affectation]);
}

// update rendez-vous statuts : 

function updateRendezvousStatus($bdd, $rdv) {
    $stmt = $bdd->prepare('UPDATE dmd_rendez_vous SET status = 4 WHERE id_rdv = ?');
    $stmt->execute([$rdv]);
}


/**
 * Insère un rendez-vous
 */
function insertRendezVous($bdd, $id_patient, $service, $data) {
    $stmt = $bdd->prepare('INSERT INTO dmd_rendez_vous (id_patient, id_service, motif, traitant, prochain_rdv) 
                          VALUES (?,?,?,?,?)');
    $stmt->execute([
        $id_patient,
        $service,
        $data['motifrdv'],
        $data['medecin'],
        $data['prochain_rdv']
    ]);
}

/**
 * Insère un compte rendu
 */
function insertCompteRendu($bdd, $id_patient, $affectation, $data) {
    $stmt = $bdd->prepare('INSERT INTO compte_rendu (id_patient, id_affectation, compte_rendu) VALUES (?,?,?)');
    $stmt->execute([
        $id_patient,
        $affectation,
        $data['compte_rendu']
    ]);
}


/**
 * Agrège les traitements (consultations, contrôles, examens, chirurgies, soins, mesures)
 * pour un infirmier donné, sur une période jour / mois / année.
 *
 * @param string $date        "YYYY-MM-DD" (day) | "YYYY-MM" (month) | "YYYY" (year)
 * @param int    $userId
 * @param PDO    $bdd
 * @param string $granularity 'day' | 'month' | 'year'
 * @return array [ ['id_type'=>..., 'nom_type'=>..., 'total'=>...], ... ]
 */
function getTraitementsAvecNomType(string $date, int $userId, PDO $bdd, string $granularity = 'day'): array
{
    // 1) Calcul des bornes [start, end)
    [$start, $end] = (function(string $date, string $g): array {
        switch ($g) {
            case 'day':   // "YYYY-MM-DD"
                $d = new DateTimeImmutable($date);
                $start = $d->setTime(0,0,0);
                $end   = $start->modify('+1 day');
                break;

            case 'month': // "YYYY-MM"
                if (preg_match('/^\d{4}-\d{2}$/', $date)) {
                    $d = new DateTimeImmutable($date . '-01');
                } else {
                    $d = new DateTimeImmutable($date);
                    $d = $d->modify('first day of this month');
                }
                $start = $d->setTime(0,0,0);
                $end   = $start->modify('+1 month');
                break;

            case 'year':  // "YYYY"
                if (preg_match('/^\d{4}$/', $date)) {
                    $d = new DateTimeImmutable($date . '-01-01');
                } else {
                    $d = new DateTimeImmutable($date);
                    $d = $d->setDate((int)$d->format('Y'), 1, 1);
                }
                $start = $d->setTime(0,0,0);
                $end   = $start->modify('+1 year');
                break;

            default:
                throw new InvalidArgumentException("granularity doit être 'day', 'month' ou 'year'");
        }
        return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
    })($date, $granularity);

    // 2) Construction du UNION ALL avec placeholders uniques
    $tables = ['consultations', 'controles', 'examens', 'chirurgies', 'soins', 'mesures'];
    $parts  = [];
    $params = [];

    foreach ($tables as $i => $table) {
        $u = ":u{$i}";
        $s = ":s{$i}";
        $e = ":e{$i}";

        $parts[] = "
            SELECT id_type, COUNT(*) AS cnt
            FROM {$table}
            WHERE traitant = {$u}
              AND date_traitement >= {$s}
              AND date_traitement <  {$e}
            GROUP BY id_type
        ";

        $params[$u] = $userId;
        $params[$s] = $start;
        $params[$e] = $end;
    }

    $union = implode(' UNION ALL ', $parts);

    $sql = "
        SELECT t.id_type, t.nom_type, SUM(x.cnt) AS total
        FROM ({$union}) AS x
        JOIN traitements t ON t.id_type = x.id_type
        GROUP BY t.id_type, t.nom_type
        ORDER BY t.id_type
    ";

    // 3) Exécution
    // Optionnel : lever les erreurs SQL proprement
    // $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $bdd->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

