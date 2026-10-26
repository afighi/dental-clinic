<?php
session_start();
require_once 'config/database.php';

// Initialize variables
$message = '';
$messageType = '';

// Debug information
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if token exists in URL
if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    
    try {
        // Check if token exists and is valid
        $stmt = $conn->prepare("SELECT * FROM password_reset WHERE token = ?");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch();

        if ($tokenData) {
            // Token exists, update user verification status
            $stmt = $conn->prepare("UPDATE user SET email_verified = 1 WHERE user_id = ?");
            $stmt->execute([$tokenData['user_id']]);

            if ($stmt->rowCount() > 0) {
                $message = "Your email has been successfully verified! You can now login.";
                $messageType = "success";

                // Delete the used token
                $stmt = $conn->prepare("DELETE FROM password_reset WHERE token = ?");
                $stmt->execute([$token]);
            } else {
                $message = "Failed to verify email. Please try again.";
                $messageType = "error";
            }
        } else {
            $message = "Invalid or expired verification token.";
            $messageType = "error";
        }
    } catch (PDOException $e) {
        $message = "Database error occurred. Please try again later.";
        $messageType = "error";
        error_log("Database Error: " . $e->getMessage());
    }
} else {
    // If no token provided and no verification email sent
    if (!isset($_SESSION['verification_email'])) {
        $message = "No verification token provided.";
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - JRC Dental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .verification-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success-message {
            color: #28a745;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .error-message {
            color: #dc3545;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .info-message {
            color: #17a2b8;
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .btn-login {
            background-color: #007bff;
            color: white;
            padding: 10px 30px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
        .btn-login:hover {
            background-color: #0056b3;
            color: white;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <h2>Email Verification</h2>
        
        <?php if ($messageType == 'success'): ?>
            <div class="success-message">
                <?php echo $message; ?>
                <div class="mt-3">
                    <a href="login.php" class="btn-login">Login Now</a>
                </div>
            </div>
        <?php elseif ($messageType == 'error'): ?>
            <div class="error-message">
                <?php echo $message; ?>
                <div class="mt-3">
                    <p>If you're having trouble, you can:</p>
                    <a href="resend-verification.php" class="btn btn-outline-primary">Request New Verification Link</a>
                    <a href="contact.php" class="btn btn-outline-secondary">Contact Support</a>
                </div>
            </div>
        <?php else: ?>
            <div class="info-message">
                <?php 
                if (isset($_SESSION['verification_email'])) {
                    echo "A verification link has been sent to " . htmlspecialchars($_SESSION['verification_email']) . ". Please check your email to verify your account.";
                } else {
                    echo "Please check your email for the verification link.";
                }
                ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>