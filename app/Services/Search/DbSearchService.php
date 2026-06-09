<?php

namespace App\Services\Search;

use PDO;

/**
 * Fallback Database Search Service using MySQL FULLTEXT MATCH/AGAINST
 */
class DbSearchService implements SearchServiceInterface
{
    protected PDO $pdo;

    /**
     * Create a new DbSearchService instance.
     *
     * @param PDO $pdo Automatically injected PDO database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Index a document (No-op for MySQL FULLTEXT since db is the source of truth).
     */
    public function index(string $index, string $id, array $document): bool
    {
        return true;
    }

    /**
     * Search the database using FULLTEXT indexes.
     */
    public function search(string $index, string $query, array $filters = []): array
    {
        // Whitelist the index table names to prevent SQL injection
        if (!in_array($index, ['threads', 'posts'])) {
            return [];
        }

        $matchColumn = $index === 'threads' ? 'title' : 'body';
        
        $sql = "SELECT id FROM {$index} WHERE MATCH({$matchColumn}) AGAINST(:query IN NATURAL LANGUAGE MODE) AND deleted_at IS NULL";
        $params = [':query' => $query];

        // Apply additional filters if present
        if (!empty($filters['category_id'])) {
            // category_id is only on threads, for posts we would join threads
            if ($index === 'threads') {
                $sql .= " AND category_id = :category_id";
                $params[':category_id'] = (int)$filters['category_id'];
            } elseif ($index === 'posts') {
                // Join threads to filter by category_id
                $sql = "SELECT p.id FROM posts p 
                        INNER JOIN threads t ON p.thread_id = t.id 
                        WHERE MATCH(p.body) AGAINST(:query IN NATURAL LANGUAGE MODE) 
                        AND p.deleted_at IS NULL 
                        AND t.category_id = :category_id";
                $params[':category_id'] = (int)$filters['category_id'];
            }
        }

        if (!empty($filters['user_id'])) {
            $sql .= " AND user_id = :user_id";
            $params[':user_id'] = (int)$filters['user_id'];
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (\PDOException $e) {
            // Log or handle error gracefully
            return [];
        }
    }

    /**
     * Delete a document (No-op for MySQL FULLTEXT since db is the source of truth).
     */
    public function delete(string $index, string $id): bool
    {
        return true;
    }
}
