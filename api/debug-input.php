<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DEBUG DADOS RECEBIDOS ===\n";
echo "REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "CONTENT_TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? 'não definido') . "\n";

$rawInput = file_get_contents("php://input");
echo "Raw Input: " . $rawInput . "\n";

$data = json_decode($rawInput, true);
echo "JSON Decoded: " . json_encode($data) . "\n";

if (is_array($data)) {
    echo "Campos recebidos:\n";
    foreach ($data as $key => $value) {
        echo "  - $key: $value\n";
    }
    
    echo "Validações:\n";
    echo "  - name: " . (isset($data['name']) ? "✓" : "✗") . "\n";
    echo "  - email: " . (isset($data['email']) ? "✓" : "✗") . "\n";
    echo "  - password: " . (isset($data['password']) ? "✓" : "✗") . "\n";
} else {
    echo "❌ Erro: Dados não são um array válido\n";
}
?>