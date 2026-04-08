<?php
require 'config.php';
class Database {
    private $host;
    private $dbname;
    private $user;
    private $pass;
    private $pdo;

    public function __construct() {
        $this->host = DB_HOST;
        $this->dbname = DB_NAME;
        $this->user = DB_USER;
        $this->pass = DB_PASS;

        $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
        } catch (PDOException $e) {
            // Check if it's a driver issue
            if (strpos($e->getMessage(), 'could not find driver') !== false) {
                die("Database Error: PDO MySQL driver not available. Please contact Hostinger support to enable PDO MySQL extension in your PHP configuration.");
            } else {
                die("Database connection failed: " . $e->getMessage());
            }
        }
    }

    public function createConnection() {
        return $this->pdo;
    }
}
?>
