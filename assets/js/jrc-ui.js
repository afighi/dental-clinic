/**
 * JRC Dental Care - UI Enhancement Scripts
 * Advanced animations and interactive effects
 */

document.addEventListener('DOMContentLoaded', function() {
    "use strict";
    
    // Initialize AOS (Animate On Scroll)
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 1000,
            easing: 'ease-in-out',
            once: true,
            mirror: false,
            offset: 50
        });
    }
    
    // Navbar scroll behavior with class change
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
        
        // Trigger on page load to handle refresh midway down page
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        }
    }
    
    // Custom navbar toggler animation
    const navbarToggler = document.querySelector('.navbar-toggler');
    if (navbarToggler) {
        navbarToggler.innerHTML = `
            <div class="navbar-toggler-icon">
                <span></span>
                <span></span>
                <span></span>
                <span></span>
            </div>
        `;
    }
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]:not([href="#"])').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                const headerOffset = navbar ? navbar.offsetHeight : 0;
                const elementPosition = targetElement.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - headerOffset - 20;
                
                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
                
                // Update active state in navigation
                document.querySelectorAll('.nav-link').forEach(navLink => {
                    navLink.classList.remove('active');
                });
                this.classList.add('active');
            }
        });
    });
    
    // Active navigation based on scroll position
    function updateActiveNavOnScroll() {
        const sections = document.querySelectorAll('section[id]');
        const scrollY = window.pageYOffset;
        
        sections.forEach(section => {
            const sectionHeight = section.offsetHeight;
            const sectionTop = section.offsetTop - 100;
            const sectionId = section.getAttribute('id');
            
            if (scrollY > sectionTop && scrollY <= sectionTop + sectionHeight) {
                document.querySelector('.nav-link[href="#' + sectionId + '"]')?.classList.add('active');
            } else {
                document.querySelector('.nav-link[href="#' + sectionId + '"]')?.classList.remove('active');
            }
        });
    }
    
    window.addEventListener('scroll', updateActiveNavOnScroll);
    updateActiveNavOnScroll();
    
    // Back to top button
    const backToTopButton = document.querySelector('.back-to-top');
    if (backToTopButton) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                backToTopButton.classList.add('active');
            } else {
                backToTopButton.classList.remove('active');
            }
        });
        
        backToTopButton.addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
    
    // Add floating shapes to hero section
    const heroSection = document.querySelector('.hero');
    if (heroSection) {
        const floatingShapes = document.createElement('div');
        floatingShapes.className = 'floating-shapes';
        
        for (let i = 0; i < 5; i++) {
            const shape = document.createElement('div');
            shape.className = 'shape';
            floatingShapes.appendChild(shape);
        }
        
        heroSection.appendChild(floatingShapes);
    }
    
    // Password visibility toggle
    const passwordToggles = document.querySelectorAll('.password-toggle');
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const input = this.closest('.input-group').querySelector('input');
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    
    // Password strength meter
    const passwordInputs = document.querySelectorAll('input[type="password"][id$="password"]:not([id$="confirm_password"])');
    passwordInputs.forEach(input => {
        // Create or get strength meter elements
        let strengthMeter = document.querySelector('.password-strength-meter');
        let strengthText = document.querySelector('.password-strength-text');
        
        if (!strengthMeter) {
            const strengthContainer = document.querySelector('.password-strength') || 
                                     input.closest('.auth-form-group').querySelector('.password-strength');
            
            if (strengthContainer) {
                strengthMeter = document.createElement('div');
                strengthMeter.className = 'password-strength-meter';
                strengthContainer.appendChild(strengthMeter);
                
                strengthText = document.createElement('div');
                strengthText.className = 'password-strength-text';
                strengthContainer.after(strengthText);
            }
        }
        
        if (strengthMeter && strengthText) {
            input.addEventListener('input', function() {
                const password = this.value;
                updatePasswordStrength(password, strengthMeter, strengthText);
            });
        }
    });
    
    function updatePasswordStrength(password, meter, text) {
        // Calculate password strength
        let strength = 0;
        let message = '';
        
        if (password.length >= 8) strength += 25;
        if (password.match(/[a-z]+/)) strength += 25;
        if (password.match(/[A-Z]+/)) strength += 25;
        if (password.match(/[0-9]+/)) strength += 25;
        
        // Reset classes
        meter.className = 'password-strength-meter';
        
        // Update UI based on strength
        if (password.length === 0) {
            meter.style.width = '0';
            text.innerHTML = '';
        } else if (strength <= 25) {
            meter.classList.add('weak');
            text.innerHTML = '<span style="color: var(--danger)">Weak</span> - Add letters, numbers & capitals';
        } else if (strength <= 50) {
            meter.classList.add('medium');
            text.innerHTML = '<span style="color: var(--warning)">Fair</span> - Add more variety';
        } else if (strength <= 75) {
            meter.classList.add('good');
            text.innerHTML = '<span style="color: var(--primary)">Good</span> - Almost there!';
        } else {
            meter.classList.add('strong');
            text.innerHTML = '<span style="color: var(--success)">Strong</span> - Great password!';
        }
    }
    
    // Multi-step form handling
    const multiStepForm = document.querySelector('.multi-step-form');
    if (multiStepForm) {
        const formSteps = multiStepForm.querySelectorAll('.form-step');
        const stepContents = multiStepForm.querySelectorAll('.step-content');
        const nextButtons = multiStepForm.querySelectorAll('.btn-next-step');
        const prevButtons = multiStepForm.querySelectorAll('.btn-prev-step');
        
        // Function to show specific step
        function showStep(stepIndex) {
            // Update step indicators
            formSteps.forEach((step, index) => {
                if (index < stepIndex) {
                    step.classList.add('completed');
                    step.classList.remove('active');
                } else if (index === stepIndex) {
                    step.classList.add('active');
                    step.classList.remove('completed');
                } else {
                    step.classList.remove('active', 'completed');
                }
            });
            
            // Show only active step content
            stepContents.forEach((content, index) => {
                content.classList.toggle('active', index === stepIndex);
                
                if (index === stepIndex) {
                    content.style.display = 'block';
                    // Add animation
                    content.classList.add('fadeIn');
                    setTimeout(() => {
                        content.classList.remove('fadeIn');
                    }, 1000);
                } else {
                    content.style.display = 'none';
                }
            });
            
            // Update progress bar if exists
            const progressBar = multiStepForm.querySelector('.progress-bar');
            if (progressBar) {
                const progress = ((stepIndex + 1) / formSteps.length) * 100;
                progressBar.style.width = `${progress}%`;
            }
        }
        
        // Handle next button clicks
        nextButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const currentStep = parseInt(this.getAttribute('data-current-step'));
                const form = this.closest('form');
                
                // Validate current step if form validation is needed
                let isValid = true;
                
                if (form.classList.contains('needs-validation')) {
                    const inputs = stepContents[currentStep].querySelectorAll('input, select, textarea');
                    
                    inputs.forEach(input => {
                        if (input.hasAttribute('required') && !input.value) {
                            input.classList.add('is-invalid');
                            isValid = false;
                        } else {
                            input.classList.remove('is-invalid');
                        }
                    });
                }
                
                if (isValid && currentStep < formSteps.length - 1) {
                    showStep(currentStep + 1);
                }
            });
        });
        
        // Handle previous button clicks
        prevButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const currentStep = parseInt(this.getAttribute('data-current-step'));
                
                if (currentStep > 0) {
                    showStep(currentStep - 1);
                }
            });
        });
        
        // Initialize first step
        if (formSteps.length > 0) {
            // Check if any step has the active class already
            const activeStep = multiStepForm.querySelector('.form-step.active');
            if (activeStep) {
                const stepIndex = Array.from(formSteps).indexOf(activeStep);
                showStep(stepIndex);
            } else {
                showStep(0);
            }
        }
    }
    
    // OTP input enhancement
    const otpInput = document.querySelector('.otp-input');
    if (otpInput) {
        // Focus input on load
        setTimeout(() => {
            otpInput.focus();
        }, 500);
        
        // Only allow numbers
        otpInput.addEventListener('keypress', function(e) {
            if (!/[0-9]/.test(e.key)) {
                e.preventDefault();
            }
        });
        
        // Format OTP as user types
        otpInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 6);
            
            // Auto-submit when 6 digits entered
            if (this.value.length === 6) {
                // Add visual feedback
                this.classList.add('is-valid');
                
                // Pulse animation on the button
                const submitBtn = this.closest('form').querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.classList.add('pulse');
                }
                
                // Auto-submit with delay
                setTimeout(() => {
                    this.closest('form').submit();
                }, 800);
            } else {
                this.classList.remove('is-valid');
            }
        });
    }
    
    // OTP timer countdown
    const otpTimer = document.querySelector('.otp-timer');
    if (otpTimer) {
        let timeLeft = 60; // 60 seconds
        
        const timerInterval = setInterval(() => {
            timeLeft--;
            
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                otpTimer.innerHTML = 'You can <a href="#" class="resend-otp">resend OTP</a> now';
                
                // Add event listener to resend link
                document.querySelector('.resend-otp').addEventListener('click', function(e) {
                    e.preventDefault();
                    // Handle OTP resend
                    // You can add AJAX call or form submission here
                });
            } else {
                otpTimer.textContent = `Resend OTP in ${timeLeft} seconds`;
            }
        }, 1000);
    }
    
    // Success checkmark animation
    const successCheckmarks = document.querySelectorAll('.success-checkmark');
    successCheckmarks.forEach(checkmark => {
        if (!checkmark.classList.contains('animated')) {
            checkmark.classList.add('animated');
            
            // Create SVG checkmark dynamically
            checkmark.innerHTML = `
                <div class="checkmark-circle">
                    <div class="background"></div>
                    <svg class="checkmark-check" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 36">
                        <path fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" d="M4 20l13 13L44 4"/>
                    </svg>
                </div>
            `;
        }
    });
    
    // Testimonials carousel
    const testimonialsContainer = document.querySelector('.testimonials-carousel');
    if (testimonialsContainer && typeof Swiper !== 'undefined') {
        new Swiper('.testimonials-carousel', {
            slidesPerView: 1,
            spaceBetween: 30,
            loop: true,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            breakpoints: {
                640: {
                    slidesPerView: 1,
                },
                768: {
                    slidesPerView: 2,
                    spaceBetween: 30,
                },
                1024: {
                    slidesPerView: 3,
                    spaceBetween: 30,
                },
            }
        });
    }
    
    // Service cards hover effect
    const serviceCards = document.querySelectorAll('.feature-card');
    serviceCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            // Add animation to icon
            const icon = this.querySelector('.feature-icon');
            if (icon) {
                icon.style.transition = 'transform 0.5s ease';
                icon.style.transform = 'rotateY(180deg)';
                
                setTimeout(() => {
                    icon.style.transform = 'rotateY(360deg)';
                }, 500);
            }
        });
    });
    
    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                
                // Show feedback with shake animation for invalid fields
                const invalidInputs = form.querySelectorAll(':invalid');
                invalidInputs.forEach(input => {
                    input.classList.add('animate__animated', 'animate__shakeX');
                    
                    input.addEventListener('animationend', () => {
                        input.classList.remove('animate__animated', 'animate__shakeX');
                    });
                });
            }
            
            form.classList.add('was-validated');
        }, false);
    });
    
    // 3D tilt effect
    const tiltElements = document.querySelectorAll('.tilt-effect');
    if (tiltElements.length > 0) {
        tiltElements.forEach(element => {
            element.addEventListener('mousemove', function(e) {
                const rect = this.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const xPercent = x / rect.width;
                const yPercent = y / rect.height;
                
                const rotateX = (0.5 - yPercent) * 10;
                const rotateY = (xPercent - 0.5) * 10;
                
                this.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
            });
            
            element.addEventListener('mouseleave', function() {
                this.style.transform = 'perspective(1000px) rotateX(0) rotateY(0)';
            });
        });
    }
    
    // Scroll reveal animation
    const scrollRevealElements = document.querySelectorAll('.scroll-reveal');
    function checkScrollReveal() {
        scrollRevealElements.forEach(element => {
            const elementTop = element.getBoundingClientRect().top;
            const windowHeight = window.innerHeight;
            
            if (elementTop < windowHeight - 100) {
                element.classList.add('revealed');
            }
        });
    }
    
    window.addEventListener('scroll', checkScrollReveal);
    checkScrollReveal(); // Check on load
    
    // Preloader animation
    const preloader = document.getElementById('preloader');
    if (preloader) {
        window.addEventListener('load', () => {
            preloader.classList.add('fade-out');
            setTimeout(() => {
                preloader.style.display = 'none';
            }, 1000);
        });
    }
    
    // Dropdown menus with animation
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const dropdownMenu = this.nextElementSibling;
            
            if (dropdownMenu.classList.contains('show')) {
                dropdownMenu.classList.remove('show');
            } else {
                // Close all other dropdowns
                document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                    menu.classList.remove('show');
                });
                
                dropdownMenu.classList.add('show');
            }
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });
    
    // Dental-specific animations
    const toothIcons = document.querySelectorAll('.tooth-animation');
    toothIcons.forEach(icon => {
        // Random delay for more natural effect
        const delay = Math.random() * 2;
        icon.style.animationDelay = `${delay}s`;
    });
    
    // Parallax effect for hero section
    const parallaxElements = document.querySelectorAll('.parallax');
    window.addEventListener('scroll', function() {
        const scrollY = window.scrollY;
        
        parallaxElements.forEach(element => {
            const speed = element.getAttribute('data-speed') || 0.3;
            element.style.transform = `translateY(${scrollY * speed}px)`;
        });
    });
    
    // Counter animation for statistics
    const counters = document.querySelectorAll('.counter');
    function startCounters() {
        counters.forEach(counter => {
            const target = +counter.getAttribute('data-target');
            const duration = 2000; // 2 seconds
            const increment = target / (duration / 16); // 60fps
            
            let current = 0;
            const updateCounter = () => {
                current += increment;
                
                if (current < target) {
                    counter.textContent = Math.ceil(current);
                    requestAnimationFrame(updateCounter);
                } else {
                    counter.textContent = target;
                }
            };
            
            updateCounter();
        });
    }
    
    // Start counters when they come into view
    const observerOptions = {
        threshold: 0.1
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                if (entry.target.classList.contains('counter-section')) {
                    startCounters();
                    observer.unobserve(entry.target);
                }
            }
        });
    }, observerOptions);
    
    const counterSection = document.querySelector('.counter-section');
    if (counterSection) {
        observer.observe(counterSection);
    }
    
    // Initialize tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    if (typeof bootstrap !== 'undefined') {
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    }
    
    // Dental appointment form enhancements
    const appointmentForm = document.getElementById('appointment-form');
    if (appointmentForm) {
        // Time slots selection
        const timeSlots = appointmentForm.querySelectorAll('.time-slot');
        timeSlots.forEach(slot => {
            slot.addEventListener('click', function() {
                // Remove active class from all slots
                timeSlots.forEach(s => s.classList.remove('active'));
                
                // Add active class to clicked slot
                this.classList.add('active');
                
                // Update hidden input
                const timeInput = appointmentForm.querySelector('input[name="appointment_time"]');
                if (timeInput) {
                    timeInput.value = this.getAttribute('data-time');
                }
            });
        });
        
        // Service selection
        const serviceCheckboxes = appointmentForm.querySelectorAll('.service-checkbox');
        serviceCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const serviceCard = this.closest('.service-selection-card');
                if (this.checked) {
                    serviceCard.classList.add('selected');
                } else {
                    serviceCard.classList.remove('selected');
                }
            });
        });
    }
});

// Preload essential images for better UX
function preloadImages(sources) {
    sources.forEach(source => {
        const img = new Image();
        img.src = source;
    });
}

// Images to preload
preloadImages([
    'images/jrclogo.png',
    'images/dentalbanner.png',
    'images/interior.jpg',
    'images/whitelogo3.png'
]);