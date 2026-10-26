<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    } else {
        header("Location: login.php");
    }
    exit();
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if notification ID is provided
    if (!isset($_POST['notification_id']) || empty($_POST['notification_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Notification ID is required']);
        exit();
    }

    $notification_id = $_POST['notification_id'];

    // Verify notification belongs to this user
    $stmt = $conn->prepare("SELECT * FROM notification WHERE notification_id = :notification_id AND user_id = :user_id");
    $stmt->bindParam(':notification_id', $notification_id);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();

    if ($stmt->rowCount() == 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Notification not found or not assigned to you']);
        exit();
    }

    try {
        // Update notification as read
        $stmt = $conn->prepare("UPDATE notification SET is_read = 1 WHERE notification_id = :notification_id");
        $stmt->bindParam(':notification_id', $notification_id);
        
        if ($stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read']);
        }
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
// Handle direct GET requests (e.g., from links in notifications list)
else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check if notification ID is provided
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        $_SESSION['error'] = "Invalid notification ID.";
        header("Location: my-notifications.php");
        exit();
    }

    $notification_id = $_GET['id'];
    $user_id = $_SESSION['user_id'];

    // Get current page for redirection
    $page = isset($_GET['page']) ? '&page=' . $_GET['page'] : '';
    $redirect_back = isset($_GET['redirect_back']) && $_GET['redirect_back'] == 1;

    // Verify notification belongs to the user
    $stmt = $conn->prepare("SELECT * FROM notification WHERE notification_id = :notification_id AND user_id = :user_id");
    $stmt->bindParam(':notification_id', $notification_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    if ($stmt->rowCount() == 0) {
        $_SESSION['error'] = "Notification not found or you don't have permission to view it.";
        header("Location: my-notifications.php$page");
        exit();
    }

    $notification = $stmt->fetch(PDO::FETCH_ASSOC);

    // Mark notification as read
    $stmt = $conn->prepare("UPDATE notification SET is_read = 1 WHERE notification_id = :notification_id");
    $stmt->bindParam(':notification_id', $notification_id);
    
    if ($stmt->execute()) {
        // For AJAX requests, return JSON response
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
            exit();
        }
        
        $_SESSION['success'] = "Notification marked as read.";
        
        // If redirect_back is true, just go back to the notifications page
        if ($redirect_back) {
            header("Location: my-notifications.php$page");
            exit();
        }
        
        // Determine redirect URL based on notification data
        $redirect_url = "my-notifications.php$page";
        
        // First check notification_type and related_id (newer format)
        if (!empty($notification['notification_type']) && !empty($notification['related_id'])) {
            switch ($notification['notification_type']) {
                case 'appointment':
                    $redirect_url = "appointment-details.php?id=" . $notification['related_id'];
                    break;
                case 'payment':
                    $redirect_url = "payment-receipt.php?id=" . $notification['related_id'];
                    break;
                case 'treatment':
                    $redirect_url = "treatment-detail.php?id=" . $notification['related_id'];
                    break;
            }
        }
        // Then check related_table and related_entity_id (older format)
        else if (!empty($notification['related_table']) && !empty($notification['related_entity_id'])) {
            switch ($notification['related_table']) {
                case 'appointment':
                    $redirect_url = "appointment-details.php?id=" . $notification['related_entity_id'];
                    break;
                case 'payment':
                    $redirect_url = "payment-success.php?id=" . $notification['related_entity_id'];
                    break;
                case 'treatment':
                    $redirect_url = "treatment-detail.php?id=" . $notification['related_entity_id'];
                    break;
            }
        }
        
        header("Location: $redirect_url");
        exit();
    } else {
        $_SESSION['error'] = "Failed to mark notification as read.";
        header("Location: my-notifications.php$page");
        exit();
    }
} else {
    // Invalid request method
    header("Location: patient-dashboard.php");
    exit();
}
?>