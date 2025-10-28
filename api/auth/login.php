<?php
// Iniciar buffer de saída e limpar qualquer conteúdo anterior
ob_start();
ob_clean();

require_once '../config/Database.php';
require_once '../middleware/AuthMiddleware.php';

// Definir content type
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

class LoginController {
    private $db;
    private $auth;
    
    private function jsonResponse($data, $status_code = 200) {
        // Limpar qualquer output anterior
        ob_clean();
        
        http_response_code($status_code);
        
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(500);
            $json = json_encode([
                'success' => false,
                'message' => 'Erro ao gerar JSON: ' . json_last_error_msg()
            ]);
        }
        
        echo $json;
        exit;
    }
    
    public function __construct() {
        try {
            $database = new Database();
            $this->db = $database->getConnection();
            $this->auth = new AuthMiddleware();
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Erro de conexão com o banco de dados: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function login() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                throw new Exception('Dados inválidos');
            }
            
            $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
            $password = $input['password'] ?? '';
            
            if (!$email) {
                throw new Exception('Email inválido');
            }
            
            if (empty($password)) {
                throw new Exception('Senha é obrigatória');
            }
            
            // Buscar usuário
            $user = $this->getUserByEmail($email);
            
            if (!$user) {
                throw new Exception('Email ou senha incorretos');
            }
            
            // Verificar senha
            if (!password_verify($password, $user['password'])) {
                throw new Exception('Email ou senha incorretos');
            }
            
            // Verificar se usuário está ativo
            if ($user['status'] !== 'active') {
                throw new Exception('Conta inativa. Entre em contato com o suporte.');
            }
            
            // Gerar token JWT
            $token = $this->auth->generateJWT($user['id'], $user['email']);
            
            // Atualizar último login
            $this->updateLastLogin($user['id']);
            
            // Buscar dados do perfil completo
            $profile = $this->getUserProfile($user['id']);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Login realizado com sucesso',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $user['id'],
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'status' => $user['status'],
                        'created_at' => $user['created_at'],
                        'profile' => $profile
                    ]
                ]
            ], 200);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    private function getUserByEmail($email) {
        $query = "SELECT id, name, email, password, status, created_at, last_login 
                  FROM users 
                  WHERE email = :email";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function updateLastLogin($user_id) {
        $query = "UPDATE users SET last_login = NOW() WHERE id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
    }
    
    private function getUserProfile($user_id) {
        // Buscar dados adicionais do perfil se existirem
        $query = "SELECT 
                    u.*,
                    s.status as subscription_status,
                    s.plan_type,
                    s.expires_at as subscription_expires
                  FROM users u
                  LEFT JOIN subscriptions s ON u.id = s.user_id AND s.status = 'active'
                  WHERE u.id = :user_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Remover dados sensíveis
        unset($profile['password']);
        
        return $profile;
    }
    
    public function checkDatabaseConnection() {
        try {
            // Tentar criar tabela de usuários se não existir
            $this->createUsersTable();
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Conexão com banco de dados OK',
                'database' => 'mei_financeiro_db'
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Erro de conexão: ' . $e->getMessage()
            ], 500);
        }
    }
    
    private function createUsersTable() {
        $query = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NULL,
            document VARCHAR(20) NULL,
            status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
            email_verified BOOLEAN DEFAULT FALSE,
            email_verification_token VARCHAR(255) NULL,
            password_reset_token VARCHAR(255) NULL,
            password_reset_expires TIMESTAMP NULL,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_status (status)
        )";
        
        $this->db->exec($query);
        
        // Criar tabela de assinaturas se não existir
        $subscription_query = "CREATE TABLE IF NOT EXISTS subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            plan_type ENUM('trial', 'monthly', 'annual') DEFAULT 'trial',
            status ENUM('active', 'inactive', 'cancelled', 'expired') DEFAULT 'trial',
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_status (user_id, status)
        )";
        
        $this->db->exec($subscription_query);
    }
}

// Processar requisição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new LoginController();
    $controller->login();
} else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['check'])) {
    $controller = new LoginController();
    $controller->checkDatabaseConnection();
} else {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido'
    ], JSON_UNESCAPED_UNICODE);
}
?>