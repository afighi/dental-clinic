<?php
session_start();
// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Get user information
$stmt = $conn->prepare("SELECT * FROM user WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get current filter if any
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$valid_statuses = ['all', 'succeeded', 'pending', 'failed'];
if (!in_array($status_filter, $valid_statuses)) {
    $status_filter = 'all';
}

// Get all payments for the patient with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build the query based on filter
$query = "SELECT p.*, a.appointment_date, a.service_id, s.service_name,
          d.first_name as dentist_first_name, d.last_name as dentist_last_name
          FROM payment p
          JOIN appointment a ON p.appointment_id = a.appointment_id
          JOIN service s ON a.service_id = s.service_id
          JOIN user d ON a.dentist_id = d.user_id
          WHERE a.patient_id = :patient_id";

if ($status_filter != 'all') {
    $query .= " AND p.status = :status";
}

$query .= " ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset";

$stmt = $conn->prepare($query);
$stmt->bindParam(':patient_id', $_SESSION['user_id'], PDO::PARAM_INT);
if ($status_filter != 'all') {
    $stmt->bindParam(':status', $status_filter, PDO::PARAM_STR);
}
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total payments count for pagination
$count_query = "SELECT COUNT(*) FROM payment p 
               JOIN appointment a ON p.appointment_id = a.appointment_id 
               WHERE a.patient_id = :patient_id";
if ($status_filter != 'all') {
    $count_query .= " AND p.status = :status";
}

$stmt = $conn->prepare($count_query);
$stmt->bindParam(':patient_id', $_SESSION['user_id'], PDO::PARAM_INT);
if ($status_filter != 'all') {
    $stmt->bindParam(':status', $status_filter, PDO::PARAM_STR);
}
$stmt->execute();
$total_payments = $stmt->fetchColumn();
$total_pages = ceil($total_payments / $limit);

// Get unpaid appointments (ones without payments)
$unpaid_query = "SELECT a.*, s.service_name, s.price,
                d.first_name as dentist_first_name, d.last_name as dentist_last_name
                FROM appointment a
                JOIN service s ON a.service_id = s.service_id
                JOIN user d ON a.dentist_id = d.user_id
                WHERE a.patient_id = :patient_id
                AND a.status != 'canceled'
                AND NOT EXISTS (
                    SELECT 1 FROM payment p 
                    WHERE p.appointment_id = a.appointment_id 
                    AND p.status = 'succeeded'
                )
                ORDER BY a.appointment_date DESC";

$stmt = $conn->prepare($unpaid_query);
$stmt->bindParam(':patient_id', $_SESSION['user_id'], PDO::PARAM_INT);
$stmt->execute();
$unpaid_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug info - can be removed after testing
$debug = false; // Set to true to see debug info
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Payments - JRC Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                    <h3>My Payments</h3>
                </div>
                
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $_SESSION['success'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $_SESSION['error'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <!-- Debug info - remove in production -->
                <?php if($debug): ?>
                <div class="alert alert-info">
                    <p><strong>Debug Info:</strong></p>
                    <p>Status Filter: <?= $status_filter ?></p>
                    <p>Query: <?= $query ?></p>
                    <p>Total Payments: <?= $total_payments ?></p>
                    <p>Payments Found: <?= count($payments) ?></p>
                </div>
                <?php endif; ?>
                
                <!-- Unpaid Appointments Section -->
                <?php if (count($unpaid_appointments) > 0): ?>
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Appointments Requiring Payment</h5>
                            <span class="badge bg-danger"><?= count($unpaid_appointments) ?> Unpaid</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Service</th>
                                            <th>Dentist</th>
                                            <th>Date & Time</th>
                                            <th>Status</th>
                                            <th>Amount</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($unpaid_appointments as $appointment): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($appointment['service_name']) ?></td>
                                                <td>Dr. <?= htmlspecialchars($appointment['dentist_first_name'] . ' ' . $appointment['dentist_last_name']) ?></td>
                                                <td><?= date('M d, Y h:i A', strtotime($appointment['appointment_date'])) ?></td>
                                                <td>
                                                    <?php 
                                                    $status_class = '';
                                                    switch ($appointment['status']) {
                                                        case 'pending':
                                                            $status_class = 'bg-warning text-dark';
                                                            break;
                                                        case 'confirmed':
                                                            $status_class = 'bg-info';
                                                            break;
                                                        case 'completed':
                                                            $status_class = 'bg-success';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?= $status_class ?>">
                                                        <?= ucfirst(htmlspecialchars($appointment['status'])) ?>
                                                    </span>
                                                </td>
                                                <td>₱<?= number_format($appointment['price'] / 100, 2) ?></td>
                                                <td>
                                                    <a href="make-payment.php?id=<?= $appointment['appointment_id'] ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-credit-card me-2"></i>Pay Now
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Filter Buttons -->
                <div class="mb-4">
                    <div class="btn-group" role="group" aria-label="Filter payments">
                        <a href="my-payments.php?status=all" class="btn <?= $status_filter == 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
                        <a href="my-payments.php?status=succeeded" class="btn <?= $status_filter == 'succeeded' ? 'btn-primary' : 'btn-outline-primary' ?>">Successful</a>
                        <a href="my-payments.php?status=pending" class="btn <?= $status_filter == 'pending' ? 'btn-primary' : 'btn-outline-primary' ?>">Pending</a>
                        <a href="my-payments.php?status=failed" class="btn <?= $status_filter == 'failed' ? 'btn-primary' : 'btn-outline-primary' ?>">Failed</a>
                    </div>
                </div>
                
                <!-- Payment History Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">Payment History (<?= $total_payments ?> <?= $status_filter != 'all' ? ucfirst($status_filter) . ' ' : '' ?>Payments)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($payments) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Receipt #</th>
                                            <th>Service</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td><?= $payment['payment_id'] . '-' . date('Ymd', strtotime($payment['created_at'])) ?></td>
                                                <td><?= htmlspecialchars($payment['service_name']) ?></td>
                                                <td><?= date('M d, Y', strtotime($payment['created_at'])) ?></td>
                                                <td>₱<?= number_format($payment['amount'] / 100, 2) ?></td>
                                                <td><?= ucfirst(htmlspecialchars($payment['payment_method'])) ?></td>
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
                                                        <?= ucfirst(htmlspecialchars($payment['status'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="payment-success.php?id=<?= $payment['payment_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-receipt"></i>
                                                        </a>
                                                        <?php if ($payment['status'] == 'failed'): ?>
                                                            <a href="make-payment.php?id=<?= $payment['appointment_id'] ?>" class="btn btn-sm btn-outline-success">
                                                                <i class="fas fa-redo"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center mt-4">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="my-payments.php?status=<?= $status_filter ?>&page=<?= $page - 1 ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                                <a class="page-link" href="my-payments.php?status=<?= $status_filter ?>&page=<?= $i ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="my-payments.php?status=<?= $status_filter ?>&page=<?= $page + 1 ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="text-center my-5">
                                <img src="images/card.png" alt="No Payments" class="img-fluid mb-3" style="max-width: 150px;">
                                <h5>No payment history found</h5>
                                <?php if ($status_filter != 'all'): ?>
                                    <p class="text-muted">No <?= $status_filter ?> payments found. Try another filter.</p>
                                    <a href="my-payments.php?status=all" class="btn btn-outline-primary mt-2">View All Payments</a>
                                <?php else: ?>
                                    <p class="text-muted">You haven't made any payments yet.</p>
                                    <?php if (count($unpaid_appointments) > 0): ?>
                                        <p>You have <?= count($unpaid_appointments) ?> pending appointments that require payment.</p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle functionality
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.body.classList.toggle('sb-sidenav-toggled');
                    localStorage.setItem('sb|sidebar-toggle', document.body.classList.contains('sb-sidenav-toggled'));
                });
            }
        });
    </script>
</body>
</html>