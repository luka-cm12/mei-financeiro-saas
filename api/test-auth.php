<?php
// Teste específico para rota auth/register
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TESTE AUTH/REGISTER ===\n";

// Simular o que aconteceria com /mei-financeiro-saas/api/auth/register
$simulatedURI = "/mei-financeiro-saas/api/auth/register";
echo "URI Simulada: " . $simulatedURI . "\n";

$path = parse_url($simulatedURI, PHP_URL_PATH);
echo "Path original: " . $path . "\n";

$path = preg_replace('#^/mei-financeiro-saas/api#', '', $path);
echo "Path após regex: " . $path . "\n";

$path = trim($path, '/');
echo "Path após trim: " . $path . "\n";

$segments = explode('/', $path);
echo "Segments: " . json_encode($segments) . "\n";

$controller = $segments[0] ?? '';
$action = $segments[1] ?? '';

echo "Controller: '" . $controller . "'\n";
echo "Action: '" . $action . "'\n";

if ($controller === 'auth') {
    echo "✓ Controller auth detectado\n";
    if ($action === 'register') {
        echo "✓ Action register detectado\n";
        
        // Verificar se o arquivo do controller existe
        $controllerPath = __DIR__ . '/controllers/AuthController.php';
        if (file_exists($controllerPath)) {
            echo "✓ AuthController.php existe\n";
            
            // Tentar incluir o controller
            try {
                require_once $controllerPath;
                echo "✓ AuthController incluído com sucesso\n";
                
                // Tentar instanciar
                $authController = new AuthController();
                echo "✓ AuthController instanciado com sucesso\n";
                
                // Verificar se método register existe
                if (method_exists($authController, 'register')) {
                    echo "✓ Método register existe\n";
                } else {
                    echo "✗ Método register NÃO existe\n";
                }
                
            } catch (Exception $e) {
                echo "✗ Erro ao incluir/instanciar: " . $e->getMessage() . "\n";
            }
        } else {
            echo "✗ AuthController.php NÃO existe\n";
        }
    } else {
        echo "✗ Action '$action' não é 'register'\n";
    }
} else {
    echo "✗ Controller '$controller' não é 'auth'\n";
}
?>