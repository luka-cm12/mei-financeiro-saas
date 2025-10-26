<?php
/**
 * Controlador de Relatórios Avançados
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class ReportController {
    private $db;
    private $auth;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auth = new AuthMiddleware();
    }
    
    public function getDashboard() {
        $auth_data = $this->auth->authenticate();
        
        try {
            $current_month = date('Y-m');
            $previous_month = date('Y-m', strtotime('-1 month'));
            
            // Resumo do mês atual
            $current_summary = $this->getMonthSummary($auth_data->user_id, $current_month);
            
            // Resumo do mês anterior
            $previous_summary = $this->getMonthSummary($auth_data->user_id, $previous_month);
            
            // Crescimento percentual
            $growth = $this->calculateGrowth($current_summary, $previous_summary);
            
            // Top categorias
            $top_categories = $this->getTopCategories($auth_data->user_id, $current_month);
            
            // Transações recentes
            $recent_transactions = $this->getRecentTransactions($auth_data->user_id, 5);
            
            // Análise de tendências (últimos 6 meses)
            $trends = $this->getTrends($auth_data->user_id, 6);
            
            // Metas próximas do vencimento
            $upcoming_goals = $this->getUpcomingGoals($auth_data->user_id);
            
            // Alertas e sugestões
            $insights = $this->generateInsights($auth_data->user_id, $current_summary, $trends);
            
            echo json_encode([
                "current_month" => $current_summary,
                "previous_month" => $previous_summary,
                "growth" => $growth,
                "top_categories" => $top_categories,
                "recent_transactions" => $recent_transactions,
                "trends" => $trends,
                "upcoming_goals" => $upcoming_goals,
                "insights" => $insights,
                "message" => "Dashboard carregado com sucesso"
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function getCashFlow() {
        $auth_data = $this->auth->authenticate();
        $period = $_GET['period'] ?? '12'; // meses
        
        try {
            $query = "SELECT 
                        DATE_FORMAT(transaction_date, '%Y-%m') as month,
                        SUM(CASE WHEN type = 'receita' THEN amount ELSE 0 END) as income,
                        SUM(CASE WHEN type = 'despesa' THEN amount ELSE 0 END) as expenses,
                        SUM(CASE WHEN type = 'receita' THEN amount ELSE -amount END) as net_flow
                      FROM transactions 
                      WHERE user_id = :user_id 
                      AND transaction_date >= DATE_SUB(NOW(), INTERVAL :period MONTH)
                      GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
                      ORDER BY month DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':user_id', $auth_data->user_id);
            $stmt->bindValue(':period', $period);
            $stmt->execute();
            
            $cash_flow = $stmt->fetchAll();
            
            echo json_encode([
                "cash_flow" => $cash_flow,
                "period_months" => $period,
                "message" => "Fluxo de caixa carregado"
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function getProfitability() {
        $auth_data = $this->auth->authenticate();
        $start_date = $_GET['start_date'] ?? date('Y-m-01', strtotime('-12 months'));
        $end_date = $_GET['end_date'] ?? date('Y-m-d');
        
        try {
            // Análise de lucratividade por categoria
            $query = "SELECT 
                        c.name as category_name,
                        c.type,
                        SUM(t.amount) as total_amount,
                        COUNT(t.id) as transaction_count,
                        AVG(t.amount) as avg_amount
                      FROM transactions t
                      LEFT JOIN categories c ON t.category_id = c.id
                      WHERE t.user_id = :user_id 
                      AND t.transaction_date BETWEEN :start_date AND :end_date
                      GROUP BY c.id, c.name, c.type
                      ORDER BY total_amount DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':user_id', $auth_data->user_id);
            $stmt->bindValue(':start_date', $start_date);
            $stmt->bindValue(':end_date', $end_date);
            $stmt->execute();
            
            $profitability = $stmt->fetchAll();
            
            // Calcular margens
            $total_income = 0;
            $total_expenses = 0;
            
            foreach ($profitability as $item) {
                if ($item['type'] === 'receita') {
                    $total_income += $item['total_amount'];
                } else {
                    $total_expenses += $item['total_amount'];
                }
            }
            
            $profit_margin = $total_income > 0 ? (($total_income - $total_expenses) / $total_income) * 100 : 0;
            
            echo json_encode([
                "profitability_by_category" => $profitability,
                "summary" => [
                    "total_income" => $total_income,
                    "total_expenses" => $total_expenses,
                    "net_profit" => $total_income - $total_expenses,
                    "profit_margin" => round($profit_margin, 2)
                ],
                "period" => [
                    "start_date" => $start_date,
                    "end_date" => $end_date
                ],
                "message" => "Análise de lucratividade carregada"
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    private function getMonthSummary($user_id, $month) {
        $query = "SELECT 
                    SUM(CASE WHEN type = 'receita' THEN amount ELSE 0 END) as total_income,
                    SUM(CASE WHEN type = 'despesa' THEN amount ELSE 0 END) as total_expenses,
                    COUNT(CASE WHEN type = 'receita' THEN 1 END) as income_count,
                    COUNT(CASE WHEN type = 'despesa' THEN 1 END) as expense_count
                  FROM transactions 
                  WHERE user_id = :user_id 
                  AND DATE_FORMAT(transaction_date, '%Y-%m') = :month";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':user_id', $user_id);
        $stmt->bindValue(':month', $month);
        $stmt->execute();
        
        $result = $stmt->fetch();
        $result['net_profit'] = $result['total_income'] - $result['total_expenses'];
        $result['month'] = $month;
        
        return $result;
    }
    
    private function calculateGrowth($current, $previous) {
        $income_growth = $previous['total_income'] > 0 
            ? (($current['total_income'] - $previous['total_income']) / $previous['total_income']) * 100 
            : 0;
            
        $expense_growth = $previous['total_expenses'] > 0 
            ? (($current['total_expenses'] - $previous['total_expenses']) / $previous['total_expenses']) * 100 
            : 0;
            
        $profit_growth = $previous['net_profit'] != 0 
            ? (($current['net_profit'] - $previous['net_profit']) / abs($previous['net_profit'])) * 100 
            : 0;
        
        return [
            "income_growth" => round($income_growth, 2),
            "expense_growth" => round($expense_growth, 2),
            "profit_growth" => round($profit_growth, 2)
        ];
    }
    
    private function getTopCategories($user_id, $month) {
        $query = "SELECT 
                    c.name,
                    c.type,
                    c.color,
                    SUM(t.amount) as total,
                    COUNT(t.id) as count
                  FROM transactions t
                  LEFT JOIN categories c ON t.category_id = c.id
                  WHERE t.user_id = :user_id 
                  AND DATE_FORMAT(t.transaction_date, '%Y-%m') = :month
                  GROUP BY c.id
                  ORDER BY total DESC
                  LIMIT 5";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':user_id', $user_id);
        $stmt->bindValue(':month', $month);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    private function getRecentTransactions($user_id, $limit) {
        $query = "SELECT 
                    t.*,
                    c.name as category_name,
                    c.color as category_color
                  FROM transactions t
                  LEFT JOIN categories c ON t.category_id = c.id
                  WHERE t.user_id = :user_id
                  ORDER BY t.created_at DESC
                  LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':user_id', $user_id);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    private function getTrends($user_id, $months) {
        $query = "SELECT 
                    DATE_FORMAT(transaction_date, '%Y-%m') as month,
                    SUM(CASE WHEN type = 'receita' THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN type = 'despesa' THEN amount ELSE 0 END) as expenses
                  FROM transactions 
                  WHERE user_id = :user_id 
                  AND transaction_date >= DATE_SUB(NOW(), INTERVAL :months MONTH)
                  GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
                  ORDER BY month ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':user_id', $user_id);
        $stmt->bindValue(':months', $months);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    private function getUpcomingGoals($user_id) {
        $query = "SELECT * FROM financial_goals 
                  WHERE user_id = :user_id 
                  AND status = 'ativa'
                  AND target_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)
                  ORDER BY target_date ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    private function generateInsights($user_id, $current_summary, $trends) {
        $insights = [];
        
        // Análise de gastos
        if ($current_summary['total_expenses'] > $current_summary['total_income']) {
            $insights[] = [
                "type" => "warning",
                "title" => "Gastos acima da receita",
                "message" => "Suas despesas estão maiores que suas receitas este mês. Considere revisar seus gastos.",
                "priority" => "high"
            ];
        }
        
        // Análise de crescimento
        if (count($trends) >= 2) {
            $last_month = end($trends);
            $second_last = prev($trends);
            
            if ($last_month['income'] > $second_last['income'] * 1.1) {
                $insights[] = [
                    "type" => "success",
                    "title" => "Crescimento na receita",
                    "message" => "Suas receitas cresceram mais de 10% em relação ao mês anterior. Parabéns!",
                    "priority" => "medium"
                ];
            }
        }
        
        // Sugestão de meta
        if ($current_summary['net_profit'] > 0) {
            $insights[] = [
                "type" => "info",
                "title" => "Oportunidade de poupança",
                "message" => "Que tal definir uma meta de poupança com parte do seu lucro atual?",
                "priority" => "low"
            ];
        }
        
        return $insights;
    }
}
?>