<?php
// NFCe - Sistema completo de emissão
$router->get('/api/nfce', function() use ($database) {
    require_once __DIR__ . '/../models/NFCe.php';
    require_once __DIR__ . '/../models/Establishment.php';
    require_once __DIR__ . '/../controllers/NFCeController.php';
    
    $controller = new NFCeController($database);
    return $controller->getNFCes();
});

$router->get('/api/nfce/{id}', function($id) use ($database) {
    require_once __DIR__ . '/../models/NFCe.php';
    require_once __DIR__ . '/../models/Establishment.php';
    require_once __DIR__ . '/../controllers/NFCeController.php';
    
    $controller = new NFCeController($database);
    return $controller->getNFCe($id);
});

$router->post('/api/nfce/emit', function() use ($database) {
    require_once __DIR__ . '/../models/NFCe.php';
    require_once __DIR__ . '/../models/Establishment.php';
    require_once __DIR__ . '/../controllers/NFCeController.php';
    
    $controller = new NFCeController($database);
    return $controller->emitNFCe();
});

$router->post('/api/nfce/{id}/cancel', function($id) use ($database) {
    require_once __DIR__ . '/../models/NFCe.php';
    require_once __DIR__ . '/../models/Establishment.php';
    require_once __DIR__ . '/../controllers/NFCeController.php';
    
    $controller = new NFCeController($database);
    return $controller->cancelNFCe($id);
});

$router->get('/api/nfce/statistics', function() use ($database) {
    require_once __DIR__ . '/../models/NFCe.php';
    require_once __DIR__ . '/../models/Establishment.php';
    require_once __DIR__ . '/../controllers/NFCeController.php';
    
    $controller = new NFCeController($database);
    return $controller->getStatistics();
});

// Download de arquivos NFCe
$router->get('/api/nfce/{id}/xml', function($id) use ($database) {
    require_once __DIR__ . '/../models/NFCe.php';
    require_once __DIR__ . '/../models/Establishment.php';
    
    try {
        // Verificar autenticação
        $headers = apache_request_headers();
        $token = $headers['Authorization'] ?? '';
        
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }
        
        $decoded = Firebase\JWT\JWT::decode($token, new Firebase\JWT\Key($_ENV['JWT_SECRET'], 'HS256'));
        $user_id = $decoded->user_id;
        
        // Buscar establishment_id do usuário
        $establishment = new Establishment($database);
        $establishment_data = $establishment->getByUserId($user_id);
        
        if (!$establishment_data) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Estabelecimento não encontrado']);
            return;
        }
        
        // Buscar NFCe
        $nfce = new NFCe($database);
        $nfce_data = $nfce->getNFCe($id, $establishment_data['id']);
        
        if (!$nfce_data || !$nfce_data['xml_file_path']) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Arquivo XML não encontrado']);
            return;
        }
        
        // Servir arquivo
        if (file_exists($nfce_data['xml_file_path'])) {
            header('Content-Type: application/xml');
            header('Content-Disposition: attachment; filename="' . basename($nfce_data['xml_file_path']) . '"');
            readfile($nfce_data['xml_file_path']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Arquivo não encontrado no servidor']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
    }
});

$router->get('/api/nfce/{id}/pdf', function($id) use ($database) {
    require_once __DIR__ . '/../models/NFCe.php';
    require_once __DIR__ . '/../models/Establishment.php';
    
    try {
        // Verificar autenticação
        $headers = apache_request_headers();
        $token = $headers['Authorization'] ?? '';
        
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }
        
        $decoded = Firebase\JWT\JWT::decode($token, new Firebase\JWT\Key($_ENV['JWT_SECRET'], 'HS256'));
        $user_id = $decoded->user_id;
        
        // Buscar establishment_id do usuário
        $establishment = new Establishment($database);
        $establishment_data = $establishment->getByUserId($user_id);
        
        if (!$establishment_data) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Estabelecimento não encontrado']);
            return;
        }
        
        // Buscar NFCe
        $nfce = new NFCe($database);
        $nfce_data = $nfce->getNFCe($id, $establishment_data['id']);
        
        if (!$nfce_data || !$nfce_data['pdf_file_path']) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Arquivo PDF não encontrado']);
            return;
        }
        
        // Servir arquivo
        if (file_exists($nfce_data['pdf_file_path'])) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($nfce_data['pdf_file_path']) . '"');
            readfile($nfce_data['pdf_file_path']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Arquivo não encontrado no servidor']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
    }
});
?>