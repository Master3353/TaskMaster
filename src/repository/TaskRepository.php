<?php

require_once 'Repository.php';

class TaskRepository extends Repository
{
    private static $instance;

    public static function getInstance()
    {
        return self::$instance ??= new TaskRepository();
    }

    // Get all tasks for a specific user with JOIN
    public function getUserTasks(int $userId): array
    {
        $query = $this->database->connect()->prepare("
            SELECT 
                t.id,
                t.task_name,
                t.description,
                t.due_date,
                p.name as priority,
                p.level as priority_level,
                s.name as status,
                s.id as status_id,
                t.created_at,
                t.updated_at,
                ta.assigned_at,
                ta.completed_at,
                CASE 
                    WHEN t.due_date < CURRENT_DATE AND s.name != 'Complete' THEN 'Overdue'
                    WHEN t.due_date = CURRENT_DATE AND s.name != 'Complete' THEN 'Due Today'
                    ELSE 'On Track'
                END as urgency
            FROM task_assignments ta
            INNER JOIN tasks t ON ta.task_id = t.id
            LEFT JOIN priorities p ON t.priority_id = p.id
            INNER JOIN statuses s ON t.status_id = s.id
            WHERE ta.user_id = :user_id
            ORDER BY 
                CASE 
                    WHEN t.due_date < CURRENT_DATE THEN 0
                    ELSE 1
                END,
                p.level DESC NULLS LAST,
                t.due_date ASC NULLS LAST
        ");
        
        $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();
        
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get single task with details
    public function getTaskById(int $taskId, int $userId): ?array
    {
        $query = $this->database->connect()->prepare("
            SELECT 
                t.id,
                t.task_name,
                t.description,
                t.due_date,
                t.priority_id,
                p.name as priority,
                t.status_id,
                s.name as status,
                t.created_at,
                t.updated_at,
                u.firstname || ' ' || u.lastname as created_by_name
            FROM tasks t
            LEFT JOIN priorities p ON t.priority_id = p.id
            INNER JOIN statuses s ON t.status_id = s.id
            INNER JOIN users u ON t.created_by = u.id
            INNER JOIN task_assignments ta ON t.id = ta.task_id
            WHERE t.id = :task_id AND ta.user_id = :user_id
        ");
        
        $query->bindParam(':task_id', $taskId, PDO::PARAM_INT);
        $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();
        
        $result = $query->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    // Create task with transaction and isolation level
    public function createTask(
        string $taskName,
        string $description,
        ?string $dueDate,
        ?int $priorityId,
        int $statusId,
        int $createdBy,
        array $assignToUsers
    ): ?int {
        $conn = $this->database->connect();
        
        try {
            // Start transaction with READ COMMITTED isolation
            $conn->beginTransaction();
            $conn->exec("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");
            
            // Insert task
            $stmt = $conn->prepare("
                INSERT INTO tasks (task_name, description, due_date, priority_id, status_id, created_by)
                VALUES (:task_name, :description, :due_date, :priority_id, :status_id, :created_by)
                RETURNING id
            ");
            
            $stmt->bindParam(':task_name', $taskName);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':due_date', $dueDate);
            $stmt->bindParam(':priority_id', $priorityId, PDO::PARAM_INT);
            $stmt->bindParam(':status_id', $statusId, PDO::PARAM_INT);
            $stmt->bindParam(':created_by', $createdBy, PDO::PARAM_INT);
            
            $stmt->execute();
            $taskId = $stmt->fetch(PDO::FETCH_ASSOC)['id'];
            
            // Assign task to users
            $assignStmt = $conn->prepare("
                INSERT INTO task_assignments (task_id, user_id)
                VALUES (:task_id, :user_id)
            ");
            
            foreach ($assignToUsers as $userId) {
                $assignStmt->bindParam(':task_id', $taskId, PDO::PARAM_INT);
                $assignStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $assignStmt->execute();
            }
            
            $conn->commit();
            return $taskId;
            
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Task creation failed: " . $e->getMessage());
            return null;
        }
    }

    // Update task with transaction
    public function updateTask(
        int $taskId,
        int $userId,
        string $taskName,
        string $description,
        ?string $dueDate,
        ?int $priorityId,
        int $statusId
    ): bool {
        $conn = $this->database->connect();
        
        try {
            $conn->beginTransaction();
            $conn->exec("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");
            
            // Verify user has access to this task
            $checkStmt = $conn->prepare("
                SELECT 1 FROM task_assignments 
                WHERE task_id = :task_id AND user_id = :user_id
            ");
            $checkStmt->bindParam(':task_id', $taskId, PDO::PARAM_INT);
            $checkStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if (!$checkStmt->fetch()) {
                $conn->rollBack();
                return false;
            }
            
            // Update task
            $stmt = $conn->prepare("
                UPDATE tasks 
                SET task_name = :task_name,
                    description = :description,
                    due_date = :due_date,
                    priority_id = :priority_id,
                    status_id = :status_id
                WHERE id = :task_id
            ");
            
            $stmt->bindParam(':task_name', $taskName);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':due_date', $dueDate);
            $stmt->bindParam(':priority_id', $priorityId, PDO::PARAM_INT);
            $stmt->bindParam(':status_id', $statusId, PDO::PARAM_INT);
            $stmt->bindParam(':task_id', $taskId, PDO::PARAM_INT);
            
            $result = $stmt->execute();
            $conn->commit();
            
            return $result;
            
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Task update failed: " . $e->getMessage());
            return false;
        }
    }

    // Delete task
    public function deleteTask(int $taskId, int $userId): bool
    {
        $conn = $this->database->connect();
        
        try {
            $conn->beginTransaction();
            
            // Verify user created this task or is admin
            $checkStmt = $conn->prepare("
                SELECT t.created_by, u.role
                FROM tasks t
                CROSS JOIN users u
                WHERE t.id = :task_id AND u.id = :user_id
            ");
            $checkStmt->bindParam(':task_id', $taskId, PDO::PARAM_INT);
            $checkStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $checkStmt->execute();
            
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result || ($result['created_by'] != $userId && $result['role'] != 'admin')) {
                $conn->rollBack();
                return false;
            }
            
            $stmt = $conn->prepare("DELETE FROM tasks WHERE id = :task_id");
            $stmt->bindParam(':task_id', $taskId, PDO::PARAM_INT);
            $success = $stmt->execute();
            
            $conn->commit();
            return $success;
            
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Task deletion failed: " . $e->getMessage());
            return false;
        }
    }

    // Get priorities
    public function getPriorities(): array
    {
        $query = $this->database->connect()->prepare("
            SELECT id, name, level FROM priorities ORDER BY level
        ");
        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get statuses
    public function getStatuses(): array
    {
        $query = $this->database->connect()->prepare("
            SELECT id, name FROM statuses ORDER BY id
        ");
        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get user task statistics using function
    public function getUserTaskStats(int $userId): ?array
    {
        $query = $this->database->connect()->prepare("
            SELECT * FROM get_user_task_stats(:user_id)
        ");
        $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();
        
        return $query->fetch(PDO::FETCH_ASSOC);
    }

    // Get tasks from view
    public function getUserTasksFromView(int $userId): array
    {
        $query = $this->database->connect()->prepare("
            SELECT * FROM v_user_tasks_overview
            WHERE user_id = :user_id
            ORDER BY 
                CASE task_urgency
                    WHEN 'Overdue' THEN 0
                    WHEN 'Due Today' THEN 1
                    ELSE 2
                END,
                priority_level DESC NULLS LAST
        ");
        $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();
        
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }
}