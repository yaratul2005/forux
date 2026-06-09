<?php

namespace Core;

use Exception;

/**
 * Regex-based HTTP Router supporting named parameters and groups
 */
class Router
{
    protected array $routes = [];
    protected string $groupPrefix = '';
    protected array $groupMiddleware = [];

    /**
     * Register a route with the router.
     *
     * @param string $method
     * @param string $path
     * @param mixed $handler Controller/Action callable or closure
     * @param array $middleware Route-specific middleware
     * @return $this
     */
    public function addRoute(string $method, string $path, $handler, array $middleware = []): self
    {
        // Prepends active group prefix and merges group middleware
        $path = $this->groupPrefix . '/' . trim($path, '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');
        $middleware = array_merge($this->groupMiddleware, $middleware);

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
        ];

        return $this;
    }

    /**
     * Register a GET route.
     */
    public function get(string $path, $handler, array $middleware = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * Register a POST route.
     */
    public function post(string $path, $handler, array $middleware = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * Register a PUT route.
     */
    public function put(string $path, $handler, array $middleware = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * Register a DELETE route.
     */
    public function delete(string $path, $handler, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * Register a PATCH route.
     */
    public function patch(string $path, $handler, array $middleware = []): self
    {
        return $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    /**
     * Create a route group with shared attributes (prefix, middleware).
     *
     * @param array $attributes ['prefix' => '/admin', 'middleware' => [...]]
     * @param callable $callback
     * @return void
     */
    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        if (isset($attributes['prefix'])) {
            $this->groupPrefix = $previousPrefix . '/' . trim($attributes['prefix'], '/');
        }

        if (isset($attributes['middleware'])) {
            $this->groupMiddleware = array_merge($previousMiddleware, (array)$attributes['middleware']);
        }

        $callback($this);

        // Restore previous group attributes (supports nested groups)
        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /**
     * Match the incoming request against registered routes.
     *
     * @param string $method
     * @param string $uri
     * @return array Matches containing handler, middleware, and route params
     * @throws Exception if no route is found (404) or method is not allowed (405)
     */
    public function match(string $method, string $uri): array
    {
        $uri = $uri === '/' ? '/' : rtrim($uri, '/');
        $method = strtoupper($method);
        $methodAllowed = false;

        foreach ($this->routes as $route) {
            // Convert route placeholders to regex. e.g. {id:\d+} -> (?P<id>\d+) or {name} -> (?P<name>[^/]+)
            $pattern = preg_replace_callback('/\{([a-zA-Z0-9_]+)(?::([^{}]+))?\}/', function ($matches) {
                $name = $matches[1];
                $regex = isset($matches[2]) ? $matches[2] : '[^/]+';
                return '(?P<' . $name . '>' . $regex . ')';
            }, $route['path']);

            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                if ($route['method'] === $method) {
                    // Extract named parameters from regex matches
                    $params = [];
                    foreach ($matches as $key => $value) {
                        if (is_string($key)) {
                            $params[$key] = urldecode($value);
                        }
                    }

                    return [
                        'handler' => $route['handler'],
                        'middleware' => $route['middleware'],
                        'params' => $params
                    ];
                }
                
                if ($route['method'] === 'GET' || $route['method'] === $method) {
                    $methodAllowed = true;
                }
            }
        }

        if ($methodAllowed) {
            throw new Exception("Method Not Allowed", 405);
        }

        throw new Exception("Route Not Found", 404);
    }
}
