<?php

namespace App\Middleware;

use Core\MiddlewareInterface;
use Core\Request;
use Core\Response;
use Modules\Auth\Services\AuthService;

/**
 * Middleware to protect state-changing endpoints against CSRF attacks.
 * Automatically injects CSRF inputs into outgoing HTML forms and meta tags.
 */
class CsrfMiddleware implements MiddlewareInterface
{
    protected AuthService $auth;

    public function __construct(AuthService $auth)
    {
        $this->auth = $auth;
    }

    public function handle(Request $request, callable $next): Response
    {
        // Bypass CSRF checks if not installed yet (e.g. during installation phase)
        if (!file_exists(ROOT_PATH . '/storage/installed.lock')) {
            return $next($request);
        }

        $csrfToken = $this->auth->getCsrfToken();

        // Validate state-changing requests
        $method = $request->getMethod();
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $submittedToken = $request->input('_token') ?: $request->header('X-CSRF-Token');
            
            if (!$submittedToken || !hash_equals($csrfToken, $submittedToken)) {
                // Log CSRF failure
                $logDir = ROOT_PATH . '/storage/logs';
                if (!is_dir($logDir)) {
                    mkdir($logDir, 0755, true);
                }
                $ip = $request->ip();
                $uri = $request->getUri();
                $timestamp = date('Y-m-d H:i:s');
                $msg = "[{$timestamp}] SECURITY WARNING: CSRF Token Validation Failed from IP: {$ip} on URI: {$uri}\n";
                file_put_contents($logDir . '/security.log', $msg, FILE_APPEND);

                if ($request->isAjax()) {
                    return Response::json(['error' => 'CSRF token mismatch. Please refresh the page.'], 403);
                }
                
                $html = "<div style='text-align:center; padding:100px 20px; font-family:system-ui, sans-serif; background:#0b0f19; color:#f3f4f6; min-height:100vh; box-sizing:border-box;'>";
                $html .= "<h1 style='font-size:3rem; color:#ef4444; margin:0;'>403</h1>";
                $html .= "<h2 style='font-weight:600; margin-top:0.5rem;'>CSRF Verification Failed</h2>";
                $html .= "<p style='color:#9ca3af; max-width:400px; margin:1rem auto; line-height:1.5;'>The session security token is invalid or has expired. Please reload the page and try again.</p>";
                $html .= "<a href='#' onclick='window.location.reload();' style='color:#10b981; text-decoration:none; font-weight:600; border:1px solid #10b981; padding:0.5rem 1.5rem; border-radius:6px; display:inline-block; margin-top:1.5rem;'>Reload Page</a>";
                $html .= "</div>";
                return Response::html($html, 403);
            }
        }

        // Get response
        $response = $next($request);

        // Inject tokens into text/html content
        $contentType = $response->getHeader('Content-Type') ?? '';
        if (str_contains(strtolower($contentType), 'text/html')) {
            $html = $response->getContent();

            // Inject meta tag in <head>
            if (str_contains($html, '</head>')) {
                $metaHtml = '    <meta name="csrf-token" content="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">' . "\n" . '</head>';
                $html = str_replace('</head>', $metaHtml, $html);
            }

            // Inject hidden inputs in post forms
            $html = preg_replace_callback('/<form\b[^>]*method=["\']post["\'][^>]*>/i', function ($matches) use ($csrfToken) {
                return $matches[0] . "\n" . '    <input type="hidden" name="_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">';
            }, $html);

            $response->setContent($html);
        }

        return $response;
    }
}
