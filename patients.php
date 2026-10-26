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

// Process search
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get all column names from patient_info table to identify the phone column
$phoneColumns = ['phone', 'phone_number', 'contact', 'contact_number', 'mobile', 'mobile_number', 'telephone'];
$foundPhoneColumn = null;

try {
    $stmt = $conn->prepare("DESCRIBE patient_info");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($phoneColumns as $possibleColumn) {
        if (in_array($possibleColumn, $columns)) {
            $foundPhoneColumn = $possibleColumn;
            break;
        }
    }
} catch (PDOException $e) {
    // Table might not exist, or other issue
}

// Build query based on search criteria
$query = "SELECT u.user_id, u.first_name, u.last_name, u.email, 
         (SELECT COUNT(*) FROM appointment a WHERE a.patient_id = u.user_id AND a.dentist_id = :dentist_id) as appointment_count,
         (SELECT MAX(a.appointment_date) FROM appointment a WHERE a.patient_id = u.user_id AND a.dentist_id = :dentist_id) as last_visit
         FROM user u 
         WHERE u.role_id = 3 
         AND EXISTS (SELECT 1 FROM appointment a WHERE a.patient_id = u.user_id AND a.dentist_id = :dentist_id)";

// Add LEFT JOIN to patient_info only if we found a valid phone column
$phoneJoin = '';
if ($foundPhoneColumn) {
    $phoneJoin = "LEFT JOIN patient_info p ON u.user_id = p.user_id";
    $query = str_replace('FROM user u', "FROM user u $phoneJoin", $query);
    // Add phone column to the SELECT
    $query = str_replace('u.email,', "u.email, p.$foundPhoneColumn as phone,", $query);
}

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search";
    // Only include phone in search if the column exists
    if ($foundPhoneColumn) {
        $query .= " OR p.$foundPhoneColumn LIKE :search";
    }
    $query .= ")";
}

$query .= " ORDER BY u.last_name, u.first_name";

// Prepare and execute the query
$stmt = $conn->prepare($query);
$stmt->bindParam(':dentist_id', $_SESSION['user_id']);

if (!empty($search)) {
    $searchParam = "%{$search}%";
    $stmt->bindParam(':search', $searchParam);
}

$stmt->execute();
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total counts
$totalPatients = count($patients);

// Get recently added patients - FIXED: Changed role_id from 4 to 3 to match patient role
$recentQuery = "SELECT u.user_id, u.first_name, u.last_name, u.email, 
               MIN(a.appointment_date) as first_appointment
               FROM user u 
               JOIN appointment a ON u.user_id = a.patient_id
               WHERE u.role_id = 3 AND a.dentist_id = :dentist_id
               GROUP BY u.user_id
               ORDER BY first_appointment DESC LIMIT 5";

// Add phone column to recent patients query if it exists
if ($foundPhoneColumn) {
    $recentQuery = str_replace('FROM user u', "FROM user u LEFT JOIN patient_info p ON u.user_id = p.user_id", $recentQuery);
    $recentQuery = str_replace('u.email,', "u.email, p.$foundPhoneColumn as phone,", $recentQuery);
}

$stmt = $conn->prepare($recentQuery);
$stmt->bindParam(':dentist_id', $_SESSION['user_id']);
$stmt->execute();
$recentPatients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients - JRC Dental Care</title>
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
                    <h3 class="mb-0">Patients</h3>
                </div>
                
                <!-- Stats Card -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="icon-container bg-primary bg-opacity-10 rounded-circle p-3 me-3">
                                        <i class="fas fa-users text-primary"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Total Patients</h6>
                                        <h3 class="mb-0"><?= $totalPatients ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Search and Filter Section -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-8">
                                <label for="search" class="form-label">Search Patients</label>
                                <input type="text" class="form-control" id="search" name="search" placeholder="Search by name, email, or phone" value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">Search</button>
                                <a href="patients.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Patients Table -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">Patient List</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($patients) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Appointments</th>
                                        <th>Last Visit</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($patients as $patient): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle-sm me-2 bg-primary text-white">
                                                    <?= substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1) ?>
                                                </div>
                                                <?= $patient['first_name'] . ' ' . $patient['last_name'] ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div><?= $patient['email'] ?></div>
                                            <?php if(isset($patient['phone'])): ?>
                                            <small class="text-muted"><?= $patient['phone'] ?: 'No phone' ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $patient['appointment_count'] ?></td>
                                        <td><?= $patient['last_visit'] ? date('M d, Y', strtotime($patient['last_visit'])) : 'Never' ?></td>
                                        <td>
                                            <!-- FIXED: Added target="_blank" to open in new tab and ensure complete page reload -->
                                            <a href="patient-details.php?id=<?= $patient['user_id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="fas fa-eye me-1"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <img src="assets/images/no-data.svg" alt="No Patients" class="img-fluid mb-3" style="max-width: 150px;">
                            <h5>No patients found</h5>
                            <p class="text-muted">
                                <?php if (!empty($search)): ?>
                                    No patients matching your search criteria
                                <?php else: ?>
                                    You don't have any patients assigned to you yet
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Patients -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">Recent Patients</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($recentPatients) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>First Appointment</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentPatients as $patient): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle-sm me-2 bg-primary text-white">
                                                    <?= substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1) ?>
                                                </div>
                                                <?= $patient['first_name'] . ' ' . $patient['last_name'] ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div><?= $patient['email'] ?></div>
                                            <?php if(isset($patient['phone'])): ?>
                                            <small class="text-muted"><?= $patient['phone'] ?: 'No phone' ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($patient['first_appointment'])) ?></td>
                                        <td>
                                            <!-- FIXED: Added target="_blank" to open in new tab and ensure complete page reload -->
                                            <a href="patient-details.php?id=<?= $patient['user_id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="fas fa-eye me-1"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-center my-4">No recent patients</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dentist-dashboard.js"></script>
    <style>
        .avatar-circle-sm {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 14px;
            font-weight: 600;
        }
    </style>
</body>
</html>