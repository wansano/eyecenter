<?php
$idrapport = isset($_GET['id']) ? intval($_GET['id']) : 0;
$pdf_url = "../impression/_rapportcaisse.php?id=" . htmlspecialchars($idrapport);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Impression reçu de paiement du patient</title>
</head>
<body style="margin:0">
    <iframe id="pdfFrame" src="<?php echo $pdf_url; ?>" style="width:100vw; height:100vh;" frameborder="0"></iframe>
    <script>
        // Impression volontaire uniquement (pas d'auto-print)
        function printPdf() {
            try {
                var frame = document.getElementById('pdfFrame');
                if (!frame || !frame.contentWindow) return;
                frame.contentWindow.focus();
                frame.contentWindow.print();
            } catch (e) {
                // noop
            }
        }
    </script>
</body>
</html>