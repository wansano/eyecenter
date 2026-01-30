<?php
session_start();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$autoprint = isset($_GET['autoprint']) ? (int)$_GET['autoprint'] : 0;

if ($id <= 0) {
    http_response_code(400);
    die('ID manquant.');
}

$pdf_url = "../impression/_rapportcaisse.php?id=" . urlencode((string)$id) . "&t=" . time();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Impression rapport de caisse</title>
    <style>
        html, body { height: 100%; margin: 0; }
        .pdf { width: 100vw; height: 100vh; border: 0; }
        @media print {
            html, body { height: auto; }
        }
    </style>
</head>
<body>
    <embed class="pdf" src="<?php echo htmlspecialchars($pdf_url, ENT_QUOTES, 'UTF-8'); ?>" type="application/pdf" />

    <script>
        function printPdf() {
            try {
                window.focus();
                window.print();
                return true;
            } catch (e) {
                return false;
            }
        }

        window.onload = function () {
            var autoprint = <?php echo (int)$autoprint; ?>;
            if (!autoprint) return;
            setTimeout(function () { printPdf(); }, 500);
        };
    </script>
</body>
</html>
