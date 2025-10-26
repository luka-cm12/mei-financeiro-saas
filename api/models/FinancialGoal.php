<?php
/**
 * Modelo de Meta Financeira
 */
class FinancialGoal {
    private $conn;
    private $table_name = "financial_goals";
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create($data) {
        $query = "INSERT INTO financial_goals 
                  (user_id, name, description, target_amount, current_amount, target_date, status) 
                  VALUES (:user_id, :name, :description, :target_amount, :current_amount, :target_date, :status)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindValue(':user_id', $data['user_id']);
        $stmt->bindValue(':name', $data['name']);
        $stmt->bindValue(':description', $data['description'] ?? '');
        $stmt->bindValue(':target_amount', $data['target_amount']);
        $stmt->bindValue(':current_amount', 0);
        $stmt->bindValue(':target_date', $data['target_date']);
        $stmt->bindValue(':status', 'ativa');
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        throw new Exception("Erro ao criar meta financeira");
    }
    
    public function getByUser($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    public function update($id, $data) {
        $fields = [];
        $values = [];
        
        if (isset($data['title'])) {
            $fields[] = "title = :title";
            $values[':title'] = $data['title'];
        }
        
        if (isset($data['description'])) {
            $fields[] = "description = :description";
            $values[':description'] = $data['description'];
        }
        
        if (isset($data['target_amount'])) {
            $fields[] = "target_amount = :target_amount";
            $values[':target_amount'] = $data['target_amount'];
        }
        
        if (isset($data['current_amount'])) {
            $fields[] = "current_amount = :current_amount";
            $values[':current_amount'] = $data['current_amount'];
        }
        
        if (isset($data['target_date'])) {
            $fields[] = "target_date = :target_date";
            $values[':target_date'] = $data['target_date'];
        }
        
        if (isset($data['status'])) {
            $fields[] = "status = :status";
            $values[':status'] = $data['status'];
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $query = "UPDATE " . $this->table_name . " SET " . implode(', ', $fields) . " WHERE id = :id";
        $values[':id'] = $id;
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($values);
    }
    
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $id);
        
        return $stmt->execute();
    }
    
    public function getProgressByUser($user_id) {
        $query = "SELECT 
                    fg.*,
                    CASE 
                        WHEN fg.target_amount > 0 THEN (fg.current_amount / fg.target_amount * 100)
                        ELSE 0
                    END as progress_percentage,
                    DATEDIFF(fg.target_date, NOW()) as days_remaining
                  FROM " . $this->table_name . " fg
                  WHERE fg.user_id = :user_id 
                  AND fg.status = 'ativa'
                  ORDER BY fg.target_date ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function updateCurrentAmount($user_id) {
        // Atualizar valor atual baseado nas transações
        $query = "UPDATE " . $this->table_name . " fg
                  SET current_amount = (
                      SELECT COALESCE(SUM(
                          CASE 
                              WHEN t.type = 'receita' THEN t.amount
                              ELSE -t.amount
                          END
                      ), 0)
                      FROM transactions t
                      WHERE t.user_id = fg.user_id
                      AND t.transaction_date >= fg.created_at
                      AND t.transaction_date <= NOW()
                  )
                  WHERE fg.user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':user_id', $user_id);
        
        return $stmt->execute();
    }
}
?>