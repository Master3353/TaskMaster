<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/TaskRepository.php';
require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../repository/SessionManager.php';

class TaskController extends AppController
{
    private $taskRepository;
    private $userRepository;
    private $sessionManager;

    public function __construct()
    {
        parent::__construct();
        $this->taskRepository = TaskRepository::getInstance();
        $this->userRepository = UserRepository::getInstance();
        $this->sessionManager = SessionManager::getInstance();
    }

    // List all user tasks
    public function index()
    {
        $this->allowMethods(['GET']);
        
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            return $this->redirectToLogin();
        }

        $tasks = $this->taskRepository->getUserTasks($user['id']);
        $stats = $this->taskRepository->getUserTaskStats($user['id']);
        $priorities = $this->taskRepository->getPriorities();
        $statuses = $this->taskRepository->getStatuses();

        return $this->render('tasks/index', [
            'user' => $user,
            'tasks' => $tasks,
            'stats' => $stats,
            'priorities' => $priorities,
            'statuses' => $statuses
        ]);
    }

    // Show create task form
    public function create()
    {
        $this->allowMethods(['GET']);
        
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            return $this->redirectToLogin();
        }

        $priorities = $this->taskRepository->getPriorities();
        $statuses = $this->taskRepository->getStatuses();
        $users = $this->userRepository->getUsers();

        return $this->render('tasks/create', [
            'user' => $user,
            'priorities' => $priorities,
            'statuses' => $statuses,
            'users' => $users
        ]);
    }

    // Store new task
    public function store()
    {
        $this->allowMethods(['POST']);
        
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            return $this->redirectToLogin();
        }

        $taskName = $_POST['task_name'] ?? '';
        $description = $_POST['description'] ?? '';
        $dueDate = $_POST['due_date'] ?? null;
        $priorityId = !empty($_POST['priority_id']) ? (int)$_POST['priority_id'] : null;
        $statusId = !empty($_POST['status_id']) ? (int)$_POST['status_id'] : 1;
        $assignToUsers = $_POST['assign_to'] ?? [];

        // Validation
        if (empty($taskName)) {
            return $this->render('tasks/create', [
                'messages' => 'Task name is required',
                'user' => $user
            ]);
        }

        if (strlen($taskName) > 200) {
            return $this->render('tasks/create', [
                'messages' => 'Task name too long (max 200 characters)',
                'user' => $user
            ]);
        }

        // Always assign to creator if not in list
        if (!in_array($user['id'], $assignToUsers)) {
            $assignToUsers[] = $user['id'];
        }

        $taskId = $this->taskRepository->createTask(
            $taskName,
            $description,
            $dueDate,
            $priorityId,
            $statusId,
            $user['id'],
            $assignToUsers
        );

        if ($taskId) {
            header("Location: /tasks");
            exit;
        } else {
            return $this->render('tasks/create', [
                'messages' => 'Failed to create task',
                'user' => $user
            ]);
        }
    }

    // Show edit task form
    public function edit($id)
    {
        $this->allowMethods(['GET']);
        
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            return $this->redirectToLogin();
        }

        $task = $this->taskRepository->getTaskById($id, $user['id']);
        
        if (!$task) {
            return $this->render('404');
        }

        $priorities = $this->taskRepository->getPriorities();
        $statuses = $this->taskRepository->getStatuses();

        return $this->render('tasks/edit', [
            'user' => $user,
            'task' => $task,
            'priorities' => $priorities,
            'statuses' => $statuses
        ]);
    }

    // Update task
    public function update($id)
    {
        $this->allowMethods(['POST']);
        
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            return $this->redirectToLogin();
        }

        $taskName = $_POST['task_name'] ?? '';
        $description = $_POST['description'] ?? '';
        $dueDate = $_POST['due_date'] ?? null;
        $priorityId = !empty($_POST['priority_id']) ? (int)$_POST['priority_id'] : null;
        $statusId = !empty($_POST['status_id']) ? (int)$_POST['status_id'] : 1;

        if (empty($taskName)) {
            return $this->render('tasks/edit', [
                'messages' => 'Task name is required',
                'user' => $user,
                'task' => $this->taskRepository->getTaskById($id, $user['id'])
            ]);
        }

        $success = $this->taskRepository->updateTask(
            $id,
            $user['id'],
            $taskName,
            $description,
            $dueDate,
            $priorityId,
            $statusId
        );

        if ($success) {
            header("Location: /tasks");
            exit;
        } else {
            return $this->render('tasks/edit', [
                'messages' => 'Failed to update task',
                'user' => $user,
                'task' => $this->taskRepository->getTaskById($id, $user['id'])
            ]);
        }
    }

    // Delete task
    public function delete($id)
    {
        $this->allowMethods(['POST']);
        
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            return $this->redirectToLogin();
        }

        $success = $this->taskRepository->deleteTask($id, $user['id']);

        header("Location: /tasks");
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
}