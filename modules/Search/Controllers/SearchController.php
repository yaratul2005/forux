<?php

namespace Modules\Search\Controllers;

use Core\Response;
use Core\Request;
use Core\View;
use App\Services\Search\SearchServiceInterface;
use PDO;
use Throwable;

/**
 * Controller to handle public search queries
 */
class SearchController
{
    protected SearchServiceInterface $searchService;
    protected PDO $pdo;
    protected Request $request;

    /**
     * Create a new SearchController instance.
     */
    public function __construct(SearchServiceInterface $searchService, PDO $pdo, Request $request)
    {
        $this->searchService = $searchService;
        $this->pdo = $pdo;
        $this->request = $request;
    }

    /**
     * Render the search page and results.
     */
    public function index(): Response
    {
        $q = trim($this->request->input('q', ''));
        $categoryId = (int)$this->request->input('category_id', 0);
        $type = $this->request->input('type', 'threads'); // 'threads' or 'posts'

        if (!in_array($type, ['threads', 'posts'], true)) {
            $type = 'threads';
        }

        $results = [];
        $hasSearched = false;

        if (strlen($q) >= 2) {
            $hasSearched = true;
            $filters = [];
            if ($categoryId > 0) {
                $filters['category_id'] = $categoryId;
            }

            try {
                // Call Search service
                $ids = $this->searchService->search($type, $q, $filters);
                
                if (!empty($ids)) {
                    $inQuery = implode(',', array_fill(0, count($ids), '?'));
                    if ($type === 'threads') {
                        $stmt = $this->pdo->prepare("
                            SELECT t.*, u.username as author_name, u.avatar_url as author_avatar,
                                   c.name as category_name, c.slug as category_slug,
                                   (SELECT COUNT(*) FROM posts p WHERE p.thread_id = t.id AND p.deleted_at IS NULL) as replies_count
                            FROM threads t
                            JOIN users u ON t.user_id = u.id
                            JOIN categories c ON t.category_id = c.id
                            WHERE t.id IN ($inQuery) AND t.deleted_at IS NULL
                        ");
                        $stmt->execute($ids);
                        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        // Maintain the relevance sorting returned by search service
                        $idOrder = array_flip($ids);
                        usort($results, function($a, $b) use ($idOrder) {
                            return ($idOrder[$a['id']] ?? 0) - ($idOrder[$b['id']] ?? 0);
                        });
                    } else {
                        $stmt = $this->pdo->prepare("
                            SELECT p.*, u.username as author_name, u.avatar_url as author_avatar,
                                   t.title as thread_title, t.slug as thread_slug
                            FROM posts p
                            JOIN users u ON p.user_id = u.id
                            JOIN threads t ON p.thread_id = t.id
                            WHERE p.id IN ($inQuery) AND p.deleted_at IS NULL
                        ");
                        $stmt->execute($ids);
                        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        $idOrder = array_flip($ids);
                        usort($results, function($a, $b) use ($idOrder) {
                            return ($idOrder[$a['id']] ?? 0) - ($idOrder[$b['id']] ?? 0);
                        });
                    }
                }
            } catch (Throwable $e) {
                // Fail gracefully
            }
        }

        // Fetch categories list for filter dropdown
        $categories = [];
        try {
            $stmt = $this->pdo->query("SELECT id, name FROM categories ORDER BY sort_order ASC, name ASC");
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {}

        return View::render('search', [
            'q' => $q,
            'categoryId' => $categoryId,
            'type' => $type,
            'results' => $results,
            'hasSearched' => $hasSearched,
            'categories' => $categories,
            'title' => 'Search Results - Forux'
        ]);
    }
}
