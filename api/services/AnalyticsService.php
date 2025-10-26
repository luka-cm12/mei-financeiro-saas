<?php

require_once __DIR__ . '/../config/Database.php';

class AnalyticsService {
    private $db;
    private $conn;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }

    /**
     * Obter métricas gerais de assinatura
     */
    public function getSubscriptionMetrics($period = '30_days') {
        $dateFilter = $this->getDateFilter($period);

        // Usuários totais
        $totalUsersQuery = "SELECT COUNT(*) as total FROM users WHERE created_at >= ?";
        $stmt = $this->conn->prepare($totalUsersQuery);
        $stmt->execute([$dateFilter['start']]);
        $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Assinaturas ativas
        $activeSubsQuery = "SELECT COUNT(*) as total FROM user_subscriptions WHERE status = 'active'";
        $stmt = $this->conn->prepare($activeSubsQuery);
        $stmt->execute();
        $activeSubscriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Trials ativos
        $activeTrialsQuery = "SELECT COUNT(*) as total FROM user_subscriptions WHERE status = 'trial'";
        $stmt = $this->conn->prepare($activeTrialsQuery);
        $stmt->execute();
        $activeTrials = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Receita total
        $revenueQuery = "SELECT 
                          SUM(sp.price) as total_revenue,
                          COUNT(*) as paying_users
                         FROM user_subscriptions us
                         INNER JOIN subscription_plans sp ON us.plan_id = sp.id
                         WHERE us.status = 'active' AND us.created_at >= ?";
        $stmt = $this->conn->prepare($revenueQuery);
        $stmt->execute([$dateFilter['start']]);
        $revenueData = $stmt->fetch(PDO::FETCH_ASSOC);

        // MRR (Monthly Recurring Revenue)
        $mrrQuery = "SELECT 
                       SUM(CASE 
                         WHEN sp.billing_period = 'monthly' THEN sp.price
                         WHEN sp.billing_period = 'yearly' THEN sp.price / 12
                         ELSE sp.price
                       END) as mrr
                     FROM user_subscriptions us
                     INNER JOIN subscription_plans sp ON us.plan_id = sp.id
                     WHERE us.status = 'active'";
        $stmt = $this->conn->prepare($mrrQuery);
        $stmt->execute();
        $mrr = $stmt->fetch(PDO::FETCH_ASSOC)['mrr'] ?? 0;

        // Taxa de conversão de trial para pago
        $conversionQuery = "SELECT 
                             COUNT(CASE WHEN us1.status = 'active' THEN 1 END) as converted,
                             COUNT(*) as total_trials
                           FROM user_subscriptions us1
                           WHERE EXISTS (
                             SELECT 1 FROM user_subscriptions us2 
                             WHERE us2.user_id = us1.user_id AND us2.status = 'trial'
                           ) AND us1.created_at >= ?";
        $stmt = $this->conn->prepare($conversionQuery);
        $stmt->execute([$dateFilter['start']]);
        $conversionData = $stmt->fetch(PDO::FETCH_ASSOC);
        $conversionRate = $conversionData['total_trials'] > 0 
            ? ($conversionData['converted'] / $conversionData['total_trials']) * 100 
            : 0;

        return [
            'total_users' => (int) $totalUsers,
            'active_subscriptions' => (int) $activeSubscriptions,
            'active_trials' => (int) $activeTrials,
            'total_revenue' => (float) ($revenueData['total_revenue'] ?? 0),
            'paying_users' => (int) ($revenueData['paying_users'] ?? 0),
            'mrr' => (float) $mrr,
            'conversion_rate' => (float) round($conversionRate, 2),
            'period' => $period
        ];
    }

    /**
     * Obter dados de churn (cancelamentos)
     */
    public function getChurnMetrics($period = '30_days') {
        $dateFilter = $this->getDateFilter($period);

        // Cancelamentos no período
        $churnQuery = "SELECT COUNT(*) as churned_users
                       FROM user_subscriptions 
                       WHERE status = 'cancelled' 
                         AND updated_at >= ? AND updated_at <= ?";
        $stmt = $this->conn->prepare($churnQuery);
        $stmt->execute([$dateFilter['start'], $dateFilter['end']]);
        $churnedUsers = $stmt->fetch(PDO::FETCH_ASSOC)['churned_users'];

        // Total de usuários ativos no início do período
        $activeStartQuery = "SELECT COUNT(*) as active_start
                            FROM user_subscriptions 
                            WHERE status = 'active' 
                              AND created_at < ?";
        $stmt = $this->conn->prepare($activeStartQuery);
        $stmt->execute([$dateFilter['start']]);
        $activeAtStart = $stmt->fetch(PDO::FETCH_ASSOC)['active_start'];

        // Taxa de churn
        $churnRate = $activeAtStart > 0 ? ($churnedUsers / $activeAtStart) * 100 : 0;

        // Principais razões de cancelamento (se houver campo)
        $reasonsQuery = "SELECT 
                          cancellation_reason,
                          COUNT(*) as count
                        FROM user_subscriptions 
                        WHERE status = 'cancelled' 
                          AND updated_at >= ? AND updated_at <= ?
                          AND cancellation_reason IS NOT NULL
                        GROUP BY cancellation_reason
                        ORDER BY count DESC
                        LIMIT 5";
        $stmt = $this->conn->prepare($reasonsQuery);
        $stmt->execute([$dateFilter['start'], $dateFilter['end']]);
        $churnReasons = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'churned_users' => (int) $churnedUsers,
            'active_at_start' => (int) $activeAtStart,
            'churn_rate' => (float) round($churnRate, 2),
            'churn_reasons' => $churnReasons,
            'period' => $period
        ];
    }

    /**
     * Análise de planos mais populares
     */
    public function getPlanAnalytics() {
        $query = "SELECT 
                    sp.id,
                    sp.name,
                    sp.slug,
                    sp.price,
                    sp.billing_period,
                    COUNT(us.id) as subscribers,
                    SUM(CASE WHEN us.status = 'active' THEN sp.price ELSE 0 END) as revenue,
                    COUNT(CASE WHEN us.status = 'active' THEN 1 END) as active_subscribers,
                    COUNT(CASE WHEN us.status = 'trial' THEN 1 END) as trial_users
                  FROM subscription_plans sp
                  LEFT JOIN user_subscriptions us ON sp.id = us.plan_id
                  GROUP BY sp.id, sp.name, sp.slug, sp.price, sp.billing_period
                  ORDER BY subscribers DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crescimento ao longo do tempo
     */
    public function getGrowthData($months = 12) {
        $growthData = [];
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = date('Y-m-01', strtotime("-$i months"));
            $nextMonth = date('Y-m-01', strtotime("-" . ($i - 1) . " months"));
            
            // Novos usuários no mês
            $newUsersQuery = "SELECT COUNT(*) as new_users
                             FROM users 
                             WHERE DATE(created_at) >= ? AND DATE(created_at) < ?";
            $stmt = $this->conn->prepare($newUsersQuery);
            $stmt->execute([$date, $nextMonth]);
            $newUsers = $stmt->fetch(PDO::FETCH_ASSOC)['new_users'];

            // Novas assinaturas no mês
            $newSubsQuery = "SELECT COUNT(*) as new_subscriptions
                            FROM user_subscriptions 
                            WHERE status = 'active' 
                              AND DATE(created_at) >= ? AND DATE(created_at) < ?";
            $stmt = $this->conn->prepare($newSubsQuery);
            $stmt->execute([$date, $nextMonth]);
            $newSubs = $stmt->fetch(PDO::FETCH_ASSOC)['new_subscriptions'];

            // Receita no mês
            $monthRevenueQuery = "SELECT SUM(sp.price) as revenue
                                 FROM user_subscriptions us
                                 INNER JOIN subscription_plans sp ON us.plan_id = sp.id
                                 WHERE us.status = 'active' 
                                   AND DATE(us.created_at) >= ? AND DATE(us.created_at) < ?";
            $stmt = $this->conn->prepare($monthRevenueQuery);
            $stmt->execute([$date, $nextMonth]);
            $revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;

            $growthData[] = [
                'month' => date('Y-m', strtotime($date)),
                'month_name' => date('M/Y', strtotime($date)),
                'new_users' => (int) $newUsers,
                'new_subscriptions' => (int) $newSubs,
                'revenue' => (float) $revenue
            ];
        }

        return $growthData;
    }

    /**
     * Métricas de retenção
     */
    public function getRetentionMetrics() {
        // Usuários que continuaram após trial
        $trialRetentionQuery = "SELECT 
                                 COUNT(CASE WHEN active_sub.id IS NOT NULL THEN 1 END) as retained,
                                 COUNT(*) as total_trials
                               FROM user_subscriptions trial_sub
                               LEFT JOIN user_subscriptions active_sub 
                                 ON trial_sub.user_id = active_sub.user_id 
                                 AND active_sub.status = 'active'
                                 AND active_sub.created_at > trial_sub.trial_ends_at
                               WHERE trial_sub.status = 'expired' 
                                 AND trial_sub.trial_ends_at IS NOT NULL";
        
        $stmt = $this->conn->prepare($trialRetentionQuery);
        $stmt->execute();
        $trialRetention = $stmt->fetch(PDO::FETCH_ASSOC);

        $trialRetentionRate = $trialRetention['total_trials'] > 0 
            ? ($trialRetention['retained'] / $trialRetention['total_trials']) * 100 
            : 0;

        // Tempo médio de vida do cliente (em dias)
        $lifetimeQuery = "SELECT AVG(DATEDIFF(
                            COALESCE(
                              CASE WHEN status = 'cancelled' THEN updated_at END,
                              ends_at,
                              NOW()
                            ), 
                            starts_at
                          )) as avg_lifetime_days
                          FROM user_subscriptions 
                          WHERE status IN ('active', 'cancelled', 'expired')
                            AND starts_at IS NOT NULL";
        
        $stmt = $this->conn->prepare($lifetimeQuery);
        $stmt->execute();
        $avgLifetime = $stmt->fetch(PDO::FETCH_ASSOC)['avg_lifetime_days'] ?? 0;

        return [
            'trial_retention_rate' => (float) round($trialRetentionRate, 2),
            'avg_customer_lifetime_days' => (int) round($avgLifetime),
            'trial_conversions' => (int) $trialRetention['retained'],
            'total_trials_ended' => (int) $trialRetention['total_trials']
        ];
    }

    /**
     * Dashboard completo
     */
    public function getDashboardData($period = '30_days') {
        return [
            'overview' => $this->getSubscriptionMetrics($period),
            'churn' => $this->getChurnMetrics($period),
            'plans' => $this->getPlanAnalytics(),
            'growth' => $this->getGrowthData(12),
            'retention' => $this->getRetentionMetrics(),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Helper para filtros de data
     */
    private function getDateFilter($period) {
        switch ($period) {
            case '7_days':
                return [
                    'start' => date('Y-m-d', strtotime('-7 days')),
                    'end' => date('Y-m-d')
                ];
            case '30_days':
                return [
                    'start' => date('Y-m-d', strtotime('-30 days')),
                    'end' => date('Y-m-d')
                ];
            case '90_days':
                return [
                    'start' => date('Y-m-d', strtotime('-90 days')),
                    'end' => date('Y-m-d')
                ];
            case '1_year':
                return [
                    'start' => date('Y-m-d', strtotime('-1 year')),
                    'end' => date('Y-m-d')
                ];
            default:
                return [
                    'start' => date('Y-m-d', strtotime('-30 days')),
                    'end' => date('Y-m-d')
                ];
        }
    }
}
?>