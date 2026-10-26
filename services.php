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

// Pagination variables
$records_per_page = 10; // Number of services per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Current page, default is 1
$offset = ($page - 1) * $records_per_page; // Calculate the offset for SQL LIMIT

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new service
    if (isset($_POST['add_service'])) {
        $service_name = trim($_POST['service_name']);
        $description = trim($_POST['description']);
        $price = intval($_POST['price'] * 100); // Convert to centavos for PayMongo
        $duration_minutes = intval($_POST['duration_minutes']);
        
        try {
            $stmt = $conn->prepare("INSERT INTO service (service_name, description, price, duration_minutes) VALUES (:service_name, :description, :price, :duration_minutes)");
            $stmt->bindParam(':service_name', $service_name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':duration_minutes', $duration_minutes);
            
            if ($stmt->execute()) {
                // Log to audit trail
                $service_id = $conn->lastInsertId();
                $audit_stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details) VALUES (:user_id, 'INSERT', 'service', :details)");
                $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $details = "Added new service: " . $service_name;
                $audit_stmt->bindParam(':details', $details);
                $audit_stmt->execute();
                
                $alert = "Service added successfully!";
                $alert_type = "success";
            }
        } catch (PDOException $e) {
            $alert = "Error: " . $e->getMessage();
            $alert_type = "danger";
        }
    }
    
    // Update service
    if (isset($_POST['update_service'])) {
        $service_id = $_POST['service_id'];
        $service_name = trim($_POST['service_name']);
        $description = trim($_POST['description']);
        $price = intval($_POST['price'] * 100); // Convert to centavos for PayMongo
        $duration_minutes = intval($_POST['duration_minutes']);
        
        try {
            $stmt = $conn->prepare("UPDATE service SET service_name = :service_name, description = :description, price = :price, duration_minutes = :duration_minutes WHERE service_id = :service_id");
            $stmt->bindParam(':service_name', $service_name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':duration_minutes', $duration_minutes);
            $stmt->bindParam(':service_id', $service_id);
            
            if ($stmt->execute()) {
                // Log to audit trail
                $audit_stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details) VALUES (:user_id, 'UPDATE', 'service', :details)");
                $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $details = "Updated service: " . $service_name;
                $audit_stmt->bindParam(':details', $details);
                $audit_stmt->execute();
                
                $alert = "Service updated successfully!";
                $alert_type = "success";
            }
        } catch (PDOException $e) {
            $alert = "Error: " . $e->getMessage();
            $alert_type = "danger";
        }
    }
    
    // Delete service
    if (isset($_POST['delete_service'])) {
        $service_id = $_POST['service_id'];
        
        // Check if service is being used in appointments
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM appointment WHERE service_id = :service_id");
        $check_stmt->bindParam(':service_id', $service_id);
        $check_stmt->execute();
        
        if ($check_stmt->fetchColumn() > 0) {
            $alert = "Cannot delete service because it is associated with one or more appointments.";
            $alert_type = "danger";
        } else {
            try {
                // Get service name for audit trail
                $name_stmt = $conn->prepare("SELECT service_name FROM service WHERE service_id = :service_id");
                $name_stmt->bindParam(':service_id', $service_id);
                $name_stmt->execute();
                $service_name = $name_stmt->fetchColumn();
                
                // Delete the service
                $stmt = $conn->prepare("DELETE FROM service WHERE service_id = :service_id");
                $stmt->bindParam(':service_id', $service_id);
                
                if ($stmt->execute()) {
                    // Log to audit trail
                    $audit_stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details) VALUES (:user_id, 'DELETE', 'service', :details)");
                    $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $details = "Deleted service: " . $service_name;
                    $audit_stmt->bindParam(':details', $details);
                    $audit_stmt->execute();
                    
                    $alert = "Service deleted successfully!";
                    $alert_type = "success";
                }
            } catch (PDOException $e) {
                $alert = "Error: " . $e->getMessage();
                $alert_type = "danger";
            }
        }
    }
}

