/**
 * JRC Dental Care - Form Validation
 * Handles validation for all forms across the website
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize form validation for all forms
    initFormValidation();
    
    // Initialize special form types
    initContactForm();
    initAppointmentForm();
    initSearchForm();
});

/**
 * Initialize form validation for all forms with data-validate attribute
 */
function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate="true"]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(form)) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            form.classList.add('was-validated');
        });
        
        // Real-time validation
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                validateInput(input);
            });
            
            input.addEventListener('blur', function() {
                validateInput(input);
            });
        });
    });
}

/**
 * Validate entire form
 * @param {HTMLFormElement} form - Form to validate
 * @returns {boolean} - Form validity
 */
function validateForm(form) {
    if (!form) return false;
    
    let isValid = true;
    const inputs = form.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
        if (!validateInput(input)) {
            isValid = false;
        }
    });
    
    // Check for password match if confirmation exists
    const password = form.querySelector('input[type="password"][id="password"]');
    const confirmPassword = form.querySelector('input[type="password"][id="confirm_password"]');
    
    if (password && confirmPassword) {
        if (password.value !== confirmPassword.value) {
            setInputInvalid(confirmPassword, 'Passwords do not match');
            isValid = false;
        }
    }
    
    // Additional form-level validations can be added here
    
    if (!isValid) {
        // Focus the first invalid input
        const firstInvalid = form.querySelector('.is-invalid');
        if (firstInvalid) {
            firstInvalid.focus();
        }
    }
    
    return isValid;
}

/**
 * Validate a single input
 * @param {HTMLElement} input - Input to validate
 * @returns {boolean} - Input validity
 */
function validateInput(input) {
    // Skip disabled or read-only inputs
    if (input.disabled || input.readOnly) return true;
    
    // Clear previous validation state
    clearInputValidation(input);
    
    // Required validation
    if (input.hasAttribute('required') && !input.value.trim()) {
        setInputInvalid(input, 'This field is required');
        return false;
    }
    
    // Email validation
    if (input.type === 'email' && input.value.trim()) {
        if (!isValidEmail(input.value)) {
            setInputInvalid(input, 'Please enter a valid email address');
            return false;
        }
    }
    
    // Password validation
    if (input.type === 'password' && input.id === 'password' && input.value.trim()) {
        const strength = calculatePasswordStrength(input.value);
        if (strength < 40) {
            setInputInvalid(input, 'Password is too weak. Include uppercase, lowercase, numbers, and symbols.');
            return false;
        }
    }
    
    // Phone validation
    if (input.type === 'tel' && input.value.trim()) {
        if (!isValidPhoneNumber(input.value)) {
            setInputInvalid(input, 'Please enter a valid phone number');
            return false;
        }
    }
    
    // Date validation
    if (input.type === 'date' && input.value) {
        const dateValue = new Date(input.value);
        
        // Check if date is valid
        if (isNaN(dateValue.getTime())) {
            setInputInvalid(input, 'Please enter a valid date');
            return false;
        }
        
        // Check min date if specified
        if (input.hasAttribute('min')) {
            const minDate = new Date(input.getAttribute('min'));
            if (dateValue < minDate) {
                setInputInvalid(input, `Date cannot be before ${formatDate(minDate)}`);
                return false;
            }
        }
        
        // Check max date if specified
        if (input.hasAttribute('max')) {
            const maxDate = new Date(input.getAttribute('max'));
            if (dateValue > maxDate) {
                setInputInvalid(input, `Date cannot be after ${formatDate(maxDate)}`);
                return false;
            }
        }
    }
    
    // Min and max validation for number inputs
    if (input.type === 'number') {
        const value = parseFloat(input.value);
        
        if (!isNaN(value)) {
            if (input.hasAttribute('min') && value < parseFloat(input.getAttribute('min'))) {
                setInputInvalid(input, `Value cannot be less than ${input.getAttribute('min')}`);
                return false;
            }
            
            if (input.hasAttribute('max') && value > parseFloat(input.getAttribute('max'))) {
                setInputInvalid(input, `Value cannot be greater than ${input.getAttribute('max')}`);
                return false;
            }
        }
    }
    
    // Minimum length validation
    if (input.hasAttribute('minlength') && input.value.trim()) {
        const minLength = parseInt(input.getAttribute('minlength'));
        if (input.value.length < minLength) {
            setInputInvalid(input, `Must be at least ${minLength} characters`);
            return false;
        }
    }
    
    // Maximum length validation
    if (input.hasAttribute('maxlength') && input.value.trim()) {
        const maxLength = parseInt(input.getAttribute('maxlength'));
        if (input.value.length > maxLength) {
            setInputInvalid(input, `Cannot exceed ${maxLength} characters`);
            return false;
        }
    }
    
    // Pattern validation
    if (input.hasAttribute('pattern') && input.value.trim()) {
        const pattern = new RegExp(input.getAttribute('pattern'));
        if (!pattern.test(input.value)) {
            const message = input.getAttribute('data-pattern-message') || 'Please match the requested format';
            setInputInvalid(input, message);
            return false;
        }
    }
    
    // Custom validation based on data attributes
    if (input.hasAttribute('data-validate-function') && input.value.trim()) {
        const validateFunction = window[input.getAttribute('data-validate-function')];
        if (typeof validateFunction === 'function') {
            const result = validateFunction(input.value);
            if (result !== true) {
                setInputInvalid(input, result || 'Invalid input');
                return false;
            }
        }
    }
    
    // If we get here, input is valid
    setInputValid(input);
    return true;
}

