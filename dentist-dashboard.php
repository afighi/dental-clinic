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

// Get dentist information
$stmt = $conn->prepare("SELECT * FROM user WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get today's appointments count
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) as today_appointments FROM appointment 
                        WHERE DATE(appointment_date) = :today 
                        AND dentist_id = :dentist_id");
$stmt->bindParam(':today', $today);
$stmt->bindParam(':dentist_id', $_SESSION['user_id']);
$stmt->execute();
$appointment_count = $stmt->fetch(PDO::FETCH_ASSOC)['today_appointments'];

// Get upcoming appointments
$stmt = $conn->prepare("SELECT a.*, u.first_name, u.last_name, s.service_name 
                       FROM appointment a 
                       JOIN user u ON a.patient_id = u.user_id 
                       JOIN service s ON a.service_id = s.service_id 
                       WHERE a.dentist_id = :dentist_id 
                       AND a.appointment_date >= NOW() 
                       ORDER BY a.appointment_date ASC LIMIT 5");
$stmt->bindParam(':dentist_id', $_SESSION['user_id']);
$stmt->execute();
$upcoming_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent treatments with service information
$stmt = $conn->prepare("SELECT t.*, u.first_name, u.last_name, s.service_name 
                       FROM treatment t 
                       JOIN user u ON t.patient_id = u.user_id 
                       JOIN service s ON t.service_id = s.service_id
                       WHERE t.dentist_id = :dentist_id 
                       ORDER BY t.treatment_date DESC LIMIT 5");
$stmt->bindParam(':dentist_id', $_SESSION['user_id']);
$stmt->execute();
$recent_treatments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get patient count
$stmt = $conn->prepare("SELECT COUNT(DISTINCT patient_id) as patient_count 
                        FROM appointment 
                        WHERE dentist_id = :dentist_id");
$stmt->bindParam(':dentist_id', $_SESSION['user_id']);
$stmt->execute();
$patient_count = $stmt->fetch(PDO::FETCH_ASSOC)['patient_count'];

// Get appointment status distribution
$stmt = $conn->prepare("SELECT status, COUNT(appointment_id) as count 
                        FROM appointment 
                        WHERE dentist_id = :dentist_id 
                        GROUP BY status");
$stmt->bindParam(':dentist_id', $_SESSION['user_id']);
$stmt->execute();
$appointment_status = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get service distribution
$stmt = $conn->prepare("SELECT s.service_name, COUNT(a.appointment_id) as count 
                       FROM service s 
                       JOIN appointment a ON s.service_id = a.service_id 
                       WHERE a.dentist_id = :dentist_id
                       GROUP BY s.service_id 
                       ORDER BY count DESC LIMIT 5");
$stmt->bindParam(':dentist_id', $_SESSION['user_id']);
$stmt->execute();
$service_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dentist Dashboard - JRC Dental Care</title>
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
        
        /* Calendar container */
        #calendar {
            width: 100%;
            height: 100%;
            min-height: 500px;
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
                <h3 class="mb-4">Dentist Dashboard</h3>
                
                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
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
                    
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm stat-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="icon-container bg-success bg-opacity-10 rounded-circle p-3 me-3">
                                        <i class="fas fa-user text-success"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Total Patients</h6>
                                        <h3 class="mb-0"><?= $patient_count ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php
                    // Get pending and completed appointment counts
                    $status_queries = [
                        ["SELECT COUNT(*) FROM appointment WHERE dentist_id = :dentist_id AND status = 'pending'", "Pending Appointments", "fa-clock", "warning"],
                        ["SELECT COUNT(*) FROM appointment WHERE dentist_id = :dentist_id AND status = 'completed'", "Completed Appointments", "fa-check-circle", "info"]
                    ];
                    
                    foreach ($status_queries as $query) {
                        $stmt = $conn->prepare($query[0]);
                        $stmt->bindParam(':dentist_id', $_SESSION['user_id']);
                        $stmt->execute();
                        $count = $stmt->fetchColumn();
                        ?>
                        <div class="col-md-3">
                            <div class="card border-0 shadow-sm stat-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="icon-container bg-<?= $query[3] ?> bg-opacity-10 rounded-circle p-3 me-3">
                                            <i class="fas <?= $query[2] ?> text-<?= $query[3] ?>"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold mb-1"><?= $query[1] ?></h6>
                                            <h3 class="mb-0"><?= $count ?></h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                
                <!-- Main Content Row -->
                <div class="row g-4">
                    <!-- Upcoming Appointments -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Upcoming Appointments</h5>
                                    <a href="appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (count($upcoming_appointments) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
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
                                                <?php foreach ($upcoming_appointments as $appointment): ?>
                                                    <tr>
                                                        <td><?= $appointment['first_name'] . ' ' . $appointment['last_name'] ?></td>
                                                        <td><?= $appointment['service_name'] ?></td>
                                                        <td><?= date('M d, Y h:i A', strtotime($appointment['appointment_date'])) ?></td>
                                                        <td>
                                                            <?php 
                                                            $status_class = '';
                                                            switch ($appointment['status']) {
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
                                                                case 'canceled': // Account for both spellings
                                                                    $status_class = 'badge-cancelled'; // Custom class
                                                                    break;
                                                            }
                                                            ?>
                                                            <span class="badge <?= $status_class ?>">
                                                                <?= ucfirst($appointment['status']) ?>
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
                                                                    <?php if ($appointment['status'] != 'cancelled' && $appointment['status'] != 'completed'): ?>
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
                                    <p class="text-center my-4">No upcoming appointments</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Treatments -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Recent Treatments</h5>
                                    <a href="treatments.php" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (count($recent_treatments) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Patient</th>
                                                    <th>Treatment</th>
                                                    <th>Date</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_treatments as $treatment): ?>
                                                    <tr>
                                                        <td><?= $treatment['first_name'] . ' ' . $treatment['last_name'] ?></td>
                                                        <td><?= $treatment['service_name'] ?? $treatment['notes'] ?? 'N/A' ?></td>
                                                        <td><?= date('M d, Y', strtotime($treatment['treatment_date'])) ?></td>
                                                        <td>
                                                            <a href="treatment-details.php?id=<?= $treatment['treatment_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                                View Details
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center my-4">No recent treatments</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Second Content Row -->
                <div class="row g-4 mt-3">
                    <!-- Calendar View -->
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">Schedule Overview</h5>
                            </div>
                            <div class="card-body">
                                <div id="calendar"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Appointment Status Chart -->
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm h-100 chart-card">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">Appointment Status</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="appointmentStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Third Content Row -->
                <div class="row g-4 mt-3">
                    <!-- Service Distribution Chart -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100 chart-card">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">Services Distribution</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="serviceDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="assets/js/dentist-dashboard.js"></script>
    <script>
        // Appointment Status Chart
        const statusLabels = [];
        const statusData = [];
        
        <?php foreach ($appointment_status as $status): ?>
            statusLabels.push('<?= ucfirst($status['status']) ?>');
            statusData.push(<?= $status['count'] ?>);
        <?php endforeach; ?>
        
        const appointmentStatusCtx = document.getElementById('appointmentStatusChart').getContext('2d');
        const appointmentStatusChart = new Chart(appointmentStatusCtx, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusData,
                    backgroundColor: [
                        '#FFC107', // pending - warning
                        '#0DCAF0', // confirmed - info
                        '#198754', // completed - success
                        '#DC3545'  // cancelled - danger
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
        
        // Initialize FullCalendar
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'timeGridWeek',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                height: 500,
                themeSystem: 'bootstrap5',
                events: 'get-dentist-appointments.php',
                eventClick: function(info) {
                    window.location.href = 'appointment-details.php?id=' + info.event.id;
                }
            });
            calendar.render();
        });
        
        // Patient Search Functionality
        const searchPatientBtn = document.getElementById('searchPatientBtn');
        if (searchPatientBtn) {
            searchPatientBtn.addEventListener('click', searchPatient);
        }
        
        const patientSearch = document.getElementById('patientSearch');
        if (patientSearch) {
            patientSearch.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchPatient();
                }
            });
        }
        
        function searchPatient() {
            const searchTerm = document.getElementById('patientSearch').value;
            if (searchTerm.length < 2) {
                alert('Please enter at least 2 characters to search');
                return;
            }
            
            const resultsContainer = document.getElementById('patientSearchResults');
            resultsContainer.innerHTML = '<p class="text-center">Searching...</p>';
            
            fetch('search-patient.php?term=' + encodeURIComponent(searchTerm))
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        resultsContainer.innerHTML = '<p class="text-center text-muted">No patients found matching your search</p>';
                        return;
                    }
                    
                    let html = '<div class="list-group">';
                    data.forEach(patient => {
                        html += `
                            <a href="patient-details.php?id=${patient.user_id}" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">${patient.first_name} ${patient.last_name}</h6>
                                    <small>${patient.email}</small>
                                </div>
                                <small>${patient.phone}</small>
                            </a>
                        `;
                    });
                    html += '</div>';
                    resultsContainer.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    resultsContainer.innerHTML = '<p class="text-center text-danger">Error occurred while searching. Please try again.</p>';
                });
        }
        
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
    </script>
</body>
</html>