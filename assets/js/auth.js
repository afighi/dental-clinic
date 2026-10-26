/**
 * JRC Dental Care - Authentication Scripts
 * Handles all forms and validation for login, registration, and password reset
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all auth forms
    initLoginForm();
    initRegisterForm();
    initForgotPasswordForm();
    initPasswordStrengthMeter();
    initPasswordToggle();
    initOtpInput();
    initFormSteps();
});

/**
 * Initialize Login Form
 */
function initLoginForm() {
    const loginForm = document.getElementById('loginForm');
    if (!loginForm) return;

    loginForm.addEventListener('submit', function(e) {
        const username = loginForm.querySelector('#username');
        const password = loginForm.querySelector('#password');
        const submitButton = loginForm.querySelector('button[type="submit"]');
        
        // Basic validation
        if (!username.value.trim() || !password.value.trim()) {
            e.preventDefault();
            showFormError(loginForm, 'Please enter both username and password.');
            return;
        }
        
        // Show loading state
        setButtonLoading(submitButton, true);
        
        // Form will be submitted normally to server
    });
}

/**
 * Initialize Registration Form - Multi-step
 */
function initRegisterForm() {
    const registerForm = document.getElementById('registerForm');
    if (!registerForm) return;

    // Initial setup
    const submitButtons = registerForm.querySelectorAll('button[type="submit"]');
    const backButtons = registerForm.querySelectorAll('button[name="back"]');
    const currentStepInput = registerForm.querySelector('input[name="step"]');
    
    // Handle next step submission
    submitButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Don't proceed if button has the 'back' name attribute
            if (button.getAttribute('name') === 'back') return;
            
            const currentStep = parseInt(currentStepInput.value);
            const form = button.closest('form');
            
            // Validate current step
            if (!validateStep(form, currentStep)) {
                e.preventDefault();
                return;
            }
            
            // Set loading state
            setButtonLoading(button, true);
            
            // Form will submit and advance to next step
        });
    });
    
    // Step validation function
    function validateStep(form, step) {
        let isValid = true;
        let errorMessage = '';
        
        switch(step) {
            case 1:
                const username = form.querySelector('#username');
                const email = form.querySelector('#email');
                const password = form.querySelector('#password');
                const confirmPassword = form.querySelector('#confirm_password');
                
                if (!username.value.trim() || !email.value.trim() || !password.value.trim() || !confirmPassword.value.trim()) {
                    errorMessage = 'All fields are required.';
                    isValid = false;
                } else if (password.value !== confirmPassword.value) {
                    errorMessage = 'Passwords do not match.';
                    isValid = false;
                } else if (!isValidEmail(email.value)) {
                    errorMessage = 'Please enter a valid email address.';
                    isValid = false;
                }
                break;
                
            case 2:
                const firstName = form.querySelector('#first_name');
                const lastName = form.querySelector('#last_name');
                
                if (!firstName.value.trim() || !lastName.value.trim()) {
                    errorMessage = 'First name and last name are required.';
                    isValid = false;
                }
                break;
                
            case 3:
                const phone = form.querySelector('#phone_number');
                const address = form.querySelector('#address');
                const dob = form.querySelector('#date_of_birth');
                const termsCheck = form.querySelector('#termsCheck');
                
                if (!phone.value.trim() || !address.value.trim() || !dob.value) {
                    errorMessage = 'All fields are required.';
                    isValid = false;
                } else if (!termsCheck.checked) {
                    errorMessage = 'You must agree to the Terms of Service and Privacy Policy.';
                    isValid = false;
                }
                break;
        }
        
        if (!isValid) {
            showFormError(form, errorMessage);
        }
        
        return isValid;
    }
}

/**
 * Initialize Forgot Password Form
 */
