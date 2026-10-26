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

// Initialize alert variables
$alert = '';
$alert_type = '';

// Get patient information
$stmt = $conn->prepare("SELECT * FROM user WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Process appointment booking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $service_id = $_POST['service_id'];
        $dentist_id = $_POST['dentist_id'];
        $appointment_date = $_POST['appointment_date'];
        $appointment_time = $_POST['appointment_time'];
        $notes = $_POST['notes'] ?? '';
        $add_to_calendar = isset($_POST['add_to_calendar']) ? 1 : 0;
        
        // Validate inputs
        if (empty($service_id) || empty($dentist_id) || empty($appointment_date) || empty($appointment_time)) {
            throw new Exception("All required fields must be filled out");
        }
        
        // Combine date and time
        $appointment_datetime = $appointment_date . ' ' . $appointment_time;
        
        // Check if the appointment date is in the future
        if (strtotime($appointment_datetime) <= time()) {
            throw new Exception("Appointment date must be in the future");
        }
        
        // Check if the dentist is available at the selected time
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM appointment 
                                     WHERE dentist_id = :dentist_id 
                                     AND appointment_date = :appointment_date 
                                     AND status != 'canceled'");
        $check_stmt->bindParam(':dentist_id', $dentist_id);
        $check_stmt->bindParam(':appointment_date', $appointment_datetime);
        $check_stmt->execute();
        
        if ($check_stmt->fetchColumn() > 0) {
            throw new Exception("Selected time slot is not available. Please choose another time.");
        }
        
        // Begin transaction
        $conn->beginTransaction();
        
        // Create new appointment - FIXED: Removed payment_status field
        $stmt = $conn->prepare("INSERT INTO appointment 
                              (patient_id, dentist_id, service_id, appointment_date, notes, status, created_at) 
                              VALUES 
                              (:patient_id, :dentist_id, :service_id, :appointment_date, :notes, 'pending', NOW())");
        
        $stmt->bindParam(':patient_id', $_SESSION['user_id']);
        $stmt->bindParam(':dentist_id', $dentist_id);
        $stmt->bindParam(':service_id', $service_id);
        $stmt->bindParam(':appointment_date', $appointment_datetime);
        $stmt->bindParam(':notes', $notes);
        $stmt->execute();
        
        $appointment_id = $conn->lastInsertId();
        
        // Handle Google Calendar integration if requested
        if ($add_to_calendar) {
            $calendar_link = "#"; // Placeholder for actual Google Calendar API integration
            
            $stmt = $conn->prepare("UPDATE appointment 
                                  SET google_event_id = :google_event_id
                                  WHERE appointment_id = :appointment_id");
            
            $google_event_id = "event_" . time(); // Placeholder
            $stmt->bindParam(':google_event_id', $google_event_id);
            $stmt->bindParam(':appointment_id', $appointment_id);
            $stmt->execute();
        }
        
        // Create notification for dentist
        $stmt = $conn->prepare("INSERT INTO notification 
                              (user_id, notification_type, notification_message, related_entity_id, related_table, is_read, is_sent, send_via, created_at) 
                              VALUES 
                              (:user_id, 'appointment', :message, :related_id, 'appointment', 0, 1, 'email', NOW())");
        
        // Get dentist name
        $dentist_stmt = $conn->prepare("SELECT first_name, last_name FROM user WHERE user_id = :dentist_id");
        $dentist_stmt->bindParam(':dentist_id', $dentist_id);
        $dentist_stmt->execute();
        $dentist = $dentist_stmt->fetch(PDO::FETCH_ASSOC);
        
        $dentist_name = "Dr. " . $dentist['first_name'] . " " . $dentist['last_name'];
        
        // Get service name
        $service_stmt = $conn->prepare("SELECT service_name FROM service WHERE service_id = :service_id");
        $service_stmt->bindParam(':service_id', $service_id);
        $service_stmt->execute();
        $service_name = $service_stmt->fetchColumn();
        
        $message = "Patient " . $user['first_name'] . " " . $user['last_name'] . " has requested an appointment for " . $service_name . " on " . date('M d, Y h:i A', strtotime($appointment_datetime));
        
        $stmt->bindParam(':user_id', $dentist_id);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':related_id', $appointment_id);
        $stmt->execute();
        
        // Create notification for patient
        $stmt = $conn->prepare("INSERT INTO notification 
                              (user_id, notification_type, notification_message, related_entity_id, related_table, is_read, is_sent, send_via, created_at) 
                              VALUES 
                              (:user_id, 'appointment', :message, :related_id, 'appointment', 0, 1, 'email', NOW())");
        
        $message = "Your appointment request with " . $dentist_name . " for " . $service_name . " on " . date('M d, Y h:i A', strtotime($appointment_datetime)) . " has been submitted and is pending confirmation.";
        
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':related_id', $appointment_id);
        $stmt->execute();
        
        // Add to audit trail
        $stmt = $conn->prepare("INSERT INTO audittrail 
                              (user_id, action_performed, table_affected, details, timestamp) 
                              VALUES 
                              (:user_id, 'INSERT', 'appointment', :details, NOW())");
        
        $details = "Created new appointment request for " . $service_name . " with " . $dentist_name;
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':details', $details);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $alert = "Appointment booked successfully! You will be notified once the dentist confirms your appointment.";
        $alert_type = "success";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        $alert = "Error: " . $e->getMessage();
        $alert_type = "danger";
    }
}

// Get all services - FIXED: Removed is_active condition
$stmt = $conn->prepare("SELECT * FROM service ORDER BY service_name");
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all dentists - FIXED: Removed is_active condition
$stmt = $conn->prepare("SELECT * FROM user WHERE role_id = 2 ORDER BY first_name");
$stmt->execute();
$dentists = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - JRC Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
                    <h3 class="mb-0">Book Appointment</h3>
                </div>
                
                <?php if ($alert): ?>
                <div class="alert alert-<?= $alert_type ?> alert-dismissible fade show" role="alert">
                    <?= $alert ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">Appointment Form</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" id="appointmentForm">
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label for="service_id" class="form-label">Select Service</label>
                                            <select class="form-select" id="service_id" name="service_id" required>
                                                <option value="">-- Select Service --</option>
                                                <?php foreach ($services as $service): ?>
                                                <option value="<?= $service['service_id'] ?>" data-price="<?= $service['price'] ?>"><?= $service['service_name'] ?> - ₱<?= number_format($service['price'] / 100, 2) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="dentist_id" class="form-label">Select Dentist</label>
                                            <select class="form-select" id="dentist_id" name="dentist_id" required>
                                                <option value="">-- Select Dentist --</option>
                                                <?php foreach ($dentists as $dentist): ?>
                                                <option value="<?= $dentist['user_id'] ?>">Dr. <?= $dentist['first_name'] . ' ' . $dentist['last_name'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label for="appointment_date" class="form-label">Appointment Date</label>
                                            <input type="date" class="form-control" id="appointment_date" name="appointment_date" min="<?= date('Y-m-d') ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="appointment_time" class="form-label">Appointment Time</label>
                                            <select class="form-select" id="appointment_time" name="appointment_time" required>
                                                <option value="">-- Select Time --</option>
                                                <!-- Time slots will be populated dynamically -->
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Additional Notes (Optional)</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Enter any specific concerns or information for the dentist"></textarea>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="add_to_calendar" name="add_to_calendar" value="1">
                                        <label class="form-check-label" for="add_to_calendar">Add to Google Calendar</label>
                                    </div>
                                    
                                    <div class="alert alert-info mb-4">
                                        <i class="fas fa-info-circle me-2"></i> Please note that your appointment request will need to be confirmed by the dentist. You will receive a notification once it's confirmed.
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Book Appointment</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">Appointment Information</h5>
                            </div>
                            <div class="card-body">
                                <div id="service-details" class="mb-4">
                                    <h6 class="fw-bold">Service Details</h6>
                                    <p id="selected-service" class="mb-1">No service selected</p>
                                    <p id="service-price" class="mb-0">Price: -</p>
                                </div>
                                <div id="dentist-details" class="mb-4">
                                    <h6 class="fw-bold">Dentist</h6>
                                    <p id="selected-dentist" class="mb-0">No dentist selected</p>
                                </div>
                                <div id="date-time-details" class="mb-4">
                                    <h6 class="fw-bold">Date & Time</h6>
                                    <p id="selected-date-time" class="mb-0">No date/time selected</p>
                                </div>
                                <hr>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i> Please arrive 15 minutes before your scheduled appointment time. Payment will be required after your appointment is confirmed.
                                </div>
                            </div>
                        </div>
                        
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">Need Help?</h5>
                            </div>
                            <div class="card-body">
                                <p>For assistance with booking your appointment, please contact us:</p>
                                <p><i class="fas fa-phone text-primary me-2"></i> +63 912 345 6789</p>
                                <p><i class="fas fa-envelope text-primary me-2"></i> info@jrcdentalcare.com</p>
                                
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="assets/js/patient-dashboard.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize date picker
            flatpickr("#appointment_date", {
                minDate: "today",
                dateFormat: "Y-m-d",
                disable: [
                    function(date) {
                        // Disable Sundays
                        return (date.getDay() === 0);
                    }
                ],
                onChange: function(selectedDates, dateStr) {
                    // When date changes, update available time slots
                    const dentistId = document.getElementById('dentist_id').value;
                    if (dentistId) {
                        fetchAvailableTimeSlots(dentistId, dateStr);
                    }
                    updateSelectedAppointment();
                }
            });
            
            // Handle dentist selection change
            document.getElementById('dentist_id').addEventListener('change', function() {
                const dentistId = this.value;
                const dateStr = document.getElementById('appointment_date').value;
                
                if (dentistId && dateStr) {
                    fetchAvailableTimeSlots(dentistId, dateStr);
                }
                
                updateSelectedAppointment();
            });
            
            // Handle service selection change
            document.getElementById('service_id').addEventListener('change', function() {
                updateSelectedAppointment();
            });
            
            // Handle time selection change
            document.getElementById('appointment_time').addEventListener('change', function() {
                updateSelectedAppointment();
            });
            
            // Function to fetch available time slots based on dentist and date
            function fetchAvailableTimeSlots(dentistId, date) {
                const timeSelect = document.getElementById('appointment_time');
                timeSelect.innerHTML = '<option value="">Loading time slots...</option>';
                timeSelect.disabled = true;
                
                // Fetch available slots from server using AJAX
                fetch(`get-available-slots.php?dentist_id=${dentistId}&date=${date}`)
                    .then(response => response.json())
                    .then(data => {
                        timeSelect.innerHTML = '<option value="">-- Select Time --</option>';
                        
                        if (data.available_slots && data.available_slots.length > 0) {
                            data.available_slots.forEach(slot => {
                                const option = document.createElement('option');
                                option.value = slot.time;
                                option.textContent = slot.formatted_time;
                                timeSelect.appendChild(option);
                            });
                        } else {
                            const option = document.createElement('option');
                            option.value = "";
                            option.textContent = "No available slots for this date";
                            timeSelect.appendChild(option);
                        }
                        
                        timeSelect.disabled = false;
                    })
                    .catch(error => {
                        console.error('Error fetching time slots:', error);
                        timeSelect.innerHTML = '<option value="">Error loading time slots</option>';
                        timeSelect.disabled = false;
                    });
            }
            
            // Function to update the selected appointment details
            function updateSelectedAppointment() {
                // Get selected values
                const serviceSelect = document.getElementById('service_id');
                const dentistSelect = document.getElementById('dentist_id');
                const dateInput = document.getElementById('appointment_date');
                const timeSelect = document.getElementById('appointment_time');
                
                // Update service details
                const selectedServiceEl = document.getElementById('selected-service');
                const servicePriceEl = document.getElementById('service-price');
                
                if (serviceSelect.value) {
                    const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
                    selectedServiceEl.textContent = selectedOption.textContent.split(' - ')[0];
                    
                    const price = selectedOption.dataset.price;
                    servicePriceEl.textContent = `Price: ₱${(price / 100).toFixed(2)}`;
                } else {
                    selectedServiceEl.textContent = 'No service selected';
                    servicePriceEl.textContent = 'Price: -';
                }
                
                // Update dentist details
                const selectedDentistEl = document.getElementById('selected-dentist');
                
                if (dentistSelect.value) {
                    selectedDentistEl.textContent = dentistSelect.options[dentistSelect.selectedIndex].textContent;
                } else {
                    selectedDentistEl.textContent = 'No dentist selected';
                }
                
                // Update date and time details
                const selectedDateTimeEl = document.getElementById('selected-date-time');
                
                if (dateInput.value && timeSelect.value) {
                    const formattedDate = new Date(dateInput.value).toLocaleDateString('en-US', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                    
                    const formattedTime = timeSelect.options[timeSelect.selectedIndex].textContent;
                    selectedDateTimeEl.textContent = `${formattedDate} at ${formattedTime}`;
                } else {
                    selectedDateTimeEl.textContent = 'No date/time selected';
                }
            }
        });
    </script>
</body>
</html>