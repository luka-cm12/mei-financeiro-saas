<?php
require_once __DIR__ . '/../vendor/autoload.php';

class SEFAZIntegration {
    private $establishment_data;
    private $certificate_path;
    private $certificate_password;
    private $environment; // 1 = Produção, 2 = Homologação
    private $uf_code;
    
    // URLs dos webservices por UF (Homologação)
    private $webservices_homolog = [
        'SP' => [
            'authorization' => 'https://homologacao.nfce.fazenda.sp.gov.br/ws/NFeAutorizacao4.asmx',
            'return_authorization' => 'https://homologacao.nfce.fazenda.sp.gov.br/ws/NFeRetAutorizacao4.asmx',
            'query_protocol' => 'https://homologacao.nfce.fazenda.sp.gov.br/ws/NFeConsultaProtocolo4.asmx',
            'query_status' => 'https://homologacao.nfce.fazenda.sp.gov.br/ws/NFeStatusServico4.asmx',
            'cancellation' => 'https://homologacao.nfce.fazenda.sp.gov.br/ws/NFeRecepcaoEvento4.asmx',
        ],
        // Adicionar outros estados conforme necessário
    ];
    
    // URLs dos webservices por UF (Produção)
    private $webservices_production = [
        'SP' => [
            'authorization' => 'https://nfce.fazenda.sp.gov.br/ws/NFeAutorizacao4.asmx',
            'return_authorization' => 'https://nfce.fazenda.sp.gov.br/ws/NFeRetAutorizacao4.asmx',
            'query_protocol' => 'https://nfce.fazenda.sp.gov.br/ws/NFeConsultaProtocolo4.asmx',
            'query_status' => 'https://nfce.fazenda.sp.gov.br/ws/NFeStatusServico4.asmx',
            'cancellation' => 'https://nfce.fazenda.sp.gov.br/ws/NFeRecepcaoEvento4.asmx',
        ],
        // Adicionar outros estados conforme necessário
    ];
    
    public function __construct($establishment_data) {
        $this->establishment_data = $establishment_data;
        $this->certificate_path = $establishment_data['certificate_file_path'];
        $this->certificate_password = $establishment_data['certificate_password'];
        $this->environment = $establishment_data['nfce_environment'] == 'production' ? 1 : 2;
        $this->uf_code = $establishment_data['state'];
    }
    
