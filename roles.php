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

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new role
    if (isset($_POST['add_role'])) {
        $role_name = trim($_POST['role_name']);
        
        try {
            // Check if role already exists
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM userrole WHERE role_name = :role_name");
            $check_stmt->bindParam(':role_name', $role_name);
            $check_stmt->execute();
            
            if ($check_stmt->fetchColumn() > 0) {
                $alert = "Role already exists.";
                $alert_type = "danger";
            } else {
                $stmt = $conn->prepare("INSERT INTO userrole (role_name) VALUES (:role_name)");
                $stmt->bindParam(':role_name', $role_name);
                
                if ($stmt->execute()) {
                    // Log to audit trail
                    $role_id = $conn->lastInsertId();
                    $audit_stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details) VALUES (:user_id, 'INSERT', 'userrole', :details)");
                    $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $details = "Added new role: " . $role_name;
                    $audit_stmt->bindParam(':details', $details);
                    $audit_stmt->execute();
                    
                    $alert = "Role added successfully!";
                    $alert_type = "success";
                }
            }
        } catch (PDOException $e) {
            $alert = "Error: " . $e->getMessage();
            $alert_type = "danger";
        }
    }
    
    // Update role
    if (isset($_POST['update_role'])) {
        $role_id = $_POST['role_id'];
        $role_name = trim($_POST['role_name']);
        
        try {
            // Check if role already exists (excluding the current one)
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM userrole WHERE role_name = :role_name AND role_id != :role_id");
            $check_stmt->bindParam(':role_name', $role_name);
            $check_stmt->bindParam(':role_id', $role_id);
            $check_stmt->execute();
            
            if ($check_stmt->fetchColumn() > 0) {
                $alert = "Role name already exists.";
                $alert_type = "danger";
            } else {
                $stmt = $conn->prepare("UPDATE userrole SET role_name = :role_name WHERE role_id = :role_id");
                $stmt->bindParam(':role_name', $role_name);
                $stmt->bindParam(':role_id', $role_id);
                
                if ($stmt->execute()) {
                    // Log to audit trail
                    $audit_stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details) VALUES (:user_id, 'UPDATE', 'userrole', :details)");
                    $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $details = "Updated role: " . $role_name;
                    $audit_stmt->bindParam(':details', $details);
                    $audit_stmt->execute();
                    
                    $alert = "Role updated successfully!";
                    $alert_type = "success";
                }
            }
        } catch (PDOException $e) {
            $alert = "Error: " . $e->getMessage();
            $alert_type = "danger";
        }
    }
    
    // Change user role and create work account
    if (isset($_POST['change_role'])) {
        $user_id = $_POST['user_id'];
        $new_role_id = $_POST['new_role_id'];
        
        try {
            // Get user details
            $user_stmt = $conn->prepare("SELECT * FROM user WHERE user_id = :user_id");
            $user_stmt->bindParam(':user_id', $user_id);
            $user_stmt->execute();
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get old role name for audit trail
            $old_role_stmt = $conn->prepare("SELECT role_name FROM userrole WHERE role_id = :role_id");
            $old_role_stmt->bindParam(':role_id', $user['role_id']);
            $old_role_stmt->execute();
            $old_role_name = $old_role_stmt->fetchColumn();
            
            // Get new role name for audit trail
            $new_role_stmt = $conn->prepare("SELECT role_name FROM userrole WHERE role_id = :role_id");
            $new_role_stmt->bindParam(':role_id', $new_role_id);
            $new_role_stmt->execute();
            $new_role_name = $new_role_stmt->fetchColumn();
            
            // Update user role
            $update_stmt = $conn->prepare("UPDATE user SET role_id = :role_id WHERE user_id = :user_id");
            $update_stmt->bindParam(':role_id', $new_role_id);
            $update_stmt->bindParam(':user_id', $user_id);
            
            if ($update_stmt->execute()) {
                // Create related info records based on new role
                
                // If changing to Dentist role
                if ($new_role_id == 2) {
                    // Check if dentist info already exists
                    $check_stmt = $conn->prepare("SELECT COUNT(*) FROM dentist_info WHERE user_id = :user_id");
                    $check_stmt->bindParam(':user_id', $user_id);
                    $check_stmt->execute();
                    
                    if ($check_stmt->fetchColumn() == 0) {
                        // Create default dentist info
                        $license_number = "TBD-" . str_pad($user_id, 5, "0", STR_PAD_LEFT);
                        $specialization = "General Dentistry";
                        
                        $info_stmt = $conn->prepare("INSERT INTO dentist_info (user_id, license_number, specialization) VALUES (:user_id, :license_number, :specialization)");
                        $info_stmt->bindParam(':user_id', $user_id);
                        $info_stmt->bindParam(':license_number', $license_number);
                        $info_stmt->bindParam(':specialization', $specialization);
                        $info_stmt->execute();
                    }
                }
                
                
                // If changing to Patient role
                if ($new_role_id == 3) {
                    // Check if patient info already exists
                    $check_stmt = $conn->prepare("SELECT COUNT(*) FROM patient_info WHERE user_id = :user_id");
                    $check_stmt->bindParam(':user_id', $user_id);
                    $check_stmt->execute();
                    
                    if ($check_stmt->fetchColumn() == 0) {
                        // Create default patient info
                        $info_stmt = $conn->prepare("INSERT INTO patient_info (user_id) VALUES (:user_id)");
                        $info_stmt->bindParam(':user_id', $user_id);
                        $info_stmt->execute();
                    }
                }
                
                // Create notification for role change
                $notification_message = "Your account role has been changed from " . $old_role_name . " to " . $new_role_name;
                $notification_stmt = $conn->prepare("INSERT INTO notification (user_id, notification_type, notification_message, related_entity_id, related_table, is_read, is_sent, send_via, scheduled_time) VALUES (:user_id, 'role_changed', :notification_message, :related_entity_id, 'user', 0, 0, 'email', NOW())");
                $notification_stmt->bindParam(':user_id', $user_id);
                $notification_stmt->bindParam(':notification_message', $notification_message);
                $notification_stmt->bindParam(':related_entity_id', $user_id);
                $notification_stmt->execute();
                
                // Log to audit trail
                $audit_stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details) VALUES (:user_id, 'UPDATE', 'user', :details)");
                $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $details = "Changed role for user ID " . $user_id . " from " . $old_role_name . " to " . $new_role_name;
                $audit_stmt->bindParam(':details', $details);
                $audit_stmt->execute();
                
                $alert = "User role changed successfully and work account created!";
                $alert_type = "success";
            }
        } catch (PDOException $e) {
            $alert = "Error: " . $e->getMessage();
            $alert_type = "danger";
        }
    }
}

