<?php
/**
 * Controlador de transações
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/Transaction.php';

class TransactionController {
    private $db;
    private $auth;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auth = new AuthMiddleware();
    }
    
    public function getTransactions() {
        $auth_data = $this->auth->authenticate();
        
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? 20;
        $type = $_GET['type'] ?? null;
        $category_id = $_GET['category_id'] ?? null;
        $start_date = $_GET['start_date'] ?? null;
        $end_date = $_GET['end_date'] ?? null;
        
        $offset = ($page - 1) * $limit;
        
        try {
            $transaction = new Transaction($this->db);
            $filters = [
                'type' => $type,
                'category_id' => $category_id,
                'start_date' => $start_date,
                'end_date' => $end_date
            ];
            
            $transactions = $transaction->getByUser($auth_data->user_id, $filters, $limit, $offset);
            $total = $transaction->countByUser($auth_data->user_id, $filters);
            
            echo json_encode([
                "transactions" => $transactions,
                "pagination" => [
                    "current_page" => (int)$page,
                    "total_items" => (int)$total,
                    "total_pages" => ceil($total / $limit),
                    "limit" => (int)$limit
                ]
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function createTransaction() {
        $auth_data = $this->auth->authenticate();
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validações
        if (!isset($data['type']) || !isset($data['amount']) || !isset($data['description'])) {
            http_response_code(400);
            echo json_encode(["message" => "Tipo, valor e descrição são obrigatórios"]);
            return;
        }
        
        if (!in_array($data['type'], ['receita', 'despesa'])) {
            http_response_code(400);
            echo json_encode(["message" => "Tipo deve ser 'receita' ou 'despesa'"]);
            return;
        }
        
        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            http_response_code(400);
            echo json_encode(["message" => "Valor deve ser um número positivo"]);
            return;
        }
        
        try {
            $transaction = new Transaction($this->db);
            $data['user_id'] = $auth_data->user_id;
            
            $transaction_id = $transaction->create($data);
            
            http_response_code(201);
            echo json_encode([
                "message" => "Transação criada com sucesso",
                "transaction_id" => $transaction_id
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function updateTransaction($id) {
        $auth_data = $this->auth->authenticate();
        $data = json_decode(file_get_contents("php://input"), true);
        
        try {
            $transaction = new Transaction($this->db);
            
            // Verificar se a transação pertence ao usuário
            $existing = $transaction->findById($id);
            if (!$existing || $existing['user_id'] != $auth_data->user_id) {
                http_response_code(404);
                echo json_encode(["message" => "Transação não encontrada"]);
                return;
            }
            
            if ($transaction->update($id, $data)) {
                echo json_encode(["message" => "Transação atualizada com sucesso"]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Erro ao atualizar transação"]);
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function deleteTransaction($id) {
        $auth_data = $this->auth->authenticate();
        
        try {
            $transaction = new Transaction($this->db);
            
            // Verificar se a transação pertence ao usuário
            $existing = $transaction->findById($id);
            if (!$existing || $existing['user_id'] != $auth_data->user_id) {
                http_response_code(404);
                echo json_encode(["message" => "Transação não encontrada"]);
                return;
            }
            
            if ($transaction->delete($id)) {
                echo json_encode(["message" => "Transação deletada com sucesso"]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Erro ao deletar transação"]);
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function getMonthlyReport() {
        $auth_data = $this->auth->authenticate();
        
        $year = $_GET['year'] ?? date('Y');
        $month = $_GET['month'] ?? date('m');
        
        try {
            $transaction = new Transaction($this->db);
            $report = $transaction->getMonthlyReport($auth_data->user_id, $year, $month);
            
            echo json_encode($report);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
}
?>