/**
 * JRC Dental Care - Animations JavaScript
 * Handles all dynamic animations and UI effects
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all animations
    initNavbarScroll();
    initAnimateOnScroll();
    initButtonRippleEffect();
    initCounterAnimation();
    initTypingEffect();
    initParallaxEffect();
    initSmoothScroll();
    initCardsHoverEffect();
    initTooltips();
    initScrollProgressIndicator();
});

/**
 * Navbar scroll effect
 * Changes navbar appearance on scroll
 */
function initNavbarScroll() {
    const navbar = document.querySelector('.navbar');
    if (!navbar) return;

    const handleScroll = () => {
        if (window.scrollY > 50) {
            navbar.classList.add('navbar-sticky');
            navbar.classList.add('navbar-scrolled');
        } else {
            navbar.classList.remove('navbar-sticky');
            navbar.classList.remove('navbar-scrolled');
        }
    };

    window.addEventListener('scroll', handleScroll);
    // Initial check
    handleScroll();
}

/**
 * Animate elements when they come into view
 */
function initAnimateOnScroll() {
    const elements = document.querySelectorAll('.animate-on-scroll');
    if (!elements.length) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('in-view');
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -100px 0px'
    });

    elements.forEach(element => {
        observer.observe(element);
    });
}

/**
 * Add ripple effect to buttons
 */
function initButtonRippleEffect() {
    const buttons = document.querySelectorAll('.btn-ripple');
    if (!buttons.length) return;

    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            const rect = button.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            const ripple = document.createElement('span');
            ripple.classList.add('ripple');
            ripple.style.left = `${x}px`;
            ripple.style.top = `${y}px`;

            button.appendChild(ripple);

            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
}

/**
 * Animated counter for statistics
 */
function initCounterAnimation() {
    const counters = document.querySelectorAll('.counter-animated');
    if (!counters.length) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const counter = entry.target;
                const target = parseInt(counter.getAttribute('data-target'));
                const duration = parseInt(counter.getAttribute('data-duration') || 2000);
                const increment = target / (duration / 16);
                
                let current = 0;
                const updateCounter = () => {
                    current += increment;
                    if (current < target) {
                        counter.textContent = Math.round(current);
                        requestAnimationFrame(updateCounter);
                    } else {
                        counter.textContent = target;
                    }
                };
                
                updateCounter();
                observer.unobserve(counter);
            }
        });
    }, {
        threshold: 0.5
    });

    counters.forEach(counter => {
        observer.observe(counter);
    });
}

/**
 * Typing effect for hero text
 */
function initTypingEffect() {
    const typingElements = document.querySelectorAll('.typing-effect');
    if (!typingElements.length) return;

    typingElements.forEach(element => {
        const text = element.getAttribute('data-text');
        if (!text) return;

        element.textContent = '';
        let i = 0;
        
        // Create cursor element
        const cursor = document.createElement('span');
        cursor.classList.add('typing-cursor');
        element.parentNode.insertBefore(cursor, element.nextSibling);

        const type = () => {
            if (i < text.length) {
                element.textContent += text.charAt(i);
                i++;
                setTimeout(type, 100);
            }
        };
        
        setTimeout(type, 1000);
    });
}

/**
 * Parallax scrolling effect
 */
function initParallaxEffect() {
    const parallaxElements = document.querySelectorAll('.parallax');
    if (!parallaxElements.length) return;

    const handleScroll = () => {
        const scrollTop = window.pageYOffset;
        
        parallaxElements.forEach(element => {
            const speed = element.getAttribute('data-parallax-speed') || 0.5;
            const offset = scrollTop * speed;
            element.style.transform = `translateY(${offset}px)`;
        });
    };

    window.addEventListener('scroll', handleScroll);
}

/**
 * Smooth scrolling for anchor links
 */
function initSmoothScroll() {
    const anchorLinks = document.querySelectorAll('a[href^="#"]:not([href="#"])');
    if (!anchorLinks.length) return;

    anchorLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                const navbarHeight = document.querySelector('.navbar')?.offsetHeight || 0;
                const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - navbarHeight;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
                
                // Update URL hash without scrolling
                history.pushState(null, null, targetId);
            }
        });
    });
}

