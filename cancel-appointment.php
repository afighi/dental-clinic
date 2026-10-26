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
    // Get appointment ID
    $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_SANITIZE_NUMBER_INT);
    $patient_id = $_SESSION['user_id'];
    
    // Validate input
    if (empty($appointment_id)) {
        $_SESSION['error'] = "Invalid appointment ID.";
        header("Location: my-appointments.php");
        exit();
    }
    
    // Check if appointment exists and belongs to the patient
    $stmt = $conn->prepare("SELECT a.*, u.first_name, u.last_name, s.service_name 
                          FROM appointment a 
                          JOIN user u ON a.dentist_id = u.user_id 
                          JOIN service s ON a.service_id = s.service_id
                          WHERE a.appointment_id = :appointment_id AND a.patient_id = :patient_id");
    $stmt->bindParam(':appointment_id', $appointment_id);
    $stmt->bindParam(':patient_id', $patient_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        $_SESSION['error'] = "Appointment not found or you don't have permission to cancel it.";
        header("Location: my-appointments.php");
        exit();
    }
    
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if appointment is already canceled
    if ($appointment['status'] == 'canceled') {
        $_SESSION['error'] = "This appointment is already canceled.";
        header("Location: appointment-details.php?id=" . $appointment_id);
        exit();
    }
    
    // Check if appointment is already completed
    if ($appointment['status'] == 'completed') {
        $_SESSION['error'] = "Completed appointments cannot be canceled.";
        header("Location: appointment-details.php?id=" . $appointment_id);
        exit();
    }
    
    // Check if appointment date is in the past
    $appointment_date = new DateTime($appointment['appointment_date']);
    $now = new DateTime();
    
    if ($appointment_date < $now) {
        $_SESSION['error'] = "Past appointments cannot be canceled.";
        header("Location: appointment-details.php?id=" . $appointment_id);
        exit();
    }
    
    // Calculate hours until appointment
    $diff = $now->diff($appointment_date);
    $hours_until_appointment = ($diff->days * 24) + $diff->h;
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Update appointment status
        $stmt = $conn->prepare("UPDATE appointment SET status = 'canceled', updated_at = NOW() WHERE appointment_id = :appointment_id");
        $stmt->bindParam(':appointment_id', $appointment_id);
        $stmt->execute();
        
        // Get patient info for notification
        $stmt = $conn->prepare("SELECT first_name, last_name FROM user WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $patient_id);
        $stmt->execute();
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Create notification for the dentist
        $notification_message = $patient['first_name'] . ' ' . $patient['last_name'] . ' has canceled the appointment for ' . 
                               $appointment['service_name'] . ' on ' . date('F j, Y \a\t g:i A', strtotime($appointment['appointment_date']));
        
        $stmt = $conn->prepare("INSERT INTO notification (user_id, notification_type, notification_message, related_entity_id, related_table, is_read, is_sent, send_via, created_at) 
                               VALUES (:dentist_id, 'appointment', :message, :appointment_id, 'appointment', 0, 1, 'email', NOW())");
        $stmt->bindParam(':dentist_id', $appointment['dentist_id']);
        $stmt->bindParam(':message', $notification_message);
        $stmt->bindParam(':appointment_id', $appointment_id);
        $stmt->execute();
        
        // Delete Google Calendar event if it exists
        if (!empty($appointment['google_event_id'])) {
            // Get Google API credentials
            $stmt = $conn->prepare("SELECT * FROM apiintegration WHERE api_name = 'GoogleCalendar' AND is_active = 1");
            $stmt->execute();
            $google_api = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($google_api) {
                $credentials = json_decode($google_api['credentials'], true);
                
                // Call function to delete Google Calendar event (implementation not shown)
                // deleteGoogleCalendarEvent($credentials, $appointment['google_event_id']);
                
                // For demonstration purposes, we'll just log the action
                error_log("Google Calendar event " . $appointment['google_event_id'] . " would be deleted here.");
            }
        }
        
        // Commit the transaction
        $conn->commit();
        
        // Set success message
        if ($hours_until_appointment < 24) {
            $_SESSION['success'] = "Your appointment has been canceled. Please note that cancellations within 24 hours may incur a cancellation fee.";
        } else {
            $_SESSION['success'] = "Your appointment has been canceled successfully.";
        }
        
        header("Location: my-appointments.php");
        exit();
        
    } catch(PDOException $e) {
        // Rollback the transaction if something failed
        $conn->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: appointment-details.php?id=" . $appointment_id);
        exit();
    }
} else {
    // If not a POST request, redirect to dashboard
    header("Location: patient-dashboard.php");
    exit();
}