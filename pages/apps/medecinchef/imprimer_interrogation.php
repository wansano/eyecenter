<?php
$compte = isset($_GET['compte']) ? intval($_GET['compte']) : 0;
$debut = isset($_GET['debut']) ? intval($_GET['debut']) : 0;
$fin = isset($_GET['fin']) ? intval($_GET['fin']) : 0;
$montant = isset($_GET['montant']) ? intval($_GET['montant']) : 0;
$entreePreuveTotal = isset($_GET['entreePreuveTotal']) ? intval($_GET['entreePreuveTotal']) : 0;
$rapportcaisse = isset($_GET['rapportcaisse']) ? intval($_GET['rapportcaisse']) : 0;
$solde = isset($_GET['solde']) ? intval($_GET['solde']) : 0;

$autoPrint = !isset($_GET['autoprint']) || (int)$_GET['autoprint'] !== 0;

$pdf_url = "../impression/_rapportinterrogation.php?compte=".$_GET['compte']."&debut=".$_GET['debut']."&fin=".$_GET['fin']."&montant=".$montant."&rapportcaisse=".$rapportcaisse."&solde=".$solde."&entreePreuveTotal=".$entreePreuveTotal;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Impression rapport d'intérrogation</title>
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