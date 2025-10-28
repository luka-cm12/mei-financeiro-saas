<?php
require_once __DIR__ . '/../../phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../endroid/qr-code/src/QrCode.php';

class TCPDF {
    protected $pages = [];
    protected $current_page = 0;
    
    public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4') {
        // Mock constructor
    }
    
    public function AddPage($orientation = '') {
        $this->current_page++;
    }
    
    public function SetFont($family, $style = '', $size = 12) {
        // Mock method
    }
    
    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '') {
        // Mock method
    }
    
    public function Ln($h = null) {
        // Mock method
    }
    
    public function SetXY($x, $y) {
        // Mock method
    }
    
    public function SetTextColor($r, $g = null, $b = null) {
        // Mock method
    }
    
    public function SetDrawColor($r, $g = null, $b = null) {
        // Mock method
    }
    
    public function Rect($x, $y, $w, $h, $style = '') {
        // Mock method
    }
    
    public function Image($file, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = '') {
        // Mock method
    }
    
    public function Output($dest = 'I', $name = 'doc.pdf', $isUTF8 = false) {
        return "Mock PDF content for: $name";
    }
}
?>