<?php
/**
 * Model de categoria
 */
class Category {
    private $conn;
    private $table_name = "categories";
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, name, type, icon, color) 
                  VALUES (:user_id, :name, :type, :icon, :color)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':user_id', $data['user_id']);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':type', $data['type']);
        $stmt->bindParam(':icon', $data['icon'] ?? 'category');
        $stmt->bindParam(':color', $data['color'] ?? '#9E9E9E');
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        throw new Exception("Erro ao criar categoria");
    }
    
    public function getByUser($user_id) {
        $query = "SELECT id, name, type, icon, color, created_at 
                  FROM " . $this->table_name . " 
                  WHERE user_id = :user_id 
                  ORDER BY type, name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function findById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        $allowed_fields = ['name', 'type', 'icon', 'color'];
        
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
    
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }
    
    public function getTransactionsByCategory($user_id, $period = 'month') {
        $date_condition = '';
        
        switch ($period) {
            case 'week':
                $date_condition = 'AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)';
                break;
            case 'month':
                $date_condition = 'AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)';
                break;
            case 'year':
                $date_condition = 'AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)';
                break;
        }
        
        $query = "SELECT 
                    c.id, 
                    c.name, 
                    c.type, 
                    c.icon, 
                    c.color,
                    COUNT(t.id) as transaction_count,
                    SUM(t.amount) as total_amount
                  FROM " . $this->table_name . " c
                  LEFT JOIN transactions t ON c.id = t.category_id AND t.user_id = c.user_id $date_condition
                  WHERE c.user_id = :user_id
                  GROUP BY c.id
                  ORDER BY total_amount DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
?>