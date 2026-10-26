<?php
session_start();
// Check if user is logged in and is a dentist
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Process filter options
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Build query based on filters
$query = "SELECT a.*, u.first_name, u.last_name, s.service_name 
         FROM appointment a 
         JOIN user u ON a.patient_id = u.user_id 
         JOIN service s ON a.service_id = s.service_id 
         WHERE a.dentist_id = :dentist_id";

$params = [':dentist_id' => $_SESSION['user_id']];

if (!empty($filter_date)) {
    $query .= " AND DATE(a.appointment_date) = :date";
    $params[':date'] = $filter_date;
}

if (!empty($filter_status)) {
    // Fix: Handle both spellings in the filter
    if ($filter_status == 'cancelled') {
        $query .= " AND (a.status = 'cancelled' OR a.status = 'canceled')";
    } else {
        $query .= " AND a.status = :status";
        $params[':status'] = $filter_status;
    }
}

$query .= " ORDER BY a.appointment_date ASC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get counts for different statuses
$status_counts = [];
$status_types = ['pending', 'confirmed', 'completed', 'cancelled'];

foreach ($status_types as $status) {
    // Fix: For 'cancelled', check both spellings
    if ($status == 'cancelled') {
        $count_stmt = $conn->prepare("SELECT COUNT(*) FROM appointment WHERE dentist_id = :dentist_id AND (status = 'cancelled' OR status = 'canceled')");
        $count_stmt->bindParam(':dentist_id', $_SESSION['user_id']);
    } else {
        $count_stmt = $conn->prepare("SELECT COUNT(*) FROM appointment WHERE dentist_id = :dentist_id AND status = :status");
        $count_stmt->bindParam(':dentist_id', $_SESSION['user_id']);
        $count_stmt->bindParam(':status', $status);
    }
    $count_stmt->execute();
    $status_counts[$status] = $count_stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - JRC Dental Care</title>
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
                    <h3 class="mb-0">Appointments</h3>
                </div>
                
                <!-- Status Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="icon-container bg-warning bg-opacity-10 rounded-circle p-3 me-3">
                                        <i class="fas fa-clock text-warning"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Pending</h6>
                                        <h3 class="mb-0"><?= $status_counts['pending'] ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="icon-container bg-info bg-opacity-10 rounded-circle p-3 me-3">
                                        <i class="fas fa-calendar-check text-info"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Confirmed</h6>
                                        <h3 class="mb-0"><?= $status_counts['confirmed'] ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="icon-container bg-success bg-opacity-10 rounded-circle p-3 me-3">
                                        <i class="fas fa-check-circle text-success"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Completed</h6>
                                        <h3 class="mb-0"><?= $status_counts['completed'] ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="icon-container bg-danger bg-opacity-10 rounded-circle p-3 me-3">
                                        <i class="fas fa-times-circle text-danger"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Cancelled</h6>
                                        <h3 class="mb-0"><?= $status_counts['cancelled'] ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters and Search -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="date" class="form-label">Filter by Date</label>
                                <input type="date" class="form-control" id="date" name="date" value="<?= $filter_date ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Filter by Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="confirmed" <?= $filter_status == 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                    <option value="completed" <?= $filter_status == 'completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="cancelled" <?= $filter_status == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="search" placeholder="Search by patient name">
                                    <button class="btn btn-outline-primary" type="button" id="searchBtn">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">Filter</button>
                                <a href="appointments.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Appointments Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">All Appointments</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($appointments) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="appointmentsTable">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Service</th>
                                        <th>Date & Time</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appointment): ?>
                                    <tr>
                                        <td><?= $appointment['first_name'] . ' ' . $appointment['last_name'] ?></td>
                                        <td><?= $appointment['service_name'] ?></td>
                                        <td><?= date('M d, Y h:i A', strtotime($appointment['appointment_date'])) ?></td>
                                        <td>
                                            <?php 
                                             $status_class = '';
                                             $display_status = $appointment['status'];
                                             
                                             // Standardize display status
                                             if ($display_status == 'canceled') {
                                                 $display_status = 'cancelled';
                                             }
                                             
                                             switch ($display_status) {
                                                 case 'pending':
                                                     $status_class = 'bg-warning text-dark';
                                                     break;
                                                 case 'confirmed':
                                                     $status_class = 'bg-info text-white';
                                                     break;
                                                 case 'completed':
                                                     $status_class = 'bg-success text-white';
                                                     break;
                                                 case 'cancelled':
                                                     $status_class = 'badge-cancelled'; // Custom class
                                                     break;
                                             }
                                            ?>
                                            <span class="badge <?= $status_class ?>">
                                                <?= ucfirst($display_status) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton1" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Action
                                                </button>
                                                <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton1">
                                                    <li><a class="dropdown-item" href="appointment-details.php?id=<?= $appointment['appointment_id'] ?>">View Details</a></li>
                                                    <?php if ($appointment['status'] == 'pending'): ?>
                                                        <li><a class="dropdown-item update-status" href="#" data-id="<?= $appointment['appointment_id'] ?>" data-status="confirmed">Confirm</a></li>
                                                    <?php endif; ?>
                                                    <?php if ($appointment['status'] == 'confirmed'): ?>
                                                        <li><a class="dropdown-item update-status" href="#" data-id="<?= $appointment['appointment_id'] ?>" data-status="completed">Mark as Completed</a></li>
                                                    <?php endif; ?>
                                                    <?php if ($appointment['status'] != 'cancelled' && $appointment['status'] != 'canceled' && $appointment['status'] != 'completed'): ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item update-status" href="#" data-id="<?= $appointment['appointment_id'] ?>" data-status="cancelled">Cancel</a></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <img src="assets/images/no-data.svg" alt="No Appointments" class="img-fluid mb-3" style="max-width: 150px;">
                            <h5>No appointments found</h5>
                            <p class="text-muted">Try changing your filters or check back later</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dentist-dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    document.getElementById('searchBtn').addEventListener('click', function() {
        const searchTerm = document.getElementById('search').value.toLowerCase();
        const table = document.getElementById('appointmentsTable');
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
        
        for (let i = 0; i < rows.length; i++) {
            const patientName = rows[i].getElementsByTagName('td')[0].textContent.toLowerCase();
            if (patientName.includes(searchTerm)) {
                rows[i].style.display = '';
            } else {
                rows[i].style.display = 'none';
            }
        }
    });
    
                // Status Update Functionality
                document.querySelectorAll('.update-status').forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        const appointmentId = this.getAttribute('data-id');
                        const newStatus = this.getAttribute('data-status');
                        
                        if (confirm(`Are you sure you want to mark this appointment as ${newStatus}?`)) {
                            fetch('update-appointment-status.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `appointment_id=${appointmentId}&status=${newStatus}`
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert('Appointment status updated successfully!');
                                    location.reload();
                                } else {
                                    alert('Error updating appointment status: ' + data.message);
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('An error occurred while updating the appointment status.');
                            });
                        }
                    });
                });
            });
    </script>
</body>
</html>