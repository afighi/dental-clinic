<?php
session_start();
// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Process form inputs
$report_type = isset($_POST['report_type']) ? $_POST['report_type'] : '';
$report_format = isset($_POST['report_format']) ? $_POST['report_format'] : 'pdf';
$include_services = isset($_POST['include_services']) ? true : false;
$include_payment_methods = isset($_POST['include_payment_methods']) ? true : false;

// Set date range based on report type
$date_start = '';
$date_end = '';
$report_title = '';

switch ($report_type) {
    case 'daily':
        $date_start = date('Y-m-d');
        $date_end = date('Y-m-d');
        $report_title = 'Daily Revenue Report - ' . date('F d, Y');
        break;
        
    case 'weekly':
        $date_start = date('Y-m-d', strtotime('monday this week'));
        $date_end = date('Y-m-d', strtotime('sunday this week'));
        $report_title = 'Weekly Revenue Report - ' . date('F d', strtotime('monday this week')) . ' to ' . date('F d, Y', strtotime('sunday this week'));
        break;
        
    case 'monthly':
        if (isset($_POST['report_month']) && !empty($_POST['report_month'])) {
            $year_month = explode('-', $_POST['report_month']);
            $year = $year_month[0];
            $month = $year_month[1];
            $date_start = "$year-$month-01";
            $date_end = date('Y-m-t', strtotime($date_start));
            $report_title = 'Monthly Revenue Report - ' . date('F Y', strtotime($date_start));
        } else {
            $date_start = date('Y-m-01');
            $date_end = date('Y-m-t');
            $report_title = 'Monthly Revenue Report - ' . date('F Y');
        }
        break;
        
    case 'yearly':
        if (isset($_POST['report_year']) && !empty($_POST['report_year'])) {
            $year = $_POST['report_year'];
            $date_start = "$year-01-01";
            $date_end = "$year-12-31";
            $report_title = 'Yearly Revenue Report - ' . $year;
        } else {
            $date_start = date('Y-01-01');
            $date_end = date('Y-12-31');
            $report_title = 'Yearly Revenue Report - ' . date('Y');
        }
        break;
        
    case 'custom':
        if (isset($_POST['report_date_start']) && isset($_POST['report_date_end']) && 
            !empty($_POST['report_date_start']) && !empty($_POST['report_date_end'])) {
            $date_start = $_POST['report_date_start'];
            $date_end = $_POST['report_date_end'];
            $report_title = 'Revenue Report - ' . date('F d, Y', strtotime($date_start)) . ' to ' . date('F d, Y', strtotime($date_end));
        } else {
            // Default to current month if no dates provided
            $date_start = date('Y-m-01');
            $date_end = date('Y-m-t');
            $report_title = 'Revenue Report - ' . date('F Y');
        }
        break;
        
    default:
        // Default to current month
        $date_start = date('Y-m-01');
        $date_end = date('Y-m-t');
        $report_title = 'Revenue Report - ' . date('F Y');
}

// Get clinic information
$clinic_query = "SELECT clinic_name, address, phone, email FROM clinic_settings LIMIT 1";
$clinic_stmt = $conn->prepare($clinic_query);
$clinic_stmt->execute();
$clinic_info = $clinic_stmt->fetch(PDO::FETCH_ASSOC);

if (!$clinic_info) {
    $clinic_info = [
        'clinic_name' => 'JRC Dental Care',
        'address' => 'Clinic Address',
        'phone' => 'Clinic Phone',
        'email' => 'Clinic Email'
    ];
}

// Get overall payment summary
$summary_query = "SELECT 
                     COUNT(*) as total_payments,
                     SUM(CASE WHEN status = 'succeeded' THEN 1 ELSE 0 END) as succeeded_payments,
                     SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
                     SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_payments,
                     SUM(CASE WHEN status = 'succeeded' THEN amount ELSE 0 END) as total_revenue
                  FROM payment
                  WHERE DATE(created_at) BETWEEN :date_start AND :date_end";
                  
$summary_stmt = $conn->prepare($summary_query);
$summary_stmt->bindParam(':date_start', $date_start);
$summary_stmt->bindParam(':date_end', $date_end);
$summary_stmt->execute();
$payment_summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

