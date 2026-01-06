<?php
    include('../PUBLIC/connect.php');
    require_once('../PUBLIC/fonction.php');
    session_start();
    $errors = 0; $existe = 0;

    // Génération du numéro de commande unique
    $nocommande = genererNumeroPaiement();
    $form = [
        'typecommande' => $_POST['typecommande'] ?? '',
        'fournisseur' => $_POST['fournisseur'] ?? '',
        'datecommande' => $_POST['datecommande'] ?? '',
        'quantitecommande' => $_POST['quantitecommande'] ?? '',
        'description' => $_POST['description'] ?? '',
    ];

    if (isset($_POST['ajouter'])) {
        // Vérification de l'existence de la commande
        $check = $bdd->prepare('SELECT 1 FROM approvisionnements WHERE no_commande = ? LIMIT 1');
        $check->execute([$nocommande]);
        if ($check->fetchColumn()) {
            $existe = 1;
        }

        // Validation des données du formulaire
        $errors = 0;
        foreach ($form as $key => $value) {
            if (empty($value)) {
                $errors++;
                echo '<div class="alert alert-danger">Le champ ' . htmlspecialchars($key) . ' est requis.</div>';
            }
        }

        // Validation supplémentaire sur la quantité
        if (!empty($form['quantitecommande']) && (!is_numeric($form['quantitecommande']) || $form['quantitecommande'] < 1)) {
            $errors++;
            echo '<div class="alert alert-danger">La quantité doit être un nombre positif.</div>';
        }

        // Validation de la date (optionnel, mais bonne pratique)
        if (!empty($form['datecommande']) && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $form['datecommande'])) {
            $errors++;
            echo '<div class="alert alert-danger">La date de commande est invalide.</div>';
        }

        if ($errors == 0 && $existe == 0) {
            // Préparer la valeur de type_commande en la rendant compatible avec la colonne
            $typeVal = $form['typecommande'];
            try {
                $colStmt = $bdd->prepare("SELECT COLUMN_TYPE, CHARACTER_MAXIMUM_LENGTH, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
                $colStmt->execute(['approvisionnements', 'type_commande']);
                $colInfo = $colStmt->fetch(PDO::FETCH_ASSOC);
                if ($colInfo) {
                    $dataType = strtolower($colInfo['DATA_TYPE']);
                    if (strpos($colInfo['COLUMN_TYPE'], 'enum(') === 0) {
                        // extraire les valeurs ENUM
                        preg_match_all("/'([^']+)'/", $colInfo['COLUMN_TYPE'], $matches);
                        $enumValues = $matches[1];
                        // normaliser (case-insensitive) en cherchant une correspondance
                        $found = false;
                        foreach ($enumValues as $ev) {
                            if (strcasecmp($ev, $typeVal) === 0) {
                                $typeVal = $ev; // utiliser la valeur exacte de l'enum
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            // essayer des correspondances simplifiées (ex : 'montures et lentilles' -> 'montures et lentilles')
                            foreach ($enumValues as $ev) {
                                if (stripos($ev, trim($typeVal)) !== false || stripos($typeVal, $ev) !== false) {
                                    $typeVal = $ev;
                                    $found = true;
                                    break;
                                }
                            }
                        }
                        if (!$found) {
                            // fallback sur la première valeur de l'enum et log
                            error_log('Type commande non reconnu pour ENUM, valeur fournie: '.json_encode($typeVal).'. Enum autorisé: '.json_encode($enumValues));
                            $typeVal = $enumValues[0];
                        }
                    } elseif (in_array($dataType, ['varchar','char','text'])) {
                        // tronquer si nécessaire
                        $max = $colInfo['CHARACTER_MAXIMUM_LENGTH'] ? (int)$colInfo['CHARACTER_MAXIMUM_LENGTH'] : 255;
                        if (strlen($typeVal) > $max) {
                            error_log('Troncation type_commande de '.strlen($typeVal).' à '.$max.' caractères.');
                            $typeVal = substr($typeVal, 0, $max);
                        }
                    } elseif (in_array($dataType, ['tinyint','smallint','mediumint','int','bigint'])) {
                        // mapper les strings à des codes entiers si nécessaire
                        $map = [
                            'montures' => 1,
                            'lentilles' => 2,
                            'montures et lentilles' => 3,
                            'mobilier' => 4,
                            'accessoires' => 5
                        ];
                        $lower = strtolower($typeVal);
                        if (isset($map[$lower])) {
                            $typeVal = $map[$lower];
                        } else {
                            // forcer entier
                            $typeVal = (int)$typeVal;
                        }
                    }
                }
            } catch (Exception $e) {
                // en cas d'erreur, log et laisser la valeur telle quelle
                error_log('Erreur récupération colonne type_commande: '.$e->getMessage());
            }

            // Insertion des données dans la base de données
            $req = $bdd->prepare('INSERT INTO approvisionnements (no_commande, type_commande, id_fournisseur, date_commande, quantite_commande, description) VALUES (?, ?, ?, ?, ?, ?)');
            $req->execute([
                $nocommande,
                $typeVal,
                $form['fournisseur'],
                $form['datecommande'],
                $form['quantitecommande'],
                $form['description']
            ]);
            $errors = 2;
            // Récupération de l'email du fournisseur
            $fournisseurStmt = $bdd->prepare('SELECT fournisseur, email FROM fournisseur_produit WHERE id_fournisseur = ? LIMIT 1');
            $fournisseurStmt->execute([$form['fournisseur']]);
            $fournisseurData = $fournisseurStmt->fetch();
            if ($fournisseurData && !empty($fournisseurData['email'])) {
                require_once __DIR__ . '/../PUBLIC/PHPMailer/vendor/autoload.php';
                require_once __DIR__ . '/message_commande.php';
                $smtp = require(__DIR__ . '/../public/smtp_config.php');
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = $smtp['host'];
                    $mail->SMTPAuth = $smtp['auth'];
                    $mail->Username = $smtp['username'];
                    $mail->Password = $smtp['password'];
                    $mail->SMTPSecure = $smtp['secure'];
                    $mail->Port = $smtp['port'];

                    // Expéditeur et destinataire
                    $mail->setFrom($smtp['from_email'], $smtp['from_name']);
                    $mail->addAddress($fournisseurData['email'], $fournisseurData['fournisseur']);

                    // Contenu
                    $mail->isHTML(true); // Permet d'envoyer le HTML
                    $mail->Subject = 'Expression de besoin EYE Center Ref : ' . $nocommande;
                    $mail->Body = genererMessageCommande(
                        $fournisseurData['fournisseur'],
                        $nocommande,
                        $form['typecommande'],
                        $form['datecommande'],
                        $form['quantitecommande'],
                        $form['description']
                        // autres paramètres optionnels si besoin
                    );
                    $mail->AltBody = strip_tags(
                        "EXPRESSION DE BESOIN\n" .
                        "Référence : EB-" . $nocommande . "\n" .
                        "Date : " . $form['datecommande'] . "\n" .
                        "Type de commande : " . $form['typecommande'] . "\n" .
                        "Quantité : " . $form['quantitecommande'] . "\n" .
                        "Description : " . $form['description'] . "\n"
                    );
                    $mail->send();
                } catch (Exception $e) {
                    // Log ou message d'erreur si besoin : $mail->ErrorInfo
                }
            }
            // Réinitialisation du formulaire après succès
            $form = [
                'typecommande' => '',
                'fournisseur' => '',
                'datecommande' => '',
                'quantitecommande' => '',
                'description' => '',
            ];
            // Redirection pour éviter la double soumission en cas de rafraîchissement
            header('Location: ' . $_SERVER['PHP_SELF'] . '?success=1');
            exit();
        } elseif ($existe == 1) {
            $errors = 3;
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
                    <h2>Ajout d'une commande</h2>
                </header>
                <!-- start: page -->
                <div class="col-md-12">
                    <section class="card">
                        <div class="card-body">
                            <?php
                                if (isset($_GET['success']) && $_GET['success'] == 1) {
                                    echo '<div class="alert alert-success"><strong>Succès</strong><br/><li>La commande a été ajoutée avec succès !</li><li>Un courriel a été envoyé au fournisseur</li></div>';
                                }
                                if ($errors == 3) {
                                    echo '<div class="alert alert-warning"><li>La commande existe déjà dans le système.</li></div>';
                                }
                            ?>
                            <form class="form-horizontal" novalidate method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data" autocomplete="off">
                                <input type="hidden" name="ajouter" value="1">
                                <div class="row form-group pb-3">
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="datecommande">Date de la commande</label>
                                            <input type="date" class="form-control" name="datecommande" value="<?php echo date('Y-m-d').''.htmlspecialchars($form['datecommande']); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="nocommande">N° commande</label>
                                            <input type="text" class="form-control" name="nocommande" value="<?php echo htmlspecialchars($nocommande); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="typecommande">Type commande</label>
                                            <select class="form-control populate" name="typecommande" required>
                                                <option value="">-- Choisir --</option>
                                                <option value="montures" <?php if($form['typecommande']==='montures') echo 'selected'; ?>>Montures</option>
                                                <option value="lentilles" <?php if($form['typecommande']==='lentilles') echo 'selected'; ?>>Lentilles</option>
                                                <option value="montures et lentilles" <?php if($form['typecommande']==='montures et lentilles') echo 'selected'; ?>>Montures et Lentilles</option>
                                                <option value="Mobilier" <?php if($form['typecommande']==='Mobilier') echo 'selected'; ?>>Mobilier de bureau</option>
                                                <option value="Accessoires" <?php if($form['typecommande']==='Accessoires') echo 'selected'; ?>>Accessoires</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="col-form-label" for="fournisseur">Fournisseur</label>
                                            <select class="form-control populate" name="fournisseur" required>
                                                <option value="">-- Choisir --</option>
                                                <?php
                                                $type = $bdd->prepare('SELECT * FROM fournisseur_produit WHERE status=1');
                                                $type->execute();
                                                while ($fournisseur = $type->fetch()) {
                                                    $selected = ($form['fournisseur'] == $fournisseur['id_fournisseur']) ? 'selected' : '';
                                                    echo '<option value="'.htmlspecialchars($fournisseur['id_fournisseur']).'" '.$selected.'>'.htmlspecialchars($fournisseur['fournisseur']).'</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label class="col-form-label" for="quantitecommande">Quantité</label>
                                            <input type="number" class="form-control" min="1" step="1" name="quantitecommande" value="<?php echo htmlspecialchars($form['quantitecommande']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label class="col-form-label" for="description">Description</label>
                                            <textarea class="form-control" rows="4" name="description" required><?php echo htmlspecialchars($form['description']); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                <footer class="card-footer text-end">
                                    <button class="btn btn-primary" type="submit" name="ajouter">Ajouter la commande</button>
                                </footer>
                            </form>
                        </div>
                    </section>
                </div>
                <!-- end: page -->
            </section>
        </div>
        <?php include('../PUBLIC/footer.php'); ?>
</body>
