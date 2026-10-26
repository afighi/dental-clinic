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

// Check if payment ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid payment ID.";
    header("Location: my-payments.php");
    exit();
}

$payment_id = $_GET['id'];
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

// Get user information
$stmt = $conn->prepare("SELECT * FROM user WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Format date for receipt number
$receipt_number = $payment_id . '-' . date('Ymd', strtotime($payment['created_at']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - JRC Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            .print-section, .print-section * {
                visibility: visible;
            }
            .print-section {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
        
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .receipt-header img {
            max-height: 80px;
        }
        
        .receipt-divider {
            height: 1px;
            background-color: #e0e0e0;
            margin: 20px 0;
        }
        
        .receipt-footer {
            font-size: 14px;
            color: #6c757d;
            text-align: center;
            margin-top: 30px;
        }
    </style>
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
                <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                    <h3>Payment Receipt</h3>
                    <div>
                        <button onclick="window.print()" class="btn btn-primary me-2">
                            <i class="fas fa-print me-2"></i>Print Receipt
                        </button>
                        <a href="my-payments.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Payments
                        </a>
                    </div>
                </div>
                
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                        <?= $_SESSION['success'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
                        <?= $_SESSION['error'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <!-- Receipt Content - This will be printable -->
                <div class="print-section">
                    <div class="receipt-container">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4">
                                <div class="receipt-header text-center mb-4">
                                    <img src="images/jrclogo.png" alt="JRC Dental Care" class="img-fluid mb-3">
                                    <h4 class="mb-1">JRC Dental Care</h4>
                                    <p class="text-muted mb-0">123 Main Street, Anytown, Philippines</p>
                                    <p class="text-muted mb-0">Tel: (123) 456-7890 | Email: info@jrcdentalcare.com</p>
                                </div>
                                
                                <div class="receipt-title text-center mb-4">
                                    <h2 class="mb-0">Official Receipt</h2>
                                    <p class="text-muted">Receipt #<?= $receipt_number ?></p>
                                </div>
                                
                                <div class="receipt-divider"></div>
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <h6 class="fw-bold mb-2">Patient Information:</h6>
                                        <p class="mb-1"><?= $user['first_name'] . ' ' . $user['last_name'] ?></p>
                                        <p class="mb-1"><?= $user['email'] ?></p>
                                        <p class="mb-1"><?= $user['phone_number'] ?></p>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <h6 class="fw-bold mb-2">Receipt Details:</h6>
                                        <p class="mb-1">Date: <?= date('F j, Y', strtotime($payment['created_at'])) ?></p>
                                        <p class="mb-1">Payment Status: 
                                            <?php if ($payment['status'] == 'succeeded'): ?>
                                                <span class="badge bg-success">Paid</span>
                                            <?php elseif ($payment['status'] == 'pending'): ?>
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Failed</span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="mb-1">Payment Method: <?= ucfirst($payment['payment_method']) ?></p>
                                    </div>
                                </div>
                                
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <h6 class="fw-bold mb-2">Appointment Details:</h6>
                                        <p class="mb-1">Appointment Date: <?= date('F j, Y h:i A', strtotime($payment['appointment_date'])) ?></p>
                                        <p class="mb-1">Dentist: Dr. <?= $payment['dentist_first_name'] . ' ' . $payment['dentist_last_name'] ?></p>
                                        <p class="mb-1">Service: <?= $payment['service_name'] ?></p>
                                    </div>
                                </div>
                                
                                <div class="receipt-divider"></div>
                                
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Description</th>
                                                <th>Quantity</th>
                                                <th class="text-end">Unit Price</th>
                                                <th class="text-end">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><?= $payment['service_name'] ?></td>
                                                <td>1</td>
                                                <td class="text-end">₱<?= number_format($payment['amount'] / 100, 2) ?></td>
                                                <td class="text-end">₱<?= number_format($payment['amount'] / 100, 2) ?></td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="3" class="text-end">Subtotal:</th>
                                                <th class="text-end">₱<?= number_format($payment['amount'] / 100, 2) ?></th>
                                            </tr>
                                            <tr>
                                                <th colspan="3" class="text-end">Tax (0%):</th>
                                                <th class="text-end">₱0.00</th>
                                            </tr>
                                            <tr>
                                                <th colspan="3" class="text-end">Total:</th>
                                                <th class="text-end">₱<?= number_format($payment['amount'] / 100, 2) ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                
                                <div class="receipt-divider"></div>
                                
                                <?php if ($payment['status'] == 'succeeded'): ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle me-2"></i>Payment has been received. Thank you for your business!
                                    </div>
                                <?php elseif ($payment['status'] == 'pending'): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Payment is currently being processed. Please check back later for the status update.
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-times-circle me-2"></i>Payment was not successful. Please contact our office for assistance.
                                    </div>
                                <?php endif; ?>
                                
                                <div class="receipt-footer">
                                    <p>This is a computer-generated receipt and does not require a signature.</p>
                                    <p>If you have any questions about this receipt, please contact support@jrcdentalcare.com</p>
                                    <p class="mb-0">Thank you for choosing JRC Dental Care!</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-center mt-4 no-print">
                    <a href="appointment-details.php?id=<?= $payment['appointment_id'] ?>" class="btn btn-outline-primary mx-2">
                        <i class="fas fa-calendar-check me-2"></i>View Appointment
                    </a>
                    <a href="my-payments.php" class="btn btn-outline-secondary mx-2">
                        <i class="fas fa-list me-2"></i>View All Payments
                    </a>
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