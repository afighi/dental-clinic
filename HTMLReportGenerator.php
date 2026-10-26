<?php
/**
 * HTML Report Generator
 * A simple class for generating HTML reports that can be printed to PDF
 */
class HTMLReportGenerator {
    private $title;
    private $content = '';
    private $styles = '
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            font-size: 12pt;
            color: #333;
        }
        .report-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .report-title {
            font-size: 24pt;
            font-weight: bold;
            margin: 10px 0;
        }
        .report-subtitle {
            font-size: 14pt;
            color: #555;
            margin: 5px 0 20px 0;
        }
        .report-meta {
            margin: 10px 0;
            font-size: 10pt;
            color: #777;
        }
        .report-section {
            margin: 20px 0;
        }
        .section-title {
            font-size: 16pt;
            font-weight: bold;
            margin: 15px 0 10px 0;
            color: #0066cc;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        .section-subtitle {
            font-size: 14pt;
            font-weight: bold;
            margin: 10px 0;
            color: #444;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th {
            background-color: #f5f5f5;
            padding: 8px;
            text-align: left;
            font-weight: bold;
        }
        td {
            padding: 8px;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10pt;
            color: #777;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        @media print {
            body {
                margin: 0;
                padding: 15mm;
                font-size: 11pt;
            }
            .no-print {
                display: none;
            }
            .page-break {
                page-break-after: always;
            }
        }
    ';
    
    /**
     * Constructor
     * 
     * @param string $title Report title
     */
    public function __construct($title) {
        $this->title = $title;
        $this->initializeDocument();
    }
    
    /**
     * Initialize the HTML document
     */
    private function initializeDocument() {
        $this->content = '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>' . htmlspecialchars($this->title) . '</title>
                <style>' . $this->styles . '</style>
            </head>
            <body>
                <div class="report-content">
        ';
    }
    
    /**
     * Add report header
     * 
     * @param string $subtitle Report subtitle
     * @param array $metadata Metadata to display (key-value pairs)
     */
    public function addHeader($subtitle = '', $metadata = []) {
        $header = '<div class="report-header">
            <div class="report-title">' . htmlspecialchars($this->title) . '</div>';
        
        if (!empty($subtitle)) {
            $header .= '<div class="report-subtitle">' . htmlspecialchars($subtitle) . '</div>';
        }
        
        if (!empty($metadata)) {
            $header .= '<div class="report-meta">';
            foreach ($metadata as $key => $value) {
                $header .= '<div><strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars($value) . '</div>';
            }
            $header .= '</div>';
        }
        
        $header .= '</div>';
        $this->content .= $header;
    }
    
    /**
     * Add a section to the report
     * 
     * @param string $title Section title
     * @param string $content Section content
     */
    public function addSection($title, $content = '') {
        $section = '<div class="report-section">
            <div class="section-title">' . htmlspecialchars($title) . '</div>';
        
        if (!empty($content)) {
            $section .= '<div>' . $content . '</div>';
        }
        
        $section .= '</div>';
        $this->content .= $section;
    }
    
    /**
     * Add a subsection to the report
     * 
     * @param string $title Subsection title
     * @param string $content Subsection content
     */
    public function addSubsection($title, $content = '') {
        $subsection = '<div class="report-subsection">
            <div class="section-subtitle">' . htmlspecialchars($title) . '</div>';
        
        if (!empty($content)) {
            $subsection .= '<div>' . $content . '</div>';
        }
        
        $subsection .= '</div>';
        $this->content .= $subsection;
    }
    
    /**
     * Add a paragraph of text
     * 
     * @param string $text Paragraph text
     */
    public function addParagraph($text) {
        $this->content .= '<p>' . nl2br(htmlspecialchars($text)) . '</p>';
    }
    
    /**
     * Add a table to the report
     * 
     * @param array $headers Table headers
     * @param array $rows Table data
     */
    public function addTable($headers, $rows) {
        $table = '<table>';
        
        // Add headers
        if (!empty($headers)) {
            $table .= '<thead><tr>';
            foreach ($headers as $header) {
                $table .= '<th>' . htmlspecialchars($header) . '</th>';
            }
            $table .= '</tr></thead>';
        }
        
        // Add rows
        if (!empty($rows)) {
            $table .= '<tbody>';
            foreach ($rows as $row) {
                $table .= '<tr>';
                foreach ($row as $cell) {
                    $table .= '<td>' . htmlspecialchars($cell) . '</td>';
                }
                $table .= '</tr>';
            }
            $table .= '</tbody>';
        }
        
        $table .= '</table>';
        $this->content .= $table;
    }
    
    /**
     * Add a page break
     */
    public function addPageBreak() {
        $this->content .= '<div class="page-break"></div>';
    }
    
    /**
     * Add footer to the report
     * 
     * @param string $text Footer text
     */
    public function addFooter($text) {
        $this->content .= '<div class="footer">' . htmlspecialchars($text) . '</div>';
    }
    
    /**
     * Add print button
     */
    public function addPrintButton() {
        $this->content .= '
            <div class="no-print" style="text-align: center; margin: 20px 0;">
                <button onclick="window.print()" style="padding: 10px 20px; background-color: #0066cc; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Print Report
                </button>
            </div>
        ';
    }
    
    /**
     * Finalize the HTML document
     */
    private function finalizeDocument() {
        $this->content .= '
                </div>
            </body>
            </html>
        ';
    }
    
    /**
     * Get the complete HTML content
     * 
     * @return string HTML content
     */
    public function getHTML() {
        $this->finalizeDocument();
        return $this->content;
    }
    
    /**
     * Save the report to a file
     * 
     * @param string $filename Filename to save to
     * @return bool Success status
     */
    public function saveToFile($filename) {
        try {
            $html = $this->getHTML();
            file_put_contents($filename, $html);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Output the report directly to the browser
     */
    public function display() {
        $html = $this->getHTML();
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }
    
    /**
     * Output the report as a downloadable HTML file
     * 
     * @param string $filename Suggested filename
     */
    public function download($filename) {
        $html = $this->getHTML();
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($html));
        echo $html;
        exit;
    }
}