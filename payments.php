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

// Get filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filters
$query = "SELECT p.*, a.appointment_date, u.first_name, u.last_name, s.service_name 
          FROM payment p 
          JOIN appointment a ON p.appointment_id = a.appointment_id 
          JOIN user u ON a.patient_id = u.user_id 
          JOIN service s ON a.service_id = s.service_id 
          WHERE 1=1";
$params = [];

if (!empty($status_filter)) {
    $query .= " AND p.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($payment_method)) {
    $query .= " AND p.payment_method = :payment_method";
    $params[':payment_method'] = $payment_method;
}

if (!empty($date_start)) {
    $query .= " AND DATE(p.created_at) >= :date_start";
    $params[':date_start'] = $date_start;
}

if (!empty($date_end)) {
    $query .= " AND DATE(p.created_at) <= :date_end";
    $params[':date_end'] = $date_end;
}

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR p.paymongo_payment_id LIKE :search OR p.paymongo_payment_intent_id LIKE :search)";
    $search_param = "%$search%";
    $params[':search'] = $search_param;
}

// Add sorting
$query .= " ORDER BY p.created_at DESC";

// Add limit for pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$records_per_page = 20;
$offset = ($page - 1) * $records_per_page;

// Prepare count query for pagination
$count_query = "SELECT COUNT(*) FROM payment p 
                JOIN appointment a ON p.appointment_id = a.appointment_id 
                JOIN user u ON a.patient_id = u.user_id 
                WHERE 1=1";
$count_params = [];

if (!empty($status_filter)) {
    $count_query .= " AND p.status = :status";
    $count_params[':status'] = $status_filter;
}

if (!empty($payment_method)) {
    $count_query .= " AND p.payment_method = :payment_method";
    $count_params[':payment_method'] = $payment_method;
}

if (!empty($date_start)) {
    $count_query .= " AND DATE(p.created_at) >= :date_start";
    $count_params[':date_start'] = $date_start;
}

if (!empty($date_end)) {
    $count_query .= " AND DATE(p.created_at) <= :date_end";
    $count_params[':date_end'] = $date_end;
}

if (!empty($search)) {
    $count_query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR p.paymongo_payment_id LIKE :search OR p.paymongo_payment_intent_id LIKE :search)";
    $count_params[':search'] = $search_param;
}

// Get total count
$count_stmt = $conn->prepare($count_query);
foreach ($count_params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// Add pagination to main query
$query .= " LIMIT :offset, :limit";
$params[':offset'] = $offset;
$params[':limit'] = $records_per_page;

// Execute main query
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    if ($key == ':offset' || $key == ':limit') {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $value);
    }
}
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment summary/stats
$summary_query = "SELECT 
                     COUNT(*) as total_payments,
                     SUM(CASE WHEN status = 'succeeded' THEN 1 ELSE 0 END) as succeeded_payments,
                     SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
                     SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_payments,
                     SUM(CASE WHEN status = 'succeeded' THEN amount ELSE 0 END) as total_revenue
                  FROM payment";
$summary_stmt = $conn->prepare($summary_query);
$summary_stmt->execute();
$payment_summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

