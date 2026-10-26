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

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $patient_id = $_SESSION['user_id'];
    $dentist_id = filter_input(INPUT_POST, 'dentist_id', FILTER_SANITIZE_NUMBER_INT);
    $service_id = filter_input(INPUT_POST, 'service_id', FILTER_SANITIZE_NUMBER_INT);
    $appointment_date = filter_input(INPUT_POST, 'appointment_date', FILTER_SANITIZE_STRING);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
    
    // Validate inputs
    if (empty($dentist_id) || empty($service_id) || empty($appointment_date)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: patient-dashboard.php");
        exit();
    }
    
    // Format date for MySQL
    $appointment_datetime = date('Y-m-d H:i:s', strtotime($appointment_date));
    
    // Check if date is in the future
    if (strtotime($appointment_datetime) <= time()) {
        $_SESSION['error'] = "Appointment date must be in the future.";
        header("Location: patient-dashboard.php");
        exit();
    }
    
    // Check if the dentist is available at the selected time
    $stmt = $conn->prepare("SELECT * FROM appointment 
                           WHERE dentist_id = :dentist_id 
                           AND status != 'canceled' 
                           AND appointment_date = :appointment_date");
    $stmt->bindParam(':dentist_id', $dentist_id);
    $stmt->bindParam(':appointment_date', $appointment_datetime);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['error'] = "The selected dentist is not available at this time. Please choose another time.";
        header("Location: patient-dashboard.php");
        exit();
    }
    
    // Get the service information to store duration
    $stmt = $conn->prepare("SELECT * FROM service WHERE service_id = :service_id");
    $stmt->bindParam(':service_id', $service_id);
    $stmt->execute();
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$service) {
        $_SESSION['error'] = "Invalid service selected.";
        header("Location: patient-dashboard.php");
        exit();
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Insert appointment
        $stmt = $conn->prepare("INSERT INTO appointment (patient_id, dentist_id, appointment_date, status, service_id, notes, created_at) 
                               VALUES (:patient_id, :dentist_id, :appointment_date, 'pending', :service_id, :notes, NOW())");
        $stmt->bindParam(':patient_id', $patient_id);
        $stmt->bindParam(':dentist_id', $dentist_id);
        $stmt->bindParam(':appointment_date', $appointment_datetime);
        $stmt->bindParam(':service_id', $service_id);
        $stmt->bindParam(':notes', $notes);
        $stmt->execute();
        
        $appointment_id = $conn->lastInsertId();
        
        // Create notification for the dentist
        $stmt = $conn->prepare("SELECT first_name, last_name FROM user WHERE user_id = :patient_id");
        $stmt->bindParam(':patient_id', $patient_id);
        $stmt->execute();
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $notification_message = "New appointment request from " . $patient['first_name'] . " " . $patient['last_name'] . " for " . $service['service_name'] . " on " . date('F j, Y \a\t g:i A', strtotime($appointment_datetime));
        
        $stmt = $conn->prepare("INSERT INTO notification (user_id, notification_type, notification_message, related_entity_id, related_table, is_read, is_sent, send_via, created_at) 
                               VALUES (:dentist_id, 'appointment', :message, :appointment_id, 'appointment', 0, 1, 'email', NOW())");
        $stmt->bindParam(':dentist_id', $dentist_id);
        $stmt->bindParam(':message', $notification_message);
        $stmt->bindParam(':appointment_id', $appointment_id);
        $stmt->execute();
        
        // Integrate with Google Calendar if enabled
        $stmt = $conn->prepare("SELECT * FROM calendar_settings WHERE user_id = :user_id AND google_calendar_enabled = 1");
        $stmt->bindParam(':user_id', $patient_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Get Google API credentials
            $stmt = $conn->prepare("SELECT * FROM apiintegration WHERE api_name = 'GoogleCalendar' AND is_active = 1");
            $stmt->execute();
            $google_api = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($google_api) {
                $credentials = json_decode($google_api['credentials'], true);
                
                // Call function to create Google Calendar event (implementation not shown)
                // $event_id = createGoogleCalendarEvent($credentials, $appointment_id, $appointment_datetime, $service, $dentist_id);
                
                // For demonstration purposes, we'll just set a dummy event ID
                $event_id = "google_" . time();
                
                // Update appointment with Google event ID
                $stmt = $conn->prepare("UPDATE appointment SET google_event_id = :event_id WHERE appointment_id = :appointment_id");
                $stmt->bindParam(':event_id', $event_id);
                $stmt->bindParam(':appointment_id', $appointment_id);
                $stmt->execute();
            }
        }
        
        // Commit the transaction
        $conn->commit();
        
        // Set success message
        $_SESSION['success'] = "Appointment booked successfully! The dentist will confirm your appointment soon.";
        header("Location: appointment-details.php?id=" . $appointment_id);
        exit();
        
    } catch(PDOException $e) {
        // Rollback the transaction if something failed
        $conn->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: patient-dashboard.php");
        exit();
    }
} else {
    // If not a POST request, redirect to dashboard
    header("Location: patient-dashboard.php");
    exit();
}