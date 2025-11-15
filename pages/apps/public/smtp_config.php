<?php
// public/smtp_config.php
// Paramètres SMTP centralisés

return array(
    'host' => 'smtp.gmail.com',
    'auth' => true,
    'username' => 'oumar220wansan@gmail.com',
    'password' => 'hksfdnkujavnhivo',
    'secure' => 'tls', // Utiliser la chaîne 'tls' au lieu de la constante PHP
    'port' => 587,
    'from_email' => 'noreply@eyes-center.com',
    'from_name' => "Clinique d'Ophtalmologie EYE Center"
);
