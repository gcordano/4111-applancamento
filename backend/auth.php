<?php
require 'db.php';

// Configuração de CORS
header("Access-Control-Allow-Origin: http://localhost:3000"); // Permite conexões do frontend
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS"); // Permite os métodos necessários
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Permite os cabeçalhos necessários
header("Access-Control-Allow-Credentials: true"); // Permite o envio de cookies ou credenciais

// Trata requisições OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'];
    $password = $data['password'];

    // Busca o usuário no banco de dados
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Compara a senha diretamente
        if ($password === $user['password']) {
            echo json_encode(['token' => base64_encode($user['id'])]);
        } else {
            http_response_code(401);
            echo json_encode(['message' => 'Senha incorreta']);
        }
    } else {
        http_response_code(404);
        echo json_encode(['message' => 'Usuário não encontrado']);
    }
}
?>