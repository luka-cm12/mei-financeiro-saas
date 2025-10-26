<?php
/**
 * Controlador de Notificações
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class NotificationController {
    private $db;
    private $auth;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auth = new AuthMiddleware();
    }
    
    public function getNotifications() {
        $auth_data = $this->auth->authenticate();
        $limit = $_GET['limit'] ?? 20;
        $offset = $_GET['offset'] ?? 0;
        $unread_only = $_GET['unread_only'] ?? false;
        
        try {
            $where_clause = $unread_only ? "AND is_read = 0" : "";
            
            $query = "SELECT * FROM notifications 
                      WHERE user_id = :user_id $where_clause
                      ORDER BY created_at DESC 
                      LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':user_id', $auth_data->user_id);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $notifications = $stmt->fetchAll();
            
            // Contar total de não lidas
            $count_query = "SELECT COUNT(*) as unread_count FROM notifications 
                           WHERE user_id = :user_id AND is_read = 0";
            $count_stmt = $this->db->prepare($count_query);
            $count_stmt->bindValue(':user_id', $auth_data->user_id);
            $count_stmt->execute();
            $unread_count = $count_stmt->fetch()['unread_count'];
            
            echo json_encode([
                "notifications" => $notifications,
                "unread_count" => $unread_count,
                "message" => "Notificações carregadas"
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function markAsRead() {
        $auth_data = $this->auth->authenticate();
        $input = json_decode(file_get_contents('php://input'), true);
        
        try {
            if (isset($input['notification_id'])) {
                // Marcar uma notificação específica
                $query = "UPDATE notifications 
                         SET is_read = 1, read_at = NOW() 
                         WHERE id = :notification_id AND user_id = :user_id";
                
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':notification_id', $input['notification_id']);
                $stmt->bindValue(':user_id', $auth_data->user_id);
                $stmt->execute();
                
            } else {
                // Marcar todas como lidas
                $query = "UPDATE notifications 
                         SET is_read = 1, read_at = NOW() 
                         WHERE user_id = :user_id AND is_read = 0";
                
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':user_id', $auth_data->user_id);
                $stmt->execute();
            }
            
            echo json_encode(["message" => "Notificação(ões) marcada(s) como lida(s)"]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function createNotification() {
        $auth_data = $this->auth->authenticate();
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['title']) || !isset($input['message'])) {
            http_response_code(400);
            echo json_encode(["message" => "Título e mensagem são obrigatórios"]);
            return;
        }
        
        try {
            $query = "INSERT INTO notifications 
                     (user_id, title, message, type, priority, data, created_at) 
                     VALUES (:user_id, :title, :message, :type, :priority, :data, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':user_id', $auth_data->user_id);
            $stmt->bindValue(':title', $input['title']);
            $stmt->bindValue(':message', $input['message']);
            $stmt->bindValue(':type', $input['type'] ?? 'info');
            $stmt->bindValue(':priority', $input['priority'] ?? 'medium');
            $stmt->bindValue(':data', isset($input['data']) ? json_encode($input['data']) : null);
            $stmt->execute();
            
            echo json_encode([
                "id" => $this->db->lastInsertId(),
                "message" => "Notificação criada com sucesso"
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function checkAlerts() {
        $auth_data = $this->auth->authenticate();
        
        try {
            $alerts = [];
            
            // Verificar metas próximas do vencimento
            $goals_alerts = $this->checkGoalsAlerts($auth_data->user_id);
            $alerts = array_merge($alerts, $goals_alerts);
            
            // Verificar gastos elevados
            $spending_alerts = $this->checkSpendingAlerts($auth_data->user_id);
            $alerts = array_merge($alerts, $spending_alerts);
            
            // Verificar receitas baixas
            $income_alerts = $this->checkIncomeAlerts($auth_data->user_id);
            $alerts = array_merge($alerts, $income_alerts);
            
            // Verificar datas de vencimento
            $due_alerts = $this->checkDueDates($auth_data->user_id);
            $alerts = array_merge($alerts, $due_alerts);
            
            // Criar notificações para novos alertas
            foreach ($alerts as $alert) {
                $this->createAlertNotification($auth_data->user_id, $alert);
            }
            
            echo json_encode([
                "alerts" => $alerts,
                "count" => count($alerts),
                "message" => "Verificação de alertas concluída"
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    private function checkGoalsAlerts($user_id) {
        $alerts = [];
        
        // Metas próximas do vencimento (próximos 7 dias)
        $query = "SELECT * FROM financial_goals 
                  WHERE user_id = :user_id 
                  AND status = 'ativa'
                  AND target_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
                  AND target_date >= NOW()";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':user_id', $user_id);
        $stmt->execute();
        
        while ($goal = $stmt->fetch()) {
            $progress = ($goal['current_amount'] / $goal['target_amount']) * 100;
            
            if ($progress < 80) {
                $alerts[] = [
                    "type" => "goal_due",
                    "priority" => "high",
                    "title" => "Meta próxima do vencimento",
                    "message" => "A meta '{$goal['name']}' vence em breve e está apenas {$progress}% concluída",
                    "data" => $goal
                ];
            }
        }
        
        return $alerts;
    }
    
    private function checkSpendingAlerts($user_id) {
        $alerts = [];
        $current_month = date('Y-m');
        
        // Verificar se gastos deste mês estão 20% acima da média dos últimos 3 meses
        $query = "SELECT 
                    AVG(monthly_expenses) as avg_expenses,
                    MAX(CASE WHEN month = :current_month THEN monthly_expenses END) as current_expenses
                  FROM (
                    SELECT 
                      DATE_FORMAT(transaction_date, '%Y-%m') as month,
                      SUM(amount) as monthly_expenses
                    FROM transactions 
                    WHERE user_id = :user_id 
                    AND type = 'despesa'
                    AND transaction_date >= DATE_SUB(NOW(), INTERVAL 4 MONTH)
                    GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
                  ) t";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':user_id', $user_id);
        $stmt->bindValue(':current_month', $current_month);
        $stmt->execute();
        
        $result = $stmt->fetch();
        
        if ($result['current_expenses'] && $result['avg_expenses']) {
            $increase = (($result['current_expenses'] - $result['avg_expenses']) / $result['avg_expenses']) * 100;
            
            if ($increase > 20) {
                $alerts[] = [
                    "type" => "high_spending",
                    "priority" => "medium",
                    "title" => "Gastos elevados detectados",
                    "message" => "Seus gastos deste mês estão " . round($increase, 1) . "% acima da média",
                    "data" => $result
                ];
            }
        }
        
        return $alerts;
    }
    
    private function checkIncomeAlerts($user_id) {
        $alerts = [];
        $current_month = date('Y-m');
        
        // Verificar se receita deste mês está 15% abaixo da média
        $query = "SELECT 
                    AVG(monthly_income) as avg_income,
                    MAX(CASE WHEN month = :current_month THEN monthly_income END) as current_income
                  FROM (
                    SELECT 
                      DATE_FORMAT(transaction_date, '%Y-%m') as month,
                      SUM(amount) as monthly_income
                    FROM transactions 
                    WHERE user_id = :user_id 
                    AND type = 'receita'
                    AND transaction_date >= DATE_SUB(NOW(), INTERVAL 4 MONTH)
                    GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
                  ) t";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':user_id', $user_id);
        $stmt->bindValue(':current_month', $current_month);
        $stmt->execute();
        
        $result = $stmt->fetch();
        
        if ($result['current_income'] && $result['avg_income']) {
            $decrease = (($result['avg_income'] - $result['current_income']) / $result['avg_income']) * 100;
            
            if ($decrease > 15) {
                $alerts[] = [
                    "type" => "low_income",
                    "priority" => "medium",
                    "title" => "Receita abaixo do esperado",
                    "message" => "Sua receita deste mês está " . round($decrease, 1) . "% abaixo da média",
                    "data" => $result
                ];
            }
        }
        
        return $alerts;
    }
    
    private function checkDueDates($user_id) {
        $alerts = [];
        
        // Verificar se há transações agendadas (se implementado no futuro)
        // Por enquanto, apenas um placeholder
        
        return $alerts;
    }
    
    private function createAlertNotification($user_id, $alert) {
        // Verificar se já existe uma notificação similar recente (últimas 24h)
        $check_query = "SELECT id FROM notifications 
                       WHERE user_id = :user_id 
                       AND type = :type 
                       AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                       LIMIT 1";
        
        $check_stmt = $this->db->prepare($check_query);
        $check_stmt->bindValue(':user_id', $user_id);
        $check_stmt->bindValue(':type', $alert['type']);
        $check_stmt->execute();
        
        if ($check_stmt->fetch()) {
            return; // Já existe notificação recente similar
        }
        
        // Criar nova notificação
        $query = "INSERT INTO notifications 
                 (user_id, title, message, type, priority, data, created_at) 
                 VALUES (:user_id, :title, :message, :type, :priority, :data, NOW())";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':user_id', $user_id);
        $stmt->bindValue(':title', $alert['title']);
        $stmt->bindValue(':message', $alert['message']);
        $stmt->bindValue(':type', $alert['type']);
        $stmt->bindValue(':priority', $alert['priority']);
        $stmt->bindValue(':data', json_encode($alert['data']));
        $stmt->execute();
    }
}
?>