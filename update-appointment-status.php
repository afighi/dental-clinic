<?php
session_start();
// Check if user is logged in and is a dentist
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
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
$valid_statuses = ['pending', 'confirmed', 'canceled', 'completed'];
if (!in_array($status, $valid_statuses)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit();
}

// Connect to database
require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

try {
    // First, check the structure of the status column in the appointment table
    $table_info_stmt = $conn->prepare("SHOW COLUMNS FROM appointment WHERE Field = 'status'");
    $table_info_stmt->execute();
    $column_info = $table_info_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug info to help diagnose the issue
    error_log("Status column info: " . print_r($column_info, true));
    
    // Verify that the appointment belongs to the logged-in dentist
    $check_stmt = $conn->prepare("SELECT dentist_id FROM appointment WHERE appointment_id = :appointment_id");
    $check_stmt->bindParam(':appointment_id', $appointment_id);
    $check_stmt->execute();
    $appointment = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit();
    }
    
    if ($appointment['dentist_id'] != $_SESSION['user_id']) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'You can only update your own appointments']);
        exit();
    }
    
    // Update the appointment status with explicit casting to match column type
    // Using ENUM values directly to avoid truncation issues
    $update_stmt = $conn->prepare("UPDATE appointment SET status = :status, updated_at = NOW() WHERE appointment_id = :appointment_id");
    $update_stmt->bindValue(':status', $status, PDO::PARAM_STR);
    $update_stmt->bindValue(':appointment_id', $appointment_id, PDO::PARAM_INT);
    $result = $update_stmt->execute();
    
    if (!$result) {
        throw new PDOException("Failed to update status: " . implode(" ", $update_stmt->errorInfo()));
    }
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Appointment status updated successfully']);
    
} catch (PDOException $e) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>