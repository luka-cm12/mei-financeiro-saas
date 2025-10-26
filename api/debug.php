<?php
// Arquivo de debug para testar roteamento
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DEBUG API MEI FINANCEIRO ===\n";
echo "REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "PATH_INFO: " . ($_SERVER['PATH_INFO'] ?? 'não definido') . "\n";

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
echo "Path original: " . $path . "\n";

$path = preg_replace('#^/mei-financeiro-saas/api#', '', $path);
echo "Path após regex: " . $path . "\n";

$path = trim($path, '/');
echo "Path após trim: " . $path . "\n";

$segments = explode('/', $path);
echo "Segments: " . json_encode($segments) . "\n";

$controller = $segments[0] ?? '';
$action = $segments[1] ?? '';

echo "Controller: " . $controller . "\n";
echo "Action: " . $action . "\n";

// Verificar se os arquivos existem
$authControllerPath = __DIR__ . '/controllers/AuthController.php';
echo "AuthController existe: " . (file_exists($authControllerPath) ? 'SIM' : 'NÃO') . "\n";
echo "Path: " . $authControllerPath . "\n";
?>