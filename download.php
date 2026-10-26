<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Validate the file parameter
if (!isset($_POST['file']) || empty($_POST['file'])) {
    http_response_code(400);
    exit('Missing file parameter');
}

$file_path = $_POST['file'];

// Basic security check - only allow downloads from reports directory
if (!preg_match('/^reports\/[a-z0-9_]+\.(html|pdf)$/i', $file_path)) {
    http_response_code(403);
    exit('Invalid file path');
}

// Check if file exists
if (!file_exists($file_path)) {
    http_response_code(404);
    exit('File not found');
}

// Log the download
require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

try {
    $audit_stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details) VALUES (:user_id, 'DOWNLOAD', 'report', :details)");
    $audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $details = "Downloaded file: " . $file_path;
    $audit_stmt->bindParam(':details', $details);
    $audit_stmt->execute();
} catch (PDOException $e) {
    // Log the error but continue with download
    error_log("Error logging download: " . $e->getMessage());
}

// Get the filename and extension from the path
$filename = basename($file_path);
$file_extension = pathinfo($filename, PATHINFO_EXTENSION);

// Set headers based on file type
if ($file_extension == 'pdf') {
    header('Content-Type: application/pdf');
} else if ($file_extension == 'html') {
    header('Content-Type: text/html');
} else {
    header('Content-Type: application/octet-stream');
}

header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Clear output buffer
ob_clean();
flush();

// Read and output file
readfile($file_path);
exit;