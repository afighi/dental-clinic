<?php
session_start();
require_once 'config/database.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $message = "Please enter your email address.";
        $messageType = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "error";
    } else {
        try {
            // Check if email exists and is not verified
            $stmt = $conn->prepare("
                SELECT user_id, first_name, email_verified 
                FROM user 
                WHERE email = :email
            ");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch();
            
            if ($user) {
                if ($user['email_verified'] == 1) {
                    $message = "This email is already verified. Please login to your account.";
                    $messageType = "info";
                } else {
                    // Create new verification token
                    $verification_token = bin2hex(random_bytes(32));
                    
                    // Delete any existing tokens
                    $stmt = $conn->prepare("
                        DELETE FROM password_reset 
                        WHERE user_id = :user_id
                    ");
                    $stmt->bindParam(':user_id', $user['user_id']);
                    $stmt->execute();
                    
                    // Insert new token
                    $stmt = $conn->prepare("
                        INSERT INTO password_reset (user_id, token, expiry_time) 
                        VALUES (:user_id, :token, DATE_ADD(NOW(), INTERVAL 24 HOUR))
                    ");
                    $stmt->bindParam(':user_id', $user['user_id']);
                    $stmt->bindParam(':token', $verification_token);
                    $stmt->execute();
                    
                    // Send verification email
                    require 'vendor/autoload.php';
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    
                    try {
                        // Server settings
                        $mail->SMTPDebug = 0;
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = getenv('SMTP_USER') ?: 'rinserenity@gmail.com';
                        $mail->Password = getenv('SMTP_PASS') ?: '';
                        $mail->SMTPSecure = 'tls';
                        $mail->Port = 587;
                        
                        // Recipients
                        $mail->setFrom(getenv('SMTP_USER') ?: 'rinserenity@gmail.com', 'JRC Dental Clinic');
                        $mail->addAddress($email, $user['first_name']);
                        
                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = 'Verify Your JRC Dental Account';
                        // Change this line in resend-verification.php
$mail->Body = '
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
    <div style="text-align: center; margin-bottom: 20px;">
        <h2 style="color: #4a8eff;">JRC Dental Account Verification</h2>
    </div>
    <p>Dear ' . $user['first_name'] . ',</p>
    <p>You requested a new verification link. Click the button below to verify your account:</p>
    <div style="text-align: center; margin: 30px 0;">
        <a href="http://' . $_SERVER['HTTP_HOST'] . '/JRCT2/verify-registration.php?token=' . $verification_token . '" 
           style="background-color: #4a8eff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold;">
            Verify Your Account
        </a>
    </div>
    <p>This link will expire in 24 hours.</p>
    <p>If you did not request this verification link, please ignore this email.</p>
    <p>Best regards,<br>JRC Dental Clinic Team</p>
</div>';
                        
                        $mail->send();
                        
                        $message = "A new verification link has been sent to your email address.";
                        $messageType = "success";
                    } catch (Exception $e) {
                        $message = "Could not send verification email. Please try again later.";
                        $messageType = "error";
                        error_log($mail->ErrorInfo);
                    }
                }
            } else {
                $message = "No account found with this email address.";
                $messageType = "error";
            }
        } catch (PDOException $e) {
            $message = "An error occurred. Please try again later.";
            $messageType = "error";
            error_log($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Verification - JRC Dental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .resend-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .resend-form {
            margin-top: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .message.success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .message.error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .message.info {
            background-color: #e1f5fe;
            color: #0288d1;
            border: 1px solid #b3e5fc;
        }

        .btn-resend {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: #4a8eff;
            color: white;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-resend:hover {
            background-color: #357abd;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
        }

        .login-link a {
            color: #4a8eff;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <header>
        <div class="header-top">
            <div class="logo-container">
                <div class="logo">
                    <img src="images/jrclogo.png" alt="JRC Dental Clinic Logo" style="width: 320px; height: 80px;">
                </div>
            </div>
            <div class="contact-container">
                <div class="contact-info">
                    <h3>Bonga Mayor, Bustos</h3>
                    <p>+63 994 557 1309</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Resend Verification Section -->
    <section class="resend-section">
        <div class="resend-container">
            <h2>Resend Verification Link</h2>
            
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="resend-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <button type="submit" class="btn-resend">Send Verification Link</button>
            </form>

            <div class="login-link">
                <p>Remember your password? <a href="login.php">Login here</a></p>
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
                <p><i class="fas fa-phone"></i> (+63) 994 557 1309</p>
                <p><i class="fas fa-map-marker-alt"></i> Purok 3, Bonga Mayor, Bustos, Bulacan</p>
            </div>
        </div>
    </footer>
</body>
</html>