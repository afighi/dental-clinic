<?php
session_start();
include_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JRC Dental Care - Your Smile, Our Priority</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="images/jrclogo.png" alt="JRC Dental Care Logo" height="50">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#services">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php echo $_SESSION['first_name']; ?>
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="<?php echo $_SESSION['role_name']; ?>-dashboard.php">Dashboard</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link btn btn-primary text-white px-4 ms-2" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-outline-primary px-4 ms-2" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section - Modified for full-width banner -->
<section id="home" class="hero">
    <div class="hero-banner" style="background-image: url('images/dentalbanner.png');">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 text-center text-lg-start hero-content">
                    <h1 class="display-4 fw-bold mb-4">Your Smile, Our Priority</h1>
                    <p class="lead mb-4">JRC Dental Care offers comprehensive dental services with a focus on patient comfort and modern techniques.</p>
                    <div class="d-grid gap-3 d-sm-flex justify-content-sm-start">
                        <a href="#services" class="btn btn-primary btn-lg px-4 me-sm-3">Our Services</a>
                        <a href="register.php" class="btn btn-outline-primary btn-lg px-4">Book Appointment</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

    <!-- Services Section -->
    <section id="services" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Our Services</h2>
                <p class="lead">Comprehensive dental care for all your needs</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon bg-primary bg-opacity-10 rounded-circle mb-4 mx-auto">
                                <i class="fas fa-tooth text-primary fa-2x p-3"></i>
                            </div>
                            <h5 class="fw-bold">General Dentistry</h5>
                            <p class="text-muted">Regular check-ups, cleanings, and preventive care to maintain your oral health.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon bg-primary bg-opacity-10 rounded-circle mb-4 mx-auto">
                                <i class="fas fa-smile text-primary fa-2x p-3"></i>
                            </div>
                            <h5 class="fw-bold">Cosmetic Dentistry</h5>
                            <p class="text-muted">Enhance your smile with veneers, whitening, and cosmetic procedures.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon bg-primary bg-opacity-10 rounded-circle mb-4 mx-auto">
                                <i class="fas fa-teeth-open text-primary fa-2x p-3"></i>
                            </div>
                            <h5 class="fw-bold">Orthodontics</h5>
                            <p class="text-muted">Straighten your teeth with braces and clear aligners for a perfect smile.</p>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </section>

    <!-- About Us Section -->
    <section id="about" class="py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <img src="images/interior.jpg" alt="About JRC Dental Care" class="img-fluid rounded shadow">
                </div>
                <div class="col-lg-6">
                    <h2 class="fw-bold mb-4">About JRC Dental Care</h2>
                    <p class="lead mb-4">JRC Dental Care has been providing exceptional dental services to our community for over 15 years.</p>
                    <p>Our mission is to provide top-quality dental care in a comfortable and welcoming environment. We use the latest technology and techniques to ensure our patients receive the best possible care.</p>
                    <p>Our team of experienced dentists and staff are dedicated to your oral health and well-being. We believe in treating each patient with compassion, respect, and personalized attention.</p>
                    <div class="d-flex mt-4">
                        <div class="me-4">
                            <h3 class="fw-bold text-primary">15+</h3>
                            <p class="text-muted">Years Experience</p>
                        </div>
                        <div class="me-4">
                            <h3 class="fw-bold text-primary">10k+</h3>
                            <p class="text-muted">Happy Patients</p>
                        </div>
                        <div>
                            <h3 class="fw-bold text-primary">15</h3>
                            <p class="text-muted">Expert Dentists</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">What Our Patients Say</h2>
                <p class="lead">Read testimonials from our satisfied patients</p>
            </div>
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="text-warning me-2">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                </div>
                                <span class="text-muted">5.0</span>
                            </div>
                            <p class="mb-4">"I've been coming to JRC Dental Care for years, and I'm always impressed by the professionalism and quality of care I receive. The staff is friendly and makes me feel comfortable."</p>
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">JD</div>
                                <div class="ms-3">
                                    <h6 class="fw-bold mb-0">John Doe</h6>
                                    <small class="text-muted">Patient since 2018</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="text-warning me-2">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                </div>
                                <span class="text-muted">5.0</span>
                            </div>
                            <p class="mb-4">"Dr. Smith and the team at JRC Dental Care made my dental experience painless and stress-free. They explained everything clearly and made sure I was comfortable throughout the procedure."</p>
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">JS</div>
                                <div class="ms-3">
                                    <h6 class="fw-bold mb-0">Jane Smith</h6>
                                    <small class="text-muted">Patient since 2020</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="text-warning me-2">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star-half-alt"></i>
                                </div>
                                <span class="text-muted">4.5</span>
                            </div>
                            <p class="mb-4">"I had a dental emergency, and JRC Dental Care got me in right away. They fixed the problem and followed up to make sure I was doing well. I highly recommend their services to anyone."</p>
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">RJ</div>
                                <div class="ms-3">
                                    <h6 class="fw-bold mb-0">Robert Johnson</h6>
                                    <small class="text-muted">Patient since 2019</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <h2 class="fw-bold mb-4">Contact Us</h2>
                    <p class="lead mb-4">We'd love to hear from you. Fill out the form and we'll get back to you as soon as possible.</p>
                    <div class="mb-4">
                        <h5 class="fw-bold">Office Hours</h5>
                        <p class="mb-1">Monday - Friday: 9:00 AM - 6:00 PM</p>
                        <p class="mb-1">Saturday: 9:00 AM - 3:00 PM</p>
                        <p>Sunday: Closed</p>
                    </div>
                    <div class="mb-4">
                        <h5 class="fw-bold">Location</h5>
                        <p class="mb-1">123 Dental Street, Suite 100</p>
                        <p>City, State 12345</p>
                    </div>
                    <div>
                        <h5 class="fw-bold">Contact Information</h5>
                        <p class="mb-1">Phone: (123) 456-7890</p>
                        <p class="mb-1">Email: info@jrcdentalcare.com</p>
                    </div>
                </div>
                
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="py-5 bg-primary text-white">
        <div class="container text-center">
            <h2 class="fw-bold mb-4">Ready to Schedule Your Visit?</h2>
            <p class="lead mb-4">Take the first step towards a healthier smile today.</p>
            <a href="register.php" class="btn btn-light btn-lg px-5">Book an Appointment</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <img src="images/whitelogo3.png" alt="JRC Dental Care Logo" height="100" class="mb-3">
                    <p>JRC Dental Care is committed to providing top-quality dental care in a comfortable and welcoming environment.</p>
                    <div class="social-icons mt-3">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-4 mb-md-0">
                    <h5 class="fw-bold mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#home" class="text-white text-decoration-none">Home</a></li>
                        <li class="mb-2"><a href="#services" class="text-white text-decoration-none">Services</a></li>
                        <li class="mb-2"><a href="#about" class="text-white text-decoration-none">About Us</a></li>
                        <li><a href="#contact" class="text-white text-decoration-none">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                    <h5 class="fw-bold mb-3">Our Services</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none">General Dentistry</a></li>
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none">Cosmetic Dentistry</a></li>
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none">Orthodontics</a></li>
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none">Pediatric Dentistry</a></li>
                        <li><a href="#" class="text-white text-decoration-none">Emergency Care</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h5 class="fw-bold mb-3">Contact Info</h5>
                    <p class="mb-2"><i class="fas fa-map-marker-alt me-2"></i> 123 Dental Street, Suite 100, City, State 12345</p>
                    <p class="mb-2"><i class="fas fa-phone-alt me-2"></i> (123) 456-7890</p>
                    <p class="mb-2"><i class="fas fa-envelope me-2"></i> info@jrcdentalcare.com</p>
                    <p><i class="fas fa-clock me-2"></i> Mon-Fri: 9am-6pm, Sat: 9am-3pm</p>
                </div>
            </div>
            <hr class="my-4">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; 2025 JRC Dental Care. All Rights Reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="#" class="text-white text-decoration-none me-3">Privacy Policy</a>
                    <a href="#" class="text-white text-decoration-none">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
</body>
</html>