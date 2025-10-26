<?php

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/models/UserSubscription.php';
require_once __DIR__ . '/integrations/MercadoPagoPayment.php';

header("Content-Type: application/json; charset=UTF-8");

// Log para debug (remover em produção)
$logFile = __DIR__ . '/logs/webhook.log';
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => getallheaders(),
    'body' => file_get_contents("php://input"),
    'get' => $_GET,
    'post' => $_POST
];
file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND | LOCK_EX);

try {
    $db = new Database();
    $conn = $db->getConnection();
    $mercadoPago = new MercadoPagoPayment();

    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
        exit;
    }

    // Obter dados do webhook
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit;
    }

    // Validar assinatura do webhook (recomendado em produção)
    $headers = getallheaders();
    $signature = $headers['X-Signature'] ?? '';
    
    if (!empty($signature)) {
        if (!$mercadoPago->validateWebhookSignature($input, $signature)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Assinatura inválida']);
            exit;
        }
    }

    // Processar webhook
    $result = $mercadoPago->processWebhook($data);

    if (!$result['success']) {
        http_response_code(400);
        echo json_encode($result);
        exit;
    }

    $webhookData = $result['data'];
    $paymentId = $webhookData['payment_id'];
    $status = $webhookData['status'];

    // Buscar assinatura relacionada
    $query = "SELECT us.*, sp.name as plan_name 
              FROM user_subscriptions us 
              INNER JOIN subscription_plans sp ON us.plan_id = sp.id 
              WHERE us.payment_reference = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bindValue(1, $paymentId);
    $stmt->execute();
    
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        // Log que não encontrou a assinatura
        error_log("Webhook: Assinatura não encontrada para payment_id: {$paymentId}");
        
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Assinatura não encontrada']);
        exit;
    }

    // Converter status do Mercado Pago para nosso sistema
    $newStatus = $mercadoPago->convertPaymentStatus($status);
    $dbStatus = $newStatus === 'completed' ? 'active' : $newStatus;

    // Atualizar apenas se o status mudou
    if ($subscription['status'] !== $dbStatus) {
        $updateQuery = "UPDATE user_subscriptions SET 
                        status = ?, 
                        updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?";
        
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bindValue(1, $dbStatus);
        $updateStmt->bindValue(2, $subscription['id']);
        
        if ($updateStmt->execute()) {
            // Log da atualização
            error_log("Webhook: Status atualizado - Subscription ID: {$subscription['id']}, Status: {$subscription['status']} -> {$dbStatus}");

            // Se foi ativado, atualizar data de início
            if ($dbStatus === 'active' && $subscription['status'] === 'pending') {
                $activateQuery = "UPDATE user_subscriptions SET 
                                  starts_at = CURRENT_DATE,
                                  ends_at = DATE_ADD(CURRENT_DATE, INTERVAL 1 MONTH)
                                  WHERE id = ?";
                
                $activateStmt = $conn->prepare($activateQuery);
                $activateStmt->bindValue(1, $subscription['id']);
                $activateStmt->execute();
            }
            
            // Resposta de sucesso
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Webhook processado com sucesso',
                'subscription_id' => $subscription['id'],
                'old_status' => $subscription['status'],
                'new_status' => $dbStatus
            ]);
        } else {
            error_log("Webhook: Erro ao atualizar subscription ID: {$subscription['id']}");
            
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar assinatura']);
        }
    } else {
        // Status já está atualizado
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Status já está atualizado',
            'subscription_id' => $subscription['id'],
            'status' => $dbStatus
        ]);
    }

} catch (Exception $e) {
    error_log("Webhook Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor'
    ]);
}
?>