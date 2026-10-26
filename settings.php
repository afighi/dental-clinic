<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Initialize variables
$alert = '';
$alert_type = '';

// Check if required tables exist
$required_tables = ['notification_settings', 'calendar_settings', 'dentist_schedule', 'user_settings'];
$missing_tables = [];

foreach ($required_tables as $table) {
    $stmt = $conn->prepare("SHOW TABLES LIKE :table");
    $stmt->bindParam(':table', $table);
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        $missing_tables[] = $table;
    }
}

// Initialize alert variables
$alert = '';
$alert_type = '';

// Get user information
$stmt = $conn->prepare("SELECT * FROM user WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// If tables are missing, show installation message
if (!empty($missing_tables) && $_SESSION['role_id'] == 2) {
    $alert = "Some required database tables are missing. Please run the database setup script or contact the administrator.";
    $alert_type = "danger";
}

// ADMIN SECTION
if ($_SESSION['role_id'] == 1) { // Admin specific code
    // Get API integration settings
    $stmt = $conn->prepare("SELECT * FROM apiintegration ORDER BY api_id");
    $stmt->execute();
    $api_integrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process form submissions for admin
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Update API integration
        if (isset($_POST['update_api'])) {
            $api_id = $_POST['api_id'];
            $credentials = trim($_POST['credentials']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            try {
                $stmt = $conn->prepare("UPDATE apiintegration SET credentials = :credentials, is_active = :is_active WHERE api_id = :api_id");
                $stmt->bindParam(':credentials', $credentials);
                $stmt->bindParam(':is_active', $is_active);
                $stmt->bindParam(':api_id', $api_id);
                
                if ($stmt->execute()) {
                    // Log to audit trail
                    $audit_stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details) VALUES (:user_id, 'UPDATE', 'apiintegration', :details)");
                    $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $details = "Updated API integration settings for API ID: " . $api_id;
                    $audit_stmt->bindParam(':details', $details);
                    $audit_stmt->execute();
                    
                    $alert = "API integration settings updated successfully!";
                    $alert_type = "success";
                    
                    // Refresh API integration data
                    $stmt = $conn->prepare("SELECT * FROM apiintegration ORDER BY api_id");
                    $stmt->execute();
                    $api_integrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } catch (PDOException $e) {
                $alert = "Error: " . $e->getMessage();
                $alert_type = "danger";
            }
        }
        
        // Test API integration
        if (isset($_POST['test_api'])) {
            $api_id = $_POST['api_id'];
            
            // Get API details
            $stmt = $conn->prepare("SELECT * FROM apiintegration WHERE api_id = :api_id");
            $stmt->bindParam(':api_id', $api_id);
            $stmt->execute();
            $api = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $test_result = false;
            $test_message = '';
            
            // Run test based on API type
            switch ($api['api_name']) {
                case 'PHPMailer':
                    // Simple check for valid JSON credentials
                    $credentials = json_decode($api['credentials'], true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($credentials['email']) && isset($credentials['app_password'])) {
                        $test_result = true;
                        $test_message = "Email credentials structure is valid. Actual sending test not performed.";
                    } else {
                        $test_message = "Invalid email credentials format. Please ensure JSON contains 'email' and 'app_password' fields.";
                    }
                    break;
                    
                case 'GoogleCalendar':
                    // Simple check for valid JSON credentials
                    $credentials = json_decode($api['credentials'], true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($credentials['client_id']) && isset($credentials['client_secret'])) {
                        $test_result = true;
                        $test_message = "Google Calendar credentials structure is valid. Actual API connection test not performed.";
                    } else {
                        $test_message = "Invalid Google Calendar credentials format. Please ensure JSON contains 'client_id' and 'client_secret' fields.";
                    }
                    break;
                    
                case 'PayMongo':
                    // Simple check for valid JSON credentials
                    $credentials = json_decode($api['credentials'], true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($credentials['public_key']) && isset($credentials['secret_key'])) {
                        $test_result = true;
                        $test_message = "PayMongo credentials structure is valid. Actual API connection test not performed.";
                    } else {
                        $test_message = "Invalid PayMongo credentials format. Please ensure JSON contains 'public_key' and 'secret_key' fields.";
                    }
                    break;
                    
                default:
                    $test_message = "Unknown API type. No test performed.";
                    break;
            }
            
            // Set alert based on test result
            if ($test_result) {
                $alert = "API Test Successful: " . $test_message;
                $alert_type = "success";
            } else {
                $alert = "API Test Failed: " . $test_message;
                $alert_type = "danger";
            }
            
            // Log the test to audit trail
            $audit_stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details) VALUES (:user_id, 'TEST', 'apiintegration', :details)");
            $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $details = "Tested API integration: " . $api['api_name'] . ". Result: " . ($test_result ? "Success" : "Failed");
            $audit_stmt->bindParam(':details', $details);
            $audit_stmt->execute();
        }
        
        // Process form submission for database maintenance (admin only)
        if (isset($_POST['check_database'])) {
            $dbCheckResult = checkAndUpdateDatabaseSchema($conn);
            if ($dbCheckResult["success"]) {
                $alert = "Database schema check completed successfully.";
                $alert_type = "success";
            } else {
                $alert = "Database schema check failed: " . $dbCheckResult["error"];
                $alert_type = "danger";
            }
        }
    }
}

