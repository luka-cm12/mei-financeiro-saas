<?php
/**
 * Controlador de Metas Financeiras
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/FinancialGoal.php';

class FinancialGoalController {
    private $db;
    private $auth;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auth = new AuthMiddleware();
    }
    
    public function getGoals() {
        $auth_data = $this->auth->authenticate();
        
        try {
            $goal = new FinancialGoal($this->db);
            $goals = $goal->getByUser($auth_data->user_id);
            
            echo json_encode([
                "goals" => $goals,
                "message" => "Metas carregadas com sucesso"
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function createGoal() {
        $auth_data = $this->auth->authenticate();
        $input = file_get_contents("php://input");
        $data = json_decode($input, true);
        
        // Debug
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(["message" => "JSON inválido: " . json_last_error_msg()]);
            return;
        }
        
        // Validações
        if (!isset($data['name']) || !isset($data['target_amount']) || !isset($data['target_date'])) {
            http_response_code(400);
            echo json_encode([
                "message" => "Nome, valor alvo e data são obrigatórios",
                "received_data" => $data,
                "input" => $input
            ]);
            return;
        }
        
        if (!is_numeric($data['target_amount']) || $data['target_amount'] <= 0) {
            http_response_code(400);
            echo json_encode(["message" => "Valor alvo deve ser um número positivo"]);
            return;
        }
        
        try {
            $goal = new FinancialGoal($this->db);
            $data['user_id'] = $auth_data->user_id;
            
            $goal_id = $goal->create($data);
            
            http_response_code(201);
            echo json_encode([
                "message" => "Meta criada com sucesso",
                "goal_id" => $goal_id
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function updateGoal($id) {
        $auth_data = $this->auth->authenticate();
        $data = json_decode(file_get_contents("php://input"), true);
        
        try {
            $goal = new FinancialGoal($this->db);
            
            // Verificar se a meta pertence ao usuário
            $existing_goal = $goal->getById($id);
            if (!$existing_goal || $existing_goal['user_id'] != $auth_data->user_id) {
                http_response_code(404);
                echo json_encode(["message" => "Meta não encontrada"]);
                return;
            }
            
            $goal->update($id, $data);
            
            echo json_encode(["message" => "Meta atualizada com sucesso"]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function deleteGoal($id) {
        $auth_data = $this->auth->authenticate();
        
        try {
            $goal = new FinancialGoal($this->db);
            
            // Verificar se a meta pertence ao usuário
            $existing_goal = $goal->getById($id);
            if (!$existing_goal || $existing_goal['user_id'] != $auth_data->user_id) {
                http_response_code(404);
                echo json_encode(["message" => "Meta não encontrada"]);
                return;
            }
            
            $goal->delete($id);
            
            echo json_encode(["message" => "Meta excluída com sucesso"]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function getProgress() {
        $auth_data = $this->auth->authenticate();
        
        try {
            $goal = new FinancialGoal($this->db);
            $progress = $goal->getProgressByUser($auth_data->user_id);
            
            echo json_encode([
                "progress" => $progress,
                "message" => "Progresso das metas carregado"
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
}
?>