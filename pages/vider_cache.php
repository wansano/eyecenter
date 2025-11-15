<?php

// Vérifie si la fonction existe et si l'OPcache est activé
if (function_exists('opcache_reset') && opcache_get_status()['opcache_enabled']) {
    opcache_reset();
    echo "Le cache OPcache a été vidé avec succès !";
} else {
    echo "L'OPcache n'est pas activé ou la fonction opcache_reset() n'existe pas.";
}

?>