/**
 * Set input as invalid with error message
 * @param {HTMLElement} input - Input element
 * @param {string} message - Error message
 */
function setInputInvalid(input, message) {
    input.classList.add('is-invalid');
    input.classList.remove('is-valid');
    
    // Find or create feedback element
    let feedback = input.nextElementSibling;
    if (!feedback || !feedback.classList.contains('invalid-feedback')) {
        feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        input.parentNode.insertBefore(feedback, input.nextSibling);
    }
    
    feedback.textContent = message;
}

/**
 * Set input as valid
 * @param {HTMLElement} input - Input element
 */
function setInputValid(input) {
    input.classList.remove('is-invalid');
    input.classList.add('is-valid');
    
    // Find and remove feedback element
    const feedback = input.nextElementSibling;
    if (feedback && feedback.classList.contains('invalid-feedback')) {
        feedback.textContent = '';
    }
}

/**
 * Clear input validation state
 * @param {HTMLElement} input - Input element
 */
function clearInputValidation(input) {
    input.classList.remove('is-invalid');
    input.classList.remove('is-valid');
    
    // Find and remove feedback element
    const feedback = input.nextElementSibling;
    if (feedback && feedback.classList.contains('invalid-feedback')) {
        feedback.textContent = '';
    }
}

/**
 * Format date to readable string
 * @param {Date} date - Date object
 * @returns {string} - Formatted date string
 */
function formatDate(date) {
    if (!date) return '';
    
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString(undefined, options);
}

/**
 * Initialize contact form
 */
function initContactForm() {
    const contactForm = document.getElementById('contactForm');
    if (!contactForm) return;
    
    contactForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (validateForm(contactForm)) {
            // Show loading state
            const submitButton = contactForm.querySelector('button[type="submit"]');
            setButtonLoading(submitButton, true);
            
            // Simulate form submission
            setTimeout(() => {
                // Success message
                const successMessage = document.createElement('div');
                successMessage.className = 'alert alert-success fade show mt-3';
                successMessage.innerHTML = `
                    <strong>Thank you!</strong> Your message has been sent successfully. We'll get back to you soon.
                `;
                
                // Clear form
                contactForm.reset();
                contactForm.classList.remove('was-validated');
                
                // Add success message
                contactForm.appendChild(successMessage);
                
                // Reset button
                setButtonLoading(submitButton, false);
                
                // Auto-remove success message after 5 seconds
                setTimeout(() => {
                    successMessage.classList.add('fade');
                    setTimeout(() => {
                        successMessage.remove();
                    }, 500);
                }, 5000);
            }, 1500);
        }
    });
}

/**
 * Initialize appointment booking form
 */
