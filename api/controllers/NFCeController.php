<?php
require_once __DIR__ . '/../vendor/autoload.php';

class NFCeController {
    private $db;
    private $nfce;
    
    public function __construct($database) {
        $this->db = $database;
        $this->nfce = new NFCe($this->db);
    }
    
    /**
     * Lista NFCes do estabelecimento
     */
    public function getNFCes() {
        try {
            $user_id = $this->getUserIdFromToken();
            if (!$user_id) {
                return $this->sendResponse(401, false, 'Token inválido');
            }
            
            // Buscar establishment_id do usuário
            $establishment = new Establishment($this->db);
            $establishment_data = $establishment->getByUserId($user_id);
            
            if (!$establishment_data) {
                return $this->sendResponse(404, false, 'Estabelecimento não encontrado');
            }
            
            // Filtros opcionais
            $filters = [];
            if (!empty($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            if (!empty($_GET['date_from'])) {
                $filters['date_from'] = $_GET['date_from'];
            }
            if (!empty($_GET['date_to'])) {
                $filters['date_to'] = $_GET['date_to'];
            }
            if (!empty($_GET['customer_document'])) {
                $filters['customer_document'] = $_GET['customer_document'];
            }
            
            $nfces = $this->nfce->getNFCes($establishment_data['id'], $filters);
            
            return $this->sendResponse(200, true, 'NFCes listadas com sucesso', $nfces);
            
        } catch (Exception $e) {
            return $this->sendResponse(500, false, 'Erro interno: ' . $e->getMessage());
        }
    }
    
    /**
     * Busca NFCe específica
     */
    public function getNFCe($nfce_id) {
        try {
            $user_id = $this->getUserIdFromToken();
            if (!$user_id) {
                return $this->sendResponse(401, false, 'Token inválido');
            }
            
            // Buscar establishment_id do usuário
            $establishment = new Establishment($this->db);
            $establishment_data = $establishment->getByUserId($user_id);
            
            if (!$establishment_data) {
                return $this->sendResponse(404, false, 'Estabelecimento não encontrado');
            }
            
            $nfce = $this->nfce->getNFCe($nfce_id, $establishment_data['id']);
            
            if (!$nfce) {
                return $this->sendResponse(404, false, 'NFCe não encontrada');
            }
            
            return $this->sendResponse(200, true, 'NFCe encontrada', $nfce);
            
        } catch (Exception $e) {
            return $this->sendResponse(500, false, 'Erro interno: ' . $e->getMessage());
        }
    }
    
    /**
     * Emite nova NFCe
     */
    public function emitNFCe() {
        try {
            $user_id = $this->getUserIdFromToken();
            if (!$user_id) {
                return $this->sendResponse(401, false, 'Token inválido');
            }
            
            // Buscar establishment_id do usuário
            $establishment = new Establishment($this->db);
            $establishment_data = $establishment->getByUserId($user_id);
            
            if (!$establishment_data) {
                return $this->sendResponse(404, false, 'Estabelecimento não encontrado');
            }
            
            // Verificar se NFCe está habilitada
            if (!$establishment_data['nfce_enabled']) {
                return $this->sendResponse(400, false, 'NFCe não está habilitada para este estabelecimento');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Adicionar dados do estabelecimento
            $input['establishment_id'] = $establishment_data['id'];
            
            // Validar dados
            $validation_errors = $this->nfce->validateNFCeData($input);
            if (!empty($validation_errors)) {
                return $this->sendResponse(400, false, 'Dados inválidos', $validation_errors);
            }
            
            // Gerar número e chave da NFCe
            $series = $establishment_data['nfce_series'] ?? 1;
            $nfce_number = $this->nfce->getNextNumber($establishment_data['id'], $series);
            $nfce_key = $this->nfce->generateNFCeKey($establishment_data, $nfce_number, $series);
            
            // Calcular totais
            $totals = $this->calculateTotals($input['items']);
            
            // Preparar dados da NFCe
            $nfce_data = [
                'establishment_id' => $establishment_data['id'],
                'nfce_number' => $nfce_number,
                'nfce_series' => $series,
                'nfce_key' => $nfce_key,
                'customer_document' => $input['customer_document'] ?? null,
                'customer_name' => $input['customer_name'] ?? null,
                'customer_email' => $input['customer_email'] ?? null,
                'customer_phone' => $input['customer_phone'] ?? null,
                'total_products' => $totals['total_products'],
                'total_discounts' => $input['total_discounts'] ?? 0,
                'total_tax' => $totals['total_tax'],
                'total_amount' => $totals['total_amount'],
                'payment_method' => $input['payment_method'],
                'payment_amount' => $input['payment_amount'],
                'change_amount' => $input['change_amount'] ?? 0,
                'status' => 'pending',
                'xml_file_path' => null,
                'pdf_file_path' => null,
                'protocol_number' => null,
                'authorization_date' => null,
                'cancellation_reason' => null,
                'items' => $input['items']
            ];
            
            // Criar NFCe no banco
            $nfce_id = $this->nfce->create($nfce_data);
            
            if ($nfce_id) {
                // Gerar XML da NFCe
                $xml_result = $this->generateNFCeXML($nfce_data, $establishment_data);
                
                if ($xml_result['success']) {
                    // Salvar caminho do XML
                    $this->nfce->updateStatus($nfce_id, 'generated', [
                        'xml_file_path' => $xml_result['xml_path']
                    ]);
                    
                    // Tentar enviar para SEFAZ (simulado por enquanto)
                    $sefaz_result = $this->sendToSEFAZ($xml_result['xml_path'], $establishment_data);
                    
                    if ($sefaz_result['success']) {
                        // Autorizada
                        $this->nfce->updateStatus($nfce_id, 'authorized', [
                            'protocol_number' => $sefaz_result['protocol'],
                            'authorization_date' => date('Y-m-d H:i:s'),
                            'pdf_file_path' => $sefaz_result['pdf_path']
                        ]);
                        
                        $status_message = 'NFCe autorizada com sucesso';
                    } else {
                        // Erro na autorização
                        $this->nfce->updateStatus($nfce_id, 'rejected');
                        $status_message = 'NFCe gerada mas rejeitada pela SEFAZ: ' . $sefaz_result['error'];
                    }
                } else {
                    $this->nfce->updateStatus($nfce_id, 'error');
                    $status_message = 'Erro ao gerar XML: ' . $xml_result['error'];
                }
                
                // Buscar NFCe atualizada
                $new_nfce = $this->nfce->getNFCe($nfce_id, $establishment_data['id']);
                
                return $this->sendResponse(201, true, $status_message, $new_nfce);
            } else {
                return $this->sendResponse(500, false, 'Erro ao criar NFCe');
            }
            
        } catch (Exception $e) {
            return $this->sendResponse(500, false, 'Erro interno: ' . $e->getMessage());
        }
    }
    
    /**
     * Calcula totais da NFCe
     */
    private function calculateTotals($items) {
        $total_products = 0;
        $total_tax = 0;
        
        foreach ($items as $item) {
            $item_total = $item['quantity'] * $item['unit_price'];
            $total_products += $item_total;
            
            // Calcular impostos (simplificado)
            // Em uma implementação real, seria baseado no regime tributário
            $total_tax += $item_total * 0.05; // 5% de exemplo
        }
        
        $total_amount = $total_products + $total_tax;
        
        return [
            'total_products' => $total_products,
            'total_tax' => $total_tax,
            'total_amount' => $total_amount
        ];
    }
    
    /**
     * Gera XML da NFCe
     */
    private function generateNFCeXML($nfce_data, $establishment_data) {
        try {
            // Criar diretório se não existir
            $xml_dir = __DIR__ . '/../../storage/nfce/xml/';
            if (!is_dir($xml_dir)) {
                mkdir($xml_dir, 0755, true);
            }
            
            // Nome do arquivo XML
            $xml_filename = $nfce_data['nfce_key'] . '.xml';
            $xml_path = $xml_dir . $xml_filename;
            
            // Gerar conteúdo XML (estrutura simplificada)
            $xml_content = $this->buildNFCeXML($nfce_data, $establishment_data);
            
            // Salvar arquivo XML
            if (file_put_contents($xml_path, $xml_content)) {
                return [
                    'success' => true,
                    'xml_path' => $xml_path,
                    'xml_filename' => $xml_filename
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Erro ao salvar arquivo XML'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Constrói XML da NFCe (estrutura simplificada)
     */
    private function buildNFCeXML($nfce_data, $establishment_data) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<nfeProc versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">' . "\n";
        $xml .= '  <NFe xmlns="http://www.portalfiscal.inf.br/nfe">' . "\n";
        $xml .= '    <infNFe Id="NFe' . $nfce_data['nfce_key'] . '">' . "\n";
        
        // Identificação da NFCe
        $xml .= '      <ide>' . "\n";
        $xml .= '        <cUF>' . substr($nfce_data['nfce_key'], 0, 2) . '</cUF>' . "\n";
        $xml .= '        <cNF>' . substr($nfce_data['nfce_key'], 35, 8) . '</cNF>' . "\n";
        $xml .= '        <natOp>Venda de mercadoria</natOp>' . "\n";
        $xml .= '        <mod>65</mod>' . "\n";
        $xml .= '        <serie>' . $nfce_data['nfce_series'] . '</serie>' . "\n";
        $xml .= '        <nNF>' . $nfce_data['nfce_number'] . '</nNF>' . "\n";
        $xml .= '        <dhEmi>' . date('c') . '</dhEmi>' . "\n";
        $xml .= '        <tpNF>1</tpNF>' . "\n";
        $xml .= '        <idDest>1</idDest>' . "\n";
        $xml .= '        <cMunFG>' . $establishment_data['city'] . '</cMunFG>' . "\n";
        $xml .= '        <tpImp>4</tpImp>' . "\n";
        $xml .= '        <tpEmis>1</tpEmis>' . "\n";
        $xml .= '        <cDV>' . substr($nfce_data['nfce_key'], -1) . '</cDV>' . "\n";
        $xml .= '        <tpAmb>2</tpAmb>' . "\n"; // 2 = Homologação, 1 = Produção
        $xml .= '        <finNFe>1</finNFe>' . "\n";
        $xml .= '        <indFinal>1</indFinal>' . "\n";
        $xml .= '        <indPres>1</indPres>' . "\n";
        $xml .= '        <procEmi>0</procEmi>' . "\n";
        $xml .= '        <verProc>1.0</verProc>' . "\n";
        $xml .= '      </ide>' . "\n";
        
        // Dados do emitente
        $xml .= '      <emit>' . "\n";
        $xml .= '        <CNPJ>' . preg_replace('/\D/', '', $establishment_data['document']) . '</CNPJ>' . "\n";
        $xml .= '        <xNome>' . htmlspecialchars($establishment_data['business_name']) . '</xNome>' . "\n";
        $xml .= '        <xFant>' . htmlspecialchars($establishment_data['trade_name']) . '</xFant>' . "\n";
        $xml .= '        <enderEmit>' . "\n";
        $xml .= '          <xLgr>' . htmlspecialchars($establishment_data['street']) . '</xLgr>' . "\n";
        $xml .= '          <nro>' . htmlspecialchars($establishment_data['number']) . '</nro>' . "\n";
        $xml .= '          <xBairro>' . htmlspecialchars($establishment_data['neighborhood']) . '</xBairro>' . "\n";
        $xml .= '          <cMun>' . htmlspecialchars($establishment_data['city']) . '</cMun>' . "\n";
        $xml .= '          <xMun>' . htmlspecialchars($establishment_data['city']) . '</xMun>' . "\n";
        $xml .= '          <UF>' . htmlspecialchars($establishment_data['state']) . '</UF>' . "\n";
        $xml .= '          <CEP>' . preg_replace('/\D/', '', $establishment_data['zip_code']) . '</CEP>' . "\n";
        $xml .= '        </enderEmit>' . "\n";
        $xml .= '        <IE>' . preg_replace('/\D/', '', $establishment_data['state_registration']) . '</IE>' . "\n";
        $xml .= '        <CRT>1</CRT>' . "\n"; // Simples Nacional
        $xml .= '      </emit>' . "\n";
        
        // Itens da NFCe
        foreach ($nfce_data['items'] as $index => $item) {
            $xml .= '      <det nItem="' . ($index + 1) . '">' . "\n";
            $xml .= '        <prod>' . "\n";
            $xml .= '          <cProd>' . ($item['product_id'] ?? ($index + 1)) . '</cProd>' . "\n";
            $xml .= '          <cEAN>SEM GTIN</cEAN>' . "\n";
            $xml .= '          <xProd>' . htmlspecialchars($item['description']) . '</xProd>' . "\n";
            $xml .= '          <NCM>' . ($item['ncm'] ?? '00000000') . '</NCM>' . "\n";
            $xml .= '          <CFOP>' . ($item['cfop'] ?? '5102') . '</CFOP>' . "\n";
            $xml .= '          <uCom>UN</uCom>' . "\n";
            $xml .= '          <qCom>' . number_format($item['quantity'], 2, '.', '') . '</qCom>' . "\n";
            $xml .= '          <vUnCom>' . number_format($item['unit_price'], 2, '.', '') . '</vUnCom>' . "\n";
            $xml .= '          <vProd>' . number_format($item['total_price'], 2, '.', '') . '</vProd>' . "\n";
            $xml .= '          <cEANTrib>SEM GTIN</cEANTrib>' . "\n";
            $xml .= '          <uTrib>UN</uTrib>' . "\n";
            $xml .= '          <qTrib>' . number_format($item['quantity'], 2, '.', '') . '</qTrib>' . "\n";
            $xml .= '          <vUnTrib>' . number_format($item['unit_price'], 2, '.', '') . '</vUnTrib>' . "\n";
            $xml .= '        </prod>' . "\n";
            $xml .= '        <imposto>' . "\n";
            $xml .= '          <ICMS>' . "\n";
            $xml .= '            <ICMSSN102>' . "\n";
            $xml .= '              <orig>0</orig>' . "\n";
            $xml .= '              <CSOSN>102</CSOSN>' . "\n";
            $xml .= '            </ICMSSN102>' . "\n";
            $xml .= '          </ICMS>' . "\n";
            $xml .= '        </imposto>' . "\n";
            $xml .= '      </det>' . "\n";
        }
        
        // Totais
        $xml .= '      <total>' . "\n";
        $xml .= '        <ICMSTot>' . "\n";
        $xml .= '          <vBC>0.00</vBC>' . "\n";
        $xml .= '          <vICMS>0.00</vICMS>' . "\n";
        $xml .= '          <vICMSDeson>0.00</vICMSDeson>' . "\n";
        $xml .= '          <vFCP>0.00</vFCP>' . "\n";
        $xml .= '          <vBCST>0.00</vBCST>' . "\n";
        $xml .= '          <vST>0.00</vST>' . "\n";
        $xml .= '          <vFCPST>0.00</vFCPST>' . "\n";
        $xml .= '          <vFCPSTRet>0.00</vFCPSTRet>' . "\n";
        $xml .= '          <vProd>' . number_format($nfce_data['total_products'], 2, '.', '') . '</vProd>' . "\n";
        $xml .= '          <vFrete>0.00</vFrete>' . "\n";
        $xml .= '          <vSeg>0.00</vSeg>' . "\n";
        $xml .= '          <vDesc>' . number_format($nfce_data['total_discounts'], 2, '.', '') . '</vDesc>' . "\n";
        $xml .= '          <vII>0.00</vII>' . "\n";
        $xml .= '          <vIPI>0.00</vIPI>' . "\n";
        $xml .= '          <vIPIDevol>0.00</vIPIDevol>' . "\n";
        $xml .= '          <vPIS>0.00</vPIS>' . "\n";
        $xml .= '          <vCOFINS>0.00</vCOFINS>' . "\n";
        $xml .= '          <vOutro>0.00</vOutro>' . "\n";
        $xml .= '          <vNF>' . number_format($nfce_data['total_amount'], 2, '.', '') . '</vNF>' . "\n";
        $xml .= '          <vTotTrib>0.00</vTotTrib>' . "\n";
        $xml .= '        </ICMSTot>' . "\n";
        $xml .= '      </total>' . "\n";
        
        // Forma de pagamento
        $xml .= '      <pag>' . "\n";
        $xml .= '        <detPag>' . "\n";
        $xml .= '          <tPag>' . $this->getPaymentCode($nfce_data['payment_method']) . '</tPag>' . "\n";
        $xml .= '          <vPag>' . number_format($nfce_data['payment_amount'], 2, '.', '') . '</vPag>' . "\n";
        $xml .= '        </detPag>' . "\n";
        if ($nfce_data['change_amount'] > 0) {
            $xml .= '        <vTroco>' . number_format($nfce_data['change_amount'], 2, '.', '') . '</vTroco>' . "\n";
        }
        $xml .= '      </pag>' . "\n";
        
        $xml .= '    </infNFe>' . "\n";
        $xml .= '  </NFe>' . "\n";
        $xml .= '</nfeProc>' . "\n";
        
        return $xml;
    }
    
    /**
     * Obtém código de forma de pagamento
     */
    private function getPaymentCode($payment_method) {
        $codes = [
            'money' => '01',    // Dinheiro
            'card' => '03',     // Cartão de crédito
            'debit' => '04',    // Cartão de débito
            'pix' => '17',      // PIX
            'transfer' => '18'  // Transferência bancária
        ];
        
        return $codes[$payment_method] ?? '99'; // Outros
    }
    
    /**
     * Envia NFCe para SEFAZ (simulado)
     */
    private function sendToSEFAZ($xml_path, $establishment_data) {
        // Por enquanto, simulação de envio
        // Em uma implementação real, aqui seria feita a integração com o webservice da SEFAZ
        
        try {
            // Simular delay de processamento
            sleep(1);
            
            // Simular sucesso na maioria dos casos
            if (rand(1, 10) <= 8) {
                // Gerar protocolo simulado
                $protocol = date('YmdHis') . rand(1000, 9999);
                
                // Simular geração de PDF
                $pdf_path = $this->generateNFCePDF($xml_path, $protocol);
                
                return [
                    'success' => true,
                    'protocol' => $protocol,
                    'pdf_path' => $pdf_path
                ];
            } else {
                // Simular erro
                return [
                    'success' => false,
                    'error' => 'Rejeição 999: Erro simulado para testes'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Gera PDF da NFCe (simulado)
     */
    private function generateNFCePDF($xml_path, $protocol) {
        // Criar diretório se não existir
        $pdf_dir = __DIR__ . '/../../storage/nfce/pdf/';
        if (!is_dir($pdf_dir)) {
            mkdir($pdf_dir, 0755, true);
        }
        
        $pdf_filename = basename($xml_path, '.xml') . '.pdf';
        $pdf_path = $pdf_dir . $pdf_filename;
        
        // Simular conteúdo PDF (em uma implementação real, seria gerado com biblioteca apropriada)
        $pdf_content = "PDF simulado da NFCe\nProtocolo: " . $protocol . "\nData: " . date('Y-m-d H:i:s');
        file_put_contents($pdf_path, $pdf_content);
        
        return $pdf_path;
    }
    
    /**
     * Cancela NFCe
     */
    public function cancelNFCe($nfce_id) {
        try {
            $user_id = $this->getUserIdFromToken();
            if (!$user_id) {
                return $this->sendResponse(401, false, 'Token inválido');
            }
            
            // Buscar establishment_id do usuário
            $establishment = new Establishment($this->db);
            $establishment_data = $establishment->getByUserId($user_id);
            
            if (!$establishment_data) {
                return $this->sendResponse(404, false, 'Estabelecimento não encontrado');
            }
            
            // Verificar se NFCe existe e pode ser cancelada
            $nfce = $this->nfce->getNFCe($nfce_id, $establishment_data['id']);
            
            if (!$nfce) {
                return $this->sendResponse(404, false, 'NFCe não encontrada');
            }
            
            if ($nfce['status'] !== 'authorized') {
                return $this->sendResponse(400, false, 'Apenas NFCes autorizadas podem ser canceladas');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $cancellation_reason = $input['reason'] ?? 'Cancelamento solicitado pelo cliente';
            
            // Simular cancelamento na SEFAZ
            $cancel_result = $this->cancelAtSEFAZ($nfce['nfce_key'], $cancellation_reason, $establishment_data);
            
            if ($cancel_result['success']) {
                $this->nfce->updateStatus($nfce_id, 'cancelled', [
                    'cancellation_reason' => $cancellation_reason
                ]);
                
                return $this->sendResponse(200, true, 'NFCe cancelada com sucesso');
            } else {
                return $this->sendResponse(400, false, 'Erro ao cancelar NFCe: ' . $cancel_result['error']);
            }
            
        } catch (Exception $e) {
            return $this->sendResponse(500, false, 'Erro interno: ' . $e->getMessage());
        }
    }
    
    /**
     * Cancela NFCe na SEFAZ (simulado)
     */
    private function cancelAtSEFAZ($nfce_key, $reason, $establishment_data) {
        // Simular cancelamento na SEFAZ
        sleep(1);
        
        if (rand(1, 10) <= 9) {
            return [
                'success' => true,
                'protocol' => date('YmdHis') . rand(1000, 9999)
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Prazo para cancelamento expirado'
            ];
        }
    }
    
    /**
     * Busca estatísticas de NFCe
     */
    public function getStatistics() {
        try {
            $user_id = $this->getUserIdFromToken();
            if (!$user_id) {
                return $this->sendResponse(401, false, 'Token inválido');
            }
            
            // Buscar establishment_id do usuário
            $establishment = new Establishment($this->db);
            $establishment_data = $establishment->getByUserId($user_id);
            
            if (!$establishment_data) {
                return $this->sendResponse(404, false, 'Estabelecimento não encontrado');
            }
            
            $period = $_GET['period'] ?? 'month';
            $stats = $this->nfce->getStatistics($establishment_data['id'], $period);
            
            return $this->sendResponse(200, true, 'Estatísticas obtidas com sucesso', $stats);
            
        } catch (Exception $e) {
            return $this->sendResponse(500, false, 'Erro interno: ' . $e->getMessage());
        }
    }
    
    /**
     * Obtém ID do usuário do token JWT
     */
    private function getUserIdFromToken() {
        $headers = apache_request_headers();
        $token = $headers['Authorization'] ?? '';
        
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }
        
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            $decoded = Firebase\JWT\JWT::decode($token, new Firebase\JWT\Key($_ENV['JWT_SECRET'], 'HS256'));
            return $decoded->user_id;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Envia resposta JSON
     */
    private function sendResponse($status_code, $success, $message, $data = null) {
        http_response_code($status_code);
        header('Content-Type: application/json');
        
        $response = [
            'success' => $success,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response);
        return true;
    }
}
?>