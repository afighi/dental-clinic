<?php
session_start();
include_once '../config/database.php';

// Check if user is logged in with admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Connect to database
$database = new Database();
$db = $database->getConnection();

// Get the period parameter
$period = isset($_GET['period']) ? $_GET['period'] : 'weekly';

try {
    $labels = [];
    $appointments = [];
    $patients = [];
    
    // Define date ranges and SQL based on period
    switch ($period) {
        case 'weekly':
            // Last 7 days, day by day
            $labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            
            // Get appointments per day of the week
            $app_query = "SELECT DAYOFWEEK(appointment_date) as day_num, COUNT(*) as count 
                         FROM appointment 
                         WHERE appointment_date BETWEEN DATE_SUB(NOW(), INTERVAL 1 WEEK) AND NOW() 
                         GROUP BY DAYOFWEEK(appointment_date)
                         ORDER BY day_num";
            
            $app_stmt = $db->prepare($app_query);
            $app_stmt->execute();
            $app_data = $app_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Initialize appointments array with zeros
            $appointments = [0, 0, 0, 0, 0, 0, 0];
            
            // Fill in actual data (adjust for 1-based day of week where 1 is Sunday)
            foreach ($app_data as $row) {
                $index = ($row['day_num'] + 5) % 7; // Adjust to make Monday index 0
                $appointments[$index] = $row['count'];
            }
            
            // Get new patient registrations per day of the week
            $pat_query = "SELECT DAYOFWEEK(created_at) as day_num, COUNT(*) as count 
                         FROM user 
                         WHERE role_id = 5 AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 1 WEEK) AND NOW() 
                         GROUP BY DAYOFWEEK(created_at)
                         ORDER BY day_num";
            
            $pat_stmt = $db->prepare($pat_query);
            $pat_stmt->execute();
            $pat_data = $pat_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Initialize patients array with zeros
            $patients = [0, 0, 0, 0, 0, 0, 0];
            
            // Fill in actual data (adjust for 1-based day of week where 1 is Sunday)
            foreach ($pat_data as $row) {
                $index = ($row['day_num'] + 5) % 7; // Adjust to make Monday index 0
                $patients[$index] = $row['count'];
            }
            
            break;
            
        case 'monthly':
            // Last 4 weeks, week by week
            $labels = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
            
            // Get appointments per week
            $app_query = "SELECT WEEK(appointment_date) - WEEK(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) as week_num, 
                                 COUNT(*) as count 
                          FROM appointment 
                          WHERE appointment_date BETWEEN DATE_SUB(NOW(), INTERVAL 1 MONTH) AND NOW() 
                          GROUP BY WEEK(appointment_date)
                          ORDER BY week_num";
            
            $app_stmt = $db->prepare($app_query);
            $app_stmt->execute();
            $app_data = $app_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Initialize appointments array with zeros
            $appointments = [0, 0, 0, 0];
            
            // Fill in actual data (limited to 4 weeks)
            foreach ($app_data as $row) {
                $index = min($row['week_num'], 3); // Limit to 0-3 index
                $appointments[$index] += $row['count'];
            }
            
            // Get new patient registrations per week
            $pat_query = "SELECT WEEK(created_at) - WEEK(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) as week_num, 
                                 COUNT(*) as count 
                          FROM user 
                          WHERE role_id = 5 AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 1 MONTH) AND NOW() 
                          GROUP BY WEEK(created_at)
                          ORDER BY week_num";
            
            $pat_stmt = $db->prepare($pat_query);
            $pat_stmt->execute();
            $pat_data = $pat_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Initialize patients array with zeros
            $patients = [0, 0, 0, 0];
            
            // Fill in actual data (limited to 4 weeks)
            foreach ($pat_data as $row) {
                $index = min($row['week_num'], 3); // Limit to 0-3 index
                $patients[$index] += $row['count'];
            }
            
            break;
            
        case 'yearly':
            // Last 12 months, month by month
            $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            
            // Get appointments per month
            $app_query = "SELECT MONTH(appointment_date) as month_num, COUNT(*) as count 
                         FROM appointment 
                         WHERE appointment_date BETWEEN DATE_SUB(NOW(), INTERVAL 1 YEAR) AND NOW() 
                         GROUP BY MONTH(appointment_date)
                         ORDER BY month_num";
            
            $app_stmt = $db->prepare($app_query);
            $app_stmt->execute();
            $app_data = $app_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Initialize appointments array with zeros
            $appointments = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
            
            // Fill in actual data
            foreach ($app_data as $row) {
                $index = $row['month_num'] - 1; // Convert 1-based to 0-based index
                $appointments[$index] = $row['count'];
            }
            
            // Get new patient registrations per month
            $pat_query = "SELECT MONTH(created_at) as month_num, COUNT(*) as count 
                         FROM user 
                         WHERE role_id = 5 AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 1 YEAR) AND NOW() 
                         GROUP BY MONTH(created_at)
                         ORDER BY month_num";
            
            $pat_stmt = $db->prepare($pat_query);
            $pat_stmt->execute();
            $pat_data = $pat_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Initialize patients array with zeros
            $patients = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
            
            // Fill in actual data
            foreach ($pat_data as $row) {
                $index = $row['month_num'] - 1; // Convert 1-based to 0-based index
                $patients[$index] = $row['count'];
            }
            
            break;
    }
    
    // Return success response with chart data
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'labels' => $labels,
        'appointments' => $appointments,
        'patients' => $patients
    ]);
    
} catch (PDOException $e) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}