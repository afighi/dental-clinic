    <?php
    session_start();
    include_once 'config/database.php';

    // Use PHPMailer
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
    use PHPMailer\PHPMailer\SMTP;

    require 'vendor/autoload.php';

    // Redirect if already logged in
    if (isset($_SESSION['user_id'])) {
        header("Location: " . $_SESSION['role_name'] . "-dashboard.php");
        exit();
    }

    $error_message = "";
    $success_message = "";
    $current_step = 1;
    $total_steps = 3;

    // Define variables to store form data
    $username = $email = $first_name = $middle_name = $last_name = $suffix = $phone_number = $address = "";

    // Process form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Handle different form steps
        if (isset($_POST['step'])) {
            $current_step = (int)$_POST['step'];

            // Store form data in session for each step
            if ($current_step == 1) {
                $_SESSION['register_data']['username'] = trim($_POST['username']);
                $_SESSION['register_data']['email'] = trim($_POST['email']);
                $_SESSION['register_data']['password'] = trim($_POST['password']);
                $confirm_password = trim($_POST['confirm_password']);

                // Validate step 1
                if (empty($_SESSION['register_data']['username']) || empty($_SESSION['register_data']['email']) ||
                    empty($_SESSION['register_data']['password']) || empty($confirm_password)) {
                    $error_message = "All fields are required.";
                    $current_step = 1;
                } else if ($_SESSION['register_data']['password'] !== $confirm_password) {
                    $error_message = "Passwords do not match.";
                    $current_step = 1;
                } else {
                    // Check if username or email already exists
                    $database = new Database();
                    $db = $database->getConnection();

                    $check_query = "SELECT user_id FROM user WHERE username = :username OR email = :email";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->bindParam(":username", $_SESSION['register_data']['username']);
                    $check_stmt->bindParam(":email", $_SESSION['register_data']['email']);
                    $check_stmt->execute();

                    if ($check_stmt->rowCount() > 0) {
                        $error_message = "Username or email already exists.";
                        $current_step = 1;
                    } else {
                        // Proceed to next step
                        $current_step = 2;
                    }
                }
            } else if ($current_step == 2) {
                $_SESSION['register_data']['first_name'] = trim($_POST['first_name']);
                $_SESSION['register_data']['middle_name'] = trim($_POST['middle_name']);
                $_SESSION['register_data']['last_name'] = trim($_POST['last_name']);
                $_SESSION['register_data']['suffix'] = trim($_POST['suffix']);

                // Validate step 2
                if (empty($_SESSION['register_data']['first_name']) || empty($_SESSION['register_data']['last_name'])) {
                    $error_message = "First name and last name are required.";
                    $current_step = 2;
                } else {
                    // Proceed to next step
                    $current_step = 3;
                }
            } else if ($current_step == 3) {
                $_SESSION['register_data']['phone_number'] = trim($_POST['phone_number']);
                $_SESSION['register_data']['address'] = trim($_POST['address']);
                $_SESSION['register_data']['date_of_birth'] = trim($_POST['date_of_birth']);

                // Validate step 3
                if (empty($_SESSION['register_data']['phone_number']) || empty($_SESSION['register_data']['address']) ||
                    empty($_SESSION['register_data']['date_of_birth'])) {
                    $error_message = "All fields are required.";
                    $current_step = 3;
                } else {
                    // Process registration
                    $database = new Database();
                    $db = $database->getConnection();

                    try {
                        // Begin transaction
                        $db->beginTransaction();

                        // Check if password exists in session
                        if (!isset($_SESSION['register_data']['password'])) {
                            throw new Exception("Password is missing. Please go back to step 1.");
                        }

                        // Hash password
                        $hashed_password = password_hash($_SESSION['register_data']['password'], PASSWORD_DEFAULT);

                        // Generate verification token
                        $verification_token = bin2hex(random_bytes(32)); // Generate a secure random token

                        // Set default role to Patient (role_id = 3)
                        $role_id = 3;

                        // Insert user
                        $query = "INSERT INTO user (username, email, password_hash, first_name, middle_name, last_name, suffix, 
                                phone_number, address, date_of_birth, role_id, email_verified, verification_token) 
                                VALUES (:username, :email, :password_hash, :first_name, :middle_name, :last_name, :suffix, 
                                :phone_number, :address, :date_of_birth, :role_id, 0, :verification_token)";

                        $stmt = $db->prepare($query);

                        // Bind parameters
                        $stmt->bindParam(":username", $_SESSION['register_data']['username']);
                        $stmt->bindParam(":email", $_SESSION['register_data']['email']);
                        $stmt->bindParam(":password_hash", $hashed_password);
                        $stmt->bindParam(":first_name", $_SESSION['register_data']['first_name']);
                        $stmt->bindParam(":middle_name", $_SESSION['register_data']['middle_name']);
                        $stmt->bindParam(":last_name", $_SESSION['register_data']['last_name']);
                        $stmt->bindParam(":suffix", $_SESSION['register_data']['suffix']);
                        $stmt->bindParam(":phone_number", $_SESSION['register_data']['phone_number']);
                        $stmt->bindParam(":address", $_SESSION['register_data']['address']);
                        $stmt->bindParam(":date_of_birth", $_SESSION['register_data']['date_of_birth']);
                        $stmt->bindParam(":role_id", $role_id);
                        $stmt->bindParam(":verification_token", $verification_token);

                        // Execute query
                        $stmt->execute();

                        // Get the last inserted user ID
                        $user_id = $db->lastInsertId();

                        // Insert patient info
                        $patient_query = "INSERT INTO patient_info (user_id, date_of_birth) 
                                        VALUES (:user_id, :date_of_birth)";

                        $patient_stmt = $db->prepare($patient_query);
                        $patient_stmt->bindParam(":user_id", $user_id);
                        $patient_stmt->bindParam(":date_of_birth", $_SESSION['register_data']['date_of_birth']);
                        $patient_stmt->execute();

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
                            $mail->addAddress($_SESSION['register_data']['email'], $_SESSION['register_data']['first_name'] . ' ' . $_SESSION['register_data']['last_name']);

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
                                        <p>Hello ' . $_SESSION['register_data']['first_name'] . ',</p>
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

                            // Commit transaction
                            $db->commit();

                            // Update success message to include verification instructions
                            $success_message = "Registration successful! A verification email has been sent to your email address. Please check your inbox and spam folder to activate your account.";

                            // Clear registration session data
                            unset($_SESSION['register_data']);
                        } catch (Exception $e) {
                            // Log email error but continue with registration
                            error_log("Mailer Error: " . $mail->ErrorInfo);

                            // Commit transaction anyway since the user is created
                            $db->commit();

                            $success_message = "Registration successful! However, we couldn't send a verification email. Please contact support to activate your account.";

                            // Clear registration session data
                            unset($_SESSION['register_data']);
                        }
                    } catch (Exception $exception) {
                        // Rollback transaction
                        $db->rollBack();
                        $error_message = "Registration failed: " . $exception->getMessage();
                        $current_step = 3;
                    }
                }
            }
        }

        // If going back a step
        if (isset($_POST['back'])) {
            $current_step--;
        }
    }

    // Fill form with session data if available
    if (isset($_SESSION['register_data'])) {
        if (isset($_SESSION['register_data']['username'])) $username = $_SESSION['register_data']['username'];
        if (isset($_SESSION['register_data']['email'])) $email = $_SESSION['register_data']['email'];
        if (isset($_SESSION['register_data']['first_name'])) $first_name = $_SESSION['register_data']['first_name'];
        if (isset($_SESSION['register_data']['middle_name'])) $middle_name = $_SESSION['register_data']['middle_name'];
        if (isset($_SESSION['register_data']['last_name'])) $last_name = $_SESSION['register_data']['last_name'];
        if (isset($_SESSION['register_data']['suffix'])) $suffix = $_SESSION['register_data']['suffix'];
        if (isset($_SESSION['register_data']['phone_number'])) $phone_number = $_SESSION['register_data']['phone_number'];
        if (isset($_SESSION['register_data']['address'])) $address = $_SESSION['register_data']['address'];
    }
    ?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - JRC Dental Care</title>
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
        
        .semi-transparent-sidebar {
            background-color: rgba(255, 255, 255, 0.7); /* White with 70% opacity */
            color: #3C096C !important;
        }
        
        .semi-transparent-sidebar a {
            color: #3C096C !important;
        }
        
        .semi-transparent-sidebar .btn-outline-light {
            color: #3C096C !important;
            border-color: #3C096C !important;
        }
        
        .semi-transparent-sidebar .btn-outline-light:hover {
            background-color: rgba(60, 9, 108, 0.1) !important;
            color: #3C096C !important;
        }
    </style>
