<?php
require_once __DIR__ . '/../../autoload.php';

class FiscalReports {
    private $db;
    private $report_types;
    
    public function __construct($database) {
        $this->db = $database;
        $this->report_types = [
            'sped' => 'Sistema Público de Escrituração Digital',
            'dte' => 'Documento Tributário Eletrônico',
            'dimob' => 'Declaração de Informações sobre Atividades Imobiliárias',
            'defis' => 'Declaração de Informações Socioeconômicas e Fiscais',
            'pgdas' => 'Programa Gerador do DAS',
            'dasn' => 'Declaração Anual do Simples Nacional'
        ];
        
        $this->createReportTables();
    }
    
    /**
     * Cria tabelas necessárias para relatórios
     */
    private function createReportTables() {
        // Tabela de relatórios gerados
        $query1 = "CREATE TABLE IF NOT EXISTS fiscal_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            establishment_id INT NOT NULL,
            report_type VARCHAR(50) NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            file_path VARCHAR(500) NULL,
            file_size INT NULL,
            status ENUM('generating', 'completed', 'error') DEFAULT 'generating',
            error_message TEXT NULL,
            generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            downloaded_at TIMESTAMP NULL,
            hash VARCHAR(64) NULL,
            FOREIGN KEY (establishment_id) REFERENCES establishments(id),
            INDEX idx_establishment_type (establishment_id, report_type),
            INDEX idx_period (period_start, period_end)
        )";
        
