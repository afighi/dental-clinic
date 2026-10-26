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

// Get current filter if any
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$valid_statuses = ['all', 'pending', 'confirmed', 'completed', 'canceled'];
if (!in_array($status_filter, $valid_statuses)) {
    $status_filter = 'all';
}

// Get all appointments for the patient with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build the query based on filter
$query = "SELECT a.*, s.service_name, s.price, u.first_name, u.last_name 
         FROM appointment a 
         JOIN service s ON a.service_id = s.service_id 
         JOIN user u ON a.dentist_id = u.user_id
         WHERE a.patient_id = :patient_id";

if ($status_filter != 'all') {
    $query .= " AND a.status = :status";
}

$query .= " ORDER BY a.appointment_date DESC LIMIT :limit OFFSET :offset";

$stmt = $conn->prepare($query);
$stmt->bindParam(':patient_id', $_SESSION['user_id']);
if ($status_filter != 'all') {
    $stmt->bindParam(':status', $status_filter);
}
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total appointments count for pagination
$count_query = "SELECT COUNT(*) FROM appointment WHERE patient_id = :patient_id";
if ($status_filter != 'all') {
    $count_query .= " AND status = :status";
}

$stmt = $conn->prepare($count_query);
$stmt->bindParam(':patient_id', $_SESSION['user_id']);
if ($status_filter != 'all') {
    $stmt->bindParam(':status', $status_filter);
}
$stmt->execute();
$total_appointments = $stmt->fetchColumn();
$total_pages = ceil($total_appointments / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - JRC Dental Care</title>
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
                    <h3>My Appointments</h3>
                    <a href="book-appointment.php" class="btn btn-primary"><i class="fas fa-calendar-plus me-2"></i>Book New Appointment</a>
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
                
                <!-- Filter Buttons -->
                <div class="mb-4">
                    <div class="btn-group" role="group" aria-label="Filter appointments">
                        <a href="?status=all" class="btn <?= $status_filter == 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
                        <a href="?status=pending" class="btn <?= $status_filter == 'pending' ? 'btn-primary' : 'btn-outline-primary' ?>">Pending</a>
                        <a href="?status=confirmed" class="btn <?= $status_filter == 'confirmed' ? 'btn-primary' : 'btn-outline-primary' ?>">Confirmed</a>
                        <a href="?status=completed" class="btn <?= $status_filter == 'completed' ? 'btn-primary' : 'btn-outline-primary' ?>">Completed</a>
                        <a href="?status=canceled" class="btn <?= $status_filter == 'canceled' ? 'btn-primary' : 'btn-outline-primary' ?>">Canceled</a>
                    </div>
                </div>
                
                <!-- Appointments Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <?php if (count($appointments) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Service</th>
                                            <th>Dentist</th>
                                            <th>Date & Time</th>
                                            <th>Status</th>
                                            <th>Payment</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($appointments as $appointment): ?>
                                            <tr>
                                                <td>#<?= $appointment['appointment_id'] ?></td>
                                                <td><?= $appointment['service_name'] ?></td>
                                                <td>Dr. <?= $appointment['first_name'] . ' ' . $appointment['last_name'] ?></td>
                                                <td><?= date('M d, Y h:i A', strtotime($appointment['appointment_date'])) ?></td>
                                                <td>
                                                    <?php 
                                                    $status_class = '';
                                                    switch ($appointment['status']) {
                                                        case 'pending':
                                                            $status_class = 'bg-warning text-dark';
                                                            break;
                                                        case 'confirmed':
                                                            $status_class = 'bg-info';
                                                            break;
                                                        case 'completed':
                                                            $status_class = 'bg-success';
                                                            break;
                                                        case 'canceled':
                                                            $status_class = 'bg-danger';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?= $status_class ?>">
                                                        <?= ucfirst($appointment['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    // Check payment status
                                                    $payment_stmt = $conn->prepare("SELECT status FROM payment WHERE appointment_id = :appointment_id");
                                                    $payment_stmt->bindParam(':appointment_id', $appointment['appointment_id']);
                                                    $payment_stmt->execute();
                                                    $payment = $payment_stmt->fetch(PDO::FETCH_ASSOC);
                                                    
                                                    if ($payment) {
                                                        $payment_class = '';
                                                        switch ($payment['status']) {
                                                            case 'succeeded':
                                                                $payment_class = 'bg-success';
                                                                $payment_text = 'Paid';
                                                                break;
                                                            case 'pending':
                                                                $payment_class = 'bg-warning text-dark';
                                                                $payment_text = 'Pending';
                                                                break;
                                                            case 'failed':
                                                                $payment_class = 'bg-danger';
                                                                $payment_text = 'Failed';
                                                                break;
                                                            default:
                                                                $payment_class = 'bg-secondary';
                                                                $payment_text = 'Unknown';
                                                        }
                                                        ?>
                                                        <span class="badge <?= $payment_class ?>">
                                                            <?= $payment_text ?>
                                                        </span>
                                                    <?php } else { ?>
                                                        <?php if ($appointment['status'] != 'canceled'): ?>
                                                            <span class="badge bg-danger">Unpaid</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">N/A</span>
                                                        <?php endif; ?>
                                                    <?php } ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="appointment-details.php?id=<?= $appointment['appointment_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if (($appointment['status'] == 'pending' || $appointment['status'] == 'confirmed') && strtotime($appointment['appointment_date']) > time()): ?>
                                                            <?php if (!$payment || $payment['status'] != 'succeeded'): ?>
                                                                <a href="make-payment.php?id=<?= $appointment['appointment_id'] ?>" class="btn btn-sm btn-outline-success">
                                                                    <i class="fas fa-credit-card"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal<?= $appointment['appointment_id'] ?>">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Cancel Modal for each appointment -->
                                                    <div class="modal fade" id="cancelModal<?= $appointment['appointment_id'] ?>" tabindex="-1" aria-labelledby="cancelModalLabel<?= $appointment['appointment_id'] ?>" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="cancelModalLabel<?= $appointment['appointment_id'] ?>">Cancel Appointment</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p>Are you sure you want to cancel this appointment for <?= $appointment['service_name'] ?> on <?= date('F j, Y \a\t g:i A', strtotime($appointment['appointment_date'])) ?>?</p>
                                                                    <p class="text-danger"><small>Note: Cancellation within 24 hours of the appointment may incur a fee.</small></p>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                    <form action="cancel-appointment.php" method="post">
                                                                        <input type="hidden" name="appointment_id" value="<?= $appointment['appointment_id'] ?>">
                                                                        <button type="submit" class="btn btn-danger">Yes, Cancel Appointment</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center mt-4">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?status=<?= $status_filter ?>&page=<?= $page - 1 ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                                <a class="page-link" href="?status=<?= $status_filter ?>&page=<?= $i ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?status=<?= $status_filter ?>&page=<?= $page + 1 ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="text-center my-5">
                                <img src="images/empty-calendar.svg" alt="No Appointments" class="img-fluid mb-3" style="max-width: 150px;">
                                <h5>No appointments found</h5>
                                <?php if ($status_filter != 'all'): ?>
                                    <p class="text-muted">No <?= $status_filter ?> appointments found. Try another filter or book a new appointment.</p>
                                    <a href="?status=all" class="btn btn-outline-primary mt-2">View All Appointments</a>
                                <?php else: ?>
                                    <p class="text-muted">You don't have any dental appointments yet.</p>
                                <?php endif; ?>
                                <a href="book-appointment.php" class="btn btn-primary mt-2">Book New Appointment</a>
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
        });
    </script>
</body>
</html>