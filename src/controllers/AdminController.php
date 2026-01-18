<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/AdminRepository.php';
require_once __DIR__ . '/../repository/SessionManager.php';

class AdminController extends AppController
{
    private $adminRepository;
    private $sessionManager;

    public function __construct()
    {
        parent::__construct();
        $this->adminRepository = AdminRepository::getInstance();
        $this->sessionManager = SessionManager::getInstance();
    }

    // Admin dashboard
    public function index()
    {
        $this->allowMethods(['GET']);
        
        $user = $this->getAuthenticatedUser();
        if (!$user || $user['role'] !== 'admin') {
            return $this->redirectToLogin();
        }

        $stats = $this->adminRepository->getDashboardStats();
        $users = $this->adminRepository->getAllUsers();
        $tasks = $this->adminRepository->getAllTasks();

        return $this->render('admin/dashboard', [
            'user' => $user,
            'stats' => $stats,
            'users' => $users,
            'tasks' => $tasks
        ]);
    }

    // User management page
    public function users()
    {
        $this->allowMethods(['GET']);
        
        $user = $this->getAuthenticatedUser();
        if (!$user || $user['role'] !== 'admin') {
            return $this->redirectToLogin();
        }

        $users = $this->adminRepository->getAllUsers();

        return $this->render('admin/users', [
            'user' => $user,
            'users' => $users
        ]);
    }

    // View user details
    public function viewUser($id)
    {
        $this->allowMethods(['GET']);
        
        $user = $this->getAuthenticatedUser();
        if (!$user || $user['role'] !== 'admin') {
            return $this->redirectToLogin();
        }

        $targetUser = $this->adminRepository->getUserDetails($id);
        if (!$targetUser) {
            return $this->render('404');
        }

        $sessions = $this->adminRepository->getUserSessions($id);

        return $this->render('admin/user_details', [
            'user' => $user,
            'targetUser' => $targetUser,
            'sessions' => $sessions
        ]);
    }

    // Toggle user enabled status
    public function toggleUserStatus($id)
    {
        $this->allowMethods(['POST']);
        
        $user = $this->getAuthenticatedUser();
        if (!$user || $user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $success = $this->adminRepository->toggleUserStatus($id, $user['id']);

        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => $success]);
            exit;
        }

        header("Location: /admin/users");
        exit;
    }

    // Delete user
    public function deleteUser($id)
    {
        $this->allowMethods(['POST']);
        
        $user = $this->getAuthenticatedUser();
        if (!$user || $user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $result = $this->adminRepository->safeDeleteUser($id, $user['id']);

        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        }

        header("Location: /admin/users");
        exit;
    }

    // Update user role
    public function updateUserRole($id)
    {
        $this->allowMethods(['POST']);
        
        $user = $this->getAuthenticatedUser();
        if (!$user || $user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $newRole = $_POST['role'] ?? '';
        $success = $this->adminRepository->updateUserRole($id, $newRole, $user['id']);

        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => $success]);
            exit;
        }

        header("Location: /admin/users");
        exit;
    }

    // Invalidate session
    public function invalidateSession($sessionId)
    {
        $this->allowMethods(['POST']);
        
        $user = $this->getAuthenticatedUser();
        if (!$user || $user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $success = $this->adminRepository->invalidateSession($sessionId, $user['id']);

        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => $success]);
            exit;
        }

        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    // Get authenticated user from session
    private function getAuthenticatedUser(): ?array
    {
        $token = $_COOKIE['session_token'] ?? null;
        
        if (!$token) {
            return null;
        }

        return $this->sessionManager->validateSession($token);
    }

    // Redirect to login
    private function redirectToLogin()
    {
        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/login");
        exit;
    }

    // Check if request is AJAX
    private function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}