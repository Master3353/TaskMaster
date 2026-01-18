<?php

require_once __DIR__ . '/../../Database.php';

class SessionManager
{
    private static $instance;
    private $database;

    private function __construct()
    {
        $this->database = new Database();
    }

    public static function getInstance()
    {
        return self::$instance ??= new SessionManager();
    }

    // Create new session
    public function createSession(int $userId, string $ipAddress, string $userAgent): ?string
    {
        $conn = $this->database->connect();
        
        try {
            $conn->beginTransaction();
            
            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour
            
            // Insert session
            $stmt = $conn->prepare("
                INSERT INTO sessions (user_id, token, ip_address, user_agent, expires_at)
                VALUES (:user_id, :token, :ip_address, :user_agent, :expires_at)
            ");
            
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':ip_address', $ipAddress);
            $stmt->bindParam(':user_agent', $userAgent);
            $stmt->bindParam(':expires_at', $expiresAt);
            
            $stmt->execute();
            
            // Update last login
            $updateStmt = $conn->prepare("
                UPDATE users 
                SET last_login = CURRENT_TIMESTAMP 
                WHERE id = :user_id
            ");
            $updateStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $updateStmt->execute();
            
            $conn->commit();
            return $token;
            
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Session creation failed: " . $e->getMessage());
            return null;
        }
    }

    // Validate session and get user
    public function validateSession(string $token): ?array
    {
        $query = $this->database->connect()->prepare("
            SELECT 
                u.id,
                u.email,
                u.firstname,
                u.lastname,
                u.role,
                u.enabled,
                s.expires_at
            FROM sessions s
            INNER JOIN users u ON s.user_id = u.id
            WHERE s.token = :token 
              AND s.is_active = TRUE
              AND s.expires_at > CURRENT_TIMESTAMP
              AND u.enabled = TRUE
        ");
        
        $query->bindParam(':token', $token);
        $query->execute();
        
        $result = $query->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    // Destroy session
    public function destroySession(string $token): bool
    {
        try {
            $stmt = $this->database->connect()->prepare("
                UPDATE sessions 
                SET is_active = FALSE 
                WHERE token = :token
            ");
            
            $stmt->bindParam(':token', $token);
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Session destruction failed: " . $e->getMessage());
            return false;
        }
    }

    // Clean expired sessions (maintenance)
    public function cleanExpiredSessions(): int
    {
        try {
            $stmt = $this->database->connect()->prepare("
                UPDATE sessions 
                SET is_active = FALSE 
                WHERE expires_at < CURRENT_TIMESTAMP 
                  AND is_active = TRUE
            ");
            
            $stmt->execute();
            return $stmt->rowCount();
            
        } catch (PDOException $e) {
            error_log("Session cleanup failed: " . $e->getMessage());
            return 0;
        }
    }

    // Extend session
    public function extendSession(string $token): bool
    {
        try {
            $newExpiry = date('Y-m-d H:i:s', time() + 3600);
            
            $stmt = $this->database->connect()->prepare("
                UPDATE sessions 
                SET expires_at = :expires_at 
                WHERE token = :token 
                  AND is_active = TRUE
            ");
            
            $stmt->bindParam(':expires_at', $newExpiry);
            $stmt->bindParam(':token', $token);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Session extension failed: " . $e->getMessage());
            return false;
        }
    }

    // Get user ID from session token
    public function getUserIdFromToken(string $token): ?int
    {
        $user = $this->validateSession($token);
        return $user ? (int)$user['id'] : null;
    }
}