function initForgotPasswordForm() {
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    if (!forgotPasswordForm) return;

    forgotPasswordForm.addEventListener('submit', function(e) {
        const email = forgotPasswordForm.querySelector('#email');
        const submitButton = forgotPasswordForm.querySelector('button[type="submit"]');
        
        // Basic validation
        if (!email.value.trim()) {
            e.preventDefault();
            showFormError(forgotPasswordForm, 'Please enter your email address.');
            return;
        } else if (!isValidEmail(email.value)) {
            e.preventDefault();
            showFormError(forgotPasswordForm, 'Please enter a valid email address.');
            return;
        }
        
        // Show loading state
        setButtonLoading(submitButton, true);
        
        // Form will be submitted normally to server
    });
    
    // OTP verification form
    const otpForm = document.getElementById('otpVerificationForm');
    if (otpForm) {
        const otpInput = otpForm.querySelector('#otp');
        
        otpInput.addEventListener('input', function() {
            // Auto-submit when 6 digits entered
            if (this.value.length === 6) {
                otpForm.submit();
            }
        });
    }
    
    // New password form
    const newPasswordForm = document.getElementById('newPasswordForm');
    if (newPasswordForm) {
        newPasswordForm.addEventListener('submit', function(e) {
            const newPassword = newPasswordForm.querySelector('#new_password');
            const confirmPassword = newPasswordForm.querySelector('#confirm_password');
            const submitButton = newPasswordForm.querySelector('button[type="submit"]');
            
            // Basic validation
            if (!newPassword.value.trim() || !confirmPassword.value.trim()) {
                e.preventDefault();
                showFormError(newPasswordForm, 'Both fields are required.');
                return;
            } else if (newPassword.value !== confirmPassword.value) {
                e.preventDefault();
                showFormError(newPasswordForm, 'Passwords do not match.');
                return;
            }
            
            // Show loading state
            setButtonLoading(submitButton, true);
            
            // Form will be submitted normally to server
        });
    }
}

/**
 * Initialize Password Strength Meter
 */
function initPasswordStrengthMeter() {
    const passwordInputs = document.querySelectorAll('input[type="password"]#password, input[type="password"]#new_password');
    
    passwordInputs.forEach(input => {
        if (!input) return;
        
        const strengthContainer = document.getElementById('password-strength');
        if (!strengthContainer) return;
        
        input.addEventListener('input', function() {
            const password = this.value;
            const strength = calculatePasswordStrength(password);
            updatePasswordStrengthUI(strengthContainer, strength);
        });
    });
    
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
    
    function updatePasswordStrengthUI(container, strength) {
        // Create or get password strength bar
        let strengthBar = container.querySelector('.password-strength-bar');
        if (!strengthBar) {
            strengthBar = document.createElement('div');
            strengthBar.classList.add('password-strength-bar');
            container.appendChild(strengthBar);
        }
        
        // Create or get password strength text
        let strengthText = container.querySelector('.password-strength-text');
        if (!strengthText) {
            strengthText = document.createElement('div');
            strengthText.classList.add('password-strength-text');
            container.appendChild(strengthText);
        }
        
        // Update bar width
        strengthBar.style.width = `${strength}%`;
        
        // Update bar color and text
        if (strength === 0) {
            strengthBar.style.backgroundColor = '#e2e8f0';
            strengthText.textContent = '';
        } else if (strength < 30) {
            strengthBar.style.backgroundColor = '#ef4444';
            strengthText.textContent = 'Weak';
            strengthText.style.color = '#ef4444';
        } else if (strength < 60) {
            strengthBar.style.backgroundColor = '#f59e0b';
            strengthText.textContent = 'Fair';
            strengthText.style.color = '#f59e0b';
        } else if (strength < 80) {
            strengthBar.style.backgroundColor = '#10b981';
            strengthText.textContent = 'Good';
            strengthText.style.color = '#10b981';
        } else {
            strengthBar.style.backgroundColor = '#3b82f6';
            strengthText.textContent = 'Strong';
            strengthText.style.color = '#3b82f6';
        }
    }
}

/**
 * Initialize Password Visibility Toggle
 */
function initPasswordToggle() {
    const toggleButtons = document.querySelectorAll('.toggle-password');
    
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const passwordInput = document.querySelector(this.getAttribute('data-target'));
            if (!passwordInput) return;
            
            // Toggle password visibility
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                this.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                passwordInput.type = 'password';
                this.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    });
}

/**
 * Initialize OTP Input Field
 */
function initOtpInput() {
    const otpInput = document.querySelector('#otp');
    if (!otpInput) return;
    
    // Only allow numbers
    otpInput.addEventListener('keypress', function(e) {
        if (isNaN(parseInt(e.key))) {
            e.preventDefault();
        }
    });
    
    // Automatically focus when page loads
    setTimeout(() => {
        otpInput.focus();
    }, 500);
}

/**
 * Initialize Multi-step Form Navigation
 */
