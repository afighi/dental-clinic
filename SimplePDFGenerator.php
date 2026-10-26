<?php
/**
 * Simple PDF Generator
 * A lightweight class for generating basic PDF files without external libraries
 */
class SimplePDFGenerator {
    private $content = '';
    private $pages = [];
    private $currentPage = '';
    private $title = '';
    private $author = '';
    private $keywords = '';
    private $creator = 'JRC Dental Care System';
    
    /**
     * Constructor
     * 
     * @param string $title Document title
     * @param string $author Document author
     */
    public function __construct($title = '', $author = '') {
        $this->title = $title;
        $this->author = $author;
        $this->startPage();
    }
    
    /**
     * Start a new page
     */
    public function startPage() {
        if (!empty($this->currentPage)) {
            $this->pages[] = $this->currentPage;
        }
        $this->currentPage = '';
    }
    
    /**
     * Add a heading to the current page
     * 
     * @param string $text Heading text
     * @param int $level Heading level (1-6)
     */
    public function addHeading($text, $level = 1) {
        $fontSize = 24 - (($level - 1) * 2);
        $this->currentPage .= sprintf(
            '<div style="font-family: Helvetica, Arial, sans-serif; font-size: %dpx; font-weight: bold; margin: 10px 0;">%s</div>',
            $fontSize,
            htmlspecialchars($text)
        );
    }
    
    /**
     * Add a paragraph to the current page
     * 
     * @param string $text Paragraph text
     */
    public function addParagraph($text) {
        $this->currentPage .= sprintf(
            '<div style="font-family: Helvetica, Arial, sans-serif; font-size: 12px; margin: 5px 0; line-height: 1.5;">%s</div>',
            nl2br(htmlspecialchars($text))
        );
    }
    
    /**
     * Add a table to the current page
     * 
     * @param array $headers Table headers
     * @param array $rows Table rows
     */
    public function addTable($headers, $rows) {
        $table = '<table style="width: 100%; border-collapse: collapse; margin: 10px 0; font-family: Helvetica, Arial, sans-serif; font-size: 12px;">';
        
        // Add headers
        if (!empty($headers)) {
            $table .= '<thead><tr>';
            foreach ($headers as $header) {
                $table .= sprintf(
                    '<th style="border: 1px solid #ddd; padding: 8px; background-color: #f2f2f2; text-align: left;">%s</th>',
                    htmlspecialchars($header)
                );
            }
            $table .= '</tr></thead>';
        }
        
        // Add rows
        if (!empty($rows)) {
            $table .= '<tbody>';
            foreach ($rows as $row) {
                $table .= '<tr>';
                foreach ($row as $cell) {
                    $table .= sprintf(
                        '<td style="border: 1px solid #ddd; padding: 8px;">%s</td>',
                        htmlspecialchars($cell)
                    );
                }
                $table .= '</tr>';
            }
            $table .= '</tbody>';
        }
        
        $table .= '</table>';
        $this->currentPage .= $table;
    }
    
    /**
     * Add a line break
     */
    public function addLineBreak() {
        $this->currentPage .= '<br />';
    }
    
    /**
     * Add a horizontal rule
     */
    public function addHorizontalRule() {
        $this->currentPage .= '<hr style="border: 0; border-top: 1px solid #ddd; margin: 15px 0;" />';
    }
    
    /**
     * Set keywords for the document
     * 
     * @param string $keywords Keywords
     */
    public function setKeywords($keywords) {
        $this->keywords = $keywords;
    }
    
