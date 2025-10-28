<?php

require_once __DIR__ . '/controllers/EstablishmentController.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$establishmentController = new EstablishmentController();
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Remove 'api' from path if present
if ($pathParts[0] === 'api') {
    array_shift($pathParts);
}

// Routes
switch ($method) {
    case 'GET':
        switch ($pathParts[0] ?? '') {
            case 'establishment':
                if (!isset($pathParts[1])) {
                    // GET /establishment - Obter dados do estabelecimento
                    $establishmentController->getEstablishment();
                } else {
                    switch ($pathParts[1]) {
                        case 'search-cep':
                            // GET /establishment/search-cep/12345678
                            if (isset($pathParts[2])) {
                                $establishmentController->searchCep($pathParts[2]);
                            } else {
                                http_response_code(400);
                                echo json_encode(['success' => false, 'message' => 'CEP não fornecido']);
                            }
                            break;
                            
                        default:
                            http_response_code(404);
                            echo json_encode(['success' => false, 'message' => 'Endpoint não encontrado']);
                            break;
                    }
                }
                break;
                
            default:
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Endpoint não encontrado']);
                break;
        }
        break;
        
    case 'POST':
        switch ($pathParts[0] ?? '') {
            case 'establishment':
                if (!isset($pathParts[1])) {
                    // POST /establishment - Criar/atualizar estabelecimento
                    $establishmentController->saveEstablishment();
                } else {
                    switch ($pathParts[1]) {
                        case 'upload-certificate':
                            // POST /establishment/upload-certificate
                            $establishmentController->uploadCertificate();
                            break;
                            
                        case 'configure-nfce':
                            // POST /establishment/configure-nfce
                            $establishmentController->configureNFCe();
                            break;
                            
                        default:
                            http_response_code(404);
                            echo json_encode(['success' => false, 'message' => 'Endpoint não encontrado']);
                            break;
                    }
                }
                break;
                
            default:
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Endpoint não encontrado']);
                break;
        }
        break;
        
    case 'PUT':
        switch ($pathParts[0] ?? '') {
            case 'establishment':
                // PUT /establishment - Atualizar estabelecimento
                $establishmentController->saveEstablishment();
                break;
                
            default:
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Endpoint não encontrado']);
                break;
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
        break;
}
?>