function initFormSteps() {
    const formSteps = document.querySelectorAll('.form-step');
    if (!formSteps.length) return;
    
    const showStep = (stepIndex) => {
        formSteps.forEach((step, index) => {
            if (index === stepIndex) {
                step.classList.remove('previous', 'next');
                step.classList.add('current');
            } else if (index < stepIndex) {
                step.classList.remove('current', 'next');
                step.classList.add('previous');
            } else {
                step.classList.remove('current', 'previous');
                step.classList.add('next');
            }
        });
        
        // Update progress bar and indicators
        updateProgressIndicators(stepIndex);
    };
    
    const updateProgressIndicators = (currentStep) => {
        const progressBar = document.querySelector('.progress-bar');
        const stepIndicators = document.querySelectorAll('.step-indicator');
        
        if (progressBar) {
            progressBar.style.width = `${((currentStep + 1) / formSteps.length) * 100}%`;
        }
        
        if (stepIndicators.length) {
            stepIndicators.forEach((indicator, index) => {
                if (index <= currentStep) {
                    indicator.classList.add('active');
                } else {
                    indicator.classList.remove('active');
                }
            });
        }
    };
    
    // Initialize first step
    showStep(0);
    
    // Handle next buttons
    const nextButtons = document.querySelectorAll('.btn-next');
    nextButtons.forEach(button => {
        button.addEventListener('click', function() {
            const currentStep = parseInt(this.getAttribute('data-current-step'));
            const form = this.closest('form');
            
            // Validate current step before proceeding
            if (validateStepFields(form, currentStep)) {
                showStep(currentStep + 1);
                // Update hidden step input if it exists
                const stepInput = form.querySelector('input[name="step"]');
                if (stepInput) {
                    stepInput.value = currentStep + 2; // Step is 1-indexed in the form
                }
            }
        });
    });
    
    // Handle back buttons
    const backButtons = document.querySelectorAll('.btn-back');
    backButtons.forEach(button => {
        button.addEventListener('click', function() {
            const currentStep = parseInt(this.getAttribute('data-current-step'));
            const form = this.closest('form');
            
            showStep(currentStep - 1);
            // Update hidden step input if it exists
            const stepInput = form.querySelector('input[name="step"]');
            if (stepInput) {
                stepInput.value = currentStep; // Step is 1-indexed in the form
            }
        });
    });
    
    // Step validation function
    function validateStepFields(form, stepIndex) {
        // Get all required fields in the current step
        const currentStepElement = form.querySelector(`.form-step:nth-child(${stepIndex + 1})`);
        if (!currentStepElement) return true;
        
        const requiredFields = currentStepElement.querySelectorAll('[required]');
        let isValid = true;
        let firstInvalidField = null;
        
        requiredFields.forEach(field => {
            // Reset previous validation
            field.classList.remove('is-invalid');
            
            // Check if field is empty
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
                
                if (!firstInvalidField) {
                    firstInvalidField = field;
                }
            }
            
            // Additional validation for specific field types
            if (field.type === 'email' && field.value.trim() && !isValidEmail(field.value)) {
                isValid = false;
                field.classList.add('is-invalid');
                
                if (!firstInvalidField) {
                    firstInvalidField = field;
                }
            }
               // Password validation
               if (field.id === 'password' || field.id === 'new_password') {
                const strength = calculatePasswordStrength(field.value);
                if (strength < 40) { // Require at least a fair password
                    isValid = false;
                    field.classList.add('is-invalid');
                    
                    if (!firstInvalidField) {
                        firstInvalidField = field;
                    }
                }
            }
            
            // Password confirmation
            if (field.id === 'confirm_password') {
                const passwordField = form.querySelector('#password') || form.querySelector('#new_password');
                if (passwordField && field.value !== passwordField.value) {
                    isValid = false;
                    field.classList.add('is-invalid');
                    
                    if (!firstInvalidField) {
                        firstInvalidField = field;
                    }
                }
            }
            
            // Phone number validation
            if (field.id === 'phone_number' && field.value.trim() && !isValidPhoneNumber(field.value)) {
                isValid = false;
                field.classList.add('is-invalid');
                
                if (!firstInvalidField) {
                    firstInvalidField = field;
                }
            }
        });
        
        // Show error message if validation fails
        if (!isValid) {
            if (firstInvalidField) {
                firstInvalidField.focus();
            }
            showFormError(form, 'Please fill in all required fields correctly.');
        }
        
        return isValid;
    }
}

/**
 * Show form error message
 * @param {HTMLElement} form - The form element
 * @param {string} message - Error message to display
 */
function showFormError(form, message) {
    // Check if error alert already exists
    let errorAlert = form.querySelector('.alert-danger');
    
    if (!errorAlert) {
        // Create new error alert
        errorAlert = document.createElement('div');
        errorAlert.className = 'alert alert-danger alert-dismissible fade show';
        errorAlert.role = 'alert';
        
        // Add close button
        errorAlert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Insert at top of form
        form.insertBefore(errorAlert, form.firstChild);
    } else {
        // Update existing error message
        errorAlert.textContent = message;
    }
    
    // Animate the alert
    errorAlert.classList.add('slide-in-down');
    
    // Scroll to error
    errorAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
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