<?php
session_start();
include_once 'config/database.php';

// Redirect if already logged in
if(isset($_SESSION['user_id'])) {
    header("Location: " . $_SESSION['role_name'] . "-dashboard.php");
    exit();
}

$error_message = "";

// Process login form submission
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // Validate input
    if(empty($username) || empty($password)) {
        $error_message = "Please enter both username and password.";
    } else {
        // Connect to database
        $database = new Database();
        $db = $database->getConnection();
        
        // Prepare query
        $query = "SELECT u.user_id, u.username, u.password_hash, u.first_name, u.last_name, u.email, 
        u.email_verified, r.role_id, r.role_name 
        FROM user u 
        JOIN userrole r ON u.role_id = r.role_id 
        WHERE u.username = :username";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->execute();
        
        // Check if user exists
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            if(password_verify($password, $row['password_hash'])) {
                // Check if email is verified
                if(!$row['email_verified']) {
                    $error_message = "Your email is not verified. Please check your email for a verification link or <a href='email-verification.php?resend=1&email=" . urlencode($row['email']) . "'>click here</a> to resend the verification email.";
                } else {
                    // Set session variables and proceed with login as normal
                    $_SESSION['user_id'] = $row['user_id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['first_name'] = $row['first_name'];
                    $_SESSION['last_name'] = $row['last_name'];
                    $_SESSION['email'] = $row['email'];
                    $_SESSION['role_id'] = $row['role_id'];
                    $_SESSION['role_name'] = strtolower($row['role_name']);
                    
                    // Log successful login
                    $audit_query = "INSERT INTO audittrail (user_id, action_performed, table_affected, details, ip_address) 
                                    VALUES (:user_id, 'LOGIN', 'user', 'User logged in successfully', :ip_address)";
                    $audit_stmt = $db->prepare($audit_query);
                    $audit_stmt->bindParam(':user_id', $row['user_id']);
                    $audit_stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
                    $audit_stmt->execute();
                    
                    // Redirect to appropriate dashboard
                    header("Location: " . strtolower($row['role_name']) . "-dashboard.php");
                    exit();
                }
            } else {
                $error_message = "Invalid password.";
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
    <title>Login - JRC Dental Care</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        /* Add custom styles to make the card semi-transparent */
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
                        <div class="col-lg-5 semi-transparent-sidebar d-flex flex-column justify-content-center p-5" style="border-top-left-radius: 1.5rem; border-bottom-left-radius: 1.5rem;">
                            <h2 class="fw-bold mb-3">Hello, Welcome!</h2>
                            <p class="mb-4">Don't have an account?</p>
                            <a href="register.php" class="btn btn-outline-light rounded-pill py-2 px-4 mb-4 w-100">Register</a>
                            <div class="mt-auto text-center">
                                <a href="index.php" class="text-decoration-none">
                                    <i class="fas fa-arrow-left me-2"></i> Back to Home
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div class="card-body p-5">
                                <h3 class="fw-bold mb-4">Login</h3>
                                
                                <?php if(!empty($error_message)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?php echo $error_message; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php endif; ?>
                                
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="fas fa-user"></i></span>
                                            <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4 text-end">
                                        <a href="forgot-password.php" class="text-decoration-none" style="color: #3C096C;">Forgot password?</a>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary py-2">Login</button>
                                    </div>
                                </form>
                            </div>
                        </div>
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
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
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
    </script>
</body>
</html>