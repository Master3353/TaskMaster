<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';

class SecurityController extends AppController
{

    private $userRepository;

    public function __construct()
    {
        //BINGO D1 - singleton UserRepository
        $this->userRepository = UserRepository::getInstance();
    }

    public function login()
    {

        if (!$this->isPost()) {
            return $this->render("login");
        }

        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        //BINGO C1 - validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->render('login', ['messages' => 'Invalid email format']);
        }

        $user = $this->userRepository->getUserByEmail($email);

        //BINGO B1 - combine password and email message
        if (!$user || !password_verify($password, $user['password'])) {
            return $this->render("login", [
                'messages' => 'Invalid email or password'
            ]);
        }

        // TODO create user session, cookie, token

        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/dashboard");
    }

    public function register()
    {

        if ($this->isGet()) {
            return $this->render("register");
        }

        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        $firstname = $_POST['firstName'] ?? '';
        $lastname = $_POST['lastName'] ?? '';

        if (empty($email) || empty($password) || empty($firstname) || empty($lastname)) {
            return $this->render('register', ['messages' => 'Fill all fields']);
        }

        if ($password !== $password2) {
            return $this->render("register", ["messages" => "Passwords should be the same!"]);
        }

        if ($this->userRepository->getUserByEmail($email)) {
            return $this->render("register", ["messages" => "User with this email already exists!"]);
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $this->userRepository->createUser(
            $email,
            $hashedPassword,
            $firstname,
            $lastname
        );
        return $this->render("login", ["messages" => "User registered successfully. Please login!"]);
    }
}