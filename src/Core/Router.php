<?php
namespace Terrena\Core;

use Terrena\Core\Auth;

class Router
{
    private array $routesGet = [];
    private array $routesPost = [];

    public function get(string $path, $handler, ?string $requiredPerm = null): void
    {
        $this->routesGet[$path] = ['handler' => $handler, 'perm' => $requiredPerm];
    }

    public function post(string $path, $handler, ?string $requiredPerm = null): void
    {
        $this->routesPost[$path] = ['handler' => $handler, 'perm' => $requiredPerm];
    }

    public function run(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $path = ($base && $base !== '/') ? preg_replace('#^' . preg_quote($base, '#') . '#', '', $uri) : $uri;
        $path = $path ?: '/';

        $routes = $method === 'POST' ? $this->routesPost : $this->routesGet;
        $route = $routes[$path] ?? null;

        if (!$route) {
            http_response_code(404);
            echo '404 - Página no encontrada';
            return;
        }

        $perm = $route['perm'] ?? null;
        if ($perm && !Auth::can($perm)) {
            http_response_code(403);
            echo '403 - Acceso denegado';
            return;
        }

        $handler = $route['handler'];
        if (is_callable($handler)) {
            $handler();
        } elseif (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            (new $class())->$method();
        } else {
            require $handler;
        }
    }
}