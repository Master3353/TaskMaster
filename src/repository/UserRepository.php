<?php

require_once __DIR__ . '/Repository.php';

class UserRepository extends Repository
{

    //BINGO D1 - singleton UserRepository
    private static $instance;
    public static function getInstance()
    {
        return self::$instance ??= new UserRepository();
    }

    public function getUsers(): ?array
    {
         $query = $this->database->connect()->prepare(
            "
            SELECT u.id, u.firstname, u.lastname, u.email, u.enabled, u.role
            FROM users u
            WHERE u.enabled = TRUE
            ORDER BY u.firstname, u.lastname;
            "
        );
        $query->execute();

        $users = $query->fetchAll(PDO::FETCH_ASSOC);
        return $users;
    }

    public function getUserByEmail(string $email)
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT * FROM users WHERE email = :email
            "
        );
        $query->bindParam(':email', $email);
        $query->execute();

        $user = $query->fetch(PDO::FETCH_ASSOC);
        return $user;
    }

    public function createUser(
        string $email,
        string $hashedPassword,
        string $firstname,
        string $lastname,
        string $bio = ''
    ) {
        $conn = $this->database->connect();
        
        try {
            $conn->beginTransaction();
            $query = $this->database->connect()->prepare(
                "
                INSERT INTO users (firstname, lastname, email, password)
                VALUES (?, ?, ?, ?)
                RETURNING id;
                "
            );
            $query->execute([
                $firstname,
                $lastname,
                $email,
                $hashedPassword,
            ]);
            $result = $query->fetch(PDO::FETCH_ASSOC);
            $userId = $result['id'];
            
            // Create user profile
            $profileQuery = $conn->prepare(
                "
                INSERT INTO user_profiles (user_id, bio)
                VALUES (?, ?);
                "
            );
            $profileQuery->execute([
                $userId,
                $bio
            ]);
            
            $conn->commit();
            return $userId;
         } catch (PDOException $e) {
            $conn->rollBack();
            error_log("User creation failed: " . $e->getMessage());
            throw $e;
        }
    }
}