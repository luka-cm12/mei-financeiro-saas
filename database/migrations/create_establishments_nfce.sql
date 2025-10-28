-- Tabela para dados do estabelecimento (empresa/MEI)
CREATE TABLE IF NOT EXISTS establishments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    
    -- Dados básicos
    business_name VARCHAR(255) NOT NULL, -- Razão social
    trade_name VARCHAR(255) NULL, -- Nome fantasia
    document_type ENUM('cpf', 'cnpj') NOT NULL DEFAULT 'cpf',
    document VARCHAR(20) NOT NULL,
    state_registration VARCHAR(50) NULL, -- Inscrição estadual
    municipal_registration VARCHAR(50) NULL, -- Inscrição municipal
    
    -- Endereço
    zip_code VARCHAR(10) NOT NULL,
    street VARCHAR(255) NOT NULL,
    number VARCHAR(20) NOT NULL,
    complement VARCHAR(100) NULL,
    neighborhood VARCHAR(100) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(2) NOT NULL,
    country VARCHAR(50) DEFAULT 'Brasil',
    
    -- Contato
    phone VARCHAR(20) NULL,
    email VARCHAR(255) NULL,
    website VARCHAR(255) NULL,
    
    -- Configurações fiscais
    tax_regime ENUM('simples_nacional', 'lucro_presumido', 'lucro_real', 'mei') DEFAULT 'mei',
    cnae_main VARCHAR(10) NULL, -- CNAE principal
    cnaes_secondary TEXT NULL, -- CNAEs secundários (JSON)
    
    -- NFCe Configuration
    nfce_enabled TINYINT(1) DEFAULT 0,
    nfce_environment ENUM('production', 'homologation') DEFAULT 'homologation',
    nfce_series INT DEFAULT 1,
    nfce_next_number INT DEFAULT 1,
    nfce_csc VARCHAR(50) NULL, -- Código de Segurança do Contribuinte
    nfce_csc_id VARCHAR(10) NULL, -- ID do CSC
    
    -- Certificado digital
    digital_certificate_type ENUM('A1', 'A3') NULL,
    certificate_file_path VARCHAR(500) NULL,
    certificate_password VARCHAR(255) NULL, -- Encrypted
    certificate_expires_at DATE NULL,
    certificate_uploaded_at DATETIME NULL,
    
    -- Status e controle
    is_active TINYINT(1) DEFAULT 1,
    fiscal_status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_establishment (user_id),
    INDEX idx_document (document),
    INDEX idx_nfce_status (nfce_enabled, is_active)
);

-- Tabela para histórico de NFCe emitidas
CREATE TABLE IF NOT EXISTS nfce_emissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    establishment_id INT NOT NULL,
    user_id INT NOT NULL,
    
    -- Identificação da NFCe
    number INT NOT NULL,
    series INT NOT NULL,
    access_key VARCHAR(44) NOT NULL, -- Chave de acesso
    protocol VARCHAR(50) NULL, -- Protocolo de autorização
    
    -- Status
    status ENUM('draft', 'processing', 'authorized', 'rejected', 'cancelled') DEFAULT 'draft',
    environment ENUM('production', 'homologation') NOT NULL,
    
    -- Dados fiscais
    emission_date DATETIME NOT NULL,
    operation_type ENUM('sale', 'return', 'transfer') DEFAULT 'sale',
    total_amount DECIMAL(10,2) NOT NULL,
    total_tax DECIMAL(10,2) DEFAULT 0.00,
    
    -- XML e arquivos
    xml_content LONGTEXT NULL, -- XML da NFCe
    xml_file_path VARCHAR(500) NULL,
    pdf_file_path VARCHAR(500) NULL,
    qr_code TEXT NULL, -- QR Code para consulta
    
    -- Dados do cliente (opcional para NFCe)
    customer_name VARCHAR(255) NULL,
    customer_document VARCHAR(20) NULL,
    customer_email VARCHAR(255) NULL,
    
    -- Produtos/Serviços (JSON)
    items JSON NOT NULL,
    
    -- Retorno SEFAZ
    sefaz_response JSON NULL,
    rejection_reason TEXT NULL,
    
    -- Cancelamento
    cancelled_at DATETIME NULL,
    cancellation_reason TEXT NULL,
    cancellation_protocol VARCHAR(50) NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_access_key (access_key),
    INDEX idx_establishment_emissions (establishment_id, status),
    INDEX idx_emission_date (emission_date),
    INDEX idx_status (status)
);

-- Tabela para produtos/serviços do estabelecimento
CREATE TABLE IF NOT EXISTS establishment_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    establishment_id INT NOT NULL,
    
    -- Dados do produto
    code VARCHAR(50) NULL, -- Código interno
    barcode VARCHAR(50) NULL, -- Código de barras
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    
    -- Classificação fiscal
    ncm VARCHAR(10) NULL, -- Nomenclatura Comum do Mercosul
    cfop VARCHAR(4) DEFAULT '5102', -- Código Fiscal de Operações
    cest VARCHAR(10) NULL, -- Código Especificador da Substituição Tributária
    
    -- Preços
    unit VARCHAR(10) DEFAULT 'UN', -- Unidade
    price DECIMAL(10,2) NOT NULL,
    cost_price DECIMAL(10,2) NULL,
    
    -- Impostos
    icms_origin INT DEFAULT 0, -- Origem da mercadoria (0-Nacional)
    icms_cst VARCHAR(3) DEFAULT '102', -- CST do ICMS
    pis_cst VARCHAR(2) DEFAULT '07', -- CST do PIS
    cofins_cst VARCHAR(2) DEFAULT '07', -- CST do COFINS
    
    -- Controle
    is_active TINYINT(1) DEFAULT 1,
    stock_quantity DECIMAL(10,3) DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE,
    
    INDEX idx_establishment_products (establishment_id, is_active),
    INDEX idx_code (code),
    INDEX idx_barcode (barcode)
);