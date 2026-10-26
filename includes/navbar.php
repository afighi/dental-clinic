<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
    <div class="container-fluid px-4">
        <button class="btn btn-sm btn-primary" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="ms-auto d-flex align-items-center">
            <!-- Notifications Dropdown -->
            <div class="dropdown me-3">
                <?php
                // Get notification count for current user
                if (!isset($conn)) {
                    require_once 'config/database.php';
                    $database = new Database();
                    $conn = $database->getConnection();
                }
                
                // Get notification count
                $notif_count_stmt = $conn->prepare("SELECT COUNT(*) FROM notification WHERE is_read = 0 AND user_id = :user_id");
                $notif_count_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $notif_count_stmt->execute();
                $notif_count = $notif_count_stmt->fetchColumn();
                ?>
                <a class="nav-link dropdown-toggle position-relative" href="#" id="navbarDropdownNotif" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-bell"></i>
                    <?php if($notif_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-counter">
                        <?= ($notif_count > 9) ? '9+' : $notif_count ?>
                    </span>
                    <?php endif; ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm notification-list" aria-labelledby="navbarDropdownNotif" style="min-width: 280px;">
                    <li><h6 class="dropdown-header">Notifications</h6></li>
                    <?php
                    // Get latest notifications for current user only
                    $stmt = $conn->prepare("SELECT * FROM notification WHERE is_read = 0 AND user_id = :user_id ORDER BY created_at DESC LIMIT 5");
                    $stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $stmt->execute();
                    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if(count($notifications) > 0):
                        foreach($notifications as $notif):
                    ?>
                    <li>
                        <?php 
                        // Set the correct notification link based on type
                        $notification_link = 'notifications.php?id=' . $notif['notification_id'];
                        if ($_SESSION['role_id'] == 3) { // For patients
                            $notification_link = 'my-notifications.php';
                            
                            // If it's an appointment or payment notification with related_id
                            if ($notif['notification_type'] == 'appointment' && !empty($notif['related_id'])) {
                                $notification_link = 'appointment-details.php?id=' . $notif['related_id'];
                            } elseif ($notif['notification_type'] == 'payment' && !empty($notif['related_id'])) {
                                $notification_link = 'payment-receipt.php?id=' . $notif['related_id'];
                            }
                        }
                        ?>
                        <a class="dropdown-item py-2" href="<?= $notification_link ?>">
                            <div class="d-flex w-100 justify-content-between">
                                <small class="fw-bold"><?= isset($notif['title']) ? $notif['title'] : 'Notification' ?></small>
                                <small class="text-muted"><?= date('M d, h:i A', strtotime($notif['created_at'])) ?></small>
                            </div>
                            <p class="mb-1 small text-truncate">
                                <?php
                                // Check which message field exists and use the appropriate one
                                if (isset($notif['notification_message'])) {
                                    echo $notif['notification_message'];
                                } elseif (isset($notif['message'])) {
                                    echo $notif['message'];
                                } else {
                                    echo 'New notification';
                                }
                                ?>
                            </p>
                        </a>
                    </li>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <li><span class="dropdown-item py-2">No new notifications</span></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <?php if ($_SESSION['role_id'] == 3): // For patients ?>
                        <a class="dropdown-item text-center" href="my-notifications.php">View all notifications</a>
                        <?php else: ?>
                        <a class="dropdown-item text-center" href="notifications.php">View all notifications</a>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
            
            <!-- User Dropdown -->
            <div class="dropdown">
                <?php
                // Get user info
                $stmt = $conn->prepare("SELECT first_name, last_name FROM user WHERE user_id = :user_id");
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $user_initials = substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1);
                ?>
                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdownUser" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                        <?= $user_initials ?>
                    </div>
                    <span class="d-none d-md-inline"><?= $user['first_name'] . ' ' . $user['last_name'] ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="navbarDropdownUser">
                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle me-2"></i> Profile</a></li>
                    
                    <?php if ($_SESSION['role_id'] == 3): // Patient specific options ?>
                    <li><a class="dropdown-item" href="my-appointments.php"><i class="fas fa-calendar-check me-2"></i> My Appointments</a></li>
                    <li><a class="dropdown-item" href="my-payments.php"><i class="fas fa-credit-card me-2"></i> My Payments</a></li>
                    <?php elseif ($_SESSION['role_id'] == 1): // Admin specific options ?>
                    <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                    <?php endif; ?>
                    
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>