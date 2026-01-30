<?php
$autoprint = isset($_GET['autoprint']) ? (int)$_GET['autoprint'] : 0;
$pdf_url = "../impression/_liste_traitements.php";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Impression liste des traitements</title>
</head>
<body style="margin:0">
    <iframe id="pdfFrame" src="<?php echo $pdf_url; ?>" style="width:100vw; height:100vh;" frameborder="0"></iframe>

    <script>
        function printPdf() {
            try {
                var frame = document.getElementById('pdfFrame');
                if (frame && frame.contentWindow && typeof frame.contentWindow.print === 'function') {
                    frame.contentWindow.print();
                    return true;
                }
            } catch (e) {
                // noop
            }
            return false;
        }

        window.onload = function() {
            var autoprint = <?php echo (int)$autoprint; ?>;
            if (!autoprint) return;
            setTimeout(function() {
                printPdf();
            }, 700);
        };
    </script>
</body>
</html>
