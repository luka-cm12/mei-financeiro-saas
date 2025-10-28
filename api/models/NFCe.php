<?php
class NFCe {
    private $conn;
    private $table = 'nfce_emissions';
    private $items_table = 'nfce_items';
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Cria nova NFCe
     */
    public function create($data) {
        try {
            $this->conn->beginTransaction();
            
            // Inserir NFCe principal
            $query = "INSERT INTO " . $this->table . " 
                      (establishment_id, nfce_number, nfce_series, nfce_key, 
                       customer_document, customer_name, customer_email, customer_phone,
                       total_products, total_discounts, total_tax, total_amount,
                       payment_method, payment_amount, change_amount,
                       emission_date, status, xml_file_path, pdf_file_path,
                       protocol_number, authorization_date, cancellation_reason,
                       created_at, updated_at) 
                      VALUES 
                      (:establishment_id, :nfce_number, :nfce_series, :nfce_key,
                       :customer_document, :customer_name, :customer_email, :customer_phone,
                       :total_products, :total_discounts, :total_tax, :total_amount,
                       :payment_method, :payment_amount, :change_amount,
                       NOW(), :status, :xml_file_path, :pdf_file_path,
                       :protocol_number, :authorization_date, :cancellation_reason,
                       NOW(), NOW())";
            
            $stmt = $this->conn->prepare($query);
            
            // Bind dos parâmetros principais
            $stmt->bindParam(':establishment_id', $data['establishment_id']);
            $stmt->bindParam(':nfce_number', $data['nfce_number']);
            $stmt->bindParam(':nfce_series', $data['nfce_series']);
            $stmt->bindParam(':nfce_key', $data['nfce_key']);
            $stmt->bindParam(':customer_document', $data['customer_document']);
            $stmt->bindParam(':customer_name', $data['customer_name']);
            $stmt->bindParam(':customer_email', $data['customer_email']);
            $stmt->bindParam(':customer_phone', $data['customer_phone']);
            $stmt->bindParam(':total_products', $data['total_products']);
            $stmt->bindParam(':total_discounts', $data['total_discounts']);
            $stmt->bindParam(':total_tax', $data['total_tax']);
            $stmt->bindParam(':total_amount', $data['total_amount']);
            $stmt->bindParam(':payment_method', $data['payment_method']);
            $stmt->bindParam(':payment_amount', $data['payment_amount']);
            $stmt->bindParam(':change_amount', $data['change_amount']);
            $stmt->bindParam(':status', $data['status']);
            $stmt->bindParam(':xml_file_path', $data['xml_file_path']);
            $stmt->bindParam(':pdf_file_path', $data['pdf_file_path']);
            $stmt->bindParam(':protocol_number', $data['protocol_number']);
            $stmt->bindParam(':authorization_date', $data['authorization_date']);
            $stmt->bindParam(':cancellation_reason', $data['cancellation_reason']);
            
            if (!$stmt->execute()) {
                throw new Exception('Erro ao inserir NFCe');
            }
            
            $nfce_id = $this->conn->lastInsertId();
            
            // Inserir itens da NFCe
            if (!empty($data['items'])) {
                $this->insertItems($nfce_id, $data['items']);
            }
            
            $this->conn->commit();
            return $nfce_id;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    /**
     * Insere itens da NFCe
     */
    private function insertItems($nfce_id, $items) {
        $query = "INSERT INTO " . $this->items_table . " 
                  (nfce_emission_id, product_id, description, quantity, unit_price, 
                   total_price, ncm, cfop, icms_origin, icms_tax_situation,
                   pis_tax_situation, cofins_tax_situation, created_at, updated_at) 
                  VALUES 
                  (:nfce_emission_id, :product_id, :description, :quantity, :unit_price,
                   :total_price, :ncm, :cfop, :icms_origin, :icms_tax_situation,
                   :pis_tax_situation, :cofins_tax_situation, NOW(), NOW())";
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($items as $item) {
            $stmt->bindParam(':nfce_emission_id', $nfce_id);
            $stmt->bindParam(':product_id', $item['product_id']);
            $stmt->bindParam(':description', $item['description']);
            $stmt->bindParam(':quantity', $item['quantity']);
            $stmt->bindParam(':unit_price', $item['unit_price']);
            $stmt->bindParam(':total_price', $item['total_price']);
            $stmt->bindParam(':ncm', $item['ncm']);
            $stmt->bindParam(':cfop', $item['cfop']);
            $stmt->bindParam(':icms_origin', $item['icms_origin']);
            $stmt->bindParam(':icms_tax_situation', $item['icms_tax_situation']);
            $stmt->bindParam(':pis_tax_situation', $item['pis_tax_situation']);
            $stmt->bindParam(':cofins_tax_situation', $item['cofins_tax_situation']);
            
            if (!$stmt->execute()) {
                throw new Exception('Erro ao inserir item da NFCe');
            }
        }
    }
    
    /**
     * Busca NFCes do estabelecimento
     */
    public function getNFCes($establishment_id, $filters = []) {
        $where_conditions = ["n.establishment_id = :establishment_id"];
        $params = [':establishment_id' => $establishment_id];
        
        // Filtros opcionais
        if (!empty($filters['status'])) {
            $where_conditions[] = "n.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(n.emission_date) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(n.emission_date) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['customer_document'])) {
            $where_conditions[] = "n.customer_document LIKE :customer_document";
            $params[':customer_document'] = '%' . $filters['customer_document'] . '%';
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "SELECT n.*, 
                         COUNT(ni.id) as total_items
                  FROM " . $this->table . " n
                  LEFT JOIN " . $this->items_table . " ni ON n.id = ni.nfce_emission_id
                  WHERE {$where_clause}
                  GROUP BY n.id
                  ORDER BY n.emission_date DESC, n.id DESC";
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Busca NFCe específica com itens
     */
    public function getNFCe($id, $establishment_id) {
        // Buscar NFCe
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE id = :id AND establishment_id = :establishment_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':establishment_id', $establishment_id);
        $stmt->execute();
        
        $nfce = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($nfce) {
            // Buscar itens
            $items_query = "SELECT ni.*, p.name as product_name
                           FROM " . $this->items_table . " ni
                           LEFT JOIN establishment_products p ON ni.product_id = p.id
                           WHERE ni.nfce_emission_id = :nfce_id
                           ORDER BY ni.id ASC";
            
            $items_stmt = $this->conn->prepare($items_query);
            $items_stmt->bindParam(':nfce_id', $id);
            $items_stmt->execute();
            
            $nfce['items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return $nfce;
    }
    
    /**
     * Atualiza status da NFCe
     */
    public function updateStatus($id, $status, $additional_data = []) {
        $set_fields = ["status = :status", "updated_at = NOW()"];
        $params = [':id' => $id, ':status' => $status];
        
        // Adicionar campos extras conforme necessário
        if (isset($additional_data['protocol_number'])) {
            $set_fields[] = "protocol_number = :protocol_number";
            $params[':protocol_number'] = $additional_data['protocol_number'];
        }
        
        if (isset($additional_data['authorization_date'])) {
            $set_fields[] = "authorization_date = :authorization_date";
            $params[':authorization_date'] = $additional_data['authorization_date'];
        }
        
        if (isset($additional_data['xml_file_path'])) {
            $set_fields[] = "xml_file_path = :xml_file_path";
            $params[':xml_file_path'] = $additional_data['xml_file_path'];
        }
        
        if (isset($additional_data['pdf_file_path'])) {
            $set_fields[] = "pdf_file_path = :pdf_file_path";
            $params[':pdf_file_path'] = $additional_data['pdf_file_path'];
        }
        
        if (isset($additional_data['cancellation_reason'])) {
            $set_fields[] = "cancellation_reason = :cancellation_reason";
            $params[':cancellation_reason'] = $additional_data['cancellation_reason'];
        }
        
        $set_clause = implode(', ', $set_fields);
        
        $query = "UPDATE " . $this->table . " SET {$set_clause} WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        return $stmt->execute();
    }
    
    /**
     * Gera próximo número da NFCe
     */
    public function getNextNumber($establishment_id, $series) {
        // Buscar último número usado
        $query = "SELECT MAX(nfce_number) as last_number 
                  FROM " . $this->table . " 
                  WHERE establishment_id = :establishment_id 
                  AND nfce_series = :series";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':establishment_id', $establishment_id);
        $stmt->bindParam(':series', $series);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $last_number = $result['last_number'] ?? 0;
        
        return $last_number + 1;
    }
    
    /**
     * Gera chave da NFCe (44 dígitos)
     */
    public function generateNFCeKey($establishment_data, $nfce_number, $series) {
        // UF do estabelecimento (2 dígitos)
        $uf_code = $this->getUFCode($establishment_data['state']);
        
        // Ano e mês (4 dígitos)
        $year_month = date('ym');
        
        // CNPJ (14 dígitos)
        $cnpj = preg_replace('/\D/', '', $establishment_data['document']);
        
        // Modelo (2 dígitos) - 65 para NFCe
        $model = '65';
        
        // Série (3 dígitos)
        $series_padded = str_pad($series, 3, '0', STR_PAD_LEFT);
        
        // Número da NFCe (9 dígitos)
        $number_padded = str_pad($nfce_number, 9, '0', STR_PAD_LEFT);
        
        // Tipo de emissão (1 dígito) - 1 para normal
        $emission_type = '1';
        
        // Código numérico (8 dígitos) - aleatório
        $numeric_code = str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        
        // Montar chave sem DV
        $key_without_dv = $uf_code . $year_month . $cnpj . $model . $series_padded . $number_padded . $emission_type . $numeric_code;
        
        // Calcular dígito verificador
        $dv = $this->calculateDV($key_without_dv);
        
        return $key_without_dv . $dv;
    }
    
    /**
     * Calcula dígito verificador da chave NFCe
     */
    private function calculateDV($key) {
        $sequence = '4329876543298765432987654329876543298765432';
        $sum = 0;
        
        for ($i = 0; $i < 43; $i++) {
            $sum += $key[$i] * $sequence[$i];
        }
        
        $remainder = $sum % 11;
        
        if ($remainder < 2) {
            return 0;
        } else {
            return 11 - $remainder;
        }
    }
    
    /**
     * Obtém código da UF
     */
    private function getUFCode($state) {
        $uf_codes = [
            'AC' => 12, 'AL' => 17, 'AP' => 16, 'AM' => 23, 'BA' => 29,
            'CE' => 23, 'DF' => 53, 'ES' => 32, 'GO' => 52, 'MA' => 21,
            'MT' => 51, 'MS' => 50, 'MG' => 31, 'PA' => 15, 'PB' => 25,
            'PR' => 41, 'PE' => 26, 'PI' => 22, 'RJ' => 33, 'RN' => 24,
            'RS' => 43, 'RO' => 11, 'RR' => 14, 'SC' => 42, 'SP' => 35,
            'SE' => 28, 'TO' => 17
        ];
        
        return $uf_codes[$state] ?? 35; // Default SP
    }
    
    /**
     * Valida dados da NFCe
     */
    public function validateNFCeData($data) {
        $errors = [];
        
        if (empty($data['establishment_id'])) {
            $errors[] = 'ID do estabelecimento é obrigatório';
        }
        
        if (empty($data['items']) || !is_array($data['items'])) {
            $errors[] = 'Itens da NFCe são obrigatórios';
        } else {
            foreach ($data['items'] as $index => $item) {
                if (empty($item['description'])) {
                    $errors[] = "Descrição do item " . ($index + 1) . " é obrigatória";
                }
                
                if (!isset($item['quantity']) || $item['quantity'] <= 0) {
                    $errors[] = "Quantidade do item " . ($index + 1) . " deve ser maior que zero";
                }
                
                if (!isset($item['unit_price']) || $item['unit_price'] <= 0) {
                    $errors[] = "Preço unitário do item " . ($index + 1) . " deve ser maior que zero";
                }
            }
        }
        
        if (!isset($data['total_amount']) || $data['total_amount'] <= 0) {
            $errors[] = 'Valor total deve ser maior que zero';
        }
        
        if (empty($data['payment_method'])) {
            $errors[] = 'Forma de pagamento é obrigatória';
        }
        
        return $errors;
    }
    
    /**
     * Busca estatísticas de NFCe
     */
    public function getStatistics($establishment_id, $period = 'month') {
        $date_condition = '';
        
        switch ($period) {
            case 'today':
                $date_condition = 'DATE(emission_date) = CURDATE()';
                break;
            case 'week':
                $date_condition = 'emission_date >= DATE_SUB(NOW(), INTERVAL 1 WEEK)';
                break;
            case 'month':
                $date_condition = 'emission_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)';
                break;
            case 'year':
                $date_condition = 'emission_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)';
                break;
        }
        
        $query = "SELECT 
                    COUNT(*) as total_nfces,
                    COUNT(CASE WHEN status = 'authorized' THEN 1 END) as authorized_count,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_count,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                    SUM(CASE WHEN status = 'authorized' THEN total_amount ELSE 0 END) as total_revenue,
                    AVG(CASE WHEN status = 'authorized' THEN total_amount ELSE NULL END) as average_ticket
                  FROM " . $this->table . "
                  WHERE establishment_id = :establishment_id
                  AND {$date_condition}";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':establishment_id', $establishment_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>