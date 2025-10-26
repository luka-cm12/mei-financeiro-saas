<?php

/**
 * CRON Job para notificações automáticas
 * 
 * Configurar no crontab para executar diariamente:
 * 0 9 * * * /usr/bin/php /path/to/your/project/api/cron/notifications.php
 * 
 * Ou a cada 6 horas:
 * 0 6,12,18,0 * * * /usr/bin/php /path/to/your/project/api/cron/notifications.php
 */

// Definir como execução CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Este script deve ser executado via linha de comando');
}

// Incluir dependências
require_once __DIR__ . '/../services/NotificationService.php';

echo "=== CRON Job de Notificações - " . date('Y-m-d H:i:s') . " ===\n";

try {
    $notificationService = new NotificationService();
    
    echo "Iniciando verificação de notificações...\n";
    
    // Executar todas as notificações
    $results = $notificationService->runAllNotifications();
    
    // Exibir resultados
    echo "\n--- Resultados ---\n";
    
    // Trial notifications
    $trialResults = $results['trial_notifications'];
    echo "Trial Notifications: {$trialResults['sent']} enviadas\n";
    if (!empty($trialResults['errors'])) {
        echo "Erros Trial: " . implode(', ', $trialResults['errors']) . "\n";
    }
    
    // Renewal notifications
    $renewalResults = $results['renewal_notifications'];
    echo "Renewal Notifications: {$renewalResults['sent']} enviadas\n";
    if (!empty($renewalResults['errors'])) {
        echo "Erros Renewal: " . implode(', ', $renewalResults['errors']) . "\n";
    }
    
    // Payment failure notifications
    $paymentResults = $results['payment_failure_notifications'];
    echo "Payment Failure Notifications: {$paymentResults['sent']} enviadas\n";
    if (!empty($paymentResults['errors'])) {
        echo "Erros Payment: " . implode(', ', $paymentResults['errors']) . "\n";
    }
    
    $totalSent = $trialResults['sent'] + $renewalResults['sent'] + $paymentResults['sent'];
    echo "\nTotal de notificações enviadas: {$totalSent}\n";
    
    echo "=== Execução concluída com sucesso ===\n";

} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "=== Execução falhou ===\n";
    exit(1);
}

exit(0);
?>