        // Tabela de configurações de relatórios por estabelecimento
        $query2 = "CREATE TABLE IF NOT EXISTS establishment_report_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            establishment_id INT NOT NULL UNIQUE,
            sped_enabled BOOLEAN DEFAULT true,
            dte_enabled BOOLEAN DEFAULT true,
            auto_generate_monthly BOOLEAN DEFAULT true,
            email_reports BOOLEAN DEFAULT false,
            email_recipients JSON NULL,
            retention_months INT DEFAULT 12,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (establishment_id) REFERENCES establishments(id)
        )";
        
        // Tabela de dados fiscais consolidados
        $query3 = "CREATE TABLE IF NOT EXISTS fiscal_data_consolidated (
            id INT AUTO_INCREMENT PRIMARY KEY,
            establishment_id INT NOT NULL,
            reference_month DATE NOT NULL,
            total_nfce_count INT DEFAULT 0,
            total_revenue DECIMAL(15,2) DEFAULT 0,
            total_tax_icms DECIMAL(15,2) DEFAULT 0,
            total_tax_pis DECIMAL(15,2) DEFAULT 0,
            total_tax_cofins DECIMAL(15,2) DEFAULT 0,
            cancellation_count INT DEFAULT 0,
            cancellation_value DECIMAL(15,2) DEFAULT 0,
            consolidated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (establishment_id) REFERENCES establishments(id),
            UNIQUE KEY uk_establishment_month (establishment_id, reference_month)
        )";
        
        $this->db->exec($query1);
        $this->db->exec($query2);
        $this->db->exec($query3);
    }
    
    /**
     * Gera relatório SPED Fiscal
     */
    public function generateSPEDReport($establishment_id, $period_start, $period_end) {
        try {
            $report_id = $this->createReportRecord($establishment_id, 'sped', $period_start, $period_end);
            
            // Buscar dados do estabelecimento
            $establishment = $this->getEstablishmentData($establishment_id);
            if (!$establishment) {
                throw new Exception('Estabelecimento não encontrado');
            }
            
            // Buscar dados fiscais do período
            $fiscal_data = $this->getFiscalDataForPeriod($establishment_id, $period_start, $period_end);
            
            // Gerar arquivo SPED
            $sped_content = $this->buildSPEDContent($establishment, $fiscal_data, $period_start, $period_end);
            
            // Salvar arquivo
            $filename = "SPED_{$establishment['document']}_{$period_start}_{$period_end}.txt";
            $file_path = $this->saveReportFile($filename, $sped_content, 'sped');
            
            // Atualizar registro
            $this->completeReportRecord($report_id, $file_path, strlen($sped_content));
            
            return [
                'success' => true,
                'report_id' => $report_id,
                'file_path' => $file_path,
                'filename' => $filename
            ];
            
        } catch (Exception $e) {
            if (isset($report_id)) {
                $this->markReportError($report_id, $e->getMessage());
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Constrói conteúdo do arquivo SPED
     */
    private function buildSPEDContent($establishment, $fiscal_data, $period_start, $period_end) {
        $lines = [];
        
        // Registro 0000 - Abertura do arquivo digital
        $lines[] = $this->buildSPEDLine0000($establishment, $period_start, $period_end);
        
        // Registro 0001 - Abertura do bloco 0
        $lines[] = '|0001|0|';
        
        // Registro 0005 - Dados complementares da entidade
        $lines[] = $this->buildSPEDLine0005($establishment);
        
        // Registro 0015 - Dados do contribuinte substituto ou responsável pelo ICMS
        if ($this->isICMSContributor($establishment)) {
            $lines[] = $this->buildSPEDLine0015($establishment);
        }
        
        // Registro 0100 - Dados do contabilista
        $lines[] = $this->buildSPEDLine0100($establishment);
        
        // Registro 0150 - Tabela de cadastro do participante
        $participants = $this->getParticipants($fiscal_data);
        foreach ($participants as $participant) {
            $lines[] = $this->buildSPEDLine0150($participant);
        }
        
        // Registro 0200 - Tabela de identificação do item
        $items = $this->getItems($fiscal_data);
        foreach ($items as $item) {
            $lines[] = $this->buildSPEDLine0200($item);
        }
        
        // Registro 0990 - Encerramento do bloco 0
        $lines[] = '|0990|' . count($lines) + 2 . '|'; // +2 para incluir este registro e o próximo
        
        // Bloco C - Documentos Fiscais I - Mercadorias e Serviços
        $lines[] = '|C001|0|'; // Abertura do bloco C
        
        // Processar NFCes do período
        $nfces = $this->getNFCesForPeriod($establishment['id'], $period_start, $period_end);
        foreach ($nfces as $nfce) {
            // Registro C100 - Nota Fiscal
            $lines[] = $this->buildSPEDLineC100($nfce);
            
            // Registro C170 - Itens do documento
            $nfce_items = $this->getNFCeItems($nfce['id']);
            foreach ($nfce_items as $item) {
                $lines[] = $this->buildSPEDLineC170($item, $nfce);
            }
        }
        
        $lines[] = '|C990|' . (count($nfces) * 2 + 2) . '|'; // Encerramento do bloco C
        
        // Bloco E - Apuração do ICMS e do IPI
        $lines[] = '|E001|0|'; // Abertura do bloco E
        $lines[] = $this->buildSPEDLineE100($fiscal_data, $period_start, $period_end);
        $lines[] = '|E990|3|'; // Encerramento do bloco E
        
        // Bloco H - Inventário
        $lines[] = '|H001|1|'; // Bloco não utilizado
        $lines[] = '|H990|2|';
        
        // Bloco 1 - Outras informações
        $lines[] = '|1001|1|'; // Bloco não utilizado
        $lines[] = '|1990|2|';
        
        // Registro 9999 - Encerramento do arquivo digital
        $lines[] = '|9999|' . (count($lines) + 1) . '|';
        
        return implode("\r\n", $lines) . "\r\n";
    }
    
    /**
     * Gera relatório DTE (Documento Tributário Eletrônico)
     */
    public function generateDTEReport($establishment_id, $period_start, $period_end) {
        try {
            $report_id = $this->createReportRecord($establishment_id, 'dte', $period_start, $period_end);
            
            // Buscar dados do estabelecimento
            $establishment = $this->getEstablishmentData($establishment_id);
            
            // Buscar NFCes do período
            $nfces = $this->getNFCesForPeriod($establishment_id, $period_start, $period_end);
            
            // Gerar arquivo DTE
            $dte_content = $this->buildDTEContent($establishment, $nfces, $period_start, $period_end);
            
            // Salvar arquivo
            $filename = "DTE_{$establishment['document']}_{$period_start}_{$period_end}.xml";
            $file_path = $this->saveReportFile($filename, $dte_content, 'dte');
            
            // Atualizar registro
            $this->completeReportRecord($report_id, $file_path, strlen($dte_content));
            
            return [
                'success' => true,
                'report_id' => $report_id,
                'file_path' => $file_path,
                'filename' => $filename
            ];
            
        } catch (Exception $e) {
            if (isset($report_id)) {
                $this->markReportError($report_id, $e->getMessage());
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Constrói conteúdo do arquivo DTE em XML
     */
    private function buildDTEContent($establishment, $nfces, $period_start, $period_end) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<DTE xmlns="http://www.fazenda.gov.br/dte" versao="1.0">' . "\n";
        
        // Cabeçalho
        $xml .= '  <cabecalho>' . "\n";
        $xml .= '    <contribuinte>' . "\n";
        $xml .= '      <cnpj>' . preg_replace('/\D/', '', $establishment['document']) . '</cnpj>' . "\n";
        $xml .= '      <razaoSocial>' . htmlspecialchars($establishment['name']) . '</razaoSocial>' . "\n";
        $xml .= '      <inscricaoEstadual>' . ($establishment['state_registration'] ?? '') . '</inscricaoEstadual>' . "\n";
        $xml .= '    </contribuinte>' . "\n";
        $xml .= '    <periodo>' . "\n";
        $xml .= '      <inicio>' . $period_start . '</inicio>' . "\n";
        $xml .= '      <fim>' . $period_end . '</fim>' . "\n";
        $xml .= '    </periodo>' . "\n";
        $xml .= '  </cabecalho>' . "\n";
        
        // Documentos
        $xml .= '  <documentos>' . "\n";
        
        foreach ($nfces as $nfce) {
            $xml .= '    <nfce>' . "\n";
            $xml .= '      <numero>' . $nfce['nfce_number'] . '</numero>' . "\n";
            $xml .= '      <serie>' . $nfce['nfce_series'] . '</serie>' . "\n";
            $xml .= '      <chave>' . $nfce['nfce_key'] . '</chave>' . "\n";
            $xml .= '      <dataEmissao>' . $nfce['emission_date'] . '</dataEmissao>' . "\n";
            $xml .= '      <valorTotal>' . number_format($nfce['total_amount'], 2, '.', '') . '</valorTotal>' . "\n";
            $xml .= '      <status>' . $nfce['status'] . '</status>' . "\n";
            
            if ($nfce['customer_document']) {
                $xml .= '      <destinatario>' . "\n";
                $xml .= '        <documento>' . $nfce['customer_document'] . '</documento>' . "\n";
                if ($nfce['customer_name']) {
                    $xml .= '        <nome>' . htmlspecialchars($nfce['customer_name']) . '</nome>' . "\n";
                }
                $xml .= '      </destinatario>' . "\n";
            }
            
            $xml .= '    </nfce>' . "\n";
        }
        
        $xml .= '  </documentos>' . "\n";
        
        // Totalizadores
        $total_count = count($nfces);
        $total_value = array_sum(array_column($nfces, 'total_amount'));
        
        $xml .= '  <totalizadores>' . "\n";
        $xml .= '    <quantidadeDocumentos>' . $total_count . '</quantidadeDocumentos>' . "\n";
        $xml .= '    <valorTotalDocumentos>' . number_format($total_value, 2, '.', '') . '</valorTotalDocumentos>' . "\n";
        $xml .= '  </totalizadores>' . "\n";
        
        $xml .= '</DTE>' . "\n";
        
        return $xml;
    }
    
    /**
     * Consolida dados fiscais mensais
     */
    public function consolidateMonthlyData($establishment_id, $reference_month) {
        try {
            // Calcular período do mês
            $start_date = $reference_month . '-01';
            $end_date = date('Y-m-t', strtotime($start_date));
            
            // Buscar NFCes do mês
            $nfces = $this->getNFCesForPeriod($establishment_id, $start_date, $end_date);
            
            // Calcular totais
            $total_count = count($nfces);
            $total_revenue = 0;
            $total_icms = 0;
            $total_pis = 0;
            $total_cofins = 0;
            $cancellation_count = 0;
            $cancellation_value = 0;
            
            foreach ($nfces as $nfce) {
                if ($nfce['status'] === 'cancelled') {
                    $cancellation_count++;
                    $cancellation_value += $nfce['total_amount'];
                } else {
                    $total_revenue += $nfce['total_amount'];
                    $total_icms += $nfce['total_tax'] ?? 0;
                    // Calcular PIS/COFINS se aplicável
                }
            }
            
            // Inserir ou atualizar dados consolidados
            $query = "INSERT INTO fiscal_data_consolidated 
                      (establishment_id, reference_month, total_nfce_count, total_revenue, 
                       total_tax_icms, total_tax_pis, total_tax_cofins, cancellation_count, 
                       cancellation_value, consolidated_at) 
                      VALUES 
                      (:establishment_id, :reference_month, :total_count, :total_revenue, 
                       :total_icms, :total_pis, :total_cofins, :cancellation_count, 
                       :cancellation_value, NOW())
                      ON DUPLICATE KEY UPDATE
                      total_nfce_count = VALUES(total_nfce_count),
                      total_revenue = VALUES(total_revenue),
                      total_tax_icms = VALUES(total_tax_icms),
                      cancellation_count = VALUES(cancellation_count),
                      cancellation_value = VALUES(cancellation_value),
                      consolidated_at = NOW()";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':establishment_id', $establishment_id);
            $stmt->bindParam(':reference_month', $reference_month);
            $stmt->bindParam(':total_count', $total_count);
            $stmt->bindParam(':total_revenue', $total_revenue);
            $stmt->bindParam(':total_icms', $total_icms);
            $stmt->bindParam(':total_pis', $total_pis);
            $stmt->bindParam(':total_cofins', $total_cofins);
            $stmt->bindParam(':cancellation_count', $cancellation_count);
            $stmt->bindParam(':cancellation_value', $cancellation_value);
            $stmt->execute();
            
            return [
                'success' => true,
                'consolidated_data' => [
                    'total_count' => $total_count,
                    'total_revenue' => $total_revenue,
                    'total_icms' => $total_icms,
                    'cancellation_count' => $cancellation_count,
                    'cancellation_value' => $cancellation_value
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtém lista de relatórios disponíveis
     */
    public function getAvailableReports($establishment_id, $limit = 20) {
        $query = "SELECT * FROM fiscal_reports 
                  WHERE establishment_id = :establishment_id 
                  ORDER BY generated_at DESC 
                  LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':establishment_id', $establishment_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtém estatísticas de relatórios
     */
    public function getReportStatistics($establishment_id, $days = 30) {
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $query = "SELECT 
                    report_type,
                    COUNT(*) as count,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors,
                    MAX(generated_at) as last_generated
                  FROM fiscal_reports 
                  WHERE establishment_id = :establishment_id 
                  AND generated_at >= :start_date 
                  GROUP BY report_type";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':establishment_id', $establishment_id);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Métodos auxiliares
    private function createReportRecord($establishment_id, $type, $period_start, $period_end) {
        $query = "INSERT INTO fiscal_reports 
                  (establishment_id, report_type, period_start, period_end, status) 
                  VALUES (:establishment_id, :type, :period_start, :period_end, 'generating')";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':establishment_id', $establishment_id);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':period_start', $period_start);
        $stmt->bindParam(':period_end', $period_end);
        $stmt->execute();
        
        return $this->db->lastInsertId();
    }
    
    private function completeReportRecord($report_id, $file_path, $file_size) {
        $hash = hash_file('sha256', $file_path);
        
        $query = "UPDATE fiscal_reports 
                  SET status = 'completed', file_path = :file_path, 
                      file_size = :file_size, hash = :hash 
                  WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $report_id);
        $stmt->bindParam(':file_path', $file_path);
        $stmt->bindParam(':file_size', $file_size);
        $stmt->bindParam(':hash', $hash);
        $stmt->execute();
    }
    
    private function markReportError($report_id, $error) {
        $query = "UPDATE fiscal_reports SET status = 'error', error_message = :error WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $report_id);
        $stmt->bindParam(':error', $error);
        $stmt->execute();
    }
    
    private function saveReportFile($filename, $content, $type) {
        $reports_dir = __DIR__ . '/../../storage/reports/' . $type;
        
        if (!is_dir($reports_dir)) {
            mkdir($reports_dir, 0755, true);
        }
        
        $file_path = $reports_dir . '/' . $filename;
        
        if (file_put_contents($file_path, $content) === false) {
            throw new Exception('Erro ao salvar arquivo do relatório');
        }
        
        return $file_path;
    }
    
    private function getEstablishmentData($establishment_id) {
        $query = "SELECT * FROM establishments WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $establishment_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getNFCesForPeriod($establishment_id, $start_date, $end_date) {
        $query = "SELECT * FROM nfce_emissions 
                  WHERE establishment_id = :establishment_id 
                  AND emission_date BETWEEN :start_date AND :end_date 
                  ORDER BY emission_date ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':establishment_id', $establishment_id);
        $stmt->bindParam(':start_date', $start_date . ' 00:00:00');
        $stmt->bindParam(':end_date', $end_date . ' 23:59:59');
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Métodos específicos do SPED (simplificados para exemplo)
    private function buildSPEDLine0000($establishment, $period_start, $period_end) {
        return '|0000|014|0|' . date('dmY', strtotime($period_start)) . '|' . 
               date('dmY', strtotime($period_end)) . '|' . 
               $establishment['name'] . '|' . 
               preg_replace('/\D/', '', $establishment['document']) . '|||A|1|';
    }
    
    private function buildSPEDLine0005($establishment) {
        return '|0005|' . $establishment['name'] . '|' . 
               $establishment['name'] . '|' . 
               $establishment['address'] . '|' . 
               $establishment['number'] . '|' . 
               ($establishment['complement'] ?? '') . '|' . 
               $establishment['neighborhood'] . '|' . 
               preg_replace('/\D/', '', $establishment['zipcode']) . '|' . 
               $establishment['city'] . '|' . 
               $establishment['state'] . '|' . 
               preg_replace('/\D/', '', $establishment['phone']) . '|||';
    }
    
    private function buildSPEDLineC100($nfce) {
        return '|C100|0|1|' . $nfce['nfce_number'] . '|65|00|' . 
               $nfce['nfce_series'] . '|' . 
               date('dmY', strtotime($nfce['emission_date'])) . '|' . 
               date('dmY', strtotime($nfce['emission_date'])) . '|' . 
               number_format($nfce['total_amount'], 2, ',', '') . '|0|0|||0|';
    }
    
    // Outros métodos auxiliares necessários...
    private function getFiscalDataForPeriod($establishment_id, $start_date, $end_date) {
        // Implementar busca de dados fiscais consolidados
        return [];
    }
    
    private function isICMSContributor($establishment) {
        return !empty($establishment['state_registration']);
    }
    
    private function getParticipants($fiscal_data) {
        return [];
    }
    
    private function getItems($fiscal_data) {
        return [];
    }
    
    private function getNFCeItems($nfce_id) {
        return [];
    }
    
    private function buildSPEDLine0015($establishment) { return ''; }
    private function buildSPEDLine0100($establishment) { return ''; }
    private function buildSPEDLine0150($participant) { return ''; }
    private function buildSPEDLine0200($item) { return ''; }
    private function buildSPEDLineC170($item, $nfce) { return ''; }
    private function buildSPEDLineE100($fiscal_data, $start, $end) { return ''; }
}
?>