<?php
require_once '../config/database.php';
require_once '../services/SEFAZIntegration.php';
require_once '../services/DANFEGenerator.php';
require_once '../services/OfflineContingency.php';
require_once '../services/EmailAutomation.php';
require_once '../services/FiscalReports.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

class AdvancedNFCeController {
    private $db;
    private $sefaz;
    private $danfe;
    private $contingency;
    private $email;
    private $reports;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_GET['path'] ?? '';
        
        switch ($method) {
            case 'GET':
                return $this->handleGet($path);
            case 'POST':
                return $this->handlePost($path);
            case 'PUT':
                return $this->handlePut($path);
            case 'DELETE':
                return $this->handleDelete($path);
            default:
                return $this->error('Método não permitido', 405);
        }
    }
    
    private function handleGet($path) {
        switch ($path) {
            case 'sefaz/status':
                return $this->checkSEFAZStatus();
            
            case 'contingency/statistics':
                return $this->getContingencyStatistics();
            
            case 'contingency/pending':
                return $this->getPendingContingency();
            
            case 'email/logs':
                return $this->getEmailLogs();
            
            case 'reports/list':
                return $this->getReportsList();
            
            case 'reports/statistics':
                return $this->getReportsStatistics();
            
            case 'danfe/preview':
                return $this->previewDANFE();
            
            default:
                return $this->error('Endpoint não encontrado', 404);
        }
    }
    
    private function handlePost($path) {
        $input = json_decode(file_get_contents('php://input'), true);
        
        switch ($path) {
            case 'sefaz/authorize':
                return $this->authorizeSEFAZ($input);
            
            case 'sefaz/cancel':
                return $this->cancelSEFAZ($input);
            
            case 'danfe/generate':
                return $this->generateDANFE($input);
            
            case 'contingency/emit':
                return $this->emitOffline($input);
            
            case 'contingency/sync':
                return $this->syncContingency($input);
            
            case 'email/send':
                return $this->sendEmail($input);
            
            case 'email/process-queue':
                return $this->processEmailQueue($input);
            
            case 'reports/generate':
                return $this->generateReport($input);
            
            case 'reports/consolidate':
                return $this->consolidateData($input);
            
            default:
                return $this->error('Endpoint não encontrado', 404);
        }
    }
    
    /**
     * Verifica status do serviço SEFAZ
     */
    private function checkSEFAZStatus() {
        try {
            $establishment_id = $_GET['establishment_id'] ?? null;
            
            if (!$establishment_id) {
                return $this->error('ID do estabelecimento é obrigatório');
            }
            
            $establishment = $this->getEstablishment($establishment_id);
            if (!$establishment) {
                return $this->error('Estabelecimento não encontrado');
            }
            
            $this->sefaz = new SEFAZIntegration($establishment);
            $status = $this->sefaz->checkServiceStatus();
            
            return $this->success($status);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Autoriza NFCe na SEFAZ
     */
    private function authorizeSEFAZ($input) {
        try {
            $nfce_id = $input['nfce_id'] ?? null;
            $establishment_id = $input['establishment_id'] ?? null;
            
            if (!$nfce_id || !$establishment_id) {
                return $this->error('NFCe ID e Estabelecimento ID são obrigatórios');
            }
            
            $establishment = $this->getEstablishment($establishment_id);
            $nfce_data = $this->getNFCeData($nfce_id);
            
            if (!$establishment || !$nfce_data) {
                return $this->error('Dados não encontrados');
            }
            
            // Verificar conectividade
            $this->contingency = new OfflineContingency($this->db);
            if (!$this->contingency->checkSEFAZConnectivity($establishment)) {
                // Emitir em modo offline
                $offline_result = $this->contingency->emitOfflineNFCe($nfce_data, $establishment);
                return $this->success($offline_result);
            }
            
            // Autorizar online
            $this->sefaz = new SEFAZIntegration($establishment);
            
            // Gerar XML (implementar método generateXML)
            $xml_content = $this->generateNFCeXML($nfce_data, $establishment);
            
            $result = $this->sefaz->authorizeNFCe($xml_content, $nfce_data['nfce_key']);
            
            if ($result['success']) {
                // Atualizar NFCe no banco
                $this->updateNFCeStatus($nfce_id, 'authorized', $result);
                
                // Gerar DANFE
                $this->generateDANFEForNFCe($nfce_id);
                
                // Enviar por email se configurado
                $this->sendNFCeEmail($nfce_id);
            }
            
            return $this->success($result);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Cancela NFCe na SEFAZ
     */
    private function cancelSEFAZ($input) {
        try {
            $nfce_id = $input['nfce_id'] ?? null;
            $reason = $input['reason'] ?? null;
            $establishment_id = $input['establishment_id'] ?? null;
            
            if (!$nfce_id || !$reason || !$establishment_id) {
                return $this->error('Dados obrigatórios não fornecidos');
            }
            
            $establishment = $this->getEstablishment($establishment_id);
            $nfce_data = $this->getNFCeData($nfce_id);
            
            $this->sefaz = new SEFAZIntegration($establishment);
            $result = $this->sefaz->cancelNFCe($nfce_data['nfce_key'], $nfce_data['protocol_number'], $reason);
            
            if ($result['success']) {
                $this->updateNFCeStatus($nfce_id, 'cancelled', $result);
            }
            
            return $this->success($result);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Gera DANFE PDF
     */
    private function generateDANFE($input) {
        try {
            $nfce_id = $input['nfce_id'] ?? null;
            
            if (!$nfce_id) {
                return $this->error('NFCe ID é obrigatório');
            }
            
            $result = $this->generateDANFEForNFCe($nfce_id);
            
            return $this->success($result);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Emite NFCe offline
     */
    private function emitOffline($input) {
        try {
            $nfce_data = $input['nfce_data'] ?? null;
            $establishment_id = $input['establishment_id'] ?? null;
            
            if (!$nfce_data || !$establishment_id) {
                return $this->error('Dados da NFCe e ID do estabelecimento são obrigatórios');
            }
            
            $establishment = $this->getEstablishment($establishment_id);
            
            $this->contingency = new OfflineContingency($this->db);
            $result = $this->contingency->emitOfflineNFCe($nfce_data, $establishment);
            
            return $this->success($result);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Sincroniza contingência offline
     */
    private function syncContingency($input) {
        try {
            $establishment_id = $input['establishment_id'] ?? null;
            
            if (!$establishment_id) {
                return $this->error('ID do estabelecimento é obrigatório');
            }
            
            $establishment = $this->getEstablishment($establishment_id);
            
            $this->contingency = new OfflineContingency($this->db);
            $result = $this->contingency->syncOfflineNFCes($establishment);
            
            return $this->success($result);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Obtém estatísticas de contingência
     */
    private function getContingencyStatistics() {
        try {
            $establishment_id = $_GET['establishment_id'] ?? null;
            
            if (!$establishment_id) {
                return $this->error('ID do estabelecimento é obrigatório');
            }
            
            $this->contingency = new OfflineContingency($this->db);
            $stats = $this->contingency->getContingencyStatistics($establishment_id);
            
            return $this->success($stats);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Envia email
     */
    private function sendEmail($input) {
        try {
            $nfce_id = $input['nfce_id'] ?? null;
            $force = $input['force'] ?? false;
            
            if (!$nfce_id) {
                return $this->error('NFCe ID é obrigatório');
            }
            
            $result = $this->sendNFCeEmail($nfce_id, $force);
            
            return $this->success($result);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Processa fila de emails
     */
    private function processEmailQueue($input) {
        try {
            $limit = $input['limit'] ?? 10;
            
            $this->email = new EmailAutomation($this->db);
            $result = $this->email->processPendingEmails($limit);
            
            return $this->success($result);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Gera relatório fiscal
     */
    private function generateReport($input) {
        try {
            $establishment_id = $input['establishment_id'] ?? null;
            $report_type = $input['report_type'] ?? null;
            $period_start = $input['period_start'] ?? null;
            $period_end = $input['period_end'] ?? null;
            
            if (!$establishment_id || !$report_type || !$period_start || !$period_end) {
                return $this->error('Todos os campos são obrigatórios');
            }
            
            $this->reports = new FiscalReports($this->db);
            
            switch ($report_type) {
                case 'sped':
                    $result = $this->reports->generateSPEDReport($establishment_id, $period_start, $period_end);
                    break;
                case 'dte':
                    $result = $this->reports->generateDTEReport($establishment_id, $period_start, $period_end);
                    break;
                default:
                    return $this->error('Tipo de relatório não suportado');
            }
            
            return $this->success($result);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Consolida dados fiscais
     */
    private function consolidateData($input) {
        try {
            $establishment_id = $input['establishment_id'] ?? null;
            $reference_month = $input['reference_month'] ?? null;
            
            if (!$establishment_id || !$reference_month) {
                return $this->error('ID do estabelecimento e mês de referência são obrigatórios');
            }
            
            $this->reports = new FiscalReports($this->db);
            $result = $this->reports->consolidateMonthlyData($establishment_id, $reference_month);
            
            return $this->success($result);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    // Métodos auxiliares
    private function generateDANFEForNFCe($nfce_id) {
        $nfce_data = $this->getNFCeData($nfce_id);
        
        if (!$nfce_data) {
            throw new Exception('NFCe não encontrada');
        }
        
        $this->danfe = new DANFEGenerator();
        return $this->danfe->generatePDF($nfce_data);
    }
    
    private function sendNFCeEmail($nfce_id, $force = false) {
        $this->email = new EmailAutomation($this->db);
        return $this->email->sendNFCeEmail($nfce_id, $force);
    }
    
    private function generateNFCeXML($nfce_data, $establishment) {
        // Implementar geração de XML
        // Por enquanto, retornar XML mockado
        return '<?xml version="1.0" encoding="UTF-8"?><nfeProc><!-- XML da NFCe --></nfeProc>';
    }
    
    private function getEstablishment($establishment_id) {
        $query = "SELECT * FROM establishments WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $establishment_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getNFCeData($nfce_id) {
        $query = "SELECT * FROM nfce_emissions WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $nfce_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function updateNFCeStatus($nfce_id, $status, $additional_data = []) {
        $fields = ['status = :status'];
        $params = [':status' => $status, ':id' => $nfce_id];
        
        if (isset($additional_data['protocol_number'])) {
            $fields[] = 'protocol_number = :protocol';
            $params[':protocol'] = $additional_data['protocol_number'];
        }
        
        if (isset($additional_data['authorization_date'])) {
            $fields[] = 'authorization_date = :auth_date';
            $params[':auth_date'] = $additional_data['authorization_date'];
        }
        
        $query = "UPDATE nfce_emissions SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        return $stmt->execute();
    }
    
    private function success($data = null, $message = 'Sucesso') {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    private function error($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
}

// Executar controller
try {
    $controller = new AdvancedNFCeController();
    $controller->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno do servidor: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>