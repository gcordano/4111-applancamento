<?php
require_once __DIR__ . "/Config/db.php";
require_once __DIR__ . '/vendor/autoload.php'; // Autoloader do Composer

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Configuração de CORS
header("Access-Control-Allow-Origin: " . $_ENV['FRONTEND_ORIGIN']);
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    return;

}

// 🔹 Obtendo a conexão Singleton do banco
$db = Database::getInstance()->getConnection();
 
// 🔹 Listar todos os arquivos
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['download']) && !isset($_GET['id'])) {
    $stmt = $db->query("SELECT id, name FROM files WHERE status = 'True'");
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode($files);
    return;
}

// 🔹 Buscar um arquivo específico por ID
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id']) && !isset($_GET['download'])) {
    $fileId = intval($_GET['id']);

    $stmt = $db->prepare("SELECT * FROM files WHERE id = ? AND status = 'True'");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($file) {
        $file['content'] = json_decode($file['content'], true);
        echo json_encode($file);
    } else {
        http_response_code(404);
        echo json_encode(["message" => "Arquivo não encontrado"]);
    }
    return;
}

// 🔹 Criar um novo arquivo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data || !isset($data['cnpj'], $data['contas'], $data['tipoRemessa'])) {
        http_response_code(400);
        echo json_encode(["message" => "Dados inválidos"]);
        return;
    }

    // 🔹 Obtém o dia da semana atual
    $hoje = date('Y-m-d'); // Data atual
    $diaSemana = date('N'); // 1 = segunda-feira, 2 = terça, ..., 5 = sexta

    if ($diaSemana == 1) { 
        // 🔹 Se for SEGUNDA-FEIRA, usa a data da última SEXTA-FEIRA
        $dataBase = date('Y-m-d', strtotime("-3 days", strtotime($hoje)));
    } else { 
        // 🔹 Para os demais dias (terça a sexta), gera o arquivo referente ao dia anterior
        $dataBase = date('Y-m-d', strtotime("-1 day", strtotime($hoje)));
    }

    $fileName = "4111_" . str_replace("-", "", $dataBase) . ".xml";

    // 🔹 Verifica se já existe um arquivo ativo para essa data
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM files WHERE content::jsonb ->> 'dataBase' = ? AND status = 'True'");
    $stmt->execute([$dataBase]);
    $existingFile = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingFile['total'] > 0) {
        http_response_code(409);
        echo json_encode(["message" => "Já existe um arquivo ativo para essa data."]);
        return;
    }

    // 🔹 Criação do conteúdo do arquivo
    $content = json_encode([
        "codigoDocumento" => "4111",
        "cnpj" => $data['cnpj'],
        "dataBase" => $dataBase,
        "tipoRemessa" => $data['tipoRemessa'],
        "contas" => $data['contas'],
    ]);

    // 🔹 Insere no banco
    $stmt = $db->prepare("INSERT INTO files (name, content, status) VALUES (?, ?, 'True')");
    $stmt->execute([$fileName, $content]);

    http_response_code(201);
    echo json_encode(["message" => "Arquivo criado com sucesso", "id" => $db->lastInsertId(), "dataBase" => $dataBase]);
    return;
}


// 🔹 Editar um arquivo
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && isset($_GET['id'])) {
    $fileId = intval($_GET['id']);
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['cnpj'], $data['contas'], $data['tipoRemessa'])) {
        return http_response_code(400);
        echo json_encode(["message" => "Dados insuficientes para atualização"]);
        return;
    }

    $stmt = $db->prepare("SELECT content FROM files WHERE id = ?");
    $stmt->execute([$fileId]);
    $existingFile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingFile) {
        return http_response_code(404);
        echo json_encode(["message" => "Arquivo não encontrado"]);
        return;
    }

    $existingContent = json_decode($existingFile['content'], true);
    $dataBase = $existingContent['dataBase'] ?? date('Y-m-d');

    $content = json_encode([
        "codigoDocumento" => "4111",
        "cnpj" => $data['cnpj'],
        "dataBase" => $dataBase,
        "tipoRemessa" => $data['tipoRemessa'],
        "contas" => $data['contas'],
    ]);

    $stmt = $db->prepare("UPDATE files SET content = ? WHERE id = ?");
    $stmt->execute([$content, $fileId]);

    http_response_code(200);
    echo json_encode(["message" => "Arquivo atualizado com sucesso"]);
    return;
}

// Lógica para download de arquivos em XML
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['download'])) {
    $fileId = $_GET['id'];

    // Busca os dados do arquivo no banco
    $stmt = $db->prepare("SELECT name, content FROM files WHERE id = ?");
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

// 🔹 Deletar um arquivo (soft delete)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(["message" => "ID do arquivo não fornecido"]);
        return;
    }

    $fileId = intval($data['id']);
    $stmt = $db->prepare("UPDATE files SET status = 'False' WHERE id = ?");
    $stmt->execute([$fileId]);

    http_response_code(200);
    echo json_encode(["message" => "Arquivo deletado com sucesso"]);
    return;
}

// 🔹 Transmitir um arquivo via script Python
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['transmit'])) {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(["message" => "ID não fornecido"]);
        return;
    }

    $fileId = intval($data['id']);
    $pythonScript = $_ENV['PYTHON_SCRIPT_PATH'];
    $command = escapeshellcmd("python3 $pythonScript $fileId");
    $output = shell_exec($command);

    if ($output === null) {
        http_response_code(500);
        echo json_encode(["message" => "Erro ao transmitir o arquivo"]);
        return;
    }

    http_response_code(200);
    echo json_encode(["message" => "Arquivo transmitido com sucesso", "output" => $output]);
    return;
}

// Caso a requisição não seja válida
http_response_code(400);
echo json_encode(["message" => "Requisição inválida"]);
return;
?>
