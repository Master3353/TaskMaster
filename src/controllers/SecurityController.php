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

        //BINGO A2 – GET and POST
        $this->allowMethods(['GET', 'POST']);

        if ($this->isGet()) {
            return $this->render("login");
        }
        if ($this->isPost()) {

            session_start();

            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';

            if (!isset($_SESSION['failures'])) {
                $_SESSION['failures'] = [];
            }

            if (!isset($_SESSION['failures'][$email])) {
                $_SESSION['failures'][$email] = 0;
            }
            //BINGO C1 - validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->render('login', ['messages' => 'Invalid email format']);
            }

            $user = $this->userRepository->getUserByEmail($email);

            //BINGO A4 – limit
            var_dump($_SESSION['failures'][$email]);
            if ($_SESSION['failures'][$email] >= 5) {
                sleep(15);
            }


            //BINGO B1 - combine password and email message
            if (!$user || !password_verify($password, $user['password'])) {
                $_SESSION['failures'][$email]++;
                return $this->render("login", [
                    'messages' => 'Invalid email or password'
                ]);
            }

            $_SESSION['failures'][$email] = 0;

            $_SESSION['user'] = [
                'id' => $user['id'],
                'email' => $user['email'],
                'firstname' => $user['firstname'],
                'lastname' => $user['lastname']
            ];

            $token = bin2hex(random_bytes(32));
            $_SESSION['token'] = $token;

            setcookie(
                'session_token',
                $token,
                [
                    'expires' => time() + 3600,  // 1 hour
                    'path' => '/',
                    'domain' => '',
                    'secure' => true,
                    'httponly' => true,          //BINGO C3 - Cookie HttpOnly
                    'samesite' => 'Strict'
                ]
            );

            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/dashboard");
            exit;
        }
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