<?php
/**
 * Controlador de assinatura
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Subscription.php';

class SubscriptionController {
    private $db;
    private $auth;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auth = new AuthMiddleware();
    }
    
    public function getStatus() {
        $auth_data = $this->auth->authenticate();
        
        try {
            $user = new User($this->db);
            $subscription_data = $user->getSubscriptionStatus($auth_data->user_id);
            
            if (!$subscription_data) {
                http_response_code(404);
                echo json_encode(["message" => "Dados de assinatura não encontrados"]);
                return;
            }
            
            $is_active = false;
            $days_remaining = 0;
            
            if ($subscription_data['subscription_expires_at']) {
                $expires_at = new DateTime($subscription_data['subscription_expires_at']);
                $now = new DateTime();
                
                if ($expires_at > $now) {
                    $is_active = true;
                    $days_remaining = $expires_at->diff($now)->days;
                }
            }
            
            echo json_encode([
                "status" => $subscription_data['subscription_status'],
                "expires_at" => $subscription_data['subscription_expires_at'],
                "is_active" => $is_active,
                "days_remaining" => $days_remaining,
                "plan_price" => 19.90
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function createPayment() {
        $auth_data = $this->auth->authenticate();
        $data = json_decode(file_get_contents("php://input"), true);
        
        try {
            // Aqui você integraria com um gateway de pagamento
            // Por exemplo: Mercado Pago, PagSeguro, Stripe, etc.
            
            $subscription = new Subscription($this->db);
            
            // Criar registro de assinatura pendente
            $subscription_id = $subscription->create([
                'user_id' => $auth_data->user_id,
                'plan_name' => 'MEI Financeiro Pro',
                'amount' => 19.90,
                'status' => 'pending',
                'payment_method' => $data['payment_method'] ?? 'credit_card',
                'starts_at' => date('Y-m-d'),
                'expires_at' => date('Y-m-d', strtotime('+1 month'))
            ]);
            
            // Exemplo de integração fictícia com gateway
            $payment_response = $this->processPayment($data);
            
            if ($payment_response['success']) {
                // Atualizar status da assinatura
                $subscription->updateStatus($subscription_id, 'active');
                
                // Atualizar usuário
                $user = new User($this->db);
                $user->updateSubscription(
                    $auth_data->user_id, 
                    'active', 
                    date('Y-m-d H:i:s', strtotime('+1 month'))
                );
                
                echo json_encode([
                    "success" => true,
                    "message" => "Assinatura ativada com sucesso!",
                    "subscription_id" => $subscription_id,
                    "expires_at" => date('Y-m-d H:i:s', strtotime('+1 month'))
                ]);
            } else {
                // Falha no pagamento
                $subscription->updateStatus($subscription_id, 'cancelled');
                
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => $payment_response['error'] ?? "Erro no processamento do pagamento"
                ]);
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function cancelSubscription() {
        $auth_data = $this->auth->authenticate();
        
        try {
            $user = new User($this->db);
            
            // Cancelar na data de expiração atual (não imediatamente)
            $user->updateSubscription($auth_data->user_id, 'cancelled', null);
            
            echo json_encode([
                "message" => "Assinatura cancelada. Você terá acesso até o final do período atual."
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function webhookPayment() {
        // Endpoint para receber notificações do gateway de pagamento
        $data = json_decode(file_get_contents("php://input"), true);
        
        try {
            // Verificar assinatura do webhook (segurança)
            if (!$this->verifyWebhookSignature($data)) {
                http_response_code(401);
                echo json_encode(["message" => "Assinatura inválida"]);
                return;
            }
            
            $subscription = new Subscription($this->db);
            $user = new User($this->db);
            
            switch ($data['event_type']) {
                case 'payment.approved':
                    // Pagamento aprovado
                    $subscription->updateStatus($data['subscription_id'], 'active');
                    $user->updateSubscription(
                        $data['user_id'], 
                        'active', 
                        date('Y-m-d H:i:s', strtotime('+1 month'))
                    );
                    break;
                    
                case 'payment.rejected':
                    // Pagamento rejeitado
                    $subscription->updateStatus($data['subscription_id'], 'cancelled');
                    break;
                    
                case 'subscription.cancelled':
                    // Assinatura cancelada
                    $user->updateSubscription($data['user_id'], 'cancelled', null);
                    break;
            }
            
            echo json_encode(["status" => "processed"]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    private function processPayment($data) {
        // Simulação de processamento de pagamento
        // Em produção, você integraria com um gateway real
        
        // Simular sucesso/falha baseado em dados do cartão (exemplo)
        if (isset($data['card_number']) && $data['card_number'] === '4111111111111111') {
            return [
                'success' => true,
                'transaction_id' => 'txn_' . uniqid(),
                'payment_method' => $data['payment_method']
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Pagamento recusado'
        ];
    }
    
    private function verifyWebhookSignature($data) {
        // Verificação de segurança do webhook
        // Implementar conforme o gateway utilizado
        return true; // Simplificado para exemplo
    }
}
?>