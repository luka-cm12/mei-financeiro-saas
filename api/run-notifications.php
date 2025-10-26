<?php

require_once __DIR__ . '/services/NotificationService.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Verificar autenticação de admin (opcional, pode remover se quiser que seja público)
    $authMiddleware = new AuthMiddleware();
    $authResult = $authMiddleware->authenticate();
    
    if (!isset($authResult->is_admin) || !$authResult->is_admin) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Acesso negado: apenas administradores'
        ]);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Método não permitido'
        ]);
        exit();
    }

    $notificationService = new NotificationService();
    
    // Executar todas as notificações
    $results = $notificationService->runAllNotifications();
    
    $totalSent = $results['trial_notifications']['sent'] + 
                 $results['renewal_notifications']['sent'] + 
                 $results['payment_failure_notifications']['sent'];
    
    $allErrors = array_merge(
        $results['trial_notifications']['errors'],
        $results['renewal_notifications']['errors'],
        $results['payment_failure_notifications']['errors']
    );

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => "Processamento concluído. {$totalSent} notificações enviadas.",
        'data' => [
            'total_sent' => $totalSent,
            'trial_sent' => $results['trial_notifications']['sent'],
            'renewal_sent' => $results['renewal_notifications']['sent'],
            'payment_failure_sent' => $results['payment_failure_notifications']['sent'],
            'errors' => $allErrors,
            'executed_at' => $results['executed_at']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>