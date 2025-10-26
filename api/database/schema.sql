-- Schema do banco de dados para MEI Financeiro SaaS
CREATE DATABASE IF NOT EXISTS mei_financeiro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mei_financeiro;

-- Tabela de usuários
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    business_name VARCHAR(150),
    business_type VARCHAR(50),
    cnpj VARCHAR(18),
    subscription_status ENUM('trial', 'active', 'suspended', 'cancelled') DEFAULT 'trial',
    subscription_expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de categorias de transações
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('receita', 'despesa') NOT NULL,
    icon VARCHAR(50),
    color VARCHAR(7),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabela de transações
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT,
    type ENUM('receita', 'despesa') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    transaction_date DATE NOT NULL,
    payment_method VARCHAR(50),
    bank_reference VARCHAR(100), -- Para integração bancária
    is_recurring BOOLEAN DEFAULT FALSE,
    recurring_frequency ENUM('semanal', 'mensal', 'anual'),
    tags TEXT, -- JSON array de tags
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Tabela de metas financeiras
CREATE TABLE financial_goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    target_amount DECIMAL(10,2) NOT NULL,
    current_amount DECIMAL(10,2) DEFAULT 0,
    target_date DATE,
    status ENUM('ativa', 'concluida', 'pausada') DEFAULT 'ativa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabela de assinaturas/pagamentos
CREATE TABLE subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_name VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'active', 'cancelled', 'expired') DEFAULT 'pending',
    payment_method VARCHAR(50),
    payment_provider VARCHAR(50), -- Mercado Pago, PagSeguro, etc.
    external_id VARCHAR(100), -- ID do pagamento no provedor
    starts_at DATE NOT NULL,
    expires_at DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabela de integração bancária
CREATE TABLE bank_integrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bank_name VARCHAR(100) NOT NULL,
    account_type VARCHAR(50),
    last_sync TIMESTAMP,
    sync_status ENUM('connected', 'disconnected', 'error') DEFAULT 'disconnected',
    api_credentials TEXT, -- JSON encriptado
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabela de notificações
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT,
    type ENUM('info', 'warning', 'success', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    action_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Índices para performance
CREATE INDEX idx_transactions_user_date ON transactions(user_id, transaction_date);
CREATE INDEX idx_transactions_category ON transactions(category_id);
CREATE INDEX idx_users_subscription ON users(subscription_status, subscription_expires_at);
CREATE INDEX idx_notifications_user_unread ON notifications(user_id, is_read);

-- Inserir categorias padrão (serão criadas automaticamente para novos usuários)
INSERT INTO categories (user_id, name, type, icon, color) VALUES
(0, 'Vendas', 'receita', 'shopping_cart', '#4CAF50'),
(0, 'Serviços', 'receita', 'build', '#2196F3'),
(0, 'Outros Recebimentos', 'receita', 'account_balance', '#FF9800'),
(0, 'Materiais/Produtos', 'despesa', 'inventory', '#F44336'),
(0, 'Marketing', 'despesa', 'campaign', '#9C27B0'),
(0, 'Transporte', 'despesa', 'directions_car', '#607D8B'),
(0, 'Alimentação', 'despesa', 'restaurant', '#FF5722'),
(0, 'Equipamentos', 'despesa', 'computer', '#795548'),
(0, 'Taxas/Impostos', 'despesa', 'receipt', '#E91E63'),
(0, 'Outros Gastos', 'despesa', 'more_horiz', '#9E9E9E');