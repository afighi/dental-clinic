<?php
session_start();

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_role = $is_logged_in ? $_SESSION['role_name'] : 'Guest';
$user_name = $is_logged_in ? $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] : 'Guest';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access - JRC Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f8f9fc;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .unauthorized-container {
            max-width: 600px;
            text-align: center;
            padding: 2rem;
        }
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background-color: #e1a2c8;
            color: white;
            text-align: center;
            padding: 1.5rem;
            border-radius: 1rem 1rem 0 0 !important;
        }
        .btn-primary {
            background-color: #1806a8;
            border-color: #1806a8;
        }
        .btn-primary:hover {
            background-color: #1404a0;
            border-color: #1404a0;
        }
        .logo {
            max-width: 100px;
            margin-bottom: 1rem;
        }
        .icon-large {
            font-size: 5rem;
            color: #e1a2c8;
            margin-bottom: 1rem;
        }
        .card-body {
            padding: 3rem 2rem;
        }
    </style>
</head>
<body>
    <div class="unauthorized-container">
        <div class="card">
            <div class="card-header">
                <img src="assets/images/jrclogo.png" alt="JRC Dental Care" class="logo">
                <h4>JRC Dental Care</h4>
            </div>
            <div class="card-body">
                <div class="icon-large">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h2 class="mb-4">Unauthorized Access</h2>
                <p class="lead mb-4">Sorry, <?php echo $user_name; ?>, you don't have permission to access this page.</p>
                <p class="mb-4">Your current role (<strong><?php echo $user_role; ?></strong>) doesn't have the necessary privileges to view this content.</p>
                <div class="d-grid gap-2 col-6 mx-auto">
                    <?php if ($is_logged_in): ?>
                        <a href="javascript:history.back()" class="btn btn-outline-secondary mb-2">
                            <i class="fas fa-arrow-left me-2"></i>Go Back
                        </a>
                        <?php if ($user_role === 'Admin' || $user_role === 'Owner Dentist'): ?>
                            <a href="admin-dashboard.php" class="btn btn-primary mb-2">
                                <i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard
                            </a>
                        <?php elseif ($user_role === 'Dentist'): ?>
                            <a href="dentist-dashboard.php" class="btn btn-primary mb-2">
                                <i class="fas fa-tachometer-alt me-2"></i>Dentist Dashboard
                            </a>
                        <?php elseif ($user_role === 'Staff'): ?>
                            <a href="staff-dashboard.php" class="btn btn-primary mb-2">
                                <i class="fas fa-tachometer-alt me-2"></i>Staff Dashboard
                            </a>
                        <?php else: ?>
                            <a href="patient-dashboard.php" class="btn btn-primary mb-2">
                                <i class="fas fa-tachometer-alt me-2"></i>Patient Dashboard
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary mb-2">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="text-center mt-3">
            <small class="text-muted">&copy; <?php echo date('Y'); ?> JRC Dental Care. All rights reserved.</small>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>