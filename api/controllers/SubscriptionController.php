<?php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/SubscriptionPlan.php';
require_once __DIR__ . '/../models/UserSubscription.php';
require_once __DIR__ . '/../models/FeatureUsage.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class SubscriptionController {
    private $db;
    private $conn;
    private $authMiddleware;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
        $this->authMiddleware = new AuthMiddleware();
    }

    /**
     * Listar todos os planos disponíveis
     */
    public function getPlans() {
        try {
            $subscriptionPlan = new SubscriptionPlan($this->conn);
            $stmt = $subscriptionPlan->readAll();
            $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Formatar features e limites
            foreach ($plans as &$plan) {
                $plan['features'] = json_decode($plan['features'], true);
                $plan['limits'] = json_decode($plan['limits_json'], true);
                unset($plan['limits_json']);
                
                // Adicionar informações de preço formatado
                $plan['price_formatted'] = 'R$ ' . number_format($plan['price'], 2, ',', '.');
                $plan['is_free'] = $plan['price'] == 0;
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $plans
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao buscar planos: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Obter detalhes de um plano específico
     */
    public function getPlan($slug) {
        try {
            $subscriptionPlan = new SubscriptionPlan($this->conn);
            $subscriptionPlan->slug = $slug;
            
            if (!$subscriptionPlan->readBySlug()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Plano não encontrado'
                ]);
                return;
            }

            $plan = [
                'id' => $subscriptionPlan->id,
                'name' => $subscriptionPlan->name,
                'slug' => $subscriptionPlan->slug,
                'description' => $subscriptionPlan->description,
                'price' => $subscriptionPlan->price,
                'currency' => $subscriptionPlan->currency,
                'billing_period' => $subscriptionPlan->billing_period,
                'features' => $subscriptionPlan->features,
                'limits' => $subscriptionPlan->limits_json,
                'is_active' => $subscriptionPlan->is_active,
                'price_formatted' => 'R$ ' . number_format($subscriptionPlan->price, 2, ',', '.'),
                'is_free' => $subscriptionPlan->price == 0
            ];

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $plan
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao buscar plano: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Obter assinatura atual do usuário
     */
    public function getCurrentSubscription() {
        try {
            $authResult = $this->authMiddleware->authenticate();
            
            // O AuthMiddleware retorna um objeto ou faz exit() em caso de erro
            $userId = $authResult->user_id;
            
            $userSubscription = new UserSubscription($this->conn);
            $subscription = $userSubscription->getActiveSubscription($userId);
            
            if (!$subscription) {
                // Usuário sem assinatura - mostrar plano gratuito
                $subscriptionPlan = new SubscriptionPlan($this->conn);
                $subscriptionPlan->slug = 'free';
                
                if ($subscriptionPlan->readBySlug()) {
                    $subscription = [
                        'id' => null,
                        'status' => 'free',
                        'plan_name' => $subscriptionPlan->name,
                        'plan_slug' => $subscriptionPlan->slug,
                        'features' => $subscriptionPlan->features,
                        'limits_json' => json_encode($subscriptionPlan->limits_json),
                        'starts_at' => null,
                        'ends_at' => null,
                        'trial_ends_at' => null,
                        'auto_renew' => false
                    ];
                }
            }

            // Buscar estatísticas de uso
            $featureUsage = new FeatureUsage($this->conn);
            $usageStats = $featureUsage->getUserUsageStats($userId);
            
            // Formatar dados
            if (isset($subscription['features'])) {
                $subscription['features'] = is_string($subscription['features']) 
                    ? json_decode($subscription['features'], true) 
                    : $subscription['features'];
            }
            if (isset($subscription['limits_json'])) {
                $subscription['limits'] = is_string($subscription['limits_json']) 
                    ? json_decode($subscription['limits_json'], true) 
                    : $subscription['limits_json'];
                unset($subscription['limits_json']);
            }
            $subscription['usage_stats'] = $usageStats;

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $subscription
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao buscar assinatura: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Iniciar processo de assinatura
     */
    public function subscribe() {
        try {
            $authResult = $this->authMiddleware->authenticate();
            $userId = $authResult->user_id;
            
            $data = json_decode(file_get_contents("php://input"), true);
            
            if (!isset($data['plan_slug'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Plano não especificado'
                ]);
                return;
            }
            $planSlug = $data['plan_slug'];
            
            // Verificar se plano existe
            $subscriptionPlan = new SubscriptionPlan($this->conn);
            $subscriptionPlan->slug = $planSlug;
            
            if (!$subscriptionPlan->readBySlug()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Plano não encontrado'
                ]);
                return;
            }

            // Verificar se usuário pode trocar de plano
            $userSubscription = new UserSubscription($this->conn);
            
            if (!$userSubscription->canChangeSubscription($userId)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Você só pode alterar sua assinatura uma vez por semana'
                ]);
                return;
            }

            // Cancelar assinatura atual se existir
            $activeSubscription = $userSubscription->getActiveSubscription($userId);
            if ($activeSubscription) {
                $userSubscription->id = $activeSubscription['id'];
                $userSubscription->user_id = $userId;
                $userSubscription->cancel();
            }

            // Se for plano gratuito, apenas cancelar o atual
            if ($planSlug === 'free') {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Plano alterado para gratuito com sucesso'
                ]);
                return;
            }

            // Criar nova assinatura
            $userSubscription = new UserSubscription($this->conn);
            $userSubscription->user_id = $userId;
            $userSubscription->plan_id = $subscriptionPlan->id;
            
            // Verificar se é trial ou assinatura paga
            $isTrial = isset($data['trial']) && $data['trial'] === true;
            
            if ($isTrial && $subscriptionPlan->price > 0) {
                // Criar trial de 7 dias
                if ($userSubscription->createTrial($userId, $subscriptionPlan->id, 7)) {
                    http_response_code(201);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Trial iniciado com sucesso',
                        'subscription_id' => $userSubscription->id,
                        'trial_days' => 7
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Erro ao iniciar trial'
                    ]);
                }
            } else {
                // Assinatura paga - normalmente aqui integraria com gateway de pagamento
                $userSubscription->status = 'pending';
                $userSubscription->starts_at = date('Y-m-d');
                $userSubscription->ends_at = $subscriptionPlan->billing_period === 'yearly' 
                    ? date('Y-m-d', strtotime('+1 year'))
                    : date('Y-m-d', strtotime('+1 month'));
                $userSubscription->auto_renew = 1;
                
                if ($userSubscription->create()) {
                    http_response_code(201);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Assinatura criada com sucesso',
                        'subscription_id' => $userSubscription->id,
                        'payment_required' => true,
                        'amount' => $subscriptionPlan->price
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Erro ao criar assinatura'
                    ]);
                }
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao processar assinatura: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Cancelar assinatura
     */
    public function cancelSubscription() {
        try {
            $authResult = $this->authMiddleware->authenticate();
            $userId = $authResult->user_id;
            
            $userSubscription = new UserSubscription($this->conn);
            $activeSubscription = $userSubscription->getActiveSubscription($userId);
            
            if (!$activeSubscription) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Nenhuma assinatura ativa encontrada'
                ]);
                return;
            }

            $userSubscription->id = $activeSubscription['id'];
            $userSubscription->user_id = $userId;
            
            if ($userSubscription->cancel()) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Assinatura cancelada com sucesso'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao cancelar assinatura'
                ]);
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao cancelar assinatura: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Verificar se usuário pode usar uma feature
     */
    public function checkFeatureAccess($featureName) {
        try {
            $authResult = $this->authMiddleware->authenticate();
            $userId = $authResult->user_id;
            
            $subscriptionPlan = new SubscriptionPlan($this->conn);
            $canUse = $subscriptionPlan->canUseFeature($userId, $featureName);
            
            $featureUsage = new FeatureUsage($this->conn);
            $currentUsage = $featureUsage->getCurrentUsage($userId, $featureName);
            $remainingLimit = $featureUsage->getRemainingLimit($userId, $featureName);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => [
                    'can_use' => $canUse,
                    'current_usage' => $currentUsage,
                    'remaining_limit' => $remainingLimit,
                    'is_unlimited' => $remainingLimit === -1
                ]
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao verificar acesso: ' . $e->getMessage()
            ]);
        }
    }
}
?>