<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/env.php';
require_once '../includes/functions.php';
require_once '../includes/database_functions.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    // Use MySQL database exclusively
    initializeDatabase();
    
    switch ($method) {
        case 'GET':
            handleGetTasksDb();
            break;
        case 'POST':
            handleCreateTaskDb($input);
            break;
        case 'PUT':
            handleUpdateTaskDb($input);
            break;
        case 'DELETE':
            handleDeleteTaskDb($input);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

function handleGetTasksDb() {
    $tasks = loadTasksFromDb();
    
    // Sort tasks by creation date (newest first) by default
    usort($tasks, function($a, $b) {
        return strtotime($b['created']) - strtotime($a['created']);
    });
    
    echo json_encode([
        'success' => true,
        'tasks' => $tasks,
        'count' => count($tasks),
        'storage' => 'database'
    ]);
}

function handleCreateTaskDb($input) {
    if (empty($input['title'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Task title is required']);
        return;
    }
    
    $taskData = [
        'title' => sanitizeInput($input['title']),
        'description' => sanitizeInput($input['description'] ?? ''),
        'dueDate' => $input['dueDate'] ?? null,
        'completed' => false
    ];
    
    $newTask = saveTaskToDb($taskData);
    
    if ($newTask) {
        echo json_encode([
            'success' => true,
            'message' => 'Task created successfully',
            'task' => $newTask,
            'storage' => 'database'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save task']);
    }
}

function handleUpdateTaskDb($input) {
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID is required']);
        return;
    }
    
    $updates = [];
    
    if (isset($input['title'])) {
        if (empty($input['title'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Task title cannot be empty']);
            return;
        }
        $updates['title'] = sanitizeInput($input['title']);
    }
    
    if (isset($input['description'])) {
        $updates['description'] = sanitizeInput($input['description']);
    }
    
    if (isset($input['dueDate'])) {
        $updates['dueDate'] = $input['dueDate'];
    }
    
    if (isset($input['completed'])) {
        $updates['completed'] = (bool)$input['completed'];
    }
    
    $updatedTask = updateTaskInDb($input['id'], $updates);
    
    if ($updatedTask) {
        echo json_encode([
            'success' => true,
            'message' => 'Task updated successfully',
            'task' => $updatedTask,
            'storage' => 'database'
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Task not found or update failed']);
    }
}

function handleDeleteTaskDb($input) {
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID is required']);
        return;
    }
    
    // Get task before deletion for response
    $task = getTaskFromDb($input['id']);
    
    if (!$task) {
        http_response_code(404);
        echo json_encode(['error' => 'Task not found']);
        return;
    }
    
    if (deleteTaskFromDb($input['id'])) {
        echo json_encode([
            'success' => true,
            'message' => 'Task deleted successfully',
            'task' => $task,
            'storage' => 'database'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete task']);
    }
}

function getTaskFromDb($id) {
    try {
        $pdo = getDbConnection();
        
        $stmt = $pdo->prepare("
            SELECT id, title, description, due_date as dueDate, completed,
                   created_at as created, updated_at as updated
            FROM tasks 
            WHERE id = :id
        ");
        
        $stmt->execute(['id' => $id]);
        $task = $stmt->fetch();
        
        if ($task) {
            $task['completed'] = (bool)$task['completed'];
            return $task;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log('Failed to get task from database: ' . $e->getMessage());
        return false;
    }
}


?>
