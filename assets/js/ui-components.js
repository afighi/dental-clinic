/**
 * JRC Dental Care - UI Components
 * Handles all UI components and interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all UI components
    initStickyHeader();
    initMobileMenu();
    initScrollToTop();
    initServicesTabs();
    initTestimonialSlider();
    initImageGallery();
    initFaqAccordion();
    initCountdownTimer();
    initNotifications();
    initBackToPreviousPage();
    initModalHandlers();
    initTooltipsAndPopovers();
});

/**
 * Initialize sticky header
 */
function initStickyHeader() {
    const header = document.querySelector('.navbar');
    if (!header) return;
    
    // Add scroll event listener
    window.addEventListener('scroll', () => {
        if (window.scrollY > 100) {
            header.classList.add('navbar-sticky');
        } else {
            header.classList.remove('navbar-sticky');
        }
    });
    
    // Check initial scroll position
    if (window.scrollY > 100) {
        header.classList.add('navbar-sticky');
    }
}

/**
 * Initialize mobile menu toggle
 */
function initMobileMenu() {
    const menuToggle = document.querySelector('.navbar-toggler');
    if (!menuToggle) return;
    
    menuToggle.addEventListener('click', function() {
        document.body.classList.toggle('mobile-menu-open');
    });
    
    // Close menu when clicking outside
    document.addEventListener('click', function(event) {
        const navbarCollapse = document.querySelector('.navbar-collapse');
        
        if (navbarCollapse && navbarCollapse.classList.contains('show')) {
            // Check if click was outside the menu
            if (!event.target.closest('.navbar-collapse') && !event.target.closest('.navbar-toggler')) {
                // Close the menu using Bootstrap collapse API
                const bsCollapse = new bootstrap.Collapse(navbarCollapse);
                bsCollapse.hide();
                document.body.classList.remove('mobile-menu-open');
            }
        }
    });
    
    // Close menu when a link is clicked
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            const navbarCollapse = document.querySelector('.navbar-collapse');
            if (navbarCollapse && navbarCollapse.classList.contains('show')) {
                const bsCollapse = new bootstrap.Collapse(navbarCollapse);
                bsCollapse.hide();
                document.body.classList.remove('mobile-menu-open');
            }
        });
    });
}

/**
 * Initialize scroll to top button
 */
