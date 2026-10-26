<?php
/**
 * JRC Dental Care - Header Component
 * Contains the navigation and header elements 
 */
?>

<header class="header">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top">
        <div class="container">
            <!-- Logo -->
            <a class="navbar-brand" href="index.php">
                <img src="assets/images/jrclogo.png" alt="JRC Dental Care Logo" height="50" class="logo">
            </a>
            
            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Navigation Links -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' || basename($_SERVER['PHP_SELF']) == '') ? 'active' : ''; ?>" href="index.php#home" data-scroll>Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'services.php') ? 'active' : ''; ?>" href="index.php#services" data-scroll>Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'about.php') ? 'active' : ''; ?>" href="index.php#about" data-scroll>About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'contact.php') ? 'active' : ''; ?>" href="index.php#contact" data-scroll>Contact</a>
                    </li>
                    
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <!-- User is logged in -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" 
                               data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="me-1"><i class="fas fa-user-circle"></i></span>
                                <?php echo htmlspecialchars($_SESSION['first_name']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-lg" aria-labelledby="navbarDropdown">
                                <li>
                                    <div class="dropdown-header px-4 py-2 border-bottom">
                                        <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
                                    </div>
                                </li>
                                <li><a class="dropdown-item" href="<?php echo strtolower($_SESSION['role_name']); ?>-dashboard.php">
                                    <i class="fas fa-tachometer-alt me-2 text-primary"></i> Dashboard
                                </a></li>
                                <li><a class="dropdown-item" href="appointments.php">
                                    <i class="fas fa-calendar-check me-2 text-primary"></i> My Appointments
                                </a></li>
                                <li><a class="dropdown-item" href="profile.php">
                                    <i class="fas fa-user-cog me-2 text-primary"></i> Profile Settings
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-2 text-danger"></i> Logout
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- User is not logged in -->
                        <li class="nav-item ms-2">
                            <a class="btn btn-primary rounded-pill px-4 py-2 shadow-sm" href="login.php">
                                <i class="fas fa-sign-in-alt me-1"></i> Login
                            </a>
                        </li>
                        <li class="nav-item ms-2">
                            <a class="btn btn-outline-primary rounded-pill px-4 py-2 shadow-sm" href="register.php">
                                <i class="fas fa-user-plus me-1"></i> Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Notification area for displaying session messages -->
    <?php if(isset($_SESSION['success_message'])): ?>
        <div class="notification-area">
            <div class="container">
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error_message'])): ?>
        <div class="notification-area">
            <div class="container">
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
</header>