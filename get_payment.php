<?php
session_start();
// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

if (isset($_GET['id'])) {
    $payment_id = intval($_GET['id']);
    
    try {
        $stmt = $conn->prepare("
            SELECT p.*, a.appointment_date, a.patient_id, a.dentist_id, s.service_name,
                   u1.first_name as patient_first_name, u1.last_name as patient_last_name,
                   u2.first_name as dentist_first_name, u2.last_name as dentist_last_name
            FROM payment p
            JOIN appointment a ON p.appointment_id = a.appointment_id
            JOIN service s ON a.service_id = s.service_id
            JOIN user u1 ON a.patient_id = u1.user_id
            JOIN user u2 ON a.dentist_id = u2.user_id
            WHERE p.payment_id = :payment_id
        ");
        $stmt->bindParam(':payment_id', $payment_id);
        $stmt->execute();
        
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($payment) {
            // Add patient and dentist full names
            $payment['patient_name'] = $payment['patient_first_name'] . ' ' . $payment['patient_last_name'];
            $payment['dentist_name'] = $payment['dentist_first_name'] . ' ' . $payment['dentist_last_name'];
            
            // Remove unnecessary fields
            unset($payment['patient_first_name']);
            unset($payment['patient_last_name']);
            unset($payment['dentist_first_name']);
            unset($payment['dentist_last_name']);
            
            // Log to audit trail
            $audit_stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details, ip_address) VALUES (:user_id, 'VIEW', 'payment', :details, :ip_address)");
            $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $details = "Viewed payment details for payment ID: " . $payment_id;
            $audit_stmt->bindParam(':details', $details);
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $audit_stmt->bindParam(':ip_address', $ip_address);
            $audit_stmt->execute();
            
            // Return payment data as JSON
            header('Content-Type: application/json');
            echo json_encode($payment);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Payment not found']);
        }
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing payment ID']);
}
?>