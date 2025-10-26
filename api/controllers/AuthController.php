<?php
/**
 * Controlador de autenticação
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/User.php';

class AuthController {
    private $db;
    private $auth;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auth = new AuthMiddleware();
    }
    
    public function register() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validação básica
        if (!isset($data['name']) || !isset($data['email']) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode(["message" => "Nome, email e senha são obrigatórios"]);
            return;
        }
        
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(["message" => "Email inválido"]);
            return;
        }
        
        if (strlen($data['password']) < 6) {
            http_response_code(400);
            echo json_encode(["message" => "Senha deve ter pelo menos 6 caracteres"]);
            return;
        }
        
        try {
            // Verificar se email já existe
            $query = "SELECT id FROM users WHERE email = :email";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':email', $data['email']);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                http_response_code(409);
                echo json_encode(["message" => "Email já está em uso"]);
                return;
            }
            
            // Criar usuário
            $user = new User($this->db);
            $user_id = $user->create($data);
            
            // Criar categorias padrão para o usuário
            $this->createDefaultCategories($user_id);
            
            // Gerar token
            $token = $this->auth->generateJWT($user_id, $data['email']);
            
            // Definir período de trial (30 dias)
            $trial_expires = date('Y-m-d H:i:s', strtotime('+30 days'));
            $query = "UPDATE users SET subscription_expires_at = :expires_at WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':expires_at', $trial_expires);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            http_response_code(201);
            echo json_encode([
                "message" => "Usuário criado com sucesso",
                "user_id" => $user_id,
                "token" => $token,
                "subscription_status" => "trial",
                "trial_expires_at" => $trial_expires
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function login() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['email']) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode(["message" => "Email e senha são obrigatórios"]);
            return;
        }
        
        try {
            $query = "SELECT id, name, email, password_hash, subscription_status, subscription_expires_at 
                      FROM users WHERE email = :email";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':email', $data['email']);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($data['password'], $user['password_hash'])) {
                http_response_code(401);
                echo json_encode(["message" => "Credenciais inválidas"]);
                return;
            }
            
            // Gerar token
            $token = $this->auth->generateJWT($user['id'], $user['email']);
            
            echo json_encode([
                "message" => "Login realizado com sucesso",
                "token" => $token,
                "user" => [
                    "id" => $user['id'],
                    "name" => $user['name'],
                    "email" => $user['email'],
                    "subscription_status" => $user['subscription_status'],
                    "subscription_expires_at" => $user['subscription_expires_at']
                ]
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function refresh() {
        $auth_data = $this->auth->authenticate();
        
        try {
            // Buscar dados atualizados do usuário
            $query = "SELECT id, name, email, subscription_status, subscription_expires_at 
                      FROM users WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $auth_data->user_id);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            if (!$user) {
                http_response_code(404);
                echo json_encode(["message" => "Usuário não encontrado"]);
                return;
            }
            
            // Gerar novo token
            $token = $this->auth->generateJWT($user['id'], $user['email']);
            
            echo json_encode([
                "message" => "Token renovado com sucesso",
                "token" => $token,
                "user" => [
                    "id" => $user['id'],
                    "name" => $user['name'],
                    "email" => $user['email'],
                    "subscription_status" => $user['subscription_status'],
                    "subscription_expires_at" => $user['subscription_expires_at']
                ]
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    private function createDefaultCategories($user_id) {
        $categories = [
            ['name' => 'Vendas', 'type' => 'receita', 'icon' => 'shopping_cart', 'color' => '#4CAF50'],
            ['name' => 'Serviços', 'type' => 'receita', 'icon' => 'build', 'color' => '#2196F3'],
            ['name' => 'Outros Recebimentos', 'type' => 'receita', 'icon' => 'account_balance', 'color' => '#FF9800'],
            ['name' => 'Materiais/Produtos', 'type' => 'despesa', 'icon' => 'inventory', 'color' => '#F44336'],
            ['name' => 'Marketing', 'type' => 'despesa', 'icon' => 'campaign', 'color' => '#9C27B0'],
            ['name' => 'Transporte', 'type' => 'despesa', 'icon' => 'directions_car', 'color' => '#607D8B'],
            ['name' => 'Alimentação', 'type' => 'despesa', 'icon' => 'restaurant', 'color' => '#FF5722'],
            ['name' => 'Equipamentos', 'type' => 'despesa', 'icon' => 'computer', 'color' => '#795548'],
            ['name' => 'Taxas/Impostos', 'type' => 'despesa', 'icon' => 'receipt', 'color' => '#E91E63'],
            ['name' => 'Outros Gastos', 'type' => 'despesa', 'icon' => 'more_horiz', 'color' => '#9E9E9E']
        ];
        
        $query = "INSERT INTO categories (user_id, name, type, icon, color) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        
        foreach ($categories as $category) {
            $stmt->execute([$user_id, $category['name'], $category['type'], $category['icon'], $category['color']]);
        }
    }
}
?>