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
    // Get audit trail records with user info
    $query = "SELECT a.*, u.first_name, u.last_name, u.username 
              FROM audittrail a
              JOIN user u ON a.user_id = u.user_id
              ORDER BY a.timestamp DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $audit_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log this view in the audit trail
    $audit_query = "INSERT INTO audittrail (user_id, action_performed, table_affected, details, ip_address) 
                    VALUES (:user_id, :action, :table, :details, :ip)";
    
    $audit_stmt = $db->prepare($audit_query);
    $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $audit_stmt->bindParam(':action', $action);
    $audit_stmt->bindParam(':table', $table);
    $audit_stmt->bindParam(':details', $details);
    $audit_stmt->bindParam(':ip', $ip_address);
    
    $action = 'VIEW';
    $table = 'audittrail';
    $details = 'Viewed full audit trail';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $audit_stmt->execute();
    
    // Return success response with audit trail data
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $audit_records]);
    
} catch (PDOException $e) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}