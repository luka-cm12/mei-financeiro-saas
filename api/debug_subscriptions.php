<?php

require_once 'config/Database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "=== Verificando assinaturas criadas ===\n\n";
    
    // Verificar assinaturas do usuário
    $query = "SELECT us.*, sp.name as plan_name, sp.slug as plan_slug, u.email
              FROM user_subscriptions us
              INNER JOIN subscription_plans sp ON us.plan_id = sp.id
              INNER JOIN users u ON us.user_id = u.id
              ORDER BY us.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($subscriptions) {
        foreach ($subscriptions as $sub) {
            echo "ID: {$sub['id']}\n";
            echo "Usuário: {$sub['email']} (ID: {$sub['user_id']})\n";
            echo "Plano: {$sub['plan_name']} ({$sub['plan_slug']})\n";
            echo "Status: {$sub['status']}\n";
            echo "Início: {$sub['starts_at']}\n";
            echo "Fim: {$sub['ends_at']}\n";
            echo "Trial: {$sub['trial_ends_at']}\n";
            echo "---\n";
        }
    } else {
        echo "Nenhuma assinatura encontrada\n";
    }
    
    echo "\n=== Verificando usuários ===\n\n";
    
    $userQuery = "SELECT id, email, name FROM users ORDER BY id DESC LIMIT 5";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->execute();
    $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        echo "ID: {$user['id']}, Email: {$user['email']}, Nome: {$user['name']}\n";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}