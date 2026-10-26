<?php
/**
 * JRC Dental Care - Footer Component
 * Contains the footer section with contact information and social links
 */
?>

<footer class="footer pt-5">
    <div class="container">
        <!-- Main Footer -->
        <div class="row">
            <!-- Logo and About -->
            <div class="col-lg-4 mb-4 mb-lg-0">
                <div class="footer-logo mb-4">
                    <img src="assets/images/whitelogo3.png" alt="JRC Dental Care Logo" height="80" class="img-fluid">
                </div>
                <p class="text-white-50 mb-4">JRC Dental Care is committed to providing top-quality dental care with a focus on patient comfort and using the latest techniques and technology.</p>
                <div class="social-icons d-flex">
                    <a href="#" class="me-3" aria-label="Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="me-3" aria-label="Twitter">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="me-3" aria-label="Instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" aria-label="LinkedIn">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-2 col-md-6 mb-4 mb-md-0">
                <h5 class="text-white mb-4">Quick Links</h5>
                <ul class="list-unstyled footer-links">
                    <li class="mb-3">
                        <a href="index.php#home" class="d-flex align-items-center">
                            <i class="fas fa-chevron-right me-2 text-primary small"></i>
                            <span>Home</span>
                        </a>
                    </li>
                    <li class="mb-3">
                        <a href="index.php#services" class="d-flex align-items-center">
                            <i class="fas fa-chevron-right me-2 text-primary small"></i>
                            <span>Services</span>
                        </a>
                    </li>
                    <li class="mb-3">
                        <a href="index.php#about" class="d-flex align-items-center">
                            <i class="fas fa-chevron-right me-2 text-primary small"></i>
                            <span>About Us</span>
                        </a>
                    </li>
                    <li>
                        <a href="index.php#contact" class="d-flex align-items-center">
                            <i class="fas fa-chevron-right me-2 text-primary small"></i>
                            <span>Contact</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Services -->
            <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                <h5 class="text-white mb-4">Our Services</h5>
                <ul class="list-unstyled footer-links">
                    <li class="mb-3">
                        <a href="#" class="d-flex align-items-center">
                            <i class="fas fa-tooth me-2 text-primary small"></i>
                            <span>General Dentistry</span>
                        </a>
                    </li>
                    <li class="mb-3">
                        <a href="#" class="d-flex align-items-center">
                            <i class="fas fa-smile me-2 text-primary small"></i>
                            <span>Cosmetic Dentistry</span>
                        </a>
                    </li>
                    <li class="mb-3">
                        <a href="#" class="d-flex align-items-center">
                            <i class="fas fa-teeth-open me-2 text-primary small"></i>
                            <span>Orthodontics</span>
                        </a>
                    </li>
                    <li class="mb-3">
                        <a href="#" class="d-flex align-items-center">
                            <i class="fas fa-baby me-2 text-primary small"></i>
                            <span>Pediatric Dentistry</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="d-flex align-items-center">
                            <i class="fas fa-first-aid me-2 text-primary small"></i>
                            <span>Emergency Care</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Contact Info -->
            <div class="col-lg-3 col-md-6">
                <h5 class="text-white mb-4">Contact Info</h5>
                <ul class="list-unstyled contact-info">
                    <li class="d-flex mb-3">
                        <div class="icon-box me-3">
                            <i class="fas fa-map-marker-alt text-primary"></i>
                        </div>
                        <div>
                            <p class="text-white-50 mb-0">123 Dental Street, Suite 100</p>
                            <p class="text-white-50 mb-0">City, State 12345</p>
                        </div>
                    </li>
                    <li class="d-flex mb-3">
                        <div class="icon-box me-3">
                            <i class="fas fa-phone-alt text-primary"></i>
                        </div>
                        <div>
                            <p class="text-white-50 mb-0">(123) 456-7890</p>
                        </div>
                    </li>
                    <li class="d-flex mb-3">
                        <div class="icon-box me-3">
                            <i class="fas fa-envelope text-primary"></i>
                        </div>
                        <div>
                            <p class="text-white-50 mb-0">info@jrcdentalcare.com</p>
                        </div>
                    </li>
                    <li class="d-flex">
                        <div class="icon-box me-3">
                            <i class="fas fa-clock text-primary"></i>
                        </div>
                        <div>
                            <p class="text-white-50 mb-0">Mon-Fri: 9am-6pm</p>
                            <p class="text-white-50 mb-0">Sat: 9am-3pm, Sun: Closed</p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Footer Bottom -->
        <hr class="footer-divider my-4">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <p class="mb-0 text-white-50">&copy; <?php echo date('Y'); ?> JRC Dental Care. All Rights Reserved.</p>
            </div>
            <div class="col-md-6 text-center text-md-end mt-3 mt-md-0">
                <a href="#" class="text-white-50 text-decoration-none me-3">Privacy Policy</a>
                <a href="#" class="text-white-50 text-decoration-none">Terms of Service</a>
            </div>
        </div>
    </div>

    <!-- Scroll to Top Button -->
    <button class="scroll-to-top">
        <i class="fas fa-arrow-up"></i>
    </button>
</footer>

<!-- Cookie Consent -->
<div class="cookie-consent-banner animate slide-in-up" id="cookieConsentBanner" style="display: none;">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
            <div class="cookie-text mb-3 mb-md-0">
                <p class="mb-0">
                    We use cookies to enhance your experience. By continuing to visit this site you agree to our use of cookies.
                    <a href="#" class="text-decoration-underline">Learn more</a>
                </p>
            </div>
            <div class="cookie-buttons d-flex">
                <button class="btn btn-light me-2" id="cookieRejectBtn">Reject All</button>
                <button class="btn btn-primary" id="cookieAcceptBtn">Accept All</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Custom JS -->
<script src="assets/js/animations.js"></script>
<script src="assets/js/ui-components.js"></script>
<script src="assets/js/form-validation.js"></script>

<!-- Initialize cookie consent -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Check if cookie consent is already given
        if (!localStorage.getItem('cookieConsent')) {
            setTimeout(function() {
                document.getElementById('cookieConsentBanner').style.display = 'block';
            }, 1000);
        }
        
        // Accept cookies
        document.getElementById('cookieAcceptBtn').addEventListener('click', function() {
            localStorage.setItem('cookieConsent', 'accepted');
            document.getElementById('cookieConsentBanner').style.display = 'none';
        });
        
        // Reject cookies
        document.getElementById('cookieRejectBtn').addEventListener('click', function() {
            localStorage.setItem('cookieConsent', 'rejected');
            document.getElementById('cookieConsentBanner').style.display = 'none';
        });
    });
</script>

<!-- Page specific scripts -->
<?php if(isset($extraScripts)) echo $extraScripts; ?>