<?php
session_start();
// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Initialize variables
$alert = '';
$alert_type = '';

// Pagination variables
$users_per_page = 10;
$logs_per_page = 10;
$users_page = isset($_GET['users_page']) ? (int)$_GET['users_page'] : 1;
$logs_page = isset($_GET['logs_page']) ? (int)$_GET['logs_page'] : 1;
$users_offset = ($users_page - 1) * $users_per_page;
$logs_offset = ($logs_page - 1) * $logs_per_page;

// Function to generate a random password
function generateRandomPassword($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new user
    if (isset($_POST['add_user'])) {
        $first_name = trim($_POST['first_name']);
        $middle_name = trim($_POST['middle_name']);
        $last_name = trim($_POST['last_name']);
        $suffix = trim($_POST['suffix']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role_id = intval($_POST['role_id']);
        $phone_number = trim($_POST['phone_number']);
        $address = trim($_POST['address']);
        $date_of_birth = $_POST['date_of_birth'];
        
        // Generate a random password
        $plain_password = generateRandomPassword();
        $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);
        
        try {
            // Check if username or email already exists
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM user WHERE username = :username OR email = :email");
            $check_stmt->bindParam(':username', $username);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();
            
            if ($check_stmt->fetchColumn() > 0) {
                $alert = "Username or email already exists.";
                $alert_type = "danger";
            } else {
                // Insert new user
                $stmt = $conn->prepare("INSERT INTO user (first_name, middle_name, last_name, suffix, username, email, password_hash, role_id, phone_number, address, date_of_birth) VALUES (:first_name, :middle_name, :last_name, :suffix, :username, :email, :password_hash, :role_id, :phone_number, :address, :date_of_birth)");
                
                $stmt->bindParam(':first_name', $first_name);
                $stmt->bindParam(':middle_name', $middle_name);
                $stmt->bindParam(':last_name', $last_name);
                $stmt->bindParam(':suffix', $suffix);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password_hash', $password_hash);
                $stmt->bindParam(':role_id', $role_id);
                $stmt->bindParam(':phone_number', $phone_number);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':date_of_birth', $date_of_birth);
                
                if ($stmt->execute()) {
                    $user_id = $conn->lastInsertId();
                    
                    // Create related info record based on role
                    if ($role_id == 2) { // Dentist
                        $license_number = $_POST['license_number'];
                        $specialization = $_POST['specialization'];
                        
                        $info_stmt = $conn->prepare("INSERT INTO dentist_info (user_id, license_number, specialization) VALUES (:user_id, :license_number, :specialization)");
                        $info_stmt->bindParam(':user_id', $user_id);
                        $info_stmt->bindParam(':license_number', $license_number);
                        $info_stmt->bindParam(':specialization', $specialization);
                        $info_stmt->execute();
                    } else if ($role_id == 3) { // Staff
                        $job_title = $_POST['job_title'];
                        
                        $info_stmt = $conn->prepare("INSERT INTO staff_info (user_id, job_title) VALUES (:user_id, :job_title)");
                        $info_stmt->bindParam(':user_id', $user_id);
                        $info_stmt->bindParam(':job_title', $job_title);
                        $info_stmt->execute();
                    } else if ($role_id == 4) { // Patient
                        $emergency_contact_name = $_POST['emergency_contact_name'];
                        $emergency_contact_phone = $_POST['emergency_contact_phone'];
                        
                        $info_stmt = $conn->prepare("INSERT INTO patient_info (user_id, emergency_contact_name, emergency_contact_phone) VALUES (:user_id, :emergency_contact_name, :emergency_contact_phone)");
                        $info_stmt->bindParam(':user_id', $user_id);
                        $info_stmt->bindParam(':emergency_contact_name', $emergency_contact_name);
                        $info_stmt->bindParam(':emergency_contact_phone', $emergency_contact_phone);
                        $info_stmt->execute();
                    }
                    
                    // Log to audit trail
                    $audit_stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details) VALUES (:user_id, 'INSERT', 'user', :details)");
                    $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $details = "Added new user: " . $username . " with role ID: " . $role_id;
                    $audit_stmt->bindParam(':details', $details);
                    $audit_stmt->execute();
                    
                    // Send email notification with credentials
                    $stmt = $conn->prepare("SELECT * FROM apiintegration WHERE api_name = 'PHPMailer' AND is_active = 1");
                    $stmt->execute();
                    $emailConfig = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($emailConfig) {
                        $emailCredentials = json_decode($emailConfig['credentials'], true);
                        
                        // Create notification for email sending
                        $notification_message = "Your account has been created. Username: " . $username . ", Password: " . $plain_password;
                        $notification_stmt = $conn->prepare("INSERT INTO notification (user_id, notification_type, notification_message, related_entity_id, related_table, is_read, is_sent, send_via, scheduled_time) VALUES (:user_id, 'account_created', :notification_message, :related_entity_id, 'user', 0, 0, 'email', NOW())");
                        $notification_stmt->bindParam(':user_id', $user_id);
                        $notification_stmt->bindParam(':notification_message', $notification_message);
                        $notification_stmt->bindParam(':related_entity_id', $user_id);
                        $notification_stmt->execute();
                    }
                    
                    $alert = "User added successfully! Temporary password: " . $plain_password;
                    $alert_type = "success";
                }
            }
        } catch (PDOException $e) {
            $alert = "Error: " . $e->getMessage();
            $alert_type = "danger";
        }
    }
    
    // Update user
    if (isset($_POST['update_user'])) {
        $user_id = $_POST['user_id'];
        $first_name = trim($_POST['first_name']);
        $middle_name = trim($_POST['middle_name']);
        $last_name = trim($_POST['last_name']);
        $suffix = trim($_POST['suffix']);
        $email = trim($_POST['email']);
        $role_id = intval($_POST['role_id']);
        $phone_number = trim($_POST['phone_number']);
        $address = trim($_POST['address']);
        $date_of_birth = $_POST['date_of_birth'];
        
        try {
            // Get current user data for role change detection
            $current_stmt = $conn->prepare("SELECT role_id FROM user WHERE user_id = :user_id");
            $current_stmt->bindParam(':user_id', $user_id);
            $current_stmt->execute();
            $current_user = $current_stmt->fetch(PDO::FETCH_ASSOC);
            $old_role_id = $current_user['role_id'];
            
            // Update user
            $stmt = $conn->prepare("UPDATE user SET first_name = :first_name, middle_name = :middle_name, last_name = :last_name, suffix = :suffix, email = :email, role_id = :role_id, phone_number = :phone_number, address = :address, date_of_birth = :date_of_birth WHERE user_id = :user_id");
            
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':middle_name', $middle_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':suffix', $suffix);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':role_id', $role_id);
            $stmt->bindParam(':phone_number', $phone_number);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':date_of_birth', $date_of_birth);
            
            if ($stmt->execute()) {
                // Handle role change (create related info records if needed)
                if ($old_role_id != $role_id) {
                    // If changing to staff role, create staff_info
                    if ($role_id == 3 && $old_role_id != 3) {
                        // Check if staff record already exists
                        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM staff_info WHERE user_id = :user_id");
                        $check_stmt->bindParam(':user_id', $user_id);
                        $check_stmt->execute();
                        
                        if ($check_stmt->fetchColumn() == 0) {
                            $job_title = "Staff";
                            $info_stmt = $conn->prepare("INSERT INTO staff_info (user_id, job_title) VALUES (:user_id, :job_title)");
                            $info_stmt->bindParam(':user_id', $user_id);
                            $info_stmt->bindParam(':job_title', $job_title);
                            $info_stmt->execute();
                        }
                    }
                    
                    // If changing to dentist role, create dentist_info
                    if ($role_id == 2 && $old_role_id != 2) {
                        // Check if dentist record already exists
                        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM dentist_info WHERE user_id = :user_id");
                        $check_stmt->bindParam(':user_id', $user_id);
                        $check_stmt->execute();
                        
                        if ($check_stmt->fetchColumn() == 0) {
                            $license_number = "TBD-" . str_pad($user_id, 5, "0", STR_PAD_LEFT);
                            $specialization = "General Dentistry";
                            $info_stmt = $conn->prepare("INSERT INTO dentist_info (user_id, license_number, specialization) VALUES (:user_id, :license_number, :specialization)");
                            $info_stmt->bindParam(':user_id', $user_id);
                            $info_stmt->bindParam(':license_number', $license_number);
                            $info_stmt->bindParam(':specialization', $specialization);
                            $info_stmt->execute();
                        }
                    }
                    
                    // If changing to patient role, create patient_info
                    if ($role_id == 4 && $old_role_id != 4) {
                        // Check if patient record already exists
                        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM patient_info WHERE user_id = :user_id");
                        $check_stmt->bindParam(':user_id', $user_id);
                        $check_stmt->execute();
                        
                        if ($check_stmt->fetchColumn() == 0) {
                            $info_stmt = $conn->prepare("INSERT INTO patient_info (user_id) VALUES (:user_id)");
                            $info_stmt->bindParam(':user_id', $user_id);
                            $info_stmt->execute();
                        }
                    }
                    
                    // Send notification about role change
                    $stmt = $conn->prepare("SELECT role_name FROM userrole WHERE role_id = :role_id");
                    $stmt->bindParam(':role_id', $role_id);
                    $stmt->execute();
                    $new_role_name = $stmt->fetchColumn();
                    
                    $notification_message = "Your account role has been updated to " . $new_role_name;
                    $notification_stmt = $conn->prepare("INSERT INTO notification (user_id, notification_type, notification_message, related_entity_id, related_table, is_read, is_sent, send_via, scheduled_time) VALUES (:user_id, 'role_updated', :notification_message, :related_entity_id, 'user', 0, 0, 'email', NOW())");
                    $notification_stmt->bindParam(':user_id', $user_id);
                    $notification_stmt->bindParam(':notification_message', $notification_message);
                    $notification_stmt->bindParam(':related_entity_id', $user_id);
                    $notification_stmt->execute();
                }
                
                // Log to audit trail
                $audit_stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details) VALUES (:user_id, 'UPDATE', 'user', :details)");
                $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $details = "Updated user: " . $first_name . " " . $last_name;
                $audit_stmt->bindParam(':details', $details);
                $audit_stmt->execute();
                
                $alert = "User updated successfully!";
                $alert_type = "success";
            }
        } catch (PDOException $e) {
            $alert = "Error: " . $e->getMessage();
            $alert_type = "danger";
        }
    }
    
    // Reset Password
    if (isset($_POST['reset_password'])) {
        $user_id = $_POST['user_id'];
        
        // Generate a new random password
        $plain_password = generateRandomPassword();
        $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);
        
        try {
            // Get user info for notification
            $user_stmt = $conn->prepare("SELECT username, email FROM user WHERE user_id = :user_id");
            $user_stmt->bindParam(':user_id', $user_id);
            $user_stmt->execute();
            $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Update password
            $stmt = $conn->prepare("UPDATE user SET password_hash = :password_hash WHERE user_id = :user_id");
            $stmt->bindParam(':password_hash', $password_hash);
            $stmt->bindParam(':user_id', $user_id);
            
            if ($stmt->execute()) {
                // Create notification for password reset
                $notification_message = "Your password has been reset. New password: " . $plain_password;
                $notification_stmt = $conn->prepare("INSERT INTO notification (user_id, notification_type, notification_message, related_entity_id, related_table, is_read, is_sent, send_via, scheduled_time) VALUES (:user_id, 'password_reset', :notification_message, :related_entity_id, 'user', 0, 0, 'email', NOW())");
                $notification_stmt->bindParam(':user_id', $user_id);
                $notification_stmt->bindParam(':notification_message', $notification_message);
                $notification_stmt->bindParam(':related_entity_id', $user_id);
                $notification_stmt->execute();
                
                // Log to audit trail
                $audit_stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details) VALUES (:user_id, 'UPDATE', 'user', :details)");
                $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $details = "Reset password for user: " . $user_data['username'];
                $audit_stmt->bindParam(':details', $details);
                $audit_stmt->execute();
                
                $alert = "Password reset successfully! New temporary password: " . $plain_password;
                $alert_type = "success";
            }
        } catch (PDOException $e) {
            $alert = "Error: " . $e->getMessage();
            $alert_type = "danger";
        }
    }
}
$role_stmt = $conn->prepare("SELECT * FROM userrole");
$role_stmt->execute();
$roles = $role_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users with role names (modified search parameters)
$search = isset($_GET['search']) ? $_GET['search'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$show_tab = isset($_GET['tab']) ? $_GET['tab'] : 'users'; // Default to users tab

// Count total users for pagination
$count_query = "SELECT COUNT(*) FROM user u JOIN userrole r ON u.role_id = r.role_id WHERE 1=1";
$count_params = [];

if (!empty($search)) {
    // Split search into words
    $search_words = explode(' ', $search);
    
    if (count($search_words) > 1) {
        $count_query .= " AND (";
        $word_conditions = [];
        foreach ($search_words as $index => $word) {
            $param_name = ":word" . $index;
            $count_params[$param_name] = "%" . $word . "%";
            
            $field_conditions = [];
            $field_conditions[] = "u.first_name LIKE " . $param_name;
            $field_conditions[] = "u.last_name LIKE " . $param_name;
            $field_conditions[] = "u.middle_name LIKE " . $param_name;
            $field_conditions[] = "u.username LIKE " . $param_name;
            $field_conditions[] = "u.email LIKE " . $param_name;
            $field_conditions[] = "u.phone_number LIKE " . $param_name;
            $field_conditions[] = "u.address LIKE " . $param_name;
            $field_conditions[] = "r.role_name LIKE " . $param_name;
            
            $word_conditions[] = "(" . implode(" OR ", $field_conditions) . ")";
        }
        
        $count_params[':firstname'] = "%" . $search_words[0] . "%";
        $count_params[':lastname'] = "%" . $search_words[count($search_words)-1] . "%";
        $fullname_condition = "(u.first_name LIKE :firstname AND u.last_name LIKE :lastname)";
        
        $count_query .= $fullname_condition . " OR (" . implode(" AND ", $word_conditions) . "))";
    } else {
        $count_query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search 
                    OR u.username LIKE :search OR u.phone_number LIKE :search OR u.address LIKE :search
                    OR u.user_id LIKE :search OR r.role_name LIKE :search OR CONCAT(u.first_name, ' ', u.last_name) LIKE :search)";
        $search_param = "%$search%";
        $count_params[':search'] = $search_param;
    }
}

