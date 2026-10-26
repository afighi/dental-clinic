<?php
/**
 * PDFReportGenerator Class
 * Generates PDF reports for the JRC Dental Care System using FPDF
 */
class PDFReportGenerator {
    private $pdf;
    private $reportTitle;
    
    /**
     * Constructor
     * 
     * @param string $reportName The name of the report
     */
    public function __construct($reportName) {
        // Include FPDF library (download and add to project first)
        require_once('fpdf/fpdf.php');
        
        // Initialize PDF object
        $this->pdf = new FPDF('P', 'mm', 'A4');
        
        // Set document information
        $this->pdf->SetTitle($reportName);
        $this->pdf->SetAuthor('JRC Dental Care');
        $this->pdf->SetCreator('JRC Dental Care Management System');
        
        // Set margins
        $this->pdf->SetMargins(15, 15, 15);
        $this->pdf->SetAutoPageBreak(true, 15);
        
        // Add a page
        $this->pdf->AddPage();
        
        $this->reportTitle = $reportName;
        
        // Set default header
        $this->pdf->SetFont('Arial', 'B', 14);
        $this->pdf->Cell(0, 10, 'JRC Dental Care Management System', 0, 1, 'C');
        $this->pdf->SetFont('Arial', '', 12);
        $this->pdf->Cell(0, 6, date('F d, Y H:i:s'), 0, 1, 'C');
        $this->pdf->Ln(5);
    }
    
    /**
     * Add header information to the report
     * 
     * @param string $title The title of the report
     * @param array $metadata Additional metadata
     */
    public function addHeader($title, $metadata = []) {
        // Add title
        $this->pdf->SetFont('Arial', 'B', 16);
        $this->pdf->Cell(0, 10, $title, 0, 1, 'C');
        $this->pdf->Ln(5);
        
        // Add metadata
        $this->pdf->SetFont('Arial', '', 10);
        foreach ($metadata as $key => $value) {
            $this->pdf->Cell(40, 6, $key . ':', 0, 0);
            $this->pdf->Cell(0, 6, $value, 0, 1);
        }
        
        $this->pdf->Ln(5);
    }
    
    /**
     * Add a section to the report
     * 
     * @param string $title The section title
     */
    public function addSection($title) {
        $this->pdf->SetFont('Arial', 'B', 14);
        $this->pdf->Cell(0, 10, $title, 0, 1);
        $this->pdf->Ln(2);
        $this->pdf->SetFont('Arial', '', 10);
    }
    
    /**
     * Add a subsection to the report
     * 
     * @param string $title The subsection title
     */
    public function addSubsection($title) {
        $this->pdf->SetFont('Arial', 'B', 12);
        $this->pdf->Cell(0, 8, $title, 0, 1);
        $this->pdf->Ln(2);
        $this->pdf->SetFont('Arial', '', 10);
    }
    
    /**
     * Add a paragraph to the report
     * 
     * @param string $text The paragraph text
     */
    public function addParagraph($text) {
        $this->pdf->MultiCell(0, 6, $text, 0, 'L');
        $this->pdf->Ln(3);
    }
    
    /**
     * Add a table to the report
     * 
     * @param array $headers The table headers
     * @param array $data The table data
     */
    public function addTable($headers, $data) {
        if (empty($data)) {
            return;
        }
        
        // Calculate column widths based on the number of columns
        $numColumns = count($headers);
        $pageWidth = 180; // A4 width minus margins
        $colWidth = $pageWidth / $numColumns;
        
        // Table headers
        $this->pdf->SetFillColor(230, 230, 230);
        $this->pdf->SetFont('Arial', 'B', 10);
        
        foreach ($headers as $index => $header) {
            $align = ($index == 0) ? 'L' : 'C';
            $this->pdf->Cell($colWidth, 8, $header, 1, 0, $align, true);
        }
        $this->pdf->Ln();
        
        // Table data
        $this->pdf->SetFont('Arial', '', 10);
        $this->pdf->SetFillColor(245, 245, 245);
        $fill = false;
        
        foreach ($data as $row) {
            foreach ($row as $index => $cell) {
                $align = ($index == 0) ? 'L' : 'C';
                $this->pdf->Cell($colWidth, 7, $cell, 1, 0, $align, $fill);
            }
            $this->pdf->Ln();
            $fill = !$fill;
        }
        
        $this->pdf->Ln(5);
    }
    
    /**
     * Add footer information to the report
     * 
     * @param string $text The footer text
     */
    public function addFooter($text) {
        $this->pdf->SetY(-20);
        $this->pdf->SetFont('Arial', 'I', 8);
        $this->pdf->Cell(0, 5, $text, 0, 1, 'C');
        $this->pdf->Cell(0, 5, 'Page ' . $this->pdf->PageNo(), 0, 1, 'C');
    }
    
    /**
     * Save the report to a file
     * 
     * @param string $filePath The path to save the file
     * @return bool Whether the save was successful
     */
    public function saveToFile($filePath) {
        try {
            $this->pdf->Output($filePath, 'F');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Output the PDF directly to the browser for download
     * 
     * @param string $filename The name of the file to download
     */
    public function download($filename) {
        $this->pdf->Output($filename, 'D');
        exit;
    }
}