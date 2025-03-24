<?php
namespace App\controllers;

use App\services\Database;
use PDO;

class MovimentacaoController {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::connect();
    }

    public function getFiles() {
        try {
            $stmt = $this->pdo->query("
                SELECT DISTINCT ON (m.guid) 
                       m.guid, 
                       cnpj.cnpj, 
                       TO_CHAR(m.data_movimento, 'YYYYMMDD') AS data_formatada, 
                       m.tipo_remessa,
                       m.data_movimento,  
                       m.transmition,
                       m.aceite
                FROM movimentacao m
                JOIN contas c ON (m.id_conta_1 = c.guid OR m.id_conta_2 = c.guid)
                JOIN cnpj ON c.id_cnpj = cnpj.id
                WHERE m.enabled = true
                ORDER BY m.guid, m.data_movimento DESC  
            ");
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            if (!$result) {
                http_response_code(200);
                echo json_encode([]);
                return;
            }
    
            $formattedFiles = array_map(function($file) {
                return [
                    "guid" => $file["guid"],
                    "cnpj" => $file["cnpj"],
                    "name" => "4111_" . $file["data_formatada"] . ".xml",
                    "tipo_remessa" => $file["tipo_remessa"],
                    "transmitido" => (bool) $file["transmition"], // Garantindo que 'transmition' é tratado como booleano
                    "aceite" => isset($file["aceite"]) ? (bool) $file["aceite"] : false
                ];
            }, $result);
    
            http_response_code(200);
            echo json_encode($formattedFiles);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro ao buscar arquivos", "error" => $e->getMessage()]);
        }
    }    

    public function getFile($id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT m.guid, cnpj.cnpj, TO_CHAR(m.data_movimento, 'YYYY-MM-DD') AS data_movimento, 
                       m.tipo_remessa, m.saldo_conta_1, m.saldo_conta_2, 
                       c1.conta AS conta_1, c2.conta AS conta_2
                FROM movimentacao m
                JOIN contas c1 ON m.id_conta_1 = c1.guid
                JOIN contas c2 ON m.id_conta_2 = c2.guid
                JOIN cnpj ON c1.id_cnpj = cnpj.id
                WHERE m.guid = :id
                LIMIT 1
            ");
            $stmt->execute(['id' => $id]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if (!$file) {
                http_response_code(404);
                echo json_encode(["message" => "Arquivo não encontrado"]);
                return;
            }
    
            // Verificar se a data foi extraída corretamente
            $dataMovimento = isset($file['data_movimento']) ? $file['data_movimento'] : date('Y-m-d');
            
            $response = [
                "guid" => $file["guid"],
                "cnpj" => $file["cnpj"],
                "name" => "4111_" . str_replace("-", "", $dataMovimento) . ".xml",
                "tipo_remessa" => $file["tipo_remessa"],
                "data_movimento" => $dataMovimento, // Incluindo data_movimento
                "contas" => [
                    ["numero" => $file["conta_1"], "saldo" => $file["saldo_conta_1"]],
                    ["numero" => $file["conta_2"], "saldo" => $file["saldo_conta_2"]]
                ],
            ];
    
            return $response;
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro ao buscar arquivo", "error" => $e->getMessage()]);
        }
    }
    
    
    public function generateXML($id) {
        try {
            // Recupera o arquivo com base no ID
            $file = $this->getFile($id);
            
            if (!$file) {
                http_response_code(404);
                echo json_encode(["message" => "Arquivo não encontrado"]);
                return;
            }
    
            // Verifica se a data_movimento está definida corretamente
            if (!isset($file['data_movimento']) || empty($file['data_movimento'])) {
                http_response_code(500);
                echo json_encode(["message" => "Erro: data_movimento não definida"]);
                return;
            }
    
            // Extrai os dados do arquivo
            $fileContent = [
                "codigoDocumento" => '4111',
                "cnpj" => $file['cnpj'],
                // Pegando diretamente do banco (mas se vier "undefined", vamos calcular a data)
                "dataBase" => $file['data_movimento'],
                "tipoRemessa" => $file['tipo_remessa'],
                "contas" => []
            ];
    
            // Adicionar as contas no XML com verificações para garantir que as chaves existam
            if (isset($file['contas']) && is_array($file['contas'])) {
                foreach ($file['contas'] as $conta) {
                    $fileContent['contas'][] = [
                        'codigoConta' => $conta['numero'] ?? '',
                        'saldoDia' => $conta['saldo'] ?? ''
                    ];
                }
            }
            
            // Se a dataBase estiver "undefined" ou vazia, aplica a regra de negócio:
            if ($fileContent['dataBase'] === 'undefined' || empty($fileContent['dataBase'])) {
                $hoje = new \DateTime();
                if ($hoje->format('N') == 1) { // Se hoje for segunda-feira
                    $hoje->modify('-3 days'); // Usa a data da última sexta
                } else {
                    $hoje->modify('-1 day'); // Nos demais dias, usa o dia anterior
                }
                $fileContent['dataBase'] = $hoje->format('Y-m-d');
            }
    
            // Gerar o XML
            $dom = new \DOMDocument('1.0', 'utf-8');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
    
            // Criar o nó raiz <documento>
            $documento = $dom->createElement('documento');
            $documento->setAttribute('codigoDocumento', $fileContent['codigoDocumento']);
            $documento->setAttribute('cnpj', $fileContent['cnpj']);
            $documento->setAttribute('dataBase', $fileContent['dataBase']);
            $documento->setAttribute('tipoRemessa', $fileContent['tipoRemessa']);
            $dom->appendChild($documento);
    
            // Criar o nó <contas>
            $contas = $dom->createElement('contas');
            $documento->appendChild($contas);
    
            // Adicionar as contas no XML
            foreach ($fileContent['contas'] as $conta) {
                $contaNode = $dom->createElement('conta');
                $contaNode->setAttribute('codigoConta', $conta['codigoConta']);
                $contaNode->setAttribute('saldoDia', $conta['saldoDia']);
                $contas->appendChild($contaNode);
            }
    
            // Gerar o XML como string
            $xmlString = $dom->saveXML();
    
            // Ajusta os espaços para tabs na indentação correta
            $xmlString = preg_replace_callback('/^(  +)/m', function ($matches) {
                $spaces = strlen($matches[1]);
                $tabs = intdiv($spaces, 2);
                return str_repeat("\t", $tabs);
            }, $xmlString);
    
            // 🚀 **Correção do nome do arquivo**
            $formattedDate = str_replace("-", "", trim($fileContent['dataBase']));
    
            if (empty($formattedDate) || strlen($formattedDate) !== 8) {
                http_response_code(500);
                echo json_encode(["message" => "Erro ao formatar data_movimento"]);
                return;
            }
    
            $fileName = "4111_" . $formattedDate . ".xml";
    
            // Configurações para o download do arquivo XML
            header('Content-Type: application/xml');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . strlen($xmlString));
    
            echo $xmlString;
            exit;
    
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro ao gerar XML", "error" => $e->getMessage()]);
        }
    }    
    
    
    public function getCnpjsEContas() {
        try {
            $stmt = $this->pdo->query("
                SELECT cnpj.id, cnpj.cnpj, cnpj.name, contas.guid as conta_id, contas.conta
                FROM cnpj
                JOIN contas ON contas.id_cnpj = cnpj.id 
                WHERE cnpj.enabled = true AND contas.enabled = true
            ");
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($result)) {
                http_response_code(404);
                echo json_encode(["message" => "Nenhum CNPJ encontrado"]);
                return;
            }

            error_log("getCnpjsEContas: " . json_encode($result));
            echo json_encode($result);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro ao buscar CNPJs e contas", "error" => $e->getMessage()]);
        }
    }

    public function createMovimentacao($data) {
        try {
            // Verifica se já existe uma movimentação ativa para a data do arquivo
            $existingMovimentacao = $this->checkExistingMovimentacao($data);
    
            if ($existingMovimentacao['exists']) {
                // Se já existir, não cria o arquivo
                http_response_code(400);
                echo json_encode(["message" => $existingMovimentacao['message']]);
                return;
            }
    
            // Se não existir, cria a movimentação
            $stmt = $this->pdo->prepare("
                INSERT INTO movimentacao (id_conta_1, saldo_conta_1, id_conta_2, saldo_conta_2, tipo_remessa, data_movimento)
                VALUES (:id_conta_1, :saldo_conta_1, :id_conta_2, :saldo_conta_2, :tipo_remessa, :data_movimento)
            ");
            $stmt->execute([
                ':id_conta_1' => $data['id_conta_1'],
                ':saldo_conta_1' => $data['saldo_conta_1'],
                ':id_conta_2' => $data['id_conta_2'],
                ':saldo_conta_2' => $data['saldo_conta_2'],
                ':tipo_remessa' => $data['tipo_remessa'],
                ':data_movimento' => $data['data_movimento'] // Passando a data calculada
            ]);
    
            http_response_code(201);
            echo json_encode(["message" => "Movimentação criada com sucesso."]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro ao criar movimentação", "error" => $e->getMessage()]);
        }
    }    

    public function checkExistingMovimentacao($data) {
        try {
            // Formatar data recebida para YYYY-MM-DD
            $data_movimento = date("Y-m-d", strtotime($data['data_movimento']));
    
            // Verifica se já existe uma movimentação para a mesma data e com 'enabled' como true
            $stmt = $this->pdo->prepare("
                SELECT guid FROM movimentacao 
                WHERE TO_CHAR(data_movimento, 'YYYY-MM-DD') = :data_movimento
                AND enabled = true
            ");
            $stmt->execute([':data_movimento' => $data_movimento]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if ($result) {
                // Se já existe, retornamos um erro
                return ["message" => "Já existe uma movimentação ativa para essa data.", "exists" => true];
            } else {
                return ["exists" => false];
            }
        } catch (\Exception $e) {
            return ["message" => "Erro ao verificar movimentação existente", "error" => $e->getMessage()];
        }
    }

    public function updateMovimentacao($id) {
        try {
            // 📌 Recebe os dados corretamente
            $data = json_decode(file_get_contents("php://input"), true);
    
            // 📌 Debug dos dados recebidos
            error_log("📥 Dados recebidos no backend (corrigido): " . json_encode($data));
    
            // 📌 Validação dos parâmetros necessários
            if (!isset($data['saldo_conta_1'], $data['saldo_conta_2'], $data['tipo_remessa'])) {
                http_response_code(400);
                echo json_encode(["message" => "Erro: Parâmetros incompletos", "data_received" => $data]);
                return;
            }
    
            // 📌 Atualiza SOMENTE saldo e tipo_remessa
            $stmt = $this->pdo->prepare("
                UPDATE movimentacao 
                SET saldo_conta_1 = :saldo_conta_1, 
                    saldo_conta_2 = :saldo_conta_2, 
                    tipo_remessa = :tipo_remessa, 
                    updatedAt = NOW()
                WHERE guid = :id
            ");
    
            $stmt->execute([
                ':id' => $id,
                ':saldo_conta_1' => $data['saldo_conta_1'],
                ':saldo_conta_2' => $data['saldo_conta_2'],
                ':tipo_remessa' => $data['tipo_remessa'],
            ]);
    
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["message" => "Movimentação não encontrada ou nenhuma alteração feita"]);
                return;
            }
    
            echo json_encode(["message" => "Movimentação atualizada com sucesso!"]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro ao atualizar movimentação", "error" => $e->getMessage()]);
        }
    }

    public function deleteMovimentacao($id) {
        try {
            // 📌 Verifica se a movimentação existe antes de inativar
            $stmt = $this->pdo->prepare("SELECT enabled FROM movimentacao WHERE guid = :id");
            $stmt->execute([':id' => $id]);
            $movimentacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if (!$movimentacao) {
                http_response_code(404);
                echo json_encode(["message" => "Movimentação não encontrada."]);
                error_log("❌ Movimentação não encontrada no banco.");
                return;
            }
    
            // 📌 Atualiza a coluna `enabled` para `false`
            $stmt = $this->pdo->prepare("UPDATE movimentacao SET enabled = false, updatedAt = NOW() WHERE guid = :id");
            $stmt->execute([':id' => $id]);
    
            // 📌 Verifica se a atualização realmente ocorreu
            if ($stmt->rowCount() > 0) {
                http_response_code(200);
                echo json_encode(["message" => "Arquivo inativado com sucesso!", "id" => $id]);
            } else {
                http_response_code(400);
                echo json_encode(["message" => "Erro ao inativar movimentação. Nenhuma linha foi alterada."]);
            }
    
            // 📌 Confirmação de consulta após atualização
            $stmt = $this->pdo->prepare("SELECT enabled FROM movimentacao WHERE guid = :id");
            $stmt->execute([':id' => $id]);
            $updatedMovimentacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno ao inativar movimentação", "error" => $e->getMessage()]);
        }
    } 
    
    public function transmit($id) {
        try {
            // 1. Recupera os dados do arquivo (utiliza o método getFile)
            $file = $this->getFile($id);
            if (!$file) {
                http_response_code(404);
                echo json_encode(["message" => "Arquivo não encontrado"]);
                return;
            }
    
            // 2. Monta o conteúdo (similar à generateXML)
            if (!isset($file['data_movimento']) || empty($file['data_movimento'])) {
                http_response_code(500);
                echo json_encode(["message" => "Erro: data_movimento não definida"]);
                return;
            }
    
            $fileContent = [
                "codigoDocumento" => '4111',
                "cnpj" => $file['cnpj'],
                "dataBase" => $file['data_movimento'],
                "tipoRemessa" => $file['tipo_remessa'],
                "contas" => []
            ];
    
            if (isset($file['contas']) && is_array($file['contas'])) {
                foreach ($file['contas'] as $conta) {
                    $fileContent['contas'][] = [
                        'codigoConta' => $conta['numero'] ?? '',
                        'saldoDia' => $conta['saldo'] ?? ''
                    ];
                }
            }
    
            if ($fileContent['dataBase'] === 'undefined' || empty($fileContent['dataBase'])) {
                $hoje = new \DateTime();
                if ($hoje->format('N') == 1) { // Segunda-feira
                    $hoje->modify('-3 days'); // Última sexta
                } else {
                    $hoje->modify('-1 day'); // Dia anterior
                }
                $fileContent['dataBase'] = $hoje->format('Y-m-d');
            }
    
            // 3. Gerar o XML
            $dom = new \DOMDocument('1.0', 'utf-8');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
    
            $documento = $dom->createElement('documento');
            $documento->setAttribute('codigoDocumento', $fileContent['codigoDocumento']);
            $documento->setAttribute('cnpj', $fileContent['cnpj']);
            $documento->setAttribute('dataBase', $fileContent['dataBase']);
            $documento->setAttribute('tipoRemessa', $fileContent['tipoRemessa']);
            $dom->appendChild($documento);
    
            $contasNode = $dom->createElement('contas');
            $documento->appendChild($contasNode);
    
            foreach ($fileContent['contas'] as $conta) {
                $contaNode = $dom->createElement('conta');
                $contaNode->setAttribute('codigoConta', $conta['codigoConta']);
                $contaNode->setAttribute('saldoDia', $conta['saldoDia']);
                $contasNode->appendChild($contaNode);
            }
    
            $xmlString = $dom->saveXML();
            $xmlString = preg_replace_callback('/^(  +)/m', function ($matches) {
                $spaces = strlen($matches[1]);
                $tabs = intdiv($spaces, 2);
                return str_repeat("\t", $tabs);
            }, $xmlString);
    
            // 4. Define o nome do arquivo com base na data
            $formattedDate = str_replace("-", "", trim($fileContent['dataBase']));
            if (empty($formattedDate) || strlen($formattedDate) !== 8) {
                http_response_code(500);
                echo json_encode(["message" => "Erro ao formatar data_movimento"]);
                return;
            }
            $fileName = "4111_" . $formattedDate . ".xml";
    
            // 5. Salva o XML no diretório desejado
            $directory = $_ENV['API_DIR'];
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            $filePath = $directory . $fileName;
            if (file_put_contents($filePath, $xmlString) === false) {
                http_response_code(500);
                echo json_encode(["message" => "Erro ao salvar o arquivo no servidor"]);
                return;
            }

            // 6. Calcula o hash SHA256 e o tamanho do XML
            $hash = hash('sha256', $xmlString);
            $tamanho = strlen($xmlString);
    
            // 7. Prepara as variáveis para o protocolo
            $staUrl = $_ENV['API_URL_PROTOCOLO'];
            $staUser = $_ENV['API_USER'];
            $staPass = $_ENV['API_PASSWORD'];
            $staObs  = $_ENV['STA_OBSERVACAO'] ?? 'envio 4111';
            $auth = base64_encode($staUser . ':' . $staPass);

            // 8. Abertura do Protocolo – monta os parâmetros
            $paramsXML = '<?xml version="1.0" encoding="UTF-8"?>'
                . '<Parametros>'
                . '<IdentificadorDocumento>4111</IdentificadorDocumento>'
                . '<Hash>' . $hash . '</Hash>'
                . '<Tamanho>' . $tamanho . '</Tamanho>'
                . '<NomeArquivo>' . $fileName . '</NomeArquivo>'
                . '<Observacao>' . $staObs . '</Observacao>'
                . '</Parametros>';
    
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $staUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/xml',
                'Authorization: Basic ' . $auth
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $paramsXML);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $protocolResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (curl_errno($ch)) {
                curl_close($ch);
                http_response_code(500);
                echo json_encode(["message" => "Erro na abertura do protocolo: " . curl_error($ch)]);
                return;
            }
            curl_close($ch);
    
            if ($httpCode !== 200 && $httpCode !== 201) {
                http_response_code(500);
                echo json_encode(["message" => "Erro na abertura do protocolo, código HTTP: " . $httpCode, "response" => $protocolResponse]);
                return;
            }
    
            // 9. Extrai o protocolo da resposta
            $xmlResponse = simplexml_load_string($protocolResponse);
            if (!$xmlResponse || !isset($xmlResponse->Protocolo)) {
                http_response_code(500);
                echo json_encode(["message" => "Resposta inválida na abertura do protocolo", "response" => $protocolResponse]);
                return;
            }
            $protocolo = (string)$xmlResponse->Protocolo;
            // Nova etapa: salva o número do protocolo no banco de dados
            $this->updateProtocol($id, $protocolo);
    
            // 10. Envio do arquivo XML – chamada PUT
            $putUrl = $staUrl . '/' . $protocolo . '/conteudo';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $putUrl);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/xml',
                'Authorization: Basic ' . $auth
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlString);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $putResponse = curl_exec($ch);
            $httpCodePut = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (curl_errno($ch)) {
                curl_close($ch);
                http_response_code(500);
                echo json_encode(["message" => "Erro no envio do arquivo: " . curl_error($ch)]);
                return;
            }
            curl_close($ch);
    
            if ($httpCodePut !== 200 && $httpCodePut !== 201) {
                http_response_code(500);
                echo json_encode(["message" => "Erro no envio do arquivo, código HTTP: " . $httpCodePut, "response" => $putResponse]);
                return;
            }
    
            // 11. Verificação da Transmissão – chamada GET
            $getUrl = $staUrl . '/' . $protocolo . '/posicaoupload';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $getUrl);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Basic ' . $auth
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $getResponse = curl_exec($ch);
            $httpCodeGet = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (curl_errno($ch)) {
                curl_close($ch);
                http_response_code(500);
                echo json_encode(["message" => "Erro na verificação da transmissão: " . curl_error($ch)]);
                return;
            }
            curl_close($ch);
    
            // Parse do XML de resposta de verificação
            $statusXML = simplexml_load_string($getResponse);
            if (!$statusXML || !isset($statusXML->Situacao)) {
                http_response_code(500);
                echo json_encode(["message" => "Resposta inválida na verificação da transmissão", "response" => $getResponse]);
                return;
            }
    
            // Verifica a situação e retorna o JSON adequado
            $situacao = (string)$statusXML->Situacao;
            if (strpos($situacao, 'Transmissão finalizada') !== false) {
                // Atualiza o status da transmissão no banco de dados para 'enable'
                $this->updateTransmissionStatus($id);
                
                echo json_encode(["message" => "finalizada com sucesso!", "transmitido" => true]);
                //$this->saveFileContent($fileName, $xmlString);
            } else {
                echo json_encode(["message" => "Erro na transmissão: " . $situacao, "transmitido" => false]);
            }
    
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro ao transmitir XML", "error" => $e->getMessage()]);
        }
    }   

    // Método para atualizar o status da transmissão para "enable"