/**
 * Add hover effects to cards
 */
function initCardsHoverEffect() {
    const cards = document.querySelectorAll('.card');
    if (!cards.length) return;

    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.classList.add('hover');
        });
        
        card.addEventListener('mouseleave', function() {
            this.classList.remove('hover');
        });
    });
}

/**
 * Initialize Bootstrap tooltips
 */
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    if (!tooltipTriggerList.length) return;
    
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Scroll progress indicator
 */
function initScrollProgressIndicator() {
    const progressBar = document.querySelector('.scroll-progress');
    if (!progressBar) return;

    const updateProgress = () => {
        const windowHeight = window.innerHeight;
        const documentHeight = document.documentElement.scrollHeight - windowHeight;
        const scrollTop = window.scrollY;
        const progress = (scrollTop / documentHeight) * 100;
        
        progressBar.style.width = `${progress}%`;
    };

    window.addEventListener('scroll', updateProgress);
    updateProgress(); // Initial update
}

/**
 * Add staggered animation to a list of elements
 * @param {string} selector - CSS selector for the container
 * @param {string} itemSelector - CSS selector for the items to animate
 * @param {number} delayBetween - Delay between animations in ms
 */
function addStaggeredAnimation(selector, itemSelector, delayBetween = 100) {
    const container = document.querySelector(selector);
    if (!container) return;
    
    const items = container.querySelectorAll(itemSelector);
    if (!items.length) return;

    const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting) {
            items.forEach((item, index) => {
                setTimeout(() => {
                    item.classList.add('fade-in-up');
                    item.style.opacity = '1';
                }, index * delayBetween);
            });
            observer.unobserve(container);
        }
    }, {
        threshold: 0.2
    });

    observer.observe(container);
}

/**
 * Add loading state to a button
 * @param {HTMLElement} button - The button element
 * @param {boolean} isLoading - Whether to add or remove loading state
 */
function setButtonLoading(button, isLoading) {
    if (!button) return;
    
    if (isLoading) {
        button.classList.add('btn-loading');
        button.disabled = true;
        
        // Save original text
        const buttonText = button.innerHTML;
        button.setAttribute('data-original-text', buttonText);
        
        // Create a span for the text to allow opacity manipulation
        button.innerHTML = `<span class="btn-text">${buttonText}</span>`;
    } else {
        button.classList.remove('btn-loading');
        button.disabled = false;
        
        // Restore original text
        const originalText = button.getAttribute('data-original-text');
        if (originalText) {
            button.innerHTML = originalText;
        }
    }
}

/**
 * Create and animate a notification
 * @param {string} message - Notification message
 * @param {string} type - Notification type (success, error, warning, info)
 * @param {number} duration - Duration in ms before notification disappears
 */
function showNotification(message, type = 'info', duration = 5000) {
    const notificationsContainer = document.querySelector('.notifications-container');
    
    // Create container if it doesn't exist
    if (!notificationsContainer) {
        const container = document.createElement('div');
        container.classList.add('notifications-container');
        document.body.appendChild(container);
    }
    
    // Create notification element
    const notification = document.createElement('div');
    notification.classList.add('notification', `notification-${type}`, 'fade-in');
    notification.innerHTML = `
        <div class="notification-icon">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
        </div>
        <div class="notification-content">
            <p>${message}</p>
        </div>
        <button type="button" class="notification-close">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add to container
    notificationsContainer.appendChild(notification);
    
    // Setup close button
    const closeButton = notification.querySelector('.notification-close');
    closeButton.addEventListener('click', () => {
        notification.classList.remove('fade-in');
        notification.classList.add('fade-out');
        
        setTimeout(() => {
            notification.remove();
        }, 300);
    });
    
    // Auto-remove after duration
    setTimeout(() => {
        if (notification.parentNode) {
            notification.classList.remove('fade-in');
            notification.classList.add('fade-out');
            
            setTimeout(() => {
                notification.remove();
            }, 300);
        }
    }, duration);
    
    return notification;
}