<?php
include('../public/connect.php');
require_once('../public/fonction.php');
session_start();

// Règle d'affichage: seulement du lundi au samedi à partir de 16h30
// Ajustez le fuseau si nécessaire via date_default_timezone_set('Africa/Abidjan');
$now = new DateTime();
$dayOfWeek = (int)$now->format('N'); // 1=lundi .. 7=dimanche
$timeNow = $now->format('H:i');
$canShowForm = ($dayOfWeek >= 1 && $dayOfWeek <= 6) && ($timeNow >= '15:00');

// Fonction pour nettoyer les entrées
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Fonction pour valider les données du formulaire
function validateAccountData($data) {
    $errors = [];
    if (empty($data['montant'])) {
        $errors[] = "Le montant est requis";
    }
    // Vérifier que chaque champ billet est bien défini (y compris 0)
    $billets = ['b1', 'b2', 'b5', 'b10', 'b20'];
    foreach ($billets as $b) {
        if (!isset($data[$b]) || $data[$b] === '' || !is_numeric($data[$b])) {
            $errors[] = "Le nombre de billet est requis pour chaque montant";
            break;
        }
    }
    if (empty($data['montant_lettre'])) {
        $errors[] = "Le montant en lettres doit être saisi";
    }
    if (empty($data['compte']) || !is_numeric($data['compte'])) {
        $errors[] = "Le compte de paiement doit être sélectionné";
    }
    return $errors;
}

// Initialisation des variables
$errors = [];
$success = false;
$formData = [];

// Traitement du formulaire
if (isset($_POST['ajouter'])) {
    // Nettoyage et récupération des données
    $formData = [
        'compte' => cleanInput($_POST['compte'] ?? ''),
        'montant' => cleanInput($_POST['montant'] ?? ''),
        'b1' => cleanInput($_POST['b1'] ?? 0),
        'b2' => cleanInput($_POST['b2'] ?? 0),
        'b5' => cleanInput($_POST['b5'] ?? 0),
        'b10' => cleanInput($_POST['b10'] ?? 0),
        'b20' => cleanInput($_POST['b20'] ?? 0),
        'montant_lettre' => cleanInput($_POST['montant_lettre'] ?? ''),
        'id_user' => $_SESSION['auth']
    ];
    // Vérifier la fenêtre d'autorisation
    if (!$canShowForm) {
        $errors[] = "Le formulaire de rapport n'est disponible qu'à partir de 16h30, du lundi au samedi.";
    } else {
        // Validation des données
        $errors = validateAccountData($formData);
    }
    if (empty($errors)) {
        try {
            $bdd->beginTransaction();
            // Vérification de l'existence d'un rapport pour la même date ET le même utilisateur
            $req1 = $bdd->prepare('SELECT COUNT(*) FROM preuvedecaisse WHERE date_rapportement = ? AND id_user = ? AND compte = ?');
            $req1->execute([date('Y-m-d'), $_SESSION['auth'], $formData['compte']]);
            $rapport_existe = $req1->fetchColumn() > 0;
            if ($rapport_existe) {
                $errors[] = "Un rapport pour cette date existe déjà.";
            } else {
                $formData['montant'] = str_replace(' ', '', $formData['montant']);
                // Insertion du rapport journalier avec la date du jour
                $req = $bdd->prepare('INSERT INTO preuvedecaisse (date_rapportement,compte, montant, b1, b2, b5, b10, b20, montant_lettre, id_user) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $req->execute([
                    date('Y-m-d'),
                    $formData['compte'],
                    $formData['montant'],
                    $formData['b1'] ?? 0,
                    $formData['b2'] ?? 0,
                    $formData['b5'] ?? 0,
                    $formData['b10'] ?? 0,
                    $formData['b20'] ?? 0,
                    $formData['montant_lettre'],
                    $_SESSION['auth']
                ]);
                $bdd->commit();
                $success = true;
                $formData = []; // Réinitialisation du formulaire après succès
            }
        } catch (Exception $e) {
            $bdd->rollBack();
            error_log("Erreur lors de l'ajout du rapport journalier : " . $e->getMessage());
            $errors[] = "Une erreur est survenue lors de l'ajout du rapport journalier";
        }
    }
}

