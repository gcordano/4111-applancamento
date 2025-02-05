<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/controllers/AuthController.php';

use App\Controllers\AuthController;
use Dotenv\Dotenv;

// Carrega o .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Configuração de CORS
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: " . $_ENV['FRONTEND_ORIGIN']);
header("Access-Control-Allow-Methods: " . $_ENV['ALLOWED_METHODS']);
header("Access-Control-Allow-Headers: " . $_ENV['ALLOWED_HEADERS']);
header("Access-Control-Allow-Credentials: true");

// Trata requisições OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Captura a rota da requisição
$path = $_GET['route'] ?? '';
$data = json_decode(file_get_contents("php://input"), true);

// Inicializa o controller de autenticação
$controller = new AuthController();

// 🔹 Corrigindo múltiplas respostas enviadas
if (ob_get_length()) {
    ob_clean(); // Limpa qualquer saída anterior
}

$response = $controller->handleRequest($path, $data);

if ($response !== null) {
    echo json_encode($response);
}

exit;
