<?php
session_start();
include_once 'config/database.php';

// Use PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'vendor/autoload.php';

$success_message = "";
$error_message = "";

// Check if verification token exists in URL
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = trim($_GET['token']);
    
    // Connect to database
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        // Find user with this verification token
        $query = "SELECT u.* FROM user u WHERE u.verification_token = :token AND u.email_verified = 0";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Update user as verified
            $update_query = "UPDATE user SET email_verified = 1, verification_token = NULL WHERE user_id = :user_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':user_id', $user['user_id']);
            
            if ($update_stmt->execute()) {
                $success_message = "Your email has been verified successfully! You can now login to your account.";
                
                // Add to audit trail
                $audit_query = "INSERT INTO audittrail (user_id, action_performed, table_affected, details, ip_address) 
                               VALUES (:user_id, 'EMAIL_VERIFICATION', 'user', 'Email verified successfully', :ip_address)";
                $audit_stmt = $db->prepare($audit_query);
                $audit_stmt->bindParam(':user_id', $user['user_id']);
                $audit_stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
                $audit_stmt->execute();
                
                // Send welcome email
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
                    $mail->Subject = 'Welcome to JRC Dental Care';
                    $mail->Body = '
                        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e1e1; border-radius: 5px;">
                            <div style="text-align: center; margin-bottom: 20px;">
                                <h2 style="color: #1806a8;">JRC Dental Care</h2>
                            </div>
                            <div style="margin-bottom: 30px;">
                                <h3>Welcome to JRC Dental Care!</h3>
                                <p>Hello ' . $user['first_name'] . ',</p>
                                <p>Thank you for verifying your email address. Your account is now fully activated and you can enjoy all the features of JRC Dental Care.</p>
                                <div style="text-align: center; margin: 30px 0;">
                                    <a href="' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . '/login.php" style="background-color: #1806a8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Login to Your Account</a>
                                </div>
                                <p>If you have any questions or need assistance, please don\'t hesitate to contact our support team.</p>
                                <p>Best regards,<br>The JRC Dental Care Team</p>
                            </div>
                            <div style="text-align: center; border-top: 1px solid #e1e1e1; padding-top: 20px; font-size: 12px; color: #777;">
                                <p>JRC Dental Care | 123 Dental Street, Suite 100 | City, State 12345</p>
                                <p>&copy; ' . date('Y') . ' JRC Dental Care. All rights reserved.</p>
                            </div>
                        </div>
                    ';
                    
                    $mail->send();
                } catch (Exception $e) {
                    // Silently log email sending error - don't show to user since account is already verified
                    error_log("Mailer Error: " . $mail->ErrorInfo);
                }
            } else {
                $error_message = "There was a problem verifying your email. Please try again or contact support.";
            }
        } else {
            $error_message = "Invalid verification token or account already verified.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
} else if (isset($_GET['resend']) && !empty($_GET['email'])) {
    // Resend verification email
    $email = trim($_GET['email']);
    
    // Connect to database
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        // Find user with this email
        $query = "SELECT u.* FROM user u WHERE u.email = :email AND u.email_verified = 0";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Generate verification token if it doesn't exist
            if (empty($user['verification_token'])) {
                $verification_token = bin2hex(random_bytes(32)); // Generate a secure random token
                
                $token_query = "UPDATE user SET verification_token = :token WHERE user_id = :user_id";
                $token_stmt = $db->prepare($token_query);
                $token_stmt->bindParam(':token', $verification_token);
                $token_stmt->bindParam(':user_id', $user['user_id']);
                $token_stmt->execute();
            } else {
                $verification_token = $user['verification_token'];
            }
            
            // Send verification email
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
                
                // Generate the verification link
                $verification_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . '/email-verification.php?token=' . $verification_token;
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Email Verification - JRC Dental Care';
                $mail->Body = '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e1e1; border-radius: 5px;">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <h2 style="color: #1806a8;">JRC Dental Care</h2>
                        </div>
                        <div style="margin-bottom: 30px;">
                            <h3>Email Verification</h3>
                            <p>Hello ' . $user['first_name'] . ',</p>
                            <p>Thank you for registering with JRC Dental Care. To complete your registration and activate your account, please verify your email address by clicking the button below:</p>
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="' . $verification_link . '" style="background-color: #1806a8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Verify Email Address</a>
                            </div>
                            <p>If the button above doesn\'t work, you can also copy and paste the following link into your browser:</p>
                            <p style="word-break: break-all;">' . $verification_link . '</p>
                            <p>This link will expire in 24 hours.</p>
                            <p>If you did not create an account with JRC Dental Care, please ignore this email.</p>
                        </div>
                        <div style="text-align: center; border-top: 1px solid #e1e1e1; padding-top: 20px; font-size: 12px; color: #777;">
                            <p>JRC Dental Care | 123 Dental Street, Suite 100 | City, State 12345</p>
                            <p>&copy; ' . date('Y') . ' JRC Dental Care. All rights reserved.</p>
                        </div>
                    </div>
                ';
                
                $mail->send();
                $success_message = "Verification email has been resent. Please check your inbox and spam folder.";
            } catch (Exception $e) {
                $error_message = "Could not send the verification email. Please try again later.";
                error_log("Mailer Error: " . $mail->ErrorInfo);
            }
        } else {
            $error_message = "Email not found or already verified.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
} else {
    // No token and no resend request
    $error_message = "Invalid request.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - JRC Dental Care</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5">
                <div class="card border-0 shadow-lg">
                    <div class="card-body p-5 text-center">
                        <a href="index.php"><img src="images/jrclogo.png" alt="JRC Dental Care Logo" height="60" class="mb-4"></a>
                        
                        <?php if (!empty($success_message)): ?>
                            <div class="text-center mb-4">
                                <div class="bg-success bg-opacity-10 text-success rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                    <i class="fas fa-check-circle fa-3x"></i>
                                </div>
                                <h4 class="mt-4 mb-3">Email Verified</h4>
                                <p class="text-muted mb-4"><?php echo $success_message; ?></p>
                                <a href="login.php" class="btn btn-primary btn-lg px-5">Login Now</a>
                            </div>
                        <?php elseif (!empty($error_message)): ?>
                            <div class="text-center mb-4">
                                <div class="bg-danger bg-opacity-10 text-danger rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                    <i class="fas fa-times-circle fa-3x"></i>
                                </div>
                                <h4 class="mt-4 mb-3">Verification Failed</h4>
                                <p class="text-muted mb-4"><?php echo $error_message; ?></p>
                                
                                <?php if (strpos($error_message, 'Invalid verification token') !== false): ?>
                                    <div class="mb-4">
                                        <p>Need a new verification link?</p>
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get">
                                            <div class="input-group mb-3">
                                                <input type="email" class="form-control" name="email" placeholder="Enter your email" required>
                                                <input type="hidden" name="resend" value="1">
                                                <button class="btn btn-primary" type="submit">Resend</button>
                                            </div>
                                        </form>
                                    </div>
                                <?php endif; ?>
                                
                                <a href="index.php" class="btn btn-outline-primary">Back to Home</a>
                            </div>
                        <?php else: ?>
                            <div class="text-center mb-4">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                    <i class="fas fa-envelope fa-3x"></i>
                                </div>
                                <h4 class="mt-4 mb-3">Email Verification</h4>
                                <p class="text-muted mb-4">Please verify your email address to activate your account.</p>
                                <a href="index.php" class="btn btn-outline-primary">Back to Home</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>