// DENTIST SECTION
else if ($_SESSION['role_id'] == 2 && empty($missing_tables)) { // Dentist specific code - only if tables exist
    // Process form submission for dentist
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Notification settings
        if (isset($_POST['update_notification_settings'])) {
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $appointment_reminders = isset($_POST['appointment_reminders']) ? 1 : 0;
            $treatment_updates = isset($_POST['treatment_updates']) ? 1 : 0;
            
            try {
                // Check if settings already exist
                $check_stmt = $conn->prepare("SELECT * FROM notification_settings WHERE user_id = :user_id");
                $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $check_stmt->execute();
                
                if ($check_stmt->rowCount() > 0) {
                    // Update existing settings
                    $stmt = $conn->prepare("UPDATE notification_settings SET 
                                           email_notifications = :email_notifications,
                                           appointment_reminders = :appointment_reminders,
                                           treatment_updates = :treatment_updates,
                                           updated_at = NOW()
                                           WHERE user_id = :user_id");
                } else {
                    // Create new settings
                    $stmt = $conn->prepare("INSERT INTO notification_settings 
                                           (user_id, email_notifications, appointment_reminders, treatment_updates, created_at) 
                                           VALUES 
                                           (:user_id, :email_notifications, :appointment_reminders, :treatment_updates, NOW())");
                }
                
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':email_notifications', $email_notifications);
                $stmt->bindParam(':appointment_reminders', $appointment_reminders);
                $stmt->bindParam(':treatment_updates', $treatment_updates);
                
                if ($stmt->execute()) {
                    // Log to audit trail
                    $audit_stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details) 
                                                VALUES (:user_id, :action, 'notification_settings', :details)");
                    $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $action = ($check_stmt->rowCount() > 0) ? 'UPDATE' : 'INSERT';
                    $audit_stmt->bindParam(':action', $action);
                    $details = "Updated notification settings";
                    $audit_stmt->bindParam(':details', $details);
                    $audit_stmt->execute();
                    
                    $alert = "Notification settings updated successfully!";
                    $alert_type = "success";
                }
            } catch (PDOException $e) {
                $alert = "Error: " . $e->getMessage();
                $alert_type = "danger";
            }
        }
        
        // Calendar integration settings
        if (isset($_POST['update_calendar_settings'])) {
            $google_calendar_enabled = isset($_POST['google_calendar_enabled']) ? 1 : 0;
            $default_reminder_time = $_POST['default_reminder_time'];
            
            try {
                // Check if settings already exist
                $check_stmt = $conn->prepare("SELECT * FROM calendar_settings WHERE user_id = :user_id");
                $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $check_stmt->execute();
                
                if ($check_stmt->rowCount() > 0) {
                    // Update existing settings
                    $stmt = $conn->prepare("UPDATE calendar_settings SET 
                                           google_calendar_enabled = :google_calendar_enabled,
                                           default_reminder_time = :default_reminder_time,
                                           updated_at = NOW()
                                           WHERE user_id = :user_id");
                } else {
                    // Create new settings
                    $stmt = $conn->prepare("INSERT INTO calendar_settings 
                                           (user_id, google_calendar_enabled, default_reminder_time, created_at) 
                                           VALUES 
                                           (:user_id, :google_calendar_enabled, :default_reminder_time, NOW())");
                }
                
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':google_calendar_enabled', $google_calendar_enabled);
                $stmt->bindParam(':default_reminder_time', $default_reminder_time);
                
                if ($stmt->execute()) {
                    // Log to audit trail
                    $audit_stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details) 
                                                VALUES (:user_id, :action, 'calendar_settings', :details)");
                    $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $action = ($check_stmt->rowCount() > 0) ? 'UPDATE' : 'INSERT';
                    $audit_stmt->bindParam(':action', $action);
                    $details = "Updated calendar settings";
                    $audit_stmt->bindParam(':details', $details);
                    $audit_stmt->execute();
                    
                    $alert = "Calendar settings updated successfully!";
                    $alert_type = "success";
                }
            } catch (PDOException $e) {
                $alert = "Error: " . $e->getMessage();
                $alert_type = "danger";
            }
        }
        
        // Working Hours settings
        if (isset($_POST['update_schedule_settings'])) {
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            
            try {
                // Begin transaction
                $conn->beginTransaction();
                
                // Delete existing schedule
                $delete_stmt = $conn->prepare("DELETE FROM dentist_schedule WHERE dentist_id = :dentist_id");
                $delete_stmt->bindParam(':dentist_id', $_SESSION['user_id']);
                $delete_stmt->execute();
                
                // Insert new schedule for each day
                foreach ($days as $day) {
                    $enabled = isset($_POST[$day . '_enabled']) ? 1 : 0;
                    
                    if ($enabled) {
                        $start_time = $_POST[$day . '_start'];
                        $end_time = $_POST[$day . '_end'];
                        
                        $stmt = $conn->prepare("INSERT INTO dentist_schedule 
                                               (dentist_id, day_of_week, start_time, end_time, is_available) 
                                               VALUES 
                                               (:dentist_id, :day_of_week, :start_time, :end_time, 1)");
                        $stmt->bindParam(':dentist_id', $_SESSION['user_id']);
                        $stmt->bindParam(':day_of_week', $day);
                        $stmt->bindParam(':start_time', $start_time);
                        $stmt->bindParam(':end_time', $end_time);
                        $stmt->execute();
                    }
                }
                
                // Log to audit trail
                $audit_stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details) 
                                            VALUES (:user_id, 'UPDATE', 'dentist_schedule', :details)");
                $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $details = "Updated working hours schedule";
                $audit_stmt->bindParam(':details', $details);
                $audit_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $alert = "Working hours updated successfully!";
                $alert_type = "success";
            } catch (PDOException $e) {
                // Roll back transaction on error
                $conn->rollBack();
                
                $alert = "Error: " . $e->getMessage();
                $alert_type = "danger";
            }
        }
    }

    // Initialize default settings
    $notification_settings = [
        'email_notifications' => 1,
        'appointment_reminders' => 1,
        'treatment_updates' => 1
    ];

    $calendar_settings = [
        'google_calendar_enabled' => 0,
        'default_reminder_time' => 60 // Default 60 minutes
    ];
    
    $schedule_settings = [];
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    
    foreach ($days as $day) {
        $schedule_settings[$day] = [
            'enabled' => false,
            'start_time' => '09:00',
            'end_time' => '17:00'
        ];
    }

    // Get actual settings from database if available
    try {
        // Get notification settings
        $stmt = $conn->prepare("SELECT * FROM notification_settings WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $notification_db = $stmt->fetch(PDO::FETCH_ASSOC);
            $notification_settings['email_notifications'] = $notification_db['email_notifications'];
            $notification_settings['appointment_reminders'] = $notification_db['appointment_reminders'];
            $notification_settings['treatment_updates'] = $notification_db['treatment_updates'];
        }

        // Get calendar settings
        $stmt = $conn->prepare("SELECT * FROM calendar_settings WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $calendar_db = $stmt->fetch(PDO::FETCH_ASSOC);
            $calendar_settings['google_calendar_enabled'] = $calendar_db['google_calendar_enabled'];
            $calendar_settings['default_reminder_time'] = $calendar_db['default_reminder_time'];
        }
        
        // Get schedule settings
        $stmt = $conn->prepare("SELECT * FROM dentist_schedule WHERE dentist_id = :dentist_id");
        $stmt->bindParam(':dentist_id', $_SESSION['user_id']);
        $stmt->execute();
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($schedules as $schedule) {
            $day = strtolower($schedule['day_of_week']);
            if (isset($schedule_settings[$day])) {
                $schedule_settings[$day]['enabled'] = $schedule['is_available'] == 1;
                $schedule_settings[$day]['start_time'] = $schedule['start_time'];
                $schedule_settings[$day]['end_time'] = $schedule['end_time'];
            }
        }
    } catch (PDOException $e) {
        // Silently fail and use defaults
    }
}

