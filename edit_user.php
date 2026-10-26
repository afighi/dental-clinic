<?php
session_start();
// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Initialize variables
$alert = '';
$alert_type = '';
$user = null;

// Get user roles for dropdown
$role_stmt = $conn->prepare("SELECT * FROM userrole");
$role_stmt->execute();
$roles = $role_stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $alert = "No user ID provided.";
    $alert_type = "danger";
} else {
    $user_id = intval($_GET['id']);
    
    // Get user data
    try {
        $stmt = $conn->prepare("SELECT * FROM user WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $alert = "User not found.";
            $alert_type = "danger";
        }
    } catch (PDOException $e) {
        $alert = "Error: " . $e->getMessage();
        $alert_type = "danger";
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $user_id = $_POST['user_id'];
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $suffix = trim($_POST['suffix']);
    $email = trim($_POST['email']);
    $role_id = intval($_POST['role_id']);
    $phone_number = trim($_POST['phone_number']);
    $address = trim($_POST['address']);
    $date_of_birth = $_POST['date_of_birth'];
    
    try {
        // Get current user data for role change detection
        $current_stmt = $conn->prepare("SELECT role_id FROM user WHERE user_id = :user_id");
        $current_stmt->bindParam(':user_id', $user_id);
        $current_stmt->execute();
        $current_user = $current_stmt->fetch(PDO::FETCH_ASSOC);
        $old_role_id = $current_user['role_id'];
        
        // Update user
        $stmt = $conn->prepare("UPDATE user SET first_name = :first_name, middle_name = :middle_name, last_name = :last_name, suffix = :suffix, email = :email, role_id = :role_id, phone_number = :phone_number, address = :address, date_of_birth = :date_of_birth WHERE user_id = :user_id");
        
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':first_name', $first_name);
        $stmt->bindParam(':middle_name', $middle_name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':suffix', $suffix);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':role_id', $role_id);
        $stmt->bindParam(':phone_number', $phone_number);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':date_of_birth', $date_of_birth);
        
        if ($stmt->execute()) {
            // Handle role change (create related info records if needed)
            if ($old_role_id != $role_id) {
                // If changing to staff role, create staff_info
                if ($role_id == 3 && $old_role_id != 3) {
                    // Check if staff record already exists
                    $check_stmt = $conn->prepare("SELECT COUNT(*) FROM staff_info WHERE user_id = :user_id");
                    $check_stmt->bindParam(':user_id', $user_id);
                    $check_stmt->execute();
                    
                    if ($check_stmt->fetchColumn() == 0) {
                        $job_title = "Staff";
                        $info_stmt = $conn->prepare("INSERT INTO staff_info (user_id, job_title) VALUES (:user_id, :job_title)");
                        $info_stmt->bindParam(':user_id', $user_id);
                        $info_stmt->bindParam(':job_title', $job_title);
                        $info_stmt->execute();
                    }
                }
                
                // If changing to dentist role, create dentist_info
                if ($role_id == 2 && $old_role_id != 2) {
                    // Check if dentist record already exists
                    $check_stmt = $conn->prepare("SELECT COUNT(*) FROM dentist_info WHERE user_id = :user_id");
                    $check_stmt->bindParam(':user_id', $user_id);
                    $check_stmt->execute();
                    
                    if ($check_stmt->fetchColumn() == 0) {
                        $license_number = "TBD-" . str_pad($user_id, 5, "0", STR_PAD_LEFT);
                        $specialization = "General Dentistry";
                        $info_stmt = $conn->prepare("INSERT INTO dentist_info (user_id, license_number, specialization) VALUES (:user_id, :license_number, :specialization)");
                        $info_stmt->bindParam(':user_id', $user_id);
                        $info_stmt->bindParam(':license_number', $license_number);
                        $info_stmt->bindParam(':specialization', $specialization);
                        $info_stmt->execute();
                    }
                }
                
                // If changing to patient role, create patient_info
                if ($role_id == 4 && $old_role_id != 4) {
                    // Check if patient record already exists
                    $check_stmt = $conn->prepare("SELECT COUNT(*) FROM patient_info WHERE user_id = :user_id");
                    $check_stmt->bindParam(':user_id', $user_id);
                    $check_stmt->execute();
                    
                    if ($check_stmt->fetchColumn() == 0) {
                        $info_stmt = $conn->prepare("INSERT INTO patient_info (user_id) VALUES (:user_id)");
                        $info_stmt->bindParam(':user_id', $user_id);
                        $info_stmt->execute();
                    }
                }
                
                // Send notification about role change
                $stmt = $conn->prepare("SELECT role_name FROM userrole WHERE role_id = :role_id");
                $stmt->bindParam(':role_id', $role_id);
                $stmt->execute();
                $new_role_name = $stmt->fetchColumn();
                
                $notification_message = "Your account role has been updated to " . $new_role_name;
                $notification_stmt = $conn->prepare("INSERT INTO notification (user_id, notification_type, notification_message, related_entity_id, related_table, is_read, is_sent, send_via, scheduled_time) VALUES (:user_id, 'role_updated', :notification_message, :related_entity_id, 'user', 0, 0, 'email', NOW())");
                $notification_stmt->bindParam(':user_id', $user_id);
                $notification_stmt->bindParam(':notification_message', $notification_message);
                $notification_stmt->bindParam(':related_entity_id', $user_id);
                $notification_stmt->execute();
            }
            
            // Log to audit trail
            $audit_stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details) VALUES (:user_id, 'UPDATE', 'user', :details)");
            $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $details = "Updated user: " . $first_name . " " . $last_name;
            $audit_stmt->bindParam(':details', $details);
            $audit_stmt->execute();
            
            $alert = "User updated successfully!";
            $alert_type = "success";
            
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM user WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $alert = "Error: " . $e->getMessage();
        $alert_type = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - JRC Dental Care</title>
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
                    <h3 class="mb-0">Edit User</h3>
                    <a href="users.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Users
                    </a>
                </div>
                
                <?php if($alert): ?>
                <div class="alert alert-<?= $alert_type ?> alert-dismissible fade show" role="alert">
                    <?= $alert ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if($user): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="middle_name" class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?= htmlspecialchars($user['middle_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="suffix" class="form-label">Suffix</label>
                                    <input type="text" class="form-control" id="suffix" name="suffix" value="<?= htmlspecialchars($user['suffix'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="role_id" class="form-label">Role</label>
                                    <select class="form-select" id="role_id" name="role_id" required>
                                        <?php foreach($roles as $role): ?>
                                        <option value="<?= $role['role_id'] ?>" <?= $user['role_id'] == $role['role_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($role['role_name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="phone_number" class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= $user['date_of_birth'] ?? '' ?>">
                                </div>
                                <div class="col-md-12">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="2"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                                </div>
                                <div class="col-md-12 text-end">
                                    <button type="submit" name="update_user" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i> Save Changes
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-exclamation-triangle text-warning fa-3x mb-3"></i>
                        <h4>User not found</h4>
                        <p class="mb-0">The requested user could not be found or you do not have permission to view it.</p>
                        <a href="users.php" class="btn btn-primary mt-3">
                            <i class="fas fa-arrow-left me-2"></i> Back to Users
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>