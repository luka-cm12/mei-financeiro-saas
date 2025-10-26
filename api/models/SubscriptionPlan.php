<?php

class SubscriptionPlan {
    private $conn;
    private $table_name = "subscription_plans";

    public $id;
    public $name;
    public $slug;
    public $description;
    public $price;
    public $currency;
    public $billing_period;
    public $features;
    public $limits_json;
    public $is_active;
    public $sort_order;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Listar todos os planos ativos
     */
    public function readAll() {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE is_active = 1 
                  ORDER BY sort_order ASC, price ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt;
    }

    /**
     * Buscar plano por slug
     */
    public function readBySlug() {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE slug = ? AND is_active = 1 
                  LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $this->slug);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            $this->id = $row['id'];
            $this->name = $row['name'];
            $this->slug = $row['slug'];
            $this->description = $row['description'];
            $this->price = $row['price'];
            $this->currency = $row['currency'];
            $this->billing_period = $row['billing_period'];
            $this->features = json_decode($row['features'], true);
            $this->limits_json = json_decode($row['limits_json'], true);
            $this->is_active = $row['is_active'];
            $this->sort_order = $row['sort_order'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            return true;
        }
        
        return false;
    }

    /**
     * Buscar plano por ID
     */
    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE id = ? 
                  LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $this->id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            $this->name = $row['name'];
            $this->slug = $row['slug'];
            $this->description = $row['description'];
            $this->price = $row['price'];
            $this->currency = $row['currency'];
            $this->billing_period = $row['billing_period'];
            $this->features = json_decode($row['features'], true);
            $this->limits_json = json_decode($row['limits_json'], true);
            $this->is_active = $row['is_active'];
            $this->sort_order = $row['sort_order'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            return true;
        }
        
        return false;
    }

    /**
     * Verificar se usuário pode usar uma feature
     */
    public function canUseFeature($userId, $featureName) {
        // Buscar assinatura ativa do usuário
        $subscription = new UserSubscription($this->conn);
        $activeSubscription = $subscription->getActiveSubscription($userId);
        
        if (!$activeSubscription) {
            // Usuário sem assinatura - usar plano gratuito
            $this->slug = 'free';
            if (!$this->readBySlug()) {
                return false;
            }
        } else {
            $this->id = $activeSubscription['plan_id'];
            if (!$this->readOne()) {
                return false;
            }
        }

        // Verificar limites do plano
        if (!isset($this->limits_json[$featureName])) {
            return true; // Se não há limite definido, permitir
        }

        $limit = $this->limits_json[$featureName];
        
        // -1 significa ilimitado
        if ($limit === -1) {
            return true;
        }

        // Verificar uso atual
        $featureUsage = new FeatureUsage($this->conn);
        $currentUsage = $featureUsage->getCurrentUsage($userId, $featureName);
        
        return $currentUsage < $limit;
    }

    /**
     * Obter limite de uma feature
     */
    public function getFeatureLimit($featureName) {
        if (!isset($this->limits_json[$featureName])) {
            return -1; // Ilimitado se não especificado
        }
        
        return $this->limits_json[$featureName];
    }
}