?>
<?php include '../public/header.php'; ?>
<body>
    <section class="body">
        <?php require '../public/navbarmenu.php'; ?>
        <div class="inner-wrapper">
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>Faire un rapport de caisse journalier</h2>
                </header>

                <!-- start: page -->
                <div class="col-md-12">
                    <section class="card">
                        <div class="card-body">
                            <?php if ($success): ?>
                                <div class="alert alert-success">
                                    <strong>Succès</strong><br>
                                    Le rapportement a été ajouté avec succès !
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <strong>Erreur</strong><br>
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($canShowForm): ?>
                            <form class="form-horizontal" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" novalidate="novalidate">
                                <input type="hidden" name="ajouter" value="1">
                                <div class="row form-group pb-3">
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="code">Date du jour</label>
                                            <input type="date" class="form-control" name="date_rapportement" id="date_rapportement" value="<?php echo date("Y-m-d"); ?>" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="col-form-label" for="formGroupExampleInput"> Choisir le mode reglement </label>
                                        <select class="form-control" name="compte" value="<?php echo getFormValue('compte') ;?>" required>
                                            <option>----- Choisir -----</option>
                                            <?php 
                                                $type = $bdd->prepare('SELECT * FROM comptes WHERE defaut = ? AND compte_pour =? AND status =?');
                                                $type -> execute([1, 1, 1]);
                                                while ($type_paiement = $type->fetch(PDO::FETCH_ASSOC))
                                                {
                                                    echo '<option value="'.$type_paiement['id_compte'].'">'.$type_paiement['nom_compte'].'</option>';
                                                } 
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row form-group pb-3">
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="montant">Montant total en chiffre</label>
                                            <input type="text" name="montant" id="montant" class="form-control" value="<?php echo getFormValue('montant'); ?>" required autocomplete="off" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="montant">Billet de 1 000</label>
                                            <input type="number" name="b1" step="0" min="0" id="b1" class="form-control" value="<?php echo getFormValue('b1'); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="montant">Billet de 2 000</label>
                                            <input type="number" name="b2" step="0" min="0" id="b2" class="form-control" value="<?php echo getFormValue('b2'); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="montant">Billet de 5 000</label>
                                            <input type="number" name="b5" step="0" min="0" id="b5" class="form-control" value="<?php echo getFormValue('b5'); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="montant">Billet de 10 000</label>
                                            <input type="number" name="b10" step="1" min="0" id="b10" class="form-control" value="<?php echo getFormValue('b10'); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="montant">Billet de 20 000</label>
                                            <input type="number" name="b20" step="1" min="0" id="b20" class="form-control" value="<?php echo getFormValue('b20'); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row form-group pb-3">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label class="col-form-label" for="montant_lettre">Montant total en lettres en <?= $devise; ?></label>
                                            <textarea class="form-control" name="montant_lettre" id="montant_lettre" rows="5" required="" readonly><?php echo getFormValue('montant_lettre', ''); ?></textarea>
                                        </div>
                                    </div>
                                </div> 
                                <footer class="card-footer text-end">
                                    <button class="btn btn-primary" type="submit">valider le proof de caisse</button>
                                </footer>
                            </form>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <strong>Information</strong><br>
                                    Le formulaire est disponible uniquement du lundi au samedi à partir de l'heure de descente soit 15h00.
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
                <!-- end: page -->
            </section>
        </div>
        <?php include('../public/footer.php'); ?>
        <script>
            
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = {
                b1: document.getElementById('b1'),
                b2: document.getElementById('b2'),
                b5: document.getElementById('b5'),
                b10: document.getElementById('b10'),
                b20: document.getElementById('b20'),
                montant: document.getElementById('montant'),
                montantLettre: document.getElementById('montant_lettre')
            };

            const devise = <?php echo json_encode(isset($devise) ? $devise : ""); ?>;

            function toInt(el) {
                const v = (el && el.value) ? el.value.toString().replace(/\s/g, '') : '0';
                const n = parseInt(v, 10);
                return isNaN(n) ? 0 : n;
            }

            function formatNumber(n) {
                return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            }

            // Conversion nombre -> lettres (FR) pour valeurs positives jusqu'au milliard
            function numberToFrenchWords(n) {
                if (n === 0) return 'zéro';
                if (n < 0) return 'moins ' + numberToFrenchWords(-n);

                const units = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize'];

                function underHundred(x) {
                    if (x < 17) return units[x];
                    if (x < 20) return 'dix-' + units[x - 10];
                    if (x < 70) {
                        const tensWords = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante'];
                        const t = Math.floor(x / 10);
                        const u = x % 10;
                        if (u === 0) return tensWords[t];
                        if (u === 1) return tensWords[t] + ' et un';
                        return tensWords[t] + '-' + units[u];
                    }
                    if (x < 80) { // 70..79 = 60 + 10..19
                        if (x === 71) return 'soixante et onze';
                        return 'soixante-' + underHundred(x - 60);
                    }
                    // 80..99 = 4*20 + 0..19
                    if (x === 80) return 'quatre-vingts';
                    return 'quatre-vingt-' + underHundred(x - 80);
                }

                function underThousand(x) {
                    if (x < 100) return underHundred(x);
                    const c = Math.floor(x / 100);
                    const r = x % 100;
                    let head = (c === 1) ? 'cent' : units[c] + ' cent';
                    if (r === 0 && c > 1) head += 's'; // pluriel "cents" si rien après
                    return head + (r ? ' ' + underHundred(r) : '');
                }

                function toWords(x) {
                    if (x < 1000) return underThousand(x);
                    if (x < 1000000) { // milliers
                        const k = Math.floor(x / 1000);
                        const r = x % 1000;
                        const kilo = (k === 1) ? 'mille' : underThousand(k) + ' mille';
                        return kilo + (r ? ' ' + underThousand(r) : '');
                    }
                    if (x < 1000000000) { // millions
                        const m = Math.floor(x / 1000000);
                        const r = x % 1000000;
                        const million = (m === 1) ? 'un million' : numberToFrenchWords(m) + ' millions';
                        return million + (r ? ' ' + numberToFrenchWords(r) : '');
                    }
                    if (x < 1000000000000) { // milliards
                        const b = Math.floor(x / 1000000000);
                        const r = x % 1000000000;
                        const milliard = (b === 1) ? 'un milliard' : numberToFrenchWords(b) + ' milliards';
                        return milliard + (r ? ' ' + numberToFrenchWords(r) : '');
                    }
                    return x.toString();
                }

                return toWords(n)
                    .replace(/\s+/g, ' ')
                    .replace(/-un\b/g, '-un') // conserver "un"
                    .trim();
            }

            function recalc() {
                const total =
                    toInt(inputs.b1) * 1000 +
                    toInt(inputs.b2) * 2000 +
                    toInt(inputs.b5) * 5000 +
                    toInt(inputs.b10) * 10000 +
                    toInt(inputs.b20) * 20000;

                if (inputs.montant) {
                    inputs.montant.value = total ? formatNumber(total) : '';
                }
                if (inputs.montantLettre) {
                    inputs.montantLettre.value = total ? (numberToFrenchWords(total) + (devise ? ' ' + devise : '')) : '';
                }
            }

            ['b1','b2','b5','b10','b20'].forEach(id => {
                const el = inputs[id];
                if (!el) return;
                el.addEventListener('input', function() {
                    // n'autoriser que les chiffres
                    this.value = this.value.replace(/[^\d]/g, '');
                    recalc();
                });
            });

            // Conserver le formatage si l'utilisateur édite manuellement le montant
            if (inputs.montant) {
                inputs.montant.addEventListener('input', function() {
                    let selectionStart = this.selectionStart;
                    let oldLength = this.value.length;
                    let value = this.value.replace(/\s/g, '').replace(/\D/g, '');
                    if (value) {
                        let formatted = value.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                        this.value = formatted;
                        let newLength = formatted.length;
                        let diff = newLength - oldLength;
                        this.setSelectionRange(selectionStart + diff, selectionStart + diff);
                    } else {
                        this.value = '';
                    }
                });
            }

            // Initialiser au chargement (utile si des valeurs existent déjà)
            recalc();
        });
        </script>
    </section>
</body>
</html>

