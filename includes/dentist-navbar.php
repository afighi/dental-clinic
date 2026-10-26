    <!-- This code should be added to the existing navbar.php file -->

<!-- If the user is a dentist (role_id = 2), show this navbar -->
<?php if(isset($_SESSION['role_id']) && $_SESSION['role_id'] == 2): ?>
<nav class="navbar navbar-expand-lg navbar-light bg-white py-3 border-bottom">
    <div class="container-fluid">
        <button class="btn btn-sm" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <span class="navbar-brand ms-2 d-none d-md-block">JRC Dental Care</span>
        
        <ul class="navbar-nav ms-auto">
            <?php
            // Get unread notifications
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notification WHERE user_id = :user_id AND is_read = 0");
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            $notification_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $conn->prepare("SELECT * FROM notification WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5");
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <!-- Notifications Dropdown -->
            <li class="nav-item dropdown me-3">
                <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-bell"></i>
                    <?php if($notification_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationCounter">
                        <?= $notification_count ?>
                    </span>
                    <?php endif; ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                    <li><h6 class="dropdown-header">Notifications</h6></li>
                    <?php if(count($notifications) > 0): ?>
                        <?php foreach($notifications as $notification): ?>
                        <li>
                            <a class="dropdown-item notification-item <?= $notification['is_read'] ? 'read' : 'unread' ?>" href="#" data-notification-id="<?= $notification['notification_id'] ?>">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-info-circle text-primary"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-2">
                                        <p class="mb-0"><?= $notification['message'] ?></p>
                                        <small class="text-muted"><?= date('M d, h:i A', strtotime($notification['created_at'])) ?></small>
                                    </div>
                                </div>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center small" href="notifications.php">View All Notifications</a></li>
                    <?php else: ?>
                        <li><a class="dropdown-item text-center" href="#">No notifications</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            
            <!-- User Dropdown -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="d-none d-md-inline me-2"><?= $_SESSION['first_name'] ?></span>
                    <i class="fas fa-user-circle"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                    <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
<?php endif; ?>