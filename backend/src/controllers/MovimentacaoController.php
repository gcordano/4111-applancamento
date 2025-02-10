<?php
namespace App\Controllers;

use App\Services\Database;
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
                       m.data_movimento  
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
                    "tipo_remessa" => $file["tipo_remessa"]
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
    
            // 2. Gerar o conteúdo XML para transmissão (mesma estrutura do XML gerado)
            $dom = new \DOMDocument('1.0', 'utf-8');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
    
            // Cria o nó raiz <documento>
            $documento = $dom->createElement('documento');
            $documento->setAttribute('codigoDocumento', '4111');
            $documento->setAttribute('cnpj', $file['cnpj']);
            $documento->setAttribute('dataBase', $file['data_movimento']);
            $documento->setAttribute('tipoRemessa', $file['tipo_remessa']);
            $dom->appendChild($documento);
    
            // Cria o nó <contas>
            $contas = $dom->createElement('contas');
            $documento->appendChild($contas);
    
            foreach ($file['contas'] as $conta) {
                $contaNode = $dom->createElement('conta');
                $contaNode->setAttribute('codigoConta', $conta['numero']);
                $contaNode->setAttribute('saldoDia', $conta['saldo']);
                $contas->appendChild($contaNode);
            }
    
            $xmlString = $dom->saveXML();
    
            // 3. Calcular o hash SHA256 e o tamanho do arquivo
            $hash = hash('sha256', $xmlString);
            $tamanho = strlen($xmlString);
    
            // 4. Gerar o nome do arquivo conforme o padrão: 4111_YYYYMMDD.xml
            $formattedDate = str_replace("-", "", $file['data_movimento']);
            $nomeArquivo = "4111_" . $formattedDate . ".xml";
    
            // 5. Ler as configurações do .env
            $staUrl = $_ENV['STA_URL'] ?? 'https://sta-h.bcb.gov.br/staws/arquivos';
            $staUser = $_ENV['STA_USER'] ?? '';
            $staPass = $_ENV['STA_PASSWORD'] ?? '';
            $staObs  = $_ENV['STA_OBSERVACAO'] ?? 'Teste de envio S no ambiente de homologação';
    
            $auth = base64_encode($staUser . ':' . $staPass);
    
            // 6. Abertura do Protocolo – Envia os parâmetros via POST
            $paramsXML = '<?xml version="1.0" encoding="UTF-8"?>'
                . '<Parametros>'
                . '<IdentificadorDocumento>4111</IdentificadorDocumento>'
                . '<Hash>' . $hash . '</Hash>'
                . '<Tamanho>' . $tamanho . '</Tamanho>'
                . '<NomeArquivo>' . $nomeArquivo . '</NomeArquivo>'
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
                $error_msg = curl_error($ch);
                curl_close($ch);
                http_response_code(500);
                echo json_encode(["message" => "Erro na abertura do protocolo: " . $error_msg]);
                return;
            }
            curl_close($ch);
    
            if ($httpCode !== 200 && $httpCode !== 201) {
                http_response_code(500);
                echo json_encode(["message" => "Erro na abertura do protocolo, código HTTP: " . $httpCode, "response" => $protocolResponse]);
                return;
            }
    
            // Parse a resposta para extrair o protocolo (supondo que o XML de resposta contenha a tag <Protocolo>)
            $xmlResponse = simplexml_load_string($protocolResponse);
            if (!$xmlResponse || !isset($xmlResponse->Protocolo)) {
                http_response_code(500);
                echo json_encode(["message" => "Resposta inválida na abertura do protocolo", "response" => $protocolResponse]);
                return;
            }
            $protocolo = (string)$xmlResponse->Protocolo;
    
            // 7. Envio do arquivo XML – PUT para {STA_URL}/{protocolo}/conteudo
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
                $error_msg = curl_error($ch);
                curl_close($ch);
                http_response_code(500);
                echo json_encode(["message" => "Erro no envio do arquivo: " . $error_msg]);
                return;
            }
            curl_close($ch);
    
            if ($httpCodePut !== 200 && $httpCodePut !== 201) {
                http_response_code(500);
                echo json_encode(["message" => "Erro no envio do arquivo, código HTTP: " . $httpCodePut, "response" => $putResponse]);
                return;
            }
    
            // 8. Verificação da Transmissão – GET para {STA_URL}/{protocolo}/posicaoupload
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
                $error_msg = curl_error($ch);
                curl_close($ch);
                http_response_code(500);
                echo json_encode(["message" => "Erro na verificação da transmissão: " . $error_msg]);
                return;
            }
            curl_close($ch);
    
            $statusXML = simplexml_load_string($getResponse);
            if (!$statusXML || !isset($statusXML->Situacao)) {
                http_response_code(500);
                echo json_encode(["message" => "Resposta inválida na verificação da transmissão", "response" => $getResponse]);
                return;
            }
            $situacao = (string)$statusXML->Situacao;
    
            // 9. Se a situação for "Transmissão finalizada", consideramos sucesso
            if (strpos($situacao, 'Transmissão finalizada') !== false) {
                // Aqui você pode atualizar o registro no banco para marcar como transmitido, se desejar.
                echo json_encode(["message" => "Transmissão finalizada com sucesso!", "transmitido" => true]);
            } else {
                echo json_encode(["message" => "Erro na transmissão: " . $situacao, "transmitido" => false]);
            }
    
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro ao transmitir XML", "error" => $e->getMessage()]);
        }
    }
    
}    
?>