if (!empty($role_filter)) {
    $count_query .= " AND u.role_id = :role_id";
    $count_params[':role_id'] = $role_filter;
}

$count_stmt = $conn->prepare($count_query);
foreach ($count_params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_users = $count_stmt->fetchColumn();
$total_users_pages = ceil($total_users / $users_per_page);

// Query for users with pagination
$query = "SELECT u.user_id, u.first_name, u.last_name, u.username, u.email, 
          u.role_id, u.created_at, r.role_name 
          FROM user u JOIN userrole r ON u.role_id = r.role_id 
          WHERE 1=1 
          ORDER BY u.created_at DESC 
          LIMIT :limit OFFSET :offset";
$params = [];

if (!empty($search)) {
    // Split search into words
    $search_words = explode(' ', $search);
    
    if (count($search_words) > 1) {
        $query .= " AND (";
        
        $word_conditions = [];
        foreach ($search_words as $index => $word) {
            $param_name = ":word" . $index;
            $params[$param_name] = "%" . $word . "%";
            
            $field_conditions = [];
            $field_conditions[] = "u.first_name LIKE " . $param_name;
            $field_conditions[] = "u.last_name LIKE " . $param_name;
            $field_conditions[] = "u.middle_name LIKE " . $param_name;
            $field_conditions[] = "u.username LIKE " . $param_name;
            $field_conditions[] = "u.email LIKE " . $param_name;
            $field_conditions[] = "u.phone_number LIKE " . $param_name;
            $field_conditions[] = "u.address LIKE " . $param_name;
            $field_conditions[] = "r.role_name LIKE " . $param_name;
            
            $word_conditions[] = "(" . implode(" OR ", $field_conditions) . ")";
        }
        
        $params[':firstname'] = "%" . $search_words[0] . "%";
        $params[':lastname'] = "%" . $search_words[count($search_words)-1] . "%";
        $fullname_condition = "(u.first_name LIKE :firstname AND u.last_name LIKE :lastname)";
        
        $query .= $fullname_condition . " OR (" . implode(" AND ", $word_conditions) . "))";
    } else {
        $query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search 
                    OR u.username LIKE :search OR u.phone_number LIKE :search OR u.address LIKE :search
                    OR u.user_id LIKE :search OR r.role_name LIKE :search OR CONCAT(u.first_name, ' ', u.last_name) LIKE :search)";
        $search_param = "%$search%";
        $params[':search'] = $search_param;
    }
}

