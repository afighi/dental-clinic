<?php
session_start();
include_once 'config/database.php';

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // Connect to database
    $database = new Database();
    $db = $database->getConnection();
    
    // Verify the user_id exists in the user table before logging to audit trail
    $check_user = "SELECT user_id FROM user WHERE user_id = :user_id";
    $user_stmt = $db->prepare($check_user);
    $user_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $user_stmt->execute();
    
    if ($user_stmt->rowCount() > 0) {
        // User exists, log the logout action in audit trail
        $audit_query = "INSERT INTO audittrail (user_id, action_performed, table_affected, details, ip_address) 
                       VALUES (:user_id, 'LOGOUT', 'user', 'User logged out', :ip_address)";
        $audit_stmt = $db->prepare($audit_query);
        $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $audit_stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
        $audit_stmt->execute();
    }
    // No need for an else clause - if the user doesn't exist, we just skip the audit logging
}

// Destroy all session data
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>