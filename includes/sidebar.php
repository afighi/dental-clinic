<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// If database connection is not available in this scope, get it
if (!isset($conn)) {
    require_once 'config/database.php';
    $database = new Database();
    $conn = $database->getConnection();
}

// Get user role if not already set
if (!isset($_SESSION['role_id'])) {
    $stmt = $conn->prepare("SELECT role_id FROM user WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $_SESSION['role_id'] = $stmt->fetchColumn();
}

// Get notification count for badge
$notif_stmt = $conn->prepare("SELECT COUNT(*) FROM notification WHERE is_read = 0 AND user_id = :user_id");
$notif_stmt->bindParam(':user_id', $_SESSION['user_id']);
$notif_stmt->execute();
$notif_count = $notif_stmt->fetchColumn();
?>

<!-- Sidebar -->
<div class="bg-white border-end" id="sidebar-wrapper">
    <div class="sidebar-heading text-center py-4 border-bottom">
        <img src="images/jrclogo.png" alt="JRC Dental Care" class="img-fluid" style="max-height: 60px;">
        <h5 class="mt-2 fw-bold text-primary"></h5>
    </div>
    <div class="list-group list-group-flush sidebar-menu">
        <?php if ($_SESSION['role_id'] == 1): // Admin ?>
            <a href="admin-dashboard.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'admin-dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            <a href="users.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">
                <i class="fas fa-users me-2"></i> User Management
            </a>
            <a href="roles.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'roles.php' ? 'active' : '' ?>">
                <i class="fas fa-user-tag me-2"></i> Role Management
            </a>
            <a href="services.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : '' ?>">
                <i class="fas fa-tooth me-2"></i> Services
            </a>
            <a href="admin-appointments.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'admin-appointments.php' ? 'active' : '' ?>">
        <i class="fas fa-calendar-alt me-2"></i> Appointments
    </a>
            <a href="payments.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : '' ?>">
                <i class="fas fa-credit-card me-2"></i> Payments
            </a>
            <a href="notifications.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : '' ?>">
                <i class="fas fa-bell me-2"></i> Notifications
                <?php if($notif_count > 0): ?>
                <span class="badge rounded-pill bg-danger ms-2 sidebar-notification-counter"><?= $notif_count ?></span>
                <?php endif; ?>
            </a>
            <a href="audittrail.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'audittrail.php' ? 'active' : '' ?>">
                <i class="fas fa-history me-2"></i> Audit Trail
            </a>
            <a href="reports.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar me-2"></i> Reports
            </a>
            <a href="settings.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>">
                <i class="fas fa-cog me-2"></i> Settings
            </a>

        <?php elseif ($_SESSION['role_id'] == 2): // Dentist ?>
            <a href="dentist-dashboard.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'dentist-dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            <a href="appointments.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'appointments.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt me-2"></i> Appointments
            </a>
            <a href="patients.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'patients.php' ? 'active' : '' ?>">
                <i class="fas fa-user me-2"></i> Patients
            </a>
            <a href="treatments.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'treatments.php' ? 'active' : '' ?>">
                <i class="fas fa-tooth me-2"></i> Treatments
            </a>
            <a href="notifications.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : '' ?>">
                <i class="fas fa-bell me-2"></i> Notifications
                <?php if($notif_count > 0): ?>
                <span class="badge rounded-pill bg-danger ms-2 sidebar-notification-counter"><?= $notif_count ?></span>
                <?php endif; ?>
            </a>

        <?php elseif ($_SESSION['role_id'] == 3): // Patient ?>
            <a href="patient-dashboard.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'patient-dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            <a href="book-appointment.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'book-appointment.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-plus me-2"></i> Book Appointment
            </a>
            <a href="my-appointments.php" class="list-group-item list-group-item-action <?= (basename($_SERVER['PHP_SELF']) == 'my-appointments.php' || basename($_SERVER['PHP_SELF']) == 'appointment-details.php') ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt me-2"></i> My Appointments
            </a>
            <a href="my-treatments.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'my-treatments.php' ? 'active' : '' ?>">
                <i class="fas fa-tooth me-2"></i> My Treatments
            </a>
            <a href="my-notifications.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'my-notifications.php' ? 'active' : '' ?>">
                <i class="fas fa-bell me-2"></i> Notifications
                <?php if($notif_count > 0): ?>
                <span class="badge rounded-pill bg-danger ms-2 sidebar-notification-counter"><?= $notif_count ?></span>
                <?php endif; ?>
            </a>
            <a href="my-payments.php" class="list-group-item list-group-item-action <?= (basename($_SERVER['PHP_SELF']) == 'my-payments.php' || basename($_SERVER['PHP_SELF']) == 'make-payment.php' || basename($_SERVER['PHP_SELF']) == 'payment-success.php') ? 'active' : '' ?>">
                <i class="fas fa-credit-card me-2"></i> Payments
                <?php
                // Get unpaid appointments count
                $unpaid_query = $conn->prepare("SELECT COUNT(*) FROM appointment a WHERE a.patient_id = :patient_id 
                              AND a.status != 'canceled' 
                              AND NOT EXISTS (
                                  SELECT 1 FROM payment p 
                                  WHERE p.appointment_id = a.appointment_id 
                                  AND p.status = 'succeeded'
                              )");
                $unpaid_query->bindParam(':patient_id', $_SESSION['user_id']);
                $unpaid_query->execute();
                $unpaid_count = $unpaid_query->fetchColumn();
                
                if ($unpaid_count > 0):
                ?>
                <span class="badge rounded-pill bg-danger ms-2"><?= $unpaid_count ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <!-- Common for all users -->
        <div class="border-top mt-3 pt-3">
            <a href="profile.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
                <i class="fas fa-user-circle me-2"></i> Profile
            </a>
            <?php if ($_SESSION['role_id'] != 3): // Not for patients ?>
            <?php endif; ?>
            <a href="logout.php" class="list-group-item list-group-item-action text-danger">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </div>
    </div>
</div>