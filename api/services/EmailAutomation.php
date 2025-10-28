<?php
require_once __DIR__ . '/../../autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailAutomation {
    private $db;
    private $mailer;
    private $email_config;
    
    public function __construct($database, $config = null) {
        $this->db = $database;
        $this->email_config = $config ?? $this->getDefaultEmailConfig();
        $this->initMailer();
        $this->createEmailTables();
    }
    
    /**
     * Configuração padrão de email
     */
    private function getDefaultEmailConfig() {
        return [
            'smtp_host' => $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com',
            'smtp_port' => $_ENV['SMTP_PORT'] ?? 587,
            'smtp_security' => $_ENV['SMTP_SECURITY'] ?? PHPMailer::ENCRYPTION_STARTTLS,
            'smtp_auth' => $_ENV['SMTP_AUTH'] ?? true,
            'smtp_username' => $_ENV['SMTP_USERNAME'] ?? '',
            'smtp_password' => $_ENV['SMTP_PASSWORD'] ?? '',
            'from_email' => $_ENV['FROM_EMAIL'] ?? 'noreply@meifinanceiro.com',
            'from_name' => $_ENV['FROM_NAME'] ?? 'MEI Financeiro - Sistema NFCe'
        ];
    }
    
    /**
     * Inicializa PHPMailer
     */
    private function initMailer() {
        $this->mailer = new PHPMailer(true);
        
        // Configuração SMTP
        $this->mailer->isSMTP();
        $this->mailer->Host = $this->email_config['smtp_host'];
        $this->mailer->SMTPAuth = $this->email_config['smtp_auth'];
        $this->mailer->Username = $this->email_config['smtp_username'];
        $this->mailer->Password = $this->email_config['smtp_password'];
        $this->mailer->SMTPSecure = $this->email_config['smtp_security'];
        $this->mailer->Port = $this->email_config['smtp_port'];
        $this->mailer->CharSet = 'UTF-8';
        
        // Remetente padrão
        $this->mailer->setFrom(
            $this->email_config['from_email'], 
            $this->email_config['from_name']
        );
    }
    
    /**
     * Cria tabelas necessárias
     */
    private function createEmailTables() {
        // Tabela de emails enviados
        $query1 = "CREATE TABLE IF NOT EXISTS email_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nfce_id INT NULL,
            establishment_id INT NOT NULL,
            to_email VARCHAR(255) NOT NULL,
            subject VARCHAR(500) NOT NULL,
            body TEXT NOT NULL,
            attachments JSON NULL,
            status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
            sent_at TIMESTAMP NULL,
            error_message TEXT NULL,
            attempts INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (nfce_id) REFERENCES nfce_emissions(id),
            FOREIGN KEY (establishment_id) REFERENCES establishments(id),
            INDEX idx_status (status),
            INDEX idx_nfce (nfce_id)
        )";
        
        // Tabela de templates de email
        $query2 = "CREATE TABLE IF NOT EXISTS email_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            subject VARCHAR(500) NOT NULL,
            body_html TEXT NOT NULL,
            body_text TEXT NOT NULL,
            variables JSON NULL,
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        // Tabela de configurações de email por estabelecimento
        $query3 = "CREATE TABLE IF NOT EXISTS establishment_email_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            establishment_id INT NOT NULL UNIQUE,
            auto_send_nfce BOOLEAN DEFAULT true,
            auto_send_danfe BOOLEAN DEFAULT true,
            copy_to_owner BOOLEAN DEFAULT false,
            owner_email VARCHAR(255) NULL,
            custom_message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (establishment_id) REFERENCES establishments(id)
        )";
        
        $this->db->exec($query1);
        $this->db->exec($query2);
        $this->db->exec($query3);
        
        // Inserir templates padrão
        $this->createDefaultTemplates();
    }
    
    /**
     * Cria templates padrão
     */
    private function createDefaultTemplates() {
        $templates = [
            [
                'name' => 'nfce_customer',
                'subject' => 'Sua NFCe - {{establishment_name}} - Número: {{nfce_number}}',
                'body_html' => $this->getCustomerNFCeHTMLTemplate(),
                'body_text' => $this->getCustomerNFCeTextTemplate(),
                'variables' => json_encode([
                    'establishment_name', 'nfce_number', 'customer_name', 
                    'total_amount', 'emission_date', 'qr_code'
                ])
            ],
            [
                'name' => 'nfce_owner',
                'subject' => 'NFCe Emitida - {{nfce_number}} - {{customer_name}}',
                'body_html' => $this->getOwnerNFCeHTMLTemplate(),
                'body_text' => $this->getOwnerNFCeTextTemplate(),
                'variables' => json_encode([
                    'nfce_number', 'customer_name', 'customer_email',
                    'total_amount', 'emission_date', 'items_count'
                ])
            ]
        ];
        
        foreach ($templates as $template) {
            $this->insertTemplateIfNotExists($template);
        }
    }
    
    /**
     * Envia NFCe por email automaticamente
     */
    public function sendNFCeEmail($nfce_id, $force = false) {
        try {
            // Buscar dados da NFCe
            $nfce_data = $this->getNFCeData($nfce_id);
            if (!$nfce_data) {
                throw new Exception('NFCe não encontrada');
            }
            
            // Verificar configurações de email do estabelecimento
            $email_settings = $this->getEstablishmentEmailSettings($nfce_data['establishment_id']);
            
            if (!$force && !$email_settings['auto_send_nfce']) {
                return ['success' => false, 'message' => 'Envio automático desabilitado'];
            }
            
            $results = [];
            
            // Enviar para cliente (se tiver email)
            if ($nfce_data['customer_email']) {
                $result = $this->sendToCustomer($nfce_data, $email_settings);
                $results['customer'] = $result;
            }
            
            // Enviar cópia para proprietário (se configurado)
            if ($email_settings['copy_to_owner'] && $email_settings['owner_email']) {
                $result = $this->sendToOwner($nfce_data, $email_settings);
                $results['owner'] = $result;
            }
            
            return [
                'success' => true,
                'results' => $results
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Envia email para cliente
     */
    private function sendToCustomer($nfce_data, $email_settings) {
        try {
            // Preparar template
            $template = $this->getTemplate('nfce_customer');
            $variables = $this->prepareCustomerVariables($nfce_data);
            
            // Processar template
            $subject = $this->processTemplate($template['subject'], $variables);
            $body_html = $this->processTemplate($template['body_html'], $variables);
            $body_text = $this->processTemplate($template['body_text'], $variables);
            
            // Adicionar mensagem personalizada se houver
            if ($email_settings['custom_message']) {
                $custom_msg = '<div style="background: #f0f8ff; padding: 15px; margin: 15px 0; border-left: 4px solid #007cba; border-radius: 4px;">';
                $custom_msg .= '<strong>Mensagem do estabelecimento:</strong><br>';
                $custom_msg .= nl2br(htmlspecialchars($email_settings['custom_message']));
                $custom_msg .= '</div>';
                
                $body_html = str_replace('{{custom_message}}', $custom_msg, $body_html);
            } else {
                $body_html = str_replace('{{custom_message}}', '', $body_html);
            }
            
            // Preparar anexos
            $attachments = $this->prepareNFCeAttachments($nfce_data);
            
            // Enviar email
            return $this->sendEmail(
                $nfce_data['customer_email'],
                $nfce_data['customer_name'] ?? 'Cliente',
                $subject,
                $body_html,
                $body_text,
                $attachments,
                $nfce_data['id'],
                $nfce_data['establishment_id']
            );
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Envia email para proprietário
     */
    private function sendToOwner($nfce_data, $email_settings) {
        try {
            // Preparar template
            $template = $this->getTemplate('nfce_owner');
            $variables = $this->prepareOwnerVariables($nfce_data);
            
            // Processar template
            $subject = $this->processTemplate($template['subject'], $variables);
            $body_html = $this->processTemplate($template['body_html'], $variables);
            $body_text = $this->processTemplate($template['body_text'], $variables);
            
            // Preparar anexos
            $attachments = $this->prepareNFCeAttachments($nfce_data);
            
            // Enviar email
            return $this->sendEmail(
                $email_settings['owner_email'],
                'Proprietário',
                $subject,
                $body_html,
                $body_text,
                $attachments,
                $nfce_data['id'],
                $nfce_data['establishment_id']
            );
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Envia email genérico
     */
    private function sendEmail($to_email, $to_name, $subject, $body_html, $body_text, $attachments = [], $nfce_id = null, $establishment_id = null) {
        try {
            // Registrar tentativa
            $log_id = $this->logEmail($to_email, $subject, $body_html, $attachments, $nfce_id, $establishment_id);
            
            // Limpar destinatários anteriores
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            
            // Configurar destinatário
            $this->mailer->addAddress($to_email, $to_name);
            
            // Configurar conteúdo
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body_html;
            $this->mailer->AltBody = $body_text;
            
            // Adicionar anexos
            foreach ($attachments as $attachment) {
                if (file_exists($attachment['path'])) {
                    $this->mailer->addAttachment(
                        $attachment['path'], 
                        $attachment['name']
                    );
                }
            }
            
            // Enviar
            $this->mailer->send();
            
            // Marcar como enviado
            $this->markEmailAsSent($log_id);
            
            return [
                'success' => true,
                'message' => 'Email enviado com sucesso',
                'log_id' => $log_id
            ];
            
        } catch (Exception $e) {
            // Marcar como falha
            if (isset($log_id)) {
                $this->markEmailAsFailed($log_id, $e->getMessage());
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Processa emails pendentes na fila
     */
    public function processPendingEmails($limit = 10) {
        try {
            $pending_emails = $this->getPendingEmails($limit);
            
            $results = [
                'processed' => 0,
                'sent' => 0,
                'failed' => 0,
                'details' => []
            ];
            
            foreach ($pending_emails as $email) {
                $results['processed']++;
                
                // Tentar reenviar
                $this->incrementEmailAttempts($email['id']);
                
                $result = $this->retryEmail($email);
                
                $results['details'][] = [
                    'email_id' => $email['id'],
                    'to_email' => $email['to_email'],
                    'success' => $result['success'],
                    'message' => $result['message'] ?? $result['error'] ?? null
                ];
                
                if ($result['success']) {
                    $results['sent']++;
                } else {
                    $results['failed']++;
                }
            }
            
            return [
                'success' => true,
                'results' => $results
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Prepara anexos da NFCe
     */
    private function prepareNFCeAttachments($nfce_data) {
        $attachments = [];
        
        // XML da NFCe
        if (!empty($nfce_data['xml_file_path']) && file_exists($nfce_data['xml_file_path'])) {
            $attachments[] = [
                'path' => $nfce_data['xml_file_path'],
                'name' => "NFCe_{$nfce_data['nfce_number']}.xml"
            ];
        }
        
        // PDF DANFE (se existir)
        if (!empty($nfce_data['pdf_file_path']) && file_exists($nfce_data['pdf_file_path'])) {
            $attachments[] = [
                'path' => $nfce_data['pdf_file_path'],
                'name' => "DANFE_{$nfce_data['nfce_number']}.pdf"
            ];
        }
        
        return $attachments;
    }
    
    /**
     * Prepara variáveis para template do cliente
     */
    private function prepareCustomerVariables($nfce_data) {
        return [
            'establishment_name' => $nfce_data['establishment_name'] ?? 'Estabelecimento',
            'nfce_number' => $nfce_data['nfce_number'],
            'customer_name' => $nfce_data['customer_name'] ?? 'Cliente',
            'total_amount' => 'R$ ' . number_format($nfce_data['total_amount'], 2, ',', '.'),
            'emission_date' => date('d/m/Y H:i', strtotime($nfce_data['emission_date'])),
            'qr_code' => $this->generateQRCodeURL($nfce_data),
            'nfce_key' => $nfce_data['nfce_key']
        ];
    }
    
    /**
     * Prepara variáveis para template do proprietário
     */
    private function prepareOwnerVariables($nfce_data) {
        return [
            'nfce_number' => $nfce_data['nfce_number'],
            'customer_name' => $nfce_data['customer_name'] ?? 'Cliente não identificado',
            'customer_email' => $nfce_data['customer_email'] ?? 'Não informado',
            'total_amount' => 'R$ ' . number_format($nfce_data['total_amount'], 2, ',', '.'),
            'emission_date' => date('d/m/Y H:i', strtotime($nfce_data['emission_date'])),
            'items_count' => count($nfce_data['items'] ?? [])
        ];
    }
    
    /**
     * Template HTML para cliente
     */
    private function getCustomerNFCeHTMLTemplate() {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Sua NFCe</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: linear-gradient(135deg, #007cba 0%, #00a8ff 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;">
                <h1 style="margin: 0; font-size: 24px;">{{establishment_name}}</h1>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">Nota Fiscal de Consumidor Eletrônica</p>
            </div>
            
            <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-top: none;">
                <h2 style="color: #007cba; margin-top: 0;">Olá, {{customer_name}}!</h2>
                
                <p>Obrigado pela sua compra! Sua Nota Fiscal de Consumidor Eletrônica foi gerada com sucesso.</p>
                
                <div style="background: white; padding: 15px; border-radius: 6px; margin: 15px 0;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Número da NFCe:</strong></td>
                            <td style="padding: 8px 0; border-bottom: 1px solid #eee; text-align: right;">{{nfce_number}}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Data/Hora:</strong></td>
                            <td style="padding: 8px 0; border-bottom: 1px solid #eee; text-align: right;">{{emission_date}}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0;"><strong>Valor Total:</strong></td>
                            <td style="padding: 8px 0; text-align: right; font-size: 18px; color: #007cba; font-weight: bold;">{{total_amount}}</td>
                        </tr>
                    </table>
                </div>
                
                {{custom_message}}
                
                <div style="background: white; padding: 15px; border-radius: 6px; margin: 15px 0; text-align: center;">
                    <p><strong>Consulte sua NFCe na Receita Federal:</strong></p>
                    <p style="font-size: 12px; color: #666; margin: 5px 0;">Chave de Acesso: {{nfce_key}}</p>
                    <p style="font-size: 12px; color: #666;">Ou acesse: <a href="{{qr_code}}" target="_blank">Portal da NFCe</a></p>
                </div>
                
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; text-align: center;">
                    <p>Este é um email automático, não responda.</p>
                    <p>Em caso de dúvidas, entre em contato conosco.</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Template texto para cliente
     */
    private function getCustomerNFCeTextTemplate() {
        return 'Olá, {{customer_name}}!

Obrigado pela sua compra! Sua Nota Fiscal de Consumidor Eletrônica foi gerada com sucesso.

DADOS DA NFCe:
- Estabelecimento: {{establishment_name}}
- Número: {{nfce_number}}
- Data/Hora: {{emission_date}}
- Valor Total: {{total_amount}}

Chave de Acesso: {{nfce_key}}

Para consultar sua NFCe na Receita Federal, acesse:
{{qr_code}}

Este é um email automático, não responda.
Em caso de dúvidas, entre em contato conosco.';
    }
    
    // Métodos auxiliares para banco de dados
    private function getNFCeData($nfce_id) {
        $query = "SELECT n.*, e.name as establishment_name, e.document as establishment_document 
                  FROM nfce_emissions n 
                  LEFT JOIN establishments e ON n.establishment_id = e.id 
                  WHERE n.id = :nfce_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':nfce_id', $nfce_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getEstablishmentEmailSettings($establishment_id) {
        $query = "SELECT * FROM establishment_email_settings WHERE establishment_id = :establishment_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':establishment_id', $establishment_id);
        $stmt->execute();
        
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Retornar configurações padrão se não existir
        if (!$settings) {
            return [
                'auto_send_nfce' => true,
                'auto_send_danfe' => true,
                'copy_to_owner' => false,
                'owner_email' => null,
                'custom_message' => null
            ];
        }
        
        return $settings;
    }
    
    private function logEmail($to_email, $subject, $body, $attachments, $nfce_id, $establishment_id) {
        $query = "INSERT INTO email_logs 
                  (nfce_id, establishment_id, to_email, subject, body, attachments, status, created_at) 
                  VALUES 
                  (:nfce_id, :establishment_id, :to_email, :subject, :body, :attachments, 'pending', NOW())";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':nfce_id', $nfce_id);
        $stmt->bindParam(':establishment_id', $establishment_id);
        $stmt->bindParam(':to_email', $to_email);
        $stmt->bindParam(':subject', $subject);
        $stmt->bindParam(':body', $body);
        $stmt->bindParam(':attachments', json_encode($attachments));
        $stmt->execute();
        
        return $this->db->lastInsertId();
    }
    
    private function markEmailAsSent($log_id) {
        $query = "UPDATE email_logs SET status = 'sent', sent_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $log_id);
        $stmt->execute();
    }
    
    private function markEmailAsFailed($log_id, $error) {
        $query = "UPDATE email_logs SET status = 'failed', error_message = :error WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $log_id);
        $stmt->bindParam(':error', $error);
        $stmt->execute();
    }
    
    private function generateQRCodeURL($nfce_data) {
        // URL base da consulta NFCe
        $base_url = "https://www.fazenda.sp.gov.br/nfce/qrcode";
        
        // Parâmetros do QR Code
        $params = [
            'p' => $nfce_data['nfce_key'],
            'v' => 'c'
        ];
        
        return $base_url . '?' . http_build_query($params);
    }
    
    private function getTemplate($name) {
        $query = "SELECT * FROM email_templates WHERE name = :name AND is_active = true";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function processTemplate($template, $variables) {
        $processed = $template;
        
        foreach ($variables as $key => $value) {
            $processed = str_replace('{{' . $key . '}}', $value, $processed);
        }
        
        return $processed;
    }
    
    // Templates para proprietário (métodos auxiliares)
    private function getOwnerNFCeHTMLTemplate() {
        return '<!-- Template HTML para proprietário -->
        <h2>Nova NFCe Emitida</h2>
        <p>Número: {{nfce_number}}</p>
        <p>Cliente: {{customer_name}}</p>
        <p>Email: {{customer_email}}</p>
        <p>Valor: {{total_amount}}</p>
        <p>Data: {{emission_date}}</p>';
    }
    
    private function getOwnerNFCeTextTemplate() {
        return 'Nova NFCe Emitida
        
Número: {{nfce_number}}
Cliente: {{customer_name}}
Email: {{customer_email}}
Valor: {{total_amount}}
Data: {{emission_date}}';
    }
    
    private function insertTemplateIfNotExists($template) {
        $query = "INSERT IGNORE INTO email_templates (name, subject, body_html, body_text, variables) 
                  VALUES (:name, :subject, :body_html, :body_text, :variables)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':name', $template['name']);
        $stmt->bindParam(':subject', $template['subject']);
        $stmt->bindParam(':body_html', $template['body_html']);
        $stmt->bindParam(':body_text', $template['body_text']);
        $stmt->bindParam(':variables', $template['variables']);
        $stmt->execute();
    }
    
    private function getPendingEmails($limit) {
        $query = "SELECT * FROM email_logs 
                  WHERE status = 'pending' AND attempts < 3 
                  ORDER BY created_at ASC 
                  LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function incrementEmailAttempts($email_id) {
        $query = "UPDATE email_logs SET attempts = attempts + 1 WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $email_id);
        $stmt->execute();
    }
    
    private function retryEmail($email_data) {
        // Implementar lógica de reenvio
        return ['success' => false, 'error' => 'Método não implementado'];
    }
}
?>