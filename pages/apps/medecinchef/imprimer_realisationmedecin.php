<?php
$userId = isset($_GET['medecin']) ? intval($_GET['medecin']) : 0;
$date_debut = isset($_GET['debut']) ? $_GET['debut'] : date('Y-m-d');
$date_fin = isset($_GET['fin']) ? $_GET['fin'] : date('Y-m-d');
$pdf_url = "../impression/_realisationsmedecin.php?medecin=" . urlencode($userId) . "&debut=" . urlencode($date_debut) . "&fin=" . urlencode($date_fin);
                                               
?>
<!DOCTYPE html>
<html>
<head>
    <title>Impression rapport</title>
</head>
<body style="margin:0">
    <iframe id="pdfFrame" src="<?php echo $pdf_url; ?>" style="width:100vw; height:100vh;" frameborder="0"></iframe>
    <script>
        window.onload = function() {
            setTimeout(function() {
                document.getElementById('pdfFrame').contentWindow.print();
            }, 1000); // attendre que le PDF charge
        };
    </script>
</body>
</html>