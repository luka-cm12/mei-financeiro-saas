<?php

class FeatureUsage {
    private $conn;
    private $table_name = "feature_usage";

    public $id;
    public $user_id;
    public $feature_name;
    public $usage_count;
    public $period_start;
    public $period_end;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Incrementar uso de uma feature
     */
    public function incrementUsage($userId, $featureName, $amount = 1) {
        // Calcular período atual (mensal)
        $periodStart = date('Y-m-01');
        $periodEnd = date('Y-m-t');

        // Verificar se já existe registro para este período
        $query = "SELECT id, usage_count FROM " . $this->table_name . "
                  WHERE user_id = ? 
                  AND feature_name = ?
                  AND period_start = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $userId);
        $stmt->bindValue(2, $featureName);
        $stmt->bindValue(3, $periodStart);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Atualizar registro existente
            $updateQuery = "UPDATE " . $this->table_name . "
                           SET usage_count = usage_count + ?
                           WHERE id = ?";
            
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindValue(1, $amount);
            $updateStmt->bindValue(2, $row['id']);
            
            return $updateStmt->execute();
        } else {
            // Criar novo registro
            $insertQuery = "INSERT INTO " . $this->table_name . "
                           SET user_id = ?,
                               feature_name = ?,
                               usage_count = ?,
                               period_start = ?,
                               period_end = ?";
            
            $insertStmt = $this->conn->prepare($insertQuery);
            $insertStmt->bindValue(1, $userId);
            $insertStmt->bindValue(2, $featureName);
            $insertStmt->bindValue(3, $amount);
            $insertStmt->bindValue(4, $periodStart);
            $insertStmt->bindValue(5, $periodEnd);
            
            return $insertStmt->execute();
        }
    }

    /**
     * Obter uso atual de uma feature no período
     */
    public function getCurrentUsage($userId, $featureName) {
        $periodStart = date('Y-m-01');

        $query = "SELECT COALESCE(usage_count, 0) as usage_count
                  FROM " . $this->table_name . "
                  WHERE user_id = ? 
                  AND feature_name = ?
                  AND period_start = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $userId);
        $stmt->bindValue(2, $featureName);
        $stmt->bindValue(3, $periodStart);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? (int)$row['usage_count'] : 0;
    }

    /**
     * Verificar se usuário pode usar uma feature
     */
    public function canUseFeature($userId, $featureName, $amount = 1) {
        // Obter plano do usuário e seus limites
        $subscriptionPlan = new SubscriptionPlan($this->conn);
        
        if (!$subscriptionPlan->canUseFeature($userId, $featureName)) {
            return false;
        }

        $limit = $subscriptionPlan->getFeatureLimit($featureName);
        
        // -1 significa ilimitado
        if ($limit === -1) {
            return true;
        }

        $currentUsage = $this->getCurrentUsage($userId, $featureName);
        
        return ($currentUsage + $amount) <= $limit;
    }

    /**
     * Usar feature com verificação automática
     */
    public function useFeature($userId, $featureName, $amount = 1) {
        if (!$this->canUseFeature($userId, $featureName, $amount)) {
            return false;
        }

        return $this->incrementUsage($userId, $featureName, $amount);
    }

    /**
     * Obter estatísticas de uso do usuário
     */
    public function getUserUsageStats($userId) {
        $periodStart = date('Y-m-01');

        $query = "SELECT feature_name, usage_count
                  FROM " . $this->table_name . "
                  WHERE user_id = ?
                  AND period_start = ?
                  ORDER BY feature_name";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $userId);
        $stmt->bindValue(2, $periodStart);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obter histórico de uso de uma feature
     */
    public function getFeatureHistory($userId, $featureName, $months = 6) {
        $query = "SELECT DATE_FORMAT(period_start, '%Y-%m') as month,
                         usage_count
                  FROM " . $this->table_name . "
                  WHERE user_id = ?
                  AND feature_name = ?
                  AND period_start >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                  ORDER BY period_start DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $userId);
        $stmt->bindValue(2, $featureName);
        $stmt->bindValue(3, $months);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resetar uso de features (para novo período)
     */
    public function resetMonthlyUsage() {
        // Este método seria chamado por um cron job no início de cada mês
        // Por enquanto, não vamos implementar pois o sistema já funciona
        // criando novos registros a cada mês
        return true;
    }

    /**
     * Obter limite restante para uma feature
     */
    public function getRemainingLimit($userId, $featureName) {
        $subscriptionPlan = new SubscriptionPlan($this->conn);
        $limit = $subscriptionPlan->getFeatureLimit($featureName);
        
        if ($limit === -1) {
            return -1; // Ilimitado
        }

        $currentUsage = $this->getCurrentUsage($userId, $featureName);
        $remaining = $limit - $currentUsage;
        
        return max(0, $remaining);
    }

    /**
     * Verificar se feature está próxima do limite
     */
    public function isNearLimit($userId, $featureName, $threshold = 0.8) {
        $subscriptionPlan = new SubscriptionPlan($this->conn);
        $limit = $subscriptionPlan->getFeatureLimit($featureName);
        
        if ($limit === -1) {
            return false; // Ilimitado nunca está próximo do limite
        }

        $currentUsage = $this->getCurrentUsage($userId, $featureName);
        $usagePercentage = $currentUsage / $limit;
        
        return $usagePercentage >= $threshold;
    }
}