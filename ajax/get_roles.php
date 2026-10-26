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
    // Get all roles with user count
    $query = "SELECT r.role_id, r.role_name, COUNT(u.user_id) as user_count
              FROM userrole r
              LEFT JOIN user u ON r.role_id = u.role_id
              GROUP BY r.role_id
              ORDER BY r.role_id";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    $table = 'userrole';
    $details = 'Viewed all roles data';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $audit_stmt->execute();
    
    // Return success response with roles data
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $roles]);
    
} catch (PDOException $e) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}