// Get payment method breakdown if requested
$payment_methods_data = [];
if ($include_payment_methods) {
    $methods_query = "SELECT 
                        payment_method,
                        COUNT(*) as count,
                        SUM(CASE WHEN status = 'succeeded' THEN amount ELSE 0 END) as total_amount
                      FROM payment
                      WHERE DATE(created_at) BETWEEN :date_start AND :date_end
                      AND status = 'succeeded'
                      GROUP BY payment_method
                      ORDER BY total_amount DESC";
                      
    $methods_stmt = $conn->prepare($methods_query);
    $methods_stmt->bindParam(':date_start', $date_start);
    $methods_stmt->bindParam(':date_end', $date_end);
    $methods_stmt->execute();
    $payment_methods_data = $methods_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get service breakdown if requested
$services_data = [];
if ($include_services) {
    $services_query = "SELECT 
                        s.service_name,
                        COUNT(*) as count,
                        SUM(p.amount) as total_amount
                      FROM payment p
                      JOIN appointment a ON p.appointment_id = a.appointment_id
                      JOIN service s ON a.service_id = s.service_id
                      WHERE DATE(p.created_at) BETWEEN :date_start AND :date_end
                      AND p.status = 'succeeded'
                      GROUP BY s.service_id, s.service_name
                      ORDER BY total_amount DESC";
                      
    $services_stmt = $conn->prepare($services_query);
    $services_stmt->bindParam(':date_start', $date_start);
    $services_stmt->bindParam(':date_end', $date_end);
    $services_stmt->execute();
    $services_data = $services_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get detailed payment data for the report period
$details_query = "SELECT 
                    p.payment_id, 
                    p.amount, 
                    p.status, 
                    p.payment_method, 
                    p.created_at,
                    CONCAT(u.first_name, ' ', u.last_name) as patient_name,
                    s.service_name
                  FROM payment p
                  JOIN appointment a ON p.appointment_id = a.appointment_id
                  JOIN user u ON a.patient_id = u.user_id
                  JOIN service s ON a.service_id = s.service_id
                  WHERE DATE(p.created_at) BETWEEN :date_start AND :date_end
                  ORDER BY p.created_at DESC";
                  
$details_stmt = $conn->prepare($details_query);
$details_stmt->bindParam(':date_start', $date_start);
$details_stmt->bindParam(':date_end', $date_end);
$details_stmt->execute();
$payment_details = $details_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly revenue trend for the selected period
$monthly_trend_query = "";
$monthly_trend_data = [];

// For PDF output
if ($report_format === 'pdf' || true) { // Always use PDF
    // Include the TCPDF library
    require_once('vendor/tcpdf/tcpdf/tcpdf.php');
    
    // Create new PDF document
    $pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('JRC Dental Care');
    $pdf->SetAuthor('JRC Dental Care Admin');
    $pdf->SetTitle($report_title);
    $pdf->SetSubject('Revenue Report');
    
    // Remove header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    
    // Title
    $pdf->Cell(0, 10, $report_title, 0, 1, 'C');
    $pdf->Ln(5);
    
    // Clinic info
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 6, $clinic_info['clinic_name'], 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, $clinic_info['address'], 0, 1, 'C');
    $pdf->Cell(0, 5, "Phone: {$clinic_info['phone']} | Email: {$clinic_info['email']}", 0, 1, 'C');
    $pdf->Ln(10);
    
    // Report date range
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, "Report Period: " . date('F d, Y', strtotime($date_start)) . " to " . date('F d, Y', strtotime($date_end)), 0, 1, 'L');
    $pdf->Cell(0, 8, "Generated on: " . date('F d, Y h:i A'), 0, 1, 'L');
    $pdf->Ln(5);
    
    // Summary section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, "Revenue Summary", 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 11);
    
    // Summary table
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(95, 8, "Total Revenue:", 1, 0, 'L', true);
    $pdf->Cell(80, 8, "₱" . number_format($payment_summary['total_revenue'] / 100, 2), 1, 1, 'R');
    
    $pdf->Cell(95, 8, "Total Successful Payments:", 1, 0, 'L', true);
    $pdf->Cell(80, 8, $payment_summary['succeeded_payments'], 1, 1, 'R');
    
    $pdf->Cell(95, 8, "Pending Payments:", 1, 0, 'L', true);
    $pdf->Cell(80, 8, $payment_summary['pending_payments'], 1, 1, 'R');
    
    $pdf->Cell(95, 8, "Failed Payments:", 1, 0, 'L', true);
    $pdf->Cell(80, 8, $payment_summary['failed_payments'], 1, 1, 'R');
    
    $pdf->Ln(10);
    
    // Payment Method Breakdown
    if ($include_payment_methods && count($payment_methods_data) > 0) {
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, "Payment Method Breakdown", 0, 1, 'L');
        $pdf->SetFont('helvetica', 'B', 11);
        
        // Headers
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(70, 8, "Payment Method", 1, 0, 'L', true);
        $pdf->Cell(40, 8, "Transactions", 1, 0, 'C', true);
        $pdf->Cell(65, 8, "Amount", 1, 1, 'R', true);
        
        $pdf->SetFont('helvetica', '', 11);
        
        $total_methods_amount = 0;
        foreach ($payment_methods_data as $method) {
            $pdf->Cell(70, 8, ucfirst($method['payment_method']), 1, 0, 'L');
            $pdf->Cell(40, 8, $method['count'], 1, 0, 'C');
            $pdf->Cell(65, 8, "₱" . number_format($method['total_amount'] / 100, 2), 1, 1, 'R');
            $total_methods_amount += $method['total_amount'];
        }
        
        // Total row
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(70, 8, "Total", 1, 0, 'L', true);
        $pdf->Cell(40, 8, array_sum(array_column($payment_methods_data, 'count')), 1, 0, 'C', true);
        $pdf->Cell(65, 8, "₱" . number_format($total_methods_amount / 100, 2), 1, 1, 'R', true);
        
        $pdf->Ln(10);
    }
    
    // Service Breakdown
    if ($include_services && count($services_data) > 0) {
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, "Service Breakdown", 0, 1, 'L');
        $pdf->SetFont('helvetica', 'B', 11);
        
        // Headers
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(95, 8, "Service", 1, 0, 'L', true);
        $pdf->Cell(30, 8, "Count", 1, 0, 'C', true);
        $pdf->Cell(50, 8, "Revenue", 1, 1, 'R', true);
        
        $pdf->SetFont('helvetica', '', 11);
        
        $total_services_amount = 0;
        foreach ($services_data as $service) {
            $pdf->Cell(95, 8, $service['service_name'], 1, 0, 'L');
            $pdf->Cell(30, 8, $service['count'], 1, 0, 'C');
            $pdf->Cell(50, 8, "₱" . number_format($service['total_amount'] / 100, 2), 1, 1, 'R');
            $total_services_amount += $service['total_amount'];
        }
        
        // Total row
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(95, 8, "Total", 1, 0, 'L', true);
        $pdf->Cell(30, 8, array_sum(array_column($services_data, 'count')), 1, 0, 'C', true);
        $pdf->Cell(50, 8, "₱" . number_format($total_services_amount / 100, 2), 1, 1, 'R', true);
        
        $pdf->Ln(10);
    }
    
    // Payment Details Table
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, "Payment Details", 0, 1, 'L');
    $pdf->SetFont('helvetica', 'B', 10);
    
    // Headers
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell(15, 8, "ID", 1, 0, 'C', true);
    $pdf->Cell(55, 8, "Patient", 1, 0, 'L', true);
    $pdf->Cell(45, 8, "Service", 1, 0, 'L', true);
    $pdf->Cell(25, 8, "Amount", 1, 0, 'R', true);
    $pdf->Cell(25, 8, "Status", 1, 0, 'C', true);
    $pdf->Cell(30, 8, "Date", 1, 1, 'C', true);
    
    $pdf->SetFont('helvetica', '', 9);
    
    foreach ($payment_details as $payment) {
        // Handle status formatting
        $status = ucfirst($payment['status']);
        
        // Format date
        $payment_date = date('M d, Y', strtotime($payment['created_at']));
        
        $pdf->Cell(15, 7, $payment['payment_id'], 1, 0, 'C');
        $pdf->Cell(55, 7, $payment['patient_name'], 1, 0, 'L');
        $pdf->Cell(45, 7, $payment['service_name'], 1, 0, 'L');
        $pdf->Cell(25, 7, "₱" . number_format($payment['amount'] / 100, 2), 1, 0, 'R');
        $pdf->Cell(25, 7, $status, 1, 0, 'C');
        $pdf->Cell(30, 7, $payment_date, 1, 1, 'C');
    }
    
    // Output the PDF
    $pdf->Output($report_title . '.pdf', 'I');
} else {
    // Invalid format requested
    echo "<div class='alert alert-danger'>Invalid report format requested.</div>";
}
?>