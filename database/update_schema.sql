-- Adicionar tabelas para as novas funcionalidades

-- Tabela de notificações
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'success', 'error', 'goal_due', 'high_spending', 'low_income') DEFAULT 'info',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabela de backups
CREATE TABLE IF NOT EXISTS backups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    backup_data LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Índices para performance
CREATE INDEX idx_notifications_user_id ON notifications(user_id);
CREATE INDEX idx_notifications_created_at ON notifications(created_at);
CREATE INDEX idx_notifications_is_read ON notifications(is_read);
CREATE INDEX idx_backups_user_id ON backups(user_id);
CREATE INDEX idx_backups_created_at ON backups(created_at);

-- Adicionar campos extras na tabela de usuários para preferências
ALTER TABLE users ADD COLUMN IF NOT EXISTS preferences JSON DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS notification_settings JSON DEFAULT NULL;

-- Adicionar campos extras na tabela de transações para mais detalhes
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS notes TEXT NULL;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS tags VARCHAR(500) NULL;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS location VARCHAR(255) NULL;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS payment_method VARCHAR(100) NULL;

-- Tabela para configurações do sistema
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NULL,
    description TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Inserir configurações padrão
INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES 
('app_version', '1.0.0', 'Versão atual do aplicativo'),
('maintenance_mode', 'false', 'Modo de manutenção ativado/desativado'),
('max_backup_per_user', '10', 'Número máximo de backups por usuário'),
('notification_retention_days', '30', 'Dias para manter notificações no sistema');

-- Atualizar a tabela de categorias com mais campos
ALTER TABLE categories ADD COLUMN IF NOT EXISTS is_default BOOLEAN DEFAULT FALSE;
ALTER TABLE categories ADD COLUMN IF NOT EXISTS description TEXT NULL;

-- Inserir categorias padrão se não existirem
INSERT IGNORE INTO categories (name, type, color, icon, is_default, description) VALUES 
('Vendas', 'receita', '#4CAF50', 'attach_money', TRUE, 'Receitas de vendas de produtos/serviços'),
('Serviços', 'receita', '#2196F3', 'work', TRUE, 'Receitas de prestação de serviços'),
('Alimentação', 'despesa', '#FF9800', 'restaurant', TRUE, 'Gastos com alimentação e refeições'),
('Transporte', 'despesa', '#9C27B0', 'directions_car', TRUE, 'Gastos com transporte e combustível'),
('Material', 'despesa', '#795548', 'inventory', TRUE, 'Compra de materiais e insumos'),
('Marketing', 'despesa', '#E91E63', 'campaign', TRUE, 'Gastos com marketing e publicidade');

COMMIT;