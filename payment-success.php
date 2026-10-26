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

// Debug incoming parameters
error_log('GET parameters: ' . print_r($_GET, true));

// Check for different parameter formats
$payment_id = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $payment_id = $_GET['id'];
} elseif (isset($_GET['payment_id']) && is_numeric($_GET['payment_id'])) {
    $payment_id = $_GET['payment_id'];
} elseif (isset($_GET['payment_intent_id'])) {
    // If Paymongo sends a payment_intent_id, we need to find the matching payment
    $payment_intent_id = $_GET['payment_intent_id'];
    
    // Query to find payment by payment_intent_id
    $stmt = $conn->prepare("SELECT payment_id FROM payment WHERE payment_intent_id = :payment_intent_id");
    $stmt->bindParam(':payment_intent_id', $payment_intent_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $payment_id = $result['payment_id'];
    }
}

if (!$payment_id) {
    $_SESSION['error'] = "Invalid payment information.";
    header("Location: my-payments.php");
    exit();
}

$patient_id = $_SESSION['user_id'];

// Get payment details
$stmt = $conn->prepare("SELECT p.*, a.patient_id, a.appointment_date, a.dentist_id, a.service_id,
                       s.service_name, s.price,
                       d.first_name AS dentist_first_name, d.last_name AS dentist_last_name
                       FROM payment p
                       JOIN appointment a ON p.appointment_id = a.appointment_id
                       JOIN service s ON a.service_id = s.service_id
                       JOIN user d ON a.dentist_id = d.user_id
                       WHERE p.payment_id = :payment_id AND a.patient_id = :patient_id");
$stmt->bindParam(':payment_id', $payment_id);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->execute();

// Check if payment exists and belongs to the patient
if ($stmt->rowCount() == 0) {
    $_SESSION['error'] = "Payment not found or you don't have permission to view it.";
    header("Location: my-payments.php");
    exit();
}

$payment = $stmt->fetch(PDO::FETCH_ASSOC);

// For test payment, simulate a successful payment by updating the status
if ($_SERVER['HTTP_HOST'] == 'localhost') {
    // Only in local testing environment
    $stmt = $conn->prepare("UPDATE payment SET status = 'succeeded', updated_at = NOW() WHERE payment_id = :payment_id");
    $stmt->bindParam(':payment_id', $payment_id);
    $stmt->execute();
    
    // Reload payment data
    $stmt = $conn->prepare("SELECT * FROM payment WHERE payment_id = :payment_id");
    $stmt->bindParam(':payment_id', $payment_id);
    $stmt->execute();
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Reload the complete payment data with joins
    $stmt = $conn->prepare("SELECT p.*, a.patient_id, a.appointment_date, a.dentist_id, a.service_id,
                           s.service_name, s.price,
                           d.first_name AS dentist_first_name, d.last_name AS dentist_last_name
                           FROM payment p
                           JOIN appointment a ON p.appointment_id = a.appointment_id
                           JOIN service s ON a.service_id = s.service_id
                           JOIN user d ON a.dentist_id = d.user_id
                           WHERE p.payment_id = :payment_id");
    $stmt->bindParam(':payment_id', $payment_id);
    $stmt->execute();
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get user information
$stmt = $conn->prepare("SELECT * FROM user WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Update notification status if this payment was notified
$stmt = $conn->prepare("UPDATE notification SET is_read = 1 
                       WHERE user_id = :user_id 
                       AND related_entity_id = :payment_id 
                       AND related_table = 'payment'");
$stmt->bindParam(':user_id', $patient_id);
$stmt->bindParam(':payment_id', $payment_id);
$stmt->execute();

// Send notification to the dentist about successful payment
if ($payment['status'] == 'succeeded') {
    $notification_message = $user['first_name'] . ' ' . $user['last_name'] . ' has successfully paid for appointment on ' . date('F j, Y', strtotime($payment['appointment_date']));
    
    $stmt = $conn->prepare("INSERT INTO notification (user_id, notification_type, notification_message, related_entity_id, related_table, is_read, is_sent, send_via, created_at) 
                           VALUES (:dentist_id, 'payment', :message, :payment_id, 'payment', 0, 1, 'email', NOW())");
    $stmt->bindParam(':dentist_id', $payment['dentist_id']);
    $stmt->bindParam(':message', $notification_message);
    $stmt->bindParam(':payment_id', $payment_id);
    $stmt->execute();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success - JRC Dental Care</title>
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
                    <h3>Payment Receipt</h3>
                    <a href="my-payments.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Payments
                    </a>
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
                
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <!-- Payment Receipt Card -->
                        <div class="card border-0 shadow-sm mb-4">
                            <?php if ($payment['status'] == 'succeeded'): ?>
                                <div class="card-header bg-success bg-opacity-10 py-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0 text-success">Payment Successful</h5>
                                        <span class="badge bg-success">Paid</span>
                                    </div>
                                </div>
                            <?php elseif ($payment['status'] == 'pending'): ?>
                                <div class="card-header bg-warning bg-opacity-10 py-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0 text-warning">Payment Pending</h5>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="card-header bg-danger bg-opacity-10 py-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0 text-danger">Payment Failed</h5>
                                        <span class="badge bg-danger">Failed</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body p-4">
                                <div class="text-center mb-4">
                                    <img src="images/jrclogo.png" alt="JRC Dental Care" class="img-fluid" style="max-height: 80px;">
                                    <h5 class="mt-3">JRC Dental Care</h5>
                                    <p class="text-muted mb-0">Receipt #<?= $payment_id . '-' . date('Ymd', strtotime($payment['created_at'])) ?></p>
                                </div>
                                
                                <hr class="my-4">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p class="text-muted mb-1">Patient</p>
                                        <h6><?= $user['first_name'] . ' ' . $user['last_name'] ?></h6>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="text-muted mb-1">Date</p>
                                        <h6><?= date('F j, Y', strtotime($payment['created_at'])) ?></h6>
                                    </div>
                                </div>
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <p class="text-muted mb-1">Service</p>
                                        <h6><?= $payment['service_name'] ?></h6>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="text-muted mb-1">Appointment Date</p>
                                        <h6><?= date('F j, Y h:i A', strtotime($payment['appointment_date'])) ?></h6>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p class="text-muted mb-1">Dentist</p>
                                        <h6>Dr. <?= $payment['dentist_first_name'] . ' ' . $payment['dentist_last_name'] ?></h6>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="text-muted mb-1">Payment Method</p>
                                        <h6><?= ucfirst($payment['payment_method']) ?></h6>
                                    </div>
                                </div>
                                
                                <div class="table-responsive mt-4">
                                    <table class="table table-bordered">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>Service</th>
                                                <th class="text-end">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><?= $payment['service_name'] ?></td>
                                                <td class="text-end">₱<?= number_format($payment['amount'] / 100, 2) ?></td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th>Total</th>
                                                <th class="text-end">₱<?= number_format($payment['amount'] / 100,2) ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <?php if ($payment['status'] == 'succeeded'): ?>
                                        <div class="alert alert-success" role="alert">
                                            <i class="fas fa-check-circle me-2"></i>Your payment has been completed successfully. 
                                            Thank you for your payment!
                                        </div>
                                        
                                        <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
                                            <a href="appointment-details.php?id=<?= $payment['appointment_id'] ?>" class="btn btn-primary">
                                                <i class="fas fa-calendar-check me-2"></i>View Appointment
                                            </a>
                                            <button onclick="window.print()" class="btn btn-outline-secondary">
                                                <i class="fas fa-print me-2"></i>Print Receipt
                                            </button>
                                        </div>
                                    <?php elseif ($payment['status'] == 'pending'): ?>
                                        <div class="alert alert-warning" role="alert">
                                            <i class="fas fa-exclamation-triangle me-2"></i>Your payment is currently being processed. 
                                            Please check back later for the status update.
                                        </div>
                                        
                                        <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
                                            <a href="appointment-details.php?id=<?= $payment['appointment_id'] ?>" class="btn btn-primary">
                                                <i class="fas fa-calendar-check me-2"></i>View Appointment
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-danger" role="alert">
                                            <i class="fas fa-times-circle me-2"></i>Your payment was not successful. 
                                            Please try again or contact our support team for assistance.
                                        </div>
                                        
                                        <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
                                            <a href="make-payment.php?id=<?= $payment['appointment_id'] ?>" class="btn btn-primary">
                                                <i class="fas fa-credit-card me-2"></i>Try Again
                                            </a>
                                            <a href="appointment-details.php?id=<?= $payment['appointment_id'] ?>" class="btn btn-outline-secondary">
                                                <i class="fas fa-calendar-check me-2"></i>View Appointment
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($payment['status'] == 'succeeded'): ?>
                                <div class="card-footer bg-white py-3 text-center">
                                    <p class="mb-0">If you have any questions about this receipt, please contact</p>
                                    <p class="mb-0 fw-bold">support@jrcdentalcare.com</p>
                                </div>
                            <?php endif; ?>
                        </div>
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