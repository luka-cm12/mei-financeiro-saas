<?php
// Limpar qualquer output anterior
ob_clean();

require_once '../config/Database.php';

// Headers seguros
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Teste simples de conexão
    $query = "SELECT 1 as test";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch();
    
    $response = [
        'success' => true,
        'message' => 'Conexão OK',
        'test_result' => $result['test'],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

exit;
?>