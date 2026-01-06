<?php
// message_commande.php
// Génère le message HTML à envoyer au fournisseur pour une commande, formaté comme sur l'image fournie

function genererMessageCommande(
    $fournisseur,
    $nocommande,
    $typecommande,
    $datecommande,
    $quantitecommande,
    $description,
    $service_demandeur = 'Administration et Finances',
    $demandeur = '',
    $objet = '',
    $justification = "Renouvellement de stock",
    $observation = '',
    $urgence = '',
    $signature_fonction = "Responsable Administration et Finances",
    $signature_date = ''
) {
    // Valeurs par défaut dynamiques
    if (empty($objet)) {
        $objet = 'Demande d’acquisition de ' . $typecommande;
    }
    if (empty($urgence)) {
        $urgence = date("Y-m-d", strtotime("+3 days"));
    }
    if (empty($signature_date)) {
        $signature_date = $datecommande;
    }
    if (empty($demandeur)) {
        $demandeur = traitant($_SESSION['auth']);
    }
    return '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>EXPRESSION DE BESOIN</title>
    <link href="https://fonts.googleapis.com/css?family=Poppins:400,600&display=swap" rel="stylesheet">
    <style>
        body { background: #fff; color: #111; font-family: "Poppins", Arial, sans-serif; }
        .container { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #eee; padding: 32px; max-width: 700px; margin: 30px auto; }
        .header-flex { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
        h2 { color: #111; font-size: 1.5em; letter-spacing: 1px; font-weight: 600; margin: 0; }
        .logo-header { height: 56px; max-width: 120px; object-fit: contain; }
        .section { margin: 32px 0 18px 0; }
        .section-title { font-weight: 600; text-transform: uppercase; margin-bottom: 8px; color: #111; }
        .info { margin-bottom: 8px; }
        .info strong, .label { color: #111; }
        hr { border: none; border-top: 1px solid #ddd; margin: 28px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 8px 6px; }
        th { background: #f7f7f7; color: #111; border-bottom: 1px solid #ddd; text-align: left; font-weight: 600; }
        td { background: #fff; color: #111; border-bottom: 1px solid #eee; }
        .footer { margin-top: 32px; color: #888; font-size: 0.95em; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-flex" style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 18px;">
            <div style="flex:1; min-width:0;">
                <h2 style="margin:0;">EXPRESSION DE BESOIN</h2>
            </div>
        </div>
        <div class="info"><span class="label">Référence :</span> EB_' . htmlspecialchars($nocommande) . '</div>
        <div class="info"><span class="label">Date :</span> ' . htmlspecialchars($datecommande) . '</div>
        <div class="info"><span class="label">Service demandeur :</span> ' . htmlspecialchars($service_demandeur) . '</div>
        <div class="info"><span class="label">Demandeur :</span> ' . htmlspecialchars($demandeur) . '</div>
        <div class="info"><span class="label">Fonction :</span> ' . htmlspecialchars($signature_fonction) . '</div>
        <hr>
        <div class="section">
            <div class="section-title">OBJET :</div>
            <div>' . nl2br(htmlspecialchars($objet)) . '</div>
        </div>
        <hr>
        <div class="section">
            <div class="section-title">JUSTIFICATION :</div>
            <div>' . nl2br(htmlspecialchars($justification)) . '</div>
        </div>
        <hr>
        <div class="section">
            <div class="section-title">DÉTAIL DU BESOIN :</div>
            <table>
                <tr>
                    <th>Description de la demande</th>
                    <th>Quantité</th>
                    <th>Observations</th>
                </tr>
                <tr>
                    <td>' . nl2br(htmlspecialchars($description)) . '</td>
                    <td>' . htmlspecialchars($quantitecommande) . '</td>
                    <td>' . htmlspecialchars($observation) . '</td>
                </tr>
            </table>
        </div>
        <hr>
        <div class="section">
            <div class="section-title">Délai souhaité :</div>
            <div> Au plus tard le : ' . nl2br(htmlspecialchars($urgence)) . '</div>
        </div>
        <hr>
        <div class="section">
            <div class="section-title">Message :</div>
            <div>Merci de bien vouloir respecter le délai mentionné ci-dessus.</div>
        </div>
    </div>
</body>
</html>';
}
