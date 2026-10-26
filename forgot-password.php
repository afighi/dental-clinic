<?php
session_start();
date_default_timezone_set('Asia/Manila');
include_once 'config/database.php';

// Use PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'vendor/autoload.php';

$error_message = "";
$success_message = "";
$step = 1;

// Process email submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['step']) && $_POST['step'] == "1") {
        $email = trim($_POST['email']);
        
        if (empty($email)) {
            $error_message = "Email is required.";
        } else {
            // Check if email exists
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT user_id, first_name, last_name, email FROM user WHERE email = :email";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":email", $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $otp = sprintf("%06d", rand(0, 999999));
$current_time = new DateTime();
$current_time->modify('+15 minutes');
$expiry_time = $current_time->format('Y-m-d H:i:s');
                
                // Store OTP in password_reset table
                $token_query = "INSERT INTO password_reset (user_id, token, expiry_time) 
                               VALUES (:user_id, :token, :expiry_time)";
                $token_stmt = $db->prepare($token_query);
                $token_stmt->bindParam(":user_id", $user['user_id']);
                $token_stmt->bindParam(":token", $otp);
                $token_stmt->bindParam(":expiry_time", $expiry_time);
                
                if ($token_stmt->execute()) {
                    // Store user info in session for verification step
                    $_SESSION['reset_user_id'] = $user['user_id'];
                    $_SESSION['reset_email'] = $user['email'];
                    
                    // Send OTP via email using PHPMailer
                    $mail = new PHPMailer(true);
                    
                    try {
                        // Server settings
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = getenv('SMTP_USER') ?: 'rinserenity@gmail.com';
                        $mail->Password = getenv('SMTP_PASS') ?: '';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;
                        
                        // Recipients
                        $mail->setFrom(getenv('SMTP_USER') ?: 'rinserenity@gmail.com', 'JRC Dental Care');
                        $mail->addAddress($user['email'], $user['first_name'] . ' ' . $user['last_name']);
                        
                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = 'Password Reset OTP - JRC Dental Care';
                        $mail->Body = '
                            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e1e1; border-radius: 5px;">
                                <div style="text-align: center; margin-bottom: 20px;">
                                    <h2 style="color: #1806a8;">JRC Dental Care</h2>
                                </div>
                                <div style="margin-bottom: 30px;">
                                    <h3>Password Reset Request</h3>
                                    <p>Hello ' . $user['first_name'] . ',</p>
                                    <p>We received a request to reset your password. Use the following OTP to reset your password:</p>
                                    <div style="text-align: center; margin: 30px 0;">
                                        <div style="font-size: 24px; font-weight: bold; letter-spacing: 5px; background-color: #f5f5f5; padding: 15px; border-radius: 5px;">' . $otp . '</div>
                                    </div>
                                    <p>This OTP will expire in 15 minutes.</p>
                                    <p>If you did not request a password reset, please ignore this email or contact our support team.</p>
                                </div>
                                <div style="text-align: center; border-top: 1px solid #e1e1e1; padding-top: 20px; font-size: 12px; color: #777;">
                                    <p>JRC Dental Care | 123 Dental Street, Suite 100 | City, State 12345</p>
                                    <p>&copy; ' . date('Y') . ' JRC Dental Care. All rights reserved.</p>
                                </div>
                            </div>
                        ';
                        
                        $mail->send();
                        $success_message = "OTP sent to your email. Please check and enter the code.";
                        $step = 2;
                        
                    } catch (Exception $e) {
                        $error_message = "Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
                    }
                } else {
                    $error_message = "Failed to generate reset token. Please try again.";
                }
            } else {
                $error_message = "Email not found in our records.";
            }
        }
    } elseif (isset($_POST['step']) && $_POST['step'] == "2") {
        // Verify OTP
        $otp = trim($_POST['otp']);
        
        if (empty($otp)) {
            $error_message = "OTP is required.";
            $step = 2;
        } else {
            $database = new Database();
            $db = $database->getConnection();
            
            // Ensure OTP is a 6-digit string with leading zeros
            $otp = sprintf("%06d", intval($otp));
            
            $query = "SELECT * FROM password_reset 
         WHERE user_id = :user_id 
         AND token = :token 
         AND expiry_time > CURRENT_TIMESTAMP
         ORDER BY reset_id DESC LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":user_id", $_SESSION['reset_user_id']);
            $stmt->bindParam(":token", $otp);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // OTP is valid and not expired
                $step = 3;
            } else {
                // Check if token exists but expired
                $query = "SELECT * FROM password_reset 
         WHERE user_id = :user_id 
         AND token = :token 
         AND expiry_time <= CURRENT_TIMESTAMP
         ORDER BY reset_id DESC LIMIT 1";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":user_id", $_SESSION['reset_user_id']);
                $stmt->bindParam(":token", $otp);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $error_message = "OTP has expired. Please request a new one.";
                } else {
                    $error_message = "Invalid OTP. Please check and try again.";
                }
                $step = 2;
            }
        }
    } elseif (isset($_POST['step']) && $_POST['step'] == "3") {
        // Reset password
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);
        
        if (empty($new_password) || empty($confirm_password)) {
            $error_message = "All fields are required.";
            $step = 3;
        } elseif ($new_password !== $confirm_password) {
            $error_message = "Passwords do not match.";
            $step = 3;
        } else {
            $database = new Database();
            $db = $database->getConnection();
            
            // Check if new password is the same as the current password
            $current_password_query = "SELECT password_hash FROM user WHERE user_id = :user_id";
            $current_password_stmt = $db->prepare($current_password_query);
            $current_password_stmt->bindParam(":user_id", $_SESSION['reset_user_id']);
            $current_password_stmt->execute();
            
            if ($current_password_stmt->rowCount() > 0) {
                $user_data = $current_password_stmt->fetch(PDO::FETCH_ASSOC);
                $current_hashed_password = $user_data['password_hash'];
                
                // Verify if new password matches the current password
                if (password_verify($new_password, $current_hashed_password)) {
                    $error_message = "Your new password cannot be the same as your current password.";
                    $step = 3;
                } else {
                    // Hash new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update user password
                    $query = "UPDATE user SET password_hash = :password_hash WHERE user_id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":password_hash", $hashed_password);
                    $stmt->bindParam(":user_id", $_SESSION['reset_user_id']);
                    
                    if ($stmt->execute()) {
                        // Delete all reset tokens for this user
                        $delete_query = "DELETE FROM password_reset WHERE user_id = :user_id";
                        $delete_stmt = $db->prepare($delete_query);
                        $delete_stmt->bindParam(":user_id", $_SESSION['reset_user_id']);
                        $delete_stmt->execute();
                        
                        // Clear reset session data
                        unset($_SESSION['reset_user_id']);
                        unset($_SESSION['reset_email']);
                        
                        $success_message = "Password reset successful! You can now login with your new password.";
                        $step = 4;
                    } else {
                        $error_message = "Failed to reset password. Please try again.";
                        $step = 3;
                    }
                }
            } else {
                $error_message = "User information not found. Please restart the password reset process.";
                $step = 1;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - JRC Dental Care</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        /* Add custom styles to match the login page */
        .semi-transparent-card {
            background-color: rgba(255, 255, 255, 0.8); /* White with 80% opacity */
        }
    </style>
</head>
<body class="bg-light" style="background-image: url('images/containerbg.png'); background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed;">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5">
                <div class="card border-0 shadow-lg semi-transparent-card" style="border-radius: 1.5rem;">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <a href="index.php"><img src="images/jrclogo.png" alt="JRC Dental Care Logo" height="60"></a>
                            <h3 class="fw-bold mt-3">Reset Your Password</h3>
                            <?php if ($step < 4): ?>
                            <p class="text-muted">
                                <?php 
                                if ($step == 1) echo "Enter your email to receive a reset code";
                                elseif ($step == 2) echo "Enter the OTP sent to your email";
                                elseif ($step == 3) echo "Create a new password";
                                ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $success_message; ?>
                            <?php if ($step == 4): ?>
                            <div class="mt-2">
                                <a href="login.php" class="alert-link" style="color: #3C096C;">Click here to login</a>
                            </div>
                            <?php endif; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($step == 1): ?>
                        <!-- Step 1: Email Input -->
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <input type="hidden" name="step" value="1">
                            
                            <div class="mb-4" >
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" placeholder="Enter your registered email" required>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary py-2">Send Reset Code</button>
                            </div>
                        </form>
                        <?php elseif ($step == 2): ?>
                        <!-- Step 2: OTP Verification -->
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <input type="hidden" name="step" value="2">
                            
                            <div class="mb-4">
                                <label for="otp" class="form-label">OTP Code</label>
                                <div class="otp-input-container d-flex justify-content-center mb-3">
                                    <input type="text" class="form-control text-center" id="otp" name="otp" placeholder="Enter 6-digit OTP" maxlength="6" required>
                                </div>
                                <div class="text-center mb-3">
                                    <span class="text-muted small">OTP sent to: <?php echo isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : ''; ?></span>
                                </div>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary py-2">Verify OTP</button>
                            </div>
                            
                            <div class="text-center">
                                <p class="mb-0">Didn't receive the code? <a href="forgot-password.php" class="text-decoration-none" style="color: #3C096C;">Resend</a></p>
                            </div>
                        </form>
                        <?php elseif ($step == 3): ?>
                        <!-- Step 3: New Password -->
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <input type="hidden" name="step" value="3">
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Create a new password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength mt-2" id="password-strength"></div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary py-2">Reset Password</button>
                            </div>
                        </form>
                        <?php elseif ($step == 4): ?>
                        <!-- Step 4: Success -->
                        <div class="text-center">
                            <div class="mb-4">
                                <i class="fas fa-check-circle text-success fa-4x"></i>
                            </div>
                            <p>Your password has been reset successfully.</p>
                            <div class="d-grid mt-4">
                                <a href="login.php" class="btn btn-primary py-2">Go to Login</a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($step < 4): ?>
                        <div class="text-center mt-4">
                            <a href="login.php" class="text-decoration-none" style="color: #3C096C;">
                                <i class="fas fa-arrow-left me-1"></i> Back to Login
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Toggle password visibility for new password
        document.getElementById('toggleNewPassword')?.addEventListener('click', function() {
            const passwordInput = document.getElementById('new_password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Password strength checker
        const passwordInput = document.getElementById('new_password');
        const strengthMeter = document.getElementById('password-strength');
        
        if (passwordInput && strengthMeter) {
            passwordInput.addEventListener('input', function() {
                const password = passwordInput.value;
                let strength = 0;
                let message = '';
                
                if (password.length >= 8) strength += 25;
                if (password.match(/[a-z]+/)) strength += 25;
                if (password.match(/[A-Z]+/)) strength += 25;
                if (password.match(/[0-9]+/)) strength += 25;
                
                if (strength === 0) {
                    strengthMeter.innerHTML = '';
                } else if (strength <= 25) {
                    message = '<span class="text-danger">Weak</span>';
                } else if (strength <= 50) {
                    message = '<span class="text-warning">Fair</span>';
                } else if (strength <= 75) {
                    message = '<span class="text-info">Good</span>';
                } else {
                    message = '<span class="text-success">Strong</span>';
                }
                
                strengthMeter.innerHTML = message;
            });
        }
        
        // Enhance OTP input field to improve user experience
        const otpInput = document.getElementById('otp');
        if (otpInput) {
            // Only allow numbers
            otpInput.addEventListener('keypress', function(e) {
                if (isNaN(parseInt(e.key))) {
                    e.preventDefault();
                }
            });
            
            // Auto-submit when 6 digits are entered
            otpInput.addEventListener('input', function() {
                if (this.value.length === 6) {
                    // Give a small delay to allow user to see what they typed
                    setTimeout(() => {
                        this.form.submit();
                    }, 500);
                }
            });
            
            // Focus on OTP input when page loads
            window.addEventListener('load', function() {
                otpInput.focus();
            });
        }
    </script>
</body>
</html>