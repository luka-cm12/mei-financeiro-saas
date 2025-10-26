<?php

class MercadoPagoPayment {
    private $accessToken;
    private $baseUrl;

    public function __construct() {
        // Configure com suas credenciais do Mercado Pago
        // Em produção, use variáveis de ambiente
        $this->accessToken = $_ENV['MP_ACCESS_TOKEN'] ?? 'TEST-5916339579313082-102617-5a7e3b1c7e6b0b0d0f0f0f0f-123456789';
        $this->baseUrl = 'https://api.mercadopago.com';
    }

    /**
     * Obter access token
     */
    public function getAccessToken() {
        return $this->accessToken;
    }

    /**
     * Criar preferência de pagamento
     */
    public function createPaymentPreference($data) {
        $preference = [
            'items' => [
                [
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'quantity' => 1,
                    'unit_price' => (float)$data['amount'],
                    'currency_id' => 'BRL'
                ]
            ],
            'payer' => [
                'email' => $data['payer_email'],
                'name' => $data['payer_name'] ?? '',
                'identification' => [
                    'type' => 'CPF',
                    'number' => $data['payer_document'] ?? ''
                ]
            ],
            'payment_methods' => [
                'excluded_payment_methods' => [],
                'excluded_payment_types' => [],
                'installments' => 12
            ],
            'back_urls' => [
                'success' => $data['success_url'] ?? 'https://seudominio.com/success',
                'failure' => $data['failure_url'] ?? 'https://seudominio.com/failure',
                'pending' => $data['pending_url'] ?? 'https://seudominio.com/pending'
            ],
            'auto_return' => 'approved',
            'external_reference' => $data['external_reference'],
            'notification_url' => $data['notification_url'] ?? 'https://seudominio.com/webhook/mercadopago',
            'expires' => true,
            'expiration_date_from' => date('c'),
            'expiration_date_to' => date('c', strtotime('+1 day'))
        ];

        return $this->makeRequest('POST', '/checkout/preferences', $preference);
    }

    /**
     * Criar pagamento PIX
     */
    public function createPixPayment($data) {
        $payment = [
            'transaction_amount' => (float)$data['amount'],
            'description' => $data['description'],
            'payment_method_id' => 'pix',
            'payer' => [
                'email' => $data['payer_email'],
                'first_name' => $data['payer_name'] ?? '',
                'identification' => [
                    'type' => 'CPF',
                    'number' => $data['payer_document'] ?? ''
                ]
            ],
            'notification_url' => $data['notification_url'] ?? '',
            'external_reference' => $data['external_reference']
        ];

        return $this->makeRequest('POST', '/v1/payments', $payment);
    }

    /**
     * Criar assinatura recorrente
     */
    public function createSubscription($data) {
        // Primeiro, criar um plano de assinatura
        $plan = [
            'reason' => $data['plan_name'],
            'auto_recurring' => [
                'frequency' => $data['frequency'], // 1 (mensal) ou 12 (anual)
                'frequency_type' => 'months',
                'transaction_amount' => (float)$data['amount'],
                'currency_id' => 'BRL'
            ],
            'payment_methods_allowed' => [
                'payment_types' => [
                    ['id' => 'credit_card'],
                    ['id' => 'debit_card']
                ],
                'payment_methods' => []
            ],
            'back_url' => $data['success_url'] ?? 'https://seudominio.com/success'
        ];

        $planResponse = $this->makeRequest('POST', '/preapproval_plan', $plan);
        
        if (!$planResponse['success']) {
            return $planResponse;
        }

        // Depois, criar a assinatura baseada no plano
        $subscription = [
            'reason' => $data['subscription_title'],
            'external_reference' => $data['external_reference'],
            'payer_email' => $data['payer_email'],
            'preapproval_plan_id' => $planResponse['data']['id'],
            'card_token_id' => $data['card_token'] ?? null,
            'auto_recurring' => [
                'frequency' => $data['frequency'],
                'frequency_type' => 'months',
                'start_date' => date('c'),
                'end_date' => date('c', strtotime('+1 year'))
            ],
            'back_url' => $data['success_url'] ?? 'https://seudominio.com/success',
            'status' => 'authorized'
        ];

        return $this->makeRequest('POST', '/preapproval', $subscription);
    }

