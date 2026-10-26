<?php
session_start();
require_once 'config/database.php';

// Skip authentication for testing
$database = new Database();
$conn = $database->getConnection();

if (isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    try {
        $stmt = $conn->prepare("SELECT * FROM user WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode($user);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing user ID']);
}
?>