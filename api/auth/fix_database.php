<?php
require_once '../config/Database.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verificar se a coluna password existe
    $check_column = "SHOW COLUMNS FROM users LIKE 'password'";
    $stmt = $db->prepare($check_column);
    $stmt->execute();
    $password_exists = $stmt->fetch();
    
    if (!$password_exists) {
        // Adicionar coluna password se não existir
        $add_password = "ALTER TABLE users ADD COLUMN password VARCHAR(255) NOT NULL AFTER email";
        $db->exec($add_password);
        
        echo json_encode([
            'success' => true,
            'message' => 'Coluna password adicionada com sucesso'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Coluna password já existe'
        ]);
    }
    
    // Verificar outras colunas necessárias e adicionar se não existirem
    $columns_to_check = [
        'phone' => "ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER password",
        'document' => "ALTER TABLE users ADD COLUMN document VARCHAR(20) NULL AFTER phone", 
        'status' => "ALTER TABLE users ADD COLUMN status ENUM('active', 'inactive', 'suspended') DEFAULT 'active' AFTER document",
        'email_verified' => "ALTER TABLE users ADD COLUMN email_verified BOOLEAN DEFAULT FALSE AFTER status",
        'last_login' => "ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL AFTER email_verified"
    ];
    
    foreach ($columns_to_check as $column => $alter_query) {
        $check = "SHOW COLUMNS FROM users LIKE '$column'";
        $stmt = $db->prepare($check);
        $stmt->execute();
        
        if (!$stmt->fetch()) {
            $db->exec($alter_query);
        }
    }
    
    // Criar tabela de subscriptions se não existir
    $create_subscriptions = "CREATE TABLE IF NOT EXISTS subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        plan_type ENUM('trial', 'monthly', 'annual') DEFAULT 'trial',
        status ENUM('active', 'inactive', 'cancelled', 'expired') DEFAULT 'active',
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NULL,
        mercadopago_subscription_id VARCHAR(255) NULL,
        mercadopago_preapproval_id VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_status (user_id, status),
        INDEX idx_expires (expires_at)
    )";
    
    $db->exec($create_subscriptions);
    
    echo json_encode([
        'success' => true,
        'message' => 'Estrutura do banco atualizada com sucesso!'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>