<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controllers\MovimentacaoController;
use App\Controllers\AuthController;

// Ativando logs para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configuração de CORS
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: " . ($_ENV['FRONTEND_ORIGIN'] ?? "*"));
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Roteamento unificado
$route = $_GET['route'] ?? '';
error_log("Rota recebida: " . $route);

// 🔹 Instanciando controllers
$movimentacaoController = new MovimentacaoController();
$authController = new AuthController();

switch ($route) {
    // Rotas de Movimentação
    case 'getCnpjsEContas':
        $movimentacaoController->getCnpjsEContas();
        break;

    case 'getDataBase':
        $movimentacaoController->getDataBase();
        break;

    case 'createMovimentacao':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents("php://input"), true);
            $movimentacaoController->createMovimentacao($data);
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Método não permitido"]);
        }
        break;

    // Rotas de Autenticação
    case 'login':
        $authController->handleRequest('login', json_decode(file_get_contents("php://input"), true));
        break;
    case 'getFile':
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
            $controller->getFile($_GET['id']);
        } else {                
            http_response_code(400);
            echo json_encode(["message" => "ID do arquivo não fornecido"]);
        }
        break;
        

    default:
        http_response_code(404);
        echo json_encode(["message" => "Rota não encontrada"]);
        break;
}
