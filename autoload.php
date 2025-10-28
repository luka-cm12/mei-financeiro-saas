<?php
/**
 * Autoloader manual para bibliotecas necessárias
 */

// As classes serão carregadas pelos arquivos vendor específicos

// Incluir bibliotecas mock
require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/endroid/qr-code/src/QrCode.php';

// Autoloader para classes do projeto
spl_autoload_register(function ($class_name) {
    // Namespace mapping
    $namespace_map = [
        'MEIFinanceiro\\Services\\' => __DIR__ . '/api/services/',
        'MEIFinanceiro\\Controllers\\' => __DIR__ . '/api/controllers/',
        'MEIFinanceiro\\Models\\' => __DIR__ . '/api/models/',
        'MEIFinanceiro\\NFCe\\' => __DIR__ . '/api/'
    ];
    
    foreach ($namespace_map as $namespace => $path) {
        if (strpos($class_name, $namespace) === 0) {
            $relative_class = substr($class_name, strlen($namespace));
            $file = $path . str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';
            
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
    
    // Fallback para classes sem namespace
    $file = __DIR__ . '/api/' . str_replace('\\', DIRECTORY_SEPARATOR, $class_name) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Criar diretórios necessários
$directories = [
    __DIR__ . '/storage',
    __DIR__ . '/storage/reports',
    __DIR__ . '/storage/reports/sped',
    __DIR__ . '/storage/reports/dte',
    __DIR__ . '/storage/xml',
    __DIR__ . '/storage/pdf',
    __DIR__ . '/storage/certificates',
    __DIR__ . '/storage/emails'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Configurações globais
define('TCPDF_FONTS_PATH', __DIR__ . '/storage/fonts/');
define('REPORTS_PATH', __DIR__ . '/storage/reports/');
define('XML_PATH', __DIR__ . '/storage/xml/');
define('PDF_PATH', __DIR__ . '/storage/pdf/');
define('CERTIFICATES_PATH', __DIR__ . '/storage/certificates/');

// Funções auxiliares
if (!function_exists('formatCurrency')) {
    function formatCurrency($value) {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}

if (!function_exists('formatDocument')) {
    function formatDocument($document) {
        $document = preg_replace('/\D/', '', $document);
        
        if (strlen($document) === 11) {
            // CPF
            return substr($document, 0, 3) . '.' . 
                   substr($document, 3, 3) . '.' . 
                   substr($document, 6, 3) . '-' . 
                   substr($document, 9, 2);
        } elseif (strlen($document) === 14) {
            // CNPJ
            return substr($document, 0, 2) . '.' . 
                   substr($document, 2, 3) . '.' . 
                   substr($document, 5, 3) . '/' . 
                   substr($document, 8, 4) . '-' . 
                   substr($document, 12, 2);
        }
        
        return $document;
    }
}

if (!function_exists('validateNFCeKey')) {
    function validateNFCeKey($key) {
        $key = preg_replace('/\D/', '', $key);
        return strlen($key) === 44;
    }
}

if (!function_exists('logError')) {
    function logError($message, $context = []) {
        $log_file = __DIR__ . '/storage/error.log';
        $timestamp = date('Y-m-d H:i:s');
        $context_str = json_encode($context);
        $log_entry = "[$timestamp] ERROR: $message | Context: $context_str" . PHP_EOL;
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
}

echo "Autoloader configurado com sucesso!" . PHP_EOL;
echo "Bibliotecas mock carregadas:" . PHP_EOL;
echo "- TCPDF (PDF generation)" . PHP_EOL;
echo "- PHPMailer (Email sending)" . PHP_EOL;  
echo "- EndroidQrCode (QR Code generation)" . PHP_EOL;
echo "Diretórios criados em /storage/" . PHP_EOL;
?>