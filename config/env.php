<?php
/**
 * Environment Configuration
 * Load environment variables for the application
 */

// Define default environment variables
$defaultEnv = [
    'DB_HOST' => 'localhost',
    'DB_PORT' => '3306',
    'DB_NAME' => 'task_tracker',
    'DB_USER' => 'root',
    'DB_PASSWORD' => '',
    'USE_DATABASE' => 'true'
];

// Load .env file if it exists
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue; // Skip comments
        
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        // Remove quotes if present
        if ((substr($value, 0, 1) == '"' && substr($value, -1) == '"') ||
            (substr($value, 0, 1) == "'" && substr($value, -1) == "'")) {
            $value = substr($value, 1, -1);
        }
        
        $_ENV[$key] = $value;
    }
}

// Set defaults for missing environment variables
foreach ($defaultEnv as $key => $value) {
    if (!isset($_ENV[$key])) {
        $_ENV[$key] = $value;
    }
}

/**
 * Get environment variable with default fallback
 */
function env($key, $default = null) {
    return $_ENV[$key] ?? $default;
}
?>