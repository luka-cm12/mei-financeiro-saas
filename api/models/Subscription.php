<?php
/**
 * Model de assinatura
 */
class Subscription {
    private $conn;
    private $table_name = "subscriptions";
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, plan_name, amount, status, payment_method, payment_provider, starts_at, expires_at) 
                  VALUES (:user_id, :plan_name, :amount, :status, :payment_method, :payment_provider, :starts_at, :expires_at)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':user_id', $data['user_id']);
        $stmt->bindParam(':plan_name', $data['plan_name']);
        $stmt->bindParam(':amount', $data['amount']);
        $stmt->bindParam(':status', $data['status']);
        $stmt->bindParam(':payment_method', $data['payment_method']);
        $stmt->bindParam(':payment_provider', $data['payment_provider'] ?? null);
        $stmt->bindParam(':starts_at', $data['starts_at']);
        $stmt->bindParam(':expires_at', $data['expires_at']);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        throw new Exception("Erro ao criar assinatura");
    }
    
    public function findById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    public function findByUser($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    public function updateStatus($id, $status) {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = :status, updated_at = CURRENT_TIMESTAMP 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        $allowed_fields = ['plan_name', 'amount', 'status', 'payment_method', 'payment_provider', 'external_id', 'expires_at'];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                $fields[] = "$key = :$key";
                $params[$key] = $value;
            }
        }
        
        if (empty($fields)) {
            throw new Exception("Nenhum campo válido para atualizar");
        }
        
        $query = "UPDATE " . $this->table_name . " SET " . implode(', ', $fields) . " WHERE id = :id";
        $params['id'] = $id;
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        
        return $stmt->execute();
    }
    
    public function getActiveSubscriptions() {
        $query = "SELECT s.*, u.name, u.email 
                  FROM " . $this->table_name . " s
                  JOIN users u ON s.user_id = u.id
                  WHERE s.status = 'active' 
                    AND s.expires_at > NOW()
                  ORDER BY s.expires_at ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function getExpiredSubscriptions() {
        $query = "SELECT s.*, u.name, u.email 
                  FROM " . $this->table_name . " s
                  JOIN users u ON s.user_id = u.id
                  WHERE s.status = 'active' 
                    AND s.expires_at <= NOW()
                  ORDER BY s.expires_at ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function getRevenueSummary($start_date = null, $end_date = null) {
        $where_clause = "WHERE s.status = 'active'";
        $params = [];
        
        if ($start_date) {
            $where_clause .= " AND s.created_at >= :start_date";
            $params['start_date'] = $start_date;
        }
        
        if ($end_date) {
            $where_clause .= " AND s.created_at <= :end_date";
            $params['end_date'] = $end_date;
        }
        
        $query = "SELECT 
                    COUNT(*) as total_subscriptions,
                    SUM(s.amount) as total_revenue,
                    AVG(s.amount) as average_revenue,
                    DATE(s.created_at) as date
                  FROM " . $this->table_name . " s
                  $where_clause
                  GROUP BY DATE(s.created_at)
                  ORDER BY date DESC";
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
?>