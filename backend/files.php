<?php
require 'db.php';
require_once __DIR__ . '/vendor/autoload.php'; // Autoloader do Composer

use Dotenv\Dotenv;

// Carrega o .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Configuração de CORS
header("Access-Control-Allow-Origin: " . $_ENV['FRONTEND_ORIGIN']);
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Lógica para listar todos os arquivos
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['download']) && !isset($_GET['id'])) {
    $stmt = $pdo->query("SELECT id, name FROM files WHERE status = 1");
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($files);
    exit;
}

// Lógica para buscar um arquivo específico por ID
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id']) && !isset($_GET['download'])) {
    $fileId = $_GET['id'];

    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND status = 1");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($file) {
        $file['content'] = json_decode($file['content'], true);
        echo json_encode($file);
        exit;
    } else {
        http_response_code(404);
        echo json_encode(["message" => "Arquivo não encontrado"]);
        exit;
    }
}

// Lógica para criar novos arquivos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $cnpj = $data['cnpj'];
    $contas = $data['contas'];
    $tipoRemessa = $data['tipoRemessa'];

    // Definir a data do dia anterior no formato correto (YYYY-MM-DD)
    $dataBase = date('Y-m-d', strtotime("-1 day"));
    $fileName = "4111_" . str_replace("-", "", $dataBase) . ".xml"; // Exemplo: 4111_20250128.xml

    // 🔍 Verificar se já existe um arquivo para essa data com status 1
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM files WHERE JSON_UNQUOTE(JSON_EXTRACT(content, '$.dataBase')) = ? AND status = 1");
    $stmt->execute([$dataBase]);
    $existingFile = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingFile['total'] > 0) {
        http_response_code(409); // Código 409 = Conflito
        echo json_encode(["message" => "Já existe um arquivo ativo para essa data."]);
        exit;
    }

    // Criar o novo arquivo se não houver conflito
    $stmt = $pdo->prepare("INSERT INTO files (name, content, status) VALUES (?, ?, 1)");
    $content = json_encode([
        "codigoDocumento" => "4111",
        "cnpj" => $cnpj,
        "dataBase" => $dataBase, 
        "tipoRemessa" => $tipoRemessa,
        "contas" => $contas,
    ]);
    $stmt->execute([$fileName, $content]);

    http_response_code(201);
    echo json_encode(["message" => "Arquivo criado com sucesso", "id" => $pdo->lastInsertId()]);
    exit;
}


// Lógica para editar um arquivo
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && isset($_GET['id'])) {
    $fileId = intval($_GET['id']);
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['cnpj'], $data['contas'], $data['tipoRemessa'])) {
        http_response_code(400);
        echo json_encode(["message" => "Dados insuficientes para atualização"]);
        exit;
    }

    // Buscar a dataBase existente para manter a original e não sobrescrever
    $stmt = $pdo->prepare("SELECT content FROM files WHERE id = ?");
    $stmt->execute([$fileId]);
    $existingFile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingFile) {
        http_response_code(404);
        echo json_encode(["message" => "Arquivo não encontrado"]);
        exit;
    }

    $existingContent = json_decode($existingFile['content'], true);
    $dataBase = $existingContent['dataBase'] ?? date('Y-m-d'); // Mantém a dataBase original

    $content = json_encode([
        "codigoDocumento" => "4111",
        "cnpj" => $data['cnpj'],
        "dataBase" => $dataBase, // Mantendo a data correta
        "tipoRemessa" => $data['tipoRemessa'],
        "contas" => $data['contas'],
    ]);

    $stmt = $pdo->prepare("UPDATE files SET content = ? WHERE id = ?");
    $stmt->execute([$content, $fileId]);

    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        echo json_encode(["message" => "Arquivo atualizado com sucesso"]);
    } else {
        http_response_code(500);
        echo json_encode(["message" => "Nenhuma alteração foi feita"]);
    }
    exit;
}

