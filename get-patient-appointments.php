<?php
session_start();
// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Get patient appointments for calendar
$patient_id = $_SESSION['user_id'];
$events = [];

try {
    // Get all appointments for the patient
    $stmt = $conn->prepare("SELECT a.appointment_id, a.appointment_date, a.status, s.service_name, 
                           u.first_name, u.last_name
                           FROM appointment a 
                           JOIN service s ON a.service_id = s.service_id 
                           JOIN user u ON a.dentist_id = u.user_id
                           WHERE a.patient_id = :patient_id");
    $stmt->bindParam(':patient_id', $patient_id);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Set color based on status
        $color = '#1806a8'; // Default primary color
        
        switch ($row['status']) {
            case 'pending':
                $color = '#ffc107'; // Warning yellow
                break;
            case 'confirmed':
                $color = '#0dcaf0'; // Info blue
                break;
            case 'completed':
                $color = '#198754'; // Success green
                break;
            case 'canceled':
                $color = '#dc3545'; // Danger red
                break;
        }
        
        // Format event title
        $title = $row['service_name'] . ' with Dr. ' . $row['first_name'] . ' ' . $row['last_name'];
        
        // Get service duration to set end time
        $service_stmt = $conn->prepare("SELECT s.duration_minutes 
                                     FROM appointment a 
                                     JOIN service s ON a.service_id = s.service_id 
                                     WHERE a.appointment_id = :appointment_id");
        $service_stmt->bindParam(':appointment_id', $row['appointment_id']);
        $service_stmt->execute();
        $service = $service_stmt->fetch(PDO::FETCH_ASSOC);
        
        $start_date = new DateTime($row['appointment_date']);
        $end_date = clone $start_date;
        
        if (isset($service['duration_minutes'])) {
            $end_date->add(new DateInterval('PT' . $service['duration_minutes'] . 'M'));
        } else {
            // Default 30 minutes if no duration specified
            $end_date->add(new DateInterval('PT30M'));
        }
        
        // Add event to array
        $events[] = [
            'id' => $row['appointment_id'],
            'title' => $title,
            'start' => $start_date->format('Y-m-d\TH:i:s'),
            'end' => $end_date->format('Y-m-d\TH:i:s'),
            'backgroundColor' => $color,
            'borderColor' => $color,
            'extendedProps' => [
                'status' => $row['status']
            ]
        ];
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($events);
    
} catch(PDOException $e) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}