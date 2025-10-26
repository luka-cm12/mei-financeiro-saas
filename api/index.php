<?php
/**
 * Ponto de entrada da API MEI Financeiro
 */

require_once __DIR__ . '/middleware/CorsMiddleware.php';

// Habilitar CORS
CorsMiddleware::handle();

// Configurar error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Capturar erros e retornar JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'message' => 'Erro interno do servidor',
        'error' => $errstr,
        'file' => basename($errfile),
        'line' => $errline
    ]);
    exit();
});

// Capturar exceções não tratadas
set_exception_handler(function($exception) {
    http_response_code(500);
    echo json_encode([
        'message' => 'Erro interno do servidor',
        'error' => $exception->getMessage()
    ]);
    exit();
});

// Roteador simples
$request_method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remover /mei-financeiro-saas/api do path se presente
$path = preg_replace('#^/mei-financeiro-saas/api#', '', $path);
$path = trim($path, '/');

// Dividir path em segmentos
$segments = explode('/', $path);

// Se não há segmentos, mostrar informações da API
if (empty($segments[0])) {
    echo json_encode([
        'message' => 'MEI Financeiro SaaS API',
        'version' => '1.0.0',
        'endpoints' => [
            'POST /auth/register' => 'Registrar usuário',
            'POST /auth/login' => 'Login',
            'POST /auth/refresh' => 'Renovar token',
            'GET /transactions' => 'Listar transações',
            'POST /transactions' => 'Criar transação',
            'PUT /transactions/{id}' => 'Atualizar transação',
            'DELETE /transactions/{id}' => 'Deletar transação',
            'GET /categories' => 'Listar categorias',
            'POST /categories' => 'Criar categoria',
            'GET /reports/monthly' => 'Relatório mensal',
            'GET /user/profile' => 'Perfil do usuário',
            'PUT /user/profile' => 'Atualizar perfil'
        ]
    ]);
    exit();
}

// Roteamento
$controller = $segments[0] ?? '';
$action = $segments[1] ?? '';
$id = $segments[2] ?? null;

try {
    switch ($controller) {
        case 'auth':
            require_once __DIR__ . '/controllers/AuthController.php';
            $authController = new AuthController();
            
            switch ($action) {
                case 'register':
                    if ($request_method === 'POST') {
                        $authController->register();
                    } else {
                        http_response_code(405);
                        echo json_encode(['message' => 'Método não permitido']);
                    }
                    break;
                    
                case 'login':
                    if ($request_method === 'POST') {
                        $authController->login();
                    } else {
                        http_response_code(405);
                        echo json_encode(['message' => 'Método não permitido']);
                    }
                    break;
                    
                case 'refresh':
                    if ($request_method === 'POST') {
                        $authController->refresh();
                    } else {
                        http_response_code(405);
                        echo json_encode(['message' => 'Método não permitido']);
                    }
                    break;
                    
                default:
                    http_response_code(404);
                    echo json_encode(['message' => 'Endpoint não encontrado']);
            }
            break;
            
        case 'transactions':
            require_once __DIR__ . '/controllers/TransactionController.php';
            $transactionController = new TransactionController();
            
            if ($action === '' || $action === null) {
                // /transactions
                switch ($request_method) {
                    case 'GET':
                        $transactionController->getTransactions();
                        break;
                    case 'POST':
                        $transactionController->createTransaction();
                        break;
                    default:
                        http_response_code(405);
                        echo json_encode(['message' => 'Método não permitido']);
                }
            } elseif (is_numeric($action)) {
                // /transactions/{id}
                $id = $action;
                switch ($request_method) {
                    case 'PUT':
                        $transactionController->updateTransaction($id);
                        break;
                    case 'DELETE':
                        $transactionController->deleteTransaction($id);
                        break;
                    default:
                        http_response_code(405);
                        echo json_encode(['message' => 'Método não permitido']);
                }
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Endpoint não encontrado']);
            }
            break;
            
        case 'categories':
            require_once __DIR__ . '/controllers/CategoryController.php';
            $categoryController = new CategoryController();
            
            switch ($request_method) {
                case 'GET':
                    $categoryController->getCategories();
                    break;
                case 'POST':
                    $categoryController->createCategory();
                    break;
                default:
                    http_response_code(405);
                    echo json_encode(['message' => 'Método não permitido']);
            }
            break;
            
        case 'reports':
            require_once __DIR__ . '/controllers/TransactionController.php';
            $transactionController = new TransactionController();
            
            switch ($action) {
                case 'monthly':
                    if ($request_method === 'GET') {
                        $transactionController->getMonthlyReport();
                    } else {
                        http_response_code(405);
                        echo json_encode(['message' => 'Método não permitido']);
                    }
                    break;
                    
                default:
                    http_response_code(404);
                    echo json_encode(['message' => 'Endpoint não encontrado']);
            }
            break;
            
        case 'user':
            require_once __DIR__ . '/controllers/UserController.php';
            $userController = new UserController();
            
            switch ($action) {
                case 'profile':
                    if ($request_method === 'GET') {
                        $userController->getProfile();
                    } elseif ($request_method === 'PUT') {
                        $userController->updateProfile();
                    } else {
                        http_response_code(405);
                        echo json_encode(['message' => 'Método não permitido']);
                    }
                    break;
                    
                default:
                    http_response_code(404);
                    echo json_encode(['message' => 'Endpoint não encontrado']);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['message' => 'Controller não encontrado']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'message' => 'Erro interno do servidor',
        'error' => $e->getMessage()
    ]);
}
?>