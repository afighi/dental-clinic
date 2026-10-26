/**
 * JRC Dental Care - Patient Dashboard JS
 */

// Toggle sidebar
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle functionality
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');
            localStorage.setItem('sb|sidebar-toggle', document.body.classList.contains('sb-sidenav-toggled'));
        });
    }
    
    // Quick Book Form Validation
    const quickBookForm = document.getElementById('quickBookForm');
    if (quickBookForm) {
        quickBookForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const serviceId = document.getElementById('serviceSelect').value;
            const dentistId = document.getElementById('dentistSelect').value;
            const appointmentDate = document.getElementById('appointmentDate').value;
            
            if (!serviceId || !dentistId || !appointmentDate) {
                alert('Please fill in all required fields');
                return;
            }
            
            // Check appointment time is in the future
            const appointmentTime = new Date(appointmentDate);
            const now = new Date();
            
            if (appointmentTime <= now) {
                alert('Please select a future date and time for your appointment');
                return;
            }
            
            // Check if appointment is during working hours (9am-5pm)
            const hours = appointmentTime.getHours();
            if (hours < 9 || hours >= 17) {
                alert('Please select an appointment time between 9:00 AM and 5:00 PM');
                return;
            }
            
            // If all validations pass, submit the form
            this.submit();
        });
    }
    
    // Service selection logic
    const serviceSelect = document.getElementById('serviceSelect');
    if (serviceSelect) {
        serviceSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const price = selectedOption.getAttribute('data-price');
                const duration = selectedOption.getAttribute('data-duration');
                
                // Update UI to show duration of service
                const durationInfo = document.getElementById('serviceDurationInfo');
                if (durationInfo) {
                    durationInfo.textContent = `Duration: ${duration} minutes`;
                    durationInfo.style.display = 'block';
                }
            }
        });
    }
    
    // Dentist selection - could fetch available times
    const dentistSelect = document.getElementById('dentistSelect');
    if (dentistSelect) {
        dentistSelect.addEventListener('change', function() {
            const dentistId = this.value;
            const appointmentDateInput = document.getElementById('appointmentDate');
            
            if (dentistId && appointmentDateInput) {
                // Could make an AJAX call here to get available time slots for the selected dentist
                // For now, we'll just enable the date input
                appointmentDateInput.disabled = false;
            }
        });
    }
});