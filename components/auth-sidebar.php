<?php
/**
 * JRC Dental Care - Auth Sidebar Component
 * Used in login, registration and password reset pages
 * 
 * @param string $type - Type of auth page (login, register, forgot-password)
 */

// Set default type if not provided
$type = isset($type) ? $type : 'login';

// Determine content based on page type
switch($type) {
    case 'register':
        $title = "Welcome Back!";
        $subtitle = "Already have an account?";
        $buttonText = "Login";
        $buttonLink = "login.php";
        $backgroundClass = "bg-gradient-animated";
        break;
    case 'forgot-password':
        $title = "Remembered Password?";
        $subtitle = "Return to login";
        $buttonText = "Login";
        $buttonLink = "login.php";
        $backgroundClass = "bg-gradient-animated";
        break;
    case 'login':
    default:
        $title = "Hello, Welcome!";
        $subtitle = "Don't have an account?";
        $buttonText = "Register";
        $buttonLink = "register.php";
        $backgroundClass = "bg-gradient-animated";
        break;
}
?>

<div class="auth-sidebar <?php echo $backgroundClass; ?> h-100 d-flex flex-column justify-content-center text-white p-5">
    <div class="animate fade-in">
        <div class="text-center mb-4">
            <img src="assets/images/whitelogo3.png" alt="JRC Dental Care" class="img-fluid mb-4" style="max-height: 80px;">
        </div>
        
        <h2 class="fw-bold mb-3"><?php echo $title; ?></h2>
        <p class="mb-4"><?php echo $subtitle; ?></p>
        
        <a href="<?php echo $buttonLink; ?>" class="btn btn-outline-light rounded-pill py-2 px-4 mb-4 w-100 hover-scale">
            <?php echo $buttonText; ?>
        </a>
        
        <?php if($type == 'register'): ?>
        <div class="mt-5">
            <div class="testimonial-mini p-3 bg-white bg-opacity-10 rounded-lg mb-4">
                <p class="text-white mb-2 fst-italic">
                    <i class="fas fa-quote-left me-2 opacity-50"></i>
                    JRC Dental made my dental experience comfortable and stress-free. Highly recommended!
                </p>
                <div class="d-flex align-items-center">
                    <div class="avatar-circle bg-primary">JS</div>
                    <div class="ms-2">
                        <p class="mb-0 small fw-bold">Jane Smith</p>
                        <p class="mb-0 small opacity-75">Patient since 2020</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="mt-auto text-center">
            <a href="index.php" class="text-white text-decoration-none d-inline-flex align-items-center">
                <i class="fas fa-arrow-left me-2"></i> Back to Home
            </a>
        </div>
    </div>
</div>

<style>
    .avatar-circle {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 12px;
        color: white;
    }
</style>