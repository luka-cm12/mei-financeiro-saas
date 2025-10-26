<?php
/**
 * Controlador de Backup e Exportação
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class BackupController {
    private $db;
    private $auth;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auth = new AuthMiddleware();
    }
    
    public function exportData() {
        $auth_data = $this->auth->authenticate();
        $format = $_GET['format'] ?? 'json';
        $data_type = $_GET['data_type'] ?? 'all';
        $start_date = $_GET['start_date'] ?? null;
        $end_date = $_GET['end_date'] ?? null;
        
        try {
            $export_data = [];
            
            // Dados do usuário
            if ($data_type === 'all' || $data_type === 'user') {
                $export_data['user'] = $this->getUserData($auth_data->user_id);
            }
            
            // Transações
            if ($data_type === 'all' || $data_type === 'transactions') {
                $export_data['transactions'] = $this->getTransactionsData($auth_data->user_id, $start_date, $end_date);
            }
            
            // Categorias
            if ($data_type === 'all' || $data_type === 'categories') {
                $export_data['categories'] = $this->getCategoriesData($auth_data->user_id);
            }
            
            // Metas financeiras
            if ($data_type === 'all' || $data_type === 'goals') {
                $export_data['financial_goals'] = $this->getGoalsData($auth_data->user_id);
            }
            
            // Adicionar metadados
            $export_data['metadata'] = [
                'export_date' => date('Y-m-d H:i:s'),
                'user_id' => $auth_data->user_id,
                'format' => $format,
                'data_type' => $data_type,
                'period' => [
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ]
            ];
            
            // Formatar e retornar dados
            switch ($format) {
                case 'csv':
                    $this->exportToCSV($export_data, $data_type);
                    break;
                case 'pdf':
                    $this->exportToPDF($export_data, $data_type);
                    break;
                case 'excel':
                    $this->exportToExcel($export_data, $data_type);
                    break;
                default:
                    $this->exportToJSON($export_data);
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro na exportação: " . $e->getMessage()]);
        }
    }
    
    public function createBackup() {
        $auth_data = $this->auth->authenticate();
        
        try {
            // Criar backup completo dos dados do usuário
            $backup_data = [
                'user' => $this->getUserData($auth_data->user_id),
                'transactions' => $this->getTransactionsData($auth_data->user_id),
                'categories' => $this->getCategoriesData($auth_data->user_id),
                'financial_goals' => $this->getGoalsData($auth_data->user_id),
                'backup_info' => [
                    'created_at' => date('Y-m-d H:i:s'),
                    'version' => '1.0',
                    'user_id' => $auth_data->user_id
                ]
            ];
            
            // Salvar backup no banco
            $query = "INSERT INTO backups (user_id, backup_data, created_at) VALUES (:user_id, :backup_data, NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':user_id', $auth_data->user_id);
            $stmt->bindValue(':backup_data', json_encode($backup_data));
            $stmt->execute();
            
            $backup_id = $this->db->lastInsertId();
            
            echo json_encode([
                "backup_id" => $backup_id,
                "message" => "Backup criado com sucesso",
                "created_at" => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro ao criar backup: " . $e->getMessage()]);
        }
    }
    
    public function restoreBackup() {
        $auth_data = $this->auth->authenticate();
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['backup_id'])) {
            http_response_code(400);
            echo json_encode(["message" => "ID do backup é obrigatório"]);
            return;
        }
        
        try {
            // Buscar backup
            $query = "SELECT backup_data FROM backups WHERE id = :backup_id AND user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':backup_id', $input['backup_id']);
            $stmt->bindValue(':user_id', $auth_data->user_id);
            $stmt->execute();
            
            $backup = $stmt->fetch();
            if (!$backup) {
                http_response_code(404);
                echo json_encode(["message" => "Backup não encontrado"]);
                return;
            }
            
            $backup_data = json_decode($backup['backup_data'], true);
            
            // Iniciar transação
            $this->db->beginTransaction();
            
            try {
                // Limpar dados atuais (opcional - pode ser configurável)
                if (isset($input['clear_existing']) && $input['clear_existing']) {
                    $this->clearUserData($auth_data->user_id);
                }
                
                // Restaurar categorias
                if (isset($backup_data['categories'])) {
                    $this->restoreCategories($auth_data->user_id, $backup_data['categories']);
                }
                
                // Restaurar transações
                if (isset($backup_data['transactions'])) {
                    $this->restoreTransactions($auth_data->user_id, $backup_data['transactions']);
                }
                
                // Restaurar metas
                if (isset($backup_data['financial_goals'])) {
                    $this->restoreGoals($auth_data->user_id, $backup_data['financial_goals']);
                }
                
                $this->db->commit();
                
                echo json_encode([
                    "message" => "Backup restaurado com sucesso",
                    "restored_at" => date('Y-m-d H:i:s')
                ]);
                
            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro na restauração: " . $e->getMessage()]);
        }
    }
    
    public function listBackups() {
        $auth_data = $this->auth->authenticate();
        
        try {
            $query = "SELECT id, created_at, 
                            JSON_EXTRACT(backup_data, '$.backup_info.version') as version
                      FROM backups 
                      WHERE user_id = :user_id 
                      ORDER BY created_at DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':user_id', $auth_data->user_id);
            $stmt->execute();
            
            $backups = $stmt->fetchAll();
            
            echo json_encode([
                "backups" => $backups,
                "count" => count($backups),
                "message" => "Lista de backups carregada"
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro ao listar backups: " . $e->getMessage()]);
        }
    }
    
    private function getUserData($user_id) {
        $query = "SELECT id, name, email, created_at FROM users WHERE id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    private function getTransactionsData($user_id, $start_date = null, $end_date = null) {
        $where_date = "";
        if ($start_date && $end_date) {
            $where_date = "AND transaction_date BETWEEN :start_date AND :end_date";
        }
        
        $query = "SELECT t.*, c.name as category_name 
                  FROM transactions t 
                  LEFT JOIN categories c ON t.category_id = c.id
                  WHERE t.user_id = :user_id $where_date
                  ORDER BY t.transaction_date DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':user_id', $user_id);
        
        if ($start_date && $end_date) {
            $stmt->bindValue(':start_date', $start_date);
            $stmt->bindValue(':end_date', $end_date);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    private function getCategoriesData($user_id) {
        $query = "SELECT * FROM categories WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    private function getGoalsData($user_id) {
        $query = "SELECT * FROM financial_goals WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    private function exportToJSON($data) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="mei_financeiro_export_' . date('Y-m-d') . '.json"');
        echo json_encode($data, JSON_PRETTY_PRINT);
    }
    
    private function exportToCSV($data, $data_type) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="mei_financeiro_' . $data_type . '_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        if ($data_type === 'transactions' && isset($data['transactions'])) {
            // Cabeçalhos
            fputcsv($output, ['Data', 'Descrição', 'Tipo', 'Valor', 'Categoria']);
            
            // Dados
            foreach ($data['transactions'] as $transaction) {
                fputcsv($output, [
                    $transaction['transaction_date'],
                    $transaction['description'],
                    $transaction['type'],
                    $transaction['amount'],
                    $transaction['category_name'] ?? ''
                ]);
            }
        }
        
        fclose($output);
    }
    
    private function exportToPDF($data, $data_type) {
        // Implementação básica - em produção usar uma biblioteca como TCPDF ou FPDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="mei_financeiro_' . $data_type . '_' . date('Y-m-d') . '.pdf"');
        
        echo "PDF export não implementado ainda. Use JSON ou CSV.";
    }
    
    private function exportToExcel($data, $data_type) {
        // Implementação básica - em produção usar PHPSpreadsheet
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="mei_financeiro_' . $data_type . '_' . date('Y-m-d') . '.xls"');
        
        echo "Excel export não implementado ainda. Use JSON ou CSV.";
    }
    
    private function clearUserData($user_id) {
        $queries = [
            "DELETE FROM transactions WHERE user_id = :user_id",
            "DELETE FROM financial_goals WHERE user_id = :user_id",
            "DELETE FROM categories WHERE user_id = :user_id"
        ];
        
        foreach ($queries as $query) {
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':user_id', $user_id);
            $stmt->execute();
        }
    }
    
    private function restoreCategories($user_id, $categories) {
        foreach ($categories as $category) {
            $query = "INSERT INTO categories (user_id, name, type, color, icon) 
                     VALUES (:user_id, :name, :type, :color, :icon)";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':user_id', $user_id);
            $stmt->bindValue(':name', $category['name']);
            $stmt->bindValue(':type', $category['type']);
            $stmt->bindValue(':color', $category['color']);
            $stmt->bindValue(':icon', $category['icon']);
            $stmt->execute();
        }
    }
    
    private function restoreTransactions($user_id, $transactions) {
        foreach ($transactions as $transaction) {
            $query = "INSERT INTO transactions (user_id, description, amount, type, transaction_date, category_id) 
                     VALUES (:user_id, :description, :amount, :type, :transaction_date, :category_id)";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':user_id', $user_id);
            $stmt->bindValue(':description', $transaction['description']);
            $stmt->bindValue(':amount', $transaction['amount']);
            $stmt->bindValue(':type', $transaction['type']);
            $stmt->bindValue(':transaction_date', $transaction['transaction_date']);
            $stmt->bindValue(':category_id', $transaction['category_id']);
            $stmt->execute();
        }
    }
    
    private function restoreGoals($user_id, $goals) {
        foreach ($goals as $goal) {
            $query = "INSERT INTO financial_goals (user_id, name, description, target_amount, current_amount, target_date, status) 
                     VALUES (:user_id, :name, :description, :target_amount, :current_amount, :target_date, :status)";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':user_id', $user_id);
            $stmt->bindValue(':name', $goal['name']);
            $stmt->bindValue(':description', $goal['description']);
            $stmt->bindValue(':target_amount', $goal['target_amount']);
            $stmt->bindValue(':current_amount', $goal['current_amount']);
            $stmt->bindValue(':target_date', $goal['target_date']);
            $stmt->bindValue(':status', $goal['status']);
            $stmt->execute();
        }
    }
}
?>