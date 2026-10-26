<?php
// This is a diagnostic script to check payment data
session_start();
// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

echo "<h1>Payment Table Diagnostic</h1>";

// Check if payment table exists
try {
    $tableStmt = $conn->prepare("SHOW TABLES LIKE 'payment'");
    $tableStmt->execute();
    
    if ($tableStmt->rowCount() > 0) {
        echo "<p>✅ Payment table exists</p>";
        
        // Get table structure
        $columnStmt = $conn->prepare("DESCRIBE payment");
        $columnStmt->execute();
        $columns = $columnStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h2>Table Structure:</h2>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        $dateColumn = null;
        $hasAmountColumn = false;
        
        foreach ($columns as $column) {
            echo "<tr>";
            foreach ($column as $key => $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
            
            // Check for date columns
            if ($column['Field'] == 'created_at' || $column['Field'] == 'payment_date') {
                $dateColumn = $column['Field'];
            }
            
            // Check for amount column
            if ($column['Field'] == 'amount') {
                $hasAmountColumn = true;
            }
        }
        
        echo "</table>";
        
        if ($dateColumn) {
            echo "<p>✅ Date column found: {$dateColumn}</p>";
        } else {
            echo "<p>❌ No date column found (looking for 'created_at' or 'payment_date')</p>";
        }
        
        if ($hasAmountColumn) {
            echo "<p>✅ Amount column found</p>";
        } else {
            echo "<p>❌ No amount column found</p>";
        }
        
        // Count records in the table
        $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM payment");
        $countStmt->execute();
        $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo "<p>Total records in payment table: {$totalRecords}</p>";
        
        if ($totalRecords > 0) {
            // If we have a date column, show records by date range
            if ($dateColumn) {
                $dateRangeStmt = $conn->prepare("SELECT MIN({$dateColumn}) as min_date, MAX({$dateColumn}) as max_date FROM payment");
                $dateRangeStmt->execute();
                $dateRange = $dateRangeStmt->fetch(PDO::FETCH_ASSOC);
                
                echo "<p>Date range: " . $dateRange['min_date'] . " to " . $dateRange['max_date'] . "</p>";
            }
            
            // Show summary by month if we have a date column
            if ($dateColumn) {
                $monthlyStmt = $conn->prepare("SELECT DATE_FORMAT({$dateColumn}, '%Y-%m') as month, COUNT(*) as count, SUM(amount) as total FROM payment GROUP BY month ORDER BY month");
                $monthlyStmt->execute();
                $monthlyData = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($monthlyData) > 0) {
                    echo "<h2>Monthly Summary:</h2>";
                    echo "<table border='1' cellpadding='5'>";
                    echo "<tr><th>Month</th><th>Payment Count</th><th>Total Amount</th><th>Average Amount</th></tr>";
                    
                    $grandTotal = 0;
                    
                    foreach ($monthlyData as $month) {
                        $avgAmount = $month['count'] > 0 ? $month['total'] / $month['count'] : 0;
                        echo "<tr>";
                        echo "<td>" . $month['month'] . "</td>";
                        echo "<td>" . $month['count'] . "</td>";
                        echo "<td>₱" . number_format($month['total'] / 100, 2) . "</td>";
                        echo "<td>₱" . number_format($avgAmount / 100, 2) . "</td>";
                        echo "</tr>";
                        
                        $grandTotal += $month['total'];
                    }
                    
                    echo "<tr style='font-weight: bold;'>";
                    echo "<td>TOTAL</td>";
                    echo "<td>-</td>";
                    echo "<td>₱" . number_format($grandTotal / 100, 2) . "</td>";
                    echo "<td>-</td>";
                    echo "</tr>";
                    
                    echo "</table>";
                } else {
                    echo "<p>No monthly data available.</p>";
                }
            }
            
            // Show raw total
            $totalStmt = $conn->prepare("SELECT SUM(amount) as total FROM payment");
            $totalStmt->execute();
            $totalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
            
            echo "<h2>Total Revenue:</h2>";
            echo "<p>Total: ₱" . number_format($totalResult['total'] / 100, 2) . "</p>";
            
            // Sample data
            echo "<h2>Sample Data (10 records):</h2>";
            $sampleQuery = "SELECT * FROM payment ORDER BY ";
            $sampleQuery .= $dateColumn ? $dateColumn . " DESC" : "payment_id DESC";
            $sampleQuery .= " LIMIT 10";
            
            $sampleStmt = $conn->prepare($sampleQuery);
            $sampleStmt->execute();
            $sampleData = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($sampleData) > 0) {
                echo "<table border='1' cellpadding='5'>";
                
                // Header row
                echo "<tr>";
                foreach (array_keys($sampleData[0]) as $header) {
                    echo "<th>" . htmlspecialchars($header) . "</th>";
                }
                echo "</tr>";
                
                // Data rows
                foreach ($sampleData as $row) {
                    echo "<tr>";
                    foreach ($row as $value) {
                        echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                    }
                    echo "</tr>";
                }
                
                echo "</table>";
            } else {
                echo "<p>No sample data available.</p>";
            }
        }
    } else {
        echo "<p>❌ Payment table does not exist</p>";
        
        // Check alternative tables
        $altTables = ['payments', 'transaction', 'transactions', 'billing', 'invoice', 'invoices'];
        $foundAlt = false;
        
        foreach ($altTables as $table) {
            $altStmt = $conn->prepare("SHOW TABLES LIKE :table");
            $altStmt->bindParam(':table', $table);
            $altStmt->execute();
            
            if ($altStmt->rowCount() > 0) {
                echo "<p>✅ Alternative payment table found: {$table}</p>";
                $foundAlt = true;
            }
        }
        
        if (!$foundAlt) {
            echo "<p>❌ No alternative payment tables found</p>";
        }
    }
} catch (PDOException $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>

<p><a href="reports.php">Back to Reports</a></p>