/**
 * Dashboard JavaScript
 * JRC Dental Care
 */

// Document Ready Function
document.addEventListener('DOMContentLoaded', function() {
    // Toggle Sidebar
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
    
    // For dropdowns
    const dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
    dropdownElementList.map(function (dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl);
    });
    
    // Handle notification read status
    const notificationItems = document.querySelectorAll('.notification-item');
    if (notificationItems) {
        notificationItems.forEach(item => {
            item.addEventListener('click', function(e) {
                const notificationId = this.getAttribute('data-notification-id');
                if (notificationId) {
                    markNotificationAsRead(notificationId);
                }
            });
        });
    }
    
    // Function to mark notification as read
    function markNotificationAsRead(notificationId) {
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
                const notificationCounter = document.getElementById('notificationCounter');
                if (notificationCounter) {
                    const currentCount = parseInt(notificationCounter.textContent);
                    if (currentCount > 0) {
                        const newCount = currentCount - 1;
                        notificationCounter.textContent = newCount;
                        if (newCount === 0) {
                            notificationCounter.style.display = 'none';
                        }
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
        });
    }

    // Real-time notification polling for admins
    const isAdmin = document.body.dataset.roleId == 1;
    if (isAdmin) {
        function fetchNotifications() {
            fetch('fetch_notifications.php')
                .then(response => response.json())
                .then(data => {
                    // Update navbar notification count
                    const $navCounter = document.querySelector('.notification-counter');
                    if ($navCounter) {
                        if (data.count > 0) {
                            $navCounter.textContent = data.count > 9 ? '9+' : data.count;
                            $navCounter.style.display = 'block';
                        } else {
                            $navCounter.style.display = 'none';
                        }
                    }

                    // Update navbar notification list
                    const $notifList = document.querySelector('.notification-list');
                    if ($notifList) {
                        const $items = $notifList.querySelectorAll('li:not(.dropdown-header, .dropdown-divider, :last-child)');
                        $items.forEach(item => item.remove());
                        if (data.notifications.length > 0) {
                            data.notifications.forEach(notif => {
                                const link = `notifications.php?id=${notif.notification_id}`;
                                const $item = document.createElement('li');
                                $item.innerHTML = `
                                    <a class="dropdown-item py-2" href="${link}">
                                        <div class="d-flex w-100 justify-content-between">
                                            <small class="fw-bold">${notif.title}</small>
                                            <small class="text-muted">${new Date(notif.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true })}</small>
                                        </div>
                                        <p class="mb-1 small text-truncate">${notif.notification_message}</p>
                                    </a>
                                `;
                                $notifList.insertBefore($item, $notifList.querySelector('.dropdown-divider'));
                            });
                        } else {
                            const $noNotif = document.createElement('li');
                            $noNotif.innerHTML = '<span class="dropdown-item py-2">No new notifications</span>';
                            $notifList.insertBefore($noNotif, $notifList.querySelector('.dropdown-divider'));
                        }
                    }

                    // Update sidebar notification count
                    const $sidebarCounter = document.querySelector('.sidebar-notification-counter');
                    if ($sidebarCounter) {
                        if (data.count > 0) {
                            $sidebarCounter.textContent = data.count > 9 ? '9+' : data.count;
                            $sidebarCounter.style.display = 'inline-block';
                        } else {
                            $sidebarCounter.style.display = 'none';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching notifications:', error);
                })
                .finally(() => {
                    // Schedule the next poll
                    setTimeout(fetchNotifications, 10000); // Poll every 10 seconds
                });
        }

        // Start polling
        fetchNotifications();
    }
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Function to show success and error messages
function showAlert(message, type = 'success') {
    const alertContainer = document.getElementById('alertContainer');
    if (alertContainer) {
        const alertHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        alertContainer.innerHTML = alertHTML;
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.classList.remove('show');
                setTimeout(() => {
                    alertContainer.innerHTML = '';
                }, 300);
            }
        }, 5000);
    }
}

// Format date for display
function formatDate(dateString, includeTime = true) {
    const date = new Date(dateString);
    const options = {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    };
    
    if (includeTime) {
        options.hour = '2-digit';
        options.minute = '2-digit';
    }
    
    return date.toLocaleDateString('en-US', options);
}

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP'
    }).format(amount);
}

/**
 * Chart initialization functions
 * These are now included directly in the HTML file
 * to ensure proper data binding with PHP variables
 */