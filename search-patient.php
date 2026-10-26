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

// Check if search term is provided
if (!isset($_GET['term']) || empty($_GET['term'])) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

$searchTerm = '%' . $_GET['term'] . '%';

// Search for patients
$stmt = $conn->prepare("SELECT u.user_id, u.first_name, u.last_name, u.email, p.phone 
                        FROM user u 
                        LEFT JOIN patient_info p ON u.user_id = p.user_id 
                        WHERE u.role_id = 4 
                        AND (u.first_name LIKE :term 
                             OR u.last_name LIKE :term 
                             OR u.email LIKE :term 
                             OR p.phone LIKE :term)
                        LIMIT 10");
$stmt->bindParam(':term', $searchTerm);
$stmt->execute();
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return JSON response
header('Content-Type: application/json');
echo json_encode($patients);
?>