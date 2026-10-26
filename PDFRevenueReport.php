<?php
/**
 * PDFRevenueReport - Direct PDF generation for revenue reports
 * This standalone file can be used to generate and download revenue reports
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date_start = $_POST['date_start'] ?? date('Y-m-d', strtotime('-30 days'));
    $date_end = $_POST['date_end'] ?? date('Y-m-d');
    $report_name = $_POST['report_name'] ?? 'Revenue_Report';
    
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Include FPDF
    require('fpdf/fpdf.php');
    
    // Create PDF document
    class RevenueReport extends FPDF {
        function Header() {
            // Logo - if you have one
            // $this->Image('logo.png', 10, 6, 30);
            
            // Header
            $this->SetFont('Arial', 'B', 15);
            $this->Cell(0, 10, 'JRC Dental Care Management System', 0, 1, 'C');
            $this->SetFont('Arial', '', 12);
            $this->Cell(0, 6, 'Revenue Report', 0, 1, 'C');
            $this->SetFont('Arial', 'I', 10);
            $this->Cell(0, 6, 'Generated on ' . date('F d, Y H:i:s'), 0, 1, 'C');
            $this->Ln(10);
        }
        
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
        
        function ReportTitle($title) {
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 10, $title, 0, 1);
            $this->Ln(5);
        }
        
        function DateRange($start, $end) {
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 10, 'Date Range: ' . date('M d, Y', strtotime($start)) . ' to ' . date('M d, Y', strtotime($end)), 0, 1);
            $this->Ln(5);
        }
        
        function SectionTitle($title) {
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 10, $title, 0, 1);
        }
        
        function TableHeader($header) {
            $this->SetFont('Arial', 'B', 10);
            $this->SetFillColor(200, 220, 255);
            
            // Column widths
            $w = array(60, 40, 40, 40);
            
            // Header
            for($i=0; $i < count($header); $i++) {
                $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
            }
            $this->Ln();
        }
        
        function TableRow($data, $w, $fill = false) {
            $this->SetFont('Arial', '', 10);
            $this->SetFillColor(235, 235, 235);
            
            // Data
            for($i=0; $i < count($data); $i++) {
                $this->Cell($w[$i], 6, $data[$i], 1, 0, 'L', $fill);
            }
            $this->Ln();
        }
    }
    
    // Initialize the PDF
    $pdf = new RevenueReport();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->ReportTitle('Financial Revenue Report');
    $pdf->DateRange($date_start, $date_end);
    
    // Find payment table and column names (for flexibility)
    $paymentTableData = findPaymentTable($conn);
    
    if ($paymentTableData) {
        $tableName = $paymentTableData['table'];
        $dateColumn = $paymentTableData['date_column'];
        $amountColumn = $paymentTableData['amount_column'];
        
        $pdf->SectionTitle('Revenue Summary');
        
        // Get total revenue
        $totalRevenue = getTotalRevenue($conn, $tableName, $amountColumn, $dateColumn, $date_start, $date_end);
        
        // Add total revenue to PDF
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Total Revenue: ₱' . number_format($totalRevenue / 100, 2), 0, 1);
        $pdf->Ln(5);
        
        // Get monthly breakdown
        $monthlyData = getMonthlyRevenue($conn, $tableName, $amountColumn, $dateColumn, $date_start, $date_end);
        
        if (!empty($monthlyData)) {
            $pdf->SectionTitle('Monthly Breakdown');
            
            // Table header
            $header = array('Month', 'Transaction Count', 'Total Revenue', 'Average Revenue');
            $pdf->TableHeader($header);
            
            // Table column widths
            $w = array(60, 40, 40, 40);
            
            // Data rows
            $fill = false;
            foreach ($monthlyData as $month) {
                $avgAmount = $month['count'] > 0 ? $month['total'] / $month['count'] : 0;
                
                $pdf->TableRow(array(
                    $month['month'],
                    $month['count'],
                    '₱' . number_format($month['total'] / 100, 2),
                    '₱' . number_format($avgAmount / 100, 2)
                ), $w, $fill);
                
                $fill = !$fill;
            }
        } else {
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(0, 10, 'No monthly data available for the selected period.', 0, 1);
        }
        
        // Get payment method breakdown if available
        $methodColumn = $paymentTableData['method_column'] ?? null;
        
        if ($methodColumn) {
            $paymentMethods = getPaymentMethodBreakdown($conn, $tableName, $amountColumn, $methodColumn, $dateColumn, $date_start, $date_end);
            
            if (!empty($paymentMethods)) {
                $pdf->AddPage();
                $pdf->SectionTitle('Payment Method Breakdown');
                
                // Table header
                $methodHeader = array('Payment Method', 'Count', 'Total Amount', '% of Revenue');
                $pdf->TableHeader($methodHeader);
                
                // Data rows
                $fill = false;
                foreach ($paymentMethods as $method) {
                    $percentage = $totalRevenue > 0 ? ($method['total'] / $totalRevenue) * 100 : 0;
                    
                    $pdf->TableRow(array(
                        $method['payment_method'],
                        $method['count'],
                        '₱' . number_format($method['total'] / 100, 2),
                        number_format($percentage, 1) . '%'
                    ), $w, $fill);
                    
                    $fill = !$fill;
                }
            }
        }
    } else {
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, 'No payment data found in the database.', 0, 1);
        $pdf->Cell(0, 10, 'Please verify your database configuration or add payment records.', 0, 1);
    }
    
    // Output the PDF
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

/**
 * Find payment table and column names
 */
