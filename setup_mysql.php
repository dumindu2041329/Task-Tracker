<?php
/**
 * MySQL Database Setup Script
 * Run this script to initialize the MySQL database for the task tracker
 */

require_once 'config/database.php';
require_once 'includes/database_functions.php';

echo "Task Tracker MySQL Setup\n";
echo "========================\n\n";

// Check if MySQL extension is loaded
if (!extension_loaded('pdo_mysql')) {
    echo "❌ Error: PDO MySQL extension is not loaded.\n";
    echo "Please install php-mysql package.\n";
    exit(1);
}

echo "✅ PDO MySQL extension is loaded.\n";

// Test database connection
try {
    echo "Testing database connection...\n";
    
    // Try to connect without specifying database first
    $config = [
        'host' => $_ENV['DB_HOST'] ?? 'sql12.freesqldatabase.com',
        'port' => $_ENV['DB_PORT'] ?? 3306,
        'username' => $_ENV['DB_USER'] ?? 'sql12783262',
        'password' => $_ENV['DB_PASSWORD'] ?? 'xpVneSYgDT',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    ];
    
    // Connect directly to your hosted database
    $pdo = getDbConnection();
    echo "✅ Connected to external MySQL database.\n";
    
    // Initialize tables
    if (initializeDatabase()) {
        echo "✅ Database tables created successfully.\n";
    } else {
        echo "❌ Failed to create database tables.\n";
        exit(1);
    }
    
    // Migrate existing JSON data
    echo "Checking for existing JSON data to migrate...\n";
    if (migrateJsonToDatabase()) {
        echo "✅ Data migration completed successfully.\n";
    } else {
        echo "⚠️  Data migration failed or no data to migrate.\n";
    }
    
    // Get current stats
    $stats = getTaskStatsFromDb();
    echo "\nDatabase Statistics:\n";
    echo "- Total tasks: {$stats['total']}\n";
    echo "- Active tasks: {$stats['active']}\n";
    echo "- Completed tasks: {$stats['completed']}\n";
    echo "- Overdue tasks: {$stats['overdue']}\n";
    echo "- Due today: {$stats['dueToday']}\n";
    
    echo "\n✅ MySQL setup completed successfully!\n";
    echo "Your task tracker is now using MySQL database for storage.\n";
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    echo "\nPlease check your database configuration:\n";
    echo "- Host: " . ($_ENV['DB_HOST'] ?? 'localhost') . "\n";
    echo "- Port: " . ($_ENV['DB_PORT'] ?? 3306) . "\n";
    echo "- Username: " . ($_ENV['DB_USER'] ?? 'root') . "\n";
    echo "- Database: " . ($_ENV['DB_NAME'] ?? 'task_tracker') . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Setup failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>