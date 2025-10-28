<?php
namespace Endroid\QrCode;

class QrCode {
    private $text;
    
    public function __construct($text = '') {
        $this->text = $text;
    }
    
    public function setText($text) {
        $this->text = $text;
        return $this;
    }
    
    public function setSize($size) {
        return $this;
    }
    
    public function setMargin($margin) {
        return $this;
    }
    
    public function setEncoding($encoding) {
        return $this;
    }
    
    public function setErrorCorrectionLevel($level) {
        return $this;
    }
    
    public function setForegroundColor($color) {
        return $this;
    }
    
    public function setBackgroundColor($color) {
        return $this;
    }
    
    public function writeString() {
        return "Mock QR Code data for: " . $this->text;
    }
    
    public function writeFile($path) {
        file_put_contents($path, $this->writeString());
    }
}

class Color {
    public function __construct($r, $g, $b, $a = 0) {
        // Mock constructor
    }
}

class ErrorCorrectionLevel {
    const LOW = 'L';
    const MEDIUM = 'M';
    const QUARTILE = 'Q';
    const HIGH = 'H';
}

class Encoding {
    const UTF_8 = 'UTF-8';
}
?>