// PATIENT SECTION
else if ($_SESSION['role_id'] == 3) { // Patient specific code (changed from role_id == 3 to role_id == 4)
    // Get user settings if they exist
    $stmt = $conn->prepare("SELECT * FROM user WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    // Default settings if none exist
    if (!$settings) {
        $settings = [
            'email_notifications' => 1,
            'sms_notifications' => 0,
            'appointment_reminders' => 1,
            'marketing_emails' => 0,
            'dark_mode' => 0,
            'language' => 'en',
            'timezone' => 'Asia/Manila'
        ];
    }

    // Check if tables have required columns and add them if missing
    function checkAndUpdateDatabaseSchema($conn) {
        try {
            // Check if service table has is_active column
            $stmt = $conn->prepare("SHOW COLUMNS FROM service LIKE 'is_active'");
            $stmt->execute();
            $serviceHasIsActive = $stmt->fetch();
            
            if (!$serviceHasIsActive) {
                // Add is_active column to service table
                $stmt = $conn->prepare("ALTER TABLE service ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
                $stmt->execute();
                error_log("Added is_active column to service table");
            }
            
            // Check if user table has is_active column
            $stmt = $conn->prepare("SHOW COLUMNS FROM user LIKE 'is_active'");
            $stmt->execute();
            $userHasIsActive = $stmt->fetch();
            
            if (!$userHasIsActive) {
                // Add is_active column to user table
                $stmt = $conn->prepare("ALTER TABLE user ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
                $stmt->execute();
                error_log("Added is_active column to user table");
            }
            
            return ["success" => true];
        } catch (Exception $e) {
            error_log("Error updating database schema: " . $e->getMessage());
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    // Process form submissions for patient
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Notification settings
        if (isset($_POST['save_notifications'])) {
            try {
                // Get form data
                $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
                $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
                $appointment_reminders = isset($_POST['appointment_reminders']) ? 1 : 0;
                $marketing_emails = isset($_POST['marketing_emails']) ? 1 : 0;
                
                // Begin transaction
                $conn->beginTransaction();
                
                // Check if settings already exist
                $check_stmt = $conn->prepare("SELECT COUNT(*) FROM user_settings WHERE user_id = :user_id");
                $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $check_stmt->execute();
                
                if ($check_stmt->fetchColumn() > 0) {
                    // Update existing settings
                    $stmt = $conn->prepare("UPDATE user_settings 
                                        SET email_notifications = :email_notifications,
                                            sms_notifications = :sms_notifications,
                                            appointment_reminders = :appointment_reminders,
                                            marketing_emails = :marketing_emails,
                                            updated_at = NOW()
                                        WHERE user_id = :user_id");
                } else {
                    // Insert new settings
                    $stmt = $conn->prepare("INSERT INTO user_settings 
                                        (user_id, email_notifications, sms_notifications, appointment_reminders, 
                                            marketing_emails, dark_mode, language, timezone, created_at, updated_at)
                                        VALUES 
                                        (:user_id, :email_notifications, :sms_notifications, :appointment_reminders,
                                            :marketing_emails, 0, 'en', 'Asia/Manila', NOW(), NOW())");
                }
                
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':email_notifications', $email_notifications);
                $stmt->bindParam(':sms_notifications', $sms_notifications);
                $stmt->bindParam(':appointment_reminders', $appointment_reminders);
                $stmt->bindParam(':marketing_emails', $marketing_emails);
                $stmt->execute();
                
                // Add to audit trail
                $stmt = $conn->prepare("INSERT INTO audittrail 
                                    (user_id, action_performed, table_affected, details, timestamp) 
                                    VALUES 
                                    (:user_id, 'UPDATE', 'user_settings', :details, NOW())");
                
                $details = "Updated notification settings";
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':details', $details);
                $stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                // Update local settings array
                $settings['email_notifications'] = $email_notifications;
                $settings['sms_notifications'] = $sms_notifications;
                $settings['appointment_reminders'] = $appointment_reminders;
                $settings['marketing_emails'] = $marketing_emails;
                
                $alert = "Notification settings updated successfully!";
                $alert_type = "success";
                
            } catch (Exception $e) {
                // Rollback transaction on error
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                
                $alert = "Error updating settings: " . $e->getMessage();
                $alert_type = "danger";
            }
        }
        
        // Display settings
        if (isset($_POST['save_display'])) {
            try {
                // Get form data
                $dark_mode = isset($_POST['dark_mode']) ? 1 : 0;
                $language = filter_input(INPUT_POST, 'language', FILTER_SANITIZE_STRING);
                $timezone = filter_input(INPUT_POST, 'timezone', FILTER_SANITIZE_STRING);
                
                // Begin transaction
                $conn->beginTransaction();
                
                // Check if settings already exist
                $check_stmt = $conn->prepare("SELECT COUNT(*) FROM user_settings WHERE user_id = :user_id");
                $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $check_stmt->execute();
                
                if ($check_stmt->fetchColumn() > 0) {
                    // Update existing settings
                    $stmt = $conn->prepare("UPDATE user_settings 
                                        SET dark_mode = :dark_mode,
                                            language = :language,
                                            timezone = :timezone,
                                            updated_at = NOW()
                                        WHERE user_id = :user_id");
                } else {
                    // Insert new settings
                    $stmt = $conn->prepare("INSERT INTO user_settings 
                                        (user_id, email_notifications, sms_notifications, appointment_reminders, 
                                            marketing_emails, dark_mode, language, timezone, created_at, updated_at)
                                        VALUES 
                                        (:user_id, 1, 0, 1, 0, :dark_mode, :language, :timezone, NOW(), NOW())");
                }
                
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':dark_mode', $dark_mode);
                $stmt->bindParam(':language', $language);
                $stmt->bindParam(':timezone', $timezone);
                $stmt->execute();
                
                // Add to audit trail
                $stmt = $conn->prepare("INSERT INTO audittrail 
                                    (user_id, action_performed, table_affected, details, timestamp) 
                                    VALUES 
                                    (:user_id, 'UPDATE', 'user_settings', :details, NOW())");
                
                $details = "Updated display settings";
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':details', $details);
                $stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                // Update local settings array
                $settings['dark_mode'] = $dark_mode;
                $settings['language'] = $language;
                $settings['timezone'] = $timezone;
                
                $alert = "Display settings updated successfully!";
                $alert_type = "success";
                
            } catch (Exception $e) {
                // Rollback transaction on error
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                
                $alert = "Error updating settings: " . $e->getMessage();
                $alert_type = "danger";
            }
        }
        
        // Account deletion request
        if (isset($_POST['request_deletion'])) {
            try {
                // Begin transaction
                $conn->beginTransaction();
                
                // Create deletion request
                $stmt = $conn->prepare("INSERT INTO account_deletion_requests 
                                    (user_id, reason, status, created_at) 
                                    VALUES 
                                    (:user_id, :reason, 'pending', NOW())");
                
                $reason = filter_input(INPUT_POST, 'deletion_reason', FILTER_SANITIZE_STRING);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':reason', $reason);
                $stmt->execute();
                
                // Create notification for admin
                $stmt = $conn->prepare("INSERT INTO notification 
                                    (user_id, title, message, notification_type, is_read, created_at) 
                                    VALUES 
                                    (:user_id, :title, :message, 'account', 0, NOW())");
                
                // Get all admin user IDs
                $admin_stmt = $conn->prepare("SELECT user_id FROM user WHERE role_id = 1 AND is_active = 1");
                $admin_stmt->execute();
                $admins = $admin_stmt->fetchAll(PDO::FETCH_COLUMN);
                
                $title = "Account Deletion Request";
                $message = "Patient " . $user['first_name'] . " " . $user['last_name'] . " has requested account deletion. Reason: " . $reason;
                
                foreach ($admins as $admin_id) {
                    $stmt->bindParam(':user_id', $admin_id);
                    $stmt->bindParam(':title', $title);
                    $stmt->bindParam(':message', $message);
                    $stmt->execute();
                }
                
                // Add to audit trail
                $stmt = $conn->prepare("INSERT INTO audittrail 
                                    (user_id, action_performed, table_affected, details, timestamp) 
                                    VALUES 
                                    (:user_id, 'INSERT', 'account_deletion_requests', :details, NOW())");
                
                $details = "Requested account deletion";
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':details', $details);
                $stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $alert = "Account deletion request submitted. An administrator will review your request.";
                $alert_type = "warning";
                
            } catch (Exception $e) {
                // Rollback transaction on error
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                
                $alert = "Error submitting request: " . $e->getMessage();
                $alert_type = "danger";
            }
        }
    }

    // Get data for export if requested
    if (isset($_GET['export']) && $_GET['export'] === 'data') {
        try {
            // Begin building the export data
            $export_data = [
                'user_info' => $user,
                'appointments' => [],
                'treatments' => [],
                'payments' => []
            ];
            
            // Unset sensitive fields
            unset($export_data['user_info']['password_hash']);
            
            // Get appointments
            $stmt = $conn->prepare("SELECT a.*, s.service_name, u.first_name as dentist_fname, u.last_name as dentist_lname 
                                FROM appointment a 
                                JOIN service s ON a.service_id = s.service_id 
                                JOIN user u ON a.dentist_id = u.user_id 
                                WHERE a.patient_id = :patient_id");
            $stmt->bindParam(':patient_id', $_SESSION['user_id']);
            $stmt->execute();
            $export_data['appointments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get treatments
            $stmt = $conn->prepare("SELECT t.*, u.first_name as dentist_fname, u.last_name as dentist_lname 
                                FROM treatment t 
                                JOIN user u ON t.dentist_id = u.user_id 
                                WHERE t.patient_id = :patient_id");
            $stmt->bindParam(':patient_id', $_SESSION['user_id']);
            $stmt->execute();
            $export_data['treatments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get payments
            $stmt = $conn->prepare("SELECT p.*, a.appointment_date, s.service_name 
                                FROM payment p 
                                JOIN appointment a ON p.appointment_id = a.appointment_id 
                                JOIN service s ON a.service_id = s.service_id 
                                WHERE a.patient_id = :patient_id");
            $stmt->bindParam(':patient_id', $_SESSION['user_id']);
            $stmt->execute();
            $export_data['payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Set headers for JSON download
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="jrc_dental_data_export_' . date('Y-m-d') . '.json"');
            
            // Output JSON data
            echo json_encode($export_data, JSON_PRETTY_PRINT);
            exit;
        } catch (Exception $e) {
            // If error occurs, redirect back to settings page with error message
            $_SESSION['settings_alert'] = "Error exporting data: " . $e->getMessage();
            $_SESSION['settings_alert_type'] = "danger";
            header("Location: settings.php");
            exit;
        }
    }

    // Check for session alerts
    if (isset($_SESSION['settings_alert'])) {
        $alert = $_SESSION['settings_alert'];
        $alert_type = $_SESSION['settings_alert_type'];
        unset($_SESSION['settings_alert']);
        unset($_SESSION['settings_alert_type']);
    }
}    
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - JRC Dental Care</title>
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
                    <h3 class="mb-0">Settings</h3>
                </div>
                
                <?php if($alert): ?>
                <div class="alert alert-<?= $alert_type ?> alert-dismissible fade show" role="alert">
                    <?= $alert ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if (!empty($missing_tables) && $_SESSION['role_id'] == 2): ?>
                <!-- Missing Tables Alert -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Database Setup Required</h5>
                        <p>The following tables are missing from the database:</p>
                        <ul>
                            <?php foreach ($missing_tables as $table): ?>
                                <li><code><?= $table ?></code></li>
                            <?php endforeach; ?>
                        </ul>
                        <p>Please run the following SQL script to create the required tables:</p>
                        <div class="bg-light p-3 mb-3 rounded">
                            <pre class="mb-0" style="white-space: pre-wrap;"></pre>
                        </div>
                        <p>After creating these tables, refresh this page to access your dentist settings.</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- ADMIN SETTINGS SECTION -->
                <?php if ($_SESSION['role_id'] == 1): // Admin Settings ?>
                
                <!-- Settings Tabs -->
                <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="api-tab" data-bs-toggle="tab" data-bs-target="#api-tab-pane" type="button" role="tab" aria-controls="api-tab-pane" aria-selected="true">API Integrations</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system-tab-pane" type="button" role="tab" aria-controls="system-tab-pane" aria-selected="false">System Information</button>
                    </li>
                </ul>
                
                <div class="tab-content" id="settingsTabsContent">
                    <!-- API Integrations Tab -->
                    <div class="tab-pane fade show active" id="api-tab-pane" role="tabpanel" aria-labelledby="api-tab" tabindex="0">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">API Integrations</h5>
                            </div>
                            <div class="card-body">
                                <div class="accordion" id="apiAccordion">
                                    <?php foreach($api_integrations as $index => $api): ?>
                                    <div class="accordion-item border-0 mb-3">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button <?= $index !== 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $api['api_id'] ?>" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>" aria-controls="collapse<?= $api['api_id'] ?>">
                                                <div class="d-flex align-items-center w-100">
                                                    <div>
                                                        <?php 
                                                        $icon_class = '';
                                                        switch ($api['api_name']) {
                                                            case 'PHPMailer':
                                                                $icon_class = 'fas fa-envelope';
                                                                break;
                                                            case 'GoogleCalendar':
                                                                $icon_class = 'far fa-calendar-alt';
                                                                break;
                                                            case 'PayMongo':
                                                                $icon_class = 'fas fa-money-bill-wave';
                                                                break;
                                                            default:
                                                                $icon_class = 'fas fa-plug';
                                                                break;
                                                        }
                                                        ?>
                                                        <i class="<?= $icon_class ?> me-2"></i>
                                                        <?= htmlspecialchars($api['api_name']) ?> Integration
                                                    </div>
                                                    <div class="ms-auto me-3">
                                                        <span class="badge <?= $api['is_active'] ? 'bg-success' : 'bg-danger' ?>">
                                                            <?= $api['is_active'] ? 'Active' : 'Inactive' ?>
                                                        </span>
                                                    </div>
                                                    </div>
                                            </button>
                                        </h2>
                                        <div id="collapse<?= $api['api_id'] ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" data-bs-parent="#apiAccordion">
                                            <div class="accordion-body">
                                                <form method="post">
                                                    <input type="hidden" name="api_id" value="<?= $api['api_id'] ?>">
                                                    <div class="row g-3">
                                                        <div class="col-md-12">
                                                            <label for="credentials<?= $api['api_id'] ?>" class="form-label">API Credentials (JSON)</label>
                                                            <textarea class="form-control" id="credentials<?= $api['api_id'] ?>" name="credentials" rows="5"><?= htmlspecialchars($api['credentials']) ?></textarea>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" role="switch" id="is_active<?= $api['api_id'] ?>" name="is_active" <?= $api['is_active'] ? 'checked' : '' ?>>
                                                                <label class="form-check-label" for="is_active<?= $api['api_id'] ?>">Active</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <button type="submit" name="update_api" class="btn btn-primary me-2">Save Changes</button>
                                                            <button type="submit" name="test_api" class="btn btn-outline-secondary">Test Connection</button>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if($api['api_name'] === 'PHPMailer'): ?>
                                                    <div class="mt-4">
                                                        <div class="alert alert-info">
                                                            <h6 class="fw-bold">Required Format:</h6>
                                                            <pre class="mb-0">{"email": "your-email@gmail.com", "app_password": "your-app-password"}</pre>
                                                        </div>
                                                    </div>
                                                    <?php elseif($api['api_name'] === 'GoogleCalendar'): ?>
                                                    <div class="mt-4">
                                                        <div class="alert alert-info">
                                                            <h6 class="fw-bold">Required Format:</h6>
                                                            <pre class="mb-0">{"client_id": "your-client-id", "client_secret": "your-client-secret"}</pre>
                                                        </div>
                                                    </div>
                                                    <?php elseif($api['api_name'] === 'PayMongo'): ?>
                                                    <div class="mt-4">
                                                        <div class="alert alert-info">
                                                            <h6 class="fw-bold">Required Format:</h6>
                                                            <pre class="mb-0">{"public_key": "your-public-key", "secret_key": "your-secret-key"}</pre>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- System Information Tab -->
                    <div class="tab-pane fade" id="system-tab-pane" role="tabpanel" aria-labelledby="system-tab" tabindex="0">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">System Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="card border-0 shadow-sm h-100">
                                            <div class="card-body">
                                                <h6 class="fw-bold mb-3"><i class="fas fa-server me-2"></i> Server Information</h6>
                                                <div class="mb-2">
                                                    <strong>PHP Version:</strong> <?= phpversion() ?>
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Web Server:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?>
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Operating System:</strong> <?= php_uname('s') . ' ' . php_uname('r') ?>
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Database:</strong> MySQL
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card border-0 shadow-sm h-100">
                                            <div class="card-body">
                                                <h6 class="fw-bold mb-3"><i class="fas fa-info-circle me-2"></i> Application Information</h6>
                                                <div class="mb-2">
                                                    <strong>App Name:</strong> JRC Dental Care
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Version:</strong> 1.0.0
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Database Schema:</strong> jrc_dental_care
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Last Updated:</strong> <?= date('M d, Y') ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-body">
                                                <h6 class="fw-bold mb-3"><i class="fas fa-database me-2"></i> Database Statistics</h6>
                                                <div class="row">
                                                    <?php
                                                    $tables = [
                                                        'user' => 'Users',
                                                        'appointment' => 'Appointments',
                                                        'service' => 'Services',
                                                        'payment' => 'Payments',
                                                        'treatment' => 'Treatments',
                                                        'notification' => 'Notifications',
                                                        'audittrail' => 'Audit Logs'
                                                    ];
                                                    
                                                    foreach ($tables as $table => $display_name):
                                                        $count_stmt = $conn->prepare("SELECT COUNT(*) FROM $table");
                                                        $count_stmt->execute();
                                                        $count = $count_stmt->fetchColumn();
                                                    ?>
                                                    <div class="col-md-3 col-sm-6 mb-3">
                                                        <div class="border rounded p-3 text-center">
                                                            <h3 class="mb-0"><?= number_format($count) ?></h3>
                                                            <small class="text-muted"><?= $display_name ?></small>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- DENTIST SETTINGS SECTION -->
                <?php elseif ($_SESSION['role_id'] == 2 && empty($missing_tables)): // Dentist Settings (only if tables exist) ?>
                
                <!-- Settings Tabs -->
                <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab" aria-controls="notifications" aria-selected="true">
                            <i class="fas fa-bell me-2"></i>Notifications
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="calendar-tab" data-bs-toggle="tab" data-bs-target="#calendar" type="button" role="tab" aria-controls="calendar" aria-selected="false">
                            <i class="fas fa-calendar me-2"></i>Calendar
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule" type="button" role="tab" aria-controls="schedule" aria-selected="false">
                            <i class="fas fa-clock me-2"></i>Working Hours
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="settingsTabContent">
                    <!-- Notifications Settings -->
                    <div class="tab-pane fade show active" id="notifications" role="tabpanel" aria-labelledby="notifications-tab">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">Notification Preferences</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" <?= $notification_settings['email_notifications'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="email_notifications">
                                                <strong>Email Notifications</strong>
                                                <p class="text-muted small mb-0">Receive important notifications via email</p>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="appointment_reminders" name="appointment_reminders" <?= $notification_settings['appointment_reminders'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="appointment_reminders">
                                                <strong>Appointment Reminders</strong>
                                                <p class="text-muted small mb-0">Receive reminders about upcoming appointments</p>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="treatment_updates" name="treatment_updates" <?= $notification_settings['treatment_updates'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="treatment_updates">
                                                <strong>Treatment Updates</strong>
                                                <p class="text-muted small mb-0">Receive updates about patient treatments and notes</p>
                                            </label>
                                        </div>
                                    </div>
                                    <button type="submit" name="update_notification_settings" class="btn btn-primary">Save Notification Settings</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Calendar Settings -->
                    <div class="tab-pane fade" id="calendar" role="tabpanel" aria-labelledby="calendar-tab">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">Calendar Integration</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="google_calendar_enabled" name="google_calendar_enabled" <?= $calendar_settings['google_calendar_enabled'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="google_calendar_enabled">
                                                <strong>Google Calendar Integration</strong>
                                                <p class="text-muted small mb-0">Sync appointments with your Google Calendar</p>
                                            </label>
                                        </div>
                                    </div>
                                    <?php if (!$calendar_settings['google_calendar_enabled']): ?>
                                    <div class="mb-3 ms-4 google-calendar-connect" style="display: none;">
                                        <a href="#" class="btn btn-outline-primary btn-sm">
                                            <i class="fab fa-google me-2"></i>Connect Google Calendar
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                    <div class="mb-3">
                                        <label for="default_reminder_time" class="form-label">Default Reminder Time</label>
                                        <select class="form-select" id="default_reminder_time" name="default_reminder_time">
                                            <option value="15" <?= $calendar_settings['default_reminder_time'] == 15 ? 'selected' : '' ?>>15 minutes before</option>
                                            <option value="30" <?= $calendar_settings['default_reminder_time'] == 30 ? 'selected' : '' ?>>30 minutes before</option>
                                            <option value="60" <?= $calendar_settings['default_reminder_time'] == 60 ? 'selected' : '' ?>>1 hour before</option>
                                            <option value="120" <?= $calendar_settings['default_reminder_time'] == 120 ? 'selected' : '' ?>>2 hours before</option>
                                            <option value="1440" <?= $calendar_settings['default_reminder_time'] == 1440 ? 'selected' : '' ?>>1 day before</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="update_calendar_settings" class="btn btn-primary">Save Calendar Settings</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Schedule Settings -->
                    <div class="tab-pane fade" id="schedule" role="tabpanel" aria-labelledby="schedule-tab">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">Working Hours</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <div class="row g-3">
                                        <?php
                                        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                                        $display_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                        
                                        foreach ($days as $i => $day):
                                            $display_day = $display_days[$i];
                                        ?>
                                        <div class="col-md-12 mb-2">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="row align-items-center">
                                                        <div class="col-md-3">
                                                            <div class="form-check">
                                                                <input class="form-check-input day-toggle" type="checkbox" id="<?= $day ?>_enabled" name="<?= $day ?>_enabled" <?= $schedule_settings[$day]['enabled'] ? 'checked' : '' ?>>
                                                                <label class="form-check-label fw-bold" for="<?= $day ?>_enabled">
                                                                    <?= $display_day ?>
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-9">
                                                            <div class="row day-hours">
                                                                <div class="col-md-5">
                                                                    <div class="input-group input-group-sm">
                                                                        <span class="input-group-text">From</span>
                                                                        <input type="time" class="form-control" name="<?= $day ?>_start" value="<?= $schedule_settings[$day]['start_time'] ?>" <?= !$schedule_settings[$day]['enabled'] ? 'disabled' : '' ?>>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-5">
                                                                    <div class="input-group input-group-sm">
                                                                        <span class="input-group-text">To</span>
                                                                        <input type="time" class="form-control" name="<?= $day ?>_end" value="<?= $schedule_settings[$day]['end_time'] ?>" <?= !$schedule_settings[$day]['enabled'] ? 'disabled' : '' ?>>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-2">
                                                                    <button type="button" class="btn btn-sm btn-outline-primary add-break-btn" <?= !$schedule_settings[$day]['enabled'] ? 'disabled' : '' ?>>
                                                                        <i class="fas fa-plus me-1"></i> Break
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-3">
                                        <button type="submit" name="update_schedule_settings" class="btn btn-primary">Save Schedule Settings</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- PATIENT SETTINGS SECTION -->
                <?php elseif ($_SESSION['role_id'] == 4): // Patient Settings (changed from role_id == 3 to role_id == 4) ?>
                
                <!-- Settings Tabs -->
                <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab" aria-controls="notifications" aria-selected="true">
                            <i class="fas fa-bell me-2"></i>Notifications
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="display-tab" data-bs-toggle="tab" data-bs-target="#display" type="button" role="tab" aria-controls="display" aria-selected="false">
                            <i class="fas fa-desktop me-2"></i>Display Settings
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="privacy-tab" data-bs-toggle="tab" data-bs-target="#privacy" type="button" role="tab" aria-controls="privacy" aria-selected="false">
                            <i class="fas fa-shield-alt me-2"></i>Privacy & Data
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="export-tab" data-bs-toggle="tab" data-bs-target="#export" type="button" role="tab" aria-controls="export" aria-selected="false">
                            <i class="fas fa-download me-2"></i>Export Data
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="delete-tab" data-bs-toggle="tab" data-bs-target="#delete" type="button" role="tab" aria-controls="delete" aria-selected="false">
                            <i class="fas fa-trash-alt me-2 text-danger"></i>Delete Account
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="settingsTabContent">
                    <!-- Notifications Settings for Patient -->
                    <div class="tab-pane fade show active" id="notifications" role="tabpanel" aria-labelledby="notifications-tab">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header <?= (isset($settings['dark_mode']) && $settings['dark_mode'] == 1) ? 'bg-dark text-light' : 'bg-white' ?> py-3">
                                <h5 class="mb-0">Notification Preferences</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" <?= isset($settings['email_notifications']) && $settings['email_notifications'] == 1 ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="email_notifications">
                                            <strong>Email Notifications</strong>
                                            <p class="text-muted small mb-0">Receive notifications via email</p>
                                        </label>
                                    </div>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="sms_notifications" name="sms_notifications" <?= isset($settings['sms_notifications']) && $settings['sms_notifications'] == 1 ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="sms_notifications">
                                            <strong>SMS Notifications</strong>
                                            <p class="text-muted small mb-0">Receive notifications via SMS (standard rates may apply)</p>
                                        </label>
                                    </div>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="appointment_reminders" name="appointment_reminders" <?= isset($settings['appointment_reminders']) && $settings['appointment_reminders'] == 1 ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="appointment_reminders">
                                            <strong>Appointment Reminders</strong>
                                            <p class="text-muted small mb-0">Receive reminders before your scheduled appointments</p>
                                        </label>
                                    </div>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="marketing_emails" name="marketing_emails" <?= isset($settings['marketing_emails']) && $settings['marketing_emails'] == 1 ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="marketing_emails">
                                            <strong>Marketing Communications</strong>
                                            <p class="text-muted small mb-0">Receive updates about promotions, special offers, and dental care tips</p>
                                        </label>
                                    </div>
                                    <button type="submit" name="save_notifications" class="btn btn-primary">Save Changes</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Display Settings for Patient -->
                    <div class="tab-pane fade" id="display" role="tabpanel" aria-labelledby="display-tab">
                        <div class="card border-0 shadow-sm mb-4 <?= (isset($settings['dark_mode']) && $settings['dark_mode'] == 1) ? 'bg-dark text-light' : '' ?>">
                            <div class="card-header <?= (isset($settings['dark_mode']) && $settings['dark_mode'] == 1) ? 'bg-dark text-light' : 'bg-white' ?> py-3">
                                <h5 class="mb-0">Display Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="dark_mode" name="dark_mode" <?= isset($settings['dark_mode']) && $settings['dark_mode'] == 1 ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="dark_mode">
                                            <strong>Dark Mode</strong>
                                            <p class="text-muted small mb-0">Switch to dark theme for reduced eye strain in low light</p>
                                        </label>
                                    </div>
                                    <div class="mb-3">
                                        <label for="language" class="form-label"><strong>Language</strong></label>
                                        <select class="form-select" id="language" name="language">
                                            <option value="en" <?= (!isset($settings['language']) || $settings['language'] == 'en') ? 'selected' : '' ?>>English</option>
                                            <option value="fil" <?= (isset($settings['language']) && $settings['language'] == 'fil') ? 'selected' : '' ?>>Filipino</option>
                                            <option value="es" <?= (isset($settings['language']) && $settings['language'] == 'es') ? 'selected' : '' ?>>Spanish</option>
                                        </select>
                                        <div class="form-text text-muted">Changes the language used throughout the application</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="timezone" class="form-label"><strong>Timezone</strong></label>
                                        <select class="form-select" id="timezone" name="timezone">
                                            <option value="Asia/Manila" <?= (!isset($settings['timezone']) || $settings['timezone'] == 'Asia/Manila') ? 'selected' : '' ?>>Philippines (GMT+8)</option>
                                            <option value="Asia/Singapore" <?= (isset($settings['timezone']) && $settings['timezone'] == 'Asia/Singapore') ? 'selected' : '' ?>>Singapore (GMT+8)</option>
                                            <option value="America/Los_Angeles" <?= (isset($settings['timezone']) && $settings['timezone'] == 'America/Los_Angeles') ? 'selected' : '' ?>>Pacific Time (GMT-8)</option>
                                            <option value="America/New_York" <?= (isset($settings['timezone']) && $settings['timezone'] == 'America/New_York') ? 'selected' : '' ?>>Eastern Time (GMT-5)</option>
                                            <option value="Europe/London" <?= (isset($settings['timezone']) && $settings['timezone'] == 'Europe/London') ? 'selected' : '' ?>>London (GMT+0)</option>
                                        </select>
                                        <div class="form-text text-muted">Appointment times will be displayed in this timezone</div>
                                    </div>
                                    <button type="submit" name="save_display" class="btn btn-primary">Save Changes</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Privacy & Data for Patient -->
                    <div class="tab-pane fade" id="privacy" role="tabpanel" aria-labelledby="privacy-tab">
                        <div class="card border-0 shadow-sm mb-4 <?= (isset($settings['dark_mode']) && $settings['dark_mode'] == 1) ? 'bg-dark text-light' : '' ?>">
                            <div class="card-header <?= (isset($settings['dark_mode']) && $settings['dark_mode'] == 1) ? 'bg-dark text-light' : 'bg-white' ?> py-3">
                                <h5 class="mb-0">Privacy & Data</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-4">
                                    <h6>Data Collection & Usage</h6>
                                    <p class="text-muted small">JRC Dental Care collects and stores your personal information, appointment history, treatment records, and payment information to provide dental services. This data is used for appointment scheduling, treatment planning, communication, and billing purposes.</p>
                                </div>
                                <div class="mb-4">
                                    <h6>Data Sharing</h6>
                                    <p class="text-muted small">Your information may be shared with dentists and staff members involved in your care, and with third-party service providers as necessary for payment processing and appointment scheduling. We do not sell or share your data with external marketing companies.</p>
                                </div>
                                <div class="mb-4">
                                    <h6>Data Security</h6>
                                    <p class="text-muted small">We implement security measures to protect your data, including encryption, access controls, and regular security audits. All staff members are trained on proper data handling procedures.</p>
                                </div>
                                <div>
                                    <h6>Your Rights</h6>
                                    <p class="text-muted small">You have the right to access, correct, or delete your personal data. You can export your data below or request account deletion if needed.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Export Data for Patient -->
                    <div class="tab-pane fade" id="export" role="tabpanel" aria-labelledby="export-tab">
                        <div class="card border-0 shadow-sm mb-4 <?= (isset($settings['dark_mode']) && $settings['dark_mode'] == 1) ? 'bg-dark text-light' : '' ?>">
                            <div class="card-header <?= (isset($settings['dark_mode']) && $settings['dark_mode'] == 1) ? 'bg-dark text-light' : 'bg-white' ?> py-3">
                                <h5 class="mb-0">Export Your Data</h5>
                            </div>
                            <div class="card-body">
                                <p>You can download a copy of your personal data in JSON format. This includes:</p>
                                <ul>
                                    <li>Personal information (name, contact details)</li>
                                    <li>Appointment history</li>
                                    <li>Treatment records</li>
                                    <li>Payment information</li>
                                </ul>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> The export process may take a few moments depending on the amount of data.
                                </div>
                                <a href="settings.php?export=data" class="btn btn-primary">
                                    <i class="fas fa-download me-2"></i> Download My Data
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Delete Account for Patient -->
                    <div class="tab-pane fade" id="delete" role="tabpanel" aria-labelledby="delete-tab">
                        <div class="card border-0 shadow-sm mb-4 <?= (isset($settings['dark_mode']) && $settings['dark_mode'] == 1) ? 'bg-dark text-light' : '' ?>">
                            <div class="card-header <?= (isset($settings['dark_mode']) && $settings['dark_mode'] == 1) ? 'bg-dark text-light' : 'bg-white' ?> py-3">
                                <h5 class="mb-0 text-danger">Delete Account</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-danger">
                                    <h6><i class="fas fa-exclamation-triangle me-2"></i> Warning: This action cannot be undone</h6>
                                    <p class="mb-0">Deleting your account will remove all your personal information, appointment history, and settings from our system. You will no longer be able to access your account or any associated data.</p>
                                </div>
                                <p>Before requesting account deletion:</p>
                                <ul>
                                    <li>Export your data if you want to keep a copy</li>
                                    <li>Cancel any upcoming appointments</li>
                                    <li>Ensure you have no outstanding payments</li>
                                </ul>
                                <p>Please note that deletion requests are reviewed by an administrator and may take up to 30 days to process.</p>
                                
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                    <i class="fas fa-trash-alt me-2"></i> Request Account Deletion
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($_SESSION['role_id'] == 1): // Admin JS ?>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <?php endif; ?>
    <script src="assets/js/dashboard.js"></script>
    
    <?php if ($_SESSION['role_id'] == 2 && empty($missing_tables)): // Dentist JS ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle Google Calendar connect button visibility
            const googleCalendarToggle = document.getElementById('google_calendar_enabled');
            const googleCalendarConnect = document.querySelector('.google-calendar-connect');
            
            if (googleCalendarToggle && googleCalendarConnect) {
                googleCalendarToggle.addEventListener('change', function() {
                    googleCalendarConnect.style.display = this.checked ? 'block' : 'none';
                });
                
                // Initial state
                googleCalendarConnect.style.display = googleCalendarToggle.checked ? 'block' : 'none';
            }
            
            // Handle working hours day toggle
            document.querySelectorAll('.day-toggle').forEach(toggle => {
                toggle.addEventListener('change', function() {
                    const dayHours = this.closest('.card-body').querySelector('.day-hours');
                    dayHours.style.opacity = this.checked ? '1' : '0.5';
                    dayHours.querySelectorAll('input').forEach(input => {
                        input.disabled = !this.checked;
                    });
                    dayHours.querySelectorAll('button').forEach(button => {
                        button.disabled = !this.checked;
                    });
                });
                
                // Initial state
                if (toggle) {
                    const dayHours = toggle.closest('.card-body').querySelector('.day-hours');
                    if (dayHours) {
                        dayHours.style.opacity = toggle.checked ? '1' : '0.5';
                    }
                }
            });
            
            // Add Break button functionality
            document.querySelectorAll('.add-break-btn').forEach(button => {
                button.addEventListener('click', function() {
                    alert('Break functionality will be implemented in a future update.');
                });
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>