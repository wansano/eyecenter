<?php
$id_patient = isset($_GET['id_patient']) ? intval($_GET['id_patient']) : 0;
$autoPrint = isset($_GET['autoprint']) && (int)$_GET['autoprint'] === 1;
$pdf_url = "../impression/_carteadhesion.php?id_patient=" . $id_patient;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Impression carte d'adhesion</title>
</head>
<body style="margin:0">
    <iframe id="pdfFrame" src="<?php echo $pdf_url; ?>" style="width:100vw; height:100vh; border:0;" frameborder="0"></iframe>
    <script>
        var __pdfReady = false;
        try {
            var f = document.getElementById('pdfFrame');
            if (f) {
                f.addEventListener('load', function () { __pdfReady = true; });
            }
        } catch (e) {}

        function printPdf() {
            try {
                var frame = document.getElementById('pdfFrame');
                if (!frame) return;

                if (!__pdfReady) {
                    setTimeout(printPdf, 300);
                    return;
                }

                if (frame.contentWindow && typeof frame.contentWindow.print === 'function') {
                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                    return;
                }
            } catch (e) {
                try { window.focus(); window.print(); } catch (e2) {}
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