// Lógica para download de arquivos em XML
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['download'])) {
    $fileId = $_GET['id'];

    // Busca os dados do arquivo no banco
    $stmt = $pdo->prepare("SELECT name, content FROM files WHERE id = ?");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($file) {
        $fileContent = json_decode($file['content'], true);

        // Criar o XML usando DOMDocument
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        // Elemento raiz <documento>
        $documento = $dom->createElement('documento');
        $documento->setAttribute('codigoDocumento', $fileContent['codigoDocumento'] ?? '4111');
        $documento->setAttribute('cnpj', $fileContent['cnpj'] ?? '');
        $documento->setAttribute('dataBase', $fileContent['dataBase'] ?? date('Y-m-d'));
        $documento->setAttribute('tipoRemessa', $fileContent['tipoRemessa'] ?? 'I');
        $dom->appendChild($documento);

        // Elemento <contas> (com 1 tab)
        $contas = $dom->createElement('contas');
        $documento->appendChild($contas);

        // Adiciona as contas como elementos filhos (com 2 tabs)
        if (isset($fileContent['contas']) && is_array($fileContent['contas'])) {
            foreach ($fileContent['contas'] as $conta) {
                $contaNode = $dom->createElement('conta');
                $contaNode->setAttribute('codigoConta', $conta['codigoConta'] ?? '');
                $contaNode->setAttribute('saldoDia', $conta['saldoDia'] ?? '');
                $contas->appendChild($contaNode);
            }
        }

        // Gera o XML como string
        $xmlString = $dom->saveXML();

        // Ajusta os espaços para tabs na identação correta
        $xmlString = preg_replace_callback('/^(  +)/m', function ($matches) {
            // Converte espaços para tabs (assumindo 2 espaços por tab)
            $spaces = strlen($matches[1]);
            $tabs = intdiv($spaces, 2);
            return str_repeat("\t", $tabs);
        }, $xmlString);

        // Configuração dos cabeçalhos para forçar o download
        header('Content-Description: File Transfer');
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="' . $file['name'] . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($xmlString));

        // Retorna o XML formatado para download
        echo $xmlString;
        exit;
    } else {
        http_response_code(404);
        echo json_encode(["message" => "Arquivo não encontrado"]);
        exit;
    }
}

// Lógica para deletar um arquivo
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents("php://input"), true);

    // Verifica se o ID foi enviado
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(["message" => "ID do arquivo não fornecido"]);
        exit;
    }

    $fileId = intval($data['id']); // Converte o ID para inteiro

    // Verifica se o arquivo existe no banco de dados
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();

    if (!$file) {
        http_response_code(404);
        echo json_encode(["message" => "Arquivo não encontrado"]);
        exit;
    }

    // Deleta o arquivo do frontend
    $stmt = $pdo->prepare("UPDATE files SET status = 0 WHERE id = ?");
    $stmt->execute([$fileId]);

    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        echo json_encode(["message" => "Arquivo deletado com sucesso"]);
    } else {
        http_response_code(500);
        echo json_encode(["message" => "Erro ao deletar arquivo"]);
    }
    exit;
}

// Lógica para transmitir um arquivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['transmit'])) {
    $fileId = intval($_POST['id']);

    // Verifica se o arquivo existe
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();

    if (!$file) {
        http_response_code(404);
        echo json_encode(["message" => "Arquivo não encontrado"]);
        exit;
    }

    // Caminho do script Python vindo do .env
    $pythonScript = $_ENV['PYTHON_SCRIPT_PATH'];

    // Comando para executar o script Python
    $command = escapeshellcmd("python3 $pythonScript $fileId");
    $output = shell_exec($command);

    if ($output === null) {
        http_response_code(500);
        echo json_encode(["message" => "Erro ao transmitir o arquivo"]);
        exit;
    }

    http_response_code(200);
    echo json_encode(["message" => "Arquivo transmitido com sucesso", "output" => $output]);
    exit;
}


?>