if (!empty($role_filter)) {
    $query .= " AND u.role_id = :role_id";
    $params[':role_id'] = $role_filter;
}

$query .= " ORDER BY u.created_at DESC LIMIT :limit OFFSET :offset";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $users_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $users_offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total logs for pagination
$log_count_query = "SELECT COUNT(*) FROM user_logs l LEFT JOIN user u ON l.user_id = u.user_id WHERE 1=1";
$log_count_params = [];

if (!empty($search)) {
    $search_words = explode(' ', $search);
    
    if (count($search_words) > 1) {
        $log_count_query .= " AND (";
        
        $log_word_conditions = [];
        foreach ($search_words as $index => $word) {
            $param_name = ":logword" . $index;
            $log_count_params[$param_name] = "%" . $word . "%";
            
            $field_conditions = [];
            $field_conditions[] = "u.first_name LIKE " . $param_name;
            $field_conditions[] = "u.last_name LIKE " . $param_name;
            $field_conditions[] = "u.username LIKE " . $param_name;
            $field_conditions[] = "u.email LIKE " . $param_name;
            $field_conditions[] = "l.action_type LIKE " . $param_name;
            $field_conditions[] = "l.log_id LIKE " . $param_name;
            $field_conditions[] = "l.log_timestamp LIKE " . $param_name;
            
            $log_word_conditions[] = "(" . implode(" OR ", $field_conditions) . ")";
        }
        
        $log_count_params[':logfirstname'] = "%" . $search_words[0] . "%";
        $log_count_params[':loglastname'] = "%" . $search_words[count($search_words)-1] . "%";
        $log_fullname_condition = "(u.first_name LIKE :logfirstname AND u.last_name LIKE :loglastname)";
        
        $log_count_query .= $log_fullname_condition . " OR (" . implode(" AND ", $log_word_conditions) . "))";
    } else {
        $log_count_query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search 
                      OR u.username LIKE :search OR u.email LIKE :search 
                      OR l.action_type LIKE :search OR l.log_id LIKE :search
                      OR l.log_timestamp LIKE :search OR CONCAT(u.first_name, ' ', u.last_name) LIKE :search)";
        $log_count_params[':search'] = "%$search%";
    }
}