function findPaymentTable($conn) {
    $possibleTables = ['payment', 'payments', 'transaction', 'transactions', 'billing', 'invoice', 'invoices'];
    
    foreach ($possibleTables as $table) {
        try {
            $tableStmt = $conn->prepare("SHOW TABLES LIKE :table");
            $tableStmt->bindParam(':table', $table);
            $tableStmt->execute();
            
            if ($tableStmt->rowCount() > 0) {
                // Get columns
                $columnStmt = $conn->prepare("DESCRIBE " . $table);
                $columnStmt->execute();
                $columns = $columnStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $dateColumn = null;
                $amountColumn = null;
                $methodColumn = null;
                
                // Find date column
                $possibleDateColumns = ['created_at', 'payment_date', 'date', 'transaction_date', 'invoice_date', 'billing_date'];
                foreach ($possibleDateColumns as $dateCol) {
                    foreach ($columns as $column) {
                        if ($column['Field'] == $dateCol) {
                            $dateColumn = $dateCol;
                            break 2;
                        }
                    }
                }
                
                // Find amount column
                $possibleAmountColumns = ['amount', 'payment_amount', 'total_amount', 'total', 'price', 'cost'];
                foreach ($possibleAmountColumns as $amountCol) {
                    foreach ($columns as $column) {
                        if ($column['Field'] == $amountCol) {
                            $amountColumn = $amountCol;
                            break 2;
                        }
                    }
                }
                
                // Find payment method column (optional)
                $possibleMethodColumns = ['payment_method', 'method', 'type', 'payment_type'];
                foreach ($possibleMethodColumns as $methodCol) {
                    foreach ($columns as $column) {
                        if ($column['Field'] == $methodCol) {
                            $methodColumn = $methodCol;
                            break 2;
                        }
                    }
                }
                
                // If we found date and amount columns, return the table info
                if ($dateColumn && $amountColumn) {
                    return [
                        'table' => $table,
                        'date_column' => $dateColumn,
                        'amount_column' => $amountColumn,
                        'method_column' => $methodColumn
                    ];
                }
            }
        } catch (PDOException $e) {
            // Skip to next table
            continue;
        }
    }
    
    return null;
}

/**
 * Get total revenue
 */
function getTotalRevenue($conn, $tableName, $amountColumn, $dateColumn, $dateStart, $dateEnd) {
    try {
        $stmt = $conn->prepare("SELECT SUM({$amountColumn}) as total 
                             FROM {$tableName} 
                             WHERE {$dateColumn} BETWEEN :date_start AND :date_end");
        $stmt->bindParam(':date_start', $dateStart);
        $stmt->bindParam(':date_end', $dateEnd);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['total'] ?? 0;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Get monthly revenue breakdown
 */
function getMonthlyRevenue($conn, $tableName, $amountColumn, $dateColumn, $dateStart, $dateEnd) {
    try {
        $stmt = $conn->prepare("SELECT DATE_FORMAT({$dateColumn}, '%Y-%m') as month, 
                             COUNT(*) as count, 
                             SUM({$amountColumn}) as total 
                             FROM {$tableName} 
                             WHERE {$dateColumn} BETWEEN :date_start AND :date_end 
                             GROUP BY month 
                             ORDER BY month");
        $stmt->bindParam(':date_start', $dateStart);
        $stmt->bindParam(':date_end', $dateEnd);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get payment method breakdown
 */
function getPaymentMethodBreakdown($conn, $tableName, $amountColumn, $methodColumn, $dateColumn, $dateStart, $dateEnd) {
    try {
        $stmt = $conn->prepare("SELECT {$methodColumn} as payment_method, 
                             COUNT(*) as count, 
                             SUM({$amountColumn}) as total 
                             FROM {$tableName} 
                             WHERE {$dateColumn} BETWEEN :date_start AND :date_end 
                             GROUP BY {$methodColumn} 
                             ORDER BY total DESC");
        $stmt->bindParam(':date_start', $dateStart);
        $stmt->bindParam(':date_end', $dateEnd);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
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
</body>
</html>