<?php
$idDepense = isset($_GET['id_depense']) ? (int)$_GET['id_depense'] : 0;
$autoprint = isset($_GET['autoprint']) ? (int)$_GET['autoprint'] : 1;
$pdf_url = "../impression/_bon_paiement_depense.php?id_depense=" . urlencode((string)$idDepense);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<title>Impression bon de paiement</title>
</head>
<body style="margin:0">
	<iframe id="pdfFrame" src="<?php echo htmlspecialchars($pdf_url, ENT_QUOTES, 'UTF-8'); ?>" style="width:100vw; height:100vh;" frameborder="0"></iframe>
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
