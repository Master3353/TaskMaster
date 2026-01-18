<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../repository/SessionManager.php';

class SecurityController extends AppController
{

    private $userRepository;
    private $sessionManager;

    public function __construct()
    {
        //BINGO D1 - singleton UserRepository
        parent::__construct();
        $this->userRepository = UserRepository::getInstance();
        $this->sessionManager = SessionManager::getInstance();
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

            // Check if user is enabled
            if (!$user['enabled']) {
                return $this->render("login", [
                    'messages' => 'Your account has been disabled. Contact administrator.'
                ]);
            }

            $_SESSION['failures'][$email] = 0;

            $_SESSION['user'] = [
                'id' => $user['id'],
                'email' => $user['email'],
                'firstname' => $user['firstname'],
                'lastname' => $user['lastname'],
                'role' => $user['role']
            ];

            // Create session in database
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $token = $this->sessionManager->createSession($user['id'], $ipAddress, $userAgent);

            if (!$token) {
                return $this->render("login", [
                    'messages' => 'Failed to create session. Please try again.'
                ]);
            }

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
            if ($user['role'] === 'admin') {
                header("Location: {$url}/admin");
            } else {
                header("Location: {$url}/tasks");
            }            exit;
        }
    }

    public function register()
    {
        $this->allowMethods(['GET', 'POST']);
        if ($this->isGet()) {
            return $this->render("register");
        }
        if ($this->isPost()) {

            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $password2 = $_POST['password2'] ?? '';
            $firstname = $_POST['firstName'] ?? '';
            $lastname = $_POST['lastName'] ?? '';

            if (empty($email) || empty($password) || empty($firstname) || empty($lastname)) {
                return $this->render('register', ['messages' => 'Fill all fields']);
            }
            //BINGO D2 - limit imput length
            if (strlen($email) > 100 || strlen($password) > 100 || strlen($firstname) > 100 || strlen($lastname) > 100) {
                return $this->render('register', ['messages' => 'Fields too long']);
            }
            if (
                strlen($password) < 10 ||
                !preg_match('/[A-Z]/', $password) ||
                !preg_match('/[a-z]/', $password) ||
                !preg_match('/[0-9]/', $password)
            ) {
                return $this->render("register", ["messages" => "Passwords is weak (min  10 characters, upper/lower case, number)"]);
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
    public function logout()
    {
        $this->allowMethods(['GET', 'POST']);

        session_start();
        
        // Destroy database session
        $token = $_COOKIE['session_token'] ?? null;
        if ($token) {
            $this->sessionManager->destroySession($token);
        }

        // Clear session
        $_SESSION = [];
        session_destroy();

        // Clear cookie
        setcookie(
            'session_token',
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );

        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/login");
        exit;
    }
}