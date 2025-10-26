<?php
/**
 * Model de transação
 */
class Transaction {
    private $conn;
    private $table_name = "transactions";
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, category_id, type, amount, description, transaction_date, payment_method, tags) 
                  VALUES (:user_id, :category_id, :type, :amount, :description, :transaction_date, :payment_method, :tags)";
        
        $stmt = $this->conn->prepare($query);
        
        // Definir data padrão se não fornecida
        $transaction_date = $data['transaction_date'] ?? date('Y-m-d');
        $tags = isset($data['tags']) ? json_encode($data['tags']) : null;
        
        $stmt->bindValue(':user_id', $data['user_id']);
        $stmt->bindValue(':category_id', $data['category_id'] ?? null);
        $stmt->bindValue(':type', $data['type']);
        $stmt->bindValue(':amount', $data['amount']);
        $stmt->bindValue(':description', $data['description']);
        $stmt->bindValue(':transaction_date', $transaction_date);
        $stmt->bindValue(':payment_method', $data['payment_method'] ?? null);
        $stmt->bindValue(':tags', $tags);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        throw new Exception("Erro ao criar transação");
    }
    
    public function getByUser($user_id, $filters = [], $limit = 20, $offset = 0) {
        $where_conditions = ["t.user_id = :user_id"];
        $params = [':user_id' => $user_id];
        
        if (!empty($filters['type'])) {
            $where_conditions[] = "t.type = :type";
            $params[':type'] = $filters['type'];
        }
        
        if (!empty($filters['category_id'])) {
            $where_conditions[] = "t.category_id = :category_id";
            $params[':category_id'] = $filters['category_id'];
        }
        
        if (!empty($filters['start_date'])) {
            $where_conditions[] = "t.transaction_date >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where_conditions[] = "t.transaction_date <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        
        $query = "SELECT t.*, c.name as category_name, c.icon as category_icon, c.color as category_color
                  FROM " . $this->table_name . " t
                  LEFT JOIN categories c ON t.category_id = c.id
                  WHERE " . implode(' AND ', $where_conditions) . "
                  ORDER BY t.transaction_date DESC, t.id DESC
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $transactions = $stmt->fetchAll();
        
        // Decodificar tags JSON
        foreach ($transactions as &$transaction) {
            $transaction['tags'] = json_decode($transaction['tags'] ?? '[]', true);
        }
        
        return $transactions;
    }
    
    public function countByUser($user_id, $filters = []) {
        $where_conditions = ["user_id = :user_id"];
        $params = [':user_id' => $user_id];
        
        if (!empty($filters['type'])) {
            $where_conditions[] = "type = :type";
            $params[':type'] = $filters['type'];
        }
        
        if (!empty($filters['category_id'])) {
            $where_conditions[] = "category_id = :category_id";
            $params[':category_id'] = $filters['category_id'];
        }
        
        if (!empty($filters['start_date'])) {
            $where_conditions[] = "transaction_date >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where_conditions[] = "transaction_date <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE " . implode(' AND ', $where_conditions);
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetch()['total'];
    }
    
    public function findById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        $allowed_fields = ['category_id', 'type', 'amount', 'description', 'transaction_date', 'payment_method', 'tags'];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                $fields[] = "$key = :$key";
                $params[$key] = $key === 'tags' ? json_encode($value) : $value;
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
        $stmt->bindValue(':id', $id);
        
        return $stmt->execute();
    }
    
    public function getMonthlyReport($user_id, $year, $month) {
        // Relatório de receitas e despesas do mês
        $query = "SELECT 
                    type,
                    SUM(amount) as total,
                    COUNT(*) as count,
                    AVG(amount) as average
                  FROM " . $this->table_name . " 
                  WHERE user_id = :user_id 
                    AND YEAR(transaction_date) = :year 
                    AND MONTH(transaction_date) = :month
                  GROUP BY type";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':user_id', $user_id);
        $stmt->bindValue(':year', $year);
        $stmt->bindValue(':month', $month);
        $stmt->execute();
        
        $summary = [];
        $total_receita = 0;
        $total_despesa = 0;
        
        while ($row = $stmt->fetch()) {
            $summary[$row['type']] = [
                'total' => (float)$row['total'],
                'count' => (int)$row['count'],
                'average' => (float)$row['average']
            ];
            
            if ($row['type'] === 'receita') {
                $total_receita = (float)$row['total'];
            } else {
                $total_despesa = (float)$row['total'];
            }
        }
        
        // Relatório por categorias
        $query_categories = "SELECT 
                               c.name as category_name,
                               c.icon,
                               c.color,
                               t.type,
                               SUM(t.amount) as total,
                               COUNT(t.id) as count
                             FROM " . $this->table_name . " t
                             LEFT JOIN categories c ON t.category_id = c.id
                             WHERE t.user_id = :user_id 
                               AND YEAR(t.transaction_date) = :year 
                               AND MONTH(t.transaction_date) = :month
                             GROUP BY t.category_id, t.type
                             ORDER BY total DESC";
        
        $stmt_categories = $this->conn->prepare($query_categories);
        $stmt_categories->bindValue(':user_id', $user_id);
        $stmt_categories->bindValue(':year', $year);
        $stmt_categories->bindValue(':month', $month);
        $stmt_categories->execute();
        
        $categories = $stmt_categories->fetchAll();
        
        // Dados diários para gráficos
        $query_daily = "SELECT 
                          DATE(transaction_date) as date,
                          type,
                          SUM(amount) as total
                        FROM " . $this->table_name . "
                        WHERE user_id = :user_id 
                          AND YEAR(transaction_date) = :year 
                          AND MONTH(transaction_date) = :month
                        GROUP BY DATE(transaction_date), type
                        ORDER BY date";
        
        $stmt_daily = $this->conn->prepare($query_daily);
        $stmt_daily->bindValue(':user_id', $user_id);
        $stmt_daily->bindValue(':year', $year);
        $stmt_daily->bindValue(':month', $month);
        $stmt_daily->execute();
        
        $daily_data = $stmt_daily->fetchAll();
        
        return [
            'summary' => $summary,
            'profit' => $total_receita - $total_despesa,
            'total_receita' => $total_receita,
            'total_despesa' => $total_despesa,
            'categories' => $categories,
            'daily_data' => $daily_data,
            'month' => $month,
            'year' => $year
        ];
    }
}
?>
