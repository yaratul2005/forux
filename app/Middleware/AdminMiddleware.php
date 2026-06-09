<?php

namespace App\Middleware;

use Core\MiddlewareInterface;
use Core\Request;
use Core\Response;
use Modules\Auth\Services\AuthService;
use PDO;

/**
 * Middleware to restrict route access to Administrators and Super Administrators.
 */
class AdminMiddleware implements MiddlewareInterface
{
    protected AuthService $auth;
    protected PDO $pdo;

    /**
     * Create a new AdminMiddleware instance.
     *
     * @param AuthService $auth Automatically resolved Auth service
     * @param PDO $pdo Automatically resolved PDO connection
     */
    public function __construct(AuthService $auth, PDO $pdo)
    {
        $this->auth = $auth;
        $this->pdo = $pdo;
    }

    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, callable $next): Response
    {
        // 1. Check if user is authenticated
        if (!$this->auth->check()) {
            // Redirect guests to the login page
            return Response::redirect('/login');
        }

        $user = $this->auth->user();

        // 2. Fetch all role names associated with the authenticated user
        try {
            $stmt = $this->pdo->prepare("
                SELECT r.name FROM user_roles ur
                JOIN roles r ON ur.role_id = r.id
                WHERE ur.user_id = ?
            ");
            $stmt->execute([$user['id']]);
            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

            // 3. Determine if the user holds an Admin or Super Admin role
            $isAdmin = in_array('Admin', $roles) || in_array('Super Admin', $roles);

            if ($isAdmin) {
                return $next($request);
            }
        } catch (\Throwable $e) {
            // Fail closed in case of database errors
        }

        // Return a clean, styled 403 Forbidden page
        $html = "<div style='text-align:center; padding:100px 20px; font-family:system-ui, sans-serif; background:#0b0f19; color:#f3f4f6; min-height:100vh; box-sizing:border-box;'>";
        $html .= "<h1 style='font-size:3rem; color:#ef4444; margin:0;'>403</h1>";
        $html .= "<h2 style='font-weight:600; margin-top:0.5rem;'>Access Denied</h2>";
        $html .= "<p style='color:#9ca3af; max-width:400px; margin:1rem auto; line-height:1.5;'>You do not have the administrator permissions required to access this area.</p>";
        $html .= "<a href='/' style='color:#10b981; text-decoration:none; font-weight:600; border:1px solid #10b981; padding:0.5rem 1.5rem; border-radius:6px; display:inline-block; margin-top:1.5rem; transition: background 0.2s;'>Return to Forum</a>";
        $html .= "</div>";

        return Response::html($html, 403);
    }
}
