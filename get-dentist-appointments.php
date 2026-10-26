<?php
session_start();
// Check if user is logged in and is a dentist
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Get dentist appointments for calendar
$stmt = $conn->prepare("SELECT a.appointment_id, a.appointment_date, a.status, 
                        a.google_event_id, u.first_name, u.last_name, s.service_name,
                        s.duration_minutes
                        FROM appointment a 
                        JOIN user u ON a.patient_id = u.user_id 
                        JOIN service s ON a.service_id = s.service_id 
                        WHERE a.dentist_id = :dentist_id");
$stmt->bindParam(':dentist_id', $_SESSION['user_id']);
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format appointments for FullCalendar
$events = [];
foreach ($appointments as $appointment) {
    // Calculate end time based on start time and duration
    $start = new DateTime($appointment['appointment_date']);
    $end = clone $start;
    $end->add(new DateInterval('PT' . $appointment['duration_minutes'] . 'M'));
    
    // Set color based on status
    $color = '';
    switch ($appointment['status']) {
        case 'pending':
            $color = '#FFC107'; // warning - yellow
            break;
        case 'confirmed':
            $color = '#0DCAF0'; // info - blue
            break;
        case 'completed':
            $color = '#198754'; // success - green
            break;
        case 'cancelled':
            $color = '#DC3545'; // danger - red
            break;
        default:
            $color = '#6C757D'; // secondary - gray
    }
    
    $events[] = [
        'id' => $appointment['appointment_id'],
        'title' => $appointment['first_name'] . ' ' . $appointment['last_name'] . ' - ' . $appointment['service_name'],
        'start' => $start->format('Y-m-d\TH:i:s'),
        'end' => $end->format('Y-m-d\TH:i:s'),
        'backgroundColor' => $color,
        'borderColor' => $color,
        'extendedProps' => [
            'status' => $appointment['status'],
            'googleEventId' => $appointment['google_event_id']
        ]
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($events);
?>