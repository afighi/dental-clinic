<?php
require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Function to measure query execution time
function measureQueryTime($conn, $query, $params = []) {
    $start = microtime(true);
    
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $end = microtime(true);
    return [
        'time' => ($end - $start) * 1000, // Convert to milliseconds
        'result' => $result
    ];
}

// Test queries
$queries = [
    'username_lookup' => [
        'query' => "SELECT * FROM user WHERE username = :username",
        'params' => [':username' => 'testuser']
    ],
    'email_lookup' => [
        'query' => "SELECT * FROM user WHERE email = :email",
        'params' => [':email' => 'test@example.com']
    ],
    'role_join' => [
        'query' => "SELECT u.*, r.role_name FROM user u JOIN userrole r ON u.role_id = r.role_id",
        'params' => []
    ]
];

// Run each query multiple times to get an average
$iterations = 100;
$results = [];

foreach ($queries as $name => $query_info) {
    $total_time = 0;
    
    for ($i = 0; $i < $iterations; $i++) {
        $result = measureQueryTime($conn, $query_info['query'], $query_info['params']);
        $total_time += $result['time'];
    }
    
    $results[$name] = [
        'average_time' => $total_time / $iterations,
        'query' => $query_info['query']
    ];
}

// Display results
echo "Query Performance Results (Average of $iterations iterations):\n\n";

foreach ($results as $name => $result) {
    echo "Query: $name\n";
    echo "SQL: {$result['query']}\n";
    echo "Average Execution Time: {$result['average_time']} ms\n\n";
}

// Show current indexes
$indexes = $conn->query("SHOW INDEXES FROM user")->fetchAll(PDO::FETCH_ASSOC);
echo "Current Indexes on user table:\n";
foreach ($indexes as $index) {
    echo "- {$index['Key_name']} on column {$index['Column_name']}\n";
}
?> 