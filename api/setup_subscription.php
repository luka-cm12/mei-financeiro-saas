<?php

require_once 'config/Database.php';

echo "Criando sistema de assinatura...\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // SQL para criação das tabelas
    $sqlStatements = [
        // Tabela de planos de assinatura
        "CREATE TABLE IF NOT EXISTS subscription_plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(50) UNIQUE NOT NULL,
            description TEXT NULL,
            price DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'BRL',
            billing_period ENUM('monthly', 'yearly') NOT NULL,
            features JSON NOT NULL,
            limits_json JSON NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        // Tabela de assinaturas dos usuários
        "CREATE TABLE IF NOT EXISTS user_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            plan_id INT NOT NULL,
            status ENUM('active', 'cancelled', 'expired', 'pending', 'trial') DEFAULT 'pending',
            starts_at DATE NOT NULL,
            ends_at DATE NOT NULL,
            trial_ends_at DATE NULL,
            cancelled_at TIMESTAMP NULL,
            payment_method VARCHAR(50) NULL,
            payment_reference VARCHAR(255) NULL,
            auto_renew BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (plan_id) REFERENCES subscription_plans(id),
            INDEX idx_user_status (user_id, status),
            INDEX idx_ends_at (ends_at)
        )",
        
        // Tabela de histórico de pagamentos
        "CREATE TABLE IF NOT EXISTS payment_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            subscription_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'BRL',
            status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
            payment_method VARCHAR(50) NULL,
            payment_reference VARCHAR(255) NULL,
            external_id VARCHAR(255) NULL,
            paid_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (subscription_id) REFERENCES user_subscriptions(id) ON DELETE CASCADE,
            INDEX idx_user_status (user_id, status),
            INDEX idx_external_id (external_id)
        )",
        
        // Tabela de uso de recursos
        "CREATE TABLE IF NOT EXISTS feature_usage (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            feature_name VARCHAR(100) NOT NULL,
            usage_count INT DEFAULT 0,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_feature_period (user_id, feature_name, period_start),
            INDEX idx_user_feature (user_id, feature_name),
            INDEX idx_period (period_start, period_end)
        )"
    ];
    
    // Executar cada comando SQL
    foreach ($sqlStatements as $sql) {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        echo "✓ Tabela criada com sucesso\n";
    }
    
    // Inserir planos padrão
    $planInsertSQL = "INSERT IGNORE INTO subscription_plans (name, slug, description, price, billing_period, features, limits_json, sort_order) VALUES
    (
        'Gratuito',
        'free',
        'Plano básico para começar a organizar suas finanças',
        0.00,
        'monthly',
        JSON_ARRAY(
            'Até 100 transações por mês',
            'Relatórios básicos',
            'Categorização simples',
            'Suporte por email'
        ),
        JSON_OBJECT(
            'transactions_per_month', 100,
            'categories', 10,
            'financial_goals', 2,
            'reports', 'basic',
            'backup_frequency', 'monthly',
            'support_level', 'email'
        ),
        1
    ),
    (
        'Premium',
        'premium',
        'Plano completo para MEIs e pequenos empresários',
        19.90,
        'monthly',
        JSON_ARRAY(
            'Transações ilimitadas',
            'Relatórios avançados e gráficos',
            'Metas financeiras ilimitadas',
            'Backup automático diário',
            'Exportação em PDF e Excel',
            'Categorias personalizadas ilimitadas',
            'Suporte prioritário',
            'Análise de tendências',
            'Alertas inteligentes'
        ),
        JSON_OBJECT(
            'transactions_per_month', -1,
            'categories', -1,
            'financial_goals', -1,
            'reports', 'advanced',
            'backup_frequency', 'daily',
            'support_level', 'priority',
            'export_formats', JSON_ARRAY('pdf', 'excel', 'csv'),
            'advanced_analytics', true,
            'smart_alerts', true
        ),
        2
    ),
    (
        'Premium Anual',
        'premium-yearly',
        'Plano Premium com desconto anual (2 meses grátis)',
        199.00,
        'yearly',
        JSON_ARRAY(
            'Todas as funcionalidades Premium',
            '2 meses grátis',
            'Consultoria financeira mensal',
            'Relatórios personalizados',
            'Integração bancária (em breve)'
        ),
        JSON_OBJECT(
            'transactions_per_month', -1,
            'categories', -1,
            'financial_goals', -1,
            'reports', 'advanced',
            'backup_frequency', 'daily',
            'support_level', 'priority',
            'export_formats', JSON_ARRAY('pdf', 'excel', 'csv'),
            'advanced_analytics', true,
            'smart_alerts', true,
            'monthly_consulting', true,
            'custom_reports', true
        ),
        3
    ),
    (
        'Empresarial',
        'business',
        'Para empresas com múltiplos usuários e necessidades avançadas',
        49.90,
        'monthly',
        JSON_ARRAY(
            'Todas as funcionalidades Premium',
            'Até 5 usuários',
            'Dashboard gerencial',
            'Relatórios consolidados',
            'API personalizada',
            'Suporte técnico dedicado',
            'Integração com sistemas externos',
            'Controle de permissões'
        ),
        JSON_OBJECT(
            'transactions_per_month', -1,
            'categories', -1,
            'financial_goals', -1,
            'reports', 'enterprise',
            'backup_frequency', 'daily',
            'support_level', 'dedicated',
            'max_users', 5,
            'api_access', true,
            'external_integrations', true,
            'permission_control', true,
            'consolidated_reports', true
        ),
        4
    )";
    
    $stmt = $conn->prepare($planInsertSQL);
    $stmt->execute();
    echo "✓ Planos padrão inseridos com sucesso\n";
    
    echo "\n🎉 Sistema de assinatura criado com sucesso!\n";
    echo "Planos disponíveis:\n";
    echo "- Gratuito (R$ 0,00/mês)\n";
    echo "- Premium (R$ 19,90/mês)\n";
    echo "- Premium Anual (R$ 199,00/ano)\n";
    echo "- Empresarial (R$ 49,90/mês)\n";
    
} catch (Exception $e) {
    echo "❌ Erro ao criar sistema de assinatura: " . $e->getMessage() . "\n";
}