<?php

namespace Core;

/**
 * HTTP Request Representation
 */
class Request
{
    protected string $method;
    protected string $uri;
    protected array $query;
    protected array $post;
    protected array $cookies;
    protected array $files;
    protected array $server;
    protected array $headers;

    /**
     * Create a new Request instance.
     */
    public function __construct(
        array $query = [],
        array $post = [],
        array $cookies = [],
        array $files = [],
        array $server = []
    ) {
        $this->query = $this->sanitize($query);
        $this->post = $this->sanitize($post);
        $this->cookies = $cookies;
        $this->files = $files;
        $this->server = $server;
        
        $this->method = strtoupper($server['REQUEST_METHOD'] ?? 'GET');
        $this->headers = $this->extractHeaders();
        $this->uri = $this->detectUri();
    }

    /**
     * Capture the current HTTP request from globals.
     *
     * @return static
     */
    public static function capture(): self
    {
        return new static($_GET, $_POST, $_COOKIE, $_FILES, $_SERVER);
    }

    /**
     * Detect the relative request URI (path) for routing.
     * Handles subfolders automatically.
     *
     * @return string
     */
    protected function detectUri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';

        // Strip query string
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }

        // Get script path (e.g. /public/index.php or /index.php)
        $scriptName = $this->server['SCRIPT_NAME'] ?? '';
        $scriptDirectory = dirname($scriptName);

        // Clean up script directory path for comparison
        $scriptDirectory = str_replace('\\', '/', $scriptDirectory);
        if ($scriptDirectory === '/') {
            $scriptDirectory = '';
        }

        // If the request URI starts with the script directory, strip it
        if (!empty($scriptDirectory) && strpos($uri, $scriptDirectory) === 0) {
            $uri = substr($uri, strlen($scriptDirectory));
        }

        // Strip "index.php" from URI if present
        $baseScriptName = basename($scriptName);
        if (strpos($uri, '/' . $baseScriptName) === 0) {
            $uri = substr($uri, strlen('/' . $baseScriptName));
        }

        $uri = '/' . trim($uri, '/');

        return $uri;
    }

    /**
     * Extract HTTP headers from $_SERVER.
     *
     * @return array
     */
    protected function extractHeaders(): array
    {
        $headers = [];
        foreach ($this->server as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            } elseif ($key === 'CONTENT_TYPE') {
                $headers['Content-Type'] = $value;
            } elseif ($key === 'CONTENT_LENGTH') {
                $headers['Content-Length'] = $value;
            }
        }
        return $headers;
    }

    /**
     * Recursively sanitize input data to prevent XSS.
     *
     * @param mixed $data
     * @return mixed
     */
    protected function sanitize($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->sanitize($value);
            }
            return $data;
        }

        if (is_string($data)) {
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        }

        return $data;
    }

    /**
     * Get the HTTP request method.
     *
     * @return string
     */
    public function getMethod(): string
    {
        // Support method spoofing (e.g. via _method field in forms)
        if ($this->method === 'POST' && isset($this->post['_method'])) {
            return strtoupper($this->post['_method']);
        }
        return $this->method;
    }

    /**
     * Get the relative URI.
     *
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Get an input value from POST or GET.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function input(?string $key = null, $default = null)
    {
        if ($key === null) {
            return array_merge($this->query, $this->post);
        }

        if (isset($this->post[$key])) {
            return $this->post[$key];
        }

        if (isset($this->query[$key])) {
            return $this->query[$key];
        }

        return $default;
    }

    /**
     * Get all inputs.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->input();
    }

    /**
     * Get a cookie value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function cookie(string $key, $default = null)
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * Get a file upload array.
     *
     * @param string $key
     * @return array|null
     */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * Get a request header.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function header(string $key, $default = null)
    {
        // Case insensitive lookup
        $normalizedKey = strtolower($key);
        foreach ($this->headers as $name => $value) {
            if (strtolower($name) === $normalizedKey) {
                return $value;
            }
        }
        return $default;
    }

    /**
     * Check if the request is an AJAX request.
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        return strtolower($this->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    /**
     * Get the client IP address.
     *
     * @return string
     */
    public function ip(): string
    {
        return $this->server['HTTP_CLIENT_IP'] 
            ?? $this->server['HTTP_X_FORWARDED_FOR'] 
            ?? $this->server['REMOTE_ADDR'] 
            ?? '127.0.0.1';
    }
}
