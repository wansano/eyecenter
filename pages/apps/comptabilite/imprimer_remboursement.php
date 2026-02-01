<?php
$affectation = isset($_GET['affectation']) ? intval($_GET['affectation']) : 0;
$remboursement = isset($_GET['remboursement']) ? intval($_GET['remboursement']) : 0;

$autoPrint = !isset($_GET['autoprint']) || (int)$_GET['autoprint'] !== 0;

if ($remboursement > 0) {
    $pdf_url = "../impression/_bonderemboursement.php?remboursement=" . $remboursement;
} else {
    $pdf_url = "../impression/_bonderemboursement.php?affectation=" . $affectation;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Impression reçu de remboursement</title>
    <style>
        @media print {
            @page {
                size: portrait;
            }
            html, body {
                width: 100%;
                height: 100%;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body style="margin:0">
    <iframe id="pdfFrame" src="<?php echo $pdf_url; ?>" style="width:100vw; height:100vh;" frameborder="0"></iframe>
    <script>
        function printPdf() {
            try {
                var frame = document.getElementById('pdfFrame');
                if (!frame || !frame.contentWindow) return;
                frame.contentWindow.focus();
                frame.contentWindow.print();
            } catch (e) {
                try { window.print(); } catch (_) {}
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