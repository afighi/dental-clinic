<?php
// Start session
session_start();

// Include database connection
require_once 'config/database.php';

$error = '';
$success = '';
$token = '';

// Check if token is provided
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Validate token
    $stmt = $conn->prepare("SELECT pr.reset_id, pr.user_id, pr.expiry_time, u.first_name, u.last_name, u.email 
                           FROM password_reset pr 
                           JOIN user u ON pr.user_id = u.user_id 
                           WHERE pr.token = :token AND pr.expiry_time > NOW()");
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        $error = "Invalid or expired token. Please request a new password reset link.";
        $token = '';
    } else {
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_id = $reset['user_id'];
        $first_name = $reset['first_name'];
    }
} else {
    header("Location: forgot-password.php");
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($token)) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        try {
            $conn->beginTransaction();
            
            // Update password in database
            $stmt = $conn->prepare("UPDATE user SET password = :password WHERE user_id = :user_id");
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            // Delete all reset tokens for this user
            $stmt = $conn->prepare("DELETE FROM password_reset WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            $conn->commit();
            
            // Log the action
            $action = "Password reset";
            $tableAffected = "user";
            $details = "Password reset completed for user ID: " . $user_id;
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            
            $auditStmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, ip_address, details) 
                                        VALUES (:user_id, :action, :table, :ip, :details)");
            $auditStmt->bindParam(':user_id', $user_id);
            $auditStmt->bindParam(':action', $action);
            $auditStmt->bindParam(':table', $tableAffected);
            $auditStmt->bindParam(':ip', $ipAddress);
            $auditStmt->bindParam(':details', $details);
            $auditStmt->execute();
            
            // Send confirmation email
            require 'vendor/autoload.php'; // Include PHPMailer
            
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
                $mail->addAddress($reset['email'], $reset['first_name'] . ' ' . $reset['last_name']);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Successfully - JRC Dental Clinic';
                $mail->Body = '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <h2 style="color: #4a8eff;">Password Reset Successfully</h2>
                        </div>
                        <p>Dear ' . $reset['first_name'] . ',</p>
                        <p>Your password for your JRC Dental Clinic account has been successfully reset.</p>
                        <p>If you did not make this change, please contact us immediately.</p>
                        <div style="text-align: center; margin: 30px 0;">
                            <a href="' . $_SERVER['HTTP_HOST'] . '/login.php" style="background-color: #4a8eff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold;">Login to Your Account</a>
                        </div>
                        <p>Thank you,<br>JRC Dental Clinic Team</p>
                    </div>';
                
                $mail->send();
            } catch (Exception $e) {
                error_log("Email error: " . $mail->ErrorInfo);
            }
            
            // Set success message
            $success = "Your password has been reset successfully. You can now login with your new password.";
            $token = '';
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Something went wrong. Please try again later.";
            error_log("Database error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - JRC Dental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/reset-password.css">
</head>
<body>
    <!-- Header Section -->
    <header>
        <div class="header-top">
            <div class="logo-container">
                <div class="logo"> <img src="images/jrclogo.png" alt="JRC Dental Clinic Logo" style="width: 320px; height: 80px;"> </div>
            </div>
            <div class="contact-container">
            <div class="contact-info">
                <h3>Bonga Mayor, Bustos</h3>
                <p>+63 994 557 1309</p>
            </div>
        </div>
        </div>
        <div class="nav-container">
            <div class="nav-wrapper">
                <nav class="main-nav">
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="about-us.php">About Us</a></li>
                        <li><a href="services.php">Our Dental Services</a></li>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Reset Password Section -->
    <section class="reset-password-section">
        <div class="container">
            <div class="section-title">
                <h2>Reset Password</h2>
                <p>Create a new password for your account</p>
            </div>

            <div class="reset-password-container">
                <div class="card">
                    <div class="card-body">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-error">
                                <?php echo $error; ?>
                                <div class="form-actions mt-4">
                                    <a href="forgot-password.php" class="btn-primary">Request New Reset Link</a>
                                </div>
                            </div>
                        <?php elseif (!empty($success)): ?>
                            <div class="alert alert-success">
                                <?php echo $success; ?>
                                <div class="form-actions mt-4">
                                    <a href="login.php" class="btn-primary">Go to Login</a>
                                </div>
                            </div>
                        <?php elseif (!empty($token)): ?>
                            <div class="welcome-message">
                                <p>Hello, <?php echo isset($first_name) ? htmlspecialchars($first_name) : 'User'; ?>!</p>
                                <p>Please create a new password for your account.</p>
                            </div>
                            
                            <form action="reset-password.php?token=<?php echo $token; ?>" method="post" class="reset-password-form">
                                <div class="form-group">
                                    <label for="new_password">New Password <span class="required">*</span></label>
                                    <div class="password-input-container">
                                        <input type="password" id="new_password" name="new_password" required minlength="8">
                                        <span class="password-toggle" id="newPasswordToggle">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                    <small>Must be at least 8 characters</small>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password <span class="required">*</span></label>
                                    <div class="password-input-container">
                                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                                        <span class="password-toggle" id="confirmPasswordToggle">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                </div>

                                <div class="password-strength">
                                    <h4>Password Strength Guidelines:</h4>
                                    <ul>
                                        <li>Minimum 8 characters</li>
                                        <li>Include at least one uppercase letter</li>
                                        <li>Include at least one lowercase letter</li>
                                        <li>Include at least one number</li>
                                        <li>Include at least one special character (e.g., !@#$%^&*)</li>
                                    </ul>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" class="btn-primary">Reset Password</button>
                                    <a href="login.php" class="btn-secondary">Cancel</a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer Section -->
    <footer>
        <div class="footer-container">
            <div class="footer-logo">
                <div class="logo">LOGO PLACEHOLDER</div>
                <p>Elevating People's lives one smile at a time.</p>
            </div>
            
            <div class="footer-links">
                <div class="footer-column">
                    <h3>Support</h3>
                    <ul>
                        <li><a href="about-us.php">About us</a></li>
                        <li><a href="contact.php">Contact us</a></li>
                        <li><a href="services.php">Our Services</a></li>
                        <li><a href="appointment.php">Book Appointment</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Treatments</h3>
                    <ul>
                        <li><a href="services/invisalign.php">Invisalign</a></li>
                        <li><a href="services/oral-surgery.php">Oral Surgery</a></li>
                        <li><a href="services/braces.php">Braces</a></li>
                        <li><a href="services/airflow.php">Airflow</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Follow Us</h3>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-tiktok"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <div class="copyright">
                <p>&copy; 2025 www.jrcdental.ph</p>
                <p>All rights reserved. Developed by ARDG</p>
            </div>
            <div class="contact-details">
                <p><i class="fas fa-phone"></i> (+63) 994 557 1309   </p>
                <p><i class="fas fa-map-marker-alt"></i> Purok 3, Bonga Mayor, Bustos, Bulacan</p>
            </div>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
    <script src="assets/js/password-toggle.js"></script>
</body>
</html>