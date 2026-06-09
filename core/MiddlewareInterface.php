<?php

namespace Core;

/**
 * Middleware Contract
 */
interface MiddlewareInterface
{
    /**
     * Handle an incoming HTTP request.
     *
     * @param Request $request
     * @param callable $next The next middleware/handler in the pipeline.
     * @return Response
     */
    public function handle(Request $request, callable $next): Response;
}
