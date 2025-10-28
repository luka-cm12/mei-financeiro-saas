<?php

class Establishment {
    private $conn;
    private $table_name = "establishments";

    public $id;
    public $user_id;
    public $business_name;
    public $trade_name;
    public $document_type;
    public $document;
    public $state_registration;
    public $municipal_registration;
    
    // Endereço
    public $zip_code;
    public $street;
    public $number;
    public $complement;
    public $neighborhood;
    public $city;
    public $state;
    public $country;
    
    // Contato
    public $phone;
    public $email;
    public $website;
    
    // Fiscal
    public $tax_regime;
    public $cnae_main;
    public $cnaes_secondary;
    
    // NFCe
    public $nfce_enabled;
    public $nfce_environment;
    public $nfce_series;
    public $nfce_next_number;
    public $nfce_csc;
    public $nfce_csc_id;
    
    // Certificado digital
    public $digital_certificate_type;
    public $certificate_file_path;
    public $certificate_password;
    public $certificate_expires_at;
    public $certificate_uploaded_at;
    
    public $is_active;
    public $fiscal_status;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Criar estabelecimento
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                 SET user_id = :user_id,
                     business_name = :business_name,
                     trade_name = :trade_name,
                     document_type = :document_type,
                     document = :document,
                     state_registration = :state_registration,
                     municipal_registration = :municipal_registration,
                     zip_code = :zip_code,
                     street = :street,
                     number = :number,
                     complement = :complement,
                     neighborhood = :neighborhood,
                     city = :city,
                     state = :state,
                     country = :country,
                     phone = :phone,
                     email = :email,
                     website = :website,
                     tax_regime = :tax_regime,
                     cnae_main = :cnae_main,
                     cnaes_secondary = :cnaes_secondary";

        $stmt = $this->conn->prepare($query);

        // Sanitizar dados
        $this->business_name = htmlspecialchars(strip_tags($this->business_name));
        $this->trade_name = htmlspecialchars(strip_tags($this->trade_name));
        $this->document = preg_replace('/[^0-9]/', '', $this->document);

