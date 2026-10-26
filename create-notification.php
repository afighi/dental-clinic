<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response = [
        'success' => false,
        'message' => 'Unauthorized access'
    ];
    echo json_encode($response);
    exit();
}

// Check if form was submitted with required parameters
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['user_id']) || !isset($_POST['title']) || !isset($_POST['message'])) {
    $response = [
        'success' => false,
        'message' => 'Invalid request'
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
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
    $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING) ?: 'general';
    $related_id = filter_input(INPUT_POST, 'related_id', FILTER_SANITIZE_NUMBER_INT) ?: null;
    
    // Validate user exists
    $stmt = $conn->prepare("SELECT user_id FROM user WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        throw new Exception('User not found');
    }
    
    // Create notification
    $stmt = $conn->prepare("INSERT INTO notification 
                          (user_id, title, message, notification_type, is_read, created_at, related_id) 
                          VALUES 
                          (:user_id, :title, :message, :type, 0, NOW(), :related_id)");
    
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':message', $message);
    $stmt->bindParam(':type', $type);
    $stmt->bindParam(':related_id', $related_id);
    $stmt->execute();
    
    $notification_id = $conn->lastInsertId();
    
    // Return success response
    $response = [
        'success' => true,
        'message' => 'Notification created successfully',
        'notification_id' => $notification_id
    ];
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit();