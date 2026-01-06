<?php
$affectation = isset($_GET['affectation']) ? intval($_GET['affectation']) : 0;
$autoPrint = !isset($_GET['autoprint']) || (int)$_GET['autoprint'] !== 0;
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
        function printPdf() {
            var frame = document.getElementById('pdfFrame');
            if (frame && frame.contentWindow) {
                frame.contentWindow.print();
            }
        }

        window.onload = function() {
            var auto = <?php echo $autoPrint ? 'true' : 'false'; ?>;
            if (!auto) return;
            setTimeout(function() {
                printPdf();
            }, 1000); // attendre que le PDF charge
        };
    </script>
</body>
</html>