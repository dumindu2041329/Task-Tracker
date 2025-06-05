<?php
/**
 * Task Tracker Utility Functions
 */

define('TASKS_FILE', __DIR__ . '/../data/tasks.json');

/**
 * Load tasks from JSON file
 * @return array Array of tasks
 */
function loadTasks() {
    if (!file_exists(TASKS_FILE)) {
        // Create directory if it doesn't exist
        $dir = dirname(TASKS_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Create empty tasks file
        file_put_contents(TASKS_FILE, json_encode([]));
        return [];
    }
    
    $content = file_get_contents(TASKS_FILE);
    $tasks = json_decode($content, true);
    
    return is_array($tasks) ? $tasks : [];
}

/**
 * Save tasks to JSON file
 * @param array $tasks Array of tasks to save
 * @return bool Success status
 */
function saveTasks($tasks) {
    try {
        // Ensure directory exists
        $dir = dirname(TASKS_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Save with pretty printing for better readability
        $json = json_encode($tasks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if ($json === false) {
            error_log('Failed to encode tasks to JSON: ' . json_last_error_msg());
            return false;
        }
        
        $result = file_put_contents(TASKS_FILE, $json, LOCK_EX);
        
        if ($result === false) {
            error_log('Failed to write tasks file: ' . TASKS_FILE);
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        error_log('Error saving tasks: ' . $e->getMessage());
        return false;
    }
}

/**
 * Generate a unique ID for tasks
 * @return string Unique identifier
 */
function generateId() {
    return uniqid('task_', true);
}

/**
 * Sanitize user input
 * @param string $input Raw input
 * @return string Sanitized input
 */
function sanitizeInput($input) {
    if (!is_string($input)) {
        return '';
    }
    
    // Remove potentially dangerous characters
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    return $input;
}

/**
 * Validate date format
 * @param string $date Date string to validate
 * @return bool Valid date status
 */
function isValidDate($date) {
    if (empty($date)) {
        return true; // Allow empty dates
    }
    
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Format date for display
 * @param string $date Date string
 * @return string Formatted date
 */
function formatDisplayDate($date) {
    if (empty($date)) {
        return '';
    }
    
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d ? $d->format('M j, Y') : $date;
}

/**
 * Check if date is overdue
 * @param string $date Date string
 * @return bool Overdue status
 */
function isOverdue($date) {
    if (empty($date)) {
        return false;
    }
    
    $dueDate = DateTime::createFromFormat('Y-m-d', $date);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    return $dueDate && $dueDate < $today;
}

/**
 * Check if date is today
 * @param string $date Date string
 * @return bool Today status
 */
function isDueToday($date) {
    if (empty($date)) {
        return false;
    }
    
    $dueDate = DateTime::createFromFormat('Y-m-d', $date);
    $today = new DateTime();
    
    return $dueDate && $dueDate->format('Y-m-d') === $today->format('Y-m-d');
}

/**
 * Validate task data
 * @param array $task Task data to validate
 * @return array Validation result with 'valid' and 'errors' keys
 */
function validateTask($task) {
    $errors = [];
    
    // Title is required
    if (empty($task['title']) || trim($task['title']) === '') {
        $errors[] = 'Task title is required';
    }
    
    // Title length check
    if (isset($task['title']) && strlen($task['title']) > 255) {
        $errors[] = 'Task title is too long (maximum 255 characters)';
    }
    
    // Description length check
    if (isset($task['description']) && strlen($task['description']) > 1000) {
        $errors[] = 'Task description is too long (maximum 1000 characters)';
    }
    
    // Due date validation
    if (isset($task['dueDate']) && !empty($task['dueDate']) && !isValidDate($task['dueDate'])) {
        $errors[] = 'Invalid due date format (expected YYYY-MM-DD)';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Get task statistics
 * @param array $tasks Array of tasks
 * @return array Statistics array
 */
function getTaskStats($tasks) {
    $total = count($tasks);
    $completed = 0;
    $overdue = 0;
    $dueToday = 0;
    
    foreach ($tasks as $task) {
        if ($task['completed']) {
            $completed++;
        }
        
        if (!$task['completed'] && isset($task['dueDate']) && !empty($task['dueDate'])) {
            if (isOverdue($task['dueDate'])) {
                $overdue++;
            } elseif (isDueToday($task['dueDate'])) {
                $dueToday++;
            }
        }
    }
    
    return [
        'total' => $total,
        'completed' => $completed,
        'active' => $total - $completed,
        'overdue' => $overdue,
        'dueToday' => $dueToday,
        'completionRate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0
    ];
}

/**
 * Log error message
 * @param string $message Error message
 * @param array $context Additional context
 */
function logError($message, $context = []) {
    $logMessage = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    
    if (!empty($context)) {
        $logMessage .= ' Context: ' . json_encode($context);
    }
    
    error_log($logMessage);
}

/**
 * Response helper function
 * @param bool $success Success status
 * @param mixed $data Response data
 * @param string $message Response message
 * @param int $httpCode HTTP status code
 */
function sendResponse($success, $data = null, $message = '', $httpCode = 200) {
    http_response_code($httpCode);
    
    $response = ['success' => $success];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    if (!empty($message)) {
        $response['message'] = $message;
    }
    
    echo json_encode($response);
    exit;
}
?>
