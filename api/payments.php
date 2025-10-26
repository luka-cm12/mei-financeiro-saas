<?php

require_once __DIR__ . '/controllers/PaymentController.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$paymentController = new PaymentController();
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Remove 'api' from path if present
if ($pathParts[0] === 'api') {
    array_shift($pathParts);
}

// Routes
switch ($method) {
    case 'POST':
        switch ($pathParts[0] ?? '') {
            case 'payment-preference':
                $paymentController->createPaymentPreference();
                break;
                
            case 'payment-pix':
                $paymentController->createPixPayment();
                break;
                
            case 'subscription':
                $paymentController->createSubscription();
                break;
                
            default:
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Endpoint não encontrado']);
                break;
        }
        break;
        
    case 'DELETE':
        switch ($pathParts[0] ?? '') {
            case 'subscription':
                if ($pathParts[1] === 'cancel') {
                    $paymentController->cancelMercadoPagoSubscription();
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Endpoint não encontrado']);
                }
                break;
                
            default:
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Endpoint não encontrado']);
                break;
        }
        break;
        
    case 'GET':
        switch ($pathParts[0] ?? '') {
            case 'payment-status':
                if (isset($pathParts[1])) {
                    $paymentController->checkPaymentStatus($pathParts[1]);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ID do pagamento não fornecido']);
                }
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