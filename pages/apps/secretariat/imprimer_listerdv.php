<?php
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$medecinId = isset($_GET['medecin']) ? (int)$_GET['medecin'] : 0;
$pdf_url = "../impression/_convocation_print.php?date=" . urlencode($date) . "&medecin=" . urlencode($medecinId);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Impression liste des rendez-vous medecin</title>
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