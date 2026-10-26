<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Get parameters
$dentist_id = isset($_GET['dentist_id']) ? intval($_GET['dentist_id']) : 0;
$date = isset($_GET['date']) ? $_GET['date'] : '';

// Validate inputs
if (empty($dentist_id) || empty($date)) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

// Define clinic working hours
$start_time = '09:00:00'; // 9 AM
$end_time = '17:00:00';   // 5 PM
$slot_duration = 30;      // 30 minutes per slot

// Create array of all possible time slots
$available_slots = [];
$current_time = strtotime($start_time);
$end_timestamp = strtotime($end_time);

while ($current_time < $end_timestamp) {
    $time_string = date('H:i:s', $current_time);
    $formatted_time = date('h:i A', $current_time);
    
    // Add slot to array
    $available_slots[] = [
        'time' => $time_string,
        'formatted_time' => $formatted_time
    ];
    
    // Move to next slot
    $current_time += $slot_duration * 60;
}

// Get booked appointments for this dentist on the selected date
// FIXED: Changed 'cancelled' to 'canceled' to match the actual status in the database
$stmt = $conn->prepare("SELECT appointment_date FROM appointment 
                        WHERE dentist_id = :dentist_id 
                        AND DATE(appointment_date) = :date 
                        AND status != 'canceled'");
$stmt->bindParam(':dentist_id', $dentist_id);
$stmt->bindParam(':date', $date);
$stmt->execute();
$booked_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Remove booked slots
foreach ($booked_appointments as $appointment) {
    $booked_time = date('H:i:s', strtotime($appointment['appointment_date']));
    
    // Find and remove the booked slot
    foreach ($available_slots as $key => $slot) {
        if ($slot['time'] === $booked_time) {
            unset($available_slots[$key]);
            break;
        }
    }
}

// If current date is today, remove past time slots
$today = date('Y-m-d');
if ($date === $today) {
    $current_hour = date('H:i:s');
    
    foreach ($available_slots as $key => $slot) {
        if ($slot['time'] <= $current_hour) {
            unset($available_slots[$key]);
        }
    }
}

// Re-index array
$available_slots = array_values($available_slots);

// Return available slots
echo json_encode(['available_slots' => $available_slots]);
exit();