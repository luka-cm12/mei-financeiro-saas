<?php
require_once '../config/Database.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verificar se a tabela existe
    $check_table = "SHOW TABLES LIKE 'users'";
    $stmt = $db->prepare($check_table);
    $stmt->execute();
    $table_exists = $stmt->fetch();
    
    if ($table_exists) {
        // Verificar estrutura da tabela
        $describe = "DESCRIBE users";
        $stmt = $db->prepare($describe);
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'table_exists' => true,
            'columns' => $columns
        ]);
    } else {
        // Criar tabela correta
        $create_table = "CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NULL,
            document VARCHAR(20) NULL,
            status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
            email_verified BOOLEAN DEFAULT FALSE,
            email_verification_token VARCHAR(255) NULL,
            password_reset_token VARCHAR(255) NULL,
            password_reset_expires TIMESTAMP NULL,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_status (status)
        )";
        
        $db->exec($create_table);
        
        echo json_encode([
            'success' => true,
            'table_exists' => false,
            'message' => 'Tabela users criada com sucesso'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>