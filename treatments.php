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

// Process filters
$patient_id = isset($_GET['patient_id']) ? $_GET['patient_id'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filters
$query = "SELECT t.*, u.first_name, u.last_name, u.email, u.phone_number,
         a.appointment_date, a.service_id, s.service_name, t.notes
         FROM treatment t 
         JOIN user u ON t.patient_id = u.user_id 
         LEFT JOIN patient_info p ON u.user_id = p.user_id
         LEFT JOIN appointment a ON t.appointment_id = a.appointment_id
         LEFT JOIN service s ON a.service_id = s.service_id
         WHERE t.dentist_id = :dentist_id";

$params = [':dentist_id' => $_SESSION['user_id']];

if (!empty($patient_id)) {
    $query .= " AND t.patient_id = :patient_id";
    $params[':patient_id'] = $patient_id;
}

if (!empty($date_from)) {
    $query .= " AND DATE(t.treatment_date) >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(t.treatment_date) <= :date_to";
    $params[':date_to'] = $date_to;
}

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE :search 
                     OR u.last_name LIKE :search 
                     OR u.email LIKE :search
                     OR t.notes LIKE :search)";
    $searchParam = "%{$search}%";
    $params[':search'] = $searchParam;
}

$query .= " ORDER BY t.treatment_date DESC";

// Prepare and execute the statement
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$treatments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get patients list for filter dropdown
$stmt = $conn->prepare(
    "SELECT DISTINCT u.user_id, u.first_name, u.last_name 
     FROM user u
     JOIN treatment t ON u.user_id = t.patient_id
     WHERE t.dentist_id = :dentist_id
     ORDER BY u.last_name, u.first_name"
);
$stmt->bindParam(':dentist_id', $_SESSION['user_id']);
$stmt->execute();
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Treatments - JRC Dental Care</title>
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
                    <h3 class="mb-0">Treatment Records</h3>
                </div>
                
                <!-- Filters and Search -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="patient_id" class="form-label">Patient</label>
                                <select class="form-select" id="patient_id" name="patient_id">
                                    <option value="">All Patients</option>
                                    <?php foreach($patients as $patient): ?>
                                    <option value="<?= $patient['user_id'] ?>" <?= $patient_id == $patient['user_id'] ? 'selected' : '' ?>>
                                        <?= $patient['first_name'] . ' ' . $patient['last_name'] ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= $date_from ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= $date_to ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" placeholder="Search treatments" value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">Filter</button>
                                <a href="treatments.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Treatments Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">Treatment History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($treatments) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Patient</th>
                                        <th>Service</th>
                                        <th>Treatment Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($treatments as $treatment): ?>
                                    <tr>
                                        <td><?= date('M d, Y', strtotime($treatment['treatment_date'])) ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle-sm me-2 bg-primary text-white">
                                                    <?= substr($treatment['first_name'], 0, 1) . substr($treatment['last_name'], 0, 1) ?>
                                                </div>
                                                <?= $treatment['first_name'] . ' ' . $treatment['last_name'] ?>
                                            </div>
                                        </td>
                                        <td><?= $treatment['service_name'] ?? 'Not specified' ?></td>
                                        <td><?= $treatment['notes'] ?? 'No notes available' ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary me-2" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewTreatmentModal" 
                                                    data-treatment-id="<?= $treatment['treatment_id'] ?>"
                                                    data-treatment-date="<?= date('M d, Y', strtotime($treatment['treatment_date'])) ?>"
                                                    data-patient-name="<?= $treatment['first_name'] . ' ' . $treatment['last_name'] ?>"
                                                    data-service-name="<?= htmlspecialchars($treatment['service_name'] ?? 'Not specified') ?>"
                                                    data-treatment-notes="<?= htmlspecialchars($treatment['notes'] ?? '') ?>">
                                                View
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editTreatmentModal" 
                                                    data-treatment-id="<?= $treatment['treatment_id'] ?>"
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
                        <div class="text-center py-4">
                            <img src="assets/images/no-data.svg" alt="No Treatments" class="img-fluid mb-3" style="max-width: 150px;">
                            <h5>No treatments found</h5>
                            <p class="text-muted">Try changing your filters or add new treatment records</p>
                        </div>
                        <?php endif; ?>
                    </div>
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
                        <label class="form-label fw-bold">Patient</label>
                        <p id="viewPatientName"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Date</label>
                        <p id="viewTreatmentDate"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Service</label>
                        <p id="viewServiceName"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Notes</label>
                        <div id="viewTreatmentNotes" class="p-3 bg-light rounded">No notes available</div>
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
                const patientName = button.getAttribute('data-patient-name');
                const date = button.getAttribute('data-treatment-date');
                const serviceName = button.getAttribute('data-service-name');
                const notes = button.getAttribute('data-treatment-notes') || '';
                
                document.getElementById('viewPatientName').textContent = patientName;
                document.getElementById('viewTreatmentDate').textContent = date;
                document.getElementById('viewServiceName').textContent = serviceName;
                
                const notesElement = document.getElementById('viewTreatmentNotes');
                if (notes && notes.trim() !== '') {
                    notesElement.innerHTML = notes.replace(/\n/g, '<br>');
                } else {
                    notesElement.innerHTML = 'No notes available';
                }
            });
            
            // Handle Edit Treatment Modal
            const editTreatmentModal = document.getElementById('editTreatmentModal');
            editTreatmentModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const treatmentId = button.getAttribute('data-treatment-id');
                const notes = button.getAttribute('data-treatment-notes') || '';
                
                document.getElementById('editTreatmentId').value = treatmentId;
                document.getElementById('editTreatmentNotes').value = notes;
            });
            
            // Save Treatment button
            document.getElementById('saveTreatmentBtn').addEventListener('click', function() {
                const treatmentId = document.getElementById('editTreatmentId').value;
                const notes = document.getElementById('editTreatmentNotes').value;
                updateTreatment(treatmentId, notes);
            });
            
            // Function to update treatment
            function updateTreatment(treatmentId, notes) {
                fetch('update-treatment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `treatment_id=${treatmentId}&notes=${encodeURIComponent(notes)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Treatment notes updated successfully!');
                        location.reload();
                    } else {
                        alert('Error updating treatment notes: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating the treatment notes.');
                });
            }
        });
    </script>
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