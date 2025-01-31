<?php
require_once __DIR__ . '/../vendor/autoload.php'; // Carrega o autoloader do Composer

use Dotenv\Dotenv;
use PDO;
use PDOException;

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../'); // Ajuste para carregar o .env
        $dotenv->load();

        $host = $_ENV['DB_HOST'];
        $dbname = $_ENV['DB_NAME'];
        $user = $_ENV['DB_USER'];
        $password = $_ENV['DB_PASSWORD'];
        $port = $_ENV['DB_PORT'];

        try {
            $this->connection = new PDO(
                "pgsql:host=$host;port=$port;dbname=$dbname",
                $user,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT => true
                ]
            );
        } catch (PDOException $e) {
            die("Erro na conexão com o banco de dados: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }
}
?>