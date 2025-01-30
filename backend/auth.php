<?php
require 'db.php';
require_once __DIR__ . '/vendor/autoload.php'; // Autoloader do Composer

use Dotenv\Dotenv;

// Carrega o .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Configuração dinâmica de CORS
header("Access-Control-Allow-Origin: " . $_ENV['FRONTEND_ORIGIN']);
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

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
        // Comparação da senha diretamente (idealmente, use password_hash)
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
