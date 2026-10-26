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
$stmt = $conn->prepare("SELECT a.*, s.service_name, s.description AS service_description, s.price, s.duration_minutes,
                       d.first_name AS dentist_first_name, d.last_name AS dentist_last_name,
                       di.license_number, di.specialization
                       FROM appointment a
                       JOIN service s ON a.service_id = s.service_id
                       JOIN user d ON a.dentist_id = d.user_id
                       JOIN dentist_info di ON d.user_id = di.user_id
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

// Get payment information if available
$stmt = $conn->prepare("SELECT * FROM payment WHERE appointment_id = :appointment_id");
$stmt->bindParam(':appointment_id', $appointment_id);
$stmt->execute();
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

// Get any treatments linked to this appointment
$stmt = $conn->prepare("SELECT * FROM treatment WHERE appointment_id = :appointment_id");
$stmt->bindParam(':appointment_id', $appointment_id);
$stmt->execute();
$treatment = $stmt->fetch(PDO::FETCH_ASSOC);

// Update notification status if this appointment was notified
$stmt = $conn->prepare("UPDATE notification SET is_read = 1 
                       WHERE user_id = :user_id 
                       AND related_entity_id = :appointment_id 
                       AND related_table = 'appointment'");
$stmt->bindParam(':user_id', $patient_id);
$stmt->bindParam(':appointment_id', $appointment_id);
$stmt->execute();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Details - JRC Dental Care</title>
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
                    <h3>Appointment Details</h3>
                    <a href="my-appointments.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-2"></i>Back to Appointments</a>
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
                
                <div class="row">
                    <div class="col-lg-8">
                        <!-- Appointment Information -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Appointment Information</h5>
                                    <span class="badge <?php 
                                    switch($appointment['status']) {
                                        case 'pending': echo 'bg-warning text-dark'; break;
                                        case 'confirmed': echo 'bg-info'; break;
                                        case 'completed': echo 'bg-success'; break;
                                        case 'canceled': echo 'bg-danger'; break;
                                        default: echo 'bg-secondary';
                                    }
                                    ?>">
                                        <?= ucfirst($appointment['status']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p class="text-muted mb-1">Service</p>
                                        <h6><?= $appointment['service_name'] ?></h6>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="text-muted mb-1">Dentist</p>
                                        <h6>Dr. <?= $appointment['dentist_first_name'] . ' ' . $appointment['dentist_last_name'] ?></h6>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p class="text-muted mb-1">Date & Time</p>
                                        <h6><?= date('F j, Y', strtotime($appointment['appointment_date'])) ?></h6>
                                        <h6><?= date('g:i A', strtotime($appointment['appointment_date'])) ?></h6>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="text-muted mb-1">Duration</p>
                                        <h6><?= $appointment['duration_minutes'] ?> minutes</h6>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <p class="text-muted mb-1">Service Description</p>
                                        <p><?= $appointment['service_description'] ?></p>
                                    </div>
                                </div>
                                
                                <?php if (!empty($appointment['notes'])): ?>
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <p class="text-muted mb-1">Notes</p>
                                        <p><?= nl2br($appointment['notes']) ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                 <!-- Appointment Actions -->
                                 <div class="d-flex justify-content-end mt-4">
                                    <?php if ($appointment['status'] == 'pending' || $appointment['status'] == 'confirmed'): ?>
                                        <button type="button" class="btn btn-outline-danger me-2" data-bs-toggle="modal" data-bs-target="#cancelModal">
                                            <i class="fas fa-times-circle me-2"></i>Cancel Appointment
                                        </button>
                                        <?php if (empty($payment) && $appointment['status'] != 'completed'): ?>
                                            <a href="make-payment.php?id=<?= $appointment_id ?>" class="btn btn-primary">
                                                <i class="fas fa-credit-card me-2"></i>Make Payment
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- Payment Information -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">Payment Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-3">
                                    <h6>Service Fee:</h6>
                                    <h6>₱<?= number_format($appointment['price'] / 100, 2) ?></h6>
                                </div>
                                
                                <?php if ($payment): ?>
                                    <div class="d-flex justify-content-between mb-3">
                                        <h6>Payment Status:</h6>
                                        <h6>
                                            <span class="badge <?php 
                                            switch($payment['status']) {
                                                case 'succeeded': echo 'bg-success'; break;
                                                case 'pending': echo 'bg-warning text-dark'; break;
                                                case 'failed': echo 'bg-danger'; break;
                                                default: echo 'bg-secondary';
                                            }
                                            ?>">
                                                <?= ucfirst($payment['status']) ?>
                                            </span>
                                        </h6>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <h6>Payment Method:</h6>
                                        <h6><?= ucfirst($payment['payment_method']) ?></h6>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <h6>Payment Date:</h6>
                                        <h6><?= date('M d, Y', strtotime($payment['created_at'])) ?></h6>
                                    </div>
                                    
                                    <?php if ($payment['status'] == 'succeeded'): ?>
                                        <div class="d-grid gap-2 mt-4">
                                            <a href="payment-receipt.php?id=<?= $payment['payment_id'] ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-receipt me-2"></i>View Receipt
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="alert alert-warning mb-3">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Payment for this appointment is pending.
                                    </div>
                                    <?php if ($appointment['status'] != 'canceled'): ?>
                                        <div class="d-grid gap-2">
                                            <a href="make-payment.php?id=<?= $appointment_id ?>" class="btn btn-primary">
                                                <i class="fas fa-credit-card me-2"></i>Make Payment
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Dentist Information -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">Dentist Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                                        <i class="fas fa-user-md text-primary"></i>
                                    </div>
                                    <div>
                                        <h6>Dr. <?= $appointment['dentist_first_name'] . ' ' . $appointment['dentist_last_name'] ?></h6>
                                        <p class="text-muted mb-0"><?= $appointment['specialization'] ?: 'General Dentist' ?></p>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <p class="text-muted mb-1">License Number</p>
                                    <p><?= $appointment['license_number'] ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Treatment Information if available -->
                <?php if ($treatment): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">Treatment Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <p class="text-muted mb-1">Treatment Date</p>
                                        <h6><?= date('F j, Y', strtotime($treatment['treatment_date'])) ?></h6>
                                    </div>
                                    <div class="col-md-4">
                                        <p class="text-muted mb-1">Status</p>
                                        <h6>
                                            <span class="badge <?php 
                                            switch($treatment['status']) {
                                                case 'planned': echo 'bg-info'; break;
                                                case 'in_progress': echo 'bg-warning text-dark'; break;
                                                case 'completed': echo 'bg-success'; break;
                                                default: echo 'bg-secondary';
                                            }
                                            ?>">
                                                <?= ucfirst(str_replace('_', ' ', $treatment['status'])) ?>
                                            </span>
                                        </h6>
                                    </div>
                                    <div class="col-md-4">
                                        <p class="text-muted mb-1">Cost</p>
                                        <h6>₱<?= number_format($treatment['cost'], 2) ?></h6>
                                    </div>
                                </div>
                                
                                <?php if (!empty($treatment['notes'])): ?>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <p class="text-muted mb-1">Treatment Notes</p>
                                        <p><?= nl2br($treatment['notes']) ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Cancel Appointment Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cancelModalLabel">Cancel Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel this appointment?</p>
                    <p class="text-danger"><small>Note: Cancellation within 24 hours of the appointment may incur a fee.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <form action="cancel-appointment.php" method="post">
                        <input type="hidden" name="appointment_id" value="<?= $appointment_id ?>">
                        <button type="submit" class="btn btn-danger">Yes, Cancel Appointment</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
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