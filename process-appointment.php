<?php
// Start session
session_start();

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    $response = [
        'success' => false,
        'message' => 'Unauthorized access'
    ];
    echo json_encode($response);
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response = [
        'success' => false,
        'message' => 'Invalid request method'
    ];
    echo json_encode($response);
    exit();
}

// Include database connection
require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

try {
    // Get form data
    $service_id = filter_input(INPUT_POST, 'service_id', FILTER_SANITIZE_NUMBER_INT);
    $dentist_id = filter_input(INPUT_POST, 'dentist_id', FILTER_SANITIZE_NUMBER_INT);
    $appointment_date = filter_input(INPUT_POST, 'appointment_date', FILTER_SANITIZE_STRING);
    $appointment_time = filter_input(INPUT_POST, 'appointment_time', FILTER_SANITIZE_STRING);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
    $add_to_calendar = isset($_POST['add_to_calendar']) ? 1 : 0;
    
    // Validate input
    if (empty($service_id) || empty($dentist_id) || empty($appointment_date) || empty($appointment_time)) {
        throw new Exception('Please fill in all required fields');
    }
    
    // Combine date and time
    $appointment_datetime = $appointment_date . ' ' . $appointment_time;
    
    // Check if datetime is valid
    $datetime = new DateTime($appointment_datetime);
    if ($datetime < new DateTime()) {
        throw new Exception('Cannot book appointments in the past');
    }
    
    // Check if timeslot is available
    $stmt = $conn->prepare("SELECT COUNT(*) FROM appointment 
                          WHERE dentist_id = :dentist_id 
                          AND DATE(appointment_date) = :appointment_date
                          AND TIME(appointment_date) = :appointment_time
                          AND status != 'cancelled'");
    $stmt->bindParam(':dentist_id', $dentist_id);
    $stmt->bindParam(':appointment_date', $appointment_date);
    $stmt->bindParam(':appointment_time', $appointment_time);
    $stmt->execute();
    
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('This time slot is no longer available. Please select another time.');
    }
    
    // Format datetime for MySQL
    $formatted_datetime = $datetime->format('Y-m-d H:i:s');
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Get service price
    $stmt = $conn->prepare("SELECT price FROM service WHERE service_id = :service_id");
    $stmt->bindParam(':service_id', $service_id);
    $stmt->execute();
    $service_price = $stmt->fetchColumn();
    
    // Insert appointment
    $stmt = $conn->prepare("INSERT INTO appointment 
                          (patient_id, dentist_id, service_id, appointment_date, notes, status, created_at, payment_status) 
                          VALUES 
                          (:patient_id, :dentist_id, :service_id, :appointment_date, :notes, 'pending', NOW(), 'unpaid')");
    
    $stmt->bindParam(':patient_id', $_SESSION['user_id']);
    $stmt->bindParam(':dentist_id', $dentist_id);
    $stmt->bindParam(':service_id', $service_id);
    $stmt->bindParam(':appointment_date', $formatted_datetime);
    $stmt->bindParam(':notes', $notes);
    $stmt->execute();
    
    $appointment_id = $conn->lastInsertId();
    
    // Create Google Calendar event if requested
    $google_event_id = null;
    $google_calendar_link = null;
    
    if ($add_to_calendar) {
        // This is a placeholder for Google Calendar integration
        // In a real implementation, you would use Google Calendar API
        // For now, we'll just set a placeholder value
        $google_event_id = 'event_' . time() . '_' . $appointment_id;
        $google_calendar_link = 'https://calendar.google.com/calendar/';
        
        // Update appointment with Google Calendar info
        $stmt = $conn->prepare("UPDATE appointment 
                              SET google_event_id = :google_event_id, google_calendar_link = :google_calendar_link 
                              WHERE appointment_id = :appointment_id");
        $stmt->bindParam(':google_event_id', $google_event_id);
        $stmt->bindParam(':google_calendar_link', $google_calendar_link);
        $stmt->bindParam(':appointment_id', $appointment_id);
        $stmt->execute();
    }
    
    // Get dentist name
    $stmt = $conn->prepare("SELECT first_name, last_name FROM user WHERE user_id = :dentist_id");
    $stmt->bindParam(':dentist_id', $dentist_id);
    $stmt->execute();
    $dentist = $stmt->fetch(PDO::FETCH_ASSOC);
    $dentist_name = "Dr. " . $dentist['first_name'] . " " . $dentist['last_name'];
    
    // Get service name
    $stmt = $conn->prepare("SELECT service_name FROM service WHERE service_id = :service_id");
    $stmt->bindParam(':service_id', $service_id);
    $stmt->execute();
    $service_name = $stmt->fetchColumn();
    
    // Create notification for dentist
    $dentist_notification_title = "New Appointment Request";
    $dentist_notification_message = "Patient " . $_SESSION['first_name'] . " " . $_SESSION['last_name'] . 
                                   " has requested an appointment for " . $service_name . 
                                   " on " . date('M d, Y h:i A', strtotime($formatted_datetime));
    
    $stmt = $conn->prepare("INSERT INTO notification 
                          (user_id, title, message, notification_type, is_read, created_at, related_id) 
                          VALUES 
                          (:user_id, :title, :message, 'appointment', 0, NOW(), :related_id)");
    
    $stmt->bindParam(':user_id', $dentist_id);
    $stmt->bindParam(':title', $dentist_notification_title);
    $stmt->bindParam(':message', $dentist_notification_message);
    $stmt->bindParam(':related_id', $appointment_id);
    $stmt->execute();
    
    // Create notification for patient
    $patient_notification_title = "Appointment Requested";
    $patient_notification_message = "Your appointment request with " . $dentist_name . 
                                  " for " . $service_name . 
                                  " on " . date('M d, Y h:i A', strtotime($formatted_datetime)) . 
                                  " has been submitted. You will be notified once it's confirmed.";
    
    $stmt = $conn->prepare("INSERT INTO notification 
                          (user_id, title, message, notification_type, is_read, created_at, related_id) 
                          VALUES 
                          (:user_id, :title, :message, 'appointment', 0, NOW(), :related_id)");
    
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':title', $patient_notification_title);
    $stmt->bindParam(':message', $patient_notification_message);
    $stmt->bindParam(':related_id', $appointment_id);
    $stmt->execute();
    
    // Create audit trail entry
    $action_details = "Created appointment #" . $appointment_id . " with " . $dentist_name . 
                    " for " . $service_name . " on " . date('M d, Y h:i A', strtotime($formatted_datetime));
    
    $stmt = $conn->prepare("INSERT INTO audittrail 
                          (user_id, action_performed, table_affected, record_id, details, timestamp) 
                          VALUES 
                          (:user_id, 'INSERT', 'appointment', :record_id, :details, NOW())");
    
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':record_id', $appointment_id);
    $stmt->bindParam(':details', $action_details);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    $response = [
        'success' => true,
        'message' => 'Appointment booked successfully',
        'appointment_id' => $appointment_id
    ];
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit();