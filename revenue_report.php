<?php
/**
 * Direct Revenue Report Generator
 * Standalone script to generate revenue reports
 */
session_start();
// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
require_once 'fpdf/fpdf.php';

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// Check if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_name = $_POST['report_name'] ?? 'Revenue Report';
    $date_start = $_POST['date_start'] ?? date('Y-m-d', strtotime('-30 days'));
    $date_end = $_POST['date_end'] ?? date('Y-m-d');
    
    // Create PDF
    class RevenueReport extends FPDF {
        function Header() {
            $this->SetFont('Arial', 'B', 15);
            $this->Cell(0, 10, 'JRC Dental Care Management System', 0, 1, 'C');
            $this->SetFont('Arial', '', 12);
            $this->Cell(0, 6, 'Revenue Report', 0, 1, 'C');
            $this->Ln(10);
        }
        
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Page '.$this->PageNo(), 0, 0, 'C');
        }
        
        function ChapterTitle($title) {
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 10, $title, 0, 1);
            $this->Ln(5);
        }
        
        function ChapterBody($txt) {
            $this->SetFont('Arial', '', 10);
            $this->MultiCell(0, 5, $txt);
            $this->Ln();
        }
    }
    
    // Initialize PDF
    $pdf = new RevenueReport();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->ChapterTitle($report_name);
    $pdf->ChapterBody('Date Range: ' . date('F d, Y', strtotime($date_start)) . ' to ' . date('F d, Y', strtotime($date_end)));
    
    // Get revenue data
    try {
        // Check if payment table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'payment'");
        if ($tableCheck->rowCount() > 0) {
            // Find which date column to use
            $columnsResult = $conn->query("DESCRIBE payment");
            $columns = $columnsResult->fetchAll(PDO::FETCH_COLUMN);
            
            $dateColumn = null;
            if (in_array('created_at', $columns)) $dateColumn = 'created_at';
            else if (in_array('payment_date', $columns)) $dateColumn = 'payment_date';
            
            if ($dateColumn) {
                // Get total revenue for the period
                $totalQuery = "SELECT SUM(amount) as total FROM payment WHERE $dateColumn BETWEEN :start AND :end";
                $totalStmt = $conn->prepare($totalQuery);
                $totalStmt->bindParam(':start', $date_start);
                $totalStmt->bindParam(':end', $date_end);
                $totalStmt->execute();
                $totalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
                $totalRevenue = $totalResult['total'] ?? 0;
                
                // Display total revenue
                $pdf->ChapterTitle('Total Revenue');
                $pdf->SetFont('Arial', 'B', 12);
                $pdf->Cell(0, 10, 'Total: ₱' . number_format($totalRevenue / 100, 2), 0, 1);
                $pdf->Ln(5);
                
                // Get monthly breakdown
                $monthlyQuery = "SELECT DATE_FORMAT($dateColumn, '%Y-%m') as month, SUM(amount) as total 
                              FROM payment 
                              WHERE $dateColumn BETWEEN :start AND :end
                              GROUP BY month 
                              ORDER BY month";
                $monthlyStmt = $conn->prepare($monthlyQuery);
                $monthlyStmt->bindParam(':start', $date_start);
                $monthlyStmt->bindParam(':end', $date_end);
                $monthlyStmt->execute();
                $monthlyData = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($monthlyData)) {
                    $pdf->ChapterTitle('Monthly Breakdown');
                    
                    // Table header
                    $pdf->SetFont('Arial', 'B', 10);
                    $pdf->Cell(90, 7, 'Month', 1);
                    $pdf->Cell(90, 7, 'Revenue', 1);
                    $pdf->Ln();
                    
                    // Table data
                    $pdf->SetFont('Arial', '', 10);
                    foreach ($monthlyData as $row) {
                        $pdf->Cell(90, 6, $row['month'], 1);
                        $pdf->Cell(90, 6, '₱' . number_format($row['total'] / 100, 2), 1);
                        $pdf->Ln();
                    }
                    $pdf->Ln(5);
                } else {
                    $pdf->ChapterBody('No monthly data available for selected period.');
                }
                
                // Get payment method breakdown
                if (in_array('payment_method', $columns)) {
                    $methodQuery = "SELECT payment_method, COUNT(*) as count, SUM(amount) as total 
                                 FROM payment 
                                 WHERE $dateColumn BETWEEN :start AND :end
                                 GROUP BY payment_method 
                                 ORDER BY total DESC";
                    $methodStmt = $conn->prepare($methodQuery);
                    $methodStmt->bindParam(':start', $date_start);
                    $methodStmt->bindParam(':end', $date_end);
                    $methodStmt->execute();
                    $methodData = $methodStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($methodData)) {
                        $pdf->ChapterTitle('Payment Methods');
                        
                        // Table header
                        $pdf->SetFont('Arial', 'B', 10);
                        $pdf->Cell(60, 7, 'Method', 1);
                        $pdf->Cell(30, 7, 'Count', 1);
                        $pdf->Cell(60, 7, 'Revenue', 1);
                        $pdf->Cell(30, 7, 'Percentage', 1);
                        $pdf->Ln();
                        
                        // Table data
                        $pdf->SetFont('Arial', '', 10);
                        foreach ($methodData as $row) {
                            $percentage = $totalRevenue > 0 ? ($row['total'] / $totalRevenue) * 100 : 0;
                            $pdf->Cell(60, 6, $row['payment_method'], 1);
                            $pdf->Cell(30, 6, $row['count'], 1);
                            $pdf->Cell(60, 6, '₱' . number_format($row['total'] / 100, 2), 1);
                            $pdf->Cell(30, 6, number_format($percentage, 1) . '%', 1);
                            $pdf->Ln();
                        }
                    }
                }
            } else {
                $pdf->ChapterBody('No suitable date column found in payment table.');
                
                // Show something anyway
                $totalQuery = "SELECT SUM(amount) as total FROM payment";
                $totalResult = $conn->query($totalQuery);
                $totalAll = $totalResult->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                $pdf->ChapterTitle('Total Revenue (All Time)');
                $pdf->SetFont('Arial', 'B', 12);
                $pdf->Cell(0, 10, 'Total: ₱' . number_format($totalAll / 100, 2), 0, 1);
            }
        } else {
            $pdf->ChapterBody('Payment table not found in database.');
            
            // Check alternative tables
            $altTables = ['payments', 'transaction', 'transactions', 'billing'];
            $altFound = false;
            
            foreach ($altTables as $table) {
                $altCheck = $conn->query("SHOW TABLES LIKE '$table'");
                if ($altCheck->rowCount() > 0) {
                    $pdf->ChapterBody("Alternative payment table found: $table");
                    $altFound = true;
                    
                    // Try to get data from it
                    $altQuery = "SELECT SUM(amount) as total FROM $table";
                    $altResult = $conn->query($altQuery);
                    if ($altResult) {
                        $altTotal = $altResult->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                        $pdf->ChapterBody("Total in $table: ₱" . number_format($altTotal / 100, 2));
                    }
                }
            }
            
            if (!$altFound) {
                $pdf->ChapterBody('No payment tables found. Please check database configuration.');
            }
        }
    } catch (PDOException $e) {
        $pdf->ChapterTitle('Database Error');
        $pdf->ChapterBody('Error: ' . $e->getMessage());
    }
    
    // Generate file
    $filename = strtolower(str_replace(' ', '_', $report_name)) . '_' . date('Ymd_His') . '.pdf';
    $file_path = 'reports/' . $filename;
    
    // Make sure reports directory exists
    if (!file_exists('reports')) {
        mkdir('reports', 0755, true);
    }
    
    // Save the PDF
    $pdf->Output($file_path, 'F');
    
    // Record the report in the database
    try {
        $stmt = $conn->prepare("INSERT INTO report (report_name, report_type, generated_by, file_path, parameters) 
                             VALUES (:report_name, 'financial', :generated_by, :file_path, :parameters)");
        
        $parameters = json_encode([
            'date_start' => $date_start,
            'date_end' => $date_end,
            'include_revenue' => 1
        ]);
        
        $stmt->bindParam(':report_name', $report_name);
        $stmt->bindParam(':generated_by', $_SESSION['user_id']);
        $stmt->bindParam(':file_path', $file_path);
        $stmt->bindParam(':parameters', $parameters);
        $stmt->execute();
        
        // Log to audit trail
        $audit_stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details) VALUES (:user_id, 'GENERATE', 'report', :details)");
        $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $details = "Generated revenue report: " . $report_name;
        $audit_stmt->bindParam(':details', $details);
        $audit_stmt->execute();
        
        // Redirect to the PDF
        header("Location: $file_path");
        exit();
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Revenue Report - JRC Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Montserrat', sans-serif;
        }
        .form-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .form-label {
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <div class="header">
                <h2>Generate Revenue Report</h2>
                <p class="text-muted">Create a financial revenue report for JRC Dental Care</p>
            </div>
            
            <form method="post">
                <div class="mb-3">
                    <label for="report_name" class="form-label">Report Name</label>
                    <input type="text" class="form-control" id="report_name" name="report_name" value="Financial Revenue Report" required>
                </div>
                
                <div class="mb-3">
                    <label for="date_range" class="form-label">Date Range</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="date_range" name="date_range" required>
                        <input type="hidden" id="date_start" name="date_start">
                        <input type="hidden" id="date_end" name="date_end">
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Generate PDF Report</button>
                    <a href="reports.php" class="btn btn-outline-secondary">Back to Reports</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize date range picker
            $('#date_range').daterangepicker({
                opens: 'center',
                startDate: moment().subtract(30, 'days'),
                endDate: moment(),
                ranges: {
                   'Today': [moment(), moment()],
                   'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                   'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                   'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                   'This Month': [moment().startOf('month'), moment().endOf('month')],
                   'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                }
            }, function(start, end, label) {
                $('#date_start').val(start.format('YYYY-MM-DD'));
                $('#date_end').val(end.format('YYYY-MM-DD'));
            });
            
            // Set initial values for date range
            $('#date_start').val(moment().subtract(30, 'days').format('YYYY-MM-DD'));
            $('#date_end').val(moment().format('YYYY-MM-DD'));
        });
    </script>