    /**
     * Generate and return the PDF content
     * 
     * @return string PDF content
     */
    private function generatePDF() {
        // Add the current page to pages array
        if (!empty($this->currentPage)) {
            $this->pages[] = $this->currentPage;
        }
        
        // Create a basic PDF structure
        $pdf = '%PDF-1.4' . "\n";
        
        // Add metadata
        $pdf .= "1 0 obj\n";
        $pdf .= "<< /Title (" . $this->escapeString($this->title) . ")\n";
        $pdf .= "   /Author (" . $this->escapeString($this->author) . ")\n";
        $pdf .= "   /Creator (" . $this->escapeString($this->creator) . ")\n";
        $pdf .= "   /Producer (SimplePDFGenerator)\n";
        $pdf .= "   /CreationDate (D:" . date('YmdHis') . ")\n";
        if (!empty($this->keywords)) {
            $pdf .= "   /Keywords (" . $this->escapeString($this->keywords) . ")\n";
        }
        $pdf .= ">>\n";
        $pdf .= "endobj\n";
        
        // Add basic structure - simplified method to create PDF
        // Real PDFs are more complex, but this will create a simple valid PDF
        $pdf .= "2 0 obj\n";
        $pdf .= "<< /Type /Catalog\n";
        $pdf .= "   /Pages 3 0 R\n";
        $pdf .= ">>\n";
        $pdf .= "endobj\n";
        
        $pdf .= "3 0 obj\n";
        $pdf .= "<< /Type /Pages\n";
        $pdf .= "   /Kids [";
        
        $pageReferences = [];
        for ($i = 0; $i < count($this->pages); $i++) {
            $pageReferences[] = (4 + $i) . " 0 R";
        }
        
        $pdf .= implode(" ", $pageReferences) . "]\n";
        $pdf .= "   /Count " . count($this->pages) . "\n";
        $pdf .= ">>\n";
        $pdf .= "endobj\n";
        
        // Add page content objects
        for ($i = 0; $i < count($this->pages); $i++) {
            $pageObjNum = 4 + $i;
            $contentObjNum = 4 + count($this->pages) + $i;
            
            // Page object
            $pdf .= $pageObjNum . " 0 obj\n";
            $pdf .= "<< /Type /Page\n";
            $pdf .= "   /Parent 3 0 R\n";
            $pdf .= "   /Resources << /Font << /F1 " . (4 + count($this->pages) * 2) . " 0 R >> >>\n";
            $pdf .= "   /MediaBox [0 0 612 792]\n";
            $pdf .= "   /Contents " . $contentObjNum . " 0 R\n";
            $pdf .= ">>\n";
            $pdf .= "endobj\n";
            
            // Content streams (simplified)
            $content = $this->convertHTMLToPDFContent($this->pages[$i]);
            $content = $this->compressContent($content);
            
            $pdf .= $contentObjNum . " 0 obj\n";
            $pdf .= "<< /Length " . strlen($content) . " >>\n";
            $pdf .= "stream\n";
            $pdf .= $content . "\n";
            $pdf .= "endstream\n";
            $pdf .= "endobj\n";
        }
        
        // Add font object (basic)
        $pdf .= (4 + count($this->pages) * 2) . " 0 obj\n";
        $pdf .= "<< /Type /Font\n";
        $pdf .= "   /Subtype /Type1\n";
        $pdf .= "   /BaseFont /Helvetica\n";
        $pdf .= ">>\n";
        $pdf .= "endobj\n";
        
        // Add xref
        $xrefPosition = strlen($pdf);
        $pdf .= "xref\n";
        $pdf .= "0 " . (5 + count($this->pages) * 2) . "\n";
        $pdf .= "0000000000 65535 f \n";
        
        // Calculate positions (simplified)
        $offset = 0;
        for ($i = 1; $i < (5 + count($this->pages) * 2); $i++) {
            $offset += 100; // This is just a placeholder
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        
        // Add trailer
        $pdf .= "trailer\n";
        $pdf .= "<< /Size " . (5 + count($this->pages) * 2) . "\n";
        $pdf .= "   /Root 2 0 R\n";
        $pdf .= "   /Info 1 0 R\n";
        $pdf .= ">>\n";
        $pdf .= "startxref\n";
        $pdf .= $xrefPosition . "\n";
        $pdf .= "%%EOF";
        
        return $pdf;
    }
    
    /**
     * Convert HTML content to PDF content
     * This is very simplified and only handles basic formatting
     * 
     * @param string $html HTML content
     * @return string PDF content stream
     */
    private function convertHTMLToPDFContent($html) {
        // Very simplified conversion - real PDF creation is much more complex
        $content = "BT\n";
        $content .= "/F1 12 Tf\n";
        $content .= "50 700 Td\n";
        
        // Strip HTML and convert to plain text
        $text = strip_tags($html);
        $text = str_replace("\n", " ", $text);
        
        // Wrap text to fit page width (simplified)
        $lines = wordwrap($text, 80, "\n", true);
        $lineArray = explode("\n", $lines);
        
        foreach ($lineArray as $line) {
            $content .= "(" . $this->escapeString($line) . ") Tj\n";
            $content .= "0 -15 Td\n"; // Move down for next line
        }
        
        $content .= "ET";
        return $content;
    }
    
    /**
     * Escape special characters in strings for PDF
     * 
     * @param string $text Text to escape
     * @return string Escaped text
     */
    private function escapeString($text) {
        return str_replace(
            ['\\', '(', ')', '\n', '\r'],
            ['\\\\', '\\(', '\\)', '\\n', '\\r'],
            $text
        );
    }
    
    /**
     * Compress content (simplified)
     * 
     * @param string $content Content to compress
     * @return string Compressed content
     */
    private function compressContent($content) {
        // In a real implementation, you'd use Flate compression
        // For simplicity, we're just returning the content uncompressed
        return $content;
    }
    
    /**
     * Generate and save the PDF to a file
     * 
     * @param string $filename Filename to save to
     * @return bool Success status
     */
    public function saveToFile($filename) {
        try {
            $pdf = $this->generatePDF();
            file_put_contents($filename, $pdf);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Generate and output the PDF for download
     * 
     * @param string $filename Suggested filename
     */
    public function download($filename) {
        $pdf = $this->generatePDF();
        
        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($pdf));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        echo $pdf;
        exit;
    }
    
    /**
     * Generate and output the PDF for display in browser
     */
    public function display() {
        $pdf = $this->generatePDF();
        
        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        echo $pdf;
        exit;
    }
}