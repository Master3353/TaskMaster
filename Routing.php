<?php

require_once 'src/controllers/SecurityController.php';
require_once 'src/controllers/TaskController.php';
require_once 'src/controllers/AdminController.php';

class Routing
{
    public static $routes = [
        "login" => [
            "controller" => "SecurityController",
            "action" => "login"
        ],
        "register" => [
            "controller" => "SecurityController",
            "action" => "register"
        ],
        "logout" => [
            "controller" => "SecurityController",
            "action" => "logout"
        ],
        "tasks" => [
            "controller" => "TaskController",
            "action" => "index"
        ],
        "tasks/create" => [
            "controller" => "TaskController",
            "action" => "create"
        ],
        "tasks/store" => [
            "controller" => "TaskController",
            "action" => "store"
        ],
        "admin" => [
            "controller" => "AdminController",
            "action" => "index"
        ],
        "admin/users" => [
            "controller" => "AdminController",
            "action" => "users"
        ],
    ];

    public static function run(string $path)
    {
        // Handle root path
        if (empty($path)) {
            header("Location: /login");
            exit;
        }

        // Handle dynamic routes with parameters
        // Pattern: tasks/edit/123 or tasks/delete/123
        if (preg_match('#^tasks/edit/(\d+)$#', $path, $matches)) {
            $controller = new TaskController();
            $controller->edit((int)$matches[1]);
            return;
        }

        if (preg_match('#^tasks/update/(\d+)$#', $path, $matches)) {
            $controller = new TaskController();
            $controller->update((int)$matches[1]);
            return;
        }

        if (preg_match('#^tasks/delete/(\d+)$#', $path, $matches)) {
            $controller = new TaskController();
            $controller->delete((int)$matches[1]);
            return;
        }

        // Admin routes with parameters
        if (preg_match('#^admin/users/view/(\d+)$#', $path, $matches)) {
            $controller = new AdminController();
            $controller->viewUser((int)$matches[1]);
            return;
        }

        if (preg_match('#^admin/users/toggle/(\d+)$#', $path, $matches)) {
            $controller = new AdminController();
            $controller->toggleUserStatus((int)$matches[1]);
            return;
        }

        if (preg_match('#^admin/users/delete/(\d+)$#', $path, $matches)) {
            $controller = new AdminController();
            $controller->deleteUser((int)$matches[1]);
            return;
        }

        if (preg_match('#^admin/users/role/(\d+)$#', $path, $matches)) {
            $controller = new AdminController();
            $controller->updateUserRole((int)$matches[1]);
            return;
        }

        if (preg_match('#^admin/sessions/invalidate/(\d+)$#', $path, $matches)) {
            $controller = new AdminController();
            $controller->invalidateSession((int)$matches[1]);
            return;
        }

        // Handle static routes
        if (isset(self::$routes[$path])) {
            $controller = self::$routes[$path]["controller"];
            $action = self::$routes[$path]["action"];

            $controllerObj = new $controller;
            $controllerObj->$action();
            return;
        }

        // Legacy dashboard route
        if ($path === 'dashboard') {
            header("Location: /tasks");
            exit;
        }

        // 404
        include 'public/views/404.html';
    }
}