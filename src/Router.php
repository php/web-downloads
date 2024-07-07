<?php

namespace App;

class Router
{
    private array $routes = [];
    private Auth $auth;

    public function __construct()
    {
        $this->auth = new Auth();
    }

    public function registerRoute(string $path, string $method, string $handler, bool $requiresAuth = false): void
    {
        $this->routes[$method][$path] = [
            'handler' => $handler,
            'requiresAuth' => $requiresAuth
        ];
    }

    public function handleRequest(): void
    {
        $path = $_SERVER['REQUEST_URI'];
        $method = $_SERVER['REQUEST_METHOD'];

        $allowedMethods = [];

        foreach ($this->routes as $registeredMethod => $paths) {
            if (isset($paths[$path])) {
                $allowedMethods[] = $registeredMethod;
            }
        }

        if (isset($this->routes[$method][$path])) {
            $route = $this->routes[$method][$path];

            if ($route['requiresAuth'] && !$this->auth->authenticate()) {
                http_response_code(401);
                echo 'Unauthorized';
                return;
            }

            $handler = $route['handler'];
            (new $handler)->handle();
        } elseif (!empty($allowedMethods)) {
            http_response_code(405);
            echo 'Method Not Allowed. Allowed methods: ' . implode(', ', $allowedMethods);
        } else {
            http_response_code(404);
            echo 'Not Found';
        }
    }
}
