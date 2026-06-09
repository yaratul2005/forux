<?php

namespace Core;

/**
 * HTTP Response Representation
 */
class Response
{
    protected string $content;
    protected int $statusCode;
    protected array $headers = [];

    /**
     * Map of HTTP status codes to phrases.
     */
    protected static array $statusTexts = [
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        503 => 'Service Unavailable',
    ];

    /**
     * Create a new Response instance.
     *
     * @param string $content
     * @param int $statusCode
     * @param array $headers
     */
    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Set the response content.
     *
     * @param string $content
     * @return $this
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Get the response content.
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Set the HTTP status code.
     *
     * @param int $statusCode
     * @return $this
     */
    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get all response headers.
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get a specific header.
     *
     * @param string $name
     * @return string|null
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Set a header.
     *
     * @param string $name
     * @param string $value
     * @return $this
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Set a cookie.
     *
     * @param string $name
     * @param string $value
     * @param int $expire
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httponly
     * @param string $samesite
     * @return $this
     */
    public function setCookie(
        string $name,
        string $value,
        int $expire = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httponly = true,
        string $samesite = 'Strict'
    ): self {
        // PHP 7.3+ supports samesite option array
        if (!headers_sent()) {
            setcookie($name, $value, [
                'expires' => $expire,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite
            ]);
        }
        return $this;
    }

    /**
     * Create a JSON response.
     *
     * @param mixed $data
     * @param int $statusCode
     * @param array $headers
     * @return static
     */
    public static function json($data, int $statusCode = 200, array $headers = []): self
    {
        $content = json_encode($data);
        $headers['Content-Type'] = 'application/json; charset=utf-8';
        return new static($content, $statusCode, $headers);
    }

    /**
     * Create a HTML response.
     *
     * @param string $html
     * @param int $statusCode
     * @param array $headers
     * @return static
     */
    public static function html(string $html, int $statusCode = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'text/html; charset=utf-8';
        return new static($html, $statusCode, $headers);
    }

    /**
     * Create a redirect response.
     *
     * @param string $url
     * @param int $statusCode
     * @param array $headers
     * @return static
     */
    public static function redirect(string $url, int $statusCode = 302, array $headers = []): self
    {
        $headers['Location'] = $url;
        return new static('', $statusCode, $headers);
    }

    /**
     * Send the response headers and content to the client.
     *
     * @return void
     */
    public function send(): void
    {
        // Don't send headers if they have already been sent (CLI testing / output leak)
        if (!headers_sent()) {
            // Send HTTP Status Line
            $statusText = self::$statusTexts[$this->statusCode] ?? 'Unknown Status';
            header("HTTP/1.1 {$this->statusCode} {$statusText}", true, $this->statusCode);

            // Send Headers
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}", true);
            }
        }

        // Output content
        echo $this->content;
    }
}
