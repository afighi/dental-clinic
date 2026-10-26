<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Dump the session data for debugging
echo "<pre style='background:#f5f5f5;padding:10px;border:1px solid #ccc;margin:10px;'>";
echo "SESSION DATA:\n";
print_r($_SESSION);
echo "</pre>";

// Direct query to get user data
try {
    $userId = $_SESSION['user_id'];
    $query = "SELECT * FROM user WHERE user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get role name
        $roleQuery = "SELECT role_name FROM userrole WHERE role_id = :role_id";
        $roleStmt = $conn->prepare($roleQuery);
        $roleStmt->bindParam(':role_id', $user['role_id'], PDO::PARAM_INT);
        $roleStmt->execute();
        $roleName = $roleStmt->fetchColumn();
        
        // Display user data
        echo "<div style='background:#fff;padding:20px;border:1px solid #ccc;margin:10px;border-radius:5px;'>";
        echo "<h2>User Profile Data</h2>";
        echo "<p><strong>User ID:</strong> " . htmlspecialchars($user['user_id']) . "</p>";
        echo "<p><strong>Username:</strong> " . htmlspecialchars($user['username']) . "</p>";
        echo "<p><strong>Email:</strong> " . htmlspecialchars($user['email']) . "</p>";
        echo "<p><strong>Name:</strong> " . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</p>";
        echo "<p><strong>Phone:</strong> " . htmlspecialchars($user['phone_number']) . "</p>";
        echo "<p><strong>Address:</strong> " . htmlspecialchars($user['address']) . "</p>";
        echo "<p><strong>Role:</strong> " . htmlspecialchars($roleName) . " (ID: " . $user['role_id'] . ")</p>";
        echo "</div>";
        
        // Dump raw user data for debugging
        echo "<pre style='background:#f5f5f5;padding:10px;border:1px solid #ccc;margin:10px;'>";
        echo "DATABASE USER DATA:\n";
        print_r($user);
        echo "</pre>";
        
        // Provide link to the full profile page
        echo "<div style='text-align:center;margin:20px;'>";
        echo "<a href='profile.php' style='padding:10px 20px;background:#1806a8;color:white;text-decoration:none;border-radius:5px;'>Go to Normal Profile Page</a>";
        echo "</div>";
    } else {
        echo "<div style='background:#fff;padding:20px;border:1px solid #ccc;margin:10px;border-radius:5px;'>";
        echo "<h2>Error</h2>";
        echo "<p>No user found with ID: " . htmlspecialchars($userId) . "</p>";
        echo "</div>";
    }
} catch (PDOException $e) {
    echo "<div style='background:#ffdddd;padding:20px;border:1px solid #f00;margin:10px;border-radius:5px;'>";
    echo "<h2>Database Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>