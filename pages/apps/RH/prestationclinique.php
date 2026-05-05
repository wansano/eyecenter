<?php
include('../PUBLIC/connect.php');
require('../PUBLIC/fonction.php');
session_start();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_date_fr(string $date): string {
    if ($date === '') {
        return '-';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }

    $mois = [
        '01' => 'janvier',
        '02' => 'février',
        '03' => 'mars',
        '04' => 'avril',
        '05' => 'mai',
        '06' => 'juin',
        '07' => 'juillet',
        '08' => 'août',
        '09' => 'septembre',
        '10' => 'octobre',
        '11' => 'novembre',
        '12' => 'décembre',
    ];

    $month = date('m', $timestamp);
    return date('d', $timestamp) . ' ' . ($mois[$month] ?? $month) . ' ' . date('Y', $timestamp);
}

function normalize_sexe($value): string {
    $raw = strtolower(trim((string)$value));

    if ($raw === '') {
        return 'Non renseigné';
    }

    if (in_array($raw, ['1', 'm', 'masculin', 'homme', 'male'], true)) {
        return 'Masculin';
    }

    if (in_array($raw, ['0', 'f', 'feminin', 'féminin', 'femme', 'female'], true)) {
        return 'Feminin';
    }

    return 'Autre';
}

$today = date('Y-m-d');
$defaultStart = date('Y-m-01');
$dateDebut = $defaultStart;
$dateFin = $today;
$errorMessage = '';

if (isset($_POST['afficher'])) {
    $dateDebut = trim((string)($_POST['datedebut'] ?? $defaultStart));
    $dateFin = trim((string)($_POST['datefin'] ?? $today));

    $isValidDebut = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut);
    $isValidFin = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin);

    if (!$isValidDebut || !$isValidFin) {
        $errorMessage = 'Veuillez renseigner des dates valides.';
        $dateDebut = $defaultStart;
        $dateFin = $today;
    } elseif ($dateDebut > $dateFin) {
        $errorMessage = 'La date de début doit être inférieure ou égale à la date de fin.';
    } elseif ($dateFin > $today) {
        $errorMessage = 'La date de fin ne peut pas être supérieure à la date du jour.';
    }
}

$tables = ['consultations', 'controles', 'examens', 'chirurgies', 'soins', 'mesures'];
$unionParts = [];
$params = [];

foreach ($tables as $index => $table) {
    $unionParts[] = "SELECT DATE(date_traitement) AS date_prestation, id_patient, traitant, id_type, '$table' AS source_table FROM $table WHERE date_traitement IS NOT NULL AND DATE(date_traitement) BETWEEN :debut$index AND :fin$index";
    $params[':debut' . $index] = $dateDebut;
    $params[':fin' . $index] = $dateFin;
}

$prestations = [];
$rows = [];
$patientIds = [];
$dailyCounts = [];
$typeCounts = [];
$typeLabels = [];
$typeValues = [];
$sexCounts = [
    'Masculin' => 0,
    'Feminin' => 0,
    'Autre' => 0,
    'Non renseigné' => 0,
];
$chartLabels = [];
$chartValues = [];
$totalPrestations = 0;
$totalPatients = 0;
$totalMale = 0;
$totalFemale = 0;

