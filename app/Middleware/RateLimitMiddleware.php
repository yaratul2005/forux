<?php

namespace App\Middleware;

use Core\MiddlewareInterface;
use Core\Request;
use Core\Response;
use Core\Container;

/**
 * Middleware to throttle request rates per IP and endpoint.
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        // Bypass if not installed yet
        if (!file_exists(ROOT_PATH . '/storage/installed.lock')) {
            return $next($request);
        }

        $ip = $request->ip();
        $uri = $request->getUri();
        
        // Determine if request targets a sensitive endpoint
        $isSensitive = $this->isSensitiveEndpoint($uri);
        
        $limit = $isSensitive ? 10 : 60; // Max requests
        $window = 60; // 1 minute window

        $rateLimitsDir = ROOT_PATH . '/storage/logs/rate_limits';
        if (!is_dir($rateLimitsDir)) {
            mkdir($rateLimitsDir, 0755, true);
        }

        // Generate file path for this IP and endpoint category
        $endpointKey = $isSensitive ? 'sensitive' : 'global';
        $limitFile = $rateLimitsDir . '/rate_' . md5($ip . '_' . $endpointKey) . '.json';

        $now = time();
        $data = ['hits' => 0, 'window_start' => $now];

        if (file_exists($limitFile)) {
            $fileData = json_decode(file_get_contents($limitFile), true);
            if ($fileData && $fileData['window_start'] + $window > $now) {
                $data = $fileData;
            }
        }

        $data['hits']++;

        // Save updated hits
        file_put_contents($limitFile, json_encode($data));

        if ($data['hits'] > $limit) {
            // Log rate limit violation
            $logFile = ROOT_PATH . '/storage/logs/security.log';
            $timestamp = date('Y-m-d H:i:s');
            $msg = "[{$timestamp}] RATE LIMIT BLOCKED IP: {$ip} on URI: {$uri} (Hits: {$data['hits']}/{$limit})\n";
            file_put_contents($logFile, $msg, FILE_APPEND);

            $retryAfter = ($data['window_start'] + $window) - $now;
            if ($retryAfter < 1) $retryAfter = 1;

            $response = new Response();
            $response->setHeader('Retry-After', (string)$retryAfter);

            if ($request->isAjax()) {
                return Response::json(['error' => 'Too many requests. Please wait ' . $retryAfter . ' seconds.'], 429);
            }

            $html = "<div style='text-align:center; padding:100px 20px; font-family:system-ui, sans-serif; background:#0b0f19; color:#f3f4f6; min-height:100vh; box-sizing:border-box;'>";
            $html .= "<h1 style='font-size:3rem; color:#ef4444; margin:0;'>429</h1>";
            $html .= "<h2 style='font-weight:600; margin-top:0.5rem;'>Too Many Requests</h2>";
            $html .= "<p style='color:#9ca3af; max-width:400px; margin:1rem auto; line-height:1.5;'>You have made too many requests in a short period. Please wait <strong>{$retryAfter}</strong> seconds before trying again.</p>";
            $html .= "</div>";
            return Response::html($html, 429);
        }

        return $next($request);
    }

    protected function isSensitiveEndpoint(string $uri): bool
    {
        $uri = '/' . trim($uri, '/');
        
        // Sensitive routes
        $sensitivePatterns = [
            '#/login$#i',
            '#/register$#i',
            '#/thread/new/\d+$#i',
            '#/post/reply/\d+$#i',
            '#/post/edit/\d+$#i',
            '#/post/react/\d+$#i',
        ];

        foreach ($sensitivePatterns as $pattern) {
            if (preg_match($pattern, $uri)) {
                return true;
            }
        }

        // Check custom admin path prefix
        $container = Container::getInstance();
        $config = $container->has('config') ? $container->get('config') : [];
        $adminPath = '/' . trim($config['app']['admin_path'] ?? 'admin', '/');

        if (str_starts_with($uri, $adminPath)) {
            return true;
        }

        return false;
    }
}
