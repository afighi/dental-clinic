<?php
// Start session
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'config/database.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    
    // Validate password
    if (empty($password)) {
        $error = "Please enter your password.";
    } else {
        // Verify password
        $stmt = $conn->prepare("SELECT password FROM user WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (password_verify($password, $user['password'])) {
            // Set verification flag in session
            $_SESSION['password_verified'] = true;
            $_SESSION['password_verified_time'] = time();
            
            // Redirect to intended page or dashboard
            if (isset($_SESSION['intended_url'])) {
                $redirect_url = $_SESSION['intended_url'];
                unset($_SESSION['intended_url']);
                header("Location: $redirect_url");
            } else {
                // Default redirect based on role
                switch ($_SESSION['role_name']) {
                    case 'Admin':
                        header("Location: admin-dashboard.php");
                        break;
                    case 'Dentist':
                        header("Location: dentist-dashboard.php");
                        break;
                    case 'Staff':
                        header("Location: staff-dashboard.php");
                        break;
                    case 'Patient':
                        header("Location: patient-dashboard.php");
                        break;
                    default:
                        header("Location: index.php");
                }
            }
            exit();
        } else {
            $error = "Incorrect password. Please try again.";
        }
    }
}

// Get user info
$stmt = $conn->prepare("SELECT first_name, last_name FROM user WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$user_full_name = $user['first_name'] . ' ' . $user['last_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Password - JRC Dental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/verify.css">
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
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li><a href="<?php echo strtolower($_SESSION['role_name']) ?>-dashboard.php">Dashboard</a></li>
                            <li><a href="logout.php">Logout</a></li>
                        <?php else: ?>
                            <li><a href="login.php">Login</a></li>
                            <li><a href="register.php">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Verify Password Section -->
    <section class="verify-section">
        <div class="container">
            <div class="section-title">
                <h2>Verify Your Password</h2>
                <p>For security reasons, please confirm your password to continue</p>
            </div>

            <div class="verify-container">
                <div class="card">
                    <div class="card-body">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-error">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <div class="user-info-section">
                            <div class="user-avatar">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <h3><?php echo htmlspecialchars($user_full_name); ?></h3>
                            <p><?php echo htmlspecialchars($_SESSION['email']); ?></p>
                        </div>

                        <form action="verify-password.php" method="post" class="verify-form">
                            <div class="form-group">
                                <label for="password">Password <span class="required">*</span></label>
                                <div class="password-input-container">
                                    <input type="password" id="password" name="password" required>
                                    <span class="password-toggle" id="passwordToggle">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn-primary">Verify Password</button>
                                <a href="index.php" class="btn-secondary">Cancel</a>
                            </div>
                        </form>

                        <div class="additional-options">
                            <p>Forgot your password? <a href="forgot-password.php">Reset it here</a></p>
                        </div>
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