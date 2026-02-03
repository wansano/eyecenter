<?php
$idDemande = isset($_GET['id_demande']) ? (int)$_GET['id_demande'] : 0;
$autoprint = isset($_GET['autoprint']) ? (int)$_GET['autoprint'] : 1;
$pdf_url = "../impression/_bon_sortie_logistique.php?id_demande=" . urlencode((string)$idDemande);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<title>Impression bon de sortie</title>
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