    /**
     * Envia NFCe para autorização na SEFAZ
     */
    public function authorizeNFCe($xml_content, $nfce_key) {
        try {
            // Validar certificado
            if (!$this->validateCertificate()) {
                throw new Exception('Certificado digital inválido ou expirado');
            }
            
            // Assinar XML
            $signed_xml = $this->signXML($xml_content, $nfce_key);
            
            // Criar envelope SOAP
            $soap_envelope = $this->createAuthorizationEnvelope($signed_xml, $nfce_key);
            
            // Enviar para SEFAZ
            $response = $this->sendToSEFAZ($soap_envelope, 'authorization');
            
            // Processar resposta
            return $this->processAuthorizationResponse($response);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Consulta retorno da autorização
     */
    public function queryAuthorization($receipt_number) {
        try {
            $soap_envelope = $this->createQueryEnvelope($receipt_number);
            $response = $this->sendToSEFAZ($soap_envelope, 'return_authorization');
            
            return $this->processQueryResponse($response);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Consulta NFCe por chave
     */
    public function queryNFCe($nfce_key) {
        try {
            $soap_envelope = $this->createProtocolQueryEnvelope($nfce_key);
            $response = $this->sendToSEFAZ($soap_envelope, 'query_protocol');
            
            return $this->processProtocolQueryResponse($response);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Cancela NFCe
     */
    public function cancelNFCe($nfce_key, $protocol_number, $reason) {
        try {
            $event_xml = $this->createCancellationEvent($nfce_key, $protocol_number, $reason);
            $signed_event = $this->signXML($event_xml, $nfce_key . '_cancel');
            
            $soap_envelope = $this->createEventEnvelope($signed_event);
            $response = $this->sendToSEFAZ($soap_envelope, 'cancellation');
            
            return $this->processCancellationResponse($response);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verifica status do serviço SEFAZ
     */
    public function checkServiceStatus() {
        try {
            $soap_envelope = $this->createStatusQueryEnvelope();
            $response = $this->sendToSEFAZ($soap_envelope, 'query_status');
            
            return $this->processStatusResponse($response);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 'offline'
            ];
        }
    }
    
    /**
     * Valida certificado digital
     */
    private function validateCertificate() {
        if (!file_exists($this->certificate_path)) {
            return false;
        }
        
        $cert_data = openssl_pkcs12_read(
            file_get_contents($this->certificate_path), 
            $certs, 
            $this->certificate_password
        );
        
        if (!$cert_data) {
            return false;
        }
        
        // Verificar se o certificado não está expirado
        $cert_info = openssl_x509_parse($certs['cert']);
        $valid_until = $cert_info['validTo_time_t'];
        
        return time() < $valid_until;
    }
    
    /**
     * Assina XML com certificado digital
     */
    private function signXML($xml_content, $reference_id) {
        // Carregar certificado
        $cert_data = openssl_pkcs12_read(
            file_get_contents($this->certificate_path), 
            $certs, 
            $this->certificate_password
        );
        
        if (!$cert_data) {
            throw new Exception('Erro ao carregar certificado digital');
        }
        
        // Criar documento XML
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadXML($xml_content);
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = false;
        
        // Encontrar elemento a ser assinado
        $infNFe = $dom->getElementsByTagName('infNFe')->item(0);
        if (!$infNFe) {
            throw new Exception('Elemento infNFe não encontrado no XML');
        }
        
        // Criar assinatura digital
        $signature = $this->createDigitalSignature($dom, $infNFe, $certs);
        
        // Adicionar assinatura ao XML
        $nfe_element = $dom->getElementsByTagName('NFe')->item(0);
        $nfe_element->appendChild($signature);
        
        return $dom->saveXML();
    }
    
    /**
     * Cria assinatura digital XML
     */
    private function createDigitalSignature($dom, $element, $certs) {
        // Calcular hash do elemento
        $canonical = $element->C14N(false, false);
        $hash = base64_encode(hash('sha1', $canonical, true));
        
        // Criar elemento Signature
        $signature = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'Signature');
        
        // SignedInfo
        $signed_info = $dom->createElement('SignedInfo');
        $signature->appendChild($signed_info);
        
        $canonicalization_method = $dom->createElement('CanonicalizationMethod');
        $canonicalization_method->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signed_info->appendChild($canonicalization_method);
        
        $signature_method = $dom->createElement('SignatureMethod');
        $signature_method->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
        $signed_info->appendChild($signature_method);
        
        $reference = $dom->createElement('Reference');
        $reference->setAttribute('URI', '#' . $element->getAttribute('Id'));
        $signed_info->appendChild($reference);
        
        $transforms = $dom->createElement('Transforms');
        $reference->appendChild($transforms);
        
        $transform = $dom->createElement('Transform');
        $transform->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($transform);
        
        $transform2 = $dom->createElement('Transform');
        $transform2->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $transforms->appendChild($transform2);
        
        $digest_method = $dom->createElement('DigestMethod');
        $digest_method->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $reference->appendChild($digest_method);
        
        $digest_value = $dom->createElement('DigestValue', $hash);
        $reference->appendChild($digest_value);
        
        // Calcular assinatura do SignedInfo
        $signed_info_canonical = $signed_info->C14N(false, false);
        $signature_hash = '';
        openssl_sign($signed_info_canonical, $signature_hash, $certs['pkey'], OPENSSL_ALGO_SHA1);
        
        $signature_value = $dom->createElement('SignatureValue', base64_encode($signature_hash));
        $signature->appendChild($signature_value);
        
        // KeyInfo
        $key_info = $dom->createElement('KeyInfo');
        $signature->appendChild($key_info);
        
        $x509_data = $dom->createElement('X509Data');
        $key_info->appendChild($x509_data);
        
        $cert_clean = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\n", "\r"], '', $certs['cert']);
        $x509_certificate = $dom->createElement('X509Certificate', $cert_clean);
        $x509_data->appendChild($x509_certificate);
        
        return $signature;
    }
    
    /**
     * Cria envelope SOAP para autorização
     */
    private function createAuthorizationEnvelope($signed_xml, $nfce_key) {
        $envelope = '<?xml version="1.0" encoding="UTF-8"?>';
        $envelope .= '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:nfe="http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4">';
        $envelope .= '<soap:Header/>';
        $envelope .= '<soap:Body>';
        $envelope .= '<nfe:nfeAutorizacaoLote>';
        $envelope .= '<nfe:nfeCabecMsg>';
        $envelope .= '<![CDATA[<?xml version="1.0" encoding="UTF-8"?>]]>';
        $envelope .= '<nfe:nfeCabecMsg xmlns="http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4">';
        $envelope .= '<cUF>' . $this->getUFCode($this->uf_code) . '</cUF>';
        $envelope .= '<versaoDados>4.00</versaoDados>';
        $envelope .= '</nfe:nfeCabecMsg>';
        $envelope .= '</nfe:nfeCabecMsg>';
        $envelope .= '<nfe:nfeDadosMsg>';
        $envelope .= '<![CDATA[';
        $envelope .= '<?xml version="1.0" encoding="UTF-8"?>';
        $envelope .= '<enviNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">';
        $envelope .= '<idLote>' . date('YmdHis') . '</idLote>';
        $envelope .= '<indSinc>1</indSinc>';
        $envelope .= $signed_xml;
        $envelope .= '</enviNFe>';
        $envelope .= ']]>';
        $envelope .= '</nfe:nfeDadosMsg>';
        $envelope .= '</nfe:nfeAutorizacaoLote>';
        $envelope .= '</soap:Body>';
        $envelope .= '</soap:Envelope>';
        
        return $envelope;
    }
    
    /**
     * Envia requisição para SEFAZ
     */
    private function sendToSEFAZ($soap_envelope, $service) {
        $webservices = $this->environment == 1 ? $this->webservices_production : $this->webservices_homolog;
        
        if (!isset($webservices[$this->uf_code][$service])) {
            throw new Exception('Webservice não configurado para ' . $this->uf_code);
        }
        
        $url = $webservices[$this->uf_code][$service];
        
        // Configurar cURL com certificado
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $soap_envelope);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSLCERT, $this->certificate_path);
        curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $this->certificate_password);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/soap+xml; charset=utf-8',
            'SOAPAction: "http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4/nfeAutorizacaoLote"'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception('Erro na comunicação com SEFAZ: ' . $error);
        }
        
        if ($http_code != 200) {
            throw new Exception('Erro HTTP ' . $http_code . ' na comunicação com SEFAZ');
        }
        
        return $response;
    }
    
    /**
     * Processa resposta de autorização
     */
    private function processAuthorizationResponse($response) {
        $dom = new DOMDocument();
        $dom->loadXML($response);
        
        // Extrair informações da resposta
        $cStat = $dom->getElementsByTagName('cStat')->item(0);
        $xMotivo = $dom->getElementsByTagName('xMotivo')->item(0);
        $nRec = $dom->getElementsByTagName('nRec')->item(0);
        
        if (!$cStat) {
            throw new Exception('Resposta inválida da SEFAZ');
        }
        
        $status_code = $cStat->nodeValue;
        $message = $xMotivo ? $xMotivo->nodeValue : 'Sem mensagem';
        $receipt = $nRec ? $nRec->nodeValue : null;
        
        // Códigos de sucesso: 100 (Autorizado), 103 (Lote recebido)
        if (in_array($status_code, ['100', '103'])) {
            return [
                'success' => true,
                'status_code' => $status_code,
                'message' => $message,
                'receipt_number' => $receipt,
                'authorized' => $status_code == '100'
            ];
        } else {
            return [
                'success' => false,
                'status_code' => $status_code,
                'message' => $message,
                'error' => "Rejeição {$status_code}: {$message}"
            ];
        }
    }
    
    /**
     * Cria evento de cancelamento
     */
    private function createCancellationEvent($nfce_key, $protocol_number, $reason) {
        $sequence = 1;
        $event_id = 'ID' . '110111' . $nfce_key . str_pad($sequence, 2, '0', STR_PAD_LEFT);
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<evento xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.00">';
        $xml .= '<infEvento Id="' . $event_id . '">';
        $xml .= '<cOrgao>' . $this->getUFCode($this->uf_code) . '</cOrgao>';
        $xml .= '<tpAmb>' . $this->environment . '</tpAmb>';
        $xml .= '<CNPJ>' . preg_replace('/\D/', '', $this->establishment_data['document']) . '</CNPJ>';
        $xml .= '<chNFe>' . $nfce_key . '</chNFe>';
        $xml .= '<dhEvento>' . date('c') . '</dhEvento>';
        $xml .= '<tpEvento>110111</tpEvento>';
        $xml .= '<nSeqEvento>' . $sequence . '</nSeqEvento>';
        $xml .= '<verEvento>1.00</verEvento>';
        $xml .= '<detEvento versao="1.00">';
        $xml .= '<descEvento>Cancelamento</descEvento>';
        $xml .= '<nProt>' . $protocol_number . '</nProt>';
        $xml .= '<xJust>' . htmlspecialchars($reason) . '</xJust>';
        $xml .= '</detEvento>';
        $xml .= '</infEvento>';
        $xml .= '</evento>';
        
        return $xml;
    }
    
    /**
     * Obtém código da UF
     */
    private function getUFCode($state) {
        $uf_codes = [
            'AC' => 12, 'AL' => 17, 'AP' => 16, 'AM' => 23, 'BA' => 29,
            'CE' => 23, 'DF' => 53, 'ES' => 32, 'GO' => 52, 'MA' => 21,
            'MT' => 51, 'MS' => 50, 'MG' => 31, 'PA' => 15, 'PB' => 25,
            'PR' => 41, 'PE' => 26, 'PI' => 22, 'RJ' => 33, 'RN' => 24,
            'RS' => 43, 'RO' => 11, 'RR' => 14, 'SC' => 42, 'SP' => 35,
            'SE' => 28, 'TO' => 17
        ];
        
        return $uf_codes[$state] ?? 35; // Default SP
    }
    
    /**
     * Cria envelope para consulta de status
     */
    private function createStatusQueryEnvelope() {
        $envelope = '<?xml version="1.0" encoding="UTF-8"?>';
        $envelope .= '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:nfe="http://www.portalfiscal.inf.br/nfe/wsdl/NFeStatusServico4">';
        $envelope .= '<soap:Header/>';
        $envelope .= '<soap:Body>';
        $envelope .= '<nfe:nfeStatusServicoNF>';
        $envelope .= '<nfe:nfeCabecMsg>';
        $envelope .= '<cUF>' . $this->getUFCode($this->uf_code) . '</cUF>';
        $envelope .= '<versaoDados>4.00</versaoDados>';
        $envelope .= '</nfe:nfeCabecMsg>';
        $envelope .= '<nfe:nfeDadosMsg>';
        $envelope .= '<consStatServ xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">';
        $envelope .= '<tpAmb>' . $this->environment . '</tpAmb>';
        $envelope .= '<cUF>' . $this->getUFCode($this->uf_code) . '</cUF>';
        $envelope .= '<xServ>STATUS</xServ>';
        $envelope .= '</consStatServ>';
        $envelope .= '</nfe:nfeDadosMsg>';
        $envelope .= '</nfe:nfeStatusServicoNF>';
        $envelope .= '</soap:Body>';
        $envelope .= '</soap:Envelope>';
        
        return $envelope;
    }
    
    /**
     * Processa resposta de status
     */
    private function processStatusResponse($response) {
        $dom = new DOMDocument();
        $dom->loadXML($response);
        
        $cStat = $dom->getElementsByTagName('cStat')->item(0);
        $xMotivo = $dom->getElementsByTagName('xMotivo')->item(0);
        
        if (!$cStat) {
            throw new Exception('Resposta inválida da SEFAZ');
        }
        
        $status_code = $cStat->nodeValue;
        $message = $xMotivo ? $xMotivo->nodeValue : 'Sem mensagem';
        
        return [
            'success' => $status_code == '107', // 107 = Serviço em operação
            'status_code' => $status_code,
            'message' => $message,
            'status' => $status_code == '107' ? 'online' : 'offline'
        ];
    }
    
    // Métodos adicionais para consulta de retorno e protocolo...
    private function createQueryEnvelope($receipt_number) {
        // Implementar envelope para consulta de retorno
        // Similar ao createAuthorizationEnvelope mas para consulta
    }
    
    private function processQueryResponse($response) {
        // Processar resposta da consulta de retorno
        // Extrair protocolo de autorização se aprovado
    }
    
    private function createProtocolQueryEnvelope($nfce_key) {
        // Implementar envelope para consulta por chave
    }
    
    private function processProtocolQueryResponse($response) {
        // Processar resposta da consulta por protocolo
    }
    
    private function createEventEnvelope($signed_event) {
        // Implementar envelope para eventos (cancelamento)
    }
    
    private function processCancellationResponse($response) {
        // Processar resposta do cancelamento
    }
}
?>