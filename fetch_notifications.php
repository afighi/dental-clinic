<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

try {
    // Get notification count for navbar
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM notification WHERE is_read = 0");
    $count_stmt->execute();
    $notif_count = $count_stmt->fetchColumn();

    // Get latest notifications for navbar dropdown
    $stmt = $conn->prepare("SELECT notification_id, title, notification_message, created_at 
                            FROM notification 
                            WHERE is_read = 0 
                            ORDER BY created_at DESC 
                            LIMIT 5");
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unread notifications for the page
    $unread_stmt = $conn->prepare("SELECT n.*, u.username, u.first_name, u.last_name 
                                   FROM notification n 
                                   LEFT JOIN user u ON n.user_id = u.user_id 
                                   WHERE n.is_read = 0 
                                   ORDER BY n.created_at DESC 
                                   LIMIT 5");
    $unread_stmt->execute();
    $unread_notifications = $unread_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total unread count
    $total_unread_stmt = $conn->prepare("SELECT COUNT(*) FROM notification WHERE is_read = 0");
    $total_unread_stmt->execute();
    $total_unread = $total_unread_stmt->fetchColumn();

    // Prepare response
    $response = [
        'count' => $notif_count,
        'notifications' => $notifications,
        'unread_notifications' => $unread_notifications,
        'total_unread' => $total_unread
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}