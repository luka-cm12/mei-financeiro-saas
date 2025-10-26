-- Tabela para log de notificações enviadas
CREATE TABLE IF NOT EXISTS notifications_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    channel VARCHAR(20) NOT NULL DEFAULT 'email', -- email, push, sms
    recipient VARCHAR(255) NOT NULL, -- email address, device token, etc.
    subject VARCHAR(255) NULL,
    content TEXT NULL,
    sent_at DATETIME NOT NULL,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_user_notifications (user_id, notification_type),
    INDEX idx_sent_date (sent_at),
    INDEX idx_status (status)
);

-- Inserir alguns tipos de notificação para referência
INSERT IGNORE INTO notifications_log (id, user_id, notification_type, channel, recipient, sent_at, status) VALUES 
(1, 1, 'trial_expiring_3days', 'email', 'exemplo@email.com', '2024-01-01 00:00:00', 'sent') 
ON DUPLICATE KEY UPDATE id=id;