function initAppointmentForm() {
    const appointmentForm = document.getElementById('appointmentForm');
    if (!appointmentForm) return;
    
    // Date picker setup
    const dateInput = appointmentForm.querySelector('#appointment_date');
    if (dateInput) {
        // Set minimum date to tomorrow
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        dateInput.min = tomorrow.toISOString().split('T')[0];
        
        // Set maximum date to 3 months from now
        const maxDate = new Date();
        maxDate.setMonth(maxDate.getMonth() + 3);
        dateInput.max = maxDate.toISOString().split('T')[0];
        
        // Listen for date changes to update available time slots
        dateInput.addEventListener('change', function() {
            updateAvailableTimeSlots(this.value);
        });
    }
    
    // Function to update time slots based on selected date
    function updateAvailableTimeSlots(selectedDate) {
        const timeSelect = appointmentForm.querySelector('#appointment_time');
        if (!timeSelect) return;
        
        // Clear existing options
        timeSelect.innerHTML = '<option value="">Select Time</option>';
        
        if (!selectedDate) return;
        
        // Convert to Date object
        const date = new Date(selectedDate);
        const day = date.getDay(); // 0 = Sunday, 6 = Saturday
        
        // Define available time slots based on day of week
        let availableTimes = [];
        
        if (day === 0) {
            // Sunday - Closed
            timeSelect.innerHTML = '<option value="">Closed on Sundays</option>';
            timeSelect.disabled = true;
            return;
        } else if (day === 6) {
            // Saturday - Limited hours
            availableTimes = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00'];
        } else {
            // Weekdays
            availableTimes = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
        }
        
        // Add time options
        timeSelect.disabled = false;
        availableTimes.forEach(time => {
            const option = document.createElement('option');
            option.value = time;
            option.textContent = formatTimeSlot(time);
            timeSelect.appendChild(option);
        });
    }
    
    // Format time slot for display
    function formatTimeSlot(time) {
        const [hours, minutes] = time.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        return `${hour12}:${minutes} ${ampm}`;
    }
    
    // Form submission
    appointmentForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (validateForm(appointmentForm)) {
            // Show loading state
            const submitButton = appointmentForm.querySelector('button[type="submit"]');
            setButtonLoading(submitButton, true);
            
            // Simulate form submission
            setTimeout(() => {
                // Success message
                const successMessage = document.createElement('div');
                successMessage.className = 'alert alert-success fade show mt-3';
                successMessage.innerHTML = `
                    <strong>Appointment Requested!</strong> We'll confirm your appointment shortly via email or phone.
                `;
                
                // Clear form
                appointmentForm.reset();
                appointmentForm.classList.remove('was-validated');
                
                // Add success message
                appointmentForm.appendChild(successMessage);
                
                // Reset button
                setButtonLoading(submitButton, false);
            }, 1500);
        }
    });
}

/**
 * Initialize search form
 */
function initSearchForm() {
    const searchForm = document.getElementById('searchForm');
    if (!searchForm) return;
    
    searchForm.addEventListener('submit', function(e) {
        const searchInput = searchForm.querySelector('input[type="search"]');
        
        if (!searchInput || !searchInput.value.trim()) {
            e.preventDefault();
            return;
        }
        
        // Form will submit normally
    });
}

/**
 * Email validation
 * @param {string} email - Email to validate
 * @returns {boolean} - Is email valid
 */
function isValidEmail(email) {
    const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
}

/**
 * Phone number validation
 * @param {string} phone - Phone number to validate
 * @returns {boolean} - Is phone number valid
 */
function isValidPhoneNumber(phone) {
    // Basic validation - can be replaced with more specific requirements
    const re = /^[+]?[(]?[0-9]{3}[)]?[-\s.]?[0-9]{3}[-\s.]?[0-9]{4,6}$/;
    return re.test(String(phone));
}

/**
 * Calculate password strength score
 * @param {string} password - Password to evaluate
 * @returns {number} - Strength score (0-100)
 */
function calculatePasswordStrength(password) {
    if (!password) return 0;
    
    let score = 0;
    
    // Length check
    if (password.length > 0) score += 1;
    if (password.length >= 8) score += 1;
    if (password.length >= 12) score += 1;
    
    // Complexity checks
    if (/[a-z]/.test(password)) score += 1; // lowercase
    if (/[A-Z]/.test(password)) score += 1; // uppercase
    if (/[0-9]/.test(password)) score += 1; // numbers
    if (/[^a-zA-Z0-9]/.test(password)) score += 1; // special chars
    
    // Calculate percentage (maximum score is 7)
    return Math.min(100, Math.round((score / 7) * 100));
}

/**
 * Handle button loading state
 * @param {HTMLElement} button - Button element
 * @param {boolean} isLoading - Whether button is loading
 */
function setButtonLoading(button, isLoading) {
    if (!button) return;
    
    if (isLoading) {
        // Save original text
        const originalText = button.innerHTML;
        button.setAttribute('data-original-text', originalText);
        
        // Replace with loading indicator
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Processing...';
        button.disabled = true;
    } else {
        // Restore original text
        const originalText = button.getAttribute('data-original-text');
        if (originalText) {
            button.innerHTML = originalText;
        }
        button.disabled = false;
    }
}