// Get all roles
$stmt = $conn->prepare("SELECT * FROM userrole ORDER BY role_id");
$stmt->execute();
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get users that can have their role changed (exclude admin role)
$users_stmt = $conn->prepare("SELECT u.user_id, u.username, u.first_name, u.last_name, u.email, r.role_name 
                             FROM user u 
                             JOIN userrole r ON u.role_id = r.role_id 
                             WHERE u.role_id != 1 
                             ORDER BY u.first_name, u.last_name");
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Management - JRC Dental Care</title>
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
                    <h3 class="mb-0">Role Management</h3>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                        <i class="fas fa-plus me-2"></i> Add New Role
                    </button>
                </div>
                
                <?php if($alert): ?>
                <div class="alert alert-<?= $alert_type ?> alert-dismissible fade show" role="alert">
                    <?= $alert ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" id="roleTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="roles-tab" data-bs-toggle="tab" data-bs-target="#roles-tab-pane" type="button" role="tab" aria-controls="roles-tab-pane" aria-selected="true">Roles</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="change-role-tab" data-bs-toggle="tab" data-bs-target="#change-role-tab-pane" type="button" role="tab" aria-controls="change-role-tab-pane" aria-selected="false">Change User Role</button>
                    </li>
                </ul>
                
                <div class="tab-content" id="roleTabsContent">
                    <!-- Roles Tab -->
                    <div class="tab-pane fade show active" id="roles-tab-pane" role="tabpanel" aria-labelledby="roles-tab" tabindex="0">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th width="10%">ID</th>
                                                <th width="60%">Role Name</th>
                                                <th width="20%">Created At</th>
                                                <th width="10%">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($roles as $role): ?>
                                            <tr>
                                                <td><?= $role['role_id'] ?></td>
                                                <td><?= htmlspecialchars($role['role_name']) ?></td>
                                                <td><?= date('M d, Y', strtotime($role['created_at'])) ?></td>
                                                <td>
                                                    <?php if($role['role_id'] > 5): // Prevent editing default roles ?>
                                                    <button class="btn btn-sm btn-outline-primary edit-btn" 
                                                            data-id="<?= $role['role_id'] ?>" 
                                                            data-name="<?= htmlspecialchars($role['role_name']) ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php else: ?>
                                                    <button class="btn btn-sm btn-outline-secondary" disabled>
                                                        <i class="fas fa-lock"></i>
                                                    </button>
                                                    <?php endif; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Change User Role Tab -->
                    <div class="tab-pane fade" id="change-role-tab-pane" role="tabpanel" aria-labelledby="change-role-tab" tabindex="0">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th width="5%">ID</th>
                                                <th width="20%">Name</th>
                                                <th width="20%">Username</th>
                                                <th width="20%">Email</th>
                                                <th width="15%">Current Role</th>
                                                <th width="20%">Change Role</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($users as $user): ?>
                                            <tr>
                                                <td><?= $user['user_id'] ?></td>
                                                <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                                <td><?= htmlspecialchars($user['username']) ?></td>
                                                <td><?= htmlspecialchars($user['email']) ?></td>
                                                <td><span class="badge bg-primary"><?= htmlspecialchars($user['role_name']) ?></span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary change-role-btn" 
                                                            data-id="<?= $user['user_id'] ?>" 
                                                            data-name="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>"
                                                            data-current-role="<?= htmlspecialchars($user['role_name']) ?>">
                                                        <i class="fas fa-exchange-alt me-1"></i> Change Role
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Role Modal -->
    <div class="modal fade" id="addRoleModal" tabindex="-1" aria-labelledby="addRoleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addRoleModalLabel">Add New Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="role_name" class="form-label">Role Name</label>
                            <input type="text" class="form-control" id="role_name" name="role_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_role" class="btn btn-primary">Add Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Role Modal -->
    <div class="modal fade" id="editRoleModal" tabindex="-1" aria-labelledby="editRoleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editRoleModalLabel">Edit Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" id="edit_role_id" name="role_id">
                        <div class="mb-3">
                            <label for="edit_role_name" class="form-label">Role Name</label>
                            <input type="text" class="form-control" id="edit_role_name" name="role_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_role" class="btn btn-primary">Update Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Change Role Modal -->
    <div class="modal fade" id="changeRoleModal" tabindex="-1" aria-labelledby="changeRoleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changeRoleModalLabel">Change User Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" id="change_user_id" name="user_id">
                        <p>Changing role for: <span id="change_user_name" class="fw-bold"></span></p>
                        <p>Current role: <span id="current_role" class="badge bg-primary"></span></p>
                        <div class="mb-3">
                            <label for="new_role_id" class="form-label">New Role</label>
                            <select class="form-select" id="new_role_id" name="new_role_id" required>
                                <?php foreach($roles as $role): ?>
                                <?php if($role['role_id'] != 1): // Exclude Admin role ?>
                                <option value="<?= $role['role_id'] ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Changing a user's role will automatically create the appropriate work account if needed.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="change_role" class="btn btn-primary">Change Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Edit role button click
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    
                    document.getElementById('edit_role_id').value = id;
                    document.getElementById('edit_role_name').value = name;
                    
                    const editModal = new bootstrap.Modal(document.getElementById('editRoleModal'));
                    editModal.show();
                });
            });
            
            // Change role button click
            document.querySelectorAll('.change-role-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    const currentRole = this.getAttribute('data-current-role');
                    
                    document.getElementById('change_user_id').value = id;
                    document.getElementById('change_user_name').textContent = name;
                    document.getElementById('current_role').textContent = currentRole;
                    
                    const changeModal = new bootstrap.Modal(document.getElementById('changeRoleModal'));
                    changeModal.show();
                });
            });
        });
    </script>
</body>
</html>