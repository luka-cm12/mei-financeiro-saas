<?php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../services/AnalyticsService.php';

class AnalyticsController {
    private $authMiddleware;
    private $analyticsService;

    public function __construct() {
        $this->authMiddleware = new AuthMiddleware();
        $this->analyticsService = new AnalyticsService();
    }

    /**
     * Verificar se o usuário é admin
     */
    private function requireAdmin() {
        $authResult = $this->authMiddleware->authenticate();
        
        // Verificar se é admin (você pode implementar um campo 'role' na tabela users)
        if (!isset($authResult->is_admin) || !$authResult->is_admin) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Acesso negado: apenas administradores'
            ]);
            exit();
        }
        
        return $authResult;
    }

    /**
     * Dashboard principal com todas as métricas
     */
    public function getDashboard() {
        try {
            $this->requireAdmin();
            
            $period = $_GET['period'] ?? '30_days';
            $allowedPeriods = ['7_days', '30_days', '90_days', '1_year'];
            
            if (!in_array($period, $allowedPeriods)) {
                $period = '30_days';
            }

            $dashboardData = $this->analyticsService->getDashboardData($period);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $dashboardData
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Métricas de assinatura
     */
    public function getSubscriptionMetrics() {
        try {
            $this->requireAdmin();
            
            $period = $_GET['period'] ?? '30_days';
            $metrics = $this->analyticsService->getSubscriptionMetrics($period);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $metrics
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Métricas de churn
     */
    public function getChurnMetrics() {
        try {
            $this->requireAdmin();
            
            $period = $_GET['period'] ?? '30_days';
            $churnData = $this->analyticsService->getChurnMetrics($period);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $churnData
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Análise de planos
     */
    public function getPlanAnalytics() {
        try {
            $this->requireAdmin();
            
            $planData = $this->analyticsService->getPlanAnalytics();

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $planData
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Dados de crescimento
     */
    public function getGrowthData() {
        try {
            $this->requireAdmin();
            
            $months = (int) ($_GET['months'] ?? 12);
            $months = min(max($months, 3), 24); // Limitar entre 3 e 24 meses
            
            $growthData = $this->analyticsService->getGrowthData($months);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $growthData
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Métricas de retenção
     */
    public function getRetentionMetrics() {
        try {
            $this->requireAdmin();
            
            $retentionData = $this->analyticsService->getRetentionMetrics();

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $retentionData
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Exportar dados para CSV
     */
    public function exportToCsv() {
        try {
            $this->requireAdmin();
            
            $type = $_GET['type'] ?? 'overview';
            $period = $_GET['period'] ?? '30_days';
            
            $data = [];
            $filename = "mei_financeiro_analytics_" . date('Y-m-d');
            
            switch ($type) {
                case 'overview':
                    $data = $this->analyticsService->getSubscriptionMetrics($period);
                    $filename .= "_overview.csv";
                    break;
                    
                case 'churn':
                    $data = $this->analyticsService->getChurnMetrics($period);
                    $filename .= "_churn.csv";
                    break;
                    
                case 'growth':
                    $data = $this->analyticsService->getGrowthData(12);
                    $filename .= "_growth.csv";
                    break;
                    
                case 'plans':
                    $data = $this->analyticsService->getPlanAnalytics();
                    $filename .= "_plans.csv";
                    break;
                    
                default:
                    $data = $this->analyticsService->getDashboardData($period);
                    $filename .= "_complete.csv";
                    break;
            }

            // Headers para download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache, must-revalidate');
            
            // Abrir output
            $output = fopen('php://output', 'w');
            
            // BOM para UTF-8 no Excel
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            if ($type === 'growth' && is_array($data)) {
                // Cabeçalhos para crescimento
                fputcsv($output, ['Mês', 'Novos Usuários', 'Novas Assinaturas', 'Receita'], ';');
                
                foreach ($data as $row) {
                    fputcsv($output, [
                        $row['month_name'],
                        $row['new_users'],
                        $row['new_subscriptions'],
                        'R$ ' . number_format($row['revenue'], 2, ',', '.')
                    ], ';');
                }
            } elseif ($type === 'plans' && is_array($data)) {
                // Cabeçalhos para planos
                fputcsv($output, ['Plano', 'Preço', 'Período', 'Assinantes', 'Ativos', 'Trials', 'Receita'], ';');
                
                foreach ($data as $row) {
                    fputcsv($output, [
                        $row['name'],
                        'R$ ' . number_format($row['price'], 2, ',', '.'),
                        $row['billing_period'],
                        $row['subscribers'],
                        $row['active_subscribers'],
                        $row['trial_users'],
                        'R$ ' . number_format($row['revenue'], 2, ',', '.')
                    ], ';');
                }
            } else {
                // Dados gerais (overview/churn)
                foreach ($data as $key => $value) {
                    if (!is_array($value)) {
                        fputcsv($output, [$key, $value], ';');
                    }
                }
            }
            
            fclose($output);
            exit();

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno: ' . $e->getMessage()
            ]);
        }
    }
}
?>