    /**
     * Processar webhook de notificação
     */
    public function processWebhook($notificationData) {
        $topic = $notificationData['topic'] ?? null;
        $id = $notificationData['id'] ?? null;

        if (!$topic || !$id) {
            return ['success' => false, 'message' => 'Dados de notificação inválidos'];
        }

        switch ($topic) {
            case 'payment':
                return $this->processPaymentNotification($id);
            
            case 'preapproval':
                return $this->processSubscriptionNotification($id);
            
            default:
                return ['success' => false, 'message' => 'Tópico não suportado'];
        }
    }

    /**
     * Processar notificação de pagamento
     */
    private function processPaymentNotification($paymentId) {
        $response = $this->makeRequest('GET', "/v1/payments/{$paymentId}");
        
        if (!$response['success']) {
            return $response;
        }

        $payment = $response['data'];
        
        return [
            'success' => true,
            'payment' => [
                'id' => $payment['id'],
                'status' => $payment['status'],
                'status_detail' => $payment['status_detail'],
                'amount' => $payment['transaction_amount'],
                'external_reference' => $payment['external_reference'],
                'payer_email' => $payment['payer']['email'] ?? null,
                'payment_method' => $payment['payment_method_id'],
                'date_created' => $payment['date_created'],
                'date_approved' => $payment['date_approved'] ?? null
            ]
        ];
    }

    /**
     * Processar notificação de assinatura
     */
    private function processSubscriptionNotification($subscriptionId) {
        $response = $this->makeRequest('GET', "/preapproval/{$subscriptionId}");
        
        if (!$response['success']) {
            return $response;
        }

        $subscription = $response['data'];
        
        return [
            'success' => true,
            'subscription' => [
                'id' => $subscription['id'],
                'status' => $subscription['status'],
                'external_reference' => $subscription['external_reference'],
                'payer_email' => $subscription['payer_email'],
                'start_date' => $subscription['auto_recurring']['start_date'] ?? null,
                'end_date' => $subscription['auto_recurring']['end_date'] ?? null,
                'next_payment_date' => $subscription['next_payment_date'] ?? null
            ]
        ];
    }

    /**
     * Cancelar assinatura
     */
    public function cancelSubscription($subscriptionId) {
        $data = ['status' => 'cancelled'];
        return $this->makeRequest('PUT', "/preapproval/{$subscriptionId}", $data);
    }

    /**
     * Pausar assinatura
     */
    public function pauseSubscription($subscriptionId) {
        $data = ['status' => 'paused'];
        return $this->makeRequest('PUT', "/preapproval/{$subscriptionId}", $data);
    }

    /**
     * Reativar assinatura
     */
    public function reactivateSubscription($subscriptionId) {
        $data = ['status' => 'authorized'];
        return $this->makeRequest('PUT', "/preapproval/{$subscriptionId}", $data);
    }

    /**
     * Obter detalhes de uma assinatura
     */
    public function getSubscriptionDetails($subscriptionId) {
        return $this->makeRequest('GET', "/preapproval/{$subscriptionId}");
    }

    /**
     * Fazer requisição para API do Mercado Pago
     */
    private function makeRequest($method, $endpoint, $data = null) {
        $url = $this->baseUrl . $endpoint;
        
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'X-Idempotency-Key: ' . uniqid()
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'message' => 'Erro de conexão: ' . $error
            ];
        }

        $responseData = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $responseData,
                'http_code' => $httpCode
            ];
        } else {
            return [
                'success' => false,
                'message' => $responseData['message'] ?? 'Erro na API do Mercado Pago',
                'error' => $responseData,
                'http_code' => $httpCode
            ];
        }
    }

    /**
     * Validar assinatura do webhook
     */
    public function validateWebhookSignature($payload, $signature) {
        // Implementar validação de assinatura para segurança
        $secret = $_ENV['MP_WEBHOOK_SECRET'] ?? '';
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Converter status do Mercado Pago para status interno
     */
    public function convertPaymentStatus($mpStatus) {
        $statusMap = [
            'approved' => 'completed',
            'pending' => 'pending',
            'in_process' => 'pending',
            'in_mediation' => 'pending',
            'rejected' => 'failed',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'charged_back' => 'refunded'
        ];

        return $statusMap[$mpStatus] ?? 'pending';
    }

    /**
     * Converter status de assinatura do Mercado Pago
     */
    public function convertSubscriptionStatus($mpStatus) {
        $statusMap = [
            'authorized' => 'active',
            'paused' => 'paused',
            'cancelled' => 'cancelled',
            'finished' => 'expired'
        ];

        return $statusMap[$mpStatus] ?? 'pending';
    }
}