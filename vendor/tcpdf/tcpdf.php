<?php
/**
 * Mock TCPDF class for development
 * In production, this should be replaced with the actual TCPDF library
 */

if (!class_exists('TCPDF')) {
    
    class TCPDF {
        
        public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8', $diskcache = false) {
            // Mock constructor
        }
        
        public function SetCreator($creator) {
            // Mock method
        }
        
        public function SetAuthor($author) {
            // Mock method
        }
        
        public function SetTitle($title) {
            // Mock method
        }
        
        public function SetSubject($subject) {
            // Mock method
        }
        
        public function SetHeaderData($logo = '', $logo_width = 0, $title = '', $string = '') {
            // Mock method
        }
        
        public function setHeaderFont($font) {
            // Mock method
        }
        
        public function setFooterFont($font) {
            // Mock method
        }
        
        public function SetDefaultMonospacedFont($font) {
            // Mock method
        }
        
        public function SetMargins($left, $top, $right = null) {
            // Mock method
        }
        
        public function SetHeaderMargin($margin) {
            // Mock method
        }
        
        public function SetFooterMargin($margin) {
            // Mock method
        }
        
        public function SetAutoPageBreak($auto, $margin = 0) {
            // Mock method
        }
        
        public function setImageScale($scale) {
            // Mock method
        }
        
        public function AddPage($orientation = '') {
            // Mock method
        }
        
        public function SetFont($family, $style = '', $size = 0, $fontfile = '', $subset = 'default', $out = true) {
            // Mock method
        }
        
        public function writeHTML($html, $ln = true, $fill = false, $reseth = false, $cell = false, $align = '') {
            // Mock method - in development, just show HTML content
            if (WP_DEBUG) {
                echo "<!-- TCPDF Mock: Would generate PDF with HTML content -->\n";
                echo "<!-- HTML Content: " . esc_html($html) . " -->\n";
            }
        }
        
        public function SetProtection($permissions = array('print', 'modify', 'copy', 'annot-forms', 'fill-forms', 'extract', 'assemble', 'print-high'), $user_pass = '', $owner_pass = null, $mode = 0, $pubkeys = null) {
            // Mock method
        }
        
        public function Output($name = 'doc.pdf', $dest = 'I') {
            // Mock method - in development, show message instead of generating PDF
            if ($dest === 'D') {
                header('Content-Type: text/html; charset=UTF-8');
                echo '<h1>PDF Generation (Mock)</h1>';
                echo '<p>In development mode, PDF generation is mocked.</p>';
                echo '<p>Filename: ' . esc_html($name) . '</p>';
                echo '<p>To enable actual PDF generation, install TCPDF library.</p>';
                echo '<p><a href="javascript:history.back()">戻る</a></p>';
                exit;
            }
        }
    }
    
    // Define TCPDF constants if not defined
    if (!defined('PDF_PAGE_ORIENTATION')) {
        define('PDF_PAGE_ORIENTATION', 'P');
    }
    if (!defined('PDF_UNIT')) {
        define('PDF_UNIT', 'mm');
    }
    if (!defined('PDF_PAGE_FORMAT')) {
        define('PDF_PAGE_FORMAT', 'A4');
    }
    if (!defined('PDF_MARGIN_LEFT')) {
        define('PDF_MARGIN_LEFT', 15);
    }
    if (!defined('PDF_MARGIN_TOP')) {
        define('PDF_MARGIN_TOP', 27);
    }
    if (!defined('PDF_MARGIN_RIGHT')) {
        define('PDF_MARGIN_RIGHT', 15);
    }
    if (!defined('PDF_MARGIN_HEADER')) {
        define('PDF_MARGIN_HEADER', 5);
    }
    if (!defined('PDF_MARGIN_FOOTER')) {
        define('PDF_MARGIN_FOOTER', 10);
    }
    if (!defined('PDF_MARGIN_BOTTOM')) {
        define('PDF_MARGIN_BOTTOM', 25);
    }
    if (!defined('PDF_IMAGE_SCALE_RATIO')) {
        define('PDF_IMAGE_SCALE_RATIO', 1.25);
    }
}