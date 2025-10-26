<?php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/UserSubscription.php';
require_once __DIR__ . '/../models/SubscriptionPlan.php';
require_once __DIR__ . '/../integrations/MercadoPagoPayment.php';

class PaymentController {
    private $db;
    private $conn;
    private $authMiddleware;
    private $mercadoPago;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
        $this->authMiddleware = new AuthMiddleware();
        $this->mercadoPago = new MercadoPagoPayment();
    }

    /**
     * Criar preferência de pagamento para checkout
     */
    public function createPaymentPreference() {
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

            // Buscar dados do plano
            $subscriptionPlan = new SubscriptionPlan($this->conn);
            $subscriptionPlan->slug = $data['plan_slug'];
            
            if (!$subscriptionPlan->readBySlug()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Plano não encontrado'
                ]);
                return;
            }

            // Buscar dados do usuário
            $userQuery = "SELECT email, name FROM users WHERE id = ?";
            $userStmt = $this->conn->prepare($userQuery);
            $userStmt->bindValue(1, $userId);
            $userStmt->execute();
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ]);
                return;
            }

            // Criar assinatura pendente
            $userSubscription = new UserSubscription($this->conn);
            $userSubscription->user_id = $userId;
            $userSubscription->plan_id = $subscriptionPlan->id;
            $userSubscription->status = 'pending';
            $userSubscription->starts_at = date('Y-m-d');
            $userSubscription->ends_at = $subscriptionPlan->billing_period === 'yearly' 
                ? date('Y-m-d', strtotime('+1 year'))
                : date('Y-m-d', strtotime('+1 month'));
            $userSubscription->payment_method = $data['payment_method'] ?? 'mercadopago';
            $userSubscription->auto_renew = 1;

            if (!$userSubscription->create()) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao criar assinatura'
                ]);
                return;
            }

            // Preparar dados para Mercado Pago
            $paymentData = [
                'title' => $subscriptionPlan->name,
                'description' => $subscriptionPlan->description,
                'amount' => $subscriptionPlan->price,
                'payer_email' => $user['email'],
                'payer_name' => $user['name'],
                'external_reference' => "subscription_{$userSubscription->id}",
                'success_url' => $data['success_url'] ?? 'https://seuapp.com/success',
                'failure_url' => $data['failure_url'] ?? 'https://seuapp.com/failure',
                'pending_url' => $data['pending_url'] ?? 'https://seuapp.com/pending',
                'notification_url' => 'https://seudominio.com/api/webhook-mercadopago.php'
            ];

            // Criar preferência no Mercado Pago
            $preference = $this->mercadoPago->createPaymentPreference($paymentData);

            if (!$preference['success']) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao criar pagamento: ' . $preference['message']
                ]);
                return;
            }

            // Salvar referência do Mercado Pago
            $updateQuery = "UPDATE user_subscriptions SET payment_reference = ? WHERE id = ?";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindValue(1, $preference['data']['id']);
            $updateStmt->bindValue(2, $userSubscription->id);
            $updateStmt->execute();

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'data' => [
                    'subscription_id' => $userSubscription->id,
                    'preference_id' => $preference['data']['id'],
                    'init_point' => $preference['data']['init_point'],
                    'sandbox_init_point' => $preference['data']['sandbox_init_point'] ?? null
                ]
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Criar pagamento PIX
     */
    public function createPixPayment() {
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

            // Buscar dados do plano
            $subscriptionPlan = new SubscriptionPlan($this->conn);
            $subscriptionPlan->slug = $data['plan_slug'];
            
            if (!$subscriptionPlan->readBySlug()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Plano não encontrado'
                ]);
                return;
            }

            // Buscar dados do usuário
            $userQuery = "SELECT email, name FROM users WHERE id = ?";
            $userStmt = $this->conn->prepare($userQuery);
            $userStmt->bindValue(1, $userId);
            $userStmt->execute();
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            // Criar assinatura pendente
            $userSubscription = new UserSubscription($this->conn);
            $userSubscription->user_id = $userId;
            $userSubscription->plan_id = $subscriptionPlan->id;
            $userSubscription->status = 'pending';
            $userSubscription->starts_at = date('Y-m-d');
            $userSubscription->ends_at = $subscriptionPlan->billing_period === 'yearly' 
                ? date('Y-m-d', strtotime('+1 year'))
                : date('Y-m-d', strtotime('+1 month'));
            $userSubscription->payment_method = 'pix';
            $userSubscription->auto_renew = 0; // PIX não é recorrente

            if (!$userSubscription->create()) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao criar assinatura'
                ]);
                return;
            }

            // Preparar dados para PIX
            $pixData = [
                'amount' => $subscriptionPlan->price,
                'description' => "Assinatura {$subscriptionPlan->name} - MEI Financeiro",
                'payer_email' => $user['email'],
                'payer_name' => $user['name'],
                'external_reference' => "subscription_{$userSubscription->id}",
                'notification_url' => 'https://seudominio.com/api/webhook-mercadopago.php'
            ];

            // Criar pagamento PIX no Mercado Pago
            $pixPayment = $this->mercadoPago->createPixPayment($pixData);

            if (!$pixPayment['success']) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao criar PIX: ' . $pixPayment['message']
                ]);
                return;
            }

            // Salvar referência do pagamento
            $updateQuery = "UPDATE user_subscriptions SET payment_reference = ? WHERE id = ?";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindValue(1, $pixPayment['data']['id']);
            $updateStmt->bindValue(2, $userSubscription->id);
            $updateStmt->execute();

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'data' => [
                    'subscription_id' => $userSubscription->id,
                    'payment_id' => $pixPayment['data']['id'],
                    'qr_code' => $pixPayment['data']['point_of_interaction']['transaction_data']['qr_code'] ?? '',
                    'qr_code_base64' => $pixPayment['data']['point_of_interaction']['transaction_data']['qr_code_base64'] ?? '',
                    'expiration_date' => $pixPayment['data']['date_of_expiration'] ?? null
                ]
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Criar assinatura recorrente
     */
    public function createSubscription() {
        try {
            $authResult = $this->authMiddleware->authenticate();
            $userId = $authResult->user_id;
            
            $data = json_decode(file_get_contents("php://input"), true);
            
            if (!isset($data['plan_slug']) || !isset($data['card_token'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Plano ou token do cartão não especificado'
                ]);
                return;
            }

            // Buscar dados do plano
            $subscriptionPlan = new SubscriptionPlan($this->conn);
            $subscriptionPlan->slug = $data['plan_slug'];
            
            if (!$subscriptionPlan->readBySlug()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Plano não encontrado'
                ]);
                return;
            }

            // Buscar dados do usuário
            $userQuery = "SELECT email, name FROM users WHERE id = ?";
            $userStmt = $this->conn->prepare($userQuery);
            $userStmt->bindValue(1, $userId);
            $userStmt->execute();
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            // Preparar dados para assinatura
            $subscriptionData = [
                'plan_name' => $subscriptionPlan->name,
                'subscription_title' => "Assinatura {$subscriptionPlan->name} - MEI Financeiro",
                'amount' => $subscriptionPlan->price,
                'frequency' => $subscriptionPlan->billing_period === 'yearly' ? 12 : 1,
                'payer_email' => $user['email'],
                'card_token' => $data['card_token'],
                'external_reference' => "user_{$userId}_plan_{$subscriptionPlan->id}",
                'success_url' => $data['success_url'] ?? 'https://seuapp.com/success'
            ];

            // Criar assinatura no Mercado Pago
            $subscription = $this->mercadoPago->createSubscription($subscriptionData);

            if (!$subscription['success']) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao criar assinatura: ' . $subscription['message']
                ]);
                return;
            }

            // Criar registro local da assinatura
            $userSubscription = new UserSubscription($this->conn);
            $userSubscription->user_id = $userId;
            $userSubscription->plan_id = $subscriptionPlan->id;
            $userSubscription->status = 'active';
            $userSubscription->starts_at = date('Y-m-d');
            $userSubscription->ends_at = $subscriptionPlan->billing_period === 'yearly' 
                ? date('Y-m-d', strtotime('+1 year'))
                : date('Y-m-d', strtotime('+1 month'));
            $userSubscription->payment_method = 'credit_card';
            $userSubscription->payment_reference = $subscription['data']['id'];
            $userSubscription->auto_renew = 1;

            if (!$userSubscription->create()) {
                // Tentar cancelar a assinatura no Mercado Pago se não conseguir salvar localmente
                $this->mercadoPago->cancelSubscription($subscription['data']['id']);
                
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao salvar assinatura'
                ]);
                return;
            }

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'data' => [
                    'subscription_id' => $userSubscription->id,
                    'mp_subscription_id' => $subscription['data']['id'],
                    'status' => 'active',
                    'next_payment_date' => $subscription['data']['next_payment_date'] ?? null
                ]
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Cancelar assinatura via Mercado Pago
     */
    public function cancelMercadoPagoSubscription() {
        try {
            $authResult = $this->authMiddleware->authenticate();
            $userId = $authResult->user_id;
            
            // Buscar assinatura ativa do usuário
            $userSubscription = new UserSubscription($this->conn);
            $activeSubscription = $userSubscription->getActiveSubscription($userId);
            
            if (!$activeSubscription || !$activeSubscription['payment_reference']) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Assinatura não encontrada'
                ]);
                return;
            }

            // Cancelar no Mercado Pago se houver referência
            if ($activeSubscription['payment_reference'] && $activeSubscription['auto_renew']) {
                $cancelResult = $this->mercadoPago->cancelSubscription($activeSubscription['payment_reference']);
                
                if (!$cancelResult['success']) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Erro ao cancelar no Mercado Pago: ' . $cancelResult['message']
                    ]);
                    return;
                }
            }

            // Cancelar localmente
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
                    'message' => 'Erro ao cancelar assinatura localmente'
                ]);
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Verificar status de pagamento
     */
    public function checkPaymentStatus($paymentId) {
        try {
            $authResult = $this->authMiddleware->authenticate();
            
            // Buscar pagamento no banco local
            $query = "SELECT us.*, sp.name as plan_name 
                      FROM user_subscriptions us 
                      INNER JOIN subscription_plans sp ON us.plan_id = sp.id 
                      WHERE us.payment_reference = ? AND us.user_id = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(1, $paymentId);
            $stmt->bindValue(2, $authResult->user_id);
            $stmt->execute();
            
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$subscription) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Pagamento não encontrado'
                ]);
                return;
            }

            // Se ainda está pendente, verificar no Mercado Pago
            if ($subscription['status'] === 'pending') {
                // Verificar status do pagamento no Mercado Pago
                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL => "https://api.mercadopago.com/v1/payments/{$paymentId}",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $this->mercadoPago->getAccessToken(),
                        'Content-Type: application/json'
                    ]
                ]);
                
                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);
                
                if ($httpCode === 200) {
                    $payment = json_decode($response, true);
                    $newStatus = $this->mercadoPago->convertPaymentStatus($payment['status']);
                    
                    // Atualizar status local se mudou
                    if ($newStatus !== 'pending') {
                        $updateQuery = "UPDATE user_subscriptions SET status = ? WHERE id = ?";
                        $updateStmt = $this->conn->prepare($updateQuery);
                        $updateStmt->bindValue(1, $newStatus === 'completed' ? 'active' : $newStatus);
                        $updateStmt->bindValue(2, $subscription['id']);
                        $updateStmt->execute();
                        
                        $subscription['status'] = $newStatus === 'completed' ? 'active' : $newStatus;
                    }
                }
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => [
                    'subscription_id' => $subscription['id'],
                    'status' => $subscription['status'],
                    'plan_name' => $subscription['plan_name'],
                    'starts_at' => $subscription['starts_at'],
                    'ends_at' => $subscription['ends_at']
                ]
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno: ' . $e->getMessage()
            ]);
        }
    }
}