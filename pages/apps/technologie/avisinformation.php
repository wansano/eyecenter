<?php
include('../PUBLIC/connect.php');
require_once('../PUBLIC/fonction.php');
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

function h($value): string
{
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function tableExists(PDO $bdd, string $table): bool
{
	try {
		$st = $bdd->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
		$st->execute([$table]);
		return (bool)$st->fetchColumn();
	} catch (Throwable $e) {
		error_log('[avisinformation] tableExists ' . $table . ': ' . $e->getMessage());
		return false;
	}
}

function sanitizeAvisContent(string $raw): string
{
	$raw = trim($raw);
	if ($raw === '') return '';

	// Accepter les anciens contenus HTML mais les stocker en texte brut.
	$raw = preg_replace('#<\s*(script|style)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $raw) ?? '';

	$raw = str_ireplace(["<br>", "<br/>", "<br />"], "\n", $raw);
	$raw = preg_replace('#</\s*p\s*>#i', "\n\n", $raw) ?? $raw;
	$raw = preg_replace('#</\s*div\s*>#i', "\n", $raw) ?? $raw;
	$raw = preg_replace('#<\s*li\b[^>]*>#i', "\n- ", $raw) ?? $raw;
	$raw = preg_replace('#</\s*li\s*>#i', "", $raw) ?? $raw;

	$text = strip_tags($raw);
	$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
	$text = preg_replace("/[ \t]+/", " ", $text) ?? $text;
	$text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

	return trim($text);
}

$alert = null;
$error = null;

if (!tableExists($bdd, 'avis_information')) {
	$error = 'La table avis_information est introuvable. Exécutez db/avis_information.sql.';
}

// PRG
if (isset($_GET['ok'])) {
	$alert = ['type' => 'success', 'message' => 'Opération effectuée avec succès.'];
}

// Traitement POST (création / modification)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
	$action = (string)($_POST['action'] ?? '');

	if ($action === 'save_avis') {
		$idAvis = (int)($_POST['id_avis'] ?? 0);
		$objet = trim((string)($_POST['objet'] ?? ''));
		$contenu = sanitizeAvisContent((string)($_POST['contenu'] ?? ''));

		if ($objet === '') {
			$error = "Veuillez saisir l'objet.";
		} elseif (mb_strlen($objet) > 200) {
			$error = "L'objet est trop long (max 200 caractères).";
		} elseif (trim($contenu) === '') {
			$error = 'Veuillez saisir le contenu.';
		} else {
			try {
				$userId = (int)($_SESSION['auth'] ?? 0);
				if ($idAvis > 0) {
					$st = $bdd->prepare('UPDATE avis_information SET objet = ?, contenu = ?, updated_at = CURRENT_TIMESTAMP WHERE id_avis = ?');
					$st->execute([$objet, $contenu, $idAvis]);
				} else {
					$st = $bdd->prepare('INSERT INTO avis_information (objet, contenu, created_by) VALUES (?, ?, ?)');
					$st->execute([$objet, $contenu, $userId > 0 ? $userId : null]);
				}

				header('Location: avisinformation.php?ok=1');
				exit;
			} catch (Throwable $e) {
				error_log('[avisinformation] save_avis: ' . $e->getMessage());
				$error = 'Une erreur est survenue lors de l\'enregistrement.';
			}
		}
	}

	if ($action === 'delete_avis') {
		$idAvis = (int)($_POST['id_avis'] ?? 0);
		if ($idAvis <= 0) {
			$error = 'Avis invalide.';
		} else {
			try {
				$st = $bdd->prepare('DELETE FROM avis_information WHERE id_avis = ?');
				$st->execute([$idAvis]);
				header('Location: avisinformation.php?ok=1');
				exit;
			} catch (Throwable $e) {
				error_log('[avisinformation] delete_avis: ' . $e->getMessage());
				$error = 'Une erreur est survenue lors de la suppression.';
			}
		}
	}
}

// Liste
$avisList = [];
if (!$error) {
	try {
		$st = $bdd->query('SELECT * FROM avis_information ORDER BY id_avis DESC LIMIT 200');
		$avisList = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
	} catch (Throwable $e) {
		error_log('[avisinformation] list: ' . $e->getMessage());
		$error = 'Une erreur est survenue lors de la récupération des avis.';
	}
}

include('../PUBLIC/header.php');
?>
<body>
	<section class="body">
		<?php require('../PUBLIC/navbarmenu.php'); ?>

		<div class="inner-wrapper">
			<section role="main" class="content-body">
				<header class="page-header">
					<h2>Avis d'information</h2>
				</header>

				<div class="col-md-12">
					<?php if ($alert): ?>
						<div class="alert alert-<?php echo h($alert['type']); ?>">
							<?php echo h($alert['message']); ?>
						</div>
					<?php endif; ?>
					<?php if ($error): ?>
						<div class="alert alert-danger"><?php echo h($error); ?></div>
					<?php endif; ?>

					<section class="card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center mb-3">
								<div></div>
								<div>
									<button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAvis" onclick="openCreateAvis()">Nouvel avis</button>
								</div>
							</div>

							<div class="table-responsive">
								<table class="table table-bordered table-striped mb-0" id="datatable-default">
									<thead>
										<tr>
											<th>Date</th>
											<th>Objet</th>
											<th>Extrait</th>
											<th>Action</th>
										</tr>
									</thead>
									<tbody>
										<?php if (empty($avisList)): ?>
											<tr><td colspan="4">Aucun avis trouvé.</td></tr>
										<?php else: ?>
											<?php foreach ($avisList as $a): ?>
												<?php
													$txt = trim((string)($a['contenu'] ?? ''));
													$excerpt = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($txt)) ?? ''), 0, 90);
													if ($excerpt !== '' && mb_strlen(strip_tags($txt)) > 90) $excerpt .= '…';
												?>
												<tr>
													<td><?php echo h($a['created_at'] ?? ''); ?></td>
													<td><?php echo h($a['objet'] ?? ''); ?></td>
													<td><?php echo h($excerpt); ?></td>
													<td>
														<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAvis"
															data-avis='<?php echo h(json_encode($a, JSON_UNESCAPED_UNICODE)); ?>'
															onclick="openEditAvis(this)">Modifier</button>
														<button type="button" class="btn btn-sm btn-default" data-bs-toggle="modal" data-bs-target="#modalPrintAvis" onclick="openPrintAvis(<?php echo (int)($a['id_avis'] ?? 0); ?>)">Imprimer</button>
														<form method="post" style="display:inline" onsubmit="return confirm('Supprimer cet avis ?');">
															<input type="hidden" name="action" value="delete_avis">
															<input type="hidden" name="id_avis" value="<?php echo (int)($a['id_avis'] ?? 0); ?>">
															<button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
														</form>
													</td>
												</tr>
											<?php endforeach; ?>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</section>
				</div>

				<!-- Modal impression avis (PDF) -->
				<div class="modal fade" id="modalPrintAvis" tabindex="-1" aria-hidden="true">
					<div class="modal-dialog modal-xl modal-dialog-scrollable">
						<div class="modal-content">
							<div class="modal-header">
								<h5 class="modal-title">Impression de l'avis d'information</h5>
								<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
							</div>
							<div class="modal-body" style="min-height:70vh;">
								<iframe id="printAvisFrame" title="Avis d'information" style="width:100%; height:65vh; border:1px solid #e5e5e5;"></iframe>
							</div>
							<div class="modal-footer">
								<button type="button" class="btn btn-default" data-bs-dismiss="modal">Fermer</button>
								<button type="button" class="btn btn-primary" id="btnPrintAvis"><i class="fa fa-print"></i> Imprimer</button>
							</div>
						</div>
					</div>
				</div>

				<!-- Modal avis (création / modification) -->
				<div class="modal fade" id="modalAvis" tabindex="-1" aria-hidden="true">
					<div class="modal-dialog modal-xl modal-dialog-scrollable">
						<div class="modal-content">
							<form method="post" id="avisForm">
								<input type="hidden" name="action" value="save_avis">
								<input type="hidden" name="id_avis" id="id_avis" value="">

								<div class="modal-header">
									<h5 class="modal-title" id="avisModalTitle">Nouvel avis d'information</h5>
									<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
								</div>

								<div class="modal-body">
									<div class="row g-3">
										<div class="col-12">
											<label class="form-label">Objet</label>
											<input type="text" class="form-control" name="objet" id="avis_objet" required maxlength="200">
										</div>
										<div class="col-12">
											<label class="form-label">Contenu</label>
											<textarea class="form-control" name="contenu" id="avis_contenu" rows="10"></textarea>
											<small class="text-muted">Texte simple (retours à la ligne autorisés).</small>
										</div>
									</div>
								</div>

								<div class="modal-footer">
									<button type="button" class="btn btn-default" data-bs-dismiss="modal">Fermer</button>
									<button type="submit" class="btn btn-success">Enregistrer</button>
								</div>
							</form>
						</div>
					</div>
				</div>

			</section>
		</div>
	</section>

	<script>
		function openPrintAvis(id) {
			var frame = document.getElementById('printAvisFrame');
			if (!frame) return;
			frame.src = '../impression/_avis_information.php?id=' + encodeURIComponent(id);
		}

		(function () {
			var btn = document.getElementById('btnPrintAvis');
			if (btn) {
				btn.addEventListener('click', function () {
					var frame = document.getElementById('printAvisFrame');
					if (!frame || !frame.src) return;
					try {
						if (frame.contentWindow) {
							frame.contentWindow.focus();
							frame.contentWindow.print();
							return;
						}
					} catch (e) {}
					window.open(frame.src, '_blank');
				});
			}
		})();

		function openCreateAvis() {
			document.getElementById('avisModalTitle').textContent = "Nouvel avis d'information";
			document.getElementById('id_avis').value = '';
			document.getElementById('avis_objet').value = '';
			document.getElementById('avis_contenu').value = '';
		}

		function htmlToPlainText(html) {
			var raw = (html == null) ? '' : String(html);
			if (!raw) return '';
			raw = raw.replace(/<\s*br\s*\/?>/gi, '\n');
			raw = raw.replace(/<\s*\/\s*p\s*>/gi, '\n\n');
			raw = raw.replace(/<\s*\/\s*div\s*>/gi, '\n');
			raw = raw.replace(/<\s*li\b[^>]*>/gi, '\n- ');
			raw = raw.replace(/<\s*\/\s*li\s*>/gi, '');
			try {
				var doc = new DOMParser().parseFromString(raw, 'text/html');
				raw = (doc && doc.body) ? (doc.body.textContent || '') : raw;
			} catch (e) {
				raw = raw.replace(/<[^>]*>/g, '');
			}
			raw = raw.replace(/\r\n?/g, '\n');
			raw = raw.replace(/[\t ]+/g, ' ');
			raw = raw.replace(/\n{3,}/g, '\n\n');
			return raw.trim();
		}

		function openEditAvis(btn) {
			try {
				var raw = btn.getAttribute('data-avis');
				var a = raw ? JSON.parse(raw) : {};
				document.getElementById('avisModalTitle').textContent = "Modifier l'avis d'information";
				document.getElementById('id_avis').value = a.id_avis || '';
				document.getElementById('avis_objet').value = a.objet || '';
				document.getElementById('avis_contenu').value = htmlToPlainText(a.contenu || '');
			} catch (e) {
				openCreateAvis();
			}
		}
	</script>

	<?php include('../PUBLIC/footer.php'); ?>
