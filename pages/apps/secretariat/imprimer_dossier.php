<?php
$id_patient = isset($_GET['id_patient']) ? intval($_GET['id_patient']) : 0;
$pdf_url = "../impression/_dossierpatient.php?id_patient=" . $id_patient;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Impression dossier patient</title>
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