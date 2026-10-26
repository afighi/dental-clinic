<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if session has expired (optional)
$session_lifetime = 1800; // 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_lifetime)) {
    session_unset();
    session_destroy();
    header('Location: login.php?msg=session_expired');
    exit();
}
$_SESSION['last_activity'] = time();

// Function to check specific role access
function checkRole($allowed_roles) {
    if (!isset($_SESSION['role_name']) || !in_array($_SESSION['role_name'], $allowed_roles)) {
        header('Location: unauthorized.php');
        exit();
    }
}
?>