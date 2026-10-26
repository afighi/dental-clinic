<?php
session_start();
// Check if user is logged in and is a dentist
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Check if required parameters are set
if (!isset($_POST['patient_id']) || !isset($_POST['description'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$patientId = $_POST['patient_id'];
$description = $_POST['description'];
$notes = isset($_POST['notes']) ? $_POST['notes'] : '';
$appointmentId = isset($_POST['appointment_id']) && !empty($_POST['appointment_id']) ? $_POST['appointment_id'] : null;

// Verify patient exists and is a patient (role_id = 3 instead of 4)
$stmt = $conn->prepare("SELECT * FROM user WHERE user_id = :patient_id AND role_id = 3");
$stmt->bindParam(':patient_id', $patientId);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit();
}

// Verify appointment if provided
if ($appointmentId !== null) {
    $stmt = $conn->prepare("SELECT * FROM appointment WHERE appointment_id = :appointment_id AND patient_id = :patient_id AND dentist_id = :dentist_id");
    $stmt->bindParam(':appointment_id', $appointmentId);
    $stmt->bindParam(':patient_id', $patientId);
    $stmt->bindParam(':dentist_id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Appointment not found or not assigned to you']);
        exit();
    }
}

try {
    // Begin transaction
    $conn->beginTransaction();
    
    // Get a service_id for the treatment (required by the schema)
    $serviceId = null;
    
    // First try to get service_id from appointment if provided
    if ($appointmentId !== null) {
        $serviceStmt = $conn->prepare("SELECT service_id FROM appointment WHERE appointment_id = :appointment_id");
        $serviceStmt->bindParam(':appointment_id', $appointmentId);
        $serviceStmt->execute();
        if ($serviceRow = $serviceStmt->fetch(PDO::FETCH_ASSOC)) {
            $serviceId = $serviceRow['service_id'];
        }
    }
    
    // If we still don't have a service_id, get the first available service
    if ($serviceId === null) {
        $serviceStmt = $conn->prepare("SELECT service_id FROM service ORDER BY service_id LIMIT 1");
        $serviceStmt->execute();
        if ($serviceRow = $serviceStmt->fetch(PDO::FETCH_ASSOC)) {
            $serviceId = $serviceRow['service_id'];
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No service available for treatment']);
            exit();
        }
    }
    
    // Based on your database schema, the correct SQL should use notes for the description
    $stmt = $conn->prepare("INSERT INTO treatment 
                           (patient_id, dentist_id, service_id, appointment_id, treatment_date, notes, status, cost) 
                           VALUES (:patient_id, :dentist_id, :service_id, :appointment_id, CURDATE(), :description_and_notes, 'completed', 0)");
    
    // Combine description and notes since there's only one notes field
    $descriptionAndNotes = $description;
    if (!empty($notes)) {
        $descriptionAndNotes .= "\n\n" . $notes;
    }
    
    $stmt->bindParam(':patient_id', $patientId);
    $stmt->bindParam(':dentist_id', $_SESSION['user_id']);
    $stmt->bindParam(':service_id', $serviceId);
    $stmt->bindParam(':appointment_id', $appointmentId);
    $stmt->bindParam(':description_and_notes', $descriptionAndNotes);
    $stmt->execute();
    
    $treatmentId = $conn->lastInsertId();
    
    // Add audit trail entry
    $action = "Added treatment record #" . $treatmentId . " for patient #" . $patientId;
    $stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details) 
                           VALUES (:user_id, :action, 'treatment', :details)");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':action', $action);
    $stmt->bindParam(':details', $description);
    $stmt->execute();
    
    // If connected to an appointment, update appointment status to completed if it's not already
    if ($appointmentId !== null) {
        $stmt = $conn->prepare("UPDATE appointment SET status = 'completed', updated_at = NOW() WHERE appointment_id = :appointment_id AND status != 'completed'");
        $stmt->bindParam(':appointment_id', $appointmentId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Add audit trail entry for appointment update
            $action = "Updated appointment #" . $appointmentId . " status to completed";
            $stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details) 
                                  VALUES (:user_id, :action, 'appointment', :details)");
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':action', $action);
            $details = "Completed appointment related to treatment #" . $treatmentId;
            $stmt->bindParam(':details', $details);
            $stmt->execute();
            
            // Create notification for patient
            $message = "Your appointment has been completed and treatment notes have been added.";
            $stmt = $conn->prepare("INSERT INTO notification (user_id, notification_type, notification_message, related_entity_id, related_table, is_read, created_at) 
                                   VALUES (:user_id, 'treatment', :message, :treatment_id, 'treatment', 0, NOW())");
            $stmt->bindParam(':user_id', $patientId);
            $stmt->bindParam(':message', $message);
            $stmt->bindParam(':treatment_id', $treatmentId);
            $stmt->execute();
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Treatment notes added successfully', 'treatment_id' => $treatmentId]);
    
} catch (PDOException $e) {
    // Roll back transaction on error
    $conn->rollBack();
    
    // Log the error
    error_log("Database error in add-treatment.php: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>