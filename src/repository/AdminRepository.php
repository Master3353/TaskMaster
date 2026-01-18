<?php

require_once __DIR__ . '/Repository.php';

class AdminRepository extends Repository
{
    private static $instance;

    public static function getInstance()
    {
        return self::$instance ??= new AdminRepository();
    }

    // Get all users with their profile info (JOIN)
    public function getAllUsers(): array
    {
        $query = $this->database->connect()->prepare("
            SELECT 
                u.id,
                u.firstname,
                u.lastname,
                u.email,
                u.role,
                u.enabled,
                u.created_at,
                u.last_login,
                up.bio,
                up.phone,
                COUNT(DISTINCT s.id) FILTER (WHERE s.is_active = TRUE AND s.expires_at > CURRENT_TIMESTAMP) as active_sessions,
                COUNT(DISTINCT ta.task_id) as assigned_tasks
            FROM users u
            LEFT JOIN user_profiles up ON u.id = up.user_id
            LEFT JOIN sessions s ON u.id = s.user_id
            LEFT JOIN task_assignments ta ON u.id = ta.user_id
            GROUP BY u.id, up.bio, up.phone
            ORDER BY u.created_at DESC
        ");
        
        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get user details
    public function getUserDetails(int $userId): ?array
    {
        $query = $this->database->connect()->prepare("
            SELECT 
                u.id,
                u.firstname,
                u.lastname,
                u.email,
                u.role,
                u.enabled,
                u.created_at,
                u.last_login,
                up.bio,
                up.phone,
                up.avatar_url
            FROM users u
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE u.id = :user_id
        ");
        
        $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();
        
        $result = $query->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    // Toggle user enabled status
    public function toggleUserStatus(int $userId, int $adminId): bool
    {
        $conn = $this->database->connect();
        
        try {
            $conn->beginTransaction();
            $conn->exec("SET TRANSACTION ISOLATION LEVEL SERIALIZABLE");
            
            // Verify admin role
            $adminCheck = $conn->prepare("
                SELECT role FROM users WHERE id = :admin_id
            ");
            $adminCheck->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
            $adminCheck->execute();
            $admin = $adminCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$admin || $admin['role'] !== 'admin') {
                $conn->rollBack();
                return false;
            }
            
            // Don't allow disabling self
            if ($userId === $adminId) {
                $conn->rollBack();
                return false;
            }
            
            // Toggle status
            $stmt = $conn->prepare("
                UPDATE users 
                SET enabled = NOT enabled 
                WHERE id = :user_id
            ");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $success = $stmt->execute();
            
            // If disabling, invalidate all sessions
            if ($success) {
                $invalidateStmt = $conn->prepare("
                    UPDATE sessions 
                    SET is_active = FALSE 
                    WHERE user_id = :user_id
                ");
                $invalidateStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $invalidateStmt->execute();
            }
            
            $conn->commit();
            return $success;
            
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Toggle user status failed: " . $e->getMessage());
            return false;
        }
    }

    // Safe delete user (using stored function)
    public function safeDeleteUser(int $userId, int $adminId): array
    {
        $conn = $this->database->connect();
        
        try {
            $conn->beginTransaction();
            $conn->exec("SET TRANSACTION ISOLATION LEVEL SERIALIZABLE");
            
            // Don't allow deleting self
            if ($userId === $adminId) {
                $conn->rollBack();
                return [
                    'success' => false,
                    'message' => 'Cannot delete your own account'
                ];
            }
            
            $stmt = $conn->prepare("
                SELECT safe_delete_user(:user_id, :admin_id) as result
            ");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $message = $result['result'];
            
            if (strpos($message, 'SUCCESS') === 0) {
                $conn->commit();
                return [
                    'success' => true,
                    'message' => 'User deleted successfully'
                ];
            } else {
                $conn->rollBack();
                return [
                    'success' => false,
                    'message' => str_replace('ERROR: ', '', $message)
                ];
            }
            
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Delete user failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Database error occurred'
            ];
        }
    }

    // Get admin dashboard statistics (from view)
    public function getDashboardStats(): ?array
    {
        $query = $this->database->connect()->prepare("
            SELECT * FROM v_admin_dashboard
        ");
        
        $query->execute();
        return $query->fetch(PDO::FETCH_ASSOC);
    }

    // Get user's active sessions
    public function getUserSessions(int $userId): array
    {
        $query = $this->database->connect()->prepare("
            SELECT 
                id,
                ip_address,
                user_agent,
                created_at,
                expires_at,
                is_active,
                CASE 
                    WHEN expires_at > CURRENT_TIMESTAMP THEN 'Active'
                    ELSE 'Expired'
                END as status
            FROM sessions
            WHERE user_id = :user_id
            ORDER BY created_at DESC
            LIMIT 10
        ");
        
        $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();
        
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    // Invalidate user session
    public function invalidateSession(int $sessionId, int $adminId): bool
    {
        $conn = $this->database->connect();
        
        try {
            // Verify admin
            $adminCheck = $conn->prepare("
                SELECT role FROM users WHERE id = :admin_id
            ");
            $adminCheck->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
            $adminCheck->execute();
            $admin = $adminCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$admin || $admin['role'] !== 'admin') {
                return false;
            }
            
            $stmt = $conn->prepare("
                UPDATE sessions 
                SET is_active = FALSE 
                WHERE id = :session_id
            ");
            $stmt->bindParam(':session_id', $sessionId, PDO::PARAM_INT);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Invalidate session failed: " . $e->getMessage());
            return false;
        }
    }

    // Get all tasks (admin view)
    public function getAllTasks(): array
    {
        $query = $this->database->connect()->prepare("
            SELECT 
                t.id,
                t.task_name,
                t.description,
                t.due_date,
                p.name as priority,
                s.name as status,
                u.firstname || ' ' || u.lastname as created_by,
                t.created_at,
                COUNT(ta.user_id) as assigned_to_count
            FROM tasks t
            LEFT JOIN priorities p ON t.priority_id = p.id
            INNER JOIN statuses s ON t.status_id = s.id
            INNER JOIN users u ON t.created_by = u.id
            LEFT JOIN task_assignments ta ON t.id = ta.task_id
            GROUP BY t.id, p.name, s.name, u.firstname, u.lastname
            ORDER BY t.created_at DESC
        ");
        
        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    // Update user role
    public function updateUserRole(int $userId, string $role, int $adminId): bool
    {
        $conn = $this->database->connect();
        
        try {
            $conn->beginTransaction();
            
            // Verify admin
            $adminCheck = $conn->prepare("
                SELECT role FROM users WHERE id = :admin_id
            ");
            $adminCheck->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
            $adminCheck->execute();
            $admin = $adminCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$admin || $admin['role'] !== 'admin') {
                $conn->rollBack();
                return false;
            }
            
            // Don't allow changing own role
            if ($userId === $adminId) {
                $conn->rollBack();
                return false;
            }
            
            // Validate role
            if (!in_array($role, ['user', 'admin'])) {
                $conn->rollBack();
                return false;
            }
            
            $stmt = $conn->prepare("
                UPDATE users 
                SET role = :role 
                WHERE id = :user_id
            ");
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $success = $stmt->execute();
            
            $conn->commit();
            return $success;
            
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Update user role failed: " . $e->getMessage());
            return false;
        }
    }
}