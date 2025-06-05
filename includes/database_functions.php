<?php
/**
 * Database-based Task Management Functions
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Load tasks from database
 * @return array Array of tasks
 */
function loadTasksFromDb() {
    try {
        $pdo = getDbConnection();
        
        $stmt = $pdo->prepare("
            SELECT id, title, description, due_date as dueDate, completed, 
                   created_at as created, updated_at as updated
            FROM tasks 
            ORDER BY created_at DESC
        ");
        
        $stmt->execute();
        $tasks = $stmt->fetchAll();
        
        // Convert boolean values
        foreach ($tasks as &$task) {
            $task['completed'] = (bool)$task['completed'];
        }
        
        return $tasks;
    } catch (PDOException $e) {
        error_log('Failed to load tasks from database: ' . $e->getMessage());
        return [];
    }
}

/**
 * Save task to database
 * @param array $task Task data
 * @return array|false Task data with ID or false on failure
 */
function saveTaskToDb($task) {
    try {
        $pdo = getDbConnection();
        
        $stmt = $pdo->prepare("
            INSERT INTO tasks (id, title, description, due_date, completed, created_at, updated_at)
            VALUES (:id, :title, :description, :due_date, :completed, NOW(), NOW())
        ");
        
        $id = generateId();
        $result = $stmt->execute([
            'id' => $id,
            'title' => $task['title'],
            'description' => $task['description'] ?? '',
            'due_date' => $task['dueDate'] ?? null,
            'completed' => $task['completed'] ?? false
        ]);
        
        if ($result) {
            return [
                'id' => $id,
                'title' => $task['title'],
                'description' => $task['description'] ?? '',
                'dueDate' => $task['dueDate'] ?? null,
                'completed' => $task['completed'] ?? false,
                'created' => date('Y-m-d H:i:s'),
                'updated' => date('Y-m-d H:i:s')
            ];
        }
        
        return false;
    } catch (PDOException $e) {
        error_log('Failed to save task to database: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update task in database
 * @param string $id Task ID
 * @param array $updates Task updates
 * @return array|false Updated task data or false on failure
 */
function updateTaskInDb($id, $updates) {
    try {
        $pdo = getDbConnection();
        
        // Build dynamic update query
        $setParts = [];
        $params = ['id' => $id];
        
        foreach ($updates as $field => $value) {
            switch ($field) {
                case 'title':
                    $setParts[] = 'title = :title';
                    $params['title'] = $value;
                    break;
                case 'description':
                    $setParts[] = 'description = :description';
                    $params['description'] = $value;
                    break;
                case 'dueDate':
                    $setParts[] = 'due_date = :due_date';
                    $params['due_date'] = $value;
                    break;
                case 'completed':
                    $setParts[] = 'completed = :completed';
                    $params['completed'] = (bool)$value;
                    break;
            }
        }
        
        if (empty($setParts)) {
            return false;
        }
        
        $setParts[] = 'updated_at = NOW()';
        $sql = "UPDATE tasks SET " . implode(', ', $setParts) . " WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            // Get and return updated task
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
        }
        
        return false;
    } catch (PDOException $e) {
        error_log('Failed to update task in database: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete task from database
 * @param string $id Task ID
 * @return bool Success status
 */
function deleteTaskFromDb($id) {
    try {
        $pdo = getDbConnection();
        
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        error_log('Failed to delete task from database: ' . $e->getMessage());
        return false;
    }
}



/**
 * Get task statistics from database
 * @return array Statistics
 */
function getTaskStatsFromDb() {
    try {
        $pdo = getDbConnection();
        
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN completed = 0 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN completed = 0 AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue,
                SUM(CASE WHEN completed = 0 AND due_date = CURDATE() THEN 1 ELSE 0 END) as due_today
            FROM tasks
        ");
        
        $stats = $stmt->fetch();
        
        return [
            'total' => (int)$stats['total'],
            'completed' => (int)$stats['completed'],
            'active' => (int)$stats['active'],
            'overdue' => (int)$stats['overdue'],
            'dueToday' => (int)$stats['due_today'],
            'completionRate' => $stats['total'] > 0 ? round(($stats['completed'] / $stats['total']) * 100, 1) : 0
        ];
    } catch (PDOException $e) {
        error_log('Failed to get task statistics from database: ' . $e->getMessage());
        return [
            'total' => 0,
            'completed' => 0,
            'active' => 0,
            'overdue' => 0,
            'dueToday' => 0,
            'completionRate' => 0
        ];
    }
}

/**
 * Migrate existing JSON tasks to database
 * @return bool Success status
 */
function migrateJsonToDatabase() {
    try {
        // Include functions.php for loadTasks function
        require_once __DIR__ . '/functions.php';
        
        // Check if database is available
        if (!isDatabaseAvailable()) {
            return false;
        }
        
        // Initialize database tables
        if (!initializeDatabase()) {
            return false;
        }
        
        // Load existing JSON tasks
        $jsonTasks = loadTasks();
        
        if (empty($jsonTasks)) {
            return true; // No tasks to migrate
        }
        
        $pdo = getDbConnection();
        
        // Prepare insert statement
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO tasks (id, title, description, due_date, completed, created_at, updated_at)
            VALUES (:id, :title, :description, :due_date, :completed, :created_at, :updated_at)
        ");
        
        $migrated = 0;
        foreach ($jsonTasks as $task) {
            $result = $stmt->execute([
                'id' => $task['id'],
                'title' => $task['title'],
                'description' => $task['description'] ?? '',
                'due_date' => $task['dueDate'] ?? null,
                'completed' => $task['completed'] ?? false,
                'created_at' => $task['created'] ?? date('Y-m-d H:i:s'),
                'updated_at' => $task['updated'] ?? date('Y-m-d H:i:s')
            ]);
            
            if ($result) {
                $migrated++;
            }
        }
        
        error_log("Migrated $migrated tasks from JSON to database");
        return true;
        
    } catch (Exception $e) {
        error_log('Migration failed: ' . $e->getMessage());
        return false;
    }
}
?>