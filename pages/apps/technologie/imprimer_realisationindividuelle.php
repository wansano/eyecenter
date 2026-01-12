<?php
$employe = isset($_GET['employe']) ? (int)$_GET['employe'] : 0;
$date_debut = isset($_GET['debut']) ? (string)$_GET['debut'] : date('Y-m-d');
$date_fin = isset($_GET['fin']) ? (string)$_GET['fin'] : date('Y-m-d');

$pdf_url = "../impression/_realisationindividuelle.php?employe=" . urlencode((string)$employe)
    . "&debut=" . urlencode($date_debut)
    . "&fin=" . urlencode($date_fin);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Impression rapport</title>
</head>
<body style="margin:0">
    <iframe id="pdfFrame" src="<?php echo htmlspecialchars($pdf_url, ENT_QUOTES, 'UTF-8'); ?>" style="width:100vw; height:100vh;" frameborder="0"></iframe>
    <script>
        window.onload = function() {
            setTimeout(function() {
                try {
                    document.getElementById('pdfFrame').contentWindow.print();
                } catch (e) {
                    // si le navigateur bloque, l'utilisateur peut imprimer via le viewer PDF
                }
            }, 1000);
        };
    </script>
</body>
</html>