// Get payment methods
$methods_query = "SELECT DISTINCT payment_method FROM payment";
$methods_stmt = $conn->prepare($methods_query);
$methods_stmt->execute();
$payment_methods = $methods_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - JRC Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Page Content -->
        <div id="page-content-wrapper">
            <!-- Navbar -->
            <?php include 'includes/navbar.php'; ?>
            
            <div class="container-fluid px-4 py-3">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="mb-0">Payment Management</h3>
                    <div>
                        <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#generateReportModal">
                            <i class="fas fa-chart-line me-2"></i> Generate Revenue Report
                        </button>
                        <a href="export_payments.php" class="btn btn-outline-primary">
                            <i class="fas fa-file-export me-2"></i> Export Payments
                        </a>
                    </div>
                </div>
                
                <!-- Payment Stats -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="icon-container bg-primary bg-opacity-10 rounded-circle p-3 me-3">
                                        <i class="fas fa-money-bill-wave text-primary"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Total Revenue</h6>
                                        <h3 class="mb-0">₱<?= number_format($payment_summary['total_revenue'] / 100, 2) ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="icon-container bg-success bg-opacity-10 rounded-circle p-3 me-3">
                                        <i class="fas fa-check-circle text-success"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Successful Payments</h6>
                                        <h3 class="mb-0"><?= $payment_summary['succeeded_payments'] ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="icon-container bg-warning bg-opacity-10 rounded-circle p-3 me-3">
                                        <i class="fas fa-clock text-warning"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Pending Payments</h6>
                                        <h3 class="mb-0"><?= $payment_summary['pending_payments'] ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="icon-container bg-danger bg-opacity-10 rounded-circle p-3 me-3">
                                        <i class="fas fa-times-circle text-danger"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Failed Payments</h6>
                                        <h3 class="mb-0"><?= $payment_summary['failed_payments'] ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-md-3">
                                <label for="status" class="form-label">Payment Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="succeeded" <?= $status_filter === 'succeeded' ? 'selected' : '' ?>>Succeeded</option>
                                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="failed" <?= $status_filter === 'failed' ? 'selected' : '' ?>>Failed</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="payment_method" class="form-label">Payment Method</label>
                                <select class="form-select" id="payment_method" name="payment_method">
                                    <option value="">All Methods</option>
                                    <?php foreach($payment_methods as $method): ?>
                                    <option value="<?= $method ?>" <?= $payment_method === $method ? 'selected' : '' ?>><?= ucfirst($method) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="daterange" class="form-label">Date Range</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="daterange" name="daterange" 
                                           value="<?= (!empty($date_start) && !empty($date_end)) ? date('m/d/Y', strtotime($date_start)) . ' - ' . date('m/d/Y', strtotime($date_end)) : '' ?>" 
                                           placeholder="All Dates">
                                    <input type="hidden" name="date_start" id="date_start" value="<?= $date_start ?>">
                                    <input type="hidden" name="date_end" id="date_end" value="<?= $date_end ?>">
                                    <button class="btn btn-outline-secondary" type="button" id="clearDate">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" placeholder="Patient name, ID..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-12 d-flex">
                                <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                                <a href="payments.php" class="btn btn-outline-secondary">Reset Filters</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Payments Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span>Showing <?= count($payments) ?> of <?= $total_records ?> payments</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Patient</th>
                                        <th>Service</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                        <th>Date & Time</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($payments) > 0): ?>
                                        <?php foreach($payments as $payment): ?>
                                        <tr>
                                            <td><?= $payment['payment_id'] ?></td>
                                            <td><?= htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) ?></td>
                                            <td><?= htmlspecialchars($payment['service_name']) ?></td>
                                            <td>₱<?= number_format($payment['amount'] / 100, 2) ?></td>
                                            <td><?= ucfirst($payment['payment_method']) ?></td>
                                            <td>
                                                <?php 
                                                $status_class = '';
                                                switch ($payment['status']) {
                                                    case 'succeeded':
                                                        $status_class = 'bg-success';
                                                        break;
                                                    case 'pending':
                                                        $status_class = 'bg-warning text-dark';
                                                        break;
                                                    case 'failed':
                                                        $status_class = 'bg-danger';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?= $status_class ?>">
                                                    <?= ucfirst($payment['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M d, Y h:i A', strtotime($payment['created_at'])) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-info view-payment-btn" 
                                                        data-id="<?= $payment['payment_id'] ?>"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#viewPaymentModal">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No payments found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if($total_pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center mt-4">
                                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page-1 ?><?= !empty($status_filter) ? '&status='.$status_filter : '' ?><?= !empty($payment_method) ? '&payment_method='.$payment_method : '' ?><?= !empty($date_start) ? '&date_start='.$date_start : '' ?><?= !empty($date_end) ? '&date_end='.$date_end : '' ?><?= !empty($search) ? '&search='.$search : '' ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                
                                <?php 
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $start_page + 4);
                                if ($end_page - $start_page < 4 && $start_page > 1) {
                                    $start_page = max(1, $end_page - 4);
                                }
                                
                                for($i = $start_page; $i <= $end_page; $i++): 
                                ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?><?= !empty($status_filter) ? '&status='.$status_filter : '' ?><?= !empty($payment_method) ? '&payment_method='.$payment_method : '' ?><?= !empty($date_start) ? '&date_start='.$date_start : '' ?><?= !empty($date_end) ? '&date_end='.$date_end : '' ?><?= !empty($search) ? '&search='.$search : '' ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page+1 ?><?= !empty($status_filter) ? '&status='.$status_filter : '' ?><?= !empty($payment_method) ? '&payment_method='.$payment_method : '' ?><?= !empty($date_start) ? '&date_start='.$date_start : '' ?><?= !empty($date_end) ? '&date_end='.$date_end : '' ?><?= !empty($search) ? '&search='.$search : '' ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View Payment Modal -->
    <div class="modal fade" id="viewPaymentModal" tabindex="-1" aria-labelledby="viewPaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewPaymentModalLabel">Payment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="paymentDetails">
                        <div class="text-center my-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading payment details...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Generate Revenue Report Modal -->
<div class="modal fade" id="generateReportModal" tabindex="-1" aria-labelledby="generateReportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="generateReportModalLabel">Generate Revenue Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="generate_revenue_report.php" method="post" target="_blank">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="report_type" class="form-label">Report Type</label>
                        <select class="form-select" id="report_type" name="report_type" required>
                            <option value="">Select Report Type</option>
                            <option value="daily">Daily Report</option>
                            <option value="weekly">Weekly Report</option>
                            <option value="monthly">Monthly Report</option>
                            <option value="yearly">Yearly Report</option>
                            <option value="custom">Custom Date Range</option>
                        </select>
                    </div>
                    
                    <div id="custom_date_range" class="mb-3 d-none">
                        <label for="report_daterange" class="form-label">Date Range</label>
                        <input type="text" class="form-control" id="report_daterange" name="report_daterange" placeholder="Select date range">
                        <input type="hidden" name="report_date_start" id="report_date_start">
                        <input type="hidden" name="report_date_end" id="report_date_end">
                    </div>
                    
                    <div id="month_select" class="mb-3 d-none">
                        <label for="report_month" class="form-label">Select Month</label>
                        <input type="month" class="form-control" id="report_month" name="report_month">
                    </div>
                    
                    <div id="year_select" class="mb-3 d-none">
                        <label for="report_year" class="form-label">Select Year</label>
                        <select class="form-select" id="report_year" name="report_year">
                            <?php 
                            $current_year = date('Y');
                            for($y = $current_year; $y >= $current_year - 5; $y--) {
                                echo "<option value=\"$y\">$y</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <input type="hidden" name="report_format" value="pdf">
                    
                    <div class="mb-3">
                        <label for="include_services" class="form-label">Include Details</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="include_services" name="include_services" checked>
                            <label class="form-check-label" for="include_services">
                                Include service breakdown
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="include_payment_methods" name="include_payment_methods" checked>
                            <label class="form-check-label" for="include_payment_methods">
                                Include payment method breakdown
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Generate PDF Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Report type change handler
            const reportTypeSelect = document.getElementById('report_type');
            const customDateRange = document.getElementById('custom_date_range');
            const monthSelect = document.getElementById('month_select');
            const yearSelect = document.getElementById('year_select');
            
            if (reportTypeSelect) {
                reportTypeSelect.addEventListener('change', function() {
                    // Hide all first
                    customDateRange.classList.add('d-none');
                    monthSelect.classList.add('d-none');
                    yearSelect.classList.add('d-none');
                    
                    // Show relevant fields based on selection
                    switch (this.value) {
                        case 'custom':
                            customDateRange.classList.remove('d-none');
                            break;
                        case 'monthly':
                            monthSelect.classList.remove('d-none');
                            break;
                        case 'yearly':
                            yearSelect.classList.remove('d-none');
                            break;
                    }
                });
            }
            
            // Initialize daterangepicker for report
            $('#report_daterange').daterangepicker({
                opens: 'left',
                autoUpdateInput: false,
                locale: {
                    cancelLabel: 'Clear'
                }
            });
            
            $('#report_daterange').on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('MM/DD/YYYY') + ' - ' + picker.endDate.format('MM/DD/YYYY'));
                $('#report_date_start').val(picker.startDate.format('YYYY-MM-DD'));
                $('#report_date_end').val(picker.endDate.format('YYYY-MM-DD'));
            });
            
            $('#report_daterange').on('cancel.daterangepicker', function(ev, picker) {
                $(this).val('');
                $('#report_date_start').val('');
                $('#report_date_end').val('');
            });
            
            // Date range picker for filters
            $('#daterange').daterangepicker({
                opens: 'left',
                autoUpdateInput: false,
                locale: {
                    cancelLabel: 'Clear'
                }
            });
            
            $('#daterange').on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('MM/DD/YYYY') + ' - ' + picker.endDate.format('MM/DD/YYYY'));
                $('#date_start').val(picker.startDate.format('YYYY-MM-DD'));
                $('#date_end').val(picker.endDate.format('YYYY-MM-DD'));
            });
            
            $('#daterange').on('cancel.daterangepicker', function(ev, picker) {
                $(this).val('');
                $('#date_start').val('');
                $('#date_end').val('');
            });
            
            // Clear date button
            $('#clearDate').on('click', function() {
                $('#daterange').val('');
                $('#date_start').val('');
                $('#date_end').val('');
            });
            
            // View payment button click
            document.querySelectorAll('.view-payment-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const paymentId = this.getAttribute('data-id');
                    const detailsContainer = document.getElementById('paymentDetails');
                    
                    // Reset container
                    detailsContainer.innerHTML = `
                        <div class="text-center my-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading payment details..</p>
                        </div>
                    `;
                    
                    // Fetch payment details
                    fetch('get_payment.php?id=' + paymentId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.error) {
                                detailsContainer.innerHTML = `
                                    <div class="alert alert-danger">
                                        ${data.error}
                                    </div>
                                `;
                            } else {
                                // Format payment status
                                let statusBadge = '';
                                switch (data.status) {
                                    case 'succeeded':
                                        statusBadge = '<span class="badge bg-success">Succeeded</span>';
                                        break;
                                    case 'pending':
                                        statusBadge = '<span class="badge bg-warning text-dark">Pending</span>';
                                        break;
                                    case 'failed':
                                        statusBadge = '<span class="badge bg-danger">Failed</span>';
                                        break;
                                }
                                
                                // Format created_at
                                const createdAt = new Date(data.created_at).toLocaleString();
                                
                                // Build payment details HTML
                                const html = `
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <h6 class="fw-bold">Payment Information</h6>
                                            <div class="mb-2">
                                                <strong>Payment ID:</strong> ${data.payment_id}
                                            </div>
                                            <div class="mb-2">
                                                <strong>Amount:</strong> ₱${(data.amount / 100).toFixed(2)}
                                            </div>
                                            <div class="mb-2">
                                                <strong>Status:</strong> ${statusBadge}
                                            </div>
                                            <div class="mb-2">
                                                <strong>Payment Method:</strong> ${data.payment_method.charAt(0).toUpperCase() + data.payment_method.slice(1)}
                                            </div>
                                            <div class="mb-2">
                                                <strong>Transaction Date:</strong> ${createdAt}
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="fw-bold">PayMongo Details</h6>
                                            <div class="mb-2">
                                                <strong>Payment Intent ID:</strong> ${data.paymongo_payment_intent_id}
                                            </div>
                                            <div class="mb-2">
                                                <strong>Payment ID:</strong> ${data.paymongo_payment_id || 'N/A'}
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <hr />
                                            <h6 class="fw-bold">Appointment Details</h6>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="mb-2">
                                                        <strong>Patient:</strong> ${data.patient_name}
                                                    </div>
                                                    <div class="mb-2">
                                                        <strong>Service:</strong> ${data.service_name}
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-2">
                                                        <strong>Appointment Date:</strong> ${new Date(data.appointment_date).toLocaleString()}
                                                    </div>
                                                    <div class="mb-2">
                                                        <strong>Dentist:</strong> ${data.dentist_name}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                `;
                                
                                detailsContainer.innerHTML = html;
                            }
                        })
                        .catch(error => {
                            detailsContainer.innerHTML = `
                                <div class="alert alert-danger">
                                    Failed to load payment details. Please try again.
                                </div>
                            `;
                            console.error('Error:', error);
                        });
                });
            });
        });
    </script>
</body>
</html>