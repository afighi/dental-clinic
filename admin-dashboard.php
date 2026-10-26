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

// Get today's appointments count
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) as today_appointments FROM appointment WHERE DATE(appointment_date) = :today");
$stmt->bindParam(':today', $today);
$stmt->execute();
$appointment_count = $stmt->fetch(PDO::FETCH_ASSOC)['today_appointments'];

// Get recent payments
$stmt = $conn->prepare("SELECT p.*, a.appointment_date, u.first_name, u.last_name 
                       FROM payment p 
                       JOIN appointment a ON p.appointment_id = a.appointment_id 
                       JOIN user u ON a.patient_id = u.user_id 
                       ORDER BY p.created_at DESC LIMIT 5");
$stmt->execute();
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total users by role
$stmt = $conn->prepare("SELECT r.role_name, COUNT(u.user_id) as count 
                       FROM user u 
                       JOIN userrole r ON u.role_id = r.role_id 
                       GROUP BY r.role_name");
$stmt->execute();
$users_by_role = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user information
$stmt = $conn->prepare("SELECT * FROM user WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get pending appointments
$stmt = $conn->prepare("SELECT a.*, u.first_name, u.last_name, s.service_name 
                       FROM appointment a 
                       JOIN user u ON a.patient_id = u.user_id 
                       JOIN service s ON a.service_id = s.service_id 
                       WHERE a.status = 'pending' 
                       ORDER BY a.appointment_date ASC LIMIT 5");
$stmt->execute();
$pending_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent notifications
$stmt = $conn->prepare("SELECT * FROM notification WHERE is_read = 0 ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get service distribution
$stmt = $conn->prepare("SELECT s.service_name, COUNT(a.appointment_id) as count 
                       FROM service s 
                       LEFT JOIN appointment a ON s.service_id = a.service_id 
                       GROUP BY s.service_id 
                       ORDER BY count DESC LIMIT 5");
$stmt->execute();
$service_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get counts for patients and dentists
$stmt = $conn->prepare("SELECT COUNT(*) FROM user WHERE role_id = 4");
$stmt->execute();
$patients_count = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM user WHERE role_id = 2");
$stmt->execute();
$dentists_count = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM service");
$stmt->execute();
$services_count = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - JRC Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Dashboard Specific Styles */
        /* General Card Styles */
        .card {
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1) !important;
        }
        
        /* Stat Cards Specific Styling */
        .stat-card .card-body {
            padding: 1.25rem;
            height: auto;
            min-height: auto;
        }
        
        .icon-container {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .card-body h6 {
            font-size: 0.875rem;
            color: #495057;
        }
        
        .card-body h3 {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0;
        }
        
        /* Chart Card Specific Styling */
        .chart-card .card-body {
            min-height: 280px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* User Role Chart Specific Size */
        #userRoleChart {
            height: 200px !important;
            width: 85% !important;
            margin: 0 auto;
        }
        
        /* Service Distribution Chart Size */
        #serviceDistributionChart {
            height: 250px !important;
            width: 100% !important;
        }
        
        /* Responsive adjustments */
        @media (max-width: 767.98px) {
            .chart-card .card-body {
                min-height: 230px;
            }
            
            #userRoleChart {
                height: 180px !important;
            }
            
            #serviceDistributionChart {
                height: 200px !important;
            }
        }
        
        @media (max-width: 575.98px) {
            .chart-card .card-body {
                min-height: 200px;
            }
            
            #userRoleChart {
                height: 160px !important;
            }
            
            #serviceDistributionChart {
                height: 180px !important;
            }
            
            .icon-container {
                width: 40px;
                height: 40px;
            }
        }
    </style>
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
                <h3 class="mb-4">Admin Dashboard</h3>
                
                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <!-- Today's Appointments Card -->
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm stat-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="icon-container bg-primary bg-opacity-10 rounded-circle p-3 me-3">
                                        <i class="fas fa-calendar-check text-primary"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Today's Appointments</h6>
                                        <h3 class="mb-0"><?= $appointment_count ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Patients Card -->
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm stat-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="icon-container bg-success bg-opacity-10 rounded-circle p-3 me-3">
                                        <i class="fas fa-user text-success"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Total Patients</h6>
                                        <h3 class="mb-0"><?= $patients_count ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Dentists Card -->
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm stat-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="icon-container bg-danger bg-opacity-10 rounded-circle p-3 me-3">
                                        <i class="fas fa-user-md text-danger"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Total Dentists</h6>
                                        <h3 class="mb-0"><?= $dentists_count ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Services Offered Card -->
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm stat-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="icon-container bg-warning bg-opacity-10 rounded-circle p-3 me-3">
                                        <i class="fas fa-tooth text-warning"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Services Offered</h6>
                                        <h3 class="mb-0"><?= $services_count ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Content Row -->
                <div class="row g-4">
                    <!-- Recent Appointments -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Pending Appointments</h5>
                                    <a href="appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (count($pending_appointments) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Patient</th>
                                                    <th>Service</th>
                                                    <th>Date & Time</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pending_appointments as $appointment): ?>
                                                    <tr>
                                                        <td><?= $appointment['first_name'] . ' ' . $appointment['last_name'] ?></td>
                                                        <td><?= $appointment['service_name'] ?></td>
                                                        <td><?= date('M d, Y h:i A', strtotime($appointment['appointment_date'])) ?></td>
                                                        <td>
                                                            <span class="badge bg-warning text-dark">
                                                                <?= ucfirst($appointment['status']) ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center my-4">No pending appointments</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Payments -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Recent Payments</h5>
                                    <a href="payments.php" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (count($recent_payments) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Patient</th>
                                                    <th>Amount</th>
                                                    <th>Method</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_payments as $payment): ?>
                                                    <tr>
                                                        <td><?= $payment['first_name'] . ' ' . $payment['last_name'] ?></td>
                                                        <td>₱<?= number_format($payment['amount'] / 100, 2) ?></td>
                                                        <td><?= ucfirst($payment['payment_method']) ?></td>
                                                        <td>
                                                            <?php 
                                                            $status_class = '';
                                                            switch ($payment['status']) {
                                                                case 'succeeded':
                                                                    $status_class = 'bg-success';
                                                                    break;
                                                                case 'pending':
                                                                    $status_class = 'bg-warning text-dark';
                                                                    break;
                                                                case 'failed':
                                                                    $status_class = 'bg-danger';
                                                                    break;
                                                            }
                                                            ?>
                                                            <span class="badge <?= $status_class ?>">
                                                                <?= ucfirst($payment['status']) ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center my-4">No recent payments</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Second Content Row - Charts -->
                <div class="row g-4 mt-3">
                    <!-- User by Roles Chart - Smaller Size -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100 chart-card">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">Users by Role</h5>
                            </div>
                            <div class="card-body">
                                <div style="width: 85%; max-width: 280px;">
                                    <canvas id="userRoleChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Services Distribution Chart - Original Size -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100 chart-card">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">Popular Services</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="serviceDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity Section -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Recent Activity</h5>
                                    <a href="audittrail.php" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php
                                $stmt = $conn->prepare("
                                    SELECT a.*, u.username 
                                    FROM audittrail a 
                                    JOIN user u ON a.user_id = u.user_id 
                                    ORDER BY a.timestamp DESC 
                                    LIMIT 5
                                ");
                                $stmt->execute();
                                $audit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (count($audit_logs) > 0):
                                ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Action</th>
                                                <th>Table</th>
                                                <th>Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($audit_logs as $log): ?>
                                            <tr>
                                                <td><?= $log['username'] ?></td>
                                                <td><?= $log['action_performed'] ?></td>
                                                <td><?= $log['table_affected'] ?></td>
                                                <td><?= date('M d, Y h:i A', strtotime($log['timestamp'])) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center my-4">No recent activity</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script>
        // User Role Chart - Smaller Size
        const userRoleCtx = document.getElementById('userRoleChart').getContext('2d');
        const userRoleChart = new Chart(userRoleCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php echo implode(',', array_map(function($item) { return "'" . $item['role_name'] . "'"; }, $users_by_role)); ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_map(function($item) { return $item['count']; }, $users_by_role)); ?>],
                    backgroundColor: [
                        '#1806a8',
                        '#e1a2c8',
                        '#4361ee',
                        '#3a0ca3',
                        '#7209b7'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            padding: 10,
                            font: {
                                size: 11
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                layout: {
                    padding: {
                        top: 5,
                        right: 5,
                        bottom: 20,
                        left: 5
                    }
                }
            }
        });
        
        // Service Distribution Chart - Fixed Configuration
        const serviceDistCtx = document.getElementById('serviceDistributionChart').getContext('2d');
        const serviceDistChart = new Chart(serviceDistCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo implode(',', array_map(function($item) { return "'" . $item['service_name'] . "'"; }, $service_distribution)); ?>],
                datasets: [{
                    label: 'Number of Appointments',
                    data: [<?php echo implode(',', array_map(function($item) { return $item['count']; }, $service_distribution)); ?>],
                    backgroundColor: '#1806a8',
                    borderWidth: 0,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        // Properly set max value with some padding
                        suggestedMax: function() {
                            const data = [<?php echo implode(',', array_map(function($item) { return $item['count']; }, $service_distribution)); ?>];
                            const max = Math.max(...data);
                            return max + Math.ceil(max * 0.2); // Add 20% padding
                        }(),
                    },
                    x: {
                        ticks: {
                            autoSkip: true,
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                },
                // Set a fixed height for the chart container
                layout: {
                    padding: {
                        top: 10,
                        right: 10,
                        bottom: 30,
                        left: 10
                    }
                }
            }
        });
    </script>
</body>
</html>