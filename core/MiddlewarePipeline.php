<?php

namespace Core;

use Closure;
use Exception;

/**
 * Onion-style Middleware Pipeline Runner
 */
class MiddlewarePipeline
{
    protected Container $container;
    protected array $middleware = [];
    protected Closure $destination;

    /**
     * Create a new Pipeline instance.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Set the middleware stack.
     *
     * @param array $middleware
     * @return $this
     */
    public function setMiddleware(array $middleware): self
    {
        $this->middleware = $middleware;
        return $this;
    }

    /**
     * Set the destination handler (core request processor).
     *
     * @param Closure $destination
     * @return $this
     */
    public function setDestination(Closure $destination): self
    {
        $this->destination = $destination;
        return $this;
    }

    /**
     * Run the pipeline through the middleware stack and return the Response.
     *
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function run(Request $request): Response
    {
        $pipeline = $this->destination;

        // Traverse the middleware stack in reverse order to construct the "onion" layers
        foreach (array_reverse($this->middleware) as $m) {
            $pipeline = function (Request $req) use ($m, $pipeline) {
                // If middleware is a class name, resolve it using the DI container
                if (is_string($m)) {
                    $m = $this->container->get($m);
                }

                if ($m instanceof MiddlewareInterface) {
                    return $m->handle($req, $pipeline);
                }

                if (is_callable($m)) {
                    return $m($req, $pipeline);
                }

                throw new Exception("Invalid middleware type. Must implement MiddlewareInterface or be callable.");
            };
        }

        return $pipeline($request);
    }
}
