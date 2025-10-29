<?php
// Iniciar buffer de saída e limpar qualquer conteúdo anterior
ob_start();
ob_clean();

require_once '../config/Database.php';
require_once '../middleware/AuthMiddleware.php';
require_once '../middleware/CorsMiddleware.php';

// Configurar CORS e headers
CorsMiddleware::handle();

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

class RegisterController {
    private $db;
    private $auth;
    
    private function jsonResponse($data, $status_code = 200) {
        // Limpar qualquer output anterior
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Definir headers
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($status_code);
        }
        
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(500);
            $json = json_encode([
                'success' => false,
                'message' => 'Erro ao gerar JSON: ' . json_last_error_msg()
            ], JSON_UNESCAPED_UNICODE);
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
    
    public function register() {
        try {
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true);
            
            if (!$input) {
                // Log do erro para debug
                error_log("Erro JSON: " . json_last_error_msg() . " - Input: " . $rawInput);
                throw new Exception('Dados inválidos - JSON mal formatado');
            }
            
            // Validar dados obrigatórios
            $name = trim($input['name'] ?? '');
            $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
            $password = $input['password'] ?? '';
            $confirm_password = $input['confirm_password'] ?? $input['password']; // Fallback se não for enviado
            $phone = trim($input['phone'] ?? '');
            $document = trim($input['document'] ?? '');
            
            // Validações
            if (empty($name)) {
                throw new Exception('Nome é obrigatório');
            }
            
            if (strlen($name) < 2) {
                throw new Exception('Nome deve ter pelo menos 2 caracteres');
            }
            
            if (!$email) {
                throw new Exception('Email inválido');
            }
            
            if (empty($password)) {
                throw new Exception('Senha é obrigatória');
            }
            
            if (strlen($password) < 6) {
                throw new Exception('Senha deve ter pelo menos 6 caracteres');
            }
            
            if ($password !== $confirm_password) {
                throw new Exception('Senhas não coincidem');
            }
            
            // Verificar se email já existe
            if ($this->emailExists($email)) {
                throw new Exception('Este email já está cadastrado');
            }
            
            // Validar documento se fornecido
            if (!empty($document)) {
                $document = preg_replace('/\D/', '', $document);
                if (!$this->validateDocument($document)) {
                    throw new Exception('Documento inválido');
                }
            }
            
            // Validar telefone se fornecido
            if (!empty($phone)) {
                $phone = preg_replace('/\D/', '', $phone);
                if (strlen($phone) < 10 || strlen($phone) > 11) {
                    throw new Exception('Telefone inválido');
                }
            }
            
            // Criar usuário
            $user_id = $this->createUser($name, $email, $password, $phone, $document);
            
            // Criar assinatura trial
            $this->createTrialSubscription($user_id);
            
            // Gerar token JWT
            $token = $this->auth->generateJWT($user_id, $email);
            
            // Buscar dados do usuário criado
            $user = $this->getUserById($user_id);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Conta criada com sucesso! Você ganhou 7 dias grátis.',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $user['id'],
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'status' => $user['status'],
                        'created_at' => $user['created_at']
                    ]
                ]
            ], 201);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    private function emailExists($email) {
        $query = "SELECT id FROM users WHERE email = :email";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }
    
    private function validateDocument($document) {
        // Validar CPF ou CNPJ
        if (strlen($document) === 11) {
            return $this->validateCPF($document);
        } elseif (strlen($document) === 14) {
            return $this->validateCNPJ($document);
        }
        return false;
    }
    
    private function validateCPF($cpf) {
        // Algoritmo de validação de CPF
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false; // CPF com todos os dígitos iguais
        }
        
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        return true;
    }
    
    private function validateCNPJ($cnpj) {
        // Algoritmo de validação de CNPJ
        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false; // CNPJ com todos os dígitos iguais
        }
        
        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += $cnpj[$i] * $weights1[$i];
        }
        $remainder = $sum % 11;
        $digit1 = $remainder < 2 ? 0 : 11 - $remainder;
        
        if ($cnpj[12] != $digit1) {
            return false;
        }
        
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += $cnpj[$i] * $weights2[$i];
        }
        $remainder = $sum % 11;
        $digit2 = $remainder < 2 ? 0 : 11 - $remainder;
        
        return $cnpj[13] == $digit2;
    }
    
    private function createUser($name, $email, $password, $phone, $document) {
        // Garantir que as tabelas existam
        $this->createTablesIfNotExists();
        
        $query = "INSERT INTO users (name, email, password, phone, document, status, created_at) 
                  VALUES (:name, :email, :password, :phone, :document, 'active', NOW())";
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':document', $document);
        
        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        
        throw new Exception('Erro ao criar usuário');
    }
    
    private function createTrialSubscription($user_id) {
        $trial_days = 7;
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$trial_days} days"));
        
        $query = "INSERT INTO subscriptions (user_id, plan_type, status, started_at, expires_at) 
                  VALUES (:user_id, 'trial', 'active', NOW(), :expires_at)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':expires_at', $expires_at);
        
        return $stmt->execute();
    }
    
    private function getUserById($user_id) {
        $query = "SELECT id, name, email, status, created_at FROM users WHERE id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function createTablesIfNotExists() {
        // Criar tabela de usuários
        $users_query = "CREATE TABLE IF NOT EXISTS users (
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
        
        $this->db->exec($users_query);
        
        // Criar tabela de assinaturas
        $subscriptions_query = "CREATE TABLE IF NOT EXISTS subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            plan_type ENUM('trial', 'monthly', 'annual') DEFAULT 'trial',
            status ENUM('active', 'inactive', 'cancelled', 'expired') DEFAULT 'active',
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            mercadopago_subscription_id VARCHAR(255) NULL,
            mercadopago_preapproval_id VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_status (user_id, status),
            INDEX idx_expires (expires_at)
        )";
        
        $this->db->exec($subscriptions_query);
    }
    
    public function checkDatabaseConnection() {
        try {
            $this->createTablesIfNotExists();
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Conexão com banco de dados OK - Tabelas criadas',
                'database' => 'mei_financeiro_db'
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Erro de conexão: ' . $e->getMessage()
            ], 500);
        }
    }
}

// Processar requisição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new RegisterController();
    $controller->register();
} else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['check'])) {
    $controller = new RegisterController();
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