$log_count_stmt = $conn->prepare($log_count_query);
foreach ($log_count_params as $key => $value) {
    $log_count_stmt->bindValue($key, $value);
}
$log_count_stmt->execute();
$total_logs = $log_count_stmt->fetchColumn();
$total_logs_pages = ceil($total_logs / $logs_per_page);

// Get user logs with enhanced search and pagination
$log_query = "SELECT l.*, u.first_name, u.last_name, u.username, u.email, u.role_id 
             FROM user_logs l 
             LEFT JOIN user u ON l.user_id = u.user_id 
             WHERE 1=1";
$log_params = [];

if (!empty($search)) {
    $search_words = explode(' ', $search);
    
    if (count($search_words) > 1) {
        $log_query .= " AND (";
        
        $log_word_conditions = [];
        foreach ($search_words as $index => $word) {
            $param_name = ":logword" . $index;
            $log_params[$param_name] = "%" . $word . "%";
            
            $field_conditions = [];
            $field_conditions[] = "u.first_name LIKE " . $param_name;
            $field_conditions[] = "u.last_name LIKE " . $param_name;
            $field_conditions[] = "u.username LIKE " . $param_name;
            $field_conditions[] = "u.email LIKE " . $param_name;
            $field_conditions[] = "l.action_type LIKE " . $param_name;
            $field_conditions[] = "l.log_id LIKE " . $param_name;
            $field_conditions[] = "l.log_timestamp LIKE " . $param_name;
            
            $log_word_conditions[] = "(" . implode(" OR ", $field_conditions) . ")";
        }
        
        $log_params[':logfirstname'] = "%" . $search_words[0] . "%";
        $log_params[':loglastname'] = "%" . $search_words[count($search_words)-1] . "%";
        $log_fullname_condition = "(u.first_name LIKE :logfirstname AND u.last_name LIKE :loglastname)";
        
        $log_query .= $log_fullname_condition . " OR (" . implode(" AND ", $log_word_conditions) . "))";
    } else {
        $log_query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search 
                      OR u.username LIKE :search OR u.email LIKE :search 
                      OR l.action_type LIKE :search OR l.log_id LIKE :search
                      OR l.log_timestamp LIKE :search OR CONCAT(u.first_name, ' ', u.last_name) LIKE :search)";
        $log_params[':search'] = "%$search%";
    }
}

