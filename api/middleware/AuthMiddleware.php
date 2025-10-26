<?php
/**
 * Middleware para autenticação JWT
 */

require_once __DIR__ . '/../config/Database.php';

class AuthMiddleware {
    private $secret_key = "mei_financeiro_secret_2024";
    private $algorithm = "HS256";
    
    public function authenticate() {
        $headers = getallheaders();
        $token = null;
        
        // Verifica o header Authorization
        if (isset($headers['Authorization'])) {
            $matches = [];
            if (preg_match('/Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
                $token = $matches[1];
            }
        }
        
        if (!$token) {
            http_response_code(401);
            echo json_encode(["message" => "Token de acesso necessário"]);
            exit();
        }
        
        try {
            $decoded = $this->decodeJWT($token);
            
            // Verifica se o usuário tem assinatura ativa (desabilitado temporariamente)
            // if (!$this->checkSubscription($decoded->user_id)) {
            //     http_response_code(403);
            //     echo json_encode([
            //         "message" => "Assinatura expirada ou inativa",
            //         "code" => "SUBSCRIPTION_EXPIRED"
            //     ]);
            //     exit();
            // }
            
            return $decoded;
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(["message" => "Token inválido: " . $e->getMessage()]);
            exit();
        }
    }
    
    public function generateJWT($user_id, $email) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $user_id,
            'email' => $email,
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60) // 24 horas
        ]);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $this->secret_key, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    private function decodeJWT($jwt) {
        $tokenParts = explode('.', $jwt);
        if (count($tokenParts) !== 3) {
            throw new Exception('Token mal formado');
        }
        
        $header = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[0]));
        $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1]));
        $signature_provided = $tokenParts[2];
        
        $base64_header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64_payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64_header . "." . $base64_payload, $this->secret_key, true);
        $base64_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        if (!hash_equals($base64_signature, $signature_provided)) {
            throw new Exception('Assinatura inválida');
        }
        
        $payload_data = json_decode($payload);
        
        if (isset($payload_data->exp) && $payload_data->exp < time()) {
            throw new Exception('Token expirado');
        }
        
        return $payload_data;
    }
    
    private function checkSubscription($user_id) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT subscription_status, subscription_expires_at FROM users WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            if (!$user) {
                return false;
            }
            
            // Permite trial e active
            if ($user['subscription_status'] === 'trial') {
                return true;
            }
            
            if ($user['subscription_status'] === 'active') {
                $expires_at = new DateTime($user['subscription_expires_at']);
                $now = new DateTime();
                return $expires_at > $now;
            }
            
            return false;
            
        } catch (Exception $e) {
            return false;
        }
    }
}
?>