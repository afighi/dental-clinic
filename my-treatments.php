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

// Get patient information
$stmt = $conn->prepare("SELECT * FROM user WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get treatments
$stmt = $conn->prepare("SELECT t.*, u.first_name, u.last_name, a.appointment_date, s.service_name 
                       FROM treatment t 
                       JOIN user u ON t.dentist_id = u.user_id 
                       LEFT JOIN appointment a ON t.appointment_id = a.appointment_id 
                       LEFT JOIN service s ON a.service_id = s.service_id 
                       WHERE t.patient_id = :patient_id 
                       ORDER BY t.treatment_date DESC");
$stmt->bindParam(':patient_id', $_SESSION['user_id']);
$stmt->execute();
$treatments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Treatment History - JRC Dental Care</title>
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
                    <h3>Treatment History</h3>
                </div>
                
                <!-- Treatment Summary -->
                <div class="row g-4 mb-4">
                    <div class="col-md-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Dental Health Timeline</h5>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> This timeline shows your dental treatments and procedures over time. Regular check-ups and proper dental care are essential for maintaining good oral health.
                                </div>
                                <div class="text-center">
                                    <span class="badge bg-primary rounded-pill mb-2">Total Treatments: <?= count($treatments) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Treatments List -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">Treatments & Procedures</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($treatments) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Treatment</th>
                                            <th>Dentist</th>
                                            <th>Service</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($treatments as $treatment): ?>
                                            <?php 
                                            // Extract first line of notes as treatment description
                                            $noteLines = explode("\n", $treatment['notes'] ?? '');
                                            $description = $noteLines[0] ?? 'Treatment';
                                            
                                            // Remove the first line from notes if it exists
                                            $remainingNotes = '';
                                            if (count($noteLines) > 1) {
                                                array_shift($noteLines);
                                                $remainingNotes = implode("\n", $noteLines);
                                            }
                                            ?>
                                            <tr>
                                                <td><?= date('M d, Y', strtotime($treatment['treatment_date'])) ?></td>
                                                <td><?= htmlspecialchars($description) ?></td>
                                                <td>Dr. <?= $treatment['first_name'] . ' ' . $treatment['last_name'] ?></td>
                                                <td><?= $treatment['service_name'] ?? 'N/A' ?></td>
                                                <td>
                                                    <?php if (!empty($remainingNotes)): ?>
                                                        <?= htmlspecialchars($remainingNotes) ?>
                                                    <?php elseif (empty($treatment['notes'])): ?>
                                                        No notes
                                                    <?php else: ?>
                                                        <!-- If notes only had one line, which is now the description -->
                                                        See treatment description
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-5">
                                <img src="assets/images/no-treatments.svg" alt="No Treatments" class="img-fluid mb-3" style="max-height: 150px;">
                                <h5>No treatment records found</h5>
                                <p class="text-muted">You don't have any dental treatment records yet.</p>
                                <a href="my-appointments.php" class="btn btn-primary mt-2">
                                    <i class="fas fa-calendar-plus me-2"></i> Book a Check-up
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Dental Health Tips -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">Dental Health Tips</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-tooth fa-3x text-primary mb-3"></i>
                                        <h6 class="card-title">Brush Twice Daily</h6>
                                        <p class="card-text small">Brush your teeth at least twice a day for 2 minutes with fluoride toothpaste.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-clock fa-3x text-primary mb-3"></i>
                                        <h6 class="card-title">Regular Check-ups</h6>
                                        <p class="card-text small">Visit your dentist every 6 months for regular check-ups and cleanings.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-utensils fa-3x text-primary mb-3"></i>
                                        <h6 class="card-title">Healthy Diet</h6>
                                        <p class="card-text small">Limit sugary foods and drinks to prevent tooth decay and gum disease.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/patient-dashboard.js"></script>
</body>
</html>