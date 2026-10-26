<?php
require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

try {
    // Add indexes
    $conn->exec("ALTER TABLE user ADD PRIMARY KEY (user_id)");
    echo "Added primary key on user_id\n";
    
    $conn->exec("CREATE UNIQUE INDEX idx_username ON user(username)");
    echo "Added unique index on username\n";
    
    $conn->exec("CREATE UNIQUE INDEX idx_email ON user(email)");
    echo "Added unique index on email\n";
    
    $conn->exec("CREATE INDEX idx_role_id ON user(role_id)");
    echo "Added index on role_id\n";
    
    // Show current indexes
    $indexes = $conn->query("SHOW INDEXES FROM user")->fetchAll(PDO::FETCH_ASSOC);
    echo "\nCurrent Indexes on user table:\n";
    foreach ($indexes as $index) {
        echo "- {$index['Key_name']} on column {$index['Column_name']}\n";
    }
    
    echo "\nIndexes have been successfully created!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 