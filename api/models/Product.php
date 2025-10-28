<?php
class Product {
    private $conn;
    private $table = 'establishment_products';
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Busca produtos do estabelecimento
     */
    public function getProducts($establishment_id) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE establishment_id = :establishment_id 
                  AND active = 1
                  ORDER BY name ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':establishment_id', $establishment_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Busca produto por ID
     */
    public function getProduct($id, $establishment_id) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE id = :id AND establishment_id = :establishment_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':establishment_id', $establishment_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Cria novo produto
     */
    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                  (establishment_id, name, description, price, unit, ncm, cfop, 
                   icms_origin, icms_tax_situation, pis_tax_situation, cofins_tax_situation, 
                   active, created_at, updated_at) 
                  VALUES 
                  (:establishment_id, :name, :description, :price, :unit, :ncm, :cfop,
                   :icms_origin, :icms_tax_situation, :pis_tax_situation, :cofins_tax_situation,
                   1, NOW(), NOW())";
        
        $stmt = $this->conn->prepare($query);
        
        // Bind dos parâmetros
        $stmt->bindParam(':establishment_id', $data['establishment_id']);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':price', $data['price']);
        $stmt->bindParam(':unit', $data['unit']);
        $stmt->bindParam(':ncm', $data['ncm']);
        $stmt->bindParam(':cfop', $data['cfop']);
        $stmt->bindParam(':icms_origin', $data['icms_origin']);
        $stmt->bindParam(':icms_tax_situation', $data['icms_tax_situation']);
        $stmt->bindParam(':pis_tax_situation', $data['pis_tax_situation']);
        $stmt->bindParam(':cofins_tax_situation', $data['cofins_tax_situation']);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Atualiza produto
     */
    public function update($id, $data) {
        $query = "UPDATE " . $this->table . " SET 
                  name = :name,
                  description = :description,
                  price = :price,
                  unit = :unit,
                  ncm = :ncm,
                  cfop = :cfop,
                  icms_origin = :icms_origin,
                  icms_tax_situation = :icms_tax_situation,
                  pis_tax_situation = :pis_tax_situation,
                  cofins_tax_situation = :cofins_tax_situation,
                  updated_at = NOW()
                  WHERE id = :id AND establishment_id = :establishment_id";
        
        $stmt = $this->conn->prepare($query);
        
        // Bind dos parâmetros
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':establishment_id', $data['establishment_id']);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':price', $data['price']);
        $stmt->bindParam(':unit', $data['unit']);
        $stmt->bindParam(':ncm', $data['ncm']);
        $stmt->bindParam(':cfop', $data['cfop']);
        $stmt->bindParam(':icms_origin', $data['icms_origin']);
        $stmt->bindParam(':icms_tax_situation', $data['icms_tax_situation']);
        $stmt->bindParam(':pis_tax_situation', $data['pis_tax_situation']);
        $stmt->bindParam(':cofins_tax_situation', $data['cofins_tax_situation']);
        
        return $stmt->execute();
    }
    
    /**
     * Remove produto (soft delete)
     */
    public function delete($id, $establishment_id) {
        $query = "UPDATE " . $this->table . " SET 
                  active = 0, updated_at = NOW()
                  WHERE id = :id AND establishment_id = :establishment_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':establishment_id', $establishment_id);
        
        return $stmt->execute();
    }
    
    /**
     * Busca produtos por termo
     */
    public function searchProducts($establishment_id, $search_term) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE establishment_id = :establishment_id 
                  AND active = 1
                  AND (name LIKE :search OR description LIKE :search)
                  ORDER BY name ASC";
        
        $stmt = $this->conn->prepare($query);
        $search = '%' . $search_term . '%';
        $stmt->bindParam(':establishment_id', $establishment_id);
        $stmt->bindParam(':search', $search);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Valida dados do produto
     */
    public function validateProductData($data) {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors[] = 'Nome do produto é obrigatório';
        }
        
        if (!isset($data['price']) || $data['price'] <= 0) {
            $errors[] = 'Preço deve ser maior que zero';
        }
        
        if (empty($data['unit'])) {
            $errors[] = 'Unidade é obrigatória';
        }
        
        if (empty($data['cfop'])) {
            $errors[] = 'CFOP é obrigatório';
        }
        
        if (!empty($data['ncm']) && strlen($data['ncm']) !== 8) {
            $errors[] = 'NCM deve ter 8 dígitos';
        }
        
        return $errors;
    }
    
    /**
     * Busca produtos mais vendidos
     */
    public function getMostSoldProducts($establishment_id, $limit = 10) {
        $query = "SELECT p.*, COALESCE(SUM(ni.quantity), 0) as total_sold
                  FROM " . $this->table . " p
                  LEFT JOIN nfce_items ni ON p.id = ni.product_id
                  LEFT JOIN nfce_emissions ne ON ni.nfce_emission_id = ne.id
                  WHERE p.establishment_id = :establishment_id 
                  AND p.active = 1
                  AND (ne.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) OR ne.id IS NULL)
                  GROUP BY p.id
                  ORDER BY total_sold DESC, p.name ASC
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':establishment_id', $establishment_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>