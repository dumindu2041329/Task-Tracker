<?php
/**
 * Database Setup and Testing Script
 */

// Set up environment variables for MySQL
$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_PORT'] = '3306';
$_ENV['DB_NAME'] = 'task_tracker';
$_ENV['DB_USER'] = 'root';
$_ENV['DB_PASSWORD'] = '';

require_once 'config/database.php';
require_once 'includes/database_functions.php';

echo "Testing MySQL Database Connection...\n";

try {
    // Test if we can create a connection without the database first
    $testDsn = "mysql:host=localhost;port=3306;charset=utf8mb4";
    $testPdo = new PDO($testDsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✓ MySQL server connection successful\n";
    
    // Create database if it doesn't exist
    $testPdo->exec("CREATE DATABASE IF NOT EXISTS task_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database 'task_tracker' created or exists\n";
    
    // Now test the full connection
    $pdo = getDbConnection();
    echo "✓ Connected to task_tracker database\n";
    
    // Initialize tables
    if (initializeDatabase()) {
        echo "✓ Database tables initialized\n";
    }
    
    // Test basic operations
    $testTask = [
        'title' => 'Test MySQL Connection',
        'description' => 'This is a test task to verify MySQL integration',
        'dueDate' => date('Y-m-d', strtotime('+1 day')),
        'completed' => false
    ];
    
    $newTask = saveTaskToDb($testTask);
    if ($newTask) {
        echo "✓ Test task created successfully\n";
        
        // Test loading tasks
        $tasks = loadTasksFromDb();
        if (count($tasks) > 0) {
            echo "✓ Tasks loaded successfully\n";
        }
        
        // Clean up test task
        if (deleteTaskFromDb($newTask['id'])) {
            echo "✓ Test task deleted successfully\n";
        }
    }
    
    echo "\nMySQL database is ready for use!\n";
    echo "The task tracker will now use MySQL for data storage.\n";
    
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    echo "The task tracker will continue using JSON file storage.\n";
}
?>