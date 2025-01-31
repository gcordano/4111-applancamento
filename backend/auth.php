<?php
require_once __DIR__ . "/Config/db.php";
require_once __DIR__ . '/vendor/autoload.php'; // Autoloader do Composer

use Dotenv\Dotenv;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

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
    echo json_encode(["message" => "OK"]);
    return http_response_code(200);
}

// 🔹 Obtendo a conexão Singleton do banco
$db = Database::getInstance()->getConnection();

// 🔹 Função para registrar um novo usuário
function registerUser($db, $email, $password) {
    // Verifica se o usuário já existe
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(["message" => "E-mail já cadastrado"]);
        return http_response_code(409);
    }

    // Hash da senha usando bcrypt
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // Insere no banco
    $stmt = $db->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    $stmt->execute([$email, $hashedPassword]);
    echo json_encode(["message" => "Usuário cadastrado com sucesso"]);
    return http_response_code(201);
    
}

// 🔹 Função para autenticar um usuário (login)
function loginUser($db, $email, $password) {
    // Busca o usuário no banco
    $stmt = $db->prepare("SELECT id, password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verifica se o usuário existe
    if (!$user) {
        echo json_encode(["message" => "Usuário não encontrado"]);
        return http_response_code(404);
    }

    // Verifica a senha usando password_verify()
    if (!password_verify($password, $user['password'])) {
        echo json_encode(["message" => "Senha incorreta"]);
        return http_response_code(401);
    }

    // Gera um token JWT para autenticação
    $secretKey = $_ENV['JWT_SECRET'];
    $payload = [
        "user_id" => $user['id'],
        "exp" => time() + 3600 // Expira em 1 hora
    ];
    $token = JWT::encode($payload, $secretKey, 'HS256');

    echo json_encode(["token" => $token]);
    return;
}

// 🔹 Função para verificar o token JWT
function verifyToken() {
    $headers = getallheaders();

    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(["message" => "Token não fornecido"]);
        return false;
    }

    // 🔹 Extrai o token do cabeçalho Authorization: Bearer <token>
    $authHeader = trim($headers['Authorization']);
    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(["message" => "Token inválido"]);
        return false;
    }

    $token = $matches[1];

    // 🔹 Verifica se a chave secreta está definida
    $secretKey = $_ENV['JWT_SECRET'] ?? '';
    if (empty($secretKey)) {
        http_response_code(500);
        echo json_encode(["message" => "Erro interno: chave JWT não definida"]);
        return false;
    }

    try {
        // 🔹 Decodifica o token
        $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
        return $decoded;
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(["message" => "Token inválido ou expirado"]);
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['protected'])) {
    $decodedToken = verifyToken();
    
    if (!$decodedToken) {
        return;
    }

    echo json_encode([
        "message" => "Acesso permitido!",
        "user_id" => $decodedToken->user_id
    ]);
    return;
}


// 🔹 Função para atualizar a senha do usuário
function updatePassword($db, $email, $oldPassword, $newPassword) {
    // Busca o usuário
    $stmt = $db->prepare("SELECT id, password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(["message" => "Usuário não encontrado"]);
        return http_response_code(404);
    }

    // Verifica se a senha antiga está correta
    if (!password_verify($oldPassword, $user['password'])) {
        echo json_encode(["message" => "Senha antiga incorreta"]);
        return http_response_code(401);
    }

    // Atualiza a senha
    $hashedNewPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$hashedNewPassword, $email]);
    echo json_encode(["message" => "Senha atualizada com sucesso"]);
    return http_response_code(200);
}

// 🔹 Roteamento para chamadas da API de autenticação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['register'])) {
    $data = json_decode(file_get_contents('php://input'), true);
    return registerUser($db, $data['email'], $data['password']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['login'])) {
    $data = json_decode(file_get_contents('php://input'), true);
    return loginUser($db, $data['email'], $data['password']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['update_password'])) {
    $data = json_decode(file_get_contents('php://input'), true);
    return updatePassword($db, $data['email'], $data['old_password'], $data['new_password']);
}

// Caso a requisição não seja válida
echo json_encode(["message" => "Requisição inválida"]);
return http_response_code(400);
?>