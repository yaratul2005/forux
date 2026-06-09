<?php

namespace Modules\API\Controllers;

use Core\Response;
use Core\Request;
use PDO;
use Throwable;

/**
 * Controller to expose public RESTful endpoints
 */
class ApiController
{
    protected PDO $pdo;
    protected Request $request;

    /**
     * Create a new ApiController instance.
     */
    public function __construct(PDO $pdo, Request $request)
    {
        $this->pdo = $pdo;
        $this->request = $request;
    }

    /**
     * Get list of all categories in JSON.
     */
    public function categories(): Response
    {
        try {
            $stmt = $this->pdo->query("SELECT id, parent_id, name, slug, description, color, sort_order FROM categories ORDER BY sort_order ASC, name ASC");
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return Response::json($categories);
        } catch (Throwable $e) {
            return Response::json(['error' => 'Database error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get list of all active threads in JSON.
     */
    public function threads(): Response
    {
        try {
            $stmt = $this->pdo->query("
                SELECT t.id, t.category_id, t.user_id, t.title, t.slug, t.views_count, t.is_pinned, t.is_locked, t.created_at, t.updated_at,
                       u.username as author_name
                FROM threads t
                JOIN users u ON t.user_id = u.id
                WHERE t.deleted_at IS NULL
                ORDER BY t.is_pinned DESC, t.created_at DESC
            ");
            $threads = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return Response::json($threads);
        } catch (Throwable $e) {
            return Response::json(['error' => 'Database error', 'message' => $e->getMessage()], 500);
        }
    }
}
