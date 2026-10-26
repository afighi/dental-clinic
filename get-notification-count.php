<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit();
}

// Include database connection
require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

try {
    // Get count of unread notifications
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notification WHERE user_id = :user_id AND is_read = 0");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $count = $stmt->fetchColumn();
    
    echo json_encode(['count' => $count]);
    
} catch (Exception $e) {
    echo json_encode(['count' => 0]);
}