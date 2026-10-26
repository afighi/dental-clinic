<?php
session_start();
// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Check if required parameters are present
if (!isset($_POST['appointment_id']) || !isset($_POST['status'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

// Get parameters
$appointment_id = intval($_POST['appointment_id']);
$status = trim($_POST['status']);

// Validate status value
$valid_statuses = ['pending', 'confirmed', 'canceled', 'completed', 'cancelled'];
if (!in_array($status, $valid_statuses)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit();
}

// Map 'cancelled' to 'canceled' if needed for database consistency
if ($status === 'cancelled') {
    $status = 'canceled';
}

// Connect to database
require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

try {
    // Check that the appointment exists
    $check_stmt = $conn->prepare("SELECT * FROM appointment WHERE appointment_id = :appointment_id");
    $check_stmt->bindParam(':appointment_id', $appointment_id);
    $check_stmt->execute();
    $appointment = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit();
    }
    
    // Update the appointment status
    $update_stmt = $conn->prepare("UPDATE appointment SET status = :status, updated_at = NOW() WHERE appointment_id = :appointment_id");
    $update_stmt->bindValue(':status', $status, PDO::PARAM_STR);
    $update_stmt->bindValue(':appointment_id', $appointment_id, PDO::PARAM_INT);
    $result = $update_stmt->execute();
    
    if (!$result) {
        throw new PDOException("Failed to update status: " . implode(" ", $update_stmt->errorInfo()));
    }
    
    // Add notification for the patient - Using correct column names from schema
    $notification_text = "Your appointment status has been updated to " . ucfirst($status);
    $notif_stmt = $conn->prepare("INSERT INTO notification (user_id, notification_type, notification_message, related_entity_id, related_table) VALUES (:user_id, 'status_update', :notification_message, :related_entity_id, 'appointment')");
    $notif_stmt->bindValue(':user_id', $appointment['patient_id'], PDO::PARAM_INT);
    $notif_stmt->bindValue(':notification_message', $notification_text, PDO::PARAM_STR);
    $notif_stmt->bindValue(':related_entity_id', $appointment_id, PDO::PARAM_INT);
    $notif_stmt->execute();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Appointment status updated successfully']);
    
} catch (PDOException $e) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>