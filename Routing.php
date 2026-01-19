<?php

require_once 'src/controllers/SecurityController.php';
require_once 'src/controllers/TaskController.php';
require_once 'src/controllers/AdminController.php';

class Routing
{
    private static array $routes = [
        'login' => ['SecurityController', 'login'],
        'register' => ['SecurityController', 'register'],
        'logout' => ['SecurityController', 'logout'],

        'tasks' => ['TaskController', 'index'],
        'tasks/create' => ['TaskController', 'create'],
        'tasks/store' => ['TaskController', 'store'],
        'tasks/edit/{id}' => ['TaskController', 'edit'],
        'tasks/update/{id}' => ['TaskController', 'update'],
        'tasks/delete/{id}' => ['TaskController', 'delete'],

        'admin' => ['AdminController', 'index'],
        'admin/users' => ['AdminController', 'users'],
        'admin/users/view/{id}' => ['AdminController', 'viewUser'],
        'admin/users/toggle/{id}' => ['AdminController', 'toggleUserStatus'],
        'admin/users/delete/{id}' => ['AdminController', 'deleteUser'],
        'admin/users/role/{id}' => ['AdminController', 'updateUserRole'],
        'admin/sessions/invalidate/{id}' => ['AdminController', 'invalidateSession'],
    ];

    public static function run(string $path)
    {
        if ($path === '') {
        header('Location: /login');
        exit;
    }

    foreach (self::$routes as $route => [$controller, $action]) {

        // Zamiana {id} → regex
        $pattern = preg_replace('#\{[a-zA-Z]+\}#', '(\d+)', $route);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $path, $matches)) {
            array_shift($matches); // usuń full match

            $controllerObj = new $controller();
            $controllerObj->$action(...$matches);
            return;
        }
    }

    if ($path === 'dashboard') {
        header('Location: /tasks');
        exit;
    }

    include 'public/views/404.html';
    }
}