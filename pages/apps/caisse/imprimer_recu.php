<?php
$affectation = isset($_GET['affectation']) ? intval($_GET['affectation']) : 0;
$pdf_url = "../impression/_recudecaisse.php?affectation=" . $affectation;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Impression reçu de paiement du patient</title>
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