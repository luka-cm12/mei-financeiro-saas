<?php
/**
 * Middleware para CORS
 */
class CorsMiddleware {
    public static function handle() {
        // Permitir todas as origens em desenvolvimento
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Em desenvolvimento, permitir qualquer origem
        if ($origin) {
            header("Access-Control-Allow-Origin: $origin");
        } else {
            header("Access-Control-Allow-Origin: *");
        }
        
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Credentials: true");
        header("Content-Type: application/json; charset=UTF-8");
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
}
?>