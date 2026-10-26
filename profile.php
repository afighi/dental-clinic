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
$editMode = false;
$emailOtpMode = false;

// Hard-code user data for display
$userData = array(
    'username' => isset($_SESSION['username']) ? $_SESSION['username'] : 'Not available',
    'email' => isset($_SESSION['email']) ? $_SESSION['email'] : 'Not available',
    'first_name' => isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'User',
    'last_name' => isset($_SESSION['last_name']) ? $_SESSION['last_name'] : '',
    'phone_number' => 'Not set',
    'address' => 'Not set',
    'date_of_birth' => '',
    'role_id' => isset($_SESSION['role_id']) ? $_SESSION['role_id'] : 1,
    'role_name' => isset($_SESSION['role_name']) ? $_SESSION['role_name'] : 'User'
);

// Try to fetch additional data from database if we need it
try {
    $stmt = $conn->prepare("SELECT * FROM user WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        if (!empty($result['phone_number'])) {
            $userData['phone_number'] = $result['phone_number'];
        }
        if (!empty($result['address'])) {
            $userData['address'] = $result['address'];
        }
        if (!empty($result['date_of_birth'])) {
            $userData['date_of_birth'] = $result['date_of_birth'];
        }
        if (!empty($result['email'])) {
            $userData['email'] = $result['email'];
        }
        if (!empty($result['first_name'])) {
            $userData['first_name'] = $result['first_name'];
        }
        if (!empty($result['last_name'])) {
            $userData['last_name'] = $result['last_name'];
        }
        if (!empty($result['username'])) {
            $userData['username'] = $result['username'];
        }
    }
} catch (PDOException $e) {
    // Just continue with session data
}

// Get recent activity
$recent_activity = [];
try {
    $activity_stmt = $conn->prepare("SELECT action_performed, table_affected, details, timestamp FROM audittrail WHERE user_id = ? ORDER BY timestamp DESC LIMIT 5");
    $activity_stmt->execute([$_SESSION['user_id']]);
    $recent_activity = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Just continue with empty activity list
}

// Toggle edit mode
if (isset($_GET['edit']) && $_GET['edit'] == 'true') {
    $editMode = true;
}

// Process email OTP generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_otp'])) {
    $newEmail = trim($_POST['new_email']);
    
    // Validate email
    if (empty($newEmail)) {
        $alert = "Email cannot be empty.";
        $alert_type = "danger";
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $alert = "Please enter a valid email address.";
        $alert_type = "danger";
    } elseif ($newEmail === $userData['email']) {
        $alert = "New email is the same as your current email.";
        $alert_type = "danger";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM user WHERE email = ? AND user_id != ?");
        $stmt->execute([$newEmail, $_SESSION['user_id']]);
        $emailExists = $stmt->fetchColumn();
        
        if ($emailExists) {
            $alert = "This email is already in use by another account.";
            $alert_type = "danger";
        } else {
            // Generate OTP
            $otp = rand(100000, 999999);
            
            // Store OTP in session
            $_SESSION['email_otp'] = $otp;
            $_SESSION['new_email'] = $newEmail;
            $_SESSION['otp_expires'] = time() + (10 * 60); // 10 minutes
            
            // Send email with OTP
            if (file_exists('vendor/autoload.php')) {
                require 'vendor/autoload.php'; // Include PHPMailer
                
                if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    
                    try {
                        // Server settings
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = getenv('SMTP_USER') ?: 'rinserenity@gmail.com';
                        $mail->Password = getenv('SMTP_PASS') ?: '';
                        $mail->SMTPSecure = 'tls';
                        $mail->Port = 587;
                        
                        // Recipients
                        $mail->setFrom(getenv('SMTP_USER') ?: 'rinserenity@gmail.com', 'JRC Dental Clinic');
                        $mail->addAddress($newEmail);
                        
                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = 'Email Change Verification - JRC Dental Clinic';
                        $mail->Body = '
                            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                                <div style="text-align: center; margin-bottom: 20px;">
                                    <h2 style="color: #4a8eff;">Email Verification</h2>
                                </div>
                                <p>Dear User,</p>
                                <p>You requested to change your email address for your JRC Dental Clinic account.</p>
                                <p>Your verification code is: <strong style="font-size: 18px;">' . $otp . '</strong></p>
                                <p>This code will expire in 10 minutes.</p>
                                <p>If you did not request this change, please ignore this email or contact us immediately.</p>
                                <p>Thank you,<br>JRC Dental Clinic Team</p>
                            </div>';
                        
                        $mail->send();
                        $alert = "Verification code has been sent to " . $newEmail . ". The code will expire in 10 minutes.";
                        $alert_type = "info";
                        $emailOtpMode = true;
                    } catch (Exception $e) {
                        $alert = "Could not send verification email. Please try again later.";
                        $alert_type = "danger";
                    }
                } else {
                    // PHPMailer not available, but simulate success for development
                    $alert = "Verification code has been sent to " . $newEmail . ". The code will expire in 10 minutes. (Debug mode: OTP is " . $otp . ")";
                    $alert_type = "info";
                    $emailOtpMode = true;
                }
            } else {
                // PHPMailer not available, but simulate success for development
                $alert = "Verification code has been sent to " . $newEmail . ". The code will expire in 10 minutes. (Debug mode: OTP is " . $otp . ")";
                $alert_type = "info";
                $emailOtpMode = true;
            }
        }
    }
}

