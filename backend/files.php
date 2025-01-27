<?php
require 'db.php';

// Configuração de CORS
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Lógica para listar todos os arquivos
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['download']) && !isset($_GET['id'])) {
    $stmt = $pdo->query("SELECT id, name FROM files");
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($files);
    exit;
}

// Lógica para buscar um arquivo específico por ID
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id']) && !isset($_GET['download'])) {
    $fileId = $_GET['id'];

    // Busca o arquivo no banco de dados
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($file) {
        // Decodifica o campo `content` armazenado em JSON
        $file['content'] = json_decode($file['content'], true);

        // Retorna todas as informações do arquivo
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
    $name = $data['name'];
    $cnpj = $data['cnpj'];
    $contas = $data['contas']; // Recebe as contas como array do frontend
    $tipoRemessa = $data['tipoRemessa'];
    $dataBase = date('Y-m-d'); // Data atual
    $horaCriacao = date('H-i-s'); // Hora atual para o nome do arquivo

    // Adiciona data e hora ao nome do arquivo
    $nameWithTimestamp = $name . '_' . $dataBase . '_' . $horaCriacao;

    // Salva os dados no banco
    $stmt = $pdo->prepare("INSERT INTO files (name, content) VALUES (?, ?)");
    $content = json_encode([
        "codigoDocumento" => "4111",
        "cnpj" => $cnpj,
        "dataBase" => $dataBase,
        "tipoRemessa" => $tipoRemessa,
        "contas" => $contas,
    ]);
    $stmt->execute([$nameWithTimestamp, $content]);

    http_response_code(201);
    echo json_encode(["message" => "Arquivo criado com sucesso", "id" => $pdo->lastInsertId()]);
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
        $dom->preserveWhiteSpace = false; // Remove espaços em branco extras
        $dom->formatOutput = true; // Habilita a formatação

        // Elemento raiz <documento>
        $documento = $dom->createElement('documento');
        $documento->setAttribute('codigoDocumento', $fileContent['codigoDocumento'] ?? '4111');
        $documento->setAttribute('cnpj', $fileContent['cnpj'] ?? '');
        $documento->setAttribute('dataBase', $fileContent['dataBase'] ?? date('Y-m-d'));
        $documento->setAttribute('tipoRemessa', $fileContent['tipoRemessa'] ?? 'I');
        $dom->appendChild($documento);

        // Elemento <contas>
        $contas = $dom->createElement('contas');
        $documento->appendChild($contas);

        // Adiciona as contas como elementos filhos
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

        // Ajusta os espaços para tabs na identação
        $xmlString = preg_replace_callback('/^(\\s+)/m', function ($matches) {
            // Substitui espaços por tabs (considerando 2 espaços por tab)
            $spaces = strlen($matches[1]);
            $tabs = intdiv($spaces, 2); // Assume 2 espaços por tab no DOMDocument
            return str_repeat("\t", $tabs);
        }, $xmlString);

        // Configuração dos cabeçalhos para forçar o download
        header('Content-Description: File Transfer');
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="' . $file['name'] . '.xml"');
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

//logica para editar
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && isset($_GET['id'])) {
    $fileId = intval($_GET['id']); // Obtém o ID do arquivo da URL

    // Obtém os dados enviados no corpo da requisição
    $data = json_decode(file_get_contents("php://input"), true);

    // Verifica se os dados obrigatórios estão presentes
    if (!isset($data['name'], $data['cnpj'], $data['contas'], $data['tipoRemessa'])) {
        http_response_code(400);
        echo json_encode(["message" => "Dados insuficientes para atualização"]);
        exit;
    }

    $name = $data['name'];
    $cnpj = $data['cnpj'];
    $contas = $data['contas'];
    $tipoRemessa = $data['tipoRemessa'];

    // Atualiza o arquivo no banco
    $content = json_encode([
        "codigoDocumento" => "4111",
        "cnpj" => $cnpj,
        "tipoRemessa" => $tipoRemessa,
        "contas" => $contas,
    ]);

    $stmt = $pdo->prepare("UPDATE files SET name = ?, content = ? WHERE id = ?");
    $stmt->execute([$name, $content, $fileId]);

    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        echo json_encode(["message" => "Arquivo atualizado com sucesso"]);
    } else {
        http_response_code(500);
        echo json_encode(["message" => "Erro ao atualizar o arquivo"]);
    }
    exit;
}


// Lógica para deletar um arquivo
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Obtém os dados enviados no corpo da requisição
    $data = json_decode(file_get_contents("php://input"), true);

    // Verifica se o ID foi enviado
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(["message" => "ID do arquivo não fornecido"]);
        exit;
    }

    $fileId = intval($data['id']); // Converte o ID para um número inteiro

    // Verifica se o arquivo existe antes de deletar
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();

    if (!$file) {
        http_response_code(404);
        echo json_encode(["message" => "Arquivo não encontrado"]);
        exit;
    }

    // Deleta o arquivo
    $stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
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