if ($errorMessage === '') {
    $sql = 'SELECT date_prestation, id_patient, traitant, id_type, source_table FROM (' . implode(' UNION ALL ', $unionParts) . ') AS prestations_period ORDER BY date_prestation ASC, id_patient ASC, id_type ASC';

    try {
        $stmt = $bdd->prepare($sql);
        $stmt->execute($params);
        $prestations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($prestations as $row) {
            $dateKey = (string)($row['date_prestation'] ?? '');
            if ($dateKey !== '') {
                $dailyCounts[$dateKey] = ($dailyCounts[$dateKey] ?? 0) + 1;
            }

            $idType = (int)($row['id_type'] ?? 0);
            if ($idType > 0) {
                $typeCounts[$idType] = ($typeCounts[$idType] ?? 0) + 1;
            }

            $idPatient = (int)($row['id_patient'] ?? 0);
            if ($idPatient > 0) {
                $patientIds[$idPatient] = true;
            }
        }

        $totalPrestations = count($prestations);
        $totalPatients = count($patientIds);

        if (!empty($patientIds)) {
            $placeholders = implode(',', array_fill(0, count($patientIds), '?'));
            $stmtPatients = $bdd->prepare('SELECT id_patient, sexe FROM patients WHERE id_patient IN (' . $placeholders . ')');
            $stmtPatients->execute(array_keys($patientIds));

            while ($patient = $stmtPatients->fetch(PDO::FETCH_ASSOC)) {
                $bucket = normalize_sexe($patient['sexe'] ?? '');
                if (!array_key_exists($bucket, $sexCounts)) {
                    $bucket = 'Autre';
                }
                $sexCounts[$bucket]++;
            }
        }

        $periodStart = new DateTimeImmutable($dateDebut);
        $periodEnd = new DateTimeImmutable($dateFin);
        $periodEndInclusive = $periodEnd->modify('+1 day');
        $period = new DatePeriod($periodStart, new DateInterval('P1D'), $periodEndInclusive);

        foreach ($period as $day) {
            $key = $day->format('Y-m-d');
            $chartLabels[] = $day->format('d/m');
            $chartValues[] = (int)($dailyCounts[$key] ?? 0);
        }

        if (!empty($typeCounts)) {
            arsort($typeCounts);
            foreach ($typeCounts as $idType => $count) {
                $label = trim((string)model((int)$idType));
                if ($label === '') {
                    $label = 'Prestation #' . (int)$idType;
                }
                $typeLabels[] = $label;
                $typeValues[] = (int)$count;
            }
        }

        $totalMale = (int)$sexCounts['Masculin'];
        $totalFemale = (int)$sexCounts['Feminin'];
    } catch (Throwable $e) {
        error_log('Erreur prestationclinique.php: ' . $e->getMessage());
        $errorMessage = 'Impossible de charger les prestations pour cette période.';
    }
}

foreach ($prestations as $row) {
    $idType = (int)($row['id_type'] ?? 0);
    $libelle = trim((string)model($idType));
    if ($libelle === '') {
        $libelle = 'Prestation #' . $idType;
    }

    $idPatient = (int)($row['id_patient'] ?? 0);
    $rows[] = [
        'date' => (string)($row['date_prestation'] ?? ''),
        'patient' => $idPatient > 0 ? (string)nom_patient($idPatient) : '-',
        'traitant' => (int)($row['traitant'] ?? 0) > 0 ? (string)traitant((int)$row['traitant']) : '-',
        'type' => $libelle,
        'source' => ucfirst((string)($row['source_table'] ?? '')),
    ];
}

