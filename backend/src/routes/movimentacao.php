<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controllers\MovimentacaoController;

// Configuração de CORS
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: " . ($_ENV['FRONTEND_ORIGIN'] ?? "*"));
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 🔹 Inicializa o controlador
$controller = new MovimentacaoController();

// 🔹 Captura a rota da requisição
$route = $_GET['route'] ?? '';

switch ($route) {
    case 'getCnpjsEContas':
        $controller->getCnpjsEContas();
        break;

    case 'getDataBase':
        $controller->getDataBase();
        break;

    case 'getFiles':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $controller->getFiles();
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Método não permitido"]);
        }
        break;

    case 'getFile': 
        if (isset($_GET['id'])) {
            $file = $controller->getFile($_GET['id']);
            if ($file) {  // Se encontrou o arquivo
            echo json_encode($file);
        }
        } else {                
            http_response_code(400);
            echo json_encode(["message" => "ID do arquivo é obrigatório"]);
        }
        break;        

    case 'create':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents("php://input"), true);
            $controller->createMovimentacao($data);
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Método não permitido"]);
        }
        break;

    case 'checkExistingMovimentacao':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents("php://input"), true);
            echo json_encode($controller->checkExistingMovimentacao($data));
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Método não permitido"]);
        }
        break;

    case 'generateXML':
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
            $controller->generateXML($_GET['id']);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "ID do arquivo é obrigatório"]);
        }
        break;
            

    case 'update':
        if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            parse_str(file_get_contents("php://input"), $data);
            $controller->updateMovimentacao($_GET['id'], $data);
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Método não permitido"]);
        }
        break;

    case 'delete':
        if ($_SERVER["REQUEST_METHOD"] === "PUT") { 
            $id = $_GET["id"] ?? null;
            if ($id) {
                $controller->deleteMovimentacao($id); 
            } else {
                http_response_code(400);
                echo json_encode(["message" => "ID não informado para deletar."]);
            }
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Método não permitido"]);
        }
        break;

    case 'transmit':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents("php://input"), true);
            if (isset($data['id'])) {
                $controller->transmit($data['id']);
            } else {
                http_response_code(400);
                echo json_encode(["message" => "ID do arquivo não informado"]);
            }
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Método não permitido"]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Rota não encontrada"]);
        break;
}
