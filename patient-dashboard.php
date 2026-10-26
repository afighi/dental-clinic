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

// Get patient information
$stmt = $conn->prepare("SELECT * FROM patient_info WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$patient_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Get upcoming appointments
$stmt = $conn->prepare("SELECT a.*, s.service_name, u.first_name, u.last_name 
                       FROM appointment a 
                       JOIN service s ON a.service_id = s.service_id 
                       JOIN user u ON a.dentist_id = u.user_id
                       WHERE a.patient_id = :patient_id 
                       AND a.appointment_date > NOW() 
                       AND a.status != 'canceled'
                       ORDER BY a.appointment_date ASC LIMIT 5");
$stmt->bindParam(':patient_id', $_SESSION['user_id']);
$stmt->execute();
$upcoming_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent treatments
$stmt = $conn->prepare("SELECT t.*, s.service_name, u.first_name, u.last_name 
                       FROM treatment t 
                       JOIN service s ON t.service_id = s.service_id 
                       JOIN user u ON t.dentist_id = u.user_id
                       WHERE t.patient_id = :patient_id 
                       ORDER BY t.treatment_date DESC LIMIT 5");
$stmt->bindParam(':patient_id', $_SESSION['user_id']);
$stmt->execute();
$recent_treatments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent payments
$stmt = $conn->prepare("SELECT p.*, a.appointment_date, s.service_name 
                       FROM payment p 
                       JOIN appointment a ON p.appointment_id = a.appointment_id 
                       JOIN service s ON a.service_id = s.service_id
                       WHERE a.patient_id = :patient_id 
                       ORDER BY p.created_at DESC LIMIT 5");
$stmt->bindParam(':patient_id', $_SESSION['user_id']);
$stmt->execute();
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread notifications
$stmt = $conn->prepare("SELECT * FROM notification 
                       WHERE user_id = :user_id 
                       AND is_read = 0 
                       ORDER BY created_at DESC LIMIT 5");
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$unread_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get services for quick booking
$stmt = $conn->prepare("SELECT * FROM service ORDER BY service_name");
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get dentists for quick booking
$stmt = $conn->prepare("SELECT u.user_id, u.first_name, u.last_name, d.specialization
                       FROM user u
                       JOIN dentist_info d ON u.user_id = d.user_id
                       WHERE u.role_id = 2 OR u.role_id = 5
                       ORDER BY u.first_name");
$stmt->execute();
$dentists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming appointment count
$stmt = $conn->prepare("SELECT COUNT(*) as upcoming_count 
                       FROM appointment 
                       WHERE patient_id = :patient_id 
                       AND appointment_date > NOW() 
                       AND status != 'canceled'");
$stmt->bindParam(':patient_id', $_SESSION['user_id']);
$stmt->execute();
$upcoming_count = $stmt->fetch(PDO::FETCH_ASSOC)['upcoming_count'];

// Get pending payment count
$stmt = $conn->prepare("SELECT COUNT(*) as pending_count 
                       FROM appointment a
                       LEFT JOIN payment p ON a.appointment_id = p.appointment_id
                       WHERE a.patient_id = :patient_id 
                       AND (p.status = 'pending' OR p.payment_id IS NULL)
                       AND a.status != 'canceled'");
$stmt->bindParam(':patient_id', $_SESSION['user_id']);
$stmt->execute();
$pending_payment_count = $stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];

// Get completed treatment count
$stmt = $conn->prepare("SELECT COUNT(*) as completed_count 
                       FROM treatment 
                       WHERE patient_id = :patient_id 
                       AND status = 'completed'");
$stmt->bindParam(':patient_id', $_SESSION['user_id']);
$stmt->execute();
$completed_treatment_count = $stmt->fetch(PDO::FETCH_ASSOC)['completed_count'];

// Get unread notification count
$stmt = $conn->prepare("SELECT COUNT(*) as unread_count 
                       FROM notification 
                       WHERE user_id = :user_id 
                       AND is_read = 0");
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$unread_notification_count = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - JRC Dental Care</title>
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
                <h3 class="mb-4">Patient Dashboard</h3>
                
                <!-- Welcome Message and Quick Actions -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="card-title">Welcome back, <?= $user['first_name'] ?>!</h4>
                                <p class="card-text text-muted">Here's an overview of your dental care activity.</p>
                            </div>
                            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                <a href="book-appointment.php" class="btn btn-primary me-2"><i class="fas fa-calendar-plus me-2"></i>Book Appointment</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="icon-container bg-primary bg-opacity-10 rounded-circle p-3 me-3">
                                        <i class="fas fa-calendar-check text-primary"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Upcoming Appointments</h6>
                                        <h3 class="mb-0"><?= $upcoming_count ?></h3>
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
                                        <i class="fas fa-credit-card text-warning"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Pending Payments</h6>
                                        <h3 class="mb-0"><?= $pending_payment_count ?></h3>
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
                                        <i class="fas fa-tooth text-success"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Completed Treatments</h6>
                                        <h3 class="mb-0"><?= $completed_treatment_count ?></h3>
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
                                        <i class="fas fa-bell text-danger"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Unread Notifications</h6>
                                        <h3 class="mb-0"><?= $unread_notification_count ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Content Row -->
                <div class="row g-4">
                    <!-- Upcoming Appointments -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Upcoming Appointments</h5>
                                    <a href="my-appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (count($upcoming_appointments) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Service</th>
                                                    <th>Dentist</th>
                                                    <th>Date & Time</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($upcoming_appointments as $appointment): ?>
                                                    <tr onclick="window.location='appointment-details.php?id=<?= $appointment['appointment_id'] ?>'" style="cursor: pointer;">
                                                        <td><?= $appointment['service_name'] ?></td>
                                                        <td>Dr. <?= $appointment['first_name'] . ' ' . $appointment['last_name'] ?></td>
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
                                                                case 'canceled':
                                                                    $status_class = 'bg-danger';
                                                                    break;
                                                            }
                                                            ?>
                                                            <span class="badge <?= $status_class ?>">
                                                                <?= ucfirst($appointment['status']) ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center my-4">
                                        <img src="images/appointment.png" alt="No Appointments" class="img-fluid mb-3" style="max-width: 150px;">
                                        <h6>No upcoming appointments</h6>
                                        <p class="text-muted">Schedule your next dental appointment today.</p>
                                        <a href="book-appointment.php" class="btn btn-primary">Book Appointment</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Payments -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Recent Payments</h5>
                                    <a href="my-payments.php" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (count($recent_payments) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Service</th>
                                                    <th>Date</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_payments as $payment): ?>
                                                    <tr onclick="window.location='payment-receipt.php?id=<?= $payment['payment_id'] ?>'" style="cursor: pointer;">
                                                        <td><?= $payment['service_name'] ?></td>
                                                        <td><?= date('M d, Y', strtotime($payment['appointment_date'])) ?></td>
                                                        <td>₱<?= number_format($payment['amount'] / 100, 2) ?></td>
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
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center my-4">
                                        <img src="images/card.png" alt="No Payments" class="img-fluid mb-3" style="max-width: 150px;">
                                        <h6>No payment history yet</h6>
                                        <p class="text-muted">Your payment history will appear here.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Second Content Row -->
                <div class="row g-4 mt-3">
                    <!-- Calendar View -->
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">Your Schedule</h5>
                            </div>
                            <div class="card-body">
                                <div id="calendar"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notifications & Quick Book -->
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Notifications</h5>
                                    <a href="my-notifications.php" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <?php if (count($unread_notifications) > 0): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach($unread_notifications as $notification): ?>
                                            <a href="notification-detail.php?id=<?= $notification['notification_id'] ?>" class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?= isset($notification['title']) ? $notification['title'] : 'New Notification' ?></h6>
                                                    <small class="text-muted"><?= date('M d, h:i A', strtotime($notification['created_at'])) ?></small>
                                                </div>
                                                <p class="mb-1 text-truncate"><?= $notification['notification_message'] ?></p>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <img src="images/bell.png" alt="No Notifications" class="img-fluid mb-3" style="max-width: 100px;">
                                        <h6>No new notifications</h6>
                                        <p class="text-muted">You're all caught up!</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Treatments Row -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Recent Treatments</h5>
                                    <a href="my-treatments.php" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (count($recent_treatments) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Service</th>
                                                    <th>Dentist</th>
                                                    <th>Date</th>
                                                    <th>Status</th>
                                                    <th>Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_treatments as $treatment): ?>
                                                    <tr>
                                                        <td><?= $treatment['service_name'] ?></td>
                                                        <td>Dr. <?= $treatment['first_name'] . ' ' . $treatment['last_name'] ?></td>
                                                        <td><?= date('M d, Y', strtotime($treatment['treatment_date'])) ?></td>
                                                        <td>
                                                            <?php 
                                                            $status_class = '';
                                                            switch ($treatment['status']) {
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
                                                                <?= ucfirst(str_replace('_', ' ', $treatment['status'])) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if(!empty($treatment['notes'])): ?>
                                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= htmlspecialchars($treatment['notes']) ?>">
                                                                    <i class="fas fa-sticky-note"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center my-4">
                                        <img src="images/treatment.png" alt="No Treatments" class="img-fluid mb-3" style="max-width: 150px;">
                                        <h6>No treatment records found</h6>
                                        <p class="text-muted">Your treatment history will appear here after your dental visits.</p>
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
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="assets/js/patient-dashboard.js"></script>
    <script>
        // Initialize FullCalendar
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                height: 500,
                themeSystem: 'bootstrap5',
                events: 'get-patient-appointments.php',
                eventClick: function(info) {
                    window.location.href = 'appointment-details.php?id=' + info.event.id;
                }
            });
            calendar.render();
            
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Dynamic service duration
            const serviceSelect = document.getElementById('serviceSelect');
            const appointmentDateInput = document.getElementById('appointmentDate');
            
            serviceSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    const durationMinutes = selectedOption.getAttribute('data-duration');
                    // You could use the duration to validate or suggest end times
                    console.log(`Selected service duration: ${durationMinutes} minutes`);
                }
            });

            // Add this to your appointment form JavaScript
function loadTimeSlots() {
    const dentist_id = document.getElementById('dentistSelect').value;
    const selected_date = document.getElementById('appointmentDate').value;
    const service_id = document.getElementById('serviceSelect').value;
    
    if (!dentist_id || !selected_date || !service_id) {
        return;
    }
    
    const timeSelect = document.getElementById('appointmentTime');
    timeSelect.innerHTML = '<option value="">Loading time slots...</option>';
    
    fetch(`get-available-time-slots.php?dentist_id=${dentist_id}&date=${selected_date}&service_id=${service_id}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                timeSelect.innerHTML = `<option value="">${data.error}</option>`;
                return;
            }
            
            if (data.slots && data.slots.length > 0) {
                timeSelect.innerHTML = '<option value="">Select a time slot</option>';
                data.slots.forEach(slot => {
                    timeSelect.innerHTML += `<option value="${slot.value}">${slot.label}</option>`;
                });
            } else {
                timeSelect.innerHTML = '<option value="">No available time slots</option>';
            }
        })
        .catch(error => {
            console.error('Error fetching time slots:', error);
            timeSelect.innerHTML = '<option value="">Error loading time slots</option>';
        });
        }

        // Add event listeners to trigger time slot loading
        document.getElementById('dentistSelect').addEventListener('change', loadTimeSlots);
        document.getElementById('appointmentDate').addEventListener('change', loadTimeSlots);
        document.getElementById('serviceSelect').addEventListener('change', loadTimeSlots);
        });
    </script>
</body>
</html>