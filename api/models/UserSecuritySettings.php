<?php
/**
 * Modelo para configurações de segurança do usuário
 */

class UserSecuritySettings {
    private $conn;
    private $table_name = "user_security_settings";
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function getByUserId($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function createOrUpdate($data) {
        // Verificar se já existe
        $existing = $this->getByUserId($data['user_id']);
        
        if ($existing) {
            return $this->update($data);
        } else {
            return $this->create($data);
        }
    }
    
    private function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, two_factor_enabled, biometric_type, device_id, backup_codes) 
                  VALUES (:user_id, :two_factor_enabled, :biometric_type, :device_id, :backup_codes)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindValue(':user_id', $data['user_id']);
        $stmt->bindValue(':two_factor_enabled', $data['two_factor_enabled'] ?? false);
        $stmt->bindValue(':biometric_type', $data['biometric_type'] ?? 'none');
        $stmt->bindValue(':device_id', $data['device_id'] ?? null);
        $stmt->bindValue(':backup_codes', isset($data['backup_codes']) ? json_encode($data['backup_codes']) : null);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        throw new Exception("Erro ao criar configurações de segurança");
    }
    
    private function update($data) {
        $query = "UPDATE " . $this->table_name . " 
                  SET two_factor_enabled = :two_factor_enabled,
                      biometric_type = :biometric_type,
                      device_id = :device_id,
                      backup_codes = :backup_codes,
                      last_biometric_setup = CASE 
                          WHEN :biometric_type != 'none' THEN NOW() 
                          ELSE last_biometric_setup 
                      END,
                      updated_at = NOW()
                  WHERE user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindValue(':user_id', $data['user_id']);
        $stmt->bindValue(':two_factor_enabled', $data['two_factor_enabled'] ?? false);
        $stmt->bindValue(':biometric_type', $data['biometric_type'] ?? 'none');
        $stmt->bindValue(':device_id', $data['device_id'] ?? null);
        $stmt->bindValue(':backup_codes', isset($data['backup_codes']) ? json_encode($data['backup_codes']) : null);
        
        if ($stmt->execute()) {
            return true;
        }
        
        throw new Exception("Erro ao atualizar configurações de segurança");
    }
    
    public function generateBackupCodes() {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            // Gera códigos de 8 dígitos
            $codes[] = sprintf('%04d-%04d', rand(1000, 9999), rand(1000, 9999));
        }
        return $codes;
    }
    
    public function verifyBackupCode($user_id, $code) {
        $settings = $this->getByUserId($user_id);
        
        if (!$settings || !$settings['backup_codes']) {
            return false;
        }
        
        $backup_codes = json_decode($settings['backup_codes'], true);
        
        if (in_array($code, $backup_codes)) {
            // Remove o código usado
            $backup_codes = array_filter($backup_codes, function($c) use ($code) {
                return $c !== $code;
            });
            
            // Atualiza no banco
            $this->update([
                'user_id' => $user_id,
                'two_factor_enabled' => $settings['two_factor_enabled'],
                'biometric_type' => $settings['biometric_type'],
                'device_id' => $settings['device_id'],
                'backup_codes' => array_values($backup_codes)
            ]);
            
            return true;
        }
        
        return false;
    }
    
    public function enableTwoFactor($user_id, $biometric_type, $device_id = null) {
        $backup_codes = $this->generateBackupCodes();
        
        return $this->createOrUpdate([
            'user_id' => $user_id,
            'two_factor_enabled' => true,
            'biometric_type' => $biometric_type,
            'device_id' => $device_id,
            'backup_codes' => $backup_codes
        ]);
    }
    
    public function disableTwoFactor($user_id) {
        return $this->createOrUpdate([
            'user_id' => $user_id,
            'two_factor_enabled' => false,
            'biometric_type' => 'none',
            'device_id' => null,
            'backup_codes' => []
        ]);
    }
}
?>