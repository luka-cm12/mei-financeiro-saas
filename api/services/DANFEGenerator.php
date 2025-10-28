<?php
require_once __DIR__ . '/../../autoload.php';

use TCPDF;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class DANFEGenerator {
    private $nfce_data;
    private $establishment_data;
    private $pdf;
    
    public function __construct($nfce_data, $establishment_data) {
        $this->nfce_data = $nfce_data;
        $this->establishment_data = $establishment_data;
        
        // Configurar TCPDF
        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('MEI Financeiro SaaS');
        $this->pdf->SetAuthor($establishment_data['business_name']);
        $this->pdf->SetTitle('DANFE NFCe ' . $nfce_data['nfce_number']);
        $this->pdf->SetSubject('Documento Auxiliar da NFCe');
        $this->pdf->SetKeywords('NFCe, DANFE, Nota Fiscal');
        
        // Configurações da página
        $this->pdf->SetHeaderData('', 0, '', '', array(0,0,0), array(255,255,255));
        $this->pdf->setFooterData(array(0,0,0), array(255,255,255));
        $this->pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $this->pdf->SetMargins(10, 10, 10);
        $this->pdf->SetHeaderMargin(5);
        $this->pdf->SetFooterMargin(10);
        $this->pdf->SetAutoPageBreak(TRUE, 15);
        $this->pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $this->pdf->SetFont('helvetica', '', 8);
    }
    
    /**
     * Gera o PDF DANFE
     */
    public function generatePDF() {
        try {
            $this->pdf->AddPage();
            
            // Cabeçalho
            $this->addHeader();
            
            // Dados do emitente
            $this->addEmitterData();
            
            // Informações da NFCe
            $this->addNFCeInfo();
            
            // Itens
            $this->addItems();
            
            // Totais
            $this->addTotals();
            
            // Pagamento
            $this->addPayment();
            
            // QR Code
            $this->addQRCode();
            
            // Informações fiscais
            $this->addFiscalInfo();
            
            // Rodapé
            $this->addFooter();
            
            return $this->pdf->Output('', 'S'); // Retorna string do PDF
            
        } catch (Exception $e) {
            throw new Exception('Erro ao gerar DANFE: ' . $e->getMessage());
        }
    }
    
    /**
     * Salva PDF em arquivo
     */
    public function savePDF($file_path) {
        try {
            $pdf_content = $this->generatePDF();
            
            // Criar diretório se não existir
            $dir = dirname($file_path);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            file_put_contents($file_path, $pdf_content);
            
            return true;
            
        } catch (Exception $e) {
            throw new Exception('Erro ao salvar DANFE: ' . $e->getMessage());
        }
    }
    
    /**
     * Adiciona cabeçalho
     */
    private function addHeader() {
        $y = 10;
        
        // Título principal
        $this->pdf->SetFont('helvetica', 'B', 14);
        $this->pdf->SetXY(10, $y);
        $this->pdf->Cell(0, 8, 'DOCUMENTO AUXILIAR DA NOTA FISCAL DE CONSUMIDOR ELETRÔNICA', 0, 1, 'C');
        
        $y += 10;
        
        // Subtítulo
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->SetXY(10, $y);
        $this->pdf->Cell(0, 6, 'DANFE NFCe - Não é documento fiscal', 0, 1, 'C');
        
        return $y + 8;
    }
    
    /**
     * Adiciona dados do emitente
     */
    private function addEmitterData() {
        $y = 30;
        
        // Caixa do emitente
        $this->pdf->Rect(10, $y, 190, 25);
        
        // Nome da empresa
        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->SetXY(12, $y + 2);
        $this->pdf->Cell(0, 6, $this->establishment_data['business_name'], 0, 1);
        
        if ($this->establishment_data['trade_name'] && 
            $this->establishment_data['trade_name'] != $this->establishment_data['business_name']) {
            $this->pdf->SetFont('helvetica', '', 10);
            $this->pdf->SetXY(12, $y + 8);
            $this->pdf->Cell(0, 5, 'Nome Fantasia: ' . $this->establishment_data['trade_name'], 0, 1);
        }
        
        // CNPJ
        $this->pdf->SetFont('helvetica', '', 9);
        $this->pdf->SetXY(12, $y + 14);
        $cnpj = $this->formatDocument($this->establishment_data['document']);
        $this->pdf->Cell(0, 4, 'CNPJ: ' . $cnpj, 0, 1);
        
        // Endereço
        $address = $this->establishment_data['street'] . ', ' . $this->establishment_data['number'];
        if ($this->establishment_data['complement']) {
            $address .= ', ' . $this->establishment_data['complement'];
        }
        $address .= ' - ' . $this->establishment_data['neighborhood'];
        $address .= ' - ' . $this->establishment_data['city'] . '/' . $this->establishment_data['state'];
        $address .= ' - CEP: ' . $this->formatCEP($this->establishment_data['zip_code']);
        
        $this->pdf->SetXY(12, $y + 18);
        $this->pdf->Cell(0, 4, $address, 0, 1);
        
        return $y + 27;
    }
    
    /**
     * Adiciona informações da NFCe
     */
    private function addNFCeInfo() {
        $y = 60;
        
        // Caixa das informações
        $this->pdf->Rect(10, $y, 190, 20);
        
        // Número e série
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->SetXY(12, $y + 2);
        $this->pdf->Cell(0, 5, 'NFCe Nº: ' . $this->nfce_data['nfce_number'] . 
                                 ' - Série: ' . $this->nfce_data['nfce_series'], 0, 1);
        
        // Data de emissão
        $emission_date = date('d/m/Y H:i:s', strtotime($this->nfce_data['emission_date']));
        $this->pdf->SetFont('helvetica', '', 9);
        $this->pdf->SetXY(12, $y + 8);
        $this->pdf->Cell(0, 4, 'Emissão: ' . $emission_date, 0, 1);
        
        // Protocolo (se autorizada)
        if ($this->nfce_data['protocol_number']) {
            $this->pdf->SetXY(12, $y + 12);
            $auth_date = date('d/m/Y H:i:s', strtotime($this->nfce_data['authorization_date']));
            $this->pdf->Cell(0, 4, 'Protocolo: ' . $this->nfce_data['protocol_number'] . 
                                   ' - Autorizada em: ' . $auth_date, 0, 1);
        }
        
        // Chave de acesso
        $this->pdf->SetXY(12, $y + 16);
        $formatted_key = $this->formatNFCeKey($this->nfce_data['nfce_key']);
        $this->pdf->Cell(0, 4, 'Chave de Acesso: ' . $formatted_key, 0, 1);
        
        return $y + 22;
    }
    
    /**
     * Adiciona itens
     */
    private function addItems() {
        $y = 85;
        
        // Cabeçalho da tabela
        $this->pdf->SetFont('helvetica', 'B', 8);
        $this->pdf->Rect(10, $y, 190, 8);
        
        $this->pdf->SetXY(12, $y + 1);
        $this->pdf->Cell(10, 6, 'Item', 0, 0, 'C');
        
        $this->pdf->SetXY(22, $y + 1);
        $this->pdf->Cell(80, 6, 'Descrição', 0, 0, 'L');
        
        $this->pdf->SetXY(102, $y + 1);
        $this->pdf->Cell(20, 6, 'Qtd', 0, 0, 'C');
        
        $this->pdf->SetXY(122, $y + 1);
        $this->pdf->Cell(25, 6, 'Valor Unit.', 0, 0, 'C');
        
        $this->pdf->SetXY(147, $y + 1);
        $this->pdf->Cell(25, 6, 'Valor Total', 0, 0, 'C');
        
        $this->pdf->SetXY(172, $y + 1);
        $this->pdf->Cell(25, 6, 'CFOP', 0, 0, 'C');
        
        $y += 8;
        
        // Itens
        $this->pdf->SetFont('helvetica', '', 8);
        $item_count = 1;
        
        foreach ($this->nfce_data['items'] as $item) {
            // Verificar se precisa de nova página
            if ($y > 250) {
                $this->pdf->AddPage();
                $y = 20;
            }
            
            $this->pdf->Rect(10, $y, 190, 6);
            
            // Número do item
            $this->pdf->SetXY(12, $y + 1);
            $this->pdf->Cell(10, 4, $item_count, 0, 0, 'C');
            
            // Descrição (limitada)
            $description = strlen($item['description']) > 40 ? 
                          substr($item['description'], 0, 37) . '...' : 
                          $item['description'];
            $this->pdf->SetXY(22, $y + 1);
            $this->pdf->Cell(80, 4, $description, 0, 0, 'L');
            
            // Quantidade
            $this->pdf->SetXY(102, $y + 1);
            $this->pdf->Cell(20, 4, number_format($item['quantity'], 2, ',', '.'), 0, 0, 'C');
            
            // Valor unitário
            $this->pdf->SetXY(122, $y + 1);
            $this->pdf->Cell(25, 4, 'R$ ' . number_format($item['unit_price'], 2, ',', '.'), 0, 0, 'R');
            
            // Valor total
            $this->pdf->SetXY(147, $y + 1);
            $this->pdf->Cell(25, 4, 'R$ ' . number_format($item['total_price'], 2, ',', '.'), 0, 0, 'R');
            
            // CFOP
            $this->pdf->SetXY(172, $y + 1);
            $this->pdf->Cell(25, 4, $item['cfop'], 0, 0, 'C');
            
            $y += 6;
            $item_count++;
        }
        
        return $y + 2;
    }
    
    /**
     * Adiciona totais
     */
    private function addTotals() {
        $y = $this->pdf->GetY() + 5;
        
        // Caixa dos totais
        $this->pdf->Rect(130, $y, 70, 25);
        
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->SetXY(132, $y + 2);
        $this->pdf->Cell(0, 5, 'RESUMO DOS VALORES', 0, 1);
        
        $this->pdf->SetFont('helvetica', '', 9);
        
        // Subtotal
        $this->pdf->SetXY(132, $y + 8);
        $this->pdf->Cell(40, 4, 'Subtotal:', 0, 0);
        $this->pdf->SetXY(170, $y + 8);
        $this->pdf->Cell(25, 4, 'R$ ' . number_format($this->nfce_data['total_products'], 2, ',', '.'), 0, 0, 'R');
        
        // Desconto
        if ($this->nfce_data['total_discounts'] > 0) {
            $this->pdf->SetXY(132, $y + 12);
            $this->pdf->Cell(40, 4, 'Desconto:', 0, 0);
            $this->pdf->SetXY(170, $y + 12);
            $this->pdf->Cell(25, 4, 'R$ ' . number_format($this->nfce_data['total_discounts'], 2, ',', '.'), 0, 0, 'R');
        }
        
        // Total
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->SetXY(132, $y + 18);
        $this->pdf->Cell(40, 4, 'TOTAL:', 0, 0);
        $this->pdf->SetXY(170, $y + 18);
        $this->pdf->Cell(25, 4, 'R$ ' . number_format($this->nfce_data['total_amount'], 2, ',', '.'), 0, 0, 'R');
        
        return $y + 27;
    }
    
    /**
     * Adiciona forma de pagamento
     */
    private function addPayment() {
        $y = $this->pdf->GetY() + 5;
        
        // Caixa do pagamento
        $this->pdf->Rect(10, $y, 190, 15);
        
        $this->pdf->SetFont('helvetica', 'B', 9);
        $this->pdf->SetXY(12, $y + 2);
        $this->pdf->Cell(0, 4, 'FORMA DE PAGAMENTO', 0, 1);
        
        $this->pdf->SetFont('helvetica', '', 9);
        
        $payment_method = $this->getPaymentMethodName($this->nfce_data['payment_method']);
        $this->pdf->SetXY(12, $y + 7);
        $this->pdf->Cell(0, 4, $payment_method . ': R$ ' . 
                               number_format($this->nfce_data['payment_amount'], 2, ',', '.'), 0, 1);
        
        if ($this->nfce_data['change_amount'] > 0) {
            $this->pdf->SetXY(12, $y + 11);
            $this->pdf->Cell(0, 4, 'Troco: R$ ' . 
                                   number_format($this->nfce_data['change_amount'], 2, ',', '.'), 0, 1);
        }
        
        return $y + 17;
    }
    
    /**
     * Adiciona QR Code
     */
    private function addQRCode() {
        $y = $this->pdf->GetY() + 5;
        
        try {
            // Gerar conteúdo do QR Code
            $qr_content = $this->generateQRCodeContent();
            
            // Criar QR Code
            $qr_code = new QrCode($qr_content);
            $qr_code->setSize(300);
            $qr_code->setMargin(10);
            
            $writer = new PngWriter();
            $result = $writer->write($qr_code);
            
            // Salvar temporariamente
            $temp_file = sys_get_temp_dir() . '/qrcode_' . $this->nfce_data['nfce_key'] . '.png';
            file_put_contents($temp_file, $result->getString());
            
            // Adicionar ao PDF
            $this->pdf->SetXY(80, $y);
            $this->pdf->Image($temp_file, 80, $y, 40, 40, 'PNG');
            
            // Remover arquivo temporário
            unlink($temp_file);
            
            // Texto abaixo do QR Code
            $this->pdf->SetFont('helvetica', '', 8);
            $this->pdf->SetXY(10, $y + 42);
            $this->pdf->Cell(0, 4, 'Consulte pela Chave de Acesso em:', 0, 1, 'C');
            
            $this->pdf->SetXY(10, $y + 46);
            $url = $this->getSEFAZConsultationURL();
            $this->pdf->Cell(0, 4, $url, 0, 1, 'C');
            
            return $y + 52;
            
        } catch (Exception $e) {
            // Se falhar o QR Code, apenas adicionar texto
            $this->pdf->SetFont('helvetica', '', 9);
            $this->pdf->SetXY(10, $y);
            $this->pdf->Cell(0, 4, 'QR Code indisponível - Consulte pela chave de acesso', 0, 1, 'C');
            
            return $y + 8;
        }
    }
    
    /**
     * Gera conteúdo do QR Code
     */
    private function generateQRCodeContent() {
        $url = $this->getSEFAZConsultationURL();
        $key = $this->nfce_data['nfce_key'];
        $date = date('Y-m-d\TH:i:s', strtotime($this->nfce_data['emission_date']));
        $total = number_format($this->nfce_data['total_amount'], 2, '.', '');
        $digest = hash('sha1', $this->nfce_data['nfce_key']); // Simplificado
        
        return $url . '?chNFe=' . $key . '&nVersao=100&tpAmb=' . 
               ($this->establishment_data['nfce_environment'] == 'production' ? '1' : '2') .
               '&cDest=&dhEmi=' . urlencode($date) . '&vNF=' . $total . '&vICMS=0.00&digVal=' . $digest;
    }
    
    /**
     * Obtém URL de consulta da SEFAZ
     */
    private function getSEFAZConsultationURL() {
        $urls = [
            'SP' => 'https://www.fazenda.sp.gov.br/nfce/qrcode',
            // Adicionar outros estados
        ];
        
        $uf = $this->establishment_data['state'];
        return $urls[$uf] ?? 'https://www.nfce.fazenda.gov.br/qrcode';
    }
    
    /**
     * Adiciona informações fiscais
     */
    private function addFiscalInfo() {
        $y = $this->pdf->GetY() + 5;
        
        $this->pdf->SetFont('helvetica', '', 7);
        
        $this->pdf->SetXY(10, $y);
        $this->pdf->Cell(0, 3, 'INFORMAÇÕES FISCAIS:', 0, 1);
        
        $y += 4;
        
        $info_text = 'Lei da Transparência dos Impostos (Lei Federal 12.741/2012) - ';
        $info_text .= 'O valor aproximado dos tributos incidentes sobre o preço deste produto é de ';
        $info_text .= 'R$ ' . number_format($this->nfce_data['total_tax'], 2, ',', '.') . ' ';
        $info_text .= '(' . number_format(($this->nfce_data['total_tax'] / $this->nfce_data['total_amount']) * 100, 2, ',', '.') . '%).';
        
        $this->pdf->SetXY(10, $y);
        $this->pdf->MultiCell(190, 3, $info_text, 0, 'L');
        
        return $y + 12;
    }
    
    /**
     * Adiciona rodapé
     */
    private function addFooter() {
        $y = $this->pdf->GetY() + 5;
        
        $this->pdf->SetFont('helvetica', '', 7);
        
        $this->pdf->SetXY(10, $y);
        $this->pdf->Cell(0, 3, 'Esta NFCe foi gerada pelo sistema MEI Financeiro SaaS', 0, 1, 'C');
        
        $this->pdf->SetXY(10, $y + 4);
        $this->pdf->Cell(0, 3, 'Desenvolvido para facilitar a gestão fiscal de micro e pequenas empresas', 0, 1, 'C');
    }
    
    /**
     * Utilitários de formatação
     */
    private function formatDocument($document) {
        $document = preg_replace('/\D/', '', $document);
        
        if (strlen($document) == 14) {
            return substr($document, 0, 2) . '.' . 
                   substr($document, 2, 3) . '.' . 
                   substr($document, 5, 3) . '/' . 
                   substr($document, 8, 4) . '-' . 
                   substr($document, 12, 2);
        }
        
        return $document;
    }
    
    private function formatCEP($cep) {
        $cep = preg_replace('/\D/', '', $cep);
        return substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
    }
    
    private function formatNFCeKey($key) {
        return chunk_split($key, 4, ' ');
    }
    
    private function getPaymentMethodName($method) {
        $methods = [
            'money' => 'Dinheiro',
            'card' => 'Cartão de Crédito',
            'debit' => 'Cartão de Débito',
            'pix' => 'PIX',
            'transfer' => 'Transferência Bancária'
        ];
        
        return $methods[$method] ?? 'Outros';
    }
}
?>