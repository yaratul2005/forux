<?php

namespace Modules\Forum\Services;

use PDO;
use Exception;
use Core\HtmlSanitizer;
use Core\Hook;

/**
 * Service managing Forum Categories, Threads, Posts, and Reactions
 */
class ForumService
{
    protected PDO $pdo;
    protected HtmlSanitizer $sanitizer;
    protected Hook $hook;

    /**
     * Create a new ForumService instance.
     */
    public function __construct(PDO $pdo, HtmlSanitizer $sanitizer, Hook $hook)
    {
        $this->pdo = $pdo;
        $this->sanitizer = $sanitizer;
        $this->hook = $hook;
    }

    /**
     * Get categories in a nested tree structure.
     *
     * @return array
     */
    public function getCategoriesTree(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM categories ORDER BY sort_order ASC, name ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $categories = [];
        $tree = [];

        foreach ($rows as $row) {
            $row['children'] = [];
            $categories[$row['id']] = $row;
        }

        foreach ($categories as $id => &$category) {
            if ($category['parent_id'] === null) {
                $tree[] = &$category;
            } else {
                $parentId = $category['parent_id'];
                if (isset($categories[$parentId])) {
                    $categories[$parentId]['children'][] = &$category;
                } else {
                    $tree[] = &$category; // Fallback to root if parent is missing
                }
            }
        }

        return $tree;
    }

