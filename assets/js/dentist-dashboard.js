/**
 * Dentist Dashboard JavaScript
 * JRC Dental Care
 */

// Toggle Sidebar
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');
        });
    }
    
    // On window resize, if the window width is greater than 768px, show sidebar by default
    function handleResize() {
        if (window.innerWidth >= 768) {
            document.body.classList.remove('sb-sidenav-toggled');
        }
    }
    
    window.addEventListener('resize', handleResize);
    
    // For notifications dropdown
    const dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
    dropdownElementList.map(function (dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl);
    });
    
    // Mark notifications as read when clicked
    const notificationItems = document.querySelectorAll('.notification-item');
    notificationItems.forEach(item => {
        item.addEventListener('click', function() {
            const notificationId = this.getAttribute('data-notification-id');
            
            // Send AJAX request to mark notification as read
            fetch('mark-notification-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `notification_id=${notificationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI to show notification as read
                    this.classList.remove('unread');
                    this.classList.add('read');
                    
                    // Update notification counter if exists
                    const notificationCounter = document.getElementById('notificationCounter');
                    if (notificationCounter) {
                        const currentCount = parseInt(notificationCounter.textContent);
                        if (currentCount > 0) {
                            notificationCounter.textContent = currentCount - 1;
                            if (currentCount - 1 === 0) {
                                notificationCounter.style.display = 'none';
                            }
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error marking notification as read:', error);
            });
        });
    });
});

// Handle profile settings form submission
const profileForm = document.getElementById('profileSettingsForm');
if (profileForm) {
    profileForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('update-profile.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Profile updated successfully!');
                // Reload user information or update UI as needed
            } else {
                alert('Error updating profile: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating your profile.');
        });
    });
}

// Handle password change form submission
const passwordForm = document.getElementById('changePasswordForm');
if (passwordForm) {
    passwordForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        // Validate password match
        const newPassword = formData.get('new_password');
        const confirmPassword = formData.get('confirm_password');
        
        if (newPassword !== confirmPassword) {
            alert('New password and confirm password do not match!');
            return;
        }
        
        fetch('change-password.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Password changed successfully!');
                this.reset();
            } else {
                alert('Error changing password: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while changing your password.');
        });
    });
}

// Function to add new treatment notes
function addTreatmentNote(patientId, appointmentId) {
    const treatmentDescription = document.getElementById('treatmentDescription').value;
    const treatmentNotes = document.getElementById('treatmentNotes').value;
    
    if (!treatmentDescription) {
        alert('Please enter a treatment description');
        return;
    }
    
    // Add debugging console log
    console.log('Adding treatment for patient:', patientId, 'appointment:', appointmentId);
    
    // Create form data
    const formData = new FormData();
    formData.append('patient_id', patientId);
    formData.append('description', treatmentDescription);
    formData.append('notes', treatmentNotes);
    if (appointmentId) {
        formData.append('appointment_id', appointmentId);
    }
    
    fetch('add-treatment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            alert('Treatment notes added successfully!');
            // Either reload the page or update the UI to show the new treatment
            location.reload();
        } else {
            alert('Error adding treatment notes: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding treatment notes.');
    });
}

// Function to update treatment notes
function updateTreatmentNote(treatmentId) {
    const treatmentDescription = document.getElementById('editTreatmentDescription').value;
    const treatmentNotes = document.getElementById('editTreatmentNotes').value;
    
    if (!treatmentDescription) {
        alert('Please enter a treatment description');
        return;
    }
    
    console.log('Updating treatment:', treatmentId);
    
    // Create form data
    const formData = new FormData();
    formData.append('treatment_id', treatmentId);
    formData.append('description', treatmentDescription);
    formData.append('notes', treatmentNotes);
    
    fetch('update-treatment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            alert('Treatment notes updated successfully!');
            // Either reload the page or update the UI to show the updated treatment
            location.reload();
        } else {
            alert('Error updating treatment notes: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating treatment notes.');
    });
}