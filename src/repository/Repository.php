<?php

require_once __DIR__ . "/../../Database.php";

class Repository
{

    protected $database;

    public function __construct()
    {
        $this->database = new Database();
    }

    public function getUser(string $email)
    {
        //BINGO A1 - SQL Injection 
        $stmt = $this->database->connect()->prepare('
            SELECT * FROM users u WHERE email = :email
        ');
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user == false) {
            return null;
        }

        return $user;
    }

    public function addUser($user)
    {
        $stmt = $this->database->connect()->prepare('
            INSERT INTO users (email, password, firstname, lastname)
            VALUES (?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $user['email'],
            $user['password'],
            $user['firstname'],
            $user['lastname']
        ]);
    }
}