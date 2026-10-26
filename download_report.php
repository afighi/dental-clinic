<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Validate file parameter
if (!isset($_GET['file']) || empty($_GET['file'])) {
    die("Error: No file specified");
}

$file_path = $_GET['file'];

// Security check - only allow files from reports directory
if (!preg_match('/^reports\/[\w\-\.]+\.pdf$/', $file_path)) {
    die("Error: Invalid file path");
}

// Check if file exists
if (!file_exists($file_path)) {
    die("Error: File not found");
}

// Get file information
$file_info = pathinfo($file_path);
$file_name = $file_info['basename'];

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output file content
readfile($file_path);
exit;