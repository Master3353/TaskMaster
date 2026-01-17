<?php


class AppController
{

    public function __construct()
    {
        //BINGO E1 – HTTPS
        if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
            header('HTTP/1.1 403 Forbidden');
            echo "HTTPS required";
            exit;
        }
    }
    //BINGO A2 - allowMethods
    protected function allowMethods(array $methods): void
    {
        $currentMethod = $_SERVER['REQUEST_METHOD'];
        if (!in_array($currentMethod, $methods)) {
            header($_SERVER["SERVER_PROTOCOL"] . " 405 Method Not Allowed");
            echo "Method $currentMethod not allowed";
            exit;
        }
    }
    protected function isGet(): bool
    {
        return $_SERVER["REQUEST_METHOD"] === 'GET';
    }

    protected function isPost(): bool
    {
        return $_SERVER["REQUEST_METHOD"] === 'POST';
    }

    protected function render(string $template = null, array $variables = [])
    {
        $templatePath = 'public/views/' . $template . '.html';
        $templatePath404 = 'public/views/404.html';
        $output = "";

        if (file_exists($templatePath)) {
            // ["message" => "Błędne hasło!"]
            extract($variables);
            // $message = "Błędne hasło!"
            // echo $message

            ob_start();
            include $templatePath;
            $output = ob_get_clean();
        } else {
            ob_start();
            include $templatePath404;
            $output = ob_get_clean();
        }
        echo $output;
    }

}