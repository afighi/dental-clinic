<?php
session_start();
// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Get user information
$stmt = $conn->prepare("SELECT * FROM user WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get page for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Reduced from 15 to show fewer per card
$offset = ($page - 1) * $limit;

// Get unread notifications
$unread_query = "SELECT * FROM notification WHERE user_id = :user_id AND is_read = 0 ORDER BY created_at DESC";
$stmt = $conn->prepare($unread_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$unread_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get read notifications with pagination
$read_query = "SELECT * FROM notification WHERE user_id = :user_id AND is_read = 1 ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($read_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$read_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total read notifications for pagination
$count_query = "SELECT COUNT(*) FROM notification WHERE user_id = :user_id AND is_read = 1";
$stmt = $conn->prepare($count_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$total_read_notifications = $stmt->fetchColumn();
$total_pages = ceil($total_read_notifications / $limit);

// Mark all as read functionality
if (isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notification SET is_read = 1 WHERE user_id = :user_id AND is_read = 0");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $_SESSION['success'] = "All notifications marked as read.";
    header("Location: my-notifications.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Notifications - JRC Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Page Content -->
        <div id="page-content-wrapper">
            <!-- Navbar -->
            <?php include 'includes/navbar.php'; ?>
            
            <div class="container-fluid px-4 py-3">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3>My Notifications</h3>
                    <?php if (count($unread_notifications) > 0): ?>
                        <form method="post" class="d-inline">
                            <button type="submit" name="mark_all_read" class="btn btn-outline-primary">
                                <i class="fas fa-check-double me-2"></i>Mark All as Read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $_SESSION['success'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $_SESSION['error'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <!-- Unread Notifications Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-bell me-2"></i>New Notifications</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($unread_notifications) > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($unread_notifications as $notification): ?>
                                    <div class="list-group-item list-group-item-action py-3 bg-light">
                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                            <div>
                                                <span class="badge rounded-pill bg-primary me-2">New</span>
                                                <h6 class="mb-1"><?= isset($notification['title']) ? htmlspecialchars($notification['title']) : 'Notification' ?></h6>
                                            </div>
                                            <small class="text-muted"><?= date('M d, Y h:i A', strtotime($notification['created_at'])) ?></small>
                                        </div>
                                        <p class="mb-1"><?= htmlspecialchars($notification['notification_message']) ?></p>
                                        
                                        <?php
                                        // Determine if there's a related entity to link to
                                        $link = '';
                                        
                                        // Check notification_type and related_id (newer format) first
                                        if (!empty($notification['notification_type']) && !empty($notification['related_id'])) {
                                            switch ($notification['notification_type']) {
                                                case 'appointment':
                                                    $link = 'appointment-details.php?id=' . $notification['related_id'];
                                                    break;
                                                case 'payment':
                                                    $link = 'payment-receipt.php?id=' . $notification['related_id'];
                                                    break;
                                                case 'treatment':
                                                    $link = 'treatment-detail.php?id=' . $notification['related_id'];
                                                    break;
                                            }
                                        }
                                        // Then check related_table and related_entity_id (older format)
                                        else if (!empty($notification['related_table']) && !empty($notification['related_entity_id'])) {
                                            switch ($notification['related_table']) {
                                                case 'appointment':
                                                    $link = 'appointment-details.php?id=' . $notification['related_entity_id'];
                                                    break;
                                                case 'payment':
                                                    $link = 'payment-success.php?id=' . $notification['related_entity_id'];
                                                    break;
                                                case 'treatment':
                                                    $link = 'treatment-detail.php?id=' . $notification['related_entity_id'];
                                                    break;
                                            }
                                        }
                                        ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <div>
                                                <?php if (!empty($link)): ?>
                                                    <a href="<?= $link ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                                <?php endif; ?>
                                            </div>
                                            <a href="mark-notification-read.php?id=<?= $notification['notification_id'] ?>&redirect_back=1" class="btn btn-sm btn-outline-secondary mark-as-read" data-notification-id="<?= $notification['notification_id'] ?>">
                                                <i class="fas fa-check me-1"></i> Mark as Read
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center my-4 py-3">
                                <img src="images/bell.png" alt="No Notifications" class="img-fluid mb-3" style="max-width: 120px;">
                                <h6>No new notifications</h6>
                                <p class="text-muted">You're all caught up!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Read Notifications Card -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Previous Notifications</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($read_notifications) > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($read_notifications as $notification): ?>
                                    <div class="list-group-item list-group-item-action py-3">
                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                            <h6 class="mb-1"><?= isset($notification['title']) ? htmlspecialchars($notification['title']) : 'Notification' ?></h6>
                                            <small class="text-muted"><?= date('M d, Y h:i A', strtotime($notification['created_at'])) ?></small>
                                        </div>
                                        <p class="mb-1"><?= htmlspecialchars($notification['notification_message']) ?></p>
                                        
                                        <?php
                                        // Determine if there's a related entity to link to
                                        $link = '';
                                        
                                        // Check notification_type and related_id (newer format) first
                                        if (!empty($notification['notification_type']) && !empty($notification['related_id'])) {
                                            switch ($notification['notification_type']) {
                                                case 'appointment':
                                                    $link = 'appointment-details.php?id=' . $notification['related_id'];
                                                    break;
                                                case 'payment':
                                                    $link = 'payment-receipt.php?id=' . $notification['related_id'];
                                                    break;
                                                case 'treatment':
                                                    $link = 'treatment-detail.php?id=' . $notification['related_id'];
                                                    break;
                                            }
                                        }
                                        // Then check related_table and related_entity_id (older format)
                                        else if (!empty($notification['related_table']) && !empty($notification['related_entity_id'])) {
                                            switch ($notification['related_table']) {
                                                case 'appointment':
                                                    $link = 'appointment-details.php?id=' . $notification['related_entity_id'];
                                                    break;
                                                case 'payment':
                                                    $link = 'payment-success.php?id=' . $notification['related_entity_id'];
                                                    break;
                                                case 'treatment':
                                                    $link = 'treatment-detail.php?id=' . $notification['related_entity_id'];
                                                    break;
                                            }
                                        }
                                        ?>
                                        
                                        <?php if (!empty($link)): ?>
                                            <div class="mt-2">
                                                <a href="<?= $link ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Pagination for Read Notifications -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation" class="mt-3 p-3">
                                    <ul class="pagination justify-content-center mb-0">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="text-center my-4 py-3">
                                <img src="images/bell.png" alt="No Notifications" class="img-fluid mb-3" style="max-width: 120px;">
                                <h6>No previous notifications</h6>
                                <p class="text-muted">Your read notifications will appear here</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
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
            
            // Add AJAX functionality for mark as read buttons
            const markAsReadButtons = document.querySelectorAll('.mark-as-read');
            markAsReadButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const url = this.getAttribute('href');
                    const notificationId = this.dataset.notificationId;
                    const notificationItem = this.closest('.list-group-item');
                    
                    // Disable the button to prevent multiple clicks
                    this.classList.add('disabled');
                    this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';
                    
                    // Send AJAX request
                    fetch('mark-notification-read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `notification_id=${notificationId}`
                    })
                    .then(response => response.json().catch(() => response.text()))
                    .then(result => {
                        if (typeof result === 'object' && result.success) {
                            // Remove the notification item with animation
                            notificationItem.style.transition = 'opacity 0.5s ease';
                            notificationItem.style.opacity = '0';
                            
                            setTimeout(() => {
                                notificationItem.remove();
                                
                                // Check if there are no more unread notifications
                                const remainingUnread = document.querySelectorAll('.card:first-of-type .list-group-item').length;
                                if (remainingUnread === 0) {
                                    // Replace with empty state
                                    const emptyState = `
                                        <div class="text-center my-4 py-3">
                                            <img src="images/bell.png" alt="No Notifications" class="img-fluid mb-3" style="max-width: 120px;">
                                            <h6>No new notifications</h6>
                                            <p class="text-muted">You're all caught up!</p>
                                        </div>
                                    `;
                                    document.querySelector('.card:first-of-type .card-body').innerHTML = emptyState;
                                    
                                    // Hide the Mark All as Read button
                                    const markAllBtn = document.querySelector('button[name="mark_all_read"]');
                                    if (markAllBtn) {
                                        markAllBtn.closest('form').style.display = 'none';
                                    }
                                }
                                
                                // Show success message
                                const alertContainer = document.createElement('div');
                                alertContainer.className = 'alert alert-success alert-dismissible fade show';
                                alertContainer.innerHTML = `
                                    Notification marked as read.
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                `;
                                document.querySelector('.container-fluid').insertBefore(alertContainer, document.querySelector('.card:first-of-type'));
                                
                                // Auto dismiss after 3 seconds
                                setTimeout(() => {
                                    alertContainer.classList.remove('show');
                                    setTimeout(() => alertContainer.remove(), 300);
                                }, 3000);
                                
                                // Refresh the page after a short delay to show in read notifications
                                setTimeout(() => {
                                    window.location.reload();
                                }, 3500);
                            }, 500);
                        } else {
                            // Just reload the page if there's an error or unexpected response
                            window.location.href = url;
                        }
                    })
                    .catch(() => {
                        // Fallback to traditional navigation if fetch fails
                        window.location.href = url;
                    });
                });
            });
        });
    </script>
</body>
</html>