<?php

class UserSubscription {
    private $conn;
    private $table_name = "user_subscriptions";

    public $id;
    public $user_id;
    public $plan_id;
    public $status;
    public $starts_at;
    public $ends_at;
    public $trial_ends_at;
    public $cancelled_at;
    public $payment_method;
    public $payment_reference;
    public $auto_renew;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Criar nova assinatura
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET user_id = :user_id,
                      plan_id = :plan_id,
                      status = :status,
                      starts_at = :starts_at,
                      ends_at = :ends_at,
                      trial_ends_at = :trial_ends_at,
                      payment_method = :payment_method,
                      payment_reference = :payment_reference,
                      auto_renew = :auto_renew";

        $stmt = $this->conn->prepare($query);

        // Sanitizar dados
        $this->user_id = htmlspecialchars(strip_tags($this->user_id));
        $this->plan_id = htmlspecialchars(strip_tags($this->plan_id));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->starts_at = htmlspecialchars(strip_tags($this->starts_at));
        $this->ends_at = htmlspecialchars(strip_tags($this->ends_at));
        $this->trial_ends_at = htmlspecialchars(strip_tags($this->trial_ends_at));
        $this->payment_method = htmlspecialchars(strip_tags($this->payment_method));
        $this->payment_reference = htmlspecialchars(strip_tags($this->payment_reference));
        $this->auto_renew = $this->auto_renew ? 1 : 0;

        // Bind dos valores
        $stmt->bindValue(':user_id', $this->user_id);
        $stmt->bindValue(':plan_id', $this->plan_id);
        $stmt->bindValue(':status', $this->status);
        $stmt->bindValue(':starts_at', $this->starts_at);
        $stmt->bindValue(':ends_at', $this->ends_at);
        $stmt->bindValue(':trial_ends_at', $this->trial_ends_at);
        $stmt->bindValue(':payment_method', $this->payment_method);
        $stmt->bindValue(':payment_reference', $this->payment_reference);
        $stmt->bindValue(':auto_renew', $this->auto_renew);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Obter assinatura ativa do usuário
     */
    public function getActiveSubscription($userId) {
        $query = "SELECT us.*, sp.name as plan_name, sp.slug as plan_slug, 
                         sp.features, sp.limits_json
                  FROM " . $this->table_name . " us
                  INNER JOIN subscription_plans sp ON us.plan_id = sp.id
                  WHERE us.user_id = ? 
                  AND us.status IN ('active', 'trial')
                  AND us.ends_at >= CURDATE()
                  ORDER BY us.created_at DESC
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $userId);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obter histórico de assinaturas do usuário
     */
    public function getUserSubscriptions($userId) {
        $query = "SELECT us.*, sp.name as plan_name, sp.slug as plan_slug
                  FROM " . $this->table_name . " us
                  INNER JOIN subscription_plans sp ON us.plan_id = sp.id
                  WHERE us.user_id = ?
                  ORDER BY us.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $userId);
        $stmt->execute();

        return $stmt;
    }

    /**
     * Cancelar assinatura
     */
    public function cancel() {
        $query = "UPDATE " . $this->table_name . "
                  SET status = 'cancelled',
                      cancelled_at = CURRENT_TIMESTAMP,
                      auto_renew = 0
                  WHERE id = ? AND user_id = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $this->id);
        $stmt->bindValue(2, $this->user_id);

        return $stmt->execute();
    }

    /**
     * Reativar assinatura
     */
    public function reactivate() {
        $query = "UPDATE " . $this->table_name . "
                  SET status = 'active',
                      cancelled_at = NULL,
                      auto_renew = 1
                  WHERE id = ? AND user_id = ?
                  AND ends_at >= CURDATE()";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $this->id);
        $stmt->bindValue(2, $this->user_id);

        return $stmt->execute();
    }

    /**
     * Atualizar status da assinatura
     */
    public function updateStatus($status) {
        $query = "UPDATE " . $this->table_name . "
                  SET status = ?
                  WHERE id = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $status);
        $stmt->bindValue(2, $this->id);

        return $stmt->execute();
    }

    /**
     * Renovar assinatura
     */
    public function renew($newEndDate) {
        $query = "UPDATE " . $this->table_name . "
                  SET ends_at = ?,
                      status = 'active'
                  WHERE id = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $newEndDate);
        $stmt->bindValue(2, $this->id);

        return $stmt->execute();
    }

    /**
     * Verificar se usuário tem assinatura ativa
     */
    public function hasActiveSubscription($userId) {
        $query = "SELECT COUNT(*) as count
                  FROM " . $this->table_name . "
                  WHERE user_id = ? 
                  AND status = 'active'
                  AND ends_at >= CURDATE()";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $userId);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'] > 0;
    }

    /**
     * Verificar se usuário está em período de teste
     */
    public function isInTrial($userId) {
        $query = "SELECT COUNT(*) as count
                  FROM " . $this->table_name . "
                  WHERE user_id = ? 
                  AND status IN ('active', 'trial')
                  AND trial_ends_at >= CURDATE()";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $userId);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'] > 0;
    }

    /**
     * Verificar se usuário pode fazer upgrade/downgrade
     */
    public function canChangeSubscription($userId) {
        $activeSubscription = $this->getActiveSubscription($userId);
        
        if (!$activeSubscription) {
            return true; // Usuário sem assinatura pode assinar qualquer plano
        }

        // Verificar se não foi alterado recentemente (últimos 7 dias)
        $query = "SELECT COUNT(*) as count
                  FROM " . $this->table_name . "
                  WHERE user_id = ? 
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $userId);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'] <= 1; // Permitir apenas uma mudança por semana
    }

    /**
     * Criar trial gratuito
     */
    public function createTrial($userId, $planId, $trialDays = 7) {
        $this->user_id = $userId;
        $this->plan_id = $planId;
        $this->status = 'trial';
        $this->starts_at = date('Y-m-d');
        $this->ends_at = date('Y-m-d', strtotime('+30 days'));
        $this->trial_ends_at = date('Y-m-d', strtotime("+{$trialDays} days"));
        $this->auto_renew = 1;

        return $this->create();
    }
}