$log_query .= " ORDER BY l.log_timestamp DESC LIMIT :limit OFFSET :offset";

$log_stmt = $conn->prepare($log_query);
foreach ($log_params as $key => $value) {
    $log_stmt->bindValue($key, $value);
}
$log_stmt->bindValue(':limit', $logs_per_page, PDO::PARAM_INT);
$log_stmt->bindValue(':offset', $logs_offset, PDO::PARAM_INT);
$log_stmt->execute();
$user_logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total results for both tabs
$total_results = $total_users + $total_logs;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - JRC Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">

</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Page Content -->
        <div id="page-content-wrapper">
            <!-- Navbar -->
            <?php include 'includes/navbar.php'; ?>
            
            <div class="container-fluid px-4 py-3">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="mb-0">User Management</h3>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-user-plus me-2"></i> Add New User
                    </button>
                </div>
                
                <?php if($alert): ?>
                <div class="alert alert-<?= $alert_type ?> alert-dismissible fade show" role="alert">
                    <?= $alert ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <!-- Filter and Search Options -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Search across all fields..." name="search" value="<?= htmlspecialchars($search) ?>">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                                <?php if (!empty($search)): ?>
                                <small class="text-muted mt-1">Found <?= $total_results ?> results (<?= $total_users ?> users, <?= $total_logs ?> logs)</small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" name="role" onchange="this.form.submit()">
                                    <option value="">All Roles</option>
                                    <?php foreach($roles as $role): ?>
                                    <option value="<?= $role['role_id'] ?>" <?= $role_filter == $role['role_id'] ? 'selected' : '' ?>>
                                        <?= $role['role_name'] ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <a href="users.php" class="btn btn-outline-secondary w-100">Reset</a>
                            </div>
                            <!-- Hidden fields to preserve state -->
                            <input type="hidden" name="tab" id="active_tab" value="<?= $show_tab ?>">
                            <input type="hidden" name="users_page" value="<?= $users_page ?>">
                            <input type="hidden" name="logs_page" value="<?= $logs_page ?>">
                        </form>
                    </div>
                </div>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" id="userTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= ($show_tab == 'users') ? 'active' : '' ?>" id="users-tab" data-bs-toggle="tab" data-bs-target="#users-tab-pane" type="button" role="tab" aria-controls="users-tab-pane" aria-selected="<?= ($show_tab == 'users') ? 'true' : 'false' ?>">
                            Users <?php if (!empty($search) && $total_users > 0): ?><span class="badge bg-primary"><?= $total_users ?></span><?php endif; ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= ($show_tab == 'logs') ? 'active' : '' ?>" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs-tab-pane" type="button" role="tab" aria-controls="logs-tab-pane" aria-selected="<?= ($show_tab == 'logs') ? 'true' : 'false' ?>">
                            User Logs <?php if (!empty($search) && $total_logs > 0): ?><span class="badge bg-primary"><?= $total_logs ?></span><?php endif; ?>
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="userTabsContent">
                    <!-- Users Tab -->
                    <div class="tab-pane fade <?= ($show_tab == 'users') ? 'show active' : '' ?>" id="users-tab-pane" role="tabpanel" aria-labelledby="users-tab" tabindex="0">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="usersTable">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Username</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($users as $user): ?>
                                            <tr>
                                                <td><?= $user['user_id'] ?></td>
                                                <td>
                                                    <?= htmlspecialchars($user['first_name']) ?> 
                                                    <?= htmlspecialchars($user['middle_name']) ?> 
                                                    <?= htmlspecialchars($user['last_name']) ?>
                                                    <?= htmlspecialchars($user['suffix']) ? htmlspecialchars($user['suffix']) : '' ?>
                                                </td>
                                                <td><?= htmlspecialchars($user['username']) ?></td>
                                                <td><?= htmlspecialchars($user['email']) ?></td>
                                                <td><span class="badge bg-primary"><?= htmlspecialchars($user['role_name']) ?></span></td>
                                                <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                onclick="editUser(<?= $user['user_id'] ?>)" 
                                                                data-bs-toggle="tooltip" 
                                                                title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-warning" 
                                                                onclick="resetPassword(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>')"
                                                                data-bs-toggle="tooltip" 
                                                                title="Reset Password">
                                                            <i class="fas fa-key"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Users Pagination -->
                                <?php if ($total_users_pages > 1): ?>
                                <nav aria-label="Users pagination">
                                    <ul class="pagination justify-content-center mt-4">
                                        <li class="page-item <?= $users_page <= 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?tab=users&search=<?= urlencode($search) ?>&role=<?= $role_filter ?>&users_page=<?= $users_page-1 ?>&logs_page=<?= $logs_page ?>">Previous</a>
                                        </li>
                                        <?php for ($i = 1; $i <= $total_users_pages; $i++): ?>
                                        <li class="page-item <?= $i == $users_page ? 'active' : '' ?>">
                                            <a class="page-link" href="?tab=users&search=<?= urlencode($search) ?>&role=<?= $role_filter ?>&users_page=<?= $i ?>&logs_page=<?= $logs_page ?>"><?= $i ?></a>
                                        </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= $users_page >= $total_users_pages ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?tab=users&search=<?= urlencode($search) ?>&role=<?= $role_filter ?>&users_page=<?= $users_page+1 ?>&logs_page=<?= $logs_page ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- User Logs Tab -->
                    <div class="tab-pane fade <?= ($show_tab == 'logs') ? 'show active' : '' ?>" id="logs-tab-pane" role="tabpanel" aria-labelledby="logs-tab" tabindex="0">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="logsTable">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>User</th>
                                                <th>Action</th>
                                                <th>Role</th>
                                                <th>Timestamp</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($user_logs as $log): ?>
                                            <tr>
                                                <td><?= $log['log_id'] ?></td>
                                                <td>
                                                    <?= htmlspecialchars($log['first_name']) ?> 
                                                    <?= htmlspecialchars($log['last_name']) ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $badge_class = '';
                                                    switch ($log['action_type']) {
                                                        case 'INSERT':
                                                            $badge_class = 'bg-success';
                                                            break;
                                                        case 'UPDATE':
                                                            $badge_class = 'bg-warning text-dark';
                                                            break;
                                                        case 'DELETE':
                                                            $badge_class = 'bg-danger';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?= $badge_class ?>"><?= $log['action_type'] ?></span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $role_stmt = $conn->prepare("SELECT role_name FROM userrole WHERE role_id = :role_id");
                                                    $role_stmt->bindParam(':role_id', $log['role_id']);
                                                    $role_stmt->execute();
                                                    $role_name = $role_stmt->fetchColumn();
                                                    ?>
                                                    <span class="badge bg-primary"><?= $role_name ?></span>
                                                </td>
                                                <td><?= date('M d, Y h:i A', strtotime($log['log_timestamp'])) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-info view-log-btn" 
                                                            data-id="<?= $log['log_id'] ?>"
                                                            data-action="<?= $log['action_type'] ?>"
                                                            data-username="<?= htmlspecialchars($log['username']) ?>"
                                                            data-email="<?= htmlspecialchars($log['email']) ?>"
                                                            data-timestamp="<?= date('M d, Y h:i A', strtotime($log['log_timestamp'])) ?>">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Logs Pagination -->
                                <?php if ($total_logs_pages > 1): ?>
                                <nav aria-label="Logs pagination">
                                    <ul class="pagination justify-content-center mt-4">
                                        <li class="page-item <?= $logs_page <= 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?tab=logs&search=<?= urlencode($search) ?>&role=<?= $role_filter ?>&users_page=<?= $users_page ?>&logs_page=<?= $logs_page-1 ?>">Previous</a>
                                        </li>
                                        <?php for ($i = 1; $i <= $total_logs_pages; $i++): ?>
                                        <li class="page-item <?= $i == $logs_page ? 'active' : '' ?>">
                                            <a class="page-link" href="?tab=logs&search=<?= urlencode($search) ?>&role=<?= $role_filter ?>&users_page=<?= $users_page ?>&logs_page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= $logs_page >= $total_logs_pages ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?tab=logs&search=<?= urlencode($search) ?>&role=<?= $role_filter ?>&users_page=<?= $users_page ?>&logs_page=<?= $logs_page+1 ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="middle_name" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="middle_name" name="middle_name">
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="suffix" class="form-label">Suffix</label>
                                <input type="text" class="form-control" id="suffix" name="suffix">
                            </div>
                            <div class="col-md-6">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phone_number" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="phone_number" name="phone_number">
                            </div>
                            <div class="col-md-6">
                                <label for="date_of_birth" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth">
                            </div>
                            <div class="col-md-12">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label for="role_id" class="form-label">Role</label>
                                <select class="form-select" id="role_id" name="role_id" required onchange="toggleRoleFields()">
                                    <?php foreach($roles as $role): ?>
                                    <option value="<?= $role['role_id'] ?>"><?= $role['role_name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Dentist-specific fields -->
                            <div class="col-md-6 role-field dentist-field" style="display: none;">
                                <label for="license_number" class="form-label">License Number</label>
                                <input type="text" class="form-control" id="license_number" name="license_number">
                            </div>
                            <div class="col-md-6 role-field dentist-field" style="display: none;">
                                <label for="specialization" class="form-label">Specialization</label>
                                <input type="text" class="form-control" id="specialization" name="specialization">
                            </div>
                            
                            
                            <!-- Patient-specific fields -->
                            <div class="col-md-6 role-field patient-field" style="display: none;">
                                <label for="emergency_contact_name" class="form-label">Emergency Contact Name</label>
                                <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name">
                            </div>
                            <div class="col-md-6 role-field patient-field" style="display: none;">
                                <label for="emergency_contact_phone" class="form-label">Emergency Contact Phone</label>
                                <input type="text" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" id="editUserForm">
                    <div class="modal-body">
                        <input type="hidden" id="edit_user_id" name="user_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="edit_first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_middle_name" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="edit_middle_name" name="middle_name">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_suffix" class="form-label">Suffix</label>
                                <input type="text" class="form-control" id="edit_suffix" name="suffix">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_phone_number" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="edit_phone_number" name="phone_number">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_date_of_birth" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="edit_date_of_birth" name="date_of_birth">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_role_id" class="form-label">Role</label>
                                <select class="form-select" id="edit_role_id" name="role_id" required>
                                    <?php foreach($roles as $role): ?>
                                    <option value="<?= $role['role_id'] ?>"><?= $role['role_name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label for="edit_address" class="form-label">Address</label>
                                <textarea class="form-control" id="edit_address" name="address" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetPasswordModalLabel">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" id="reset_user_id" name="user_id">
                        <p>Are you sure you want to reset the password for <span id="reset_user_name" class="fw-bold"></span>?</p>
                        <p>A new temporary password will be generated and sent to the user's email.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="reset_password" class="btn btn-warning">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Log Details Modal -->
    <div class="modal fade" id="viewLogModal" tabindex="-1" aria-labelledby="viewLogModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewLogModalLabel">Log Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <strong>Action Type:</strong> <span id="log_action_type" class="badge"></span>
                    </div>
                    <div class="mb-3">
                        <strong>Username:</strong> <span id="log_username"></span>
                    </div>
                    <div class="mb-3">
                        <strong>Email:</strong> <span id="log_email"></span>
                    </div>
                    <div class="mb-3">
                        <strong>Timestamp:</strong> <span id="log_timestamp"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script>
        function toggleRoleFields() {
            const roleId = document.getElementById('role_id').value;
            
            document.querySelectorAll('.role-field').forEach(field => {
                field.style.display = 'none';
            });
            
            if (roleId == 2) {
                document.querySelectorAll('.dentist-field').forEach(field => {
                    field.style.display = 'block';
                });
            } else if (roleId == 3) {
                document.querySelectorAll('.staff-field').forEach(field => {
                    field.style.display = 'block';
                });
            } else if (roleId == 4) {
                document.querySelectorAll('.patient-field').forEach(field => {
                    field.style.display = 'block';
                });
            }
        }

        function editUser(userId) {
            $.ajax({
                url: 'get_user.php',
                type: 'GET',
                data: { id: userId },
                dataType: 'json',
                success: function(data) {
                    $('#edit_user_id').val(data.user_id);
                    $('#edit_first_name').val(data.first_name);
                    $('#edit_middle_name').val(data.middle_name || '');
                    $('#edit_last_name').val(data.last_name);
                    $('#edit_suffix').val(data.suffix || '');
                    $('#edit_email').val(data.email);
                    $('#edit_phone_number').val(data.phone_number || '');
                    $('#edit_date_of_birth').val(data.date_of_birth || '');
                    $('#edit_address').val(data.address || '');
                    $('#edit_role_id').val(data.role_id);
                    
                    $('#editUserModal').modal('show');
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    alert('Error loading user data: ' + error);
                }
            });
        }

        function resetPassword(userId, userName) {
            $('#reset_user_id').val(userId);
            $('#reset_user_name').text(userName);
            $('#resetPasswordModal').modal('show');
        }

        $(document).ready(function() {
            toggleRoleFields();
            
            $('[data-bs-toggle="tooltip"]').each(function() {
                new bootstrap.Tooltip(this);
            });
            
            $(document).on('click', '.edit-btn', function(e) {
                e.preventDefault();
                var userId = $(this).data('id');
                editUser(userId);
            });
            
            $(document).on('click', '.reset-btn', function(e) {
                e.preventDefault();
                var userId = $(this).data('id');
                var userName = $(this).data('name');
                resetPassword(userId, userName);
            });
            
            $(document).on('click', '.view-log-btn', function(e) {
                e.preventDefault();
                
                var logId = $(this).data('id');
                var action = $(this).data('action');
                var username = $(this).data('username');
                var email = $(this).data('email');
                var timestamp = $(this).data('timestamp');
                
                $('#log_action_type').text(action);
                $('#log_username').text(username);
                $('#log_email').text(email);
                $('#log_timestamp').text(timestamp);
                
                var badgeElement = document.getElementById('log_action_type');
                badgeElement.className = 'badge';
                
                if (action === 'INSERT') {
                    badgeElement.classList.add('bg-success');
                } else if (action === 'UPDATE') {
                    badgeElement.classList.add('bg-warning', 'text-dark');
                } else if (action === 'DELETE') {
                    badgeElement.classList.add('bg-danger');
                }
                
                var viewLogModal = new bootstrap.Modal(document.getElementById('viewLogModal'));
                viewLogModal.show();
            });
            
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
                var tabId = $(e.target).attr('id');
                var activeTab = 'users';
                
                if (tabId === 'logs-tab') {
                    activeTab = 'logs';
                }
                
                $('#active_tab').val(activeTab);
            });
        });
    </script>
</body>
</html>