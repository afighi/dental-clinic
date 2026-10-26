<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Initialize variables
$alert = '';
$alert_type = '';

// Determine if user is admin, dentist, or other role
$is_admin = ($_SESSION['role_id'] == 1);
$is_dentist = ($_SESSION['role_id'] == 2);

// Pagination settings
$records_per_page = 5;

// Get current page
$unread_page = isset($_GET['unread_page']) ? (int)$_GET['unread_page'] : 1;
$read_page = isset($_GET['read_page']) ? (int)$_GET['read_page'] : 1;

if ($unread_page < 1) $unread_page = 1;
if ($read_page < 1) $read_page = 1;

// Mark notification as read
if (isset($_GET['mark_read']) && !empty($_GET['id'])) {
    $notification_id = intval($_GET['id']);
    
    try {
        if ($is_admin) {
            $stmt = $conn->prepare("UPDATE notification SET is_read = 1 WHERE notification_id = :id");
            $stmt->bindParam(':id', $notification_id);
        } else {
            $stmt = $conn->prepare("UPDATE notification SET is_read = 1 WHERE notification_id = :id AND user_id = :user_id");
            $stmt->bindParam(':id', $notification_id);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
        }
        
        if ($stmt->execute()) {
            $alert = "Notification marked as read.";
            $alert_type = "success";
        }
    } catch (PDOException $e) {
        $alert = "Error: " . $e->getMessage();
        $alert_type = "danger";
    }
}

// Mark all notifications as read
if (isset($_GET['mark_all_read'])) {
    try {
        if ($is_admin) {
            $stmt = $conn->prepare("UPDATE notification SET is_read = 1 WHERE is_read = 0");
        } else {
            $stmt = $conn->prepare("UPDATE notification SET is_read = 1 WHERE is_read = 0 AND user_id = :user_id");
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
        }
        
        if ($stmt->execute()) {
            $alert = "All notifications marked as read.";
            $alert_type = "success";
        }
    } catch (PDOException $e) {
        $alert = "Error: " . $e->getMessage();
        $alert_type = "danger";
    }
}

// Delete notification
if (isset($_GET['delete']) && !empty($_GET['id'])) {
    $notification_id = intval($_GET['id']);
    
    try {
        if ($is_admin) {
            $stmt = $conn->prepare("DELETE FROM notification WHERE notification_id = :id");
            $stmt->bindParam(':id', $notification_id);
        } else {
            $stmt = $conn->prepare("DELETE FROM notification WHERE notification_id = :id AND user_id = :user_id");
            $stmt->bindParam(':id', $notification_id);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
        }
        
        if ($stmt->execute()) {
            $alert = "Notification deleted.";
            $alert_type = "success";
        }
    } catch (PDOException $e) {
        $alert = "Error: " . $e->getMessage();
        $alert_type = "danger";
    }
}

// Create new notification (admin only)
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_notification'])) {
    $recipients = $_POST['recipients'];
    $notification_type = trim($_POST['notification_type']);
    $notification_message = trim($_POST['notification_message']);
    $scheduled_time = !empty($_POST['scheduled_time']) ? $_POST['scheduled_time'] : null;
    
    try {
        if ($recipients === 'all') {
            $recipients_stmt = $conn->prepare("SELECT user_id FROM user");
            $recipients_stmt->execute();
            $recipient_ids = $recipients_stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $role_id = intval($recipients);
            $recipients_stmt = $conn->prepare("SELECT user_id FROM user WHERE role_id = :role_id");
            $recipients_stmt->bindParam(':role_id', $role_id);
            $recipients_stmt->execute();
            $recipient_ids = $recipients_stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        $success_count = 0;
        foreach ($recipient_ids as $user_id) {
            $stmt = $conn->prepare("INSERT INTO notification (user_id, notification_type, notification_message, is_read, is_sent, send_via, scheduled_time) VALUES (:user_id, :notification_type, :notification_message, 0, 0, 'email', :scheduled_time)");
            
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':notification_type', $notification_type);
            $stmt->bindParam(':notification_message', $notification_message);
            $stmt->bindParam(':scheduled_time', $scheduled_time);
            
            if ($stmt->execute()) {
                $success_count++;
            }
        }
        
        if ($success_count > 0) {
            $audit_stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details) VALUES (:user_id, 'INSERT', 'notification', :details)");
            $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $details = "Created " . $success_count . " notifications of type: " . $notification_type;
            $audit_stmt->bindParam(':details', $details);
            $audit_stmt->execute();
            
            $alert = $success_count . " notifications created successfully!";
            $alert_type = "success";
        } else {
            $alert = "No notifications were created.";
            $alert_type = "warning";
        }
    } catch (PDOException $e) {
        $alert = "Error: " . $e->getMessage();
        $alert_type = "danger";
    }
}

