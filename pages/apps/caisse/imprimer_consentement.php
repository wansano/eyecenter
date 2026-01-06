<?php
$affectation = isset($_GET['affectation']) ? (int)$_GET['affectation'] : 0;
$pdf_url = "../impression/_consentement.php?affectation=" . $affectation;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Impression consentement de chirurgie</title>
</head>
<body style="margin:0">
    <iframe id="pdfFrame" src="<?php echo $pdf_url; ?>" style="width:100vw; height:100vh;" frameborder="0"></iframe>
    <script>
        window.onload = function() {
            setTimeout(function() {
                var frame = document.getElementById('pdfFrame');
                if (frame && frame.contentWindow) {
                    frame.contentWindow.print();
                }
            }, 1000);
        };
    </script>
</body>
</html>
