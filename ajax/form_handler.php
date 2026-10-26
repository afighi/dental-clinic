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

// Get the form type from the request
$form_type = isset($_POST['form_type']) ? $_POST['form_type'] : '';

// Process based on form type
switch ($form_type) {
    case 'add_user':
        handleAddUser($db);
        break;
    case 'edit_user':
        handleEditUser($db);
        break;
    case 'delete_user':
        handleDeleteUser($db);
        break;
    case 'add_role':
        handleAddRole($db);
        break;
    case 'edit_role':
        handleEditRole($db);
        break;
    case 'delete_role':
        handleDeleteRole($db);
        break;
    case 'generate_report':
        handleGenerateReport($db);
        break;
    case 'create_purchase_order':
        handleCreatePurchaseOrder($db);
        break;
    default:
        // Return error for unknown form type
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unknown form type']);
        break;
}

/**
 * Handle add user form submission
 */
function handleAddUser($db) {
    try {
        // Initialize action-related variables
        $action = 'INSERT';
        $table = 'user';
        $details = '';
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        // Get form data
        $first_name = $_POST['first_name'] ?? '';
        $middle_name = $_POST['middle_name'] ?? null;
        $last_name = $_POST['last_name'] ?? '';
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $phone_number = $_POST['phone_number'] ?? null;
        $address = $_POST['address'] ?? null;
        $role_id = $_POST['role_id'] ?? '';
        $send_credentials = isset($_POST['send_credentials']) ? true : false;
        
        // Basic validation
        if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password) || empty($role_id)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
            return;
        }
        
        // Check if passwords match
        if ($password !== $confirm_password) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
            return;
        }
        
        // Check if username already exists
        $check_query = "SELECT COUNT(*) FROM user WHERE username = :username";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':username', $username);
        $check_stmt->execute();
        
        if ($check_stmt->fetchColumn() > 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            return;
        }
        
        // Check if email already exists
        $check_query = "SELECT COUNT(*) FROM user WHERE email = :email";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->execute();
        
        if ($check_stmt->fetchColumn() > 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            return;
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Create verification token if sending credentials
        $verification_token = null;
        $verification_token_expires_at = null;
        
        if ($send_credentials) {
            $verification_token = bin2hex(random_bytes(32));
            $verification_token_expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
        }
        
        // Insert user into database
        $insert_query = "INSERT INTO user (first_name, middle_name, last_name, username, email, password, 
                                           phone_number, address, role_id, verification_token, verification_token_expires_at) 
                         VALUES (:first_name, :middle_name, :last_name, :username, :email, :password, 
                                 :phone_number, :address, :role_id, :verification_token, :verification_token_expires_at)";
        
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':first_name', $first_name);
        $insert_stmt->bindParam(':middle_name', $middle_name);
        $insert_stmt->bindParam(':last_name', $last_name);
        $insert_stmt->bindParam(':username', $username);
        $insert_stmt->bindParam(':email', $email);
        $insert_stmt->bindParam(':password', $hashed_password);
        $insert_stmt->bindParam(':phone_number', $phone_number);
        $insert_stmt->bindParam(':address', $address);
        $insert_stmt->bindParam(':role_id', $role_id);
        $insert_stmt->bindParam(':verification_token', $verification_token);
        $insert_stmt->bindParam(':verification_token_expires_at', $verification_token_expires_at);
        
        $insert_stmt->execute();
        $user_id = $db->lastInsertId();
        
        // If sending credentials, create notification
        if ($send_credentials && $user_id) {
            // Initialize the missing variables before use
            $type = 'ACCOUNT_CREATED';
            $message = 'Your account has been created. Please verify your email to activate your account.';
            
            $notification_query = "INSERT INTO notification (user_id, notification_type, notification_message, is_read, is_sent, send_via) 
                                  VALUES (:user_id, :type, :message, 0, 0, 'email')";
            
            $notification_stmt = $db->prepare($notification_query);
            $notification_stmt->bindParam(':user_id', $user_id);
            $notification_stmt->bindParam(':type', $type);
            $notification_stmt->bindParam(':message', $message);
            $notification_stmt->execute();
            
            // TODO: Send email with verification link in a real application
        }
        
        // Log the activity in audit trail
        $audit_query = "INSERT INTO audittrail (user_id, action_performed, table_affected, details, ip_address) 
                        VALUES (:user_id, :action, :table, :details, :ip)";
        
        $audit_stmt = $db->prepare($audit_query);
        $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $audit_stmt->bindParam(':action', $action);
        $audit_stmt->bindParam(':table', $table);
        $audit_stmt->bindParam(':details', $details);
        $audit_stmt->bindParam(':ip', $ip_address);
        
        $action = 'INSERT';
        $table = 'user';
        $details = "Created new user: $username (ID: $user_id)";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $audit_stmt->execute();
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'User created successfully',
            'reset' => true,
            'closeModal' => true,
            'reload' => true
        ]);
        
    } catch (PDOException $e) {
        // Return error response
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Handle edit user form submission
 */
function handleEditUser($db) {
    try {
        // Initialize action-related variables
        $action = 'UPDATE';
        $table = 'user';
        $details = '';
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        // Get form data
        $user_id = $_POST['user_id'] ?? '';
        $first_name = $_POST['first_name'] ?? '';
        $middle_name = $_POST['middle_name'] ?? null;
        $last_name = $_POST['last_name'] ?? '';
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone_number = $_POST['phone_number'] ?? null;
        $address = $_POST['address'] ?? null;
        $role_id = $_POST['role_id'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        
        // Basic validation
        if (empty($user_id) || empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($role_id)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
            return;
        }
        
        // Check if username already exists for another user
        $check_query = "SELECT COUNT(*) FROM user WHERE username = :username AND user_id != :user_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':username', $username);
        $check_stmt->bindParam(':user_id', $user_id);
        $check_stmt->execute();
        
        if ($check_stmt->fetchColumn() > 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            return;
        }
        
        // Check if email already exists for another user
        $check_query = "SELECT COUNT(*) FROM user WHERE email = :email AND user_id != :user_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->bindParam(':user_id', $user_id);
        $check_stmt->execute();
        
        if ($check_stmt->fetchColumn() > 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            return;
        }
        
        // Update user in database
        if (!empty($new_password)) {
            // Hash new password and update user with password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $update_query = "UPDATE user 
                             SET first_name = :first_name, 
                                 middle_name = :middle_name, 
                                 last_name = :last_name, 
                                 username = :username, 
                                 email = :email, 
                                 password = :password, 
                                 phone_number = :phone_number, 
                                 address = :address, 
                                 role_id = :role_id 
                             WHERE user_id = :user_id";
            
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':password', $hashed_password);
        } else {
            // Update user without changing password
            $update_query = "UPDATE user 
                             SET first_name = :first_name, 
                                 middle_name = :middle_name, 
                                 last_name = :last_name, 
                                 username = :username, 
                                 email = :email, 
                                 phone_number = :phone_number, 
                                 address = :address, 
                                 role_id = :role_id 
                             WHERE user_id = :user_id";
            
            $update_stmt = $db->prepare($update_query);
        }
        
        $update_stmt->bindParam(':first_name', $first_name);
        $update_stmt->bindParam(':middle_name', $middle_name);
        $update_stmt->bindParam(':last_name', $last_name);
        $update_stmt->bindParam(':username', $username);
        $update_stmt->bindParam(':email', $email);
        $update_stmt->bindParam(':phone_number', $phone_number);
        $update_stmt->bindParam(':address', $address);
        $update_stmt->bindParam(':role_id', $role_id);
        $update_stmt->bindParam(':user_id', $user_id);
        
        $update_stmt->execute();
        
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
        $table = 'user';
        $details = "Updated user: $username (ID: $user_id)";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $audit_stmt->execute();
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'User updated successfully',
            'closeModal' => true,
            'reload' => true
        ]);
        
    } catch (PDOException $e) {
        // Return error response
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Handle delete user form submission
 */
function handleDeleteUser($db) {
    try {
        // Initialize action-related variables
        $action = 'DELETE';
        $table = 'user';
        $details = '';
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        // Get user id
        $user_id = $_POST['user_id'] ?? '';
        
        // Basic validation
        if (empty($user_id)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'User ID is required']);
            return;
        }
        
        // Get user details for audit log
        $user_query = "SELECT username FROM user WHERE user_id = :user_id";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->bindParam(':user_id', $user_id);
        $user_stmt->execute();
        
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }
        
        // Delete related records first
        // These deletes would depend on your database structure and constraints
        // Check for cascade delete settings or manually delete related records
        
        // Delete user's notifications
        $delete_notifications = "DELETE FROM notification WHERE user_id = :user_id";
        $notification_stmt = $db->prepare($delete_notifications);
        $notification_stmt->bindParam(':user_id', $user_id);
        $notification_stmt->execute();
        
        // Delete user
        $delete_query = "DELETE FROM user WHERE user_id = :user_id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':user_id', $user_id);
        $delete_stmt->execute();
        
        // Log the activity in audit trail
        $audit_query = "INSERT INTO audittrail (user_id, action_performed, table_affected, details, ip_address) 
                        VALUES (:user_id, :action, :table, :details, :ip)";
        
        $audit_stmt = $db->prepare($audit_query);
        $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $audit_stmt->bindParam(':action', $action);
        $audit_stmt->bindParam(':table', $table);
        $audit_stmt->bindParam(':details', $details);
        $audit_stmt->bindParam(':ip', $ip_address);
        
        $action = 'DELETE';
        $table = 'user';
        $details = "Deleted user: " . $user['username'] . " (ID: $user_id)";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $audit_stmt->execute();
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'User deleted successfully',
            'reload' => true
        ]);
        
    } catch (PDOException $e) {
        // Return error response
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Handle add role form submission
 */
function handleAddRole($db) {
    try {
        // Initialize action-related variables
        $action = 'INSERT';
        $table = 'userrole';
        $details = '';
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        // Get form data
        $role_name = $_POST['role_name'] ?? '';
        
        // Basic validation
        if (empty($role_name)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Role name is required']);
            return;
        }
        
        // Check if role already exists
        $check_query = "SELECT COUNT(*) FROM userrole WHERE role_name = :role_name";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':role_name', $role_name);
        $check_stmt->execute();
        
        if ($check_stmt->fetchColumn() > 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Role already exists']);
            return;
        }
        
        // Insert role into database
        $insert_query = "INSERT INTO userrole (role_name) VALUES (:role_name)";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':role_name', $role_name);
        $insert_stmt->execute();
        
        $role_id = $db->lastInsertId();
        
        // Log the activity in audit trail
        $audit_query = "INSERT INTO audittrail (user_id, action_performed, table_affected, details, ip_address) 
                        VALUES (:user_id, :action, :table, :details, :ip)";
        
        $audit_stmt = $db->prepare($audit_query);
        $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $audit_stmt->bindParam(':action', $action);
        $audit_stmt->bindParam(':table', $table);
        $audit_stmt->bindParam(':details', $details);
        $audit_stmt->bindParam(':ip', $ip_address);
        
        $action = 'INSERT';
        $table = 'userrole';
        $details = "Created new role: $role_name (ID: $role_id)";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $audit_stmt->execute();
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Role created successfully',
            'reset' => true,
            'closeModal' => true,
            'reload' => true
        ]);
        
    } catch (PDOException $e) {
        // Return error response
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Handle edit role form submission
 */
function handleEditRole($db) {
    try {
        // Initialize action-related variables
        $action = 'UPDATE';
        $table = 'userrole';
        $details = '';
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        // Get form data
        $role_id = $_POST['role_id'] ?? '';
        $role_name = $_POST['role_name'] ?? '';
        
        // Basic validation
        if (empty($role_id) || empty($role_name)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Role ID and name are required']);
            return;
        }
        
        // Check if role already exists for another ID
        $check_query = "SELECT COUNT(*) FROM userrole WHERE role_name = :role_name AND role_id != :role_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':role_name', $role_name);
        $check_stmt->bindParam(':role_id', $role_id);
        $check_stmt->execute();
        
        if ($check_stmt->fetchColumn() > 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Role name already exists']);
            return;
        }
        
        // Update role in database
        $update_query = "UPDATE userrole SET role_name = :role_name WHERE role_id = :role_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':role_name', $role_name);
        $update_stmt->bindParam(':role_id', $role_id);
        $update_stmt->execute();
        
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
        $table = 'userrole';
        $details = "Updated role: $role_name (ID: $role_id)";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $audit_stmt->execute();
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Role updated successfully',
            'closeModal' => true,
            'reload' => true
        ]);
        
    } catch (PDOException $e) {
        // Return error response
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Handle delete role form submission
 */
function handleDeleteRole($db) {
    try {
        // Initialize action-related variables
        $action = 'DELETE';
        $table = 'userrole';
        $details = '';
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        // Get role id
        $role_id = $_POST['role_id'] ?? '';
        
        // Basic validation
        if (empty($role_id)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Role ID is required']);
            return;
        }
        
        // Check if any users are assigned to this role
        $check_query = "SELECT COUNT(*) FROM user WHERE role_id = :role_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':role_id', $role_id);
        $check_stmt->execute();
        
        if ($check_stmt->fetchColumn() > 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Cannot delete role because users are assigned to it']);
            return;
        }
        
        // Get role name for audit log
        $role_query = "SELECT role_name FROM userrole WHERE role_id = :role_id";
        $role_stmt = $db->prepare($role_query);
        $role_stmt->bindParam(':role_id', $role_id);
        $role_stmt->execute();
        
        $role = $role_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$role) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Role not found']);
            return;
        }
        
        // Delete role
        $delete_query = "DELETE FROM userrole WHERE role_id = :role_id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':role_id', $role_id);
        $delete_stmt->execute();
        
        // Log the activity in audit trail
        $audit_query = "INSERT INTO audittrail (user_id, action_performed, table_affected, details, ip_address) 
                        VALUES (:user_id, :action, :table, :details, :ip)";
        
        $audit_stmt = $db->prepare($audit_query);
        $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $audit_stmt->bindParam(':action', $action);
        $audit_stmt->bindParam(':table', $table);
        $audit_stmt->bindParam(':details', $details);
        $audit_stmt->bindParam(':ip', $ip_address);
        
        $action = 'DELETE';
        $table = 'userrole';
        $details = "Deleted role: " . $role['role_name'] . " (ID: $role_id)";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $audit_stmt->execute();
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Role deleted successfully',
            'reload' => true
        ]);
        
    } catch (PDOException $e) {
        // Return error response
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Handle generate report form submission
 */
function handleGenerateReport($db) {
    try {
        // Initialize action-related variables
        $action = 'GENERATE';
        $table = 'report';
        $details = '';
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        // Get form data
        $report_type = $_POST['report_type'] ?? '';
        $date_range = $_POST['date_range'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $format_type = $_POST['format_type'] ?? '';
        
        // Basic validation
        if (empty($report_type) || empty($date_range) || empty($format_type)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
            return;
        }
        
        // Validate custom date range if selected
        if ($date_range === 'custom' && (empty($start_date) || empty($end_date))) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Start date and end date are required for custom range']);
            return;
        }
        
        // Calculate date range based on selection
        $from_date = '';
        $to_date = '';
        
        switch ($date_range) {
            case 'today':
                $from_date = date('Y-m-d 00:00:00');
                $to_date = date('Y-m-d 23:59:59');
                break;
            case 'this_week':
                $from_date = date('Y-m-d 00:00:00', strtotime('monday this week'));
                $to_date = date('Y-m-d 23:59:59', strtotime('sunday this week'));
                break;
            case 'this_month':
                $from_date = date('Y-m-01 00:00:00');
                $to_date = date('Y-m-t 23:59:59');
                break;
            case 'last_month':
                $from_date = date('Y-m-01 00:00:00', strtotime('first day of last month'));
                $to_date = date('Y-m-t 23:59:59', strtotime('last day of last month'));
                break;
            case 'this_year':
                $from_date = date('Y-01-01 00:00:00');
                $to_date = date('Y-12-31 23:59:59');
                break;
            case 'custom':
                $from_date = date('Y-m-d 00:00:00', strtotime($start_date));
                $to_date = date('Y-m-d 23:59:59', strtotime($end_date));
                break;
        }
        
        // Generate a unique filename for the report
        $filename = $report_type . '_' . date('Ymd_His') . '.' . $format_type;
        $file_path = '../reports/' . $filename;
        
        // Insert record into reports table
        $insert_query = "INSERT INTO report (report_name, report_type, generated_by, file_path, parameters) 
                         VALUES (:report_name, :report_type, :generated_by, :file_path, :parameters)";
        
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':report_name', $filename);
        $insert_stmt->bindParam(':report_type', $report_type);
        $insert_stmt->bindParam(':generated_by', $_SESSION['user_id']);
        $insert_stmt->bindParam(':file_path', $file_path);
        
        // Store parameters as JSON
        $parameters = json_encode([
            'date_range' => $date_range,
            'from_date' => $from_date,
            'to_date' => $to_date,
            'format' => $format_type
        ]);
        
        $insert_stmt->bindParam(':parameters', $parameters);
        $insert_stmt->execute();
        
        $report_id = $db->lastInsertId();
        
        // Log the activity in audit trail
        $audit_query = "INSERT INTO audittrail (user_id, action_performed, table_affected, details, ip_address) 
                        VALUES (:user_id, :action, :table, :details, :ip)";
        
        $audit_stmt = $db->prepare($audit_query);
        $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $audit_stmt->bindParam(':action', $action);
        $audit_stmt->bindParam(':table', $table);
        $audit_stmt->bindParam(':details', $details);
        $audit_stmt->bindParam(':ip', $ip_address);
        
        $action = 'GENERATE';
        $table = 'report';
        $details = "Generated report: $report_type (ID: $report_id)";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $audit_stmt->execute();
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Report generated successfully',
            'download_url' => 'download_report.php?id=' . $report_id,
            'closeModal' => true
        ]);
        
    } catch (PDOException $e) {
        // Return error response
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Handle create purchase order form submission
 */
function handleCreatePurchaseOrder($db) {
    try {
        // Initialize action-related variables
        $action = 'INSERT';
        $table = 'purchase_order';
        $details = '';
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        // Get form data
        $supplier_id = $_POST['supplier_id'] ?? '';
        $expected_delivery = $_POST['expected_delivery'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];
        
        // Basic validation
        if (empty($supplier_id) || empty($expected_delivery) || empty($items)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
            return;
        }
        
        // Calculate total amount
        $total_amount = 0;
        foreach ($items as $item) {
            $total_amount += $item['quantity'] * $item['unit_cost'];
        }
        
        // Start transaction
        $db->beginTransaction();
        
        // Insert purchase order
        $insert_query = "INSERT INTO purchase_order (supplier_id, user_id, order_date, status, total_amount, expected_delivery, notes, order_items) 
                         VALUES (:supplier_id, :user_id, NOW(), 'Pending', :total_amount, :expected_delivery, :notes, :order_items)";
        
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':supplier_id', $supplier_id);
        $insert_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $insert_stmt->bindParam(':total_amount', $total_amount);
        $insert_stmt->bindParam(':expected_delivery', $expected_delivery);
        $insert_stmt->bindParam(':notes', $notes);
        
        // Store items as JSON
        $order_items_json = json_encode($items);
        $insert_stmt->bindParam(':order_items', $order_items_json);
        
        $insert_stmt->execute();
        $po_id = $db->lastInsertId();
        
        // Log the activity in audit trail
        $audit_query = "INSERT INTO audittrail (user_id, action_performed, table_affected, details, ip_address) 
                        VALUES (:user_id, :action, :table, :details, :ip)";
        
        $audit_stmt = $db->prepare($audit_query);
        $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $audit_stmt->bindParam(':action', $action);
        $audit_stmt->bindParam(':table', $table);
        $audit_stmt->bindParam(':details', $details);
        $audit_stmt->bindParam(':ip', $ip_address);
        
        $action = 'INSERT';
        $table = 'purchase_order';
        $details = "Created purchase order #$po_id for $" . number_format($total_amount, 2);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $audit_stmt->execute();
        
        // Commit transaction
        $db->commit();
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Purchase order created successfully',
            'po_id' => $po_id,
            'reset' => true,
            'closeModal' => true,
            'reload' => true
        ]);
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        $db->rollBack();
        
        // Return error response
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
