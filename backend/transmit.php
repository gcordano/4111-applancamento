<?php
require 'db.php';
require 'vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Configuração de CORS
header("Access-Control-Allow-Origin: " . $_ENV['FRONTEND_ORIGIN']);
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Receber os dados enviados pelo frontend
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id'])) {
    http_response_code(400);
    echo json_encode(["message" => "ID do arquivo não fornecido"]);
    return;
}

$fileId = intval($data['id']);

// Buscar o nome do arquivo no banco
$stmt = $pdo->prepare("SELECT name FROM files WHERE id = ?");
$stmt->execute([$fileId]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    http_response_code(404);
    echo json_encode(["message" => "Arquivo não encontrado"]);
    return;
}

// Caminho do script Python
$pythonScript = __DIR__ . "/transmit.py";
$caminhoArquivo = __DIR__ . "/" . $file['name']; // Caminho do XML

// Carregar credenciais do .env
$usuario = $_ENV['API_USER'];
$senha = $_ENV['API_PASSWORD'];
$certificado = __DIR__ . "/" . $_ENV['API_CERTIFICATE']; 
$url = $_ENV['API_URL'];

// Montar o comando para executar o script Python
$command = escapeshellcmd("python3 $pythonScript \"$caminhoArquivo\" \"$url\" \"$usuario\" \"$senha\" \"$certificado\"");
$output = shell_exec($command);

// Verifica se houve erro na execução
if ($output === null) {
    http_response_code(500);
    echo json_encode(["message" => "Erro ao executar script Python"]);
    return;
}

// Retornar resposta
http_response_code(200);
echo json_encode(["message" => "Arquivo transmitido com sucesso", "output" => $output]);
?>
