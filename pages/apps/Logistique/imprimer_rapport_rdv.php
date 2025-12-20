<?php
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$pdf_url = "../impression/_rapport_rdv_jour.php?date=" . urlencode($date);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Impression rapport des rendez-vous</title>
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
