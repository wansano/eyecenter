<?php
// Configuration de la base de données
if (!defined('DB_HOST')) define('DB_HOST', 'localhost:3306');
if (!defined('DB_NAME')) define('DB_NAME', 'eyecenter');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

if (!class_exists('Database')) {
    class Database {
        private static $instance = null;
        private $conn;

        private function __construct() {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];

            try {
                $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log("Erreur de connexion : " . $e->getMessage());
                throw new Exception("Une erreur est survenue lors de la connexion à la base de données.");
            }
        }

        public static function getInstance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance->conn;
        }

        // Empêcher la duplication de connexion
        private function __clone() {}
        public function __wakeup() {}
    }
}

// Pour la compatibilité avec le code existant
try {
    $bdd = Database::getInstance();
} catch (Exception $e) {
    error_log($e->getMessage());
    die("Une erreur est survenue. Veuillez contacter l'administrateur.");
}