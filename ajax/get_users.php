<?php
session_start();
include_once '../config/database.php';

// Check if user is logged in with admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Connect to database
$database = new Database();
$db = $database->getConnection();

try {
    // Get all users with role information
    $query = "SELECT u.user_id, u.first_name, u.middle_name, u.last_name, 
                     u.username, u.email, u.phone_number, u.email_verified, u.created_at,
                     r.role_name
              FROM user u
              JOIN userrole r ON u.role_id = r.role_id
              ORDER BY u.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log the audit trail for this activity
    $audit_query = "INSERT INTO audittrail (user_id, action_performed, table_affected, details, ip_address) 
                    VALUES (:user_id, :action, :table, :details, :ip)";
    
    $audit_stmt = $db->prepare($audit_query);
    $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $audit_stmt->bindParam(':action', $action);
    $audit_stmt->bindParam(':table', $table);
    $audit_stmt->bindParam(':details', $details);
    $audit_stmt->bindParam(':ip', $ip_address);
    
    $action = 'VIEW';
    $table = 'user';
    $details = 'Viewed all users data';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $audit_stmt->execute();
    
    // Return success response with users data
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $users]);
    
} catch (PDOException $e) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}