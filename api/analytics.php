<?php

require_once __DIR__ . '/controllers/AnalyticsController.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$analyticsController = new AnalyticsController();
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
            case 'analytics':
                if (!isset($pathParts[1])) {
                    // GET /analytics - Dashboard completo
                    $analyticsController->getDashboard();
                } else {
                    switch ($pathParts[1]) {
                        case 'dashboard':
                            $analyticsController->getDashboard();
                            break;
                            
                        case 'metrics':
                            $analyticsController->getSubscriptionMetrics();
                            break;
                            
                        case 'churn':
                            $analyticsController->getChurnMetrics();
                            break;
                            
                        case 'plans':
                            $analyticsController->getPlanAnalytics();
                            break;
                            
                        case 'growth':
                            $analyticsController->getGrowthData();
                            break;
                            
                        case 'retention':
                            $analyticsController->getRetentionMetrics();
                            break;
                            
                        case 'export':
                            $analyticsController->exportToCsv();
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
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
        break;
}
?>