// Process email OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $enteredOtp = trim($_POST['otp']);
    
    if (!isset($_SESSION['email_otp']) || !isset($_SESSION['new_email']) || !isset($_SESSION['otp_expires'])) {
        $alert = "OTP session has expired. Please request a new verification code.";
        $alert_type = "danger";
    } elseif (time() > $_SESSION['otp_expires']) {
        $alert = "Verification code has expired. Please request a new one.";
        $alert_type = "danger";
        unset($_SESSION['email_otp']);
        unset($_SESSION['new_email']);
        unset($_SESSION['otp_expires']);
    } elseif ($enteredOtp != $_SESSION['email_otp']) {
        $alert = "Invalid verification code. Please try again.";
        $alert_type = "danger";
        $emailOtpMode = true;
    } else {
        // OTP verified, update email
        $stmt = $conn->prepare("UPDATE user SET email = ? WHERE user_id = ?");
        $stmt->execute([$_SESSION['new_email'], $_SESSION['user_id']]);
        
        // Log the email change in audit trail
        $action = "Email change";
        $tableAffected = "user";
        $details = "User changed their email from {$userData['email']} to {$_SESSION['new_email']}";
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        
        $auditStmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, ip_address, details) 
                                    VALUES (?, ?, ?, ?, ?)");
        $auditStmt->execute([$_SESSION['user_id'], $action, $tableAffected, $ipAddress, $details]);
        
        // Update session
        $_SESSION['email'] = $_SESSION['new_email'];
        $userData['email'] = $_SESSION['new_email'];
        
        // Clear OTP data
        unset($_SESSION['email_otp']);
        unset($_SESSION['new_email']);
        unset($_SESSION['otp_expires']);
        
        $alert = "Your email has been successfully updated.";
        $alert_type = "success";
        $emailOtpMode = false;
    }
}

// Process profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $phoneNumber = trim($_POST['phone_number']);
    $address = trim($_POST['address']);
    $dateOfBirth = trim($_POST['date_of_birth']);
    
    // Simple validation
    if (empty($firstName) || empty($lastName)) {
        $alert = "First name and last name are required.";
        $alert_type = "danger";
        $editMode = true;
    } else {
        try {
            $stmt = $conn->prepare("UPDATE user SET first_name = ?, last_name = ?, phone_number = ?, address = ?, date_of_birth = ? WHERE user_id = ?");
            $stmt->execute([$firstName, $lastName, $phoneNumber, $address, $dateOfBirth, $_SESSION['user_id']]);
            
            // Log the profile update in audit trail
            $action = "Profile update";
            $tableAffected = "user";
            $details = "User updated their profile information";
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            
            $auditStmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, ip_address, details) 
                                        VALUES (?, ?, ?, ?, ?)");
            $auditStmt->execute([$_SESSION['user_id'], $action, $tableAffected, $ipAddress, $details]);
            
            // Update session and display data
            $_SESSION['first_name'] = $firstName;
            $_SESSION['last_name'] = $lastName;
            $userData['first_name'] = $firstName;
            $userData['last_name'] = $lastName;
            $userData['phone_number'] = $phoneNumber;
            $userData['address'] = $address;
            $userData['date_of_birth'] = $dateOfBirth;
            
            $alert = "Your profile has been successfully updated.";
            $alert_type = "success";
            $editMode = false;
            
            // Refresh activity list
            $activity_stmt = $conn->prepare("SELECT action_performed, table_affected, details, timestamp FROM audittrail WHERE user_id = ? ORDER BY timestamp DESC LIMIT 5");
            $activity_stmt->execute([$_SESSION['user_id']]);
            $recent_activity = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $alert = "Database error: " . $e->getMessage();
            $alert_type = "danger";
            $editMode = true;
        }
    }
}

