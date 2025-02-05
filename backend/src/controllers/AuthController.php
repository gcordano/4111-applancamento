<?php
namespace App\Controllers;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

class AuthController {
    private $userModel;
    private $secretKey;

    public function __construct() {
        $this->userModel = new User();
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();
        $this->secretKey = $_ENV['JWT_SECRET'] ?? '';
    }

    public function handleRequest($path, $data) {
        switch ($path) {
            case 'login':
                return $this->login($data);
            case 'register':
                return $this->register($data);
            default:
                http_response_code(404);
                return ["message" => "Rota não encontrada"];
        }
    }

    private function login($data) {
        if (!isset($data['email']) || !isset($data['password'])) {
            http_response_code(400);
            return ["message" => "E-mail e senha são obrigatórios"];
        }

        $user = $this->userModel->findByEmail($data['email']);

        if (!$user || !password_verify($data['password'], $user['password'])) {
            http_response_code(401);
            return ["message" => "Credenciais inválidas"];
        }

        // Gerando token JWT
        $payload = [
            "user_id" => $user['id'],
            "exp" => time() + 3600 // Expira em 1 hora
        ];
        $token = JWT::encode($payload, $this->secretKey, 'HS256');

        http_response_code(200);
        return ["message" => "Login bem-sucedido", "token" => $token];
    }

    private function register($data) {
        if (!isset($data['email']) || !isset($data['password'])) {
            http_response_code(400);
            return ["message" => "E-mail e senha são obrigatórios"];
        }

        if ($this->userModel->findByEmail($data['email'])) {
            http_response_code(409);
            return ["message" => "E-mail já cadastrado"];
        }

        $this->userModel->createUser($data['email'], $data['password']);

        http_response_code(201);
        return ["message" => "Usuário cadastrado com sucesso"];
    }
}