    /**
     * Get a category by its slug.
     */
    public function getCategoryBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM categories WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get threads by category, with pagination.
     */
    public function getThreadsByCategory(int $categoryId, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*, u.username as author_name, u.avatar_url as author_avatar,
                   (SELECT COUNT(*) FROM posts p WHERE p.thread_id = t.id AND p.deleted_at IS NULL) as replies_count,
                   COALESCE(last_p.created_at, t.created_at) as last_post_at,
                   last_u.username as last_post_author
            FROM threads t
            JOIN users u ON t.user_id = u.id
            LEFT JOIN posts last_p ON last_p.id = (
                SELECT MAX(id) FROM posts WHERE thread_id = t.id AND deleted_at IS NULL
            )
            LEFT JOIN users last_u ON last_p.user_id = last_u.id
            WHERE t.category_id = ? AND t.deleted_at IS NULL
            ORDER BY t.is_pinned DESC, last_post_at DESC
            LIMIT ? OFFSET ?
        ");
        
        // PDO needs explicit types when emulate prepares is false
        $stmt->bindValue(1, $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get total thread count in a category.
     */
    public function getThreadsCountByCategory(int $categoryId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM threads WHERE category_id = ? AND deleted_at IS NULL");
        $stmt->execute([$categoryId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get a thread by its slug.
     */
    public function getThreadBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*, c.name as category_name, c.slug as category_slug
            FROM threads t
            JOIN categories c ON t.category_id = c.id
            WHERE t.slug = ? AND t.deleted_at IS NULL
        ");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get posts for a thread, including users details and post reaction summaries.
     */
    public function getPostsByThread(int $threadId): array
    {
        // Query 1: Fetch all active posts in the thread
        $stmt = $this->pdo->prepare("
            SELECT p.*, u.username, u.email, u.avatar_url, u.bio, u.location, u.reputation_points
            FROM posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.thread_id = ? AND p.deleted_at IS NULL
            ORDER BY p.id ASC
        ");
        $stmt->execute([$threadId]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($posts)) {
            return [];
        }

        $postIds = array_column($posts, 'id');
        $inQuery = implode(',', array_fill(0, count($postIds), '?'));

        // Query 2: Fetch reaction counts for all these posts (max 2 queries design)
        $stmtReactions = $this->pdo->prepare("
            SELECT reactable_id, reaction_type, COUNT(*) as count
            FROM reactions
            WHERE reactable_type = 'post' AND reactable_id IN ($inQuery)
            GROUP BY reactable_id, reaction_type
        ");
        $stmtReactions->execute($postIds);
        $reactionsData = $stmtReactions->fetchAll(PDO::FETCH_ASSOC);

        // Group reactions by post ID
        $reactionsMap = [];
        foreach ($reactionsData as $reaction) {
            $reactionsMap[$reaction['reactable_id']][$reaction['reaction_type']] = (int)$reaction['count'];
        }

        // Merge reactions into posts
        foreach ($posts as &$post) {
            $post['reactions'] = $reactionsMap[$post['id']] ?? [];
        }

        return $posts;
    }

    /**
     * Create a new thread and its first post in a database transaction.
     *
     * @return string The thread slug
     * @throws Exception
     */
    public function createThread(int $categoryId, int $userId, string $title, string $body): string
    {
        $cleanTitle = trim(htmlspecialchars($title, ENT_QUOTES, 'UTF-8'));
        $cleanBody = $this->sanitizer->sanitize($body);

        if (empty($cleanTitle)) {
            throw new Exception("Thread title cannot be empty.");
        }
        if (empty($cleanBody)) {
            throw new Exception("Thread post content cannot be empty.");
        }

        $slug = $this->generateUniqueSlug($cleanTitle);

        $this->pdo->beginTransaction();
        try {
            // Insert thread
            $stmt = $this->pdo->prepare("
                INSERT INTO threads (category_id, user_id, title, slug) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$categoryId, $userId, $cleanTitle, $slug]);
            $threadId = $this->pdo->lastInsertId();

            // Insert first post
            $stmtPost = $this->pdo->prepare("
                INSERT INTO posts (thread_id, user_id, body, is_first_post) 
                VALUES (?, ?, ?, 1)
            ");
            $stmtPost->execute([$threadId, $userId, $cleanBody]);

            $this->pdo->commit();

            $this->hook->doAction('forum.thread_created', [
                'thread_id' => $threadId,
                'category_id' => $categoryId,
                'user_id' => $userId,
                'title' => $cleanTitle,
                'slug' => $slug
            ]);

            return $slug;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Create a reply post in a thread.
     */
    public function createReply(int $threadId, int $userId, string $body): int
    {
        $cleanBody = $this->sanitizer->sanitize($body);

        if (empty($cleanBody)) {
            throw new Exception("Reply content cannot be empty.");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO posts (thread_id, user_id, body, is_first_post) 
            VALUES (?, ?, ?, 0)
        ");
        $stmt->execute([$threadId, $userId, $cleanBody]);
        $replyId = (int)$this->pdo->lastInsertId();

        $this->hook->doAction('forum.reply_created', [
            'post_id' => $replyId,
            'thread_id' => $threadId,
            'user_id' => $userId,
            'body' => $cleanBody
        ]);

        return $replyId;
    }

    /**
     * Update an existing post and log revision history if content changed.
     */
    public function updatePost(int $postId, int $userId, string $body): bool
    {
        $cleanBody = $this->sanitizer->sanitize($body);

        if (empty($cleanBody)) {
            throw new Exception("Post content cannot be empty.");
        }

        // Get current post details
        $stmt = $this->pdo->prepare("SELECT user_id, body FROM posts WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$postId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$current) {
            throw new Exception("Post not found.");
        }

        // Verify if body actually changed
        if ($current['body'] === $cleanBody) {
            return true;
        }

        $this->pdo->beginTransaction();
        try {
            // Save old version in revisions
            $stmtRev = $this->pdo->prepare("
                INSERT INTO post_revisions (post_id, user_id, body_before) 
                VALUES (?, ?, ?)
            ");
            $stmtRev->execute([$postId, $userId, $current['body']]);

            // Update body
            $stmtUpdate = $this->pdo->prepare("UPDATE posts SET body = ? WHERE id = ?");
            $stmtUpdate->execute([$cleanBody, $postId]);

            $this->pdo->commit();

            $this->hook->doAction('forum.post_updated', [
                'post_id' => $postId,
                'user_id' => $userId,
                'body' => $cleanBody
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Soft-delete a post by setting deleted_at timestamp.
     */
    public function deletePost(int $postId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE posts SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
        $result = $stmt->execute([$postId]);
        if ($result) {
            $this->hook->doAction('forum.post_deleted', [
                'post_id' => $postId
            ]);
        }
        return $result;
    }

    /**
     * Toggle a user's reaction on a post.
     */
    public function toggleReaction(int $userId, int $postId, string $reactionType): void
    {
        // Validate reaction type
        $allowed = ['like', 'love', 'haha', 'sad', 'angry'];
        if (!in_array($reactionType, $allowed, true)) {
            throw new Exception("Invalid reaction type.");
        }

        // Check for existing reaction
        $stmt = $this->pdo->prepare("
            SELECT id, reaction_type FROM reactions 
            WHERE user_id = ? AND reactable_type = 'post' AND reactable_id = ?
        ");
        $stmt->execute([$userId, $postId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if ($existing['reaction_type'] === $reactionType) {
                // If the same type, delete/remove reaction (Toggle off)
                $delete = $this->pdo->prepare("DELETE FROM reactions WHERE id = ?");
                $delete->execute([$existing['id']]);
            } else {
                // If different type, update reaction
                $update = $this->pdo->prepare("UPDATE reactions SET reaction_type = ? WHERE id = ?");
                $update->execute([$reactionType, $existing['id']]);
            }
        } else {
            // Insert reaction
            $insert = $this->pdo->prepare("
                INSERT INTO reactions (user_id, reactable_type, reactable_id, reaction_type) 
                VALUES (?, 'post', ?, ?)
            ");
            $insert->execute([$userId, $postId, $reactionType]);
        }
    }

    /**
     * Increment views count on thread.
     */
    public function incrementThreadViews(int $threadId): void
    {
        $stmt = $this->pdo->prepare("UPDATE threads SET views_count = views_count + 1 WHERE id = ?");
        $stmt->execute([$threadId]);
    }

    /**
     * Helper to generate a unique URL slug from a title string.
     */
    protected function generateUniqueSlug(string $title): string
    {
        $slug = $this->slugify($title);
        
        $stmt = $this->pdo->prepare("SELECT id FROM threads WHERE slug = ?");
        $stmt->execute([$slug]);
        
        if ($stmt->fetch()) {
            // Append random 4-character suffix if duplicate
            $slug .= '-' . bin2hex(random_bytes(2));
        }

        return $slug;
    }

    /**
     * Standard slugify helper.
     */
    protected function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        if (function_exists('iconv')) {
            $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        }
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        
        return empty($text) ? 'thread' : $text;
    }
}
