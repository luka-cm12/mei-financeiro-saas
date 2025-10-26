<?php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/EmailService.php';

class NotificationService {
    private $db;
    private $conn;
    private $emailService;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
        $this->emailService = new EmailService();
    }

    /**
     * Verificar e enviar notificações de trial expirando
     */
    public function checkTrialExpirations() {
        $notifications = [
            'sent' => 0,
            'errors' => []
        ];

        try {
            // Buscar usuários com trial expirando em 3 dias
            $this->processTrialNotifications(3, 'trial_expiring_3days', $notifications);
            
            // Buscar usuários com trial expirando em 1 dia
            $this->processTrialNotifications(1, 'trial_expiring_1day', $notifications);
            
            // Buscar usuários com trial expirado hoje
            $this->processTrialNotifications(0, 'trial_expired', $notifications);

            return $notifications;

        } catch (Exception $e) {
            $notifications['errors'][] = 'Erro geral: ' . $e->getMessage();
            return $notifications;
        }
    }

    /**
     * Processar notificações por período
     */
    private function processTrialNotifications($days, $notificationType, &$notifications) {
        // Query baseada no período
        if ($days > 0) {
            // Trial expirando em X dias
            $query = "SELECT u.id, u.name, u.email, us.trial_ends_at, sp.name as plan_name
                      FROM users u
                      INNER JOIN user_subscriptions us ON u.id = us.user_id
                      INNER JOIN subscription_plans sp ON us.plan_id = sp.id
                      WHERE us.status = 'trial' 
                        AND DATE(us.trial_ends_at) = DATE_ADD(CURDATE(), INTERVAL ? DAY)
                        AND u.email IS NOT NULL
                        AND u.email != ''";
        } else {
            // Trial expirado hoje
            $query = "SELECT u.id, u.name, u.email, us.trial_ends_at, sp.name as plan_name
                      FROM users u
                      INNER JOIN user_subscriptions us ON u.id = us.user_id
                      INNER JOIN subscription_plans sp ON us.plan_id = sp.id
                      WHERE us.status = 'trial' 
                        AND DATE(us.trial_ends_at) = CURDATE()
                        AND u.email IS NOT NULL
                        AND u.email != ''";
        }

        $stmt = $this->conn->prepare($query);
        
        if ($days > 0) {
            $stmt->bindValue(1, $days);
        }
        
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as $user) {
            // Verificar se já enviou notificação hoje para este usuário e tipo
            if ($this->hasNotificationSentToday($user['id'], $notificationType)) {
                continue;
            }

            // Preparar dados para o template
            $templateData = [
                'user_name' => $user['name'],
                'plan_name' => $user['plan_name'],
                'upgrade_url' => 'https://meifinanceiro.com/upgrade?user=' . $user['id']
            ];

            // Obter template do email
            $template = $this->emailService->getEmailTemplate($notificationType, $templateData);

            // Enviar email
            $result = $this->emailService->sendEmail(
                $user['email'],
                $template['subject'],
                $template['html']
            );

            if ($result['success']) {
                // Registrar notificação enviada
                $this->recordNotification($user['id'], $notificationType, 'email', $user['email']);
                $notifications['sent']++;
            } else {
                $notifications['errors'][] = "Erro ao enviar para {$user['email']}: {$result['message']}";
            }
        }
    }

    /**
     * Verificar se já foi enviada notificação hoje
     */
    private function hasNotificationSentToday($userId, $type) {
        $query = "SELECT COUNT(*) as count FROM notifications_log 
                  WHERE user_id = ? 
                    AND notification_type = ? 
                    AND DATE(sent_at) = CURDATE()";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $userId);
        $stmt->bindValue(2, $type);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    /**
     * Registrar notificação enviada
     */
    private function recordNotification($userId, $type, $channel, $recipient) {
        $query = "INSERT INTO notifications_log (user_id, notification_type, channel, recipient, sent_at, status)
                  VALUES (?, ?, ?, ?, NOW(), 'sent')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $userId);
        $stmt->bindValue(2, $type);
        $stmt->bindValue(3, $channel);
        $stmt->bindValue(4, $recipient);
        
        return $stmt->execute();
    }

    /**
     * Verificar assinaturas próximas ao vencimento
     */
    public function checkSubscriptionRenewals() {
        $notifications = [
            'sent' => 0,
            'errors' => []
        ];

        try {
            // Buscar assinaturas que vencem em 3 dias
            $query = "SELECT u.id, u.name, u.email, us.ends_at, sp.name as plan_name, sp.price
                      FROM users u
                      INNER JOIN user_subscriptions us ON u.id = us.user_id
                      INNER JOIN subscription_plans sp ON us.plan_id = sp.id
                      WHERE us.status = 'active' 
                        AND us.auto_renew = 1
                        AND DATE(us.ends_at) = DATE_ADD(CURDATE(), INTERVAL 3 DAY)
                        AND u.email IS NOT NULL
                        AND u.email != ''";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($users as $user) {
                // Verificar se já enviou notificação
                if ($this->hasNotificationSentToday($user['id'], 'subscription_renewal')) {
                    continue;
                }

                $templateData = [
                    'user_name' => $user['name'],
                    'plan_name' => $user['plan_name'],
                    'renewal_date' => date('d/m/Y', strtotime($user['ends_at'])),
                    'amount' => 'R$ ' . number_format($user['price'], 2, ',', '.')
                ];

                $template = $this->emailService->getEmailTemplate('subscription_renewal', $templateData);

                $result = $this->emailService->sendEmail(
                    $user['email'],
                    $template['subject'],
                    $template['html']
                );

                if ($result['success']) {
                    $this->recordNotification($user['id'], 'subscription_renewal', 'email', $user['email']);
                    $notifications['sent']++;
                } else {
                    $notifications['errors'][] = "Erro ao enviar para {$user['email']}: {$result['message']}";
                }
            }

            return $notifications;

        } catch (Exception $e) {
            $notifications['errors'][] = 'Erro geral: ' . $e->getMessage();
            return $notifications;
        }
    }

    /**
     * Notificar sobre falhas de pagamento
     */
    public function notifyPaymentFailures() {
        $notifications = [
            'sent' => 0,
            'errors' => []
        ];

        try {
            // Buscar assinaturas com pagamento falhado
            $query = "SELECT u.id, u.name, u.email, us.status, sp.name as plan_name
                      FROM users u
                      INNER JOIN user_subscriptions us ON u.id = us.user_id
                      INNER JOIN subscription_plans sp ON us.plan_id = sp.id
                      WHERE us.status = 'payment_failed'
                        AND DATE(us.updated_at) = CURDATE()
                        AND u.email IS NOT NULL
                        AND u.email != ''";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($users as $user) {
                // Verificar se já enviou notificação
                if ($this->hasNotificationSentToday($user['id'], 'payment_failed')) {
                    continue;
                }

                $templateData = [
                    'user_name' => $user['name'],
                    'plan_name' => $user['plan_name'],
                    'update_payment_url' => 'https://meifinanceiro.com/payment?user=' . $user['id']
                ];

                $template = $this->emailService->getEmailTemplate('payment_failed', $templateData);

                $result = $this->emailService->sendEmail(
                    $user['email'],
                    $template['subject'],
                    $template['html']
                );

                if ($result['success']) {
                    $this->recordNotification($user['id'], 'payment_failed', 'email', $user['email']);
                    $notifications['sent']++;
                } else {
                    $notifications['errors'][] = "Erro ao enviar para {$user['email']}: {$result['message']}";
                }
            }

            return $notifications;

        } catch (Exception $e) {
            $notifications['errors'][] = 'Erro geral: ' . $e->getMessage();
            return $notifications;
        }
    }

    /**
     * Executar todas as verificações de notificação
     */
    public function runAllNotifications() {
        $results = [
            'trial_notifications' => $this->checkTrialExpirations(),
            'renewal_notifications' => $this->checkSubscriptionRenewals(),
            'payment_failure_notifications' => $this->notifyPaymentFailures(),
            'executed_at' => date('Y-m-d H:i:s')
        ];

        // Log do resultado
        $logData = json_encode($results);
        error_log("Notifications executed: " . $logData);

        return $results;
    }
}
?>