<?php

namespace Modules\Notifications\Controllers;

use Core\Response;
use Core\Request;
use Modules\Auth\Services\AuthService;
use PDO;

/**
 * Controller to handle notification-related AJAX calls
 */
class NotificationController
{
    protected AuthService $auth;
    protected PDO $pdo;
    protected Request $request;

    /**
     * Create a new NotificationController instance.
     */
    public function __construct(AuthService $auth, PDO $pdo, Request $request)
    {
        $this->auth = $auth;
        $this->pdo = $pdo;
        $this->request = $request;
    }

    /**
     * Get count of unread notifications for currently logged in user.
     */
    public function getUnreadCount(): Response
    {
        if (!$this->auth->check()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $user = $this->auth->user();

        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM notifications 
                WHERE notifiable_id = ? AND read_at IS NULL
            ");
            $stmt->execute([$user['id']]);
            $count = (int)$stmt->fetchColumn();
            
            return Response::json([
                'count' => $count
            ]);
        } catch (\Throwable $e) {
            return Response::json(['error' => 'Database error'], 500);
        }
    }
}