// Get total number of services for pagination
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM service");
$count_stmt->execute();
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get services with pagination
$stmt = $conn->prepare("SELECT * FROM service ORDER BY service_name LIMIT :offset, :records_per_page");
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':records_per_page', $records_per_page, PDO::PARAM_INT);
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services - JRC Dental Care</title>
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
                    <h3 class="mb-0">Manage Services</h3>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                        <i class="fas fa-plus me-2"></i> Add New Service
                    </button>
                </div>
                
                <?php if($alert): ?>
                <div class="alert alert-<?= $alert_type ?> alert-dismissible fade show" role="alert">
                    <?= $alert ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="servicesTable">
                                <thead>
                                    <tr>
                                        <th width="5%">ID</th>
                                        <th width="20%">Service Name</th>
                                        <th width="30%">Description</th>
                                        <th width="15%">Price (PHP)</th>
                                        <th width="15%">Duration (min)</th>
                                        <th width="15%">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($services as $service): ?>
                                    <tr>
                                        <td><?= $service['service_id'] ?></td>
                                        <td><?= htmlspecialchars($service['service_name']) ?></td>
                                        <td><?= htmlspecialchars($service['description']) ?></td>
                                        <td>₱<?= number_format($service['price'] / 100, 2) ?></td>
                                        <td><?= $service['duration_minutes'] ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary edit-btn" 
                                                    data-id="<?= $service['service_id'] ?>" 
                                                    data-name="<?= htmlspecialchars($service['service_name']) ?>" 
                                                    data-description="<?= htmlspecialchars($service['description']) ?>" 
                                                    data-price="<?= $service['price'] / 100 ?>" 
                                                    data-duration="<?= $service['duration_minutes'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger delete-btn" 
                                                    data-id="<?= $service['service_id'] ?>" 
                                                    data-name="<?= htmlspecialchars($service['service_name']) ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination Navigation -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <!-- Previous Page Link -->
                                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                
                                <!-- Page Numbers -->
                                <?php for ($i = max(1, $page - 2); $i <= min($page + 2, $total_pages); $i++): ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <!-- Next Page Link -->
                                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                        
                        <!-- Pagination Information -->
                        <div class="text-center mt-2">
                            <p class="text-muted">
                                Showing <?= count($services) ?> of <?= $total_records ?> services 
                                (Page <?= $page ?> of <?= $total_pages ?>)
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Service Modal -->
    <div class="modal fade" id="addServiceModal" tabindex="-1" aria-labelledby="addServiceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addServiceModalLabel">Add New Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="service_name" class="form-label">Service Name</label>
                            <input type="text" class="form-control" id="service_name" name="service_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="price" class="form-label">Price (PHP)</label>
                            <input type="number" step="0.01" class="form-control" id="price" name="price" required>
                        </div>
                        <div class="mb-3">
                            <label for="duration_minutes" class="form-label">Duration (minutes)</label>
                            <input type="number" class="form-control" id="duration_minutes" name="duration_minutes" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_service" class="btn btn-primary">Add Service</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Service Modal -->
    <div class="modal fade" id="editServiceModal" tabindex="-1" aria-labelledby="editServiceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editServiceModalLabel">Edit Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <input type="hidden" name="page" value="<?= $page ?>">
                    <div class="modal-body">
                        <input type="hidden" id="edit_service_id" name="service_id">
                        <div class="mb-3">
                            <label for="edit_service_name" class="form-label">Service Name</label>
                            <input type="text" class="form-control" id="edit_service_name" name="service_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_price" class="form-label">Price (PHP)</label>
                            <input type="number" step="0.01" class="form-control" id="edit_price" name="price" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_duration_minutes" class="form-label">Duration (minutes)</label>
                            <input type="number" class="form-control" id="edit_duration_minutes" name="duration_minutes" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_service" class="btn btn-primary">Update Service</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Service Modal -->
    <div class="modal fade" id="deleteServiceModal" tabindex="-1" aria-labelledby="deleteServiceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteServiceModalLabel">Delete Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <input type="hidden" name="page" value="<?= $page ?>">
                    <div class="modal-body">
                        <input type="hidden" id="delete_service_id" name="service_id">
                        <p>Are you sure you want to delete the service: <span id="service_name_display" class="fw-bold"></span>?</p>
                        <p class="text-danger">This action cannot be undone!</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_service" class="btn btn-danger">Delete Service</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script>
        // Edit button click
        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const description = this.getAttribute('data-description');
                const price = this.getAttribute('data-price');
                const duration = this.getAttribute('data-duration');
                
                document.getElementById('edit_service_id').value = id;
                document.getElementById('edit_service_name').value = name;
                document.getElementById('edit_description').value = description;
                document.getElementById('edit_price').value = price;
                document.getElementById('edit_duration_minutes').value = duration;
                
                const editModal = new bootstrap.Modal(document.getElementById('editServiceModal'));
                editModal.show();
            });
        });
        
        // Delete button click
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                
                document.getElementById('delete_service_id').value = id;
                document.getElementById('service_name_display').textContent = name;
                
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteServiceModal'));
                deleteModal.show();
            });
        });
    </script>
</body>
</html>