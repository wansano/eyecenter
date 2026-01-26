<?php
$affectation = isset($_GET['affectation']) ? intval($_GET['affectation']) : 0;
$autoprint = isset($_GET['autoprint']) ? intval($_GET['autoprint']) : 1;
$pdf_url = "../impression/_consultation.php?affectation=" . $affectation;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Impression consultation</title>
</head>
<body style="margin:0">
    <iframe id="pdfFrame" src="<?php echo $pdf_url; ?>" style="width:100vw; height:100vh;" frameborder="0"></iframe>
    <?php if ($autoprint === 1): ?>
        <script>
            window.onload = function() {
                setTimeout(function() {
                    document.getElementById('pdfFrame').contentWindow.print();
                }, 1000); // attendre que le PDF charge
            };
        </script>
    <?php endif; ?>
</body>
</html>