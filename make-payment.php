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

// Check if appointment ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid appointment ID.";
    header("Location: my-appointments.php");
    exit();
}

$appointment_id = $_GET['id'];
$patient_id = $_SESSION['user_id'];

// Get appointment details
$stmt = $conn->prepare("SELECT a.*, s.service_name, s.price, s.duration_minutes,
                       d.first_name AS dentist_first_name, d.last_name AS dentist_last_name
                       FROM appointment a
                       JOIN service s ON a.service_id = s.service_id
                       JOIN user d ON a.dentist_id = d.user_id
                       WHERE a.appointment_id = :appointment_id AND a.patient_id = :patient_id");
$stmt->bindParam(':appointment_id', $appointment_id);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->execute();

// Check if appointment exists and belongs to the patient
if ($stmt->rowCount() == 0) {
    $_SESSION['error'] = "Appointment not found or you don't have permission to view it.";
    header("Location: my-appointments.php");
    exit();
}

$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if payment already exists
$stmt = $conn->prepare("SELECT * FROM payment WHERE appointment_id = :appointment_id AND status = 'succeeded'");
$stmt->bindParam(':appointment_id', $appointment_id);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    $_SESSION['error'] = "Payment for this appointment has already been made.";
    header("Location: appointment-details.php?id=" . $appointment_id);
    exit();
}

// Get PayMongo API credentials
$stmt = $conn->prepare("SELECT * FROM apiintegration WHERE api_name = 'PayMongo' AND is_active = 1");
$stmt->execute();
$paymongo_api = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$paymongo_api) {
    $_SESSION['error'] = "Payment gateway is currently unavailable. Please try again later.";
    header("Location: appointment-details.php?id=" . $appointment_id);
    exit();
}

$paymongo_credentials = json_decode($paymongo_api['credentials'], true);
$paymongo_public_key = $paymongo_credentials['public_key'];
$paymongo_secret_key = $paymongo_credentials['secret_key'];

// Get user information
$stmt = $conn->prepare("SELECT * FROM user WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Default email, phone, and name values in case they are not set in the user record
$user_email = isset($user['email']) ? $user['email'] : '';
$user_phone = isset($user['phone_number']) ? $user['phone_number'] : '';
$user_first_name = isset($user['first_name']) ? $user['first_name'] : '';
$user_last_name = isset($user['last_name']) ? $user['last_name'] : '';
$user_full_name = trim($user_first_name . ' ' . $user_last_name);
// If full name is still empty, set a default name
if (empty($user_full_name)) {
    $user_full_name = 'JRC Dental Patient';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment - JRC Dental Care</title>
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
                    <h3>Make Payment</h3>
                    <a href="appointment-details.php?id=<?= $appointment_id ?>" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Appointment
                    </a>
                </div>
                
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $_SESSION['error'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-lg-8">
                        <!-- Payment Form -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">Payment Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <p class="text-muted mb-1">Service</p>
                                        <h6><?= $appointment['service_name'] ?></h6>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="text-muted mb-1">Amount to Pay</p>
                                        <h5 class="text-primary">₱<?= number_format($appointment['price'] / 100, 2) ?></h5>
                                    </div>
                                </div>
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <p class="text-muted mb-1">Appointment Date & Time</p>
                                        <h6><?= date('F j, Y', strtotime($appointment['appointment_date'])) ?></h6>
                                        <h6><?= date('g:i A', strtotime($appointment['appointment_date'])) ?></h6>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="text-muted mb-1">Dentist</p>
                                        <h6>Dr. <?= $appointment['dentist_first_name'] . ' ' . $appointment['dentist_last_name'] ?></h6>
                                    </div>
                                </div>
                                
                                <hr class="my-4">
                                
                                <!-- Payment Method Selection -->
                                <h6 class="mb-3">Select Payment Method</h6>
                                
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="payment-methods mb-4">
                                            <div class="form-check form-check-inline mb-3">
                                                <input class="form-check-input" type="radio" name="paymentMethod" id="methodGcash" value="gcash" checked>
                                                <label class="form-check-label d-flex align-items-center" for="methodGcash">
                                                    <img src="images/gcash-logo.png" alt="GCash" class="me-2" style="height: 30px;">
                                                    GCash
                                                </label>
                                            </div>
                                            
                                            <div class="form-check form-check-inline mb-3">
                                                <input class="form-check-input" type="radio" name="paymentMethod" id="methodGrabpay" value="grab_pay">
                                                <label class="form-check-label d-flex align-items-center" for="methodGrabpay">
                                                    <img src="images/grabpay-logo.png" alt="GrabPay" class="me-2" style="height: 30px;">
                                                    GrabPay
                                                </label>
                                            </div>
                                            
                                            <div class="form-check form-check-inline mb-3">
                                                <input class="form-check-input" type="radio" name="paymentMethod" id="methodPaymaya" value="paymaya">
                                                <label class="form-check-label d-flex align-items-center" for="methodPaymaya">
                                                    <img src="images/paymaya-logo.png" alt="PayMaya" class="me-2" style="height: 30px;">
                                                    PayMaya
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <form id="paymentForm" action="process-payment.php" method="post">
                                    <input type="hidden" name="appointment_id" value="<?= $appointment_id ?>">
                                    <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
                                    <input type="hidden" name="amount" value="<?= $appointment['price'] ?>">
                                    <input type="hidden" name="payment_method" id="paymentMethodInput" value="gcash">
                                    
                                    <div class="mb-3">
                                        <label for="billing_name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="billing_name" name="billing_name" value="<?= htmlspecialchars($user_full_name) ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user_email) ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user_phone) ?>" required>
                                        <small class="text-muted">We'll send payment confirmation to this number</small>
                                    </div>
                                    
                                    <div class="d-grid gap-2 mt-4">
                                        <button type="submit" class="btn btn-primary py-2">
                                            <i class="fas fa-credit-card me-2"></i>Proceed to Payment
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- Payment Summary -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">Payment Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-3">
                                    <h6><?= $appointment['service_name'] ?></h6>
                                    <h6>₱<?= number_format($appointment['price'] / 100, 2) ?></h6>
                                </div>
                                
                                <hr>
                                
                                <div class="d-flex justify-content-between mb-3">
                                    <h6 class="fw-bold">Total Amount</h6>
                                    <h6 class="fw-bold text-primary">₱<?= number_format($appointment['price'] / 100, 2) ?></h6>
                                </div>
                                
                                <div class="alert alert-info mt-4">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <small>You will be redirected to the selected payment provider to complete your transaction securely.</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Payment Security Info -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="rounded-circle bg-success bg-opacity-10 p-2 me-3">
                                        <i class="fas fa-shield-alt text-success"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Secure Payment</h6>
                                    </div>
                                </div>
                                <p class="text-muted small mb-0">All payment information is securely processed through PayMongo's secure payment gateway.</p>
                            </div>
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
            
            // Payment method selection
            const paymentMethods = document.querySelectorAll('input[name="paymentMethod"]');
            const paymentMethodInput = document.getElementById('paymentMethodInput');
            
            paymentMethods.forEach(method => {
                method.addEventListener('change', function() {
                    paymentMethodInput.value = this.value;
                });
            });
            
            // Form validation
            const paymentForm = document.getElementById('paymentForm');
            paymentForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Basic validation
                const billingName = document.getElementById('billing_name').value;
                const email = document.getElementById('email').value;
                const phone = document.getElementById('phone').value;
                
                if (!billingName || !email || !phone) {
                    alert('Please fill in all required fields');
                    return;
                }
                
                // Submit the form
                this.submit();
            });
        });
    </script>
</body>
</html>