<?php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/Establishment.php';

class EstablishmentController {
    private $db;
    private $conn;
    private $authMiddleware;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
        $this->authMiddleware = new AuthMiddleware();
    }

    /**
     * Obter dados do estabelecimento
     */
    public function getEstablishment() {
        try {
            $authResult = $this->authMiddleware->authenticate();
            $userId = $authResult->user_id;

            $establishment = new Establishment($this->conn);
            
            if ($establishment->readByUserId($userId)) {
                // Mascarar senha do certificado
                $data = [
                    'id' => $establishment->id,
                    'business_name' => $establishment->business_name,
                    'trade_name' => $establishment->trade_name,
                    'document_type' => $establishment->document_type,
                    'document' => $establishment->document,
                    'state_registration' => $establishment->state_registration,
                    'municipal_registration' => $establishment->municipal_registration,
                    'zip_code' => $establishment->zip_code,
                    'street' => $establishment->street,
                    'number' => $establishment->number,
                    'complement' => $establishment->complement,
                    'neighborhood' => $establishment->neighborhood,
                    'city' => $establishment->city,
                    'state' => $establishment->state,
                    'country' => $establishment->country,
                    'phone' => $establishment->phone,
                    'email' => $establishment->email,
                    'website' => $establishment->website,
                    'tax_regime' => $establishment->tax_regime,
                    'cnae_main' => $establishment->cnae_main,
                    'cnaes_secondary' => $establishment->cnaes_secondary ? json_decode($establishment->cnaes_secondary, true) : [],
                    'nfce_enabled' => (bool) $establishment->nfce_enabled,
                    'nfce_environment' => $establishment->nfce_environment,
                    'nfce_series' => $establishment->nfce_series,
                    'nfce_next_number' => $establishment->nfce_next_number,
                    'nfce_csc_configured' => !empty($establishment->nfce_csc),
                    'digital_certificate_type' => $establishment->digital_certificate_type,
                    'certificate_uploaded' => !empty($establishment->certificate_file_path),
                    'certificate_expires_at' => $establishment->certificate_expires_at,
                    'certificate_uploaded_at' => $establishment->certificate_uploaded_at,
                    'fiscal_status' => $establishment->fiscal_status,
                    'created_at' => $establishment->created_at,
                    'updated_at' => $establishment->updated_at
                ];

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => $data
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Estabelecimento não encontrado'
                ]);
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Criar ou atualizar estabelecimento
     */
    public function saveEstablishment() {
        try {
            $authResult = $this->authMiddleware->authenticate();
            $userId = $authResult->user_id;

            $data = json_decode(file_get_contents("php://input"), true);

            if (!$this->validateEstablishmentData($data)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Dados inválidos fornecidos'
                ]);
                return;
            }

            $establishment = new Establishment($this->conn);
            $isUpdate = $establishment->readByUserId($userId);

            // Validar documento
            if (!$establishment->validateDocument($data['document'], $data['document_type'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Documento inválido'
                ]);
                return;
            }

            // Mapear dados
            $establishment->user_id = $userId;
            $establishment->business_name = $data['business_name'];
            $establishment->trade_name = $data['trade_name'] ?? '';
            $establishment->document_type = $data['document_type'];
            $establishment->document = preg_replace('/[^0-9]/', '', $data['document']);
            $establishment->state_registration = $data['state_registration'] ?? '';
            $establishment->municipal_registration = $data['municipal_registration'] ?? '';
            $establishment->zip_code = preg_replace('/[^0-9]/', '', $data['zip_code']);
            $establishment->street = $data['street'];
            $establishment->number = $data['number'];
            $establishment->complement = $data['complement'] ?? '';
            $establishment->neighborhood = $data['neighborhood'];
            $establishment->city = $data['city'];
            $establishment->state = strtoupper($data['state']);
            $establishment->country = $data['country'] ?? 'Brasil';
            $establishment->phone = $data['phone'] ?? '';
            $establishment->email = $data['email'] ?? '';
            $establishment->website = $data['website'] ?? '';
            $establishment->tax_regime = $data['tax_regime'] ?? 'mei';
            $establishment->cnae_main = $data['cnae_main'] ?? '';
            $establishment->cnaes_secondary = !empty($data['cnaes_secondary']) ? json_encode($data['cnaes_secondary']) : null;

            if ($isUpdate) {
                $success = $establishment->update();
                $message = 'Estabelecimento atualizado com sucesso';
            } else {
                $success = $establishment->create();
                $message = 'Estabelecimento criado com sucesso';
            }

            if ($success) {
                http_response_code($isUpdate ? 200 : 201);
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'data' => ['id' => $establishment->id]
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao salvar estabelecimento'
                ]);
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Upload de certificado digital
     */
    public function uploadCertificate() {
        try {
            $authResult = $this->authMiddleware->authenticate();
            $userId = $authResult->user_id;

            // Verificar se o estabelecimento existe
            $establishment = new Establishment($this->conn);
            if (!$establishment->readByUserId($userId)) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Estabelecimento não encontrado'
                ]);
                return;
            }

            // Verificar se foi enviado arquivo
            if (!isset($_FILES['certificate']) || $_FILES['certificate']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Arquivo de certificado não enviado ou inválido'
                ]);
                return;
            }

            $file = $_FILES['certificate'];
            $password = $_POST['password'] ?? '';
            $type = $_POST['type'] ?? 'A1';

            // Validar tipo de arquivo
            $allowedExtensions = ['pfx', 'p12'];
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($fileExtension, $allowedExtensions)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Tipo de arquivo inválido. Use .pfx ou .p12'
                ]);
                return;
            }

            // Criar diretório se não existir
            $uploadDir = __DIR__ . '/../storage/certificates/' . $userId . '/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Gerar nome único para o arquivo
            $fileName = 'certificate_' . time() . '.' . $fileExtension;
            $filePath = $uploadDir . $fileName;

            // Mover arquivo
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao salvar arquivo'
                ]);
                return;
            }

            // Validar certificado
            $certInfo = $this->validateCertificate($filePath, $password);
            if (!$certInfo) {
                unlink($filePath); // Remover arquivo inválido
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Certificado inválido ou senha incorreta'
                ]);
                return;
            }

            // Atualizar dados do certificado no banco
            $certData = [
                'type' => $type,
                'file_path' => $filePath,
                'password' => $password,
                'expires_at' => $certInfo['expires_at']
            ];

            if ($establishment->updateDigitalCertificate($certData)) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Certificado digital salvo com sucesso',
                    'data' => [
                        'type' => $type,
                        'expires_at' => $certInfo['expires_at'],
                        'subject' => $certInfo['subject']
                    ]
                ]);
            } else {
                unlink($filePath); // Remover arquivo se falhar no banco
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao salvar dados do certificado'
                ]);
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Configurar NFCe
     */
    public function configureNFCe() {
        try {
            $authResult = $this->authMiddleware->authenticate();
            $userId = $authResult->user_id;

            $data = json_decode(file_get_contents("php://input"), true);

            // Verificar se o estabelecimento existe
            $establishment = new Establishment($this->conn);
            if (!$establishment->readByUserId($userId)) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Estabelecimento não encontrado'
                ]);
                return;
            }

            // Validar dados NFCe
            if (!isset($data['enabled'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Dados de configuração NFCe inválidos'
                ]);
                return;
            }

            $nfceConfig = [
                'enabled' => (bool) $data['enabled'],
                'environment' => $data['environment'] ?? 'homologation',
                'series' => (int) ($data['series'] ?? 1),
                'csc' => $data['csc'] ?? '',
                'csc_id' => $data['csc_id'] ?? ''
            ];

            // Se habilitar NFCe, validar se tem certificado
            if ($nfceConfig['enabled'] && empty($establishment->certificate_file_path)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Certificado digital é obrigatório para emitir NFCe'
                ]);
                return;
            }

            if ($establishment->updateNFCeConfig($nfceConfig)) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Configuração NFCe atualizada com sucesso'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao atualizar configuração NFCe'
                ]);
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Buscar CEP
     */
    public function searchCep($cep) {
        try {
            $cep = preg_replace('/[^0-9]/', '', $cep);
            
            if (strlen($cep) !== 8) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'CEP inválido'
                ]);
                return;
            }

            // Usar ViaCEP API
            $url = "https://viacep.com.br/ws/{$cep}/json/";
            $response = file_get_contents($url);
            
            if ($response === false) {
                throw new Exception('Erro ao consultar CEP');
            }

            $data = json_decode($response, true);
            
            if (isset($data['erro'])) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'CEP não encontrado'
                ]);
                return;
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => [
                    'zip_code' => $data['cep'],
                    'street' => $data['logradouro'],
                    'neighborhood' => $data['bairro'],
                    'city' => $data['localidade'],
                    'state' => $data['uf']
                ]
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Validar dados do estabelecimento
     */
    private function validateEstablishmentData($data) {
        $required = [
            'business_name', 'document_type', 'document',
            'zip_code', 'street', 'number', 'neighborhood', 'city', 'state'
        ];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }

        // Validar tipo de documento
        if (!in_array($data['document_type'], ['cpf', 'cnpj'])) {
            return false;
        }

        // Validar UF
        $validStates = [
            'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 
            'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 
            'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'
        ];

        if (!in_array(strtoupper($data['state']), $validStates)) {
            return false;
        }

        return true;
    }

    /**
     * Validar certificado digital
     */
    private function validateCertificate($filePath, $password) {
        try {
            $certData = file_get_contents($filePath);
            
            if (!openssl_pkcs12_read($certData, $certs, $password)) {
                return false;
            }

            $certInfo = openssl_x509_parse($certs['cert']);
            
            if (!$certInfo) {
                return false;
            }

            return [
                'subject' => $certInfo['subject']['CN'] ?? 'Não identificado',
                'expires_at' => date('Y-m-d', $certInfo['validTo_time_t']),
                'valid_from' => date('Y-m-d', $certInfo['validFrom_time_t'])
            ];

        } catch (Exception $e) {
            return false;
        }
    }
}
?>