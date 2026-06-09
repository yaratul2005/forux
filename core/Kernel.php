<?php

namespace Core;

use Exception;
use Throwable;

/**
 * Core Application Kernel
 */
class Kernel
{
    protected Container $container;
    protected array $middleware = []; // Global middleware stack
    protected array $config = [];

    /**
     * Initialize the Kernel.
     */
    public function __construct()
    {
        $this->container = new Container();
        $this->container->instance(Container::class, $this->container);

        $this->bootstrap();
    }

    /**
     * Bootstrap the application.
     */
    protected function bootstrap(): void
    {
        $this->loadConfiguration();
        $this->initializeErrorHandling();
        $this->registerCoreServices();
        $this->loadModules();
    }

    /**
     * Load environment and application configurations.
     */
    protected function loadConfiguration(): void
    {
        $configPath = ROOT_PATH . '/config/config.php';
        $examplePath = ROOT_PATH . '/config/config.example.php';

        if (file_exists($configPath)) {
            $this->config = require $configPath;
        } elseif (file_exists($examplePath)) {
            $this->config = require $examplePath;
        } else {
            $this->config = [];
        }

        $this->container->instance('config', $this->config);
    }

    /**
     * Set up custom error and exception handling.
     */
    protected function initializeErrorHandling(): void
    {
        error_reporting(E_ALL);

        // Convert PHP errors to ErrorExceptions
        set_error_handler(function ($level, $message, $file, $line) {
            if (error_reporting() & $level) {
                throw new \ErrorException($message, 0, $level, $file, $line);
            }
        });

        // Set global exception handler
        set_exception_handler([$this, 'handleException']);
    }

    /**
     * Register core services into the DI container.
     */
    protected function registerCoreServices(): void
    {
        // Bind the current request as a singleton
        $request = Request::capture();
        $this->container->instance(Request::class, $request);

        // Bind Hook system as a singleton
        $hook = new Hook();
        $this->container->instance(Hook::class, $hook);

        // Bind Router as a singleton
        $router = new Router();
        $this->container->instance(Router::class, $router);

        // Bind Kernel
        $this->container->instance(Kernel::class, $this);
    }

    /**
     * Load enabled modules and their routes/hooks.
     */
    protected function loadModules(): void
    {
        // TODO: In Phase 5, read database settings and dynamically bootstrap active modules.
        // For Phase 2, we will manually load any static module routes if they exist.
    }

    /**
     * Add a global middleware to the application.
     *
     * @param string|callable|MiddlewareInterface $middleware
     * @return $this
     */
    public function addMiddleware($middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Get the DI Container instance.
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Handle the incoming request, run the middleware pipeline, and send the response.
     */
    public function handle(): void
    {
        $request = $this->container->get(Request::class);
        $router = $this->container->get(Router::class);

        try {
            // Match route
            $route = $router->match($request->getMethod(), $request->getUri());

            // Prepare destination handler execution
            $destination = function (Request $req) use ($route) {
                return $this->dispatchToHandler($route['handler'], $route['params']);
            };

            // Run requests through global and route-specific middleware pipeline
            $pipeline = new MiddlewarePipeline($this->container);
            $response = $pipeline
                ->setMiddleware(array_merge($this->middleware, $route['middleware']))
                ->setDestination($destination)
                ->run($request);

        } catch (Throwable $e) {
            $response = $this->handleExceptionResponse($e);
        }

        // Send response back to browser
        $response->send();
    }

    /**
     * Dispatch matching route parameters to the handler.
     *
     * @param mixed $handler
     * @param array $params
     * @return Response
     * @throws Exception
     */
    protected function dispatchToHandler($handler, array $params): Response
    {
        // If the handler is a closure/callable
        if (is_callable($handler) && !is_array($handler)) {
            $response = $handler(...array_values($params));
        } elseif (is_array($handler) && count($handler) === 2) {
            // Resolved from DI container (supports controller constructor DI)
            $controller = $this->container->get($handler[0]);
            $method = $handler[1];

            if (!method_exists($controller, $method)) {
                throw new Exception("Method [{$method}] not found on controller [" . get_class($controller) . "]");
            }

            $response = $controller->$method(...array_values($params));
        } else {
            throw new Exception("Invalid route handler registered.");
        }

        // Normalize string responses to HTML Responses
        if (is_string($response)) {
            return Response::html($response);
        }

        if ($response instanceof Response) {
            return $response;
        }

        throw new Exception("Route handler must return a Response instance or a string.");
    }

    /**
     * Generate error responses for caught exceptions.
     *
     * @param Throwable $e
     * @return Response
     */
    protected function handleExceptionResponse(Throwable $e): Response
    {
        $code = $e->getCode();
        // Force standard HTTP status codes
        if (!in_array($code, [400, 401, 403, 404, 405, 429, 500, 503])) {
            $code = 500;
        }

        $debug = $this->config['app']['debug'] ?? false;

        $this->logException($e);

        if ($debug) {
            // Pretty debug exception viewer
            $html = "<h1>Exception: {$e->getMessage()}</h1>";
            $html .= "<p><strong>File:</strong> {$e->getFile()} (line {$e->getLine()})</p>";
            $html .= "<h3>Stack Trace:</h3><pre>{$e->getTraceAsString()}</pre>";
            return Response::html($html, $code);
        }

        // Simple production error page
        $message = $code === 404 ? 'Page Not Found' : 'An error occurred. Please try again later.';
        $html = "<div style='text-align:center; padding:50px; font-family:sans-serif;'>";
        $html .= "<h1>Status {$code}</h1><p>{$message}</p></div>";
        return Response::html($html, $code);
    }

    /**
     * Log exception to storage logs.
     *
     * @param Throwable $e
     */
    protected function logException(Throwable $e): void
    {
        $logDir = ROOT_PATH . '/storage/logs';
        if (!is_dir($logDir)) {
            return;
        }

        $logFile = $logDir . '/error.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$e->getCode()}] Exception: {$e->getMessage()} in {$e->getFile()} on line {$e->getLine()}\n";
        $logMessage .= "Stack Trace:\n{$e->getTraceAsString()}\n";
        $logMessage .= "----------------------------------------\n";

        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Fallback global exception handler.
     *
     * @param Throwable $e
     */
    public function handleException(Throwable $e): void
    {
        $response = $this->handleExceptionResponse($e);
        $response->send();
        exit(1);
    }
}
