<?php
session_start(); // démarre la session en cours
session_unset(); // supprime toutes les variables globales de session
session_destroy(); // détruit toutes les données associées à la session
header("Location: ../../login.php?r=2"); // redirige l'utilisateur vers la page d'accueil
exit; // arrête l'exécution du script
?>