<?php
/**
 * Model de usuário
 */
class User {
    private $conn;
    private $table_name = "users";
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (name, email, password_hash, phone, business_name, business_type, cnpj) 
                  VALUES (:name, :email, :password_hash, :phone, :business_name, :business_type, :cnpj)";
        
        $stmt = $this->conn->prepare($query);
        
        // Hash da senha
        $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Bind dos parâmetros
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':password_hash', $password_hash);
        $stmt->bindParam(':phone', $data['phone'] ?? null);
        $stmt->bindParam(':business_name', $data['business_name'] ?? null);
        $stmt->bindParam(':business_type', $data['business_type'] ?? null);
        $stmt->bindParam(':cnpj', $data['cnpj'] ?? null);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        throw new Exception("Erro ao criar usuário");
    }
    
    public function findById($id) {
        $query = "SELECT id, name, email, phone, business_name, business_type, cnpj, 
                         subscription_status, subscription_expires_at, created_at 
                  FROM " . $this->table_name . " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    public function findByEmail($email) {
        $query = "SELECT id, name, email, password_hash, subscription_status, subscription_expires_at 
                  FROM " . $this->table_name . " WHERE email = :email";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            if ($key !== 'password' && $key !== 'id') {
                $fields[] = "$key = :$key";
                $params[$key] = $value;
            }
        }
        
        if (isset($data['password'])) {
            $fields[] = "password_hash = :password_hash";
            $params['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        if (empty($fields)) {
            throw new Exception("Nenhum campo para atualizar");
        }
        
        $query = "UPDATE " . $this->table_name . " SET " . implode(', ', $fields) . " WHERE id = :id";
        $params['id'] = $id;
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        
        return $stmt->execute();
    }
    
    public function updateSubscription($id, $status, $expires_at = null) {
        $query = "UPDATE " . $this->table_name . " 
                  SET subscription_status = :status, subscription_expires_at = :expires_at 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':expires_at', $expires_at);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }
    
    public function getSubscriptionStatus($id) {
        $query = "SELECT subscription_status, subscription_expires_at 
                  FROM " . $this->table_name . " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch();
    }
}
?>