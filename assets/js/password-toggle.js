/**
 * Password Toggle JavaScript
 * This script enables toggling password visibility in password fields
 */

document.addEventListener('DOMContentLoaded', function() {
    // Function to setup password toggle for a specific toggle element and password input
    function setupPasswordToggle(toggleId, passwordId) {
        const toggleElement = document.getElementById(toggleId);
        const passwordInput = document.getElementById(passwordId);
        
        if (toggleElement && passwordInput) {
            toggleElement.addEventListener('click', function() {
                // Toggle password visibility
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    toggleElement.innerHTML = '<i class="fas fa-eye-slash"></i>';
                    toggleElement.setAttribute('title', 'Hide password');
                } else {
                    passwordInput.type = 'password';
                    toggleElement.innerHTML = '<i class="fas fa-eye"></i>';
                    toggleElement.setAttribute('title', 'Show password');
                }
            });
        }
    }
    
    // Setup toggle for common password fields
    setupPasswordToggle('passwordToggle', 'password');
    setupPasswordToggle('confirmPasswordToggle', 'confirm_password');
    setupPasswordToggle('currentPasswordToggle', 'current_password');
    setupPasswordToggle('newPasswordToggle', 'new_password');
    
    // Add password strength indicator if it exists
    const passwordInput = document.getElementById('new_password') || document.getElementById('password');
    const strengthIndicator = document.querySelector('.password-strength-meter');
    
    if (passwordInput && strengthIndicator) {
        passwordInput.addEventListener('input', function() {
            const password = passwordInput.value;
            let strength = 0;
            
            // Calculate password strength
            if (password.length >= 8) strength += 1;
            if (password.match(/[A-Z]/)) strength += 1;
            if (password.match(/[a-z]/)) strength += 1;
            if (password.match(/[0-9]/)) strength += 1;
            if (password.match(/[^A-Za-z0-9]/)) strength += 1;
            
            // Update the strength indicator
            const strengthClass = [
                'very-weak',
                'weak',
                'medium',
                'strong',
                'very-strong'
            ][strength] || '';
            
            strengthIndicator.className = 'password-strength-meter ' + strengthClass;
            
            // Update strength text if it exists
            const strengthText = document.querySelector('.password-strength-text');
            if (strengthText) {
                const strengthLabels = [
                    'Very Weak',
                    'Weak',
                    'Medium',
                    'Strong',
                    'Very Strong'
                ];
                strengthText.textContent = strengthLabels[strength] || '';
            }
        });
    }
});