        // Bind values
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":business_name", $this->business_name);
        $stmt->bindParam(":trade_name", $this->trade_name);
        $stmt->bindParam(":document_type", $this->document_type);
        $stmt->bindParam(":document", $this->document);
        $stmt->bindParam(":state_registration", $this->state_registration);
        $stmt->bindParam(":municipal_registration", $this->municipal_registration);
        $stmt->bindParam(":zip_code", $this->zip_code);
        $stmt->bindParam(":street", $this->street);
        $stmt->bindParam(":number", $this->number);
        $stmt->bindParam(":complement", $this->complement);
        $stmt->bindParam(":neighborhood", $this->neighborhood);
        $stmt->bindParam(":city", $this->city);
        $stmt->bindParam(":state", $this->state);
        $stmt->bindParam(":country", $this->country);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":website", $this->website);
        $stmt->bindParam(":tax_regime", $this->tax_regime);
        $stmt->bindParam(":cnae_main", $this->cnae_main);
        $stmt->bindParam(":cnaes_secondary", $this->cnaes_secondary);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Ler estabelecimento por user_id
     */
    public function readByUserId($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = ? AND is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->mapFromArray($row);
            return true;
        }

        return false;
    }

    /**
     * Atualizar estabelecimento
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                 SET business_name = :business_name,
                     trade_name = :trade_name,
                     document_type = :document_type,
                     document = :document,
                     state_registration = :state_registration,
                     municipal_registration = :municipal_registration,
                     zip_code = :zip_code,
                     street = :street,
                     number = :number,
                     complement = :complement,
                     neighborhood = :neighborhood,
                     city = :city,
                     state = :state,
                     country = :country,
                     phone = :phone,
                     email = :email,
                     website = :website,
                     tax_regime = :tax_regime,
                     cnae_main = :cnae_main,
                     cnaes_secondary = :cnaes_secondary,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND user_id = :user_id";

        $stmt = $this->conn->prepare($query);

        // Sanitizar dados
        $this->business_name = htmlspecialchars(strip_tags($this->business_name));
        $this->trade_name = htmlspecialchars(strip_tags($this->trade_name));
        $this->document = preg_replace('/[^0-9]/', '', $this->document);

        // Bind values
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":business_name", $this->business_name);
        $stmt->bindParam(":trade_name", $this->trade_name);
        $stmt->bindParam(":document_type", $this->document_type);
        $stmt->bindParam(":document", $this->document);
        $stmt->bindParam(":state_registration", $this->state_registration);
        $stmt->bindParam(":municipal_registration", $this->municipal_registration);
        $stmt->bindParam(":zip_code", $this->zip_code);
        $stmt->bindParam(":street", $this->street);
        $stmt->bindParam(":number", $this->number);
        $stmt->bindParam(":complement", $this->complement);
        $stmt->bindParam(":neighborhood", $this->neighborhood);
        $stmt->bindParam(":city", $this->city);
        $stmt->bindParam(":state", $this->state);
        $stmt->bindParam(":country", $this->country);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":website", $this->website);
        $stmt->bindParam(":tax_regime", $this->tax_regime);
        $stmt->bindParam(":cnae_main", $this->cnae_main);
        $stmt->bindParam(":cnaes_secondary", $this->cnaes_secondary);

        return $stmt->execute();
    }

    /**
     * Atualizar configurações NFCe
     */
    public function updateNFCeConfig($nfce_config) {
        $query = "UPDATE " . $this->table_name . "
                 SET nfce_enabled = :nfce_enabled,
                     nfce_environment = :nfce_environment,
                     nfce_series = :nfce_series,
                     nfce_csc = :nfce_csc,
                     nfce_csc_id = :nfce_csc_id,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND user_id = :user_id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":nfce_enabled", $nfce_config['enabled']);
        $stmt->bindParam(":nfce_environment", $nfce_config['environment']);
        $stmt->bindParam(":nfce_series", $nfce_config['series']);
        $stmt->bindParam(":nfce_csc", $nfce_config['csc']);
        $stmt->bindParam(":nfce_csc_id", $nfce_config['csc_id']);

        return $stmt->execute();
    }

    /**
     * Atualizar certificado digital
     */
    public function updateDigitalCertificate($cert_data) {
        $query = "UPDATE " . $this->table_name . "
                 SET digital_certificate_type = :cert_type,
                     certificate_file_path = :file_path,
                     certificate_password = :password,
                     certificate_expires_at = :expires_at,
                     certificate_uploaded_at = CURRENT_TIMESTAMP,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND user_id = :user_id";

        $stmt = $this->conn->prepare($query);

        // Criptografar senha do certificado
        $encrypted_password = $cert_data['password'] ? base64_encode($cert_data['password']) : null;

        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":cert_type", $cert_data['type']);
        $stmt->bindParam(":file_path", $cert_data['file_path']);
        $stmt->bindParam(":password", $encrypted_password);
        $stmt->bindParam(":expires_at", $cert_data['expires_at']);

        return $stmt->execute();
    }

    /**
     * Obter próximo número de NFCe
     */
    public function getNextNFCeNumber() {
        $query = "SELECT nfce_next_number FROM " . $this->table_name . " WHERE id = ? FOR UPDATE";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $next_number = $result['nfce_next_number'];
        
        // Incrementar para próxima
        $update_query = "UPDATE " . $this->table_name . " SET nfce_next_number = nfce_next_number + 1 WHERE id = ?";
        $update_stmt = $this->conn->prepare($update_query);
        $update_stmt->bindParam(1, $this->id);
        $update_stmt->execute();
        
        return $next_number;
    }

    /**
     * Validar documento (CPF/CNPJ)
     */
    public function validateDocument($document, $type) {
        $document = preg_replace('/[^0-9]/', '', $document);
        
        if ($type === 'cpf') {
            return $this->validateCPF($document);
        } elseif ($type === 'cnpj') {
            return $this->validateCNPJ($document);
        }
        
        return false;
    }

    /**
     * Validar CPF
     */
    private function validateCPF($cpf) {
        if (strlen($cpf) !== 11) return false;
        if (preg_match('/(\d)\1{10}/', $cpf)) return false;

        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) return false;
        }

        return true;
    }

    /**
     * Validar CNPJ
     */
    private function validateCNPJ($cnpj) {
        if (strlen($cnpj) !== 14) return false;
        if (preg_match('/(\d)\1{13}/', $cnpj)) return false;

        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $weights2 = [6, 7, 8, 9, 2, 3, 4, 5, 6, 7, 8, 9];

        for ($i = 0, $sum = 0; $i < 12; $i++) {
            $sum += $cnpj[$i] * $weights1[$i];
        }
        $digit1 = $sum % 11 < 2 ? 0 : 11 - ($sum % 11);

        for ($i = 0, $sum = 0; $i < 13; $i++) {
            $sum += $cnpj[$i] * $weights2[$i];
        }
        $digit2 = $sum % 11 < 2 ? 0 : 11 - ($sum % 11);

        return ($cnpj[12] == $digit1 && $cnpj[13] == $digit2);
    }

    /**
     * Mapear array para propriedades
     */
    private function mapFromArray($data) {
        $this->id = $data['id'];
        $this->user_id = $data['user_id'];
        $this->business_name = $data['business_name'];
        $this->trade_name = $data['trade_name'];
        $this->document_type = $data['document_type'];
        $this->document = $data['document'];
        $this->state_registration = $data['state_registration'];
        $this->municipal_registration = $data['municipal_registration'];
        $this->zip_code = $data['zip_code'];
        $this->street = $data['street'];
        $this->number = $data['number'];
        $this->complement = $data['complement'];
        $this->neighborhood = $data['neighborhood'];
        $this->city = $data['city'];
        $this->state = $data['state'];
        $this->country = $data['country'];
        $this->phone = $data['phone'];
        $this->email = $data['email'];
        $this->website = $data['website'];
        $this->tax_regime = $data['tax_regime'];
        $this->cnae_main = $data['cnae_main'];
        $this->cnaes_secondary = $data['cnaes_secondary'];
        $this->nfce_enabled = $data['nfce_enabled'];
        $this->nfce_environment = $data['nfce_environment'];
        $this->nfce_series = $data['nfce_series'];
        $this->nfce_next_number = $data['nfce_next_number'];
        $this->nfce_csc = $data['nfce_csc'];
        $this->nfce_csc_id = $data['nfce_csc_id'];
        $this->digital_certificate_type = $data['digital_certificate_type'];
        $this->certificate_file_path = $data['certificate_file_path'];
        $this->certificate_password = $data['certificate_password'];
        $this->certificate_expires_at = $data['certificate_expires_at'];
        $this->certificate_uploaded_at = $data['certificate_uploaded_at'];
        $this->is_active = $data['is_active'];
        $this->fiscal_status = $data['fiscal_status'];
        $this->created_at = $data['created_at'];
        $this->updated_at = $data['updated_at'];
    }
    
    /**
     * Busca estabelecimento por user_id (retorna array)
     */
    public function getByUserId($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id AND is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>