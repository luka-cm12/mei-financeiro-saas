<?php
/**
 * Controlador de usuário
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/User.php';

class UserController {
    private $db;
    private $auth;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auth = new AuthMiddleware();
    }
    
    public function getProfile() {
        $auth_data = $this->auth->authenticate();
        
        try {
            $user = new User($this->db);
            $profile = $user->findById($auth_data->user_id);
            
            if (!$profile) {
                http_response_code(404);
                echo json_encode(["message" => "Usuário não encontrado"]);
                return;
            }
            
            // Remover dados sensíveis
            unset($profile['password_hash']);
            
            echo json_encode([
                "profile" => $profile
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function updateProfile() {
        $auth_data = $this->auth->authenticate();
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Remover campos que não podem ser alterados por aqui
        unset($data['id']);
        unset($data['subscription_status']);
        unset($data['subscription_expires_at']);
        unset($data['created_at']);
        unset($data['updated_at']);
        
        if (empty($data)) {
            http_response_code(400);
            echo json_encode(["message" => "Nenhum dado para atualizar"]);
            return;
        }
        
        // Validar email se fornecido
        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(["message" => "Email inválido"]);
            return;
        }
        
        // Validar senha se fornecida
        if (isset($data['password']) && strlen($data['password']) < 6) {
            http_response_code(400);
            echo json_encode(["message" => "Senha deve ter pelo menos 6 caracteres"]);
            return;
        }
        
        try {
            // Verificar se email já existe (se estiver sendo alterado)
            if (isset($data['email'])) {
                $query = "SELECT id FROM users WHERE email = :email AND id != :user_id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':email', $data['email']);
                $stmt->bindParam(':user_id', $auth_data->user_id);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    http_response_code(409);
                    echo json_encode(["message" => "Email já está em uso por outro usuário"]);
                    return;
                }
            }
            
            $user = new User($this->db);
            
            if ($user->update($auth_data->user_id, $data)) {
                echo json_encode(["message" => "Perfil atualizado com sucesso"]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Erro ao atualizar perfil"]);
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function getSubscriptionStatus() {
        $auth_data = $this->auth->authenticate();
        
        try {
            $user = new User($this->db);
            $subscription = $user->getSubscriptionStatus($auth_data->user_id);
            
            if (!$subscription) {
                http_response_code(404);
                echo json_encode(["message" => "Usuário não encontrado"]);
                return;
            }
            
            $is_expired = false;
            $days_remaining = null;
            
            if ($subscription['subscription_expires_at']) {
                $expires_at = new DateTime($subscription['subscription_expires_at']);
                $now = new DateTime();
                $is_expired = $expires_at < $now;
                
                if (!$is_expired) {
                    $days_remaining = $now->diff($expires_at)->days;
                }
            }
            
            echo json_encode([
                "subscription_status" => $subscription['subscription_status'],
                "expires_at" => $subscription['subscription_expires_at'],
                "is_expired" => $is_expired,
                "days_remaining" => $days_remaining
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
}
?>