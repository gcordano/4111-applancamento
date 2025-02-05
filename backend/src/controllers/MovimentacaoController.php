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

            $response = [
                "guid" => $file["guid"],
                "cnpj" => $file["cnpj"],
                "name" => "4111_" . str_replace("-", "", $file["data_movimento"]) . ".xml",
                "tipo_remessa" => $file["tipo_remessa"],
                "contas" => [
                    ["numero" => $file["conta_1"], "saldo" => $file["saldo_conta_1"]],
                    ["numero" => $file["conta_2"], "saldo" => $file["saldo_conta_2"]]
                ],
            ];

            echo json_encode($response);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro ao buscar arquivo", "error" => $e->getMessage()]);
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
    
}    
?>
