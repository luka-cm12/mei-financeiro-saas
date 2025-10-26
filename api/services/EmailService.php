<?php

class EmailService {
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $fromEmail;
    private $fromName;

    public function __construct() {
        // Configuração SMTP - em produção usar variáveis de ambiente
        $this->smtpHost = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $this->smtpPort = $_ENV['SMTP_PORT'] ?? 587;
        $this->smtpUsername = $_ENV['SMTP_USERNAME'] ?? 'noreply@meifinanceiro.com';
        $this->smtpPassword = $_ENV['SMTP_PASSWORD'] ?? 'sua_senha_app';
        $this->fromEmail = $_ENV['FROM_EMAIL'] ?? 'noreply@meifinanceiro.com';
        $this->fromName = $_ENV['FROM_NAME'] ?? 'MEI Financeiro';
    }

    /**
     * Enviar email usando PHP mail() ou SMTP
     */
    public function sendEmail($to, $subject, $htmlBody, $textBody = null) {
        try {
            // Se não tiver corpo texto, extrair do HTML
            if ($textBody === null) {
                $textBody = strip_tags($htmlBody);
            }

            // Headers do email
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
                'Reply-To: ' . $this->fromEmail,
                'X-Mailer: PHP/' . phpversion(),
                'X-Priority: 3',
            ];

            // Tentar enviar usando mail() primeiro (mais simples)
            if (function_exists('mail') && mail($to, $subject, $htmlBody, implode("\r\n", $headers))) {
                return [
                    'success' => true,
                    'message' => 'Email enviado com sucesso',
                    'method' => 'php_mail'
                ];
            }

            // Se mail() falhar, tentar SMTP manual
            return $this->sendViaSMTP($to, $subject, $htmlBody, $textBody);

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao enviar email: ' . $e->getMessage(),
                'method' => 'error'
            ];
        }
    }

    /**
     * Enviar via SMTP manual (fallback)
     */
    private function sendViaSMTP($to, $subject, $htmlBody, $textBody) {
        try {
            // Conectar ao servidor SMTP
            $socket = fsockopen($this->smtpHost, $this->smtpPort, $errno, $errstr, 30);
            
            if (!$socket) {
                throw new Exception("Não foi possível conectar ao SMTP: $errstr ($errno)");
            }

            // Função para ler resposta
            $read = function() use ($socket) {
                return fgets($socket, 515);
            };

            // Função para enviar comando
            $send = function($cmd) use ($socket) {
                fwrite($socket, $cmd . "\r\n");
            };

            // Protocolo SMTP básico
            $read(); // Banner inicial
            
            $send("EHLO " . $_SERVER['HTTP_HOST'] ?? 'localhost');
            $read();
            
            $send("STARTTLS");
            $read();
            
            // Reabrir conexão com TLS (simplificado)
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            
            $send("EHLO " . $_SERVER['HTTP_HOST'] ?? 'localhost');
            $read();
            
            $send("AUTH LOGIN");
            $read();
            
            $send(base64_encode($this->smtpUsername));
            $read();
            
            $send(base64_encode($this->smtpPassword));
            $read();
            
            $send("MAIL FROM: <{$this->fromEmail}>");
            $read();
            
            $send("RCPT TO: <$to>");
            $read();
            
            $send("DATA");
            $read();
            
            // Cabeçalhos e corpo
            $emailContent = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
            $emailContent .= "To: $to\r\n";
            $emailContent .= "Subject: $subject\r\n";
            $emailContent .= "MIME-Version: 1.0\r\n";
            $emailContent .= "Content-Type: text/html; charset=UTF-8\r\n";
            $emailContent .= "\r\n";
            $emailContent .= $htmlBody;
            $emailContent .= "\r\n.";
            
            $send($emailContent);
            $read();
            
            $send("QUIT");
            $read();
            
            fclose($socket);

            return [
                'success' => true,
                'message' => 'Email enviado via SMTP',
                'method' => 'smtp'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro SMTP: ' . $e->getMessage(),
                'method' => 'smtp_error'
            ];
        }
    }

    /**
     * Templates de email predefinidos
     */
    public function getEmailTemplate($type, $data = []) {
        switch ($type) {
            case 'trial_expiring_3days':
                return [
                    'subject' => 'Seu trial expira em 3 dias - MEI Financeiro',
                    'html' => $this->getTrialExpiringTemplate($data, 3),
                ];

            case 'trial_expiring_1day':
                return [
                    'subject' => 'Seu trial expira amanhã! - MEI Financeiro',
                    'html' => $this->getTrialExpiringTemplate($data, 1),
                ];

            case 'trial_expired':
                return [
                    'subject' => 'Seu trial expirou - Continue com MEI Financeiro',
                    'html' => $this->getTrialExpiredTemplate($data),
                ];

            case 'subscription_renewal':
                return [
                    'subject' => 'Sua assinatura será renovada em breve',
                    'html' => $this->getSubscriptionRenewalTemplate($data),
                ];

            case 'payment_failed':
                return [
                    'subject' => 'Problema com seu pagamento - MEI Financeiro',
                    'html' => $this->getPaymentFailedTemplate($data),
                ];

            default:
                return [
                    'subject' => 'MEI Financeiro - Notificação',
                    'html' => '<p>Você recebeu uma notificação do MEI Financeiro.</p>',
                ];
        }
    }

    /**
     * Template para trial expirando
     */
    private function getTrialExpiringTemplate($data, $days) {
        $userName = $data['user_name'] ?? 'Usuário';
        $planName = $data['plan_name'] ?? 'Premium';
        $upgradeUrl = $data['upgrade_url'] ?? 'https://meifinanceiro.com/upgrade';

        $daysText = $days === 1 ? 'amanhã' : "em {$days} dias";

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Trial Expirando</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='color: white; margin: 0; font-size: 28px;'>⏰ Seu trial expira {$daysText}!</h1>
            </div>
            
            <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e9ecef;'>
                <p style='font-size: 18px; margin-bottom: 20px;'>Olá, <strong>{$userName}</strong>!</p>
                
                <p style='font-size: 16px; margin-bottom: 25px;'>
                    Esperamos que esteja aproveitando todas as funcionalidades do <strong>MEI Financeiro</strong>! 
                    Seu período de teste {$daysText} e não queremos que você perca acesso às suas análises financeiras.
                </p>
                
                <div style='background: #fff; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #28a745;'>
                    <h3 style='color: #28a745; margin-top: 0;'>🚀 Continue aproveitando:</h3>
                    <ul style='margin: 15px 0; padding-left: 20px;'>
                        <li>Controle completo de receitas e despesas</li>
                        <li>Relatórios avançados e gráficos</li>
                        <li>Metas financeiras personalizadas</li>
                        <li>Backup automático dos seus dados</li>
                        <li>Suporte técnico prioritário</li>
                    </ul>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$upgradeUrl}' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 18px; font-weight: bold; display: inline-block;'>
                        Assinar Agora - R$ 19,90/mês
                    </a>
                </div>
                
                <div style='background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <p style='margin: 0; font-size: 14px; color: #1976d2;'>
                        <strong>💝 Oferta especial:</strong> Use o cupom <code>TRIAL30</code> e ganhe 30% de desconto no primeiro mês!
                    </p>
                </div>
                
                <hr style='margin: 25px 0; border: none; border-top: 1px solid #ddd;'>
                
                <p style='font-size: 14px; color: #666; text-align: center; margin: 0;'>
                    Dúvidas? Entre em contato: <a href='mailto:suporte@meifinanceiro.com'>suporte@meifinanceiro.com</a><br>
                    <a href='#' style='color: #666; text-decoration: none;'>Descadastrar</a>
                </p>
            </div>
        </body>
        </html>";
    }

    /**
     * Template para trial expirado
     */
    private function getTrialExpiredTemplate($data) {
        $userName = $data['user_name'] ?? 'Usuário';
        $upgradeUrl = $data['upgrade_url'] ?? 'https://meifinanceiro.com/upgrade';

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Trial Expirado</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='color: white; margin: 0; font-size: 28px;'>😢 Seu trial expirou</h1>
            </div>
            
            <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e9ecef;'>
                <p style='font-size: 18px; margin-bottom: 20px;'>Olá, <strong>{$userName}</strong>!</p>
                
                <p style='font-size: 16px; margin-bottom: 25px;'>
                    Seu período de teste do MEI Financeiro expirou, mas não se preocupe! 
                    Todos os seus dados estão seguros e você pode reativar sua conta a qualquer momento.
                </p>
                
                <div style='background: #fff3cd; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #ffc107;'>
                    <h3 style='color: #856404; margin-top: 0;'>⚠️ Funcionalidades limitadas</h3>
                    <p style='margin: 0; color: #856404;'>
                        Com o trial expirado, o acesso às funcionalidades premium está temporariamente suspenso. 
                        Assine agora para continuar aproveitando todos os benefícios!
                    </p>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$upgradeUrl}' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 18px; font-weight: bold; display: inline-block;'>
                        Reativar Agora - R$ 19,90/mês
                    </a>
                </div>
                
                <div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <p style='margin: 0; font-size: 14px; color: #0c5460;'>
                        <strong>🎁 Última chance:</strong> Use o cupom <code>VOLTA50</code> e ganhe 50% de desconto no primeiro mês!
                    </p>
                </div>
                
                <hr style='margin: 25px 0; border: none; border-top: 1px solid #ddd;'>
                
                <p style='font-size: 14px; color: #666; text-align: center; margin: 0;'>
                    Dúvidas? Entre em contato: <a href='mailto:suporte@meifinanceiro.com'>suporte@meifinanceiro.com</a><br>
                    <a href='#' style='color: #666; text-decoration: none;'>Descadastrar</a>
                </p>
            </div>
        </body>
        </html>";
    }

    /**
     * Template para renovação de assinatura
     */
    private function getSubscriptionRenewalTemplate($data) {
        $userName = $data['user_name'] ?? 'Usuário';
        $renewalDate = $data['renewal_date'] ?? date('d/m/Y');
        $amount = $data['amount'] ?? 'R$ 19,90';

        return "
        <!DOCTYPE html>
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='color: white; margin: 0;'>🔄 Renovação Automática</h1>
            </div>
            
            <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e9ecef;'>
                <p>Olá, <strong>{$userName}</strong>!</p>
                <p>Sua assinatura será renovada automaticamente em <strong>{$renewalDate}</strong> no valor de <strong>{$amount}</strong>.</p>
                <p>Obrigado por continuar conosco! 💚</p>
            </div>
        </body>
        </html>";
    }

    /**
     * Template para falha no pagamento
     */
    private function getPaymentFailedTemplate($data) {
        $userName = $data['user_name'] ?? 'Usuário';
        $updateUrl = $data['update_payment_url'] ?? 'https://meifinanceiro.com/payment';

        return "
        <!DOCTYPE html>
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='color: white; margin: 0;'>⚠️ Problema no Pagamento</h1>
            </div>
            
            <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e9ecef;'>
                <p>Olá, <strong>{$userName}</strong>!</p>
                <p>Tivemos um problema ao processar o pagamento da sua assinatura. Para continuar aproveitando todos os recursos, atualize suas informações de pagamento.</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$updateUrl}' style='background: #dc3545; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                        Atualizar Pagamento
                    </a>
                </div>
            </div>
        </body>
        </html>";
    }
}
?>