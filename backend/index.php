<?php
// Incluindo a conexão com o banco de dados
require_once 'db.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: http://localhost:3000"); // Permite requisições do frontend
header("Access-Control-Allow-Methods: POST, GET, OPTIONS"); // Métodos permitidos
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Cabeçalhos permitidos
header("Access-Control-Allow-Credentials: true"); // Permite envio de cookies ou autenticação (opcional)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Pegando o caminho da requisição
$path = isset($_SERVER["PATH_INFO"]) ? trim($_SERVER["PATH_INFO"], '/') : trim($_SERVER["REQUEST_URI"], '/');

// Capturando os dados da requisição
$data = json_decode(file_get_contents("php://input"), true);

// Verificando o endpoint
switch ($path) {
    case 'login': // Endpoint para login
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $data['email'] ?? null;
            $password = $data['password'] ?? null;

            if ($email && $password) {
                // Busca o usuário no banco
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && $password === $user['password']) { // Comparação simples (texto puro)
                    echo json_encode([
                        "message" => "Login bem-sucedido",
                        "token" => base64_encode($user['id'])
                    ]);
                } else {
                    http_response_code(401);
                    echo json_encode(["message" => "Credenciais inválidas"]);
                }
            } else {
                http_response_code(400);
                echo json_encode(["message" => "E-mail e senha são obrigatórios"]);
            }
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Método não permitido"]);
        }
        break;

    case 'register': // Endpoint para registro
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $data['email'] ?? null;
            $password = $data['password'] ?? null;

            if ($email && $password) {
                // Verifica se o e-mail já existe
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    http_response_code(409);
                    echo json_encode(["message" => "E-mail já registrado"]);
                } else {
                    // Insere o novo usuário no banco
                    $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
                    $stmt->execute([$email, $password]);
                    echo json_encode(["message" => "Usuário registrado com sucesso"]);
                }
            } else {
                http_response_code(400);
                echo json_encode(["message" => "E-mail e senha são obrigatórios"]);
            }
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Método não permitido"]);
        }
        break;

    default: // Se o endpoint não existir
        http_response_code(404);
        echo json_encode(["message" => "Endpoint não encontrado"]);
        break;
}
