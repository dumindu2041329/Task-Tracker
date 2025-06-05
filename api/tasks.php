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
    // Check if database is available and use it, otherwise fallback to JSON
    $useDatabase = isDatabaseAvailable();
    
    if ($useDatabase) {
        initializeDatabase();
        migrateJsonToDatabase();
    }
    
    switch ($method) {
        case 'GET':
            handleGetTasks($useDatabase);
            break;
        case 'POST':
            handleCreateTask($input, $useDatabase);
            break;
        case 'PUT':
            handleUpdateTask($input, $useDatabase);
            break;
        case 'DELETE':
            handleDeleteTask($input, $useDatabase);
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

function handleGetTasks($useDatabase = false) {
    if ($useDatabase) {
        $tasks = loadTasksFromDb();
    } else {
        $tasks = loadTasks();
    }
    
    // Sort tasks by creation date (newest first) by default
    usort($tasks, function($a, $b) {
        return strtotime($b['created']) - strtotime($a['created']);
    });
    
    echo json_encode([
        'success' => true,
        'tasks' => $tasks,
        'count' => count($tasks),
        'storage' => $useDatabase ? 'database' : 'json'
    ]);
}

function handleCreateTask($input, $useDatabase = false) {
    if (empty($input['title'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Task title is required']);
        return;
    }
    
    if ($useDatabase) {
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
    } else {
        $tasks = loadTasks();
        
        $newTask = [
            'id' => generateId(),
            'title' => sanitizeInput($input['title']),
            'description' => sanitizeInput($input['description'] ?? ''),
            'dueDate' => $input['dueDate'] ?? null,
            'completed' => false,
            'created' => date('Y-m-d H:i:s'),
            'updated' => date('Y-m-d H:i:s')
        ];
        
        $tasks[] = $newTask;
        
        if (saveTasks($tasks)) {
            echo json_encode([
                'success' => true,
                'message' => 'Task created successfully',
                'task' => $newTask,
                'storage' => 'json'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save task']);
        }
    }
}

function handleUpdateTask($input, $useDatabase = false) {
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID is required']);
        return;
    }
    
    if ($useDatabase) {
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
    } else {
        $tasks = loadTasks();
        $taskIndex = findTaskIndex($tasks, $input['id']);
        
        if ($taskIndex === false) {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found']);
            return;
        }
        
        // Update task fields
        if (isset($input['title'])) {
            if (empty($input['title'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Task title cannot be empty']);
                return;
            }
            $tasks[$taskIndex]['title'] = sanitizeInput($input['title']);
        }
        
        if (isset($input['description'])) {
            $tasks[$taskIndex]['description'] = sanitizeInput($input['description']);
        }
        
        if (isset($input['dueDate'])) {
            $tasks[$taskIndex]['dueDate'] = $input['dueDate'];
        }
        
        if (isset($input['completed'])) {
            $tasks[$taskIndex]['completed'] = (bool)$input['completed'];
        }
        
        $tasks[$taskIndex]['updated'] = date('Y-m-d H:i:s');
        
        if (saveTasks($tasks)) {
            echo json_encode([
                'success' => true,
                'message' => 'Task updated successfully',
                'task' => $tasks[$taskIndex],
                'storage' => 'json'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update task']);
        }
    }
}

function handleDeleteTask($input, $useDatabase = false) {
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID is required']);
        return;
    }
    
    if ($useDatabase) {
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
    } else {
        $tasks = loadTasks();
        $taskIndex = findTaskIndex($tasks, $input['id']);
        
        if ($taskIndex === false) {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found']);
            return;
        }
        
        $deletedTask = $tasks[$taskIndex];
        array_splice($tasks, $taskIndex, 1);
        
        if (saveTasks($tasks)) {
            echo json_encode([
                'success' => true,
                'message' => 'Task deleted successfully',
                'task' => $deletedTask,
                'storage' => 'json'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete task']);
        }
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

function findTaskIndex($tasks, $id) {
    foreach ($tasks as $index => $task) {
        if ($task['id'] === $id) {
            return $index;
        }
    }
    return false;
}
?>