// Process password change form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $alert = "Please fill in all password fields.";
        $alert_type = "danger";
    } elseif ($new_password !== $confirm_password) {
        $alert = "New passwords do not match.";
        $alert_type = "danger";
    } elseif (strlen($new_password) < 8) {
        $alert = "Password must be at least 8 characters long.";
        $alert_type = "danger";
    } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
        $alert = "Password must include at least one uppercase letter, one lowercase letter, and one number.";
        $alert_type = "danger";
    } elseif ($current_password === $new_password) {
        $alert = "New password cannot be the same as your current password.";
        $alert_type = "danger";
    } else {
        try {
            // Use the correct password column name
            $password_column = 'password_hash';
            
            // Get the current hashed password
            $stmt = $conn->prepare("SELECT $password_column FROM user WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $hashed_password = $stmt->fetchColumn();
            
            // Verify current password
            if (!password_verify($current_password, $hashed_password)) {
                $alert = "Current password is incorrect.";
                $alert_type = "danger";
            } else {
                // Hash the new password
                $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password in database
                $stmt = $conn->prepare("UPDATE user SET password_hash = ? WHERE user_id = ?");
                $stmt->execute([$new_hashed_password, $_SESSION['user_id']]);
                
                // Log the password change in audit trail
                $action = "Password change";
                $tableAffected = "user";
                $details = "User changed their password";
                $ipAddress = $_SERVER['REMOTE_ADDR'];
                
                $auditStmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, ip_address, details) 
                                            VALUES (?, ?, ?, ?, ?)");
                $auditStmt->execute([$_SESSION['user_id'], $action, $tableAffected, $ipAddress, $details]);
                
                $alert = "Your password has been changed successfully.";
                $alert_type = "success";
                
                // Send email notification if PHPMailer is available
                if (file_exists('vendor/autoload.php')) {
                    require 'vendor/autoload.php'; // Include PHPMailer
                    
                    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                        
                        try {
                            // Server settings
                            $mail->isSMTP();
                            $mail->Host = 'smtp.gmail.com';
                            $mail->SMTPAuth = true;
                            $mail->Username = getenv('SMTP_USER') ?: 'jrcdentalclinic@gmail.com';
                            $mail->Password = getenv('SMTP_PASS') ?: '';
                            $mail->SMTPSecure = 'tls';
                            $mail->Port = 587;
                            
                            // Recipients
                            $mail->setFrom(getenv('SMTP_USER') ?: 'jrcdentalclinic@gmail.com', 'JRC Dental Clinic');
                            if (isset($_SESSION['email']) && isset($_SESSION['first_name'])) {
                                $mail->addAddress($_SESSION['email'], $_SESSION['first_name'] . ' ' . $_SESSION['last_name']);
                                
                                // Content
                                $mail->isHTML(true);
                                $mail->Subject = 'Password Changed - JRC Dental Clinic';
                                $mail->Body = '
                                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                                        <div style="text-align: center; margin-bottom: 20px;">
                                            <h2 style="color: #4a8eff;">Password Changed</h2>
                                        </div>
                                        <p>Dear ' . $_SESSION['first_name'] . ',</p>
                                        <p>Your password for your JRC Dental Clinic account has been successfully changed.</p>
                                        <p>If you did not make this change, please contact us immediately or reset your password.</p>
                                        <p>Thank you,<br>JRC Dental Clinic Team</p>
                                    </div>';
                                
                                $mail->send();
                            }
                        } catch (Exception $e) {
                            // Just log the error but don't show to user
                            error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
                        }
                    }
                }
                
                // Refresh activity list
                $activity_stmt = $conn->prepare("SELECT action_performed, table_affected, details, timestamp FROM audittrail WHERE user_id = ? ORDER BY timestamp DESC LIMIT 5");
                $activity_stmt->execute([$_SESSION['user_id']]);
                $recent_activity = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $alert = "Database error: " . $e->getMessage();
            $alert_type = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - JRC Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .avatar-circle {
            width: 100px;
            height: 100px;
            background-color: #1806a8;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .initials {
            font-size: 40px;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .password-input-container {
            position: relative;
        }
        
        .otp-input {
            letter-spacing: 0.5em;
            text-align: center;
            font-size: 1.5em;
        }
    </style>
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
                    <h3 class="mb-0">My Profile</h3>
                </div>
                
                <?php if($alert): ?>
                <div class="alert alert-<?= $alert_type ?> alert-dismissible fade show" role="alert">
                    <?= $alert ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div class="row g-4">
                    <!-- Profile Information -->
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Profile Information</h5>
                                <?php if (!$editMode): ?>
                                <a href="?edit=true" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if ($editMode): ?>
                                <!-- Edit Profile Form -->
                                <form action="profile.php" method="post">
                                    <div class="text-center mb-4">
                                        <div class="avatar-circle mx-auto mb-3">
                                            <span class="initials">
                                                <?= substr($userData['first_name'], 0, 1) ?><?= substr($userData['last_name'], 0, 1) ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($userData['username']) ?>" readonly disabled>
                                        <div class="form-text">Username cannot be changed</div>
                                    </div><div class="row mb-3">
                                        <div class="col">
                                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($userData['first_name']) ?>" required>
                                        </div>
                                        <div class="col">
                                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($userData['last_name']) ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <div class="input-group">
                                            <input type="email" class="form-control" id="email" value="<?= htmlspecialchars($userData['email']) ?>" readonly disabled>
                                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#changeEmailModal">
                                                Change
                                            </button>
                                        </div>
                                        <div class="form-text">Email change requires verification</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone_number" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone_number" name="phone_number" value="<?= htmlspecialchars($userData['phone_number']) ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= htmlspecialchars($userData['date_of_birth']) ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($userData['address']) ?></textarea>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mt-4">
                                        <a href="profile.php" class="btn btn-secondary">Cancel</a>
                                        <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </form>
                                
                                <?php else: ?>
                                <!-- Display Profile Information -->
                                <div class="text-center mb-4">
                                    <div class="avatar-circle mx-auto mb-3">
                                        <span class="initials">
                                            <?= substr($userData['first_name'], 0, 1) ?><?= substr($userData['last_name'], 0, 1) ?>
                                        </span>
                                    </div>
                                    <h5 class="mb-1"><?= htmlspecialchars($userData['first_name']) ?> <?= htmlspecialchars($userData['last_name']) ?></h5>
                                    <p class="text-muted mb-0"><?= htmlspecialchars($userData['role_name']) ?></p>
                                </div>
                                
                                <div class="mb-2">
                                    <strong><i class="fas fa-user me-2"></i> Username:</strong> 
                                    <?= htmlspecialchars($userData['username']) ?>
                                </div>
                                <div class="mb-2">
                                    <strong><i class="fas fa-envelope me-2"></i> Email:</strong> 
                                    <?= htmlspecialchars($userData['email']) ?>
                                </div>
                                <div class="mb-2">
                                    <strong><i class="fas fa-phone me-2"></i> Phone:</strong> 
                                    <?= htmlspecialchars($userData['phone_number']) ?>
                                </div>
                                <div class="mb-2">
                                    <strong><i class="fas fa-calendar me-2"></i> Date of Birth:</strong> 
                                    <?= !empty($userData['date_of_birth']) ? date('M d, Y', strtotime($userData['date_of_birth'])) : 'Not set' ?>
                                </div>
                                <div class="mb-2">
                                    <strong><i class="fas fa-map-marker-alt me-2"></i> Address:</strong> 
                                    <?= htmlspecialchars($userData['address']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Change Password and Recent Activity -->
                    <div class="col-md-8">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form action="profile.php" method="post">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label for="current_password" class="form-label">Current Password</label>
                                            <div class="password-input-container">
                                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                                <span class="password-toggle" id="currentPasswordToggle">
                                                    <i class="fas fa-eye"></i>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="new_password" class="form-label">New Password</label>
                                            <div class="password-input-container">
                                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                                <span class="password-toggle" id="newPasswordToggle">
                                                    <i class="fas fa-eye"></i>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                                            <div class="password-input-container">
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                                <span class="password-toggle" id="confirmPasswordToggle">
                                                    <i class="fas fa-eye"></i>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="alert alert-info">
                                                <small><i class="fas fa-info-circle me-2"></i> Password must be at least 8 characters and include at least one uppercase letter, one lowercase letter, and one number.</small>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" name="change_password" class="btn btn-primary">
                                                <i class="fas fa-key me-2"></i> Change Password
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">Recent Activity</h5>
                            </div>
                            <div class="card-body">
                                <?php if(!empty($recent_activity)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Action</th>
                                                <th>Table</th>
                                                <th>Details</th>
                                                <th>Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($recent_activity as $activity): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($activity['action_performed'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($activity['table_affected'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($activity['details'] ?? 'N/A') ?></td>
                                                <td><?= isset($activity['timestamp']) ? date('M d, Y h:i A', strtotime($activity['timestamp'])) : 'N/A' ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center my-4">No recent activity</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Change Email Modal -->
    <div class="modal fade" id="changeEmailModal" tabindex="-1" aria-labelledby="changeEmailModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changeEmailModalLabel">Change Email Address</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <?php if ($emailOtpMode): ?>
                <!-- OTP Verification Form -->
                <form action="profile.php?edit=true" method="post">
                    <div class="modal-body">
                        <p>A verification code has been sent to <strong><?= htmlspecialchars($_SESSION['new_email'] ?? '') ?></strong>.</p>
                        <div class="mb-3">
                            <label for="otp" class="form-label">Enter Verification Code</label>
                            <input type="text" class="form-control otp-input" id="otp" name="otp" maxlength="6" required>
                        </div>
                        <div class="text-center">
                            <p>
                                <small class="text-muted">
                                    Didn't receive the code? 
                                    <a href="#" onclick="document.getElementById('generateOtpForm').submit(); return false;">
                                        Send again
                                    </a>
                                </small>
                            </p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="verify_otp" class="btn btn-primary">Verify</button>
                    </div>
                </form>
                <?php else: ?>
                <!-- Email Change Form -->
                <form action="profile.php?edit=true" method="post" id="generateOtpForm">
                    <div class="modal-body">
                        <p>Enter your new email address below. We'll send a verification code to confirm the change.</p>
                        <div class="mb-3">
                            <label for="new_email" class="form-label">New Email Address</label>
                            <input type="email" class="form-control" id="new_email" name="new_email" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="generate_otp" class="btn btn-primary">Send Verification Code</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script>
        // Password toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            // For current password
            const currentPasswordToggle = document.getElementById('currentPasswordToggle');
            const currentPasswordInput = document.getElementById('current_password');
            
            if (currentPasswordToggle && currentPasswordInput) {
                currentPasswordToggle.addEventListener('click', function() {
                    togglePasswordVisibility(currentPasswordInput, currentPasswordToggle);
                });
            }

            // For new password
            const newPasswordToggle = document.getElementById('newPasswordToggle');
            const newPasswordInput = document.getElementById('new_password');
            
            if (newPasswordToggle && newPasswordInput) {
                newPasswordToggle.addEventListener('click', function() {
                    togglePasswordVisibility(newPasswordInput, newPasswordToggle);
                });
            }

            // For confirm password
            const confirmPasswordToggle = document.getElementById('confirmPasswordToggle');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            if (confirmPasswordToggle && confirmPasswordInput) {
                confirmPasswordToggle.addEventListener('click', function() {
                    togglePasswordVisibility(confirmPasswordInput, confirmPasswordToggle);
                });
            }

            // Function to toggle password visibility
            function togglePasswordVisibility(inputElement, toggleElement) {
                if (inputElement.type === 'password') {
                    inputElement.type = 'text';
                    toggleElement.innerHTML = '<i class="fas fa-eye-slash"></i>';
                } else {
                    inputElement.type = 'password';
                    toggleElement.innerHTML = '<i class="fas fa-eye"></i>';
                }
            }
            
            // Auto-open email modal if in OTP mode
            <?php if ($emailOtpMode): ?>
            var changeEmailModal = new bootstrap.Modal(document.getElementById('changeEmailModal'));
            changeEmailModal.show();
            <?php endif; ?>
            
            // Focus OTP input
            const otpInput = document.getElementById('otp');
            if (otpInput) {
                otpInput.focus();
            }
        });
    </script>
</body>
</html>