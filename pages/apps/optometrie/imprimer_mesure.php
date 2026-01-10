<?php
$affectation = isset($_GET['affectation']) ? intval($_GET['affectation']) : 0;
$autoprint = isset($_GET['autoprint']) ? intval($_GET['autoprint']) : 1;
$pdf_url = "../impression/_mesure.php?affectation=" . $affectation;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Impression mesure</title>
</head>
<body style="margin:0">
    <iframe id="pdfFrame" src="<?php echo $pdf_url; ?>" style="width:100vw; height:100vh;" frameborder="0"></iframe>
    <script>
        function printPdf() {
            try {
                var frame = document.getElementById('pdfFrame');
                if (!frame || !frame.contentWindow) return false;
                if (typeof frame.contentWindow.focus === 'function') frame.contentWindow.focus();
                if (typeof frame.contentWindow.print === 'function') {
                    frame.contentWindow.print();
                    return true;
                }
            } catch (e) {
                // noop
            }
            return false;
        }

        window.onload = function() {
            var shouldAutoPrint = <?php echo (int)$autoprint; ?>;
            if (!shouldAutoPrint) return;
            setTimeout(function() {
                printPdf();
            }, 600);
        };
    </script>
</body>
</html>