<?php
/**
 * Controlador para autenticação de dois fatores
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/UserSecuritySettings.php';

class TwoFactorController {
    private $db;
    private $auth;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auth = new AuthMiddleware();
    }
    
    public function getSettings() {
        $auth_data = $this->auth->authenticate();
        
        try {
            $security = new UserSecuritySettings($this->db);
            $settings = $security->getByUserId($auth_data->user_id);
            
            if ($settings) {
                // Remove códigos de backup da resposta por segurança
                unset($settings['backup_codes']);
                unset($settings['device_id']);
            } else {
                $settings = [
                    'two_factor_enabled' => false,
                    'biometric_type' => 'none',
                    'last_biometric_setup' => null
                ];
            }
            
            echo json_encode([
                "settings" => $settings,
                "message" => "Configurações carregadas"
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function enableTwoFactor() {
        $auth_data = $this->auth->authenticate();
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['biometric_type'])) {
            http_response_code(400);
            echo json_encode(["message" => "Tipo de biometria é obrigatório"]);
            return;
        }
        
        $valid_types = ['fingerprint', 'face', 'both'];
        if (!in_array($input['biometric_type'], $valid_types)) {
            http_response_code(400);
            echo json_encode(["message" => "Tipo de biometria inválido"]);
            return;
        }
        
        try {
            $security = new UserSecuritySettings($this->db);
            $security->enableTwoFactor(
                $auth_data->user_id,
                $input['biometric_type'],
                $input['device_id'] ?? null
            );
            
            // Gerar códigos de backup para mostrar ao usuário
            $backup_codes = $security->generateBackupCodes();
            
            echo json_encode([
                "message" => "Autenticação de dois fatores ativada",
                "backup_codes" => $backup_codes,
                "warning" => "Guarde esses códigos em local seguro. Eles serão mostrados apenas uma vez."
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function disableTwoFactor() {
        $auth_data = $this->auth->authenticate();
        $input = json_decode(file_get_contents('php://input'), true);
        
        try {
            $security = new UserSecuritySettings($this->db);
            $current_settings = $security->getByUserId($auth_data->user_id);
            
            if (!$current_settings || !$current_settings['two_factor_enabled']) {
                http_response_code(400);
                echo json_encode(["message" => "Autenticação de dois fatores não está ativada"]);
                return;
            }
            
            // Se tem código de backup, verificar
            if (isset($input['backup_code'])) {
                if (!$security->verifyBackupCode($auth_data->user_id, $input['backup_code'])) {
                    http_response_code(400);
                    echo json_encode(["message" => "Código de backup inválido"]);
                    return;
                }
            }
            
            $security->disableTwoFactor($auth_data->user_id);
            
            echo json_encode([
                "message" => "Autenticação de dois fatores desativada"
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function verifyBiometric() {
        $auth_data = $this->auth->authenticate();
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['biometric_verified']) || !$input['biometric_verified']) {
            http_response_code(400);
            echo json_encode(["message" => "Verificação biométrica falhou"]);
            return;
        }
        
        try {
            $security = new UserSecuritySettings($this->db);
            $settings = $security->getByUserId($auth_data->user_id);
            
            if (!$settings || !$settings['two_factor_enabled']) {
                http_response_code(400);
                echo json_encode(["message" => "Autenticação de dois fatores não configurada"]);
                return;
            }
            
            // Verificar device_id se fornecido
            if ($settings['device_id'] && isset($input['device_id'])) {
                if ($settings['device_id'] !== $input['device_id']) {
                    http_response_code(400);
                    echo json_encode(["message" => "Dispositivo não reconhecido"]);
                    return;
                }
            }
            
            echo json_encode([
                "message" => "Verificação biométrica bem-sucedida",
                "verified" => true
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function verifyBackupCode() {
        $auth_data = $this->auth->authenticate();
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['backup_code'])) {
            http_response_code(400);
            echo json_encode(["message" => "Código de backup é obrigatório"]);
            return;
        }
        
        try {
            $security = new UserSecuritySettings($this->db);
            
            if ($security->verifyBackupCode($auth_data->user_id, $input['backup_code'])) {
                echo json_encode([
                    "message" => "Código de backup válido",
                    "verified" => true
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    "message" => "Código de backup inválido ou já utilizado"
                ]);
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function regenerateBackupCodes() {
        $auth_data = $this->auth->authenticate();
        
        try {
            $security = new UserSecuritySettings($this->db);
            $settings = $security->getByUserId($auth_data->user_id);
            
            if (!$settings || !$settings['two_factor_enabled']) {
                http_response_code(400);
                echo json_encode(["message" => "Autenticação de dois fatores não está ativada"]);
                return;
            }
            
            $new_codes = $security->generateBackupCodes();
            
            // Atualizar com novos códigos
            $security->createOrUpdate([
                'user_id' => $auth_data->user_id,
                'two_factor_enabled' => $settings['two_factor_enabled'],
                'biometric_type' => $settings['biometric_type'],
                'device_id' => $settings['device_id'],
                'backup_codes' => $new_codes
            ]);
            
            echo json_encode([
                "message" => "Novos códigos de backup gerados",
                "backup_codes" => $new_codes,
                "warning" => "Os códigos antigos foram invalidados"
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
}
?>