</head>
<body class="bg-light" style="background-image: url('images/containerbg.png'); background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed;">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-lg-10">
                <div class="card border-0 shadow-lg overflow-hidden semi-transparent-card" style="border-radius: 1.5rem;">
                    <div class="row g-0">
                        <div class="col-lg-5 d-none d-lg-block semi-transparent-sidebar" style="border-top-left-radius: 1.5rem; border-bottom-left-radius: 1.5rem;">
                            <div class="h-100 d-flex flex-column justify-content-center p-5">
                                <h2 class="fw-bold mb-3">Welcome Back!</h2>
                                <p class="mb-4">Already have an account?</p>
                                <a href="login.php" class="btn btn-outline-light rounded-pill py-2 px-4 mb-4 w-100">Login</a>
                                <div class="mt-auto text-center">
                                    <a href="index.php" class="text-decoration-none">
                                        <i class="fas fa-arrow-left me-2"></i> Back to Home
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div class="card-body p-5">
                                <h3 class="fw-bold mb-4">Create an Account</h3>
                                
                                <?php if(!empty($success_message)): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?php echo $success_message; ?>
                                    <a href="login.php" class="alert-link">Click here to login</a> once you've verified your email.
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php endif; ?>
                                
                                <?php if(empty($success_message)): ?>
                                <!-- Progress Bar -->
                                <div class="mb-4">
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar bg-primary" role="progressbar" 
                                            style="width: <?php echo ($current_step / $total_steps) * 100; ?>%" 
                                            aria-valuenow="<?php echo ($current_step / $total_steps) * 100; ?>" 
                                            aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <div class="d-flex justify-content-between small mt-2">
                                        <span class="<?php echo $current_step >= 1 ? 'text-primary' : 'text-muted'; ?>">Account</span>
                                        <span class="<?php echo $current_step >= 2 ? 'text-primary' : 'text-muted'; ?>">Personal</span>
                                        <span class="<?php echo $current_step >= 3 ? 'text-primary' : 'text-muted'; ?>">Contact</span>
                                    </div>
                                </div>
                                
                                <?php if(!empty($error_message)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?php echo $error_message; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Step 1: Account Information -->
                                <?php if($current_step == 1): ?>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                    <input type="hidden" name="step" value="1">
                                    
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="fas fa-user"></i></span>
                                            <input type="text" class="form-control" id="username" name="username" 
                                                value="<?php echo htmlspecialchars($username); ?>" placeholder="Choose a username" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="fas fa-envelope"></i></span>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                value="<?php echo htmlspecialchars($email); ?>" placeholder="Enter your email" required>
                                        </div>
                                        <small class="text-muted">We'll send a verification email to this address</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="password" name="password" 
                                                placeholder="Create a password" required>
                                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="password-strength mt-2" id="password-strength"></div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="confirm_password" class="form-label">Confirm Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                                placeholder="Confirm your password" required>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <a href="login.php" class="btn btn-outline-secondary">Cancel</a>
                                        <button type="submit" class="btn btn-primary">Next <i class="fas fa-arrow-right ms-1"></i></button>
                                    </div>
                                </form>
                                <?php endif; ?>
                                
                                <!-- Step 2: Personal Information -->
                                <?php if($current_step == 2): ?>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                    <input type="hidden" name="step" value="2">
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6 mb-3 mb-md-0">
                                            <label for="first_name" class="form-label">First Name</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                                value="<?php echo htmlspecialchars($first_name); ?>" placeholder="Enter your first name" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="middle_name" class="form-label">Middle Name (optional)</label>
                                            <input type="text" class="form-control" id="middle_name" name="middle_name" 
                                                value="<?php echo htmlspecialchars($middle_name); ?>" placeholder="Enter your middle name">
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-8 mb-3 mb-md-0">
                                            <label for="last_name" class="form-label">Last Name</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                                value="<?php echo htmlspecialchars($last_name); ?>" placeholder="Enter your last name" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="suffix" class="form-label">Suffix</label>
                                            <select class="form-select" id="suffix" name="suffix">
                                                <option value="" <?php echo $suffix == "" ? "selected" : ""; ?>>None</option>
                                                <option value="Jr." <?php echo $suffix == "Jr." ? "selected" : ""; ?>>Jr.</option>
                                                <option value="Sr." <?php echo $suffix == "Sr." ? "selected" : ""; ?>>Sr.</option>
                                                <option value="I" <?php echo $suffix == "I" ? "selected" : ""; ?>>I</option>
                                                <option value="II" <?php echo $suffix == "II" ? "selected" : ""; ?>>II</option>
                                                <option value="III" <?php echo $suffix == "III" ? "selected" : ""; ?>>III</option>
                                                <option value="IV" <?php echo $suffix == "IV" ? "selected" : ""; ?>>IV</option>
                                                <option value="V" <?php echo $suffix == "V" ? "selected" : ""; ?>>V</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <button type="submit" name="back" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left me-1"></i> Back
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            Next <i class="fas fa-arrow-right ms-1"></i>
                                        </button>
                                    </div>
                                </form>
                                <?php endif; ?>
                                
                                <!-- Step 3: Contact Information -->
                                <?php if($current_step == 3): ?>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                    <input type="hidden" name="step" value="3">
                                    
                                    <div class="mb-3">
                                        <label for="phone_number" class="form-label">Phone Number</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="fas fa-phone"></i></span>
                                            <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                                value="<?php echo htmlspecialchars($phone_number); ?>" placeholder="Enter your phone number" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="fas fa-calendar"></i></span>
                                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="address" class="form-label">Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="fas fa-map-marker-alt"></i></span>
                                            <textarea class="form-control" id="address" name="address" 
                                                    rows="3" placeholder="Enter your address" required><?php echo htmlspecialchars($address); ?></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="termsCheck" required>
                                        <label class="form-check-label" for="termsCheck">
                                            I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms of Service</a> 
                                            and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                                        </label>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <button type="submit" name="back" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left me-1"></i> Back
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            Register <i class="fas fa-user-plus ms-1"></i>
                                        </button>
                                    </div>
                                </form>
                                <?php endif; ?>
                                
                                
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="termsModalLabel">Terms of Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Welcome to JRC Dental Care! These terms and conditions outline the rules and regulations for the use of our services.</p>
                    <!-- Add terms content here -->
                    <h6>1. Acceptance of Terms</h6>
                    <p>By accessing or using our services, you agree to be bound by these Terms and Conditions.</p>
                    
                    <h6>2. User Accounts</h6>
                    <p>When you create an account with us, you must provide information that is accurate, complete, and current at all times.</p>
                    
                    <h6>3. Privacy</h6>
                    <p>Your use of our services is also governed by our Privacy Policy.</p>
                    
                    <h6>4. Termination</h6>
                    <p>We may terminate or suspend your account immediately, without prior notice or liability, for any reason.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Privacy Modal -->
    <div class="modal fade" id="privacyModal" tabindex="-1" aria-labelledby="privacyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="privacyModalLabel">Privacy Policy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>At JRC Dental Care, we take your privacy seriously. This Privacy Policy describes how we collect, use, and share your information.</p>
                    <!-- Add privacy content here -->
                    <h6>1. Information We Collect</h6>
                    <p>We collect information you provide directly to us when you register for an account, schedule an appointment, or contact us.</p>
                    
                    <h6>2. How We Use Your Information</h6>
                    <p>We use the information we collect to provide, maintain, and improve our services, communicate with you, and for other legitimate purposes.</p>
                    
                    <h6>3. Information Sharing</h6>
                    <p>We may share your information with third parties only for the purposes described in this Privacy Policy or as required by law.</p>

                    <h6>4. Data Retention</h6>
                    <p>Your personal data will be retained for as long as necessary to fulfill the purposes outlined in this Privacy Policy, unless a longer retention period is required or permitted by law.</p>

                    <h6>5. Security</h6>
                    <p>We take appropriate technical and organizational measures to protect your personal data against unauthorized access, disclosure, alteration, and destruction.</p>

                    <h6>6. Your Rights</h6>
                    <p>You have the right to access, correct, and delete your personal data, as well as other rights under applicable privacy laws.</p>

                    <h6>7. Changes to This Policy</h6>
                    <p>We may update our Privacy Policy from time to time. We will notify you of any significant changes by posting the new policy on this page.</p>

                    <h6>8. Contact Us</h6>
                    <p>If you have any questions about this Privacy Policy, please contact us at support@jrcdentalcare.com.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

    <!-- Custom JS -->
    <script src="assets/js/script.js"></script>

    <!-- Password Strength Validation -->
    <script>
    // Show/Hide password functionality
    const togglePassword = document.getElementById('togglePassword');
    const passwordField = document.getElementById('password');
    
    if (togglePassword && passwordField) {
        togglePassword.addEventListener('click', function () {
            const type = passwordField.type === 'password' ? 'text' : 'password';
            passwordField.type = type;
            const icon = this.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            }
        });
    }

    // Password strength indicator
    const passwordStrength = document.getElementById('password-strength');
    if (passwordField && passwordStrength) {
        passwordField.addEventListener('input', function () {
            const password = passwordField.value;
            let strength = 0;
            let message = '';
            
            if (password.length >= 8) strength += 25;
            if (password.match(/[a-z]+/)) strength += 25;
            if (password.match(/[A-Z]+/)) strength += 25;
            if (password.match(/[0-9]+/)) strength += 25;
            
            if (strength === 0) {
                passwordStrength.innerHTML = '';
            } else if (strength <= 25) {
                message = '<span class="text-danger">Weak</span>';
            } else if (strength <= 50) {
                message = '<span class="text-warning">Fair</span>';
            } else if (strength <= 75) {
                message = '<span class="text-info">Good</span>';
            } else {
                message = '<span class="text-success">Strong</span>';
            }
            
            passwordStrength.innerHTML = message;
        });
    }
    </script>
</body>
</html>