include('../PUBLIC/header.php');
?>
<body>
    <section class="body">

        <?php require('../PUBLIC/navbarmenu.php'); ?>

        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Prestation clinique</h2>
                </header>

                <style>
                    .presta-hero {
                        background: linear-gradient(135deg, #0f172a 0%, #0d6efd 45%, #14b8a6 100%);
                        color: #fff;
                        border-radius: 18px;
                        padding: 24px;
                        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);
                    }
                    .presta-hero small {
                        color: rgba(255, 255, 255, .82);
                    }
                    .stat-card {
                        border-radius: 16px;
                        border: 1px solid rgba(13, 110, 253, .12);
                        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
                    }
                    .stat-value {
                        font-size: 2rem;
                        line-height: 1;
                        font-weight: 700;
                        color: #0d6efd;
                    }
                    .chart-card {
                        border-radius: 18px;
                        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
                    }
                    .ct-chart {
                        height: 320px;
                    }
                    .ct-chart-pie {
                        height: 320px;
                    }
                    .ct-series-a .ct-bar {
                        stroke: #0d6efd;
                        stroke-width: 14px;
                    }
                    .ct-series-a .ct-slice-pie {
                        fill: #0d6efd;
                    }
                    .ct-series-b .ct-slice-pie {
                        fill: #14b8a6;
                    }
                    .ct-series-c .ct-slice-pie {
                        fill: #f59e0b;
                    }
                    .ct-series-d .ct-slice-pie {
                        fill: #6c757d;
                    }
                    .ct-label {
                        font-size: 0.75rem;
                        color: #64748b;
                    }
                    .table-prestations thead th {
                        background: #0f172a;
                        color: #fff;
                        border-color: #0f172a;
                    }
                </style>

                <div class="col-md-12">
                    <section class="presta-hero mb-4">
                        <div class="row align-items-center">
                            <div class="col-lg-8">
                                <h3 class="mb-2 text-white">Suivi des prestations cliniques</h3>
                                <small>Affiche les prestations effectuées sur une période donnée, le total des patients distincts et la répartition par sexe.</small>
                            </div>
                            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                                <div class="small text-uppercase opacity-75">Période analysée</div>
                                <div class="h5 mb-0 text-white"><?php echo h(format_date_fr($dateDebut)); ?> - <?php echo h(format_date_fr($dateFin)); ?></div>
                            </div>
                        </div>
                    </section>

                    <?php if ($errorMessage !== ''): ?>
                        <div class="alert alert-danger"><?php echo h($errorMessage); ?></div>
                    <?php endif; ?>

                    <section class="card mb-4">
                        <div class="card-body">
                            <form method="post" class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="col-form-label" for="datedebut">Date début</label>
                                    <input type="date" class="form-control" id="datedebut" name="datedebut" value="<?php echo h($dateDebut); ?>" max="<?php echo h($today); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="col-form-label" for="datefin">Date fin</label>
                                    <input type="date" class="form-control" id="datefin" name="datefin" value="<?php echo h($dateFin); ?>" max="<?php echo h($today); ?>" required>
                                </div>
                                <div class="col-md-4 d-flex gap-2">
                                    <button type="submit" name="afficher" class="btn btn-primary">Afficher</button>
                                    <a href="prestationclinique.php" class="btn btn-outline-secondary">Réinitialiser</a>
                                    <?php if ($errorMessage === '' && $totalPrestations > 0): ?>
                                        <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#pdfModal" onclick="chargerPDF('<?php echo h($dateDebut); ?>', '<?php echo h($dateFin); ?>')">
                                            <i class="fa fa-file-pdf-o"></i> Imprimer PDF
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </section>

                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <section class="card stat-card h-100">
                                <div class="card-body">
                                    <div class="text-muted text-uppercase small">Prestations</div>
                                    <div class="stat-value"><?php echo h(number_format($totalPrestations, 0, ',', ' ')); ?></div>
                                    <div class="text-muted mt-2">Total des prestations réalisées sur la période.</div>
                                </div>
                            </section>
                        </div>
                        <div class="col-md-4 mb-4">
                            <section class="card stat-card h-100">
                                <div class="card-body">
                                    <div class="text-muted text-uppercase small">Patients distincts</div>
                                    <div class="stat-value"><?php echo h(number_format($totalPatients, 0, ',', ' ')); ?></div>
                                    <div class="text-muted mt-2">Nombre total de patients ayant reçu au moins une prestation.</div>
                                </div>
                            </section>
                        </div>
                        <div class="col-md-4 mb-4">
                            <section class="card stat-card h-100">
                                <div class="card-body">
                                    <div class="text-muted text-uppercase small">Répartition par sexe</div>
                                    <div class="row mt-2">
                                        <div class="col-6">
                                            <div class="h5 mb-1 text-primary"><?php echo h(number_format($totalMale, 0, ',', ' ')); ?></div>
                                            <div class="text-muted small">Masculin</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="h5 mb-1 text-success"><?php echo h(number_format($totalFemale, 0, ',', ' ')); ?></div>
                                            <div class="text-muted small">Feminin</div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-8 mb-4">
                            <section class="card chart-card h-100">
                                <div class="card-header bg-white border-0">
                                    <h4 class="card-title mb-0">Évolution des prestations par jour</h4>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($chartValues)): ?>
                                        <div class="alert alert-info mb-0">Aucune prestation trouvée sur la période sélectionnée.</div>
                                    <?php else: ?>
                                        <div id="prestationsBarChart" class="ct-chart"></div>
                                    <?php endif; ?>
                                </div>
                            </section>
                        </div>
                        <div class="col-lg-4 mb-4">
                            <section class="card chart-card h-100">
                                <div class="card-header bg-white border-0">
                                    <h4 class="card-title mb-0">Patients par sexe</h4>
                                </div>
                                <div class="card-body">
                                    <?php if ($totalPatients <= 0): ?>
                                        <div class="alert alert-info mb-0">Aucun patient à répartir sur cette période.</div>
                                    <?php else: ?>
                                        <div id="patientsPieChart" class="ct-chart ct-chart-pie"></div>
                                    <?php endif; ?>
                                </div>
                            </section>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-12 mb-4">
                            <section class="card chart-card h-100">
                                <div class="card-header bg-white border-0">
                                    <h4 class="card-title mb-0">Évolution par type de prestation</h4>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($typeValues)): ?>
                                        <div class="alert alert-info mb-0">Aucun type de prestation trouvé sur la période sélectionnée.</div>
                                    <?php else: ?>
                                        <div id="prestationsTypeChart" class="ct-chart"></div>
                                    <?php endif; ?>
                                </div>
                            </section>
                        </div>
                    </div>

                    <section class="card chart-card mb-4">
                        <div class="card-header bg-white border-0">
                            <h4 class="card-title mb-0">Détail des prestations effectuées</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0 table-prestations" id="prestationsTable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Patient</th>
                                            <th>Prestation</th>
                                            <th>Traitant</th>
                                            <th>Source</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($rows)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">Aucune prestation trouvée.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($rows as $row): ?>
                                                <tr>
                                                    <td><?php echo h(format_date_fr((string)$row['date'])); ?></td>
                                                    <td><?php echo h((string)$row['patient']); ?></td>
                                                    <td><?php echo h((string)$row['type']); ?></td>
                                                    <td><?php echo h((string)$row['traitant']); ?></td>
                                                    <td><?php echo h((string)$row['source']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>
            </section>
        </div>

        <script src="../vendor/chartist/chartist.min.js"></script>
        <script>
            function chargerPDF(dateDebut, dateFin) {
                var pdfUrl = '../impression/_statistique_clinique.php?dateDebut=' + encodeURIComponent(dateDebut) + '&dateFin=' + encodeURIComponent(dateFin);
                document.getElementById('pdfIframe').src = pdfUrl;
            }

            document.addEventListener('DOMContentLoaded', function () {
                var barLabels = <?php echo json_encode(array_values($chartLabels), JSON_UNESCAPED_UNICODE); ?>;
                var barSeries = <?php echo json_encode([array_values($chartValues)], JSON_UNESCAPED_UNICODE); ?>;
                var typeLabels = <?php echo json_encode(array_values($typeLabels), JSON_UNESCAPED_UNICODE); ?>;
                var typeSeries = <?php echo json_encode([array_values($typeValues)], JSON_UNESCAPED_UNICODE); ?>;
                var pieLabels = <?php echo json_encode(['Masculin', 'Feminin'], JSON_UNESCAPED_UNICODE); ?>;
                var pieSeries = <?php echo json_encode([
                    (int)$sexCounts['Masculin'],
                    (int)$sexCounts['Feminin'],
                ], JSON_UNESCAPED_UNICODE); ?>;
                var pieTotal = pieSeries.reduce(function (sum, value) {
                    return sum + Number(value || 0);
                }, 0);

                if (document.getElementById('prestationsBarChart') && barSeries.length && barSeries[0].some(function (value) { return Number(value) > 0; })) {
                    new Chartist.Bar('#prestationsBarChart', {
                        labels: barLabels,
                        series: barSeries
                    }, {
                        fullWidth: true,
                        chartPadding: { top: 10, right: 10, bottom: 0, left: 10 },
                        axisX: {
                            showGrid: false,
                            labelInterpolationFnc: function (value, index) {
                                return index % 2 === 0 ? value : '';
                            }
                        },
                        axisY: {
                            onlyInteger: true,
                            offset: 30
                        },
                        seriesBarDistance: 12
                    });
                }

                if (document.getElementById('prestationsTypeChart') && typeSeries.length && typeSeries[0].some(function (value) { return Number(value) > 0; })) {
                    new Chartist.Bar('#prestationsTypeChart', {
                        labels: typeLabels,
                        series: typeSeries
                    }, {
                        fullWidth: true,
                        chartPadding: { top: 10, right: 10, bottom: 40, left: 10 },
                        axisX: {
                            offset: 40,
                            labelInterpolationFnc: function (value, index) {
                                var count = Number(typeSeries[0][index] || 0);
                                return value + ' (' + count + ')';
                            }
                        },
                        axisY: {
                            offset: 80,
                            labelInterpolationFnc: function (value) {
                                return value.length > 18 ? value.slice(0, 18) + '…' : value;
                            }
                        },
                        seriesBarDistance: 10
                    });
                }

                if (document.getElementById('patientsPieChart') && pieTotal > 0) {
                    var piePercentages = pieSeries.map(function (value) {
                        if (!pieTotal) return 0;
                        return Math.round((Number(value || 0) / pieTotal) * 100);
                    });

                    new Chartist.Pie('#patientsPieChart', {
                        labels: pieLabels.map(function (label, index) {
                            return label + ' ' + String(piePercentages[index] || 0) + '%';
                        }),
                        series: pieSeries
                    }, {
                        showLabel: true,
                        labelInterpolationFnc: function (value) {
                            return value;
                        }
                    });
                }

                if (window.jQuery && jQuery.fn && typeof jQuery.fn.DataTable === 'function' && document.getElementById('prestationsTable')) {
                    jQuery('#prestationsTable').DataTable({
                        pageLength: 10,
                        order: [[0, 'desc']],
                        language: {
                            search: 'Rechercher :',
                            lengthMenu: 'Afficher _MENU_ lignes',
                            info: 'Affichage de _START_ à _END_ sur _TOTAL_ lignes',
                            infoEmpty: 'Aucune ligne disponible',
                            zeroRecords: 'Aucun résultat trouvé',
                            paginate: {
                                previous: 'Précédent',
                                next: 'Suivant'
                            }
                        }
                    });
                }
            });
        </script>

        <!-- Modal PDF -->
        <div class="modal fade" id="pdfModal" tabindex="-1" aria-labelledby="pdfModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="pdfModalLabel">Statistique Clinique - PDF</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body" style="height: 600px; padding: 0;">
                        <iframe id="pdfIframe" style="width: 100%; height: 100%; border: none;" title="Statistique Clinique PDF"></iframe>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                        <button type="button" id="pdfPrintBtn" class="btn btn-primary">Imprimer</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Charger le PDF dans l'iframe lorsque le modal est affiché
            document.getElementById('pdfModal').addEventListener('shown.bs.modal', function () {
                var dateDebut = document.getElementById('datedebut').value;
                var dateFin = document.getElementById('datefin').value;
                var pdfUrl = '../impression/_statistique_clinique.php?dateDebut=' + encodeURIComponent(dateDebut) + '&dateFin=' + encodeURIComponent(dateFin);
                var iframe = document.getElementById('pdfIframe');
                if (iframe.src !== pdfUrl) {
                    iframe.src = pdfUrl;
                }
            });

            // Imprimer le PDF affiché dans l'iframe
            document.getElementById('pdfPrintBtn').addEventListener('click', function () {
                var iframe = document.getElementById('pdfIframe');
                if (iframe && iframe.contentWindow) {
                    iframe.contentWindow.focus();
                    iframe.contentWindow.print();
                }
            });
        </script>

        <?php include('../PUBLIC/footer.php'); ?>
    </section>
</body>
</html>