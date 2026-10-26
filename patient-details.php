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

// Check if patient ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dentist-dashboard.php");
    exit();
}

$patientId = $_GET['id'];

// Get patient information
$stmt = $conn->prepare("SELECT u.*, p.* 
                        FROM user u 
                        LEFT JOIN patient_info p ON u.user_id = p.user_id 
                        WHERE u.user_id = :patient_id AND u.role_id = 3");
$stmt->bindParam(':patient_id', $patientId);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    header("Location: dentist-dashboard.php");
    exit();
}

$patient = $stmt->fetch(PDO::FETCH_ASSOC);

// Get patient appointment history
$stmt = $conn->prepare("SELECT a.*, s.service_name 
                        FROM appointment a 
                        JOIN service s ON a.service_id = s.service_id 
                        WHERE a.patient_id = :patient_id 
                        AND a.dentist_id = :dentist_id 
                        ORDER BY a.appointment_date DESC");
$stmt->bindParam(':patient_id', $patientId);
$stmt->bindParam(':dentist_id', $_SESSION['user_id']);
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get patient treatment history
$stmt = $conn->prepare("SELECT * FROM treatment 
                        WHERE patient_id = :patient_id 
                        AND dentist_id = :dentist_id 
                        ORDER BY treatment_date DESC");
$stmt->bindParam(':patient_id', $patientId);
$stmt->bindParam(':dentist_id', $_SESSION['user_id']);
$stmt->execute();
$treatments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details - JRC Dental Care</title>
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
                    <h3>Patient Details</h3>
                    <a href="javascript:history.back()" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                </div>
                
                <!-- Patient Information Card -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">Personal Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Name:</strong> <?= $patient['first_name'] . ' ' . $patient['last_name'] ?></p>
                                        <p><strong>Email:</strong> <?= $patient['email'] ?></p>
                                        <p><strong>Phone:</strong> <?= $patient['phone'] ?? 'Not provided' ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Gender:</strong> <?= $patient['gender'] ?? 'Not provided' ?></p>
                                        <p><strong>Date of Birth:</strong> <?= $patient['date_of_birth'] ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'Not provided' ?></p>
                                        <p><strong>Address:</strong> <?= $patient['address'] ?? 'Not provided' ?></p>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <h6 class="mb-2">Medical History</h6>
                                        <p><?= $patient['medical_history'] ?? 'No medical history recorded.' ?></p>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <h6 class="mb-2">Allergies</h6>
                                        <p><?= $patient['allergies'] ?? 'No allergies recorded.' ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs for Appointments and Treatments -->
                <ul class="nav nav-tabs" id="patientTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="appointments-tab" data-bs-toggle="tab" data-bs-target="#appointments" type="button" role="tab">
                            Appointment History
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="treatments-tab" data-bs-toggle="tab" data-bs-target="#treatments" type="button" role="tab">
                            Treatment History
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content pt-3" id="patientTabContent">
                    <!-- Appointments Tab -->
                    <div class="tab-pane fade show active" id="appointments" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Appointment History</h5>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (count($appointments) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date & Time</th>
                                                    <th>Service</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($appointments as $appointment): ?>
                                                    <tr>
                                                        <td><?= date('M d, Y h:i A', strtotime($appointment['appointment_date'])) ?></td>
                                                        <td><?= $appointment['service_name'] ?></td>
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
                                                                case 'cancelled':
                                                                    $status_class = 'bg-danger';
                                                                    break;
                                                            }
                                                            ?>
                                                            <span class="badge <?= $status_class ?>">
                                                                <?= ucfirst($appointment['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <a href="appointment-details.php?id=<?= $appointment['appointment_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                                View Details
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center my-4">No appointment history found for this patient.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Treatments Tab -->
                    <div class="tab-pane fade" id="treatments" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Treatment History</h5>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTreatmentModal">
                                        <i class="fas fa-plus me-2"></i>Add Treatment
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (count($treatments) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Description</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($treatments as $treatment): ?>
                                                    <tr>
                                                        <td><?= date('M d, Y', strtotime($treatment['treatment_date'])) ?></td>
                                                        <td>
                                                            <?php 
                                                            // Extract first line of notes as description
                                                            $noteLines = explode("\n", $treatment['notes'] ?? '');
                                                            echo htmlspecialchars($noteLines[0] ?? 'Treatment');
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-outline-primary me-2" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#viewTreatmentModal" 
                                                                    data-treatment-id="<?= $treatment['treatment_id'] ?>"
                                                                    data-treatment-date="<?= date('M d, Y', strtotime($treatment['treatment_date'])) ?>"
                                                                    data-treatment-desc="<?= htmlspecialchars($noteLines[0] ?? 'Treatment') ?>"
                                                                    data-treatment-notes="<?= htmlspecialchars($treatment['notes'] ?? '') ?>">
                                                                View Details
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#editTreatmentModal" 
                                                                    data-treatment-id="<?= $treatment['treatment_id'] ?>"
                                                                    data-treatment-desc="<?= htmlspecialchars($noteLines[0] ?? 'Treatment') ?>"
                                                                    data-treatment-notes="<?= htmlspecialchars($treatment['notes'] ?? '') ?>">
                                                                Edit
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center my-4">No treatment history found for this patient.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Treatment Modal -->
    <div class="modal fade" id="addTreatmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Treatment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addTreatmentForm">
                        <div class="mb-3">
                            <label for="treatmentDescription" class="form-label">Treatment Description</label>
                            <input type="text" class="form-control" id="treatmentDescription" required>
                        </div>
                        <div class="mb-3">
                            <label for="treatmentNotes" class="form-label">Treatment Notes</label>
                            <textarea class="form-control" id="treatmentNotes" rows="5"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="addTreatmentNote(<?= $patientId ?>, null)">Save Treatment</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View Treatment Modal -->
    <div class="modal fade" id="viewTreatmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Treatment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Date</label>
                        <p id="viewTreatmentDate"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Description</label>
                        <p id="viewTreatmentDesc"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Notes</label>
                        <div id="viewTreatmentNotes" class="p-3 bg-light rounded"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Treatment Modal -->
    <div class="modal fade" id="editTreatmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Treatment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editTreatmentForm">
                        <input type="hidden" id="editTreatmentId">
                        <div class="mb-3">
                            <label for="editTreatmentDescription" class="form-label">Treatment Description</label>
                            <input type="text" class="form-control" id="editTreatmentDescription" required>
                        </div>
                        <div class="mb-3">
                            <label for="editTreatmentNotes" class="form-label">Treatment Notes</label>
                            <textarea class="form-control" id="editTreatmentNotes" rows="5"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveTreatmentBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dentist-dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle View Treatment Modal
            const viewTreatmentModal = document.getElementById('viewTreatmentModal');
            viewTreatmentModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const date = button.getAttribute('data-treatment-date');
                const description = button.getAttribute('data-treatment-desc');
                const notes = button.getAttribute('data-treatment-notes');
                
                document.getElementById('viewTreatmentDate').textContent = date;
                document.getElementById('viewTreatmentDesc').textContent = description;
                document.getElementById('viewTreatmentNotes').innerHTML = notes.replace(/\n/g, '<br>');
            });
            
            // Handle Edit Treatment Modal
            const editTreatmentModal = document.getElementById('editTreatmentModal');
            editTreatmentModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const treatmentId = button.getAttribute('data-treatment-id');
                const description = button.getAttribute('data-treatment-desc');
                const notes = button.getAttribute('data-treatment-notes');
                
                document.getElementById('editTreatmentId').value = treatmentId;
                document.getElementById('editTreatmentDescription').value = description;
                document.getElementById('editTreatmentNotes').value = notes;
            });
            
            // Save Treatment button
            document.getElementById('saveTreatmentBtn').addEventListener('click', function() {
                const treatmentId = document.getElementById('editTreatmentId').value;
                updateTreatmentNote(treatmentId);
            });
        });
    </script>