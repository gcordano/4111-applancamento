<?php
namespace App\Controllers;

use App\Models\User;

class UserController {
    private $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    public function registerUser($email, $password) {
        return $this->userModel->registerUser($email, $password);
    }

    public function loginUser($email, $password) {
        $user = $this->userModel->loginUser($email, $password); // 🔹 Chama o método do modelo

        if ($user && isset($user['id'])) { // 🔹 Verifica se encontrou um usuário válido
            $token = bin2hex(random_bytes(32)); // 🔹 Gera um token aleatório (pode ser JWT)
            
            return [
                "status" => 200,
                "message" => "Login bem-sucedido",
                "token" => $token
            ];
        } else {
            return [
                "status" => 401,
                "message" => "Credenciais inválidas"
            ];
        }
    }
}
