<?php
require_once __DIR__ . '/../../autoload.php';

class OfflineContingency {
    private $db;
    private $contingency_table = 'nfce_contingency';
    
    public function __construct($database) {
        $this->db = $database;
        $this->createContingencyTable();
    }
    
    /**
     * Cria tabela de contingência se não existir
     */
    private function createContingencyTable() {
        $query = "CREATE TABLE IF NOT EXISTS {$this->contingency_table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            establishment_id INT NOT NULL,
            nfce_data JSON NOT NULL,
            offline_number INT NOT NULL,
            offline_key VARCHAR(44) NOT NULL,
            xml_content TEXT,
            sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending',
            sync_attempts INT DEFAULT 0,
            sync_error TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            synced_at TIMESTAMP NULL,
            nfce_id INT NULL,
            FOREIGN KEY (establishment_id) REFERENCES establishments(id),
            FOREIGN KEY (nfce_id) REFERENCES nfce_emissions(id),
            INDEX idx_sync_status (sync_status),
            INDEX idx_establishment (establishment_id)
        )";
        
        $this->db->exec($query);
    }
    
    /**
     * Verifica se há conectividade com a SEFAZ
     */
    public function checkSEFAZConnectivity($establishment_data) {
        try {
            // Criar instância da integração SEFAZ
            $sefaz = new SEFAZIntegration($establishment_data);
            
            // Verificar status do serviço
            $status = $sefaz->checkServiceStatus();
            
            return $status['success'] && $status['status'] === 'online';
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Emite NFCe em modo offline
     */
    public function emitOfflineNFCe($nfce_data, $establishment_data) {
        try {
            // Gerar número offline sequencial
            $offline_number = $this->getNextOfflineNumber($establishment_data['id']);
            
            // Gerar chave offline (com identificador especial)
            $offline_key = $this->generateOfflineKey($establishment_data, $offline_number);
            
            // Preparar dados para contingência
            $contingency_data = [
                'establishment_id' => $establishment_data['id'],
                'nfce_data' => json_encode($nfce_data),
                'offline_number' => $offline_number,
                'offline_key' => $offline_key,
                'xml_content' => $this->generateOfflineXML($nfce_data, $establishment_data, $offline_key),
                'sync_status' => 'pending'
            ];
            
            // Inserir na tabela de contingência
            $contingency_id = $this->saveContingency($contingency_data);
            
            // Retornar NFCe offline
            return [
                'success' => true,
                'contingency_id' => $contingency_id,
                'nfce' => [
                    'id' => null,
                    'establishment_id' => $establishment_data['id'],
                    'nfce_number' => $offline_number,
                    'nfce_series' => 900, // Série especial para offline
                    'nfce_key' => $offline_key,
                    'customer_document' => $nfce_data['customer_document'] ?? null,
                    'customer_name' => $nfce_data['customer_name'] ?? null,
                    'customer_email' => $nfce_data['customer_email'] ?? null,
                    'customer_phone' => $nfce_data['customer_phone'] ?? null,
                    'total_products' => $nfce_data['total_products'],
                    'total_discounts' => $nfce_data['total_discounts'] ?? 0,
                    'total_tax' => $nfce_data['total_tax'] ?? 0,
                    'total_amount' => $nfce_data['total_amount'],
                    'payment_method' => $nfce_data['payment_method'],
                    'payment_amount' => $nfce_data['payment_amount'],
                    'change_amount' => $nfce_data['change_amount'] ?? 0,
                    'emission_date' => date('Y-m-d H:i:s'),
                    'status' => 'offline',
                    'xml_file_path' => null,
                    'pdf_file_path' => null,
                    'protocol_number' => null,
                    'authorization_date' => null,
                    'cancellation_reason' => null,
                    'items' => $nfce_data['items'],
                    'offline' => true,
                    'contingency_id' => $contingency_id
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro na emissão offline: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Sincroniza NFCes offline com a SEFAZ
     */
    public function syncOfflineNFCes($establishment_data) {
        try {
            // Verificar conectividade
            if (!$this->checkSEFAZConnectivity($establishment_data)) {
                return [
                    'success' => false,
                    'error' => 'SEFAZ offline - sincronização adiada'
                ];
            }
            
            // Buscar NFCes pendentes de sincronização
            $pending_nfces = $this->getPendingSync($establishment_data['id']);
            
            $sync_results = [
                'total' => count($pending_nfces),
                'synced' => 0,
                'errors' => 0,
                'details' => []
            ];
            
            foreach ($pending_nfces as $contingency) {
                $result = $this->syncSingleNFCe($contingency, $establishment_data);
                
                $sync_results['details'][] = [
                    'contingency_id' => $contingency['id'],
                    'offline_number' => $contingency['offline_number'],
                    'success' => $result['success'],
                    'message' => $result['message'] ?? null,
                    'nfce_id' => $result['nfce_id'] ?? null
                ];
                
                if ($result['success']) {
                    $sync_results['synced']++;
                } else {
                    $sync_results['errors']++;
                }
            }
            
            return [
                'success' => true,
                'sync_results' => $sync_results
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro na sincronização: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Sincroniza uma NFCe específica
     */
    private function syncSingleNFCe($contingency, $establishment_data) {
        try {
            $this->incrementSyncAttempts($contingency['id']);
            
            // Decodificar dados da NFCe
            $nfce_data = json_decode($contingency['nfce_data'], true);
            
            // Criar NFCe normal no sistema
            $nfce = new NFCe($this->db);
            
            // Gerar número e chave normais
            $series = $establishment_data['nfce_series'] ?? 1;
            $normal_number = $nfce->getNextNumber($establishment_data['id'], $series);
            $normal_key = $nfce->generateNFCeKey($establishment_data, $normal_number, $series);
            
            // Atualizar dados para emissão normal
            $nfce_data['nfce_number'] = $normal_number;
            $nfce_data['nfce_series'] = $series;
            $nfce_data['nfce_key'] = $normal_key;
            $nfce_data['status'] = 'pending';
            
            // Criar NFCe no banco principal
            $nfce_id = $nfce->create($nfce_data);
            
            if (!$nfce_id) {
                throw new Exception('Erro ao criar NFCe no banco principal');
            }
            
            // Tentar autorizar na SEFAZ
            $sefaz = new SEFAZIntegration($establishment_data);
            
            // Gerar XML normal
            $xml_generator = new NFCeController($this->db);
            $xml_result = $xml_generator->generateNFCeXML($nfce_data, $establishment_data);
            
            if (!$xml_result['success']) {
                throw new Exception('Erro ao gerar XML: ' . $xml_result['error']);
            }
            
            // Enviar para SEFAZ
            $sefaz_result = $sefaz->authorizeNFCe($xml_result['xml_content'], $normal_key);
            
            if ($sefaz_result['success']) {
                // Atualizar NFCe como autorizada
                $nfce->updateStatus($nfce_id, 'authorized', [
                    'protocol_number' => $sefaz_result['protocol_number'] ?? 'SYNC_' . date('YmdHis'),
                    'authorization_date' => date('Y-m-d H:i:s'),
                    'xml_file_path' => $xml_result['xml_path']
                ]);
                
                // Marcar contingência como sincronizada
                $this->markAsSynced($contingency['id'], $nfce_id);
                
                return [
                    'success' => true,
                    'message' => 'NFCe sincronizada com sucesso',
                    'nfce_id' => $nfce_id
                ];
            } else {
                // Marcar como erro
                $error_message = $sefaz_result['error'] ?? 'Erro desconhecido na SEFAZ';
                $this->markSyncError($contingency['id'], $error_message);
                
                return [
                    'success' => false,
                    'message' => $error_message
                ];
            }
            
        } catch (Exception $e) {
            $error_message = 'Erro na sincronização: ' . $e->getMessage();
            $this->markSyncError($contingency['id'], $error_message);
            
            return [
                'success' => false,
                'message' => $error_message
            ];
        }
    }
    
    /**
     * Gera próximo número offline
     */
    private function getNextOfflineNumber($establishment_id) {
        $query = "SELECT MAX(offline_number) as last_number 
                  FROM {$this->contingency_table} 
                  WHERE establishment_id = :establishment_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':establishment_id', $establishment_id);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $last_number = $result['last_number'] ?? 0;
        
        return $last_number + 1;
    }
    
    /**
     * Gera chave offline (com identificador especial)
     */
    private function generateOfflineKey($establishment_data, $offline_number) {
        // Usar série 900 para identificar como offline
        $series = 900;
        
        // UF do estabelecimento
        $uf_code = $this->getUFCode($establishment_data['state']);
        
        // Ano e mês
        $year_month = date('ym');
        
        // CNPJ
        $cnpj = preg_replace('/\D/', '', $establishment_data['document']);
        
        // Modelo 65 para NFCe
        $model = '65';
        
        // Série offline
        $series_padded = str_pad($series, 3, '0', STR_PAD_LEFT);
        
        // Número offline
        $number_padded = str_pad($offline_number, 9, '0', STR_PAD_LEFT);
        
        // Tipo de emissão 9 = Contingência offline
        $emission_type = '9';
        
        // Código numérico
        $numeric_code = str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        
        // Montar chave sem DV
        $key_without_dv = $uf_code . $year_month . $cnpj . $model . 
                         $series_padded . $number_padded . $emission_type . $numeric_code;
        
        // Calcular dígito verificador
        $dv = $this->calculateDV($key_without_dv);
        
        return $key_without_dv . $dv;
    }
    
    /**
     * Gera XML para contingência offline
     */
    private function generateOfflineXML($nfce_data, $establishment_data, $offline_key) {
        // XML simplificado para contingência
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<nfeProc versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">' . "\n";
        $xml .= '  <NFe xmlns="http://www.portalfiscal.inf.br/nfe">' . "\n";
        $xml .= '    <infNFe Id="NFe' . $offline_key . '">' . "\n";
        
        // Indicar emissão em contingência offline
        $xml .= '      <ide>' . "\n";
        $xml .= '        <tpEmis>9</tpEmis>' . "\n"; // Contingência offline
        $xml .= '        <dhCont>' . date('c') . '</dhCont>' . "\n";
        $xml .= '        <xJust>Emissão em contingência offline</xJust>' . "\n";
        $xml .= '      </ide>' . "\n";
        
        // Adicionar outros elementos necessários...
        
        $xml .= '    </infNFe>' . "\n";
        $xml .= '  </NFe>' . "\n";
        $xml .= '</nfeProc>' . "\n";
        
        return $xml;
    }
    
    /**
     * Salva dados de contingência
     */
    private function saveContingency($data) {
        $query = "INSERT INTO {$this->contingency_table} 
                  (establishment_id, nfce_data, offline_number, offline_key, 
                   xml_content, sync_status, created_at) 
                  VALUES 
                  (:establishment_id, :nfce_data, :offline_number, :offline_key, 
                   :xml_content, :sync_status, NOW())";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':establishment_id', $data['establishment_id']);
        $stmt->bindParam(':nfce_data', $data['nfce_data']);
        $stmt->bindParam(':offline_number', $data['offline_number']);
        $stmt->bindParam(':offline_key', $data['offline_key']);
        $stmt->bindParam(':xml_content', $data['xml_content']);
        $stmt->bindParam(':sync_status', $data['sync_status']);
        
        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        
        throw new Exception('Erro ao salvar dados de contingência');
    }
    
    /**
     * Busca NFCes pendentes de sincronização
     */
    private function getPendingSync($establishment_id, $limit = 50) {
        $query = "SELECT * FROM {$this->contingency_table} 
                  WHERE establishment_id = :establishment_id 
                  AND sync_status = 'pending' 
                  AND sync_attempts < 3
                  ORDER BY created_at ASC 
                  LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':establishment_id', $establishment_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Incrementa tentativas de sincronização
     */
    private function incrementSyncAttempts($contingency_id) {
        $query = "UPDATE {$this->contingency_table} 
                  SET sync_attempts = sync_attempts + 1 
                  WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $contingency_id);
        $stmt->execute();
    }
    
    /**
     * Marca contingência como sincronizada
     */
    private function markAsSynced($contingency_id, $nfce_id) {
        $query = "UPDATE {$this->contingency_table} 
                  SET sync_status = 'synced', 
                      synced_at = NOW(), 
                      nfce_id = :nfce_id 
                  WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $contingency_id);
        $stmt->bindParam(':nfce_id', $nfce_id);
        $stmt->execute();
    }
    
    /**
     * Marca erro na sincronização
     */
    private function markSyncError($contingency_id, $error) {
        $query = "UPDATE {$this->contingency_table} 
                  SET sync_status = 'error', 
                      sync_error = :error 
                  WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $contingency_id);
        $stmt->bindParam(':error', $error);
        $stmt->execute();
    }
    
    /**
     * Obtém estatísticas de contingência
     */
    public function getContingencyStatistics($establishment_id) {
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN sync_status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN sync_status = 'synced' THEN 1 ELSE 0 END) as synced,
                    SUM(CASE WHEN sync_status = 'error' THEN 1 ELSE 0 END) as errors
                  FROM {$this->contingency_table} 
                  WHERE establishment_id = :establishment_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':establishment_id', $establishment_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Utilitários
    private function getUFCode($state) {
        $uf_codes = [
            'AC' => 12, 'AL' => 17, 'AP' => 16, 'AM' => 23, 'BA' => 29,
            'CE' => 23, 'DF' => 53, 'ES' => 32, 'GO' => 52, 'MA' => 21,
            'MT' => 51, 'MS' => 50, 'MG' => 31, 'PA' => 15, 'PB' => 25,
            'PR' => 41, 'PE' => 26, 'PI' => 22, 'RJ' => 33, 'RN' => 24,
            'RS' => 43, 'RO' => 11, 'RR' => 14, 'SC' => 42, 'SP' => 35,
            'SE' => 28, 'TO' => 17
        ];
        
        return $uf_codes[$state] ?? 35;
    }
    
    private function calculateDV($key) {
        $sequence = '4329876543298765432987654329876543298765432';
        $sum = 0;
        
        for ($i = 0; $i < 43; $i++) {
            $sum += $key[$i] * $sequence[$i];
        }
        
        $remainder = $sum % 11;
        
        if ($remainder < 2) {
            return 0;
        } else {
            return 11 - $remainder;
        }
    }
}
?>