/**
 * JRC Dental Care - Notifications Management
 * 
 * This script handles the notification interactions including:
 * - AJAX-based marking of notifications as read
 * - Dynamic UI updates
 * - Sidebar functionality
 */

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
    
    // Initialize the Mark as Read functionality
    initializeMarkAsReadButtons();
    
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });
});

/**
 * Set up event handlers for mark as read buttons
 */
function initializeMarkAsReadButtons() {
    const markAsReadButtons = document.querySelectorAll('.mark-as-read');
    markAsReadButtons.forEach(button => {
        button.addEventListener('click', handleMarkAsReadClick);
    });
}

/**
 * Handle click on Mark as Read button
 * @param {Event} e - Click event
 */
function handleMarkAsReadClick(e) {
    e.preventDefault();
    const url = this.getAttribute('href');
    const notificationItem = this.closest('.list-group-item');
    const notificationId = this.dataset.notificationId || url.split('id=')[1]?.split('&')[0];
    
    // If we can't get the ID, fall back to traditional navigation
    if (!notificationId) {
        window.location.href = url;
        return;
    }
    
    // Show loading state
    this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';
    this.classList.add('disabled');
    
    // Send AJAX request
    fetch('mark-notification-read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `notification_id=${notificationId}`
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Update UI
            updateNotificationUI(notificationItem, this);
            showTemporaryMessage('Notification marked as read', 'success');
        } else {
            // Show error and reset button
            showTemporaryMessage(result.message || 'Error marking notification as read', 'danger');
            this.innerHTML = '<i class="fas fa-check me-1"></i> Mark as Read';
            this.classList.remove('disabled');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Fallback to traditional navigation
        window.location.href = url;
    });
}

/**
 * Update notification UI after marking as read
 * @param {Element} notificationItem - The notification list item element
 * @param {Element} button - The mark as read button
 */
function updateNotificationUI(notificationItem, button) {
    // Remove background highlighting
    notificationItem.classList.remove('bg-light');
    
    // Find and remove the "New" badge
    const badge = notificationItem.querySelector('.badge');
    if (badge) badge.remove();
    
    // Remove the mark as read button
    button.remove();
    
    // Update unread count in the sidebar if present
    const unreadBadge = document.querySelector('#notifications-count');
    if (unreadBadge) {
        const currentCount = parseInt(unreadBadge.textContent);
        if (currentCount > 0) {
            const newCount = currentCount - 1;
            unreadBadge.textContent = newCount;
            
            // Hide the badge if count reaches zero
            if (newCount === 0) {
                unreadBadge.style.display = 'none';
            }
        }
    }
}

/**
 * Show a temporary message to the user
 * @param {string} message - The message to display
 * @param {string} type - The message type (success, danger, etc.)
 */
function showTemporaryMessage(message, type = 'success') {
    const alertContainer = document.createElement('div');
    alertContainer.className = `alert alert-${type} alert-dismissible fade show mt-3`;
    alertContainer.role = 'alert';
    alertContainer.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Insert at the top of the container
    const container = document.querySelector('.container-fluid');
    container.insertBefore(alertContainer, document.querySelector('.card'));
    
    // Auto dismiss after 3 seconds
    setTimeout(() => {
        alertContainer.classList.remove('show');
        setTimeout(() => alertContainer.remove(), 300);
    }, 3000);
}