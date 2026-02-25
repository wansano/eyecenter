<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
session_start();
$errors = 0;

include('../PUBLIC/header.php');
?>
<body>
<section class="body">
    <?php require('../PUBLIC/navbarmenu.php'); ?>
    <div class="inner-wrapper">
        <section role="main" class="content-body">
            <header class="page-header">
                <h2>Liste des traités pendant cette période.</h2>
            </header>
            <!-- start: page -->
            <div class="col-md-12">
                <div class="row">
                    <div class="col">
                        <section class="card">
                            <header class="card-header">
                                <h3 class="card-title" style="text-transform:inherit; font-weight:lighter; ">
                                    <button class="btn btn-info btn-sm" onclick="exportAllDataToExcel('datatable-default', 'tableau_donnees_cumulation')">Exporter au format excel</button>
                                </h3>
                            </header>
                            <div class="card-body">
                                <table class="table table-bordered table-striped mb-0" id="datatable-default">
                                    <thead>
                                        <tr>
                                            <th>N° RECU</th>
                                            <th>DOSSIER</th>
                                            <th>PATIENT</th>
                                            <th>CONTACT</th>
                                            <th>STATUTS</th>
											<th>PRESTATION</th>
                                            <th>MONTANT</th>
                                            <th>PAYE PAR</th>
                                            <th>DATE</th>
                                            <th>CAISSIER</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    $params = [];
                                    $sql = 'SELECT * FROM paiements WHERE 1';
                                    if (isset($_GET['type']) && $_GET['type'] !== '') {
                                        $sql .= ' AND types = ?';
                                        $params[] = $_GET['type'];
                                    }
                                    if (isset($_GET['debut'], $_GET['fin'])) {
                                        $sql .= ' AND datepaiement BETWEEN ? AND ?';
                                        $params[] = $_GET['debut'];
                                        $params[] = $_GET['fin'];
                                    }
                                    $sql .= ' ORDER BY datepaiement';
                                    $reponse1 = $bdd->prepare($sql);
                                    $reponse1->execute($params);
                                    while ($donnees1 = $reponse1->fetch(PDO::FETCH_ASSOC)) {
                                        $patientId = $donnees1['patient'];
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($donnees1['code']) . '</td>';
                                        echo '<td>PAT-' . htmlspecialchars($patientId) . '</td>';
                                        echo '<td>' . htmlspecialchars(nom_patient($patientId)) . '</td>';
                                        echo '<td>' . htmlspecialchars(return_phone($patientId)) . '</td>';
                                        echo '<td>' . (return_assure($patientId) == 0 ? 'Non assuré(e)' : 'Assuré(e)') . '</td>';
										echo '<td>' . htmlspecialchars(model($donnees1['types'])) . '</td>';
                                        echo '<td>' . number_format($donnees1['montant']) . ' ' . htmlspecialchars($devise) . '</td>';
                                        echo '<td>' . htmlspecialchars(compte($donnees1['compte'])) . '</td>';
                                        echo '<td>' . htmlspecialchars($donnees1['datepaiement']) . '</td>';
                                        echo '<td>' . htmlspecialchars(traitant($donnees1['caisse'])) . '</td>';
                                        echo '</tr>';
                                    }
                                    ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
            <?php include('../PUBLIC/footer.php'); ?>
            <script>
                $(document).ready(function() {
                    if (!$.fn.DataTable.isDataTable('#datatable-default')) {
                        $('#datatable-default').DataTable({
                            paging: true,
                            pageLength: 10
                        });
                    }
                });
                function exportAllDataToExcel(tableID, filename = '') {
                    var table = $('#' + tableID).DataTable();
                    table.page.len(-1).draw();
                    exportTableToExcel(tableID, filename);
                    table.page.len(10).draw();
                }
                function exportTableToExcel(tableID, filename = ''){
                    var downloadLink;
                    var dataType = 'application/vnd.ms-excel';
                    var tableSelect = document.getElementById(tableID);
                    var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
                    filename = filename ? filename + '.xls' : 'tableau_donnees_cumulation.xls';
                    downloadLink = document.createElement("a");
                    document.body.appendChild(downloadLink);
                    downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
                    downloadLink.download = filename;
                    downloadLink.click();
                    document.body.removeChild(downloadLink);
                }
            </script>
        </section>
    </div>
</section>
</body>
</html>