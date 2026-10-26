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

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get treatment ID and notes from POST request
$treatment_id = isset($_POST['treatment_id']) ? $_POST['treatment_id'] : null;
$notes = isset($_POST['notes']) ? $_POST['notes'] : '';

// Validate treatment ID
if (!$treatment_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Treatment ID is required']);
    exit();
}

try {
    // Verify that the treatment belongs to the logged-in dentist
    $stmt = $conn->prepare("SELECT * FROM treatment WHERE treatment_id = :treatment_id AND dentist_id = :dentist_id");
    $stmt->bindParam(':treatment_id', $treatment_id);
    $stmt->bindParam(':dentist_id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Treatment not found or unauthorized']);
        exit();
    }
    
    // Update the treatment notes
    $stmt = $conn->prepare("UPDATE treatment SET notes = :notes, updated_at = NOW() WHERE treatment_id = :treatment_id");
    $stmt->bindParam(':notes', $notes);
    $stmt->bindParam(':treatment_id', $treatment_id);
    $result = $stmt->execute();
    
    if ($result) {
        // Add to audit trail
        $stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details, ip_address) 
                               VALUES (:user_id, 'UPDATE', 'treatment', :details, :ip_address)");
        $details = "Updated treatment notes for treatment ID: " . $treatment_id;
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':details', $details);
        $stmt->bindParam(':ip_address', $ip);
        $stmt->execute();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Treatment notes updated successfully']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to update treatment notes']);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>