function initScrollToTop() {
    // Create scroll to top button if it doesn't exist
    let scrollTopBtn = document.querySelector('.scroll-to-top');
    
    if (!scrollTopBtn) {
        scrollTopBtn = document.createElement('button');
        scrollTopBtn.className = 'scroll-to-top';
        scrollTopBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
        document.body.appendChild(scrollTopBtn);
        
        // Apply styles
        scrollTopBtn.style.position = 'fixed';
        scrollTopBtn.style.bottom = '20px';
        scrollTopBtn.style.right = '20px';
        scrollTopBtn.style.height = '45px';
        scrollTopBtn.style.width = '45px';
        scrollTopBtn.style.borderRadius = '50%';
        scrollTopBtn.style.backgroundColor = 'var(--primary)';
        scrollTopBtn.style.color = 'white';
        scrollTopBtn.style.border = 'none';
        scrollTopBtn.style.display = 'flex';
        scrollTopBtn.style.alignItems = 'center';
        scrollTopBtn.style.justifyContent = 'center';
        scrollTopBtn.style.cursor = 'pointer';
        scrollTopBtn.style.boxShadow = 'var(--shadow-md)';
        scrollTopBtn.style.zIndex = '999';
        scrollTopBtn.style.opacity = '0';
        scrollTopBtn.style.visibility = 'hidden';
        scrollTopBtn.style.transition = 'all 0.3s ease-in-out';
    }
    
    // Toggle button visibility based on scroll position
    window.addEventListener('scroll', () => {
        if (window.scrollY > 500) {
            scrollTopBtn.style.opacity = '1';
            scrollTopBtn.style.visibility = 'visible';
        } else {
            scrollTopBtn.style.opacity = '0';
            scrollTopBtn.style.visibility = 'hidden';
        }
    });
    
    // Scroll to top when button is clicked
    scrollTopBtn.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

/**
 * Initialize services tabs
 */
function initServicesTabs() {
    const servicesTabs = document.querySelector('.services-tabs');
    if (!servicesTabs) return;
    
    const tabLinks = servicesTabs.querySelectorAll('[data-bs-toggle="tab"]');
    
    // Add animation classes when a tab is shown
    tabLinks.forEach(tabLink => {
        tabLink.addEventListener('shown.bs.tab', function(event) {
            const targetId = event.target.getAttribute('href');
            const targetPane = document.querySelector(targetId);
            
            if (targetPane) {
                // Remove animation classes from all panes
                const allPanes = servicesTabs.querySelectorAll('.tab-pane');
                allPanes.forEach(pane => {
                    pane.classList.remove('animate', 'fade-in');
                });
                
                // Add animation classes to the active pane
                targetPane.classList.add('animate', 'fade-in');
            }
        });
    });
}

/**
 * Initialize testimonial slider
 */
function initTestimonialSlider() {
    const testimonialSlider = document.querySelector('.testimonial-slider');
    if (!testimonialSlider) return;
    
    // Check if bootstrap carousel is initialized
    if (testimonialSlider.getAttribute('data-initialized') === 'true') {
        return;
    }
    
    // Initialize bootstrap carousel
    const carousel = new bootstrap.Carousel(testimonialSlider, {
        interval: 5000,
        wrap: true,
        keyboard: true,
        touch: true,
        pause: 'hover'
    });
    
    // Mark as initialized
    testimonialSlider.setAttribute('data-initialized', 'true');
    
    // Add animation to slides when they become active
    testimonialSlider.addEventListener('slide.bs.carousel', function(event) {
        const targetSlide = event.relatedTarget;
        const slides = testimonialSlider.querySelectorAll('.carousel-item');
        
        // Remove animation classes from all slides
        slides.forEach(slide => {
            slide.classList.remove('animate', 'fade-in');
        });
        
        // Add animation classes to the target slide
        setTimeout(() => {
            targetSlide.classList.add('animate', 'fade-in');
        }, 50);
    });
}

/**
 * Initialize image gallery with lightbox
 */
function initImageGallery() {
    const gallery = document.querySelector('.gallery');
    if (!gallery) return;
    
    const galleryItems = gallery.querySelectorAll('.gallery-item');
    
    galleryItems.forEach(item => {
        item.addEventListener('click', function() {
            const imgSrc = this.querySelector('img').getAttribute('src');
            const imgAlt = this.querySelector('img').getAttribute('alt');
            
            // Create lightbox modal if it doesn't exist
            let lightboxModal = document.querySelector('#lightboxModal');
            
            if (!lightboxModal) {
                const modalHTML = `
                    <div class="modal fade" id="lightboxModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content bg-transparent border-0">
                                <div class="modal-body p-0">
                                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
                                    <img src="" class="img-fluid rounded" alt="">
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                document.body.insertAdjacentHTML('beforeend', modalHTML);
                lightboxModal = document.querySelector('#lightboxModal');
            }
            
            // Set image source and alt
            const modalImg = lightboxModal.querySelector('.modal-body img');
            modalImg.setAttribute('src', imgSrc);
            modalImg.setAttribute('alt', imgAlt);
            
            // Show modal
            const modal = new bootstrap.Modal(lightboxModal);
            modal.show();
        });
    });
}

/**
 * Initialize FAQ accordion
 */
function initFaqAccordion() {
    const faqAccordion = document.querySelector('.faq-accordion');
    if (!faqAccordion) return;
    
    const accordionItems = faqAccordion.querySelectorAll('.accordion-item');
    
    accordionItems.forEach(item => {
        const header = item.querySelector('.accordion-header');
        const collapse = item.querySelector('.accordion-collapse');
        
        // Add event listener to header
        header.addEventListener('click', function() {
            // Get the current state
            const isExpanded = header.getAttribute('aria-expanded') === 'true';
            
            // Toggle icon rotation
            const icon = header.querySelector('.accordion-icon');
            if (icon) {
                if (isExpanded) {
                    icon.style.transform = 'rotate(0deg)';
                } else {
                    icon.style.transform = 'rotate(180deg)';
                }
            }
        });
        
        // Add animation when accordion is shown
        collapse.addEventListener('shown.bs.collapse', function() {
            const content = this.querySelector('.accordion-body');
            content.classList.add('animate', 'fade-in');
        });
        
        // Remove animation when accordion is hidden
        collapse.addEventListener('hidden.bs.collapse', function() {
            const content = this.querySelector('.accordion-body');
            content.classList.remove('animate', 'fade-in');
        });
    });
}

/**
 * Initialize countdown timer
 */
function initCountdownTimer() {
    const countdownElement = document.querySelector('.countdown-timer');
    if (!countdownElement) return;
    
    // Get target date from data attribute
    const targetDateStr = countdownElement.getAttribute('data-target-date');
    if (!targetDateStr) return;
    
    const targetDate = new Date(targetDateStr).getTime();
    
    // Create timer elements if they don't exist
    if (!countdownElement.querySelector('.countdown-days')) {
        countdownElement.innerHTML = `
            <div class="countdown-item">
                <div class="countdown-value countdown-days">00</div>
                <div class="countdown-label">Days</div>
            </div>
            <div class="countdown-item">
                <div class="countdown-value countdown-hours">00</div>
                <div class="countdown-label">Hours</div>
            </div>
            <div class="countdown-item">
                <div class="countdown-value countdown-minutes">00</div>
                <div class="countdown-label">Minutes</div>
            </div>
            <div class="countdown-item">
                <div class="countdown-value countdown-seconds">00</div>
                <div class="countdown-label">Seconds</div>
            </div>
        `;
    }
    
    // Get timer elements
    const daysElement = countdownElement.querySelector('.countdown-days');
    const hoursElement = countdownElement.querySelector('.countdown-hours');
    const minutesElement = countdownElement.querySelector('.countdown-minutes');
    const secondsElement = countdownElement.querySelector('.countdown-seconds');
    
    // Update timer function
    function updateTimer() {
        const now = new Date().getTime();
        const distance = targetDate - now;
        
        // Calculate time units
        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        
        // Update display
        daysElement.textContent = days.toString().padStart(2, '0');
        hoursElement.textContent = hours.toString().padStart(2, '0');
        minutesElement.textContent = minutes.toString().padStart(2, '0');
        secondsElement.textContent = seconds.toString().padStart(2, '0');
        
        // Check if countdown is expired
        if (distance < 0) {
            clearInterval(timerInterval);
            daysElement.textContent = '00';
            hoursElement.textContent = '00';
            minutesElement.textContent = '00';
            secondsElement.textContent = '00';
            
            // Show expired message
            const expiredMessage = document.createElement('div');
            expiredMessage.className = 'countdown-expired mt-3 alert alert-info';
            expiredMessage.textContent = 'The countdown has ended!';
            countdownElement.parentNode.appendChild(expiredMessage);
        }
    }
    
    // Initial update
    updateTimer();
    
    // Update every second
    const timerInterval = setInterval(updateTimer, 1000);
}

/**
 * Initialize notifications system
 */
function initNotifications() {
    // Create notifications container if it doesn't exist
    let notificationsContainer = document.querySelector('.notifications-container');
    
    if (!notificationsContainer) {
        notificationsContainer = document.createElement('div');
        notificationsContainer.className = 'notifications-container';
        notificationsContainer.style.position = 'fixed';
        notificationsContainer.style.top = '20px';
        notificationsContainer.style.right = '20px';
        notificationsContainer.style.zIndex = '9999';
        document.body.appendChild(notificationsContainer);
    }
    
    // Global function to show notifications
    window.showNotification = function(message, type = 'info', duration = 5000) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type} animate fade-in-left`;
        notification.style.backgroundColor = 'white';
        notification.style.borderLeft = `4px solid var(--${type === 'success' ? 'success' : type === 'error' ? 'danger' : type === 'warning' ? 'warning' : 'info'})`;
        notification.style.boxShadow = 'var(--shadow-lg)';
        notification.style.borderRadius = '4px';
        notification.style.padding = '15px 45px 15px 15px';
        notification.style.marginBottom = '10px';
        notification.style.position = 'relative';
        notification.style.minWidth = '300px';
        notification.style.maxWidth = '450px';
        
        // Add notification content
        notification.innerHTML = `
            <div class="notification-icon" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%);">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}" 
                   style="color: var(--${type === 'success' ? 'success' : type === 'error' ? 'danger' : type === 'warning' ? 'warning' : 'info'})"></i>
            </div>
            <div class="notification-content" style="margin-left: 25px;">
                <p style="margin: 0;">${message}</p>
            </div>
            <button type="button" class="notification-close" style="position: absolute; top: 10px; right: 10px; background: none; border: none; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        // Add to container
        notificationsContainer.appendChild(notification);
        
        // Setup close button
        const closeButton = notification.querySelector('.notification-close');
        closeButton.addEventListener('click', () => {
            closeNotification(notification);
        });
        
        // Auto-remove after duration
        if (duration > 0) {
            setTimeout(() => {
                closeNotification(notification);
            }, duration);
        }
        
        return notification;
    };
    
    // Helper function to close notification with animation
    function closeNotification(notification) {
        notification.classList.remove('fade-in-left');
        notification.classList.add('fade-out-right');
        
        setTimeout(() => {
            notification.remove();
        }, 300);
    }
}

/**
 * Initialize back to previous page button
 */
function initBackToPreviousPage() {
    const backButtons = document.querySelectorAll('.btn-back');
    
    backButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = '/index.php';
            }
        });
    });
}

/**
 * Initialize modal handlers
 */
function initModalHandlers() {
    // Auto-focus first input in modals
    const modals = document.querySelectorAll('.modal');
    
    modals.forEach(modal => {
        modal.addEventListener('shown.bs.modal', function() {
            const firstInput = this.querySelector('input:not([type=hidden]):not([readonly]):not([disabled])');
            if (firstInput) {
                firstInput.focus();
            }
        });
    });
    
    // Handle modal forms submission
    const modalForms = document.querySelectorAll('.modal form');
    
    modalForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // Default behavior is to validate form using form-validation.js
            // Additional modal-specific handling can be added here
        });
    });
}

/**
 * Initialize Bootstrap tooltips and popovers
 */
function initTooltipsAndPopovers() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            trigger: 'hover'
        });
    });
    
    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl, {
            trigger: 'focus'
        });
    });
}