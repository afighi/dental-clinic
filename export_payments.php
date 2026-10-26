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
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filters
$query = "SELECT p.*, a.appointment_date, u1.first_name as patient_first_name, u1.last_name as patient_last_name, 
                 u2.first_name as dentist_first_name, u2.last_name as dentist_last_name, s.service_name
          FROM payment p 
          JOIN appointment a ON p.appointment_id = a.appointment_id 
          JOIN user u1 ON a.patient_id = u1.user_id 
          JOIN user u2 ON a.dentist_id = u2.user_id
          JOIN service s ON a.service_id = s.service_id 
          WHERE 1=1";
$params = [];

if (!empty($status_filter)) {
    $query .= " AND p.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($payment_method)) {
    $query .= " AND p.payment_method = :payment_method";
    $params[':payment_method'] = $payment_method;
}

if (!empty($date_start)) {
    $query .= " AND DATE(p.created_at) >= :date_start";
    $params[':date_start'] = $date_start;
}

if (!empty($date_end)) {
    $query .= " AND DATE(p.created_at) <= :date_end";
    $params[':date_end'] = $date_end;
}

if (!empty($search)) {
    $query .= " AND (u1.first_name LIKE :search OR u1.last_name LIKE :search OR p.paymongo_payment_id LIKE :search OR p.paymongo_payment_intent_id LIKE :search)";
    $search_param = "%$search%";
    $params[':search'] = $search_param;
}

// Add sorting
$query .= " ORDER BY p.created_at DESC";

// Execute query
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Log the export action
$audit_stmt = $conn->prepare("INSERT INTO audittrail (user_id, action_performed, table_affected, details, ip_address) VALUES (:user_id, 'EXPORT', 'payment', :details, :ip_address)");
$audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
$details = "Exported payment data";
$audit_stmt->bindParam(':details', $details);
$ip_address = $_SERVER['REMOTE_ADDR'];
$audit_stmt->bindParam(':ip_address', $ip_address);
$audit_stmt->execute();

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="payments_export_' . date('Y-m-d_H-i-s') . '.csv"');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, [
    'Payment ID', 
    'Appointment ID',
    'Patient Name',
    'Dentist Name',
    'Service',
    'Amount (PHP)', 
    'Payment Method',
    'Status',
    'PayMongo Payment Intent ID',
    'PayMongo Payment ID',
    'Appointment Date',
    'Created At',
    'Updated At'
]);

// Output each row of the data
foreach ($payments as $row) {
    fputcsv($output, [
        $row['payment_id'],
        $row['appointment_id'],
        $row['patient_first_name'] . ' ' . $row['patient_last_name'],
        $row['dentist_first_name'] . ' ' . $row['dentist_last_name'],
        $row['service_name'],
        number_format($row['amount'] / 100, 2),
        ucfirst($row['payment_method']),
        ucfirst($row['status']),
        $row['paymongo_payment_intent_id'],
        $row['paymongo_payment_id'] ? $row['paymongo_payment_id'] : 'N/A',
        date('Y-m-d H:i:s', strtotime($row['appointment_date'])),
        date('Y-m-d H:i:s', strtotime($row['created_at'])),
        date('Y-m-d H:i:s', strtotime($row['updated_at']))
    ]);
}

// Close the file pointer
fclose($output);
exit;
?>