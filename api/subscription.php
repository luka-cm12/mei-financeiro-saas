<?php

require_once 'controllers/SubscriptionController.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$controller = new SubscriptionController();
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Parse the URI to get the endpoint and parameters  
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
$pathParts = [];

if (!empty($pathInfo)) {
    $pathParts = explode('/', trim($pathInfo, '/'));
    $pathParts = array_filter($pathParts); // Remove empty parts
    $pathParts = array_values($pathParts); // Reset array indexes
}

try {
    switch ($method) {
        case 'GET':
            if (empty($pathParts)) {
                // GET /api/subscription - Listar todos os planos por padrão
                $controller->getPlans();
            } elseif ($pathParts[0] === 'plans') {
                // GET /api/subscription/plans - Listar todos os planos
                $controller->getPlans();
            } elseif ($pathParts[0] === 'plan' && isset($pathParts[1])) {
                // GET /api/subscription/plan/{slug} - Obter detalhes de um plano
                $controller->getPlan($pathParts[1]);
            } elseif ($pathParts[0] === 'current') {
                // GET /api/subscription/current - Obter assinatura atual do usuário
                $controller->getCurrentSubscription();
            } elseif ($pathParts[0] === 'check' && isset($pathParts[1])) {
                // GET /api/subscription/check/{feature} - Verificar acesso a feature
                $controller->checkFeatureAccess($pathParts[1]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Endpoint não encontrado: ' . json_encode($pathParts)
                ]);
            }
            break;

        case 'POST':
            if (empty($pathParts) || $pathParts[0] === 'subscribe') {
                // POST /api/subscription/subscribe - Iniciar processo de assinatura
                $controller->subscribe();
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Endpoint não encontrado'
                ]);
            }
            break;

        case 'PUT':
            if ($pathParts[0] === 'cancel') {
                // PUT /api/subscription/cancel - Cancelar assinatura
                $controller->cancelSubscription();
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Endpoint não encontrado'
                ]);
            }
            break;

        case 'DELETE':
            if ($pathParts[0] === 'cancel') {
                // DELETE /api/subscription/cancel - Cancelar assinatura (alternativo)
                $controller->cancelSubscription();
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Endpoint não encontrado'
                ]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Método não permitido'
            ]);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}