private function updateTransmissionStatus($id) {
    try {
        // Atualiza a coluna `transmission` para 'enable'
        $stmt = $this->pdo->prepare("UPDATE movimentacao SET transmition = 'true', updatedAt = NOW() WHERE guid = :id");
        $stmt->execute([':id' => $id]);
    } catch (\PDOException $e) {
        echo json_encode(["message" => "Erro ao atualizar o status da transmissão", "error" => $e->getMessage()]);
        http_response_code(500);
    }
}

private function updateProtocol($id, $protocol) {
    $stmt = $this->pdo->prepare("UPDATE movimentacao SET protocolo = :protocol WHERE guid = :id");
    $stmt->bindValue(':protocol', $protocol);
    $stmt->bindValue(':id', $id);
    $stmt->execute();
}

// Função fictícia para salvar o conteúdo do arquivo na tabela files  [XML]]
// private function saveFileContent($fileName, $xmlString) {
//     $xml = simplexml_load_string($xmlString);
//     if ($xml === false) {
//         throw new Exception("Erro ao validar XML");
//     }
//     $dom = new \DOMDocument('1.0', 'utf-8');
//     $dom->preserveWhiteSpace = false;
//     $dom->formatOutput = true;

//     // Carrega o conteúdo do XML em DOMDocument
//     $dom->loadXML($xmlString);

