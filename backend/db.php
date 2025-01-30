<?php
require_once __DIR__ . '/vendor/autoload.php'; // Carrega o autoloader do Composer

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__); // Carrega o .env
$dotenv->load();

// Obtém variáveis do .env
$host = $_ENV['DB_HOST'];
$dbname = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$password = $_ENV['DB_PASSWORD'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}
?>
