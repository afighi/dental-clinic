<!-- If the user is a dentist (role_id = 2), show this sidebar menu -->
<?php if(isset($_SESSION['role_id']) && $_SESSION['role_id'] == 2): ?>
<div class="bg-white border-right" id="sidebar-wrapper">
    <div class="sidebar-heading text-center py-4">
        <img src="images/jrclogo.png" alt="JRC Dental Care" class="img-fluid" style="max-height: 60px;">
    </div>
    <div class="list-group list-group-flush sidebar-menu">
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
        <a href="profile.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user-md me-2"></i> My Profile
        </a>
        <a href="settings.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>">
                <i class="fas fa-cog me-2"></i> Settings
        </a>
        <a href="logout.php" class="list-group-item list-group-item-action text-danger">
            <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>
    </div>
</div>
<?php endif; ?>