//     // Converte o conteúdo para uma string
//     $xmlString = $dom->saveXML();
//     // Insere no banco de dados
//     $xmlString = preg_replace('/\s+/', ' ', $xmlString);
//     $stmt = $this->pdo->prepare("INSERT INTO files (file_name, file_content, created_at, updated_at) VALUES (:fileName, :xmlString, NOW(), NOW())");
    
//     // Binding dos parâmetros
    
//     $stmt->bindParam(':fileName', $fileName);
//     $stmt->bindParam(':xmlString', $xmlString);
//     return $stmt->execute();
// }

// private function saveFileContent($fileName, $xmlString) {
//     // Verifica se o XML é válido
//     $xml = simplexml_load_string($xmlString);
//     if ($xml === false) {
//         throw new Exception("Erro ao validar XML");
//     }

//     // Converte o XML para uma string
//     $dom = new \DOMDocument('1.0', 'utf-8');
//     $dom->preserveWhiteSpace = false;
//     $dom->formatOutput = true;
    
//     // Carrega o XML em DOMDocument
//     $dom->loadXML($xmlString);

//     // Converte o conteúdo para uma string
//     $xmlContent = $dom->saveXML();
    
//     // Agora vamos embalar o conteúdo XML dentro de um JSON
//     $jsonData = json_encode(['xml_content' => $xmlContent]);

//     // Insere o JSON no banco de dados
//     $stmt = $this->pdo->prepare("INSERT INTO files (file_name, file_content, created_at, updated_at) VALUES (:fileName, :fileContent, NOW(), NOW())");
    
//     // Binding dos parâmetros
//     $stmt->bindParam(':fileName', $fileName);
//     $stmt->bindParam(':fileContent', $jsonData);  // Salva o JSON com o XML dentro
    
//     // Executa a inserção no banco de dados
//     return $stmt->execute();
// }


}
?>