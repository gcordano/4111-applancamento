<?php
require 'db.php';

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Receber os dados enviados pelo frontend
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id'])) {
    http_response_code(400);
    echo json_encode(["message" => "ID do arquivo não fornecido"]);
    exit;
}

$fileId = intval($data['id']);

// Buscar o nome do arquivo no banco
$stmt = $pdo->prepare("SELECT name FROM files WHERE id = ?");
$stmt->execute([$fileId]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    http_response_code(404);
    echo json_encode(["message" => "Arquivo não encontrado"]);
    exit;
}

// Caminho do script Python
$pythonScript = __DIR__ . "/transmit.py";
$caminhoArquivo = __DIR__ . "/" . $file['name']; // Caminho completo do arquivo XML

// Dados para autenticação e certificado
$usuario = "444920001.S-DEVHOMOL";
$senha = "dv527atc";
$certificado = __DIR__ . "/certificado.pem"; // Certificado no servidor
$url = "https://sta-h.bcb.gov.br/api/exemplo";

// Montar o comando para executar o script Python
$command = escapeshellcmd("python3 $pythonScript \"$caminhoArquivo\" \"$url\" \"$usuario\" \"$senha\" \"$certificado\"");
$output = shell_exec($command);

// Retornar resposta
if ($output) {
    http_response_code(200);
    echo json_encode(["message" => "Arquivo $fileId transmitido com sucesso", "output" => $output]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Erro ao transmitir o arquivo"]);
}
?>
