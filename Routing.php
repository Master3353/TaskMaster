<?php

require_once 'src/controllers/SecurityController.php';


// TODO musimy zapewnic, ze utworzony 
// obiekt ma tylko jedna instancję - SINGLETON

// TODO 2 /dashboard -- wszystkei dnae
// /dashboard/12234 -- wyciagnie nam jakis elemtn o wskaznaym ID 12234
// REGEX
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
    ];

    public static function run(string $path)
    {
        switch ($path) {
            case 'dashboard':
                include 'public/views/dashboard.html';
                break;
            case 'login':
            case 'register':
                $controller = Routing::$routes[$path]["controller"];
                $action = Routing::$routes[$path]["action"];

                $controllerObj = new $controller;
                $id = null;

                $controllerObj->$action($id);
                break;
            default:
                include 'public/views/404.html';
                break;
        }
    }
}