<?php
session_start();
// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Get filters if provided
$table_filter = isset($_GET['table']) ? $_GET['table'] : '';
$user_filter = isset($_GET['user_id']) ? $_GET['user_id'] : '';
$action_filter = isset($_GET['action']) ? $_GET['action'] : '';
$date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';

// Build query based on filters
$query = "SELECT a.*, u.username, u.first_name, u.last_name 
          FROM audittrail a 
          JOIN user u ON a.user_id = u.user_id 
          WHERE 1=1";
$params = [];

if (!empty($table_filter)) {
    $query .= " AND a.table_affected = :table";
    $params[':table'] = $table_filter;
}

if (!empty($user_filter)) {
    $query .= " AND a.user_id = :user_id";
    $params[':user_id'] = $user_filter;
}

if (!empty($action_filter)) {
    $query .= " AND a.action_performed = :action";
    $params[':action'] = $action_filter;
}

if (!empty($date_start)) {
    $query .= " AND DATE(a.timestamp) >= :date_start";
    $params[':date_start'] = $date_start;
}

if (!empty($date_end)) {
    $query .= " AND DATE(a.timestamp) <= :date_end";
    $params[':date_end'] = $date_end;
}

// Add sorting
$query .= " ORDER BY a.timestamp DESC";

// Execute query
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$audit_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Log the export action
$audit_stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details, ip_address) VALUES (:user_id, 'EXPORT', 'audittrail', :details, :ip_address)");
$audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
$details = "Exported audit trail data";
$audit_stmt->bindParam(':details', $details);
$ip_address = $_SERVER['REMOTE_ADDR'];
$audit_stmt->bindParam(':ip_address', $ip_address);
$audit_stmt->execute();

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="audit_trail_export_' . date('Y-m-d_H-i-s') . '.csv"');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, [
    'Audit ID', 
    'User ID', 
    'Username',
    'Full Name',
    'Action Performed', 
    'Table Affected', 
    'Timestamp', 
    'IP Address', 
    'Details'
]);

// Output each row of the data
foreach ($audit_records as $row) {
    fputcsv($output, [
        $row['audit_id'],
        $row['user_id'],
        $row['username'],
        $row['first_name'] . ' ' . $row['last_name'],
        $row['action_performed'],
        $row['table_affected'],
        $row['timestamp'],
        $row['ip_address'] ? $row['ip_address'] : 'N/A',
        $row['details']
    ]);
}

// Close the file pointer
fclose($output);
exit;
?>