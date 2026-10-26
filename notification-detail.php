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

// Check if notification ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid notification ID.";
    header("Location: my-notifications.php");
    exit();
}

$notification_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Verify notification belongs to the user
$stmt = $conn->prepare("SELECT * FROM notification WHERE notification_id = :notification_id AND user_id = :user_id");
$stmt->bindParam(':notification_id', $notification_id);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    $_SESSION['error'] = "Notification not found or you don't have permission to view it.";
    header("Location: my-notifications.php");
    exit();
}

$notification = $stmt->fetch(PDO::FETCH_ASSOC);

// Mark notification as read
if (!$notification['is_read']) {
    $stmt = $conn->prepare("UPDATE notification SET is_read = 1 WHERE notification_id = :notification_id");
    $stmt->bindParam(':notification_id', $notification_id);
    $stmt->execute();
    $notification['is_read'] = 1;
}

// Get related entity details if applicable
$related_entity = null;
if (!empty($notification['related_table']) && !empty($notification['related_entity_id'])) {
    switch ($notification['related_table']) {
        case 'appointment':
            $stmt = $conn->prepare("SELECT a.*, s.service_name, u.first_name, u.last_name 
                                   FROM appointment a 
                                   JOIN service s ON a.service_id = s.service_id 
                                   JOIN user u ON a.dentist_id = u.user_id
                                   WHERE a.appointment_id = :id");
            $stmt->bindParam(':id', $notification['related_entity_id']);
            $stmt->execute();
            $related_entity = $stmt->fetch(PDO::FETCH_ASSOC);
            $related_entity['type'] = 'appointment';
            break;
            
        case 'payment':
            $stmt = $conn->prepare("SELECT p.*, a.appointment_date, s.service_name 
                                   FROM payment p 
                                   JOIN appointment a ON p.appointment_id = a.appointment_id 
                                   JOIN service s ON a.service_id = s.service_id
                                   WHERE p.payment_id = :id");
            $stmt->bindParam(':id', $notification['related_entity_id']);
            $stmt->execute();
            $related_entity = $stmt->fetch(PDO::FETCH_ASSOC);
            $related_entity['type'] = 'payment';
            break;
            
        case 'treatment':
            $stmt = $conn->prepare("SELECT t.*, s.service_name, u.first_name, u.last_name 
                                   FROM treatment t 
                                   JOIN service s ON t.service_id = s.service_id 
                                   JOIN user u ON t.dentist_id = u.user_id
                                   WHERE t.treatment_id = :id");
            $stmt->bindParam(':id', $notification['related_entity_id']);
            $stmt->execute();
            $related_entity = $stmt->fetch(PDO::FETCH_ASSOC);
            $related_entity['type'] = 'treatment';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Detail - JRC Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/patient-dashboard.css">
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
                    <h3>Notification Details</h3>
                    <a href="my-notifications.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Notifications
                    </a>
                </div>
                
                <div class="row">
                    <div class="col-lg-8 mx-auto">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><?= isset($notification['title']) ? $notification['title'] : 'Notification' ?></h5>
                                    <span class="badge <?= $notification['is_read'] ? 'bg-secondary' : 'bg-primary' ?>">
                                        <?= $notification['is_read'] ? 'Read' : 'Unread' ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <div class="mb-4">
                                    <p class="text-muted small mb-1">Received on</p>
                                    <p><?= date('F j, Y \a\t g:i A', strtotime($notification['created_at'])) ?></p>
                                </div>
                                
                                <div class="mb-4">
                                    <p class="text-muted small mb-1">Message</p>
                                    <div class="p-3 bg-light rounded">
                                        <p class="mb-0"><?= nl2br($notification['notification_message']) ?></p>
                                    </div>
                                </div>
                                
                                <?php if ($notification['notification_type']): ?>
                                    <div class="mb-4">
                                        <p class="text-muted small mb-1">Type</p>
                                        <p>
                                            <?php
                                            switch ($notification['notification_type']) {
                                                case 'appointment':
                                                    echo '<span class="badge bg-info">Appointment</span>';
                                                    break;
                                                case 'payment':
                                                    echo '<span class="badge bg-success">Payment</span>';
                                                    break;
                                                case 'treatment':
                                                    echo '<span class="badge bg-warning text-dark">Treatment</span>';
                                                    break;
                                                default:
                                                    echo '<span class="badge bg-secondary">System</span>';
                                            }
                                            ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($related_entity): ?>
                                    <div class="mt-4 pt-4 border-top">
                                        <h6 class="mb-3">Related Information</h6>
                                        
                                        <?php if ($related_entity['type'] == 'appointment'): ?>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p class="text-muted small mb-1">Service</p>
                                                    <p><?= $related_entity['service_name'] ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p class="text-muted small mb-1">Dentist</p>
                                                    <p>Dr. <?= $related_entity['first_name'] . ' ' . $related_entity['last_name'] ?></p>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p class="text-muted small mb-1">Date & Time</p>
                                                    <p><?= date('F j, Y \a\t g:i A', strtotime($related_entity['appointment_date'])) ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p class="text-muted small mb-1">Status</p>
                                                    <p>
                                                        <?php 
                                                        $status_class = '';
                                                        switch ($related_entity['status']) {
                                                            case 'pending':
                                                                $status_class = 'bg-warning text-dark';
                                                                break;
                                                            case 'confirmed':
                                                                $status_class = 'bg-info';
                                                                break;
                                                            case 'completed':
                                                                $status_class = 'bg-success';
                                                                break;
                                                            case 'canceled':
                                                                $status_class = 'bg-danger';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge <?= $status_class ?>">
                                                            <?= ucfirst($related_entity['status']) ?>
                                                        </span>
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                                                <a href="appointment-details.php?id=<?= $related_entity['appointment_id'] ?>" class="btn btn-primary">
                                                    <i class="fas fa-calendar-check me-2"></i>View Appointment
                                                </a>
                                            </div>
                                            
                                        <?php elseif ($related_entity['type'] == 'payment'): ?>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p class="text-muted small mb-1">Service</p>
                                                    <p><?= $related_entity['service_name'] ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p class="text-muted small mb-1">Amount</p>
                                                    <p>₱<?= number_format($related_entity['amount'] / 100, 2) ?></p>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p class="text-muted small mb-1">Payment Date</p>
                                                    <p><?= date('F j, Y', strtotime($related_entity['created_at'])) ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p class="text-muted small mb-1">Status</p>
                                                    <p>
                                                        <?php 
                                                        $status_class = '';
                                                        switch ($related_entity['status']) {
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
                                                            <?= ucfirst($related_entity['status']) ?>
                                                        </span>
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                                                <a href="payment-success.php?id=<?= $related_entity['payment_id'] ?>" class="btn btn-primary">
                                                    <i class="fas fa-receipt me-2"></i>View Payment
                                                </a>
                                            </div>
                                            
                                        <?php elseif ($related_entity['type'] == 'treatment'): ?>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p class="text-muted small mb-1">Service</p>
                                                    <p><?= $related_entity['service_name'] ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p class="text-muted small mb-1">Dentist</p>
                                                    <p>Dr. <?= $related_entity['first_name'] . ' ' . $related_entity['last_name'] ?></p>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p class="text-muted small mb-1">Treatment Date</p>
                                                    <p><?= date('F j, Y', strtotime($related_entity['treatment_date'])) ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p class="text-muted small mb-1">Status</p>
                                                    <p>
                                                        <?php 
                                                        $status_class = '';
                                                        switch ($related_entity['status']) {
                                                            case 'planned':
                                                                $status_class = 'bg-info';
                                                                break;
                                                            case 'in_progress':
                                                                $status_class = 'bg-warning text-dark';
                                                                break;
                                                            case 'completed':
                                                                $status_class = 'bg-success';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge <?= $status_class ?>">
                                                            <?= ucfirst(str_replace('_', ' ', $related_entity['status'])) ?>
                                                        </span>
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                                                <a href="treatment-detail.php?id=<?= $related_entity['treatment_id'] ?>" class="btn btn-primary">
                                                    <i class="fas fa-tooth me-2"></i>View Treatment
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
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
        });
    </script>
</body>
</html>