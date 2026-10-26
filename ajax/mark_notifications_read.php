<?php
session_start();
include_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Connect to database
$database = new Database();
$db = $database->getConnection();

try {
    // Mark all notifications as read for the current user
    $query = "UPDATE notification 
              SET is_read = 1 
              WHERE user_id = :user_id AND is_read = 0";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $count = $stmt->rowCount();
    
    // Log the activity in audit trail
    $audit_query = "INSERT INTO audittrail (user_id, action_performed, table_affected, details, ip_address) 
                    VALUES (:user_id, :action, :table, :details, :ip)";
    
    $audit_stmt = $db->prepare($audit_query);
    $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $audit_stmt->bindParam(':action', $action);
    $audit_stmt->bindParam(':table', $table);
    $audit_stmt->bindParam(':details', $details);
    $audit_stmt->bindParam(':ip', $ip_address);
    
    $action = 'UPDATE';
    $table = 'notification';
    $details = "Marked $count notifications as read";
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $audit_stmt->execute();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => "Marked $count notifications as read"]);
    
} catch (PDOException $e) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}