// Get roles for recipients dropdown (admin only)
if ($is_admin) {
    $roles_stmt = $conn->prepare("SELECT * FROM userrole ORDER BY role_id");
    $roles_stmt->execute();
    $roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get notifications with pagination
function getNotifications($conn, $is_read, $page, $records_per_page, $is_admin) {
    $offset = ($page - 1) * $records_per_page;
    
    $total_pages = 0;
    $total_records = 0;
    
    if ($is_admin) {
        $count_query = "SELECT COUNT(*) FROM notification WHERE is_read = :is_read";
        $count_stmt = $conn->prepare($count_query);
        $count_stmt->bindParam(':is_read', $is_read, PDO::PARAM_INT);
    } else {
        $count_query = "SELECT COUNT(*) FROM notification WHERE is_read = :is_read AND user_id = :user_id";
        $count_stmt = $conn->prepare($count_query);
        $count_stmt->bindParam(':is_read', $is_read, PDO::PARAM_INT);
        $count_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    }
    
    $count_stmt->execute();
    $total_records = $count_stmt->fetchColumn();
    
    $total_pages = ceil($total_records / $records_per_page);
    
    if ($is_admin) {
        $query = "SELECT n.*, u.username, u.first_name, u.last_name 
                  FROM notification n 
                  LEFT JOIN user u ON n.user_id = u.user_id 
                  WHERE n.is_read = :is_read
                  ORDER BY n.created_at DESC 
                  LIMIT :offset, :limit";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':is_read', $is_read, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    } else {
        $query = "SELECT n.*, u.username, u.first_name, u.last_name 
                  FROM notification n 
                  LEFT JOIN user u ON n.user_id = u.user_id 
                  WHERE n.is_read = :is_read AND n.user_id = :user_id
                  ORDER BY n.created_at DESC 
                  LIMIT :offset, :limit";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':is_read', $is_read, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'notifications' => $notifications,
        'total_pages' => $total_pages,
        'total_records' => $total_records
    ];
}

// Get unread notifications
$unread_result = getNotifications($conn, 0, $unread_page, $records_per_page, $is_admin);
$unread_notifications = $unread_result['notifications'];
$total_unread_pages = $unread_result['total_pages'];
$total_unread = $unread_result['total_records'];

// Get read notifications
$read_result = getNotifications($conn, 1, $read_page, $records_per_page, $is_admin);
$read_notifications = $read_result['notifications'];
$total_read_pages = $read_result['total_pages'];
$total_read = $read_result['total_records'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - JRC Dental Care</title>
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
                    <h3 class="mb-0">Notifications</h3>
                    <div>
                        <a href="notifications.php?mark_all_read=1" class="btn btn-outline-primary me-2">
                            <i class="fas fa-check-double me-2"></i> Mark All as Read
                        </a>
                        <?php if ($is_admin): ?>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createNotificationModal">
                            <i class="fas fa-plus me-2"></i> Create Notification
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if($alert): ?>
                <div class="alert alert-<?= $alert_type ?> alert-dismissible fade show" role="alert">
                    <?= $alert ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <!-- Unread Notifications Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Unread Notifications <span class="badge bg-primary ms-2 notification-count"><?= $total_unread ?></span></h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group unread-notifications">
                            <?php if(count($unread_notifications) > 0): ?>
                                <?php foreach($unread_notifications as $notification): ?>
                                <div class="list-group-item list-group-item-action py-3 bg-light position-relative">
                                    <div class="d-flex w-100 justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">
                                            <?= htmlspecialchars($notification['notification_type']) ?>
                                            <span class="badge bg-primary ms-2">New</span>
                                        </h6>
                                        <small class="text-muted"><?= date('M d, Y h:i A', strtotime($notification['created_at'])) ?></small>
                                    </div>
                                    <p class="mb-1"><?= htmlspecialchars($notification['notification_message']) ?></p>
                                    
                                    <?php if ($is_admin): ?>
                                    <small class="text-muted">
                                    For: 
                                    <?php if(!empty($notification['first_name']) && !empty($notification['last_name'])): ?>
                                        <?= htmlspecialchars($notification['first_name'] . ' ' . $notification['last_name']) ?>
                                        <?php if(!empty($notification['username'])): ?>
                                            (<?= htmlspecialchars($notification['username']) ?>)
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php 
                                        $user_id = $notification['user_id'];
                                        $user_stmt = $conn->prepare("SELECT username, first_name, last_name FROM user WHERE user_id = :user_id");
                                        $user_stmt->bindParam(':user_id', $user_id);
                                        $user_stmt->execute();
                                        $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
                                        
                                        if($user_data): ?>
                                            <?= htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']) ?>
                                            (<?= htmlspecialchars($user_data['username']) ?>)
                                        <?php else: ?>
                                            <em>Deleted User</em>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    </small>
                                    <?php endif; ?>
                                    
                                    <div class="position-absolute top-0 end-0 p-2">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-link text-dark" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="notifications.php?mark_read=1&id=<?= $notification['notification_id'] ?>">
                                                        <i class="fas fa-check me-2"></i> Mark as Read
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="notifications.php?delete=1&id=<?= $notification['notification_id'] ?>" onclick="return confirm('Are you sure you want to delete this notification?')">
                                                        <i class="fas fa-trash me-2"></i> Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-bell fa-3x text-muted mb-3"></i>
                                    <h5>No unread notifications</h5>
                                    <p class="text-muted">You're all caught up!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Unread Pagination -->
                        <?php if($total_unread_pages > 1): ?>
                        <nav aria-label="Unread notification pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= ($unread_page <= 1) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="notifications.php?unread_page=<?= $unread_page - 1 ?>&read_page=<?= $read_page ?>" aria-label="Previous">
                                        <span aria-hidden="true">«</span>
                                    </a>
                                </li>
                                <?php for($i = 1; $i <= $total_unread_pages; $i++): ?>
                                <li class="page-item <?= ($i == $unread_page) ? 'active' : '' ?>">
                                    <a class="page-link" href="notifications.php?unread_page=<?= $i ?>&read_page=<?= $read_page ?>"><?= $i ?></a>
                                </li>
                                <?php endfor; ?>
                                <li class="page-item <?= ($unread_page >= $total_unread_pages) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="notifications.php?unread_page=<?= $unread_page + 1 ?>&read_page=<?= $read_page ?>" aria-label="Next">
                                        <span aria-hidden="true">»</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Read Notifications Card -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Read Notifications <span class="badge bg-secondary ms-2"><?= $total_read ?></span></h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group read-notifications">
                            <?php if(count($read_notifications) > 0): ?>
                                <?php foreach($read_notifications as $notification): ?>
                                <div class="list-group-item list-group-item-action py-3 position-relative">
                                    <div class="d-flex w-100 justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">
                                            <?= htmlspecialchars($notification['notification_type']) ?>
                                        </h6>
                                        <small class="text-muted"><?= date('M d, Y h:i A', strtotime($notification['created_at'])) ?></small>
                                    </div>
                                    <p class="mb-1"><?= htmlspecialchars($notification['notification_message']) ?></p>
                                    
                                    <?php if ($is_admin): ?>
                                    <small class="text-muted">
                                    For: 
                                    <?php if(!empty($notification['first_name']) && !empty($notification['last_name'])): ?>
                                        <?= htmlspecialchars($notification['first_name'] . ' ' . $notification['last_name']) ?>
                                        <?php if(!empty($notification['username'])): ?>
                                            (<?= htmlspecialchars($notification['username']) ?>)
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php 
                                        $user_id = $notification['user_id'];
                                        $user_stmt = $conn->prepare("SELECT username, first_name, last_name FROM user WHERE user_id = :user_id");
                                        $user_stmt->bindParam(':user_id', $user_id);
                                        $user_stmt->execute();
                                        $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
                                        
                                        if($user_data): ?>
                                            <?= htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']) ?>
                                            (<?= htmlspecialchars($user_data['username']) ?>)
                                        <?php else: ?>
                                            <em>Deleted User</em>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    </small>
                                    <?php endif; ?>
                                    
                                    <div class="position-absolute top-0 end-0 p-2">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-link text-dark" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="notifications.php?delete=1&id=<?= $notification['notification_id'] ?>" onclick="return confirm('Are you sure you want to delete this notification?')">
                                                        <i class="fas fa-trash me-2"></i> Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-check-circle fa-3x text-muted mb-3"></i>
                                    <h5>No read notifications</h5>
                                    <p class="text-muted">You haven't read any notifications yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Read Pagination -->
                        <?php if($total_read_pages > 1): ?>
                        <nav aria-label="Read notification pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= ($read_page <= 1) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="notifications.php?read_page=<?= $read_page - 1 ?>&unread_page=<?= $unread_page ?>" aria-label="Previous">
                                        <span aria-hidden="true">«</span>
                                    </a>
                                </li>
                                <?php for($i = 1; $i <= $total_read_pages; $i++): ?>
                                <li class="page-item <?= ($i == $read_page) ? 'active' : '' ?>">
                                    <a class="page-link" href="notifications.php?read_page=<?= $i ?>&unread_page=<?= $unread_page ?>"><?= $i ?></a>
                                </li>
                                <?php endfor; ?>
                                <li class="page-item <?= ($read_page >= $total_read_pages) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="notifications.php?read_page=<?= $read_page + 1 ?>&unread_page=<?= $unread_page ?>" aria-label="Next">
                                        <span aria-hidden="true">»</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($is_admin): ?>
    <!-- Create Notification Modal (Admin Only) -->
    <div class="modal fade" id="createNotificationModal" tabindex="-1" aria-labelledby="createNotificationModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createNotificationModalLabel">Create Notification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="recipients" class="form-label">Recipients</label>
                            <select class="form-select" id="recipients" name="recipients" required>
                                <option value="all">All Users</option>
                                <?php foreach($roles as $role): ?>
                                <option value="<?= $role['role_id'] ?>">All <?= $role['role_name'] ?>s</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="notification_type" class="form-label">Notification Type</label>
                            <input type="text" class="form-control" id="notification_type" name="notification_type" required>
                        </div>
                        <div class="mb-3">
                            <label for="notification_message" class="form-label">Message</label>
                            <textarea class="form-control" id="notification_message" name="notification_message" rows="4" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="scheduled_time" class="form-label">Schedule Time (Optional)</label>
                            <input type="datetime-local" class="form-control" id="scheduled_time" name="scheduled_time">
                            <small class="text-muted">Leave empty to send immediately.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_notification" class="btn btn-primary">Create Notification</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <?php if ($is_admin): ?>
    <script>
        $(document).ready(function() {
            // Function to fetch notifications via AJAX
            function fetchNotifications() {
                $.ajax({
                    url: 'fetch_notifications.php',
                    method: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        // Update navbar notification count
                        const $counter = $('.notification-counter');
                        if (data.count > 0) {
                            $counter.text(data.count > 9 ? '9+' : data.count).show();
                        } else {
                            $counter.hide();
                        }

                        // Update navbar notification list
                        const $notifList = $('.notification-list');
                        $notifList.find('li:not(.dropdown-header, .dropdown-divider, :last)').remove();
                        if (data.notifications.length > 0) {
                            $.each(data.notifications, function(i, notif) {
                                let link = 'notifications.php?id=' + notif.notification_id;
                                const $item = $(`
                                    <li>
                                        <a class="dropdown-item py-2" href="${link}">
                                            <div class="d-flex w-100 justify-content-between">
                                                <small class="fw-bold">${notif.title}</small>
                                                <small class="text-muted">${new Date(notif.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true })}</small>
                                            </div>
                                            <p class="mb-1 small text-truncate">${notif.notification_message}</p>
                                        </a>
                                    </li>
                                `);
                                $notifList.find('.dropdown-divider').before($item);
                            });
                        } else {
                            $notifList.find('.dropdown-divider').before('<li><span class="dropdown-item py-2">No new notifications</span></li>');
                        }

                        // Update unread notifications on the page
                        const $unreadList = $('.unread-notifications');
                        const $unreadCount = $('.notification-count');
                        $unreadCount.text(data.total_unread);
                        $unreadList.empty();
                        if (data.unread_notifications.length > 0) {
                            $.each(data.unread_notifications, function(i, notif) {
                                let userInfo = notif.first_name && notif.last_name ? 
                                    `${notif.first_name} ${notif.last_name} (${notif.username || ''})` : 
                                    '<em>Deleted User</em>';
                                const $item = $(`
                                    <div class="list-group-item list-group-item-action py-3 bg-light position-relative">
                                        <div class="d-flex w-100 justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0">
                                                ${notif.notification_type}
                                                <span class="badge bg-primary ms-2">New</span>
                                            </h6>
                                            <small class="text-muted">${new Date(notif.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true })}</small>
                                        </div>
                                        <p class="mb-1">${notif.notification_message}</p>
                                        <small class="text-muted">For: ${userInfo}</small>
                                        <div class="position-absolute top-0 end-0 p-2">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-link text-dark" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <a class="dropdown-item" href="notifications.php?mark_read=1&id=${notif.notification_id}">
                                                            <i class="fas fa-check me-2"></i> Mark as Read
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="notifications.php?delete=1&id=${notif.notification_id}" onclick="return confirm('Are you sure you want to delete this notification?')">
                                                            <i class="fas fa-trash me-2"></i> Delete
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                `);
                                $unreadList.append($item);
                            });
                        } else {
                            $unreadList.html(`
                                <div class="text-center py-5">
                                    <i class="fas fa-bell fa-3x text-muted mb-3"></i>
                                    <h5>No unread notifications</h5>
                                    <p class="text-muted">You're all caught up!</p>
                                </div>
                            `);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error fetching notifications:', error);
                    },
                    complete: function() {
                        // Schedule the next poll
                        setTimeout(fetchNotifications, 10000); // Poll every 10 seconds
                    }
                });
            }

            // Start polling for admins only
            <?php if ($is_admin): ?>
            fetchNotifications();
            <?php endif; ?>
        });
    </script>
    <?php endif; ?>
</body>
</html>