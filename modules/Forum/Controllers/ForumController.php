<?php

namespace Modules\Forum\Controllers;

use Core\Response;
use Core\Request;
use Modules\Forum\Services\ForumService;
use Modules\Auth\Services\AuthService;
use Throwable;

/**
 * Controller managing Forum HTTP requests
 */
class ForumController
{
    protected ForumService $forumService;
    protected AuthService $auth;
    protected Request $request;

    /**
     * Create a new ForumController instance.
     */
    public function __construct(ForumService $forumService, AuthService $auth, Request $request)
    {
        $this->forumService = $forumService;
        $this->auth = $auth;
        $this->request = $request;
    }

    /**
     * Homepage: List all categories and nested sub-categories.
     */
    public function index(): Response
    {
        $categoriesTree = $this->forumService->getCategoriesTree();
        
        $html = "<div class='forum-layout'>";
        $html .= "<div class='main-content'>";
        $html .= "<h2>Categories</h2>";

        if (empty($categoriesTree)) {
            $html .= "<div class='empty-state'>No categories found. Run database seed or configure categories in Admin.</div>";
        } else {
            foreach ($categoriesTree as $cat) {
                $html .= "<div class='category-group'>";
                $html .= "<div class='category-header' style='border-left: 4px solid {$cat['color']}'>";
                $html .= "<div>";
                $html .= "<h3 style='margin:0;'><a class='cat-link' href='category/{$cat['slug']}'>{$cat['name']}</a></h3>";
                $html .= "<p class='cat-desc'>{$cat['description']}</p>";
                $html .= "</div>";
                $html .= "</div>";

                if (!empty($cat['children'])) {
                    $html .= "<div class='subcategories-list'>";
                    foreach ($cat['children'] as $child) {
                        $html .= "<div class='subcategory-item'>";
                        $html .= "<span class='sub-icon' style='color: {$child['color']}'>🗁</span>";
                        $html .= "<div>";
                        $html .= "<h4 style='margin:0;'><a class='cat-link' href='category/{$child['slug']}'>{$child['name']}</a></h4>";
                        $html .= "<p class='cat-desc'>{$child['description']}</p>";
                        $html .= "</div>";
                        $html .= "</div>";
                    }
                    $html .= "</div>";
                }
                $html .= "</div>";
            }
        }
        $html .= "</div>"; // end main-content

        $html .= $this->renderSidebar();
        $html .= "</div>"; // end forum-layout

        return Response::html($this->renderLayout('Home - Forux Forum', $html));
    }

    /**
     * Category view: Display threads inside a category with pagination.
     */
    public function showCategory(string $slug): Response
    {
        $category = $this->forumService->getCategoryBySlug($slug);
        if (!$category) {
            return Response::html($this->renderLayout('Category Not Found', "<h2>404 Category Not Found</h2>"), 404);
        }

        $page = (int)$this->request->input('page', 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $threads = $this->forumService->getThreadsByCategory($category['id'], $limit, $offset);
        $totalThreads = $this->forumService->getThreadsCountByCategory($category['id']);
        $totalPages = ceil($totalThreads / $limit) ?: 1;

        $html = "<div class='forum-layout'>";
        $html .= "<div class='main-content'>";
        
        // Breadcrumb
        $html .= "<div class='breadcrumbs'><a href='../public/index.php'>Home</a> &raquo; {$category['name']}</div>";

        $html .= "<div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;'>";
        $html .= "<h2>{$category['name']}</h2>";
        if ($this->auth->check()) {
            $html .= "<a href='../public/thread/new/{$category['id']}' class='btn btn-small' style='width:auto;'>New Thread</a>";
        }
        $html .= "</div>";

        if (empty($threads)) {
            $html .= "<div class='empty-state'>No threads inside this category yet. Be the first to start a discussion!</div>";
        } else {
            $html .= "<div class='threads-list'>";
            foreach ($threads as $t) {
                $pinnedClass = $t['is_pinned'] ? 'pinned' : '';
                $lockedIcon = $t['is_locked'] ? ' 🔒' : '';
                $pinnedIcon = $t['is_pinned'] ? '📌 ' : '';

                $html .= "<div class='thread-row {$pinnedClass}'>";
                $html .= "<div style='flex:1;'>";
                $html .= "<h4 style='margin:0;'><a href='../public/thread/{$t['slug']}'>{$pinnedIcon}{$t['title']}</a>{$lockedIcon}</h4>";
                $html .= "<div style='font-size:0.8rem; color:var(--text-muted); margin-top:0.25rem;'>";
                $html .= "Started by @{$t['author_name']} &bull; " . date('M d, Y', strtotime($t['created_at']));
                $html .= "</div>";
                $html .= "</div>";

                $html .= "<div class='thread-stats'>";
                $html .= "<div><strong>{$t['replies_count']}</strong> replies</div>";
                $html .= "<div><strong>{$t['views_count']}</strong> views</div>";
                $html .= "</div>";

                if ($t['last_post_author']) {
                    $html .= "<div class='thread-last-post'>";
                    $html .= "<div>Last reply by @{$t['last_post_author']}</div>";
                    $html .= "<div style='font-size:0.75rem; color:var(--text-muted);'>" . date('M d, Y H:i', strtotime($t['last_post_at'])) . "</div>";
                    $html .= "</div>";
                }
                $html .= "</div>";
            }
            $html .= "</div>";

            // Pagination
            if ($totalPages > 1) {
                $html .= "<div class='pagination'>";
                for ($i = 1; $i <= $totalPages; $i++) {
                    $activeClass = $i === $page ? 'active' : '';
                    $html .= "<a href='?page={$i}' class='page-link {$activeClass}'>{$i}</a>";
                }
                $html .= "</div>";
            }
        }

        $html .= "</div>"; // end main-content
        $html .= $this->renderSidebar();
        $html .= "</div>"; // end forum-layout

        return Response::html($this->renderLayout($category['name'] . ' - Forux Forum', $html));
    }

    /**
     * Thread view: Display posts timeline.
     */
    public function showThread(string $slug): Response
    {
        $thread = $this->forumService->getThreadBySlug($slug);
        if (!$thread) {
            return Response::html($this->renderLayout('Thread Not Found', "<h2>404 Thread Not Found</h2>"), 404);
        }

        $this->forumService->incrementThreadViews($thread['id']);
        $posts = $this->forumService->getPostsByThread($thread['id']);
        $currentUser = $this->auth->user();

        $html = "<div class='forum-layout'>";
        $html .= "<div class='main-content' style='flex:1;'>";
        
        $html .= "<div class='breadcrumbs'><a href='../public/index.php'>Home</a> &raquo; <a href='../public/category/{$thread['category_slug']}'>{$thread['category_name']}</a> &raquo; {$thread['title']}</div>";

        $html .= "<div style='margin-bottom: 2rem;'>";
        $html .= "<h2 style='margin-bottom:0.25rem;'>{$thread['title']}</h2>";
        $html .= "</div>";

        $html .= "<div class='posts-timeline'>";
        foreach ($posts as $post) {
            $avatar = $post['avatar_url'] ?: 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($post['email']))) . '?d=mp&s=80';
            
            $html .= "<div class='post-card' id='post-{$post['id']}'>";
            
            // User column
            $html .= "<div class='post-user'>";
            $html .= "<img class='post-avatar' src='{$avatar}' alt='Avatar'>";
            $html .= "<div class='post-username'><a href='../public/user/{$post['username']}'>@{$post['username']}</a></div>";
            $html .= "<div class='post-reputation'>★ {$post['reputation_points']} pts</div>";
            $html .= "</div>";

            // Content column
            $html .= "<div class='post-content'>";
            $html .= "<div class='post-meta'>";
            $html .= "Posted " . date('M d, Y H:i', strtotime($post['created_at']));
            $html .= "</div>";
            
            $html .= "<div class='post-body' id='body-{$post['id']}'>{$post['body']}</div>";

            // Reactions list
            $html .= "<div class='reactions-bar'>";
            foreach ($post['reactions'] as $type => $count) {
                if ($count > 0) {
                    $emoji = $this->getReactionEmoji($type);
                    $html .= "<span class='reaction-pill'>{$emoji} {$count}</span>";
                }
            }
            $html .= "</div>";

            // Footer controls
            $html .= "<div class='post-footer'>";
            if ($currentUser) {
                // Reaction toggles
                $html .= "<div class='reaction-picker'>";
                $html .= "<button class='footer-action'>☺ React</button>";
                $html .= "<div class='reaction-dropdown'>";
                foreach (['like', 'love', 'haha', 'sad', 'angry'] as $reactType) {
                    $emoji = $this->getReactionEmoji($reactType);
                    $html .= "<form action='../public/post/react/{$post['id']}' method='POST' style='display:inline;'>";
                    $html .= "<input type='hidden' name='reaction' value='{$reactType}'>";
                    $html .= "<button type='submit' class='react-btn'>{$emoji}</button>";
                    $html .= "</form>";
                }
                $html .= "</div>";
                $html .= "</div>";

                // Quote button
                $html .= "<button class='footer-action' onclick='quotePost(\"{$post['username']}\", \"body-{$post['id']}\")'>❝ Quote</button>";

                // Edit/Delete buttons (if owner)
                if ($post['user_id'] == $currentUser['id']) {
                    $html .= "<a class='footer-action' href='../public/post/edit/{$post['id']}'>✎ Edit</a>";
                    if (!$post['is_first_post']) {
                        $html .= "<form action='../public/post/delete/{$post['id']}' method='POST' style='display:inline;' onsubmit='return confirm(\"Soft delete this post?\");'>";
                        $html .= "<button type='submit' class='footer-action action-danger' style='background:none; border:none; padding:0; cursor:pointer;'>🗑 Delete</button>";
                        $html .= "</form>";
                    }
                }
            }
            $html .= "</div>";

            $html .= "</div>"; // end content column
            $html .= "</div>"; // end post card
        }
        $html .= "</div>"; // end posts timeline

        // Reply box
        if ($currentUser) {
            if ($thread['is_locked']) {
                $html .= "<div class='empty-state'>🔒 This thread is locked. You cannot post replies.</div>";
            } else {
                $html .= "<div class='reply-box'>";
                $html .= "<h3>Post a Reply</h3>";
                $html .= "<form action='../public/post/reply/{$thread['id']}' method='POST'>";
                $html .= "<div class='form-group'>";
                $html .= "<textarea id='reply-textarea' name='body' class='form-control' rows='6' placeholder='Write your reply here...' required></textarea>";
                $html .= "</div>";
                $html .= "<button type='submit' class='btn'>Submit Reply</button>";
                $html .= "</form>";
                $html .= "</div>";
            }
        } else {
            $html .= "<div class='empty-state'>Please <a href='../public/login' style='color:var(--primary); font-weight:600;'>log in</a> to post a reply.</div>";
        }

        $html .= "</div>"; // end main-content
        $html .= "</div>"; // end forum-layout

        // Client-side quote script
        $html .= "
        <script>
        function quotePost(username, bodyId) {
            var bodyElement = document.getElementById(bodyId);
            var replyArea = document.getElementById('reply-textarea');
            if(bodyElement && replyArea) {
                var content = bodyElement.innerHTML;
                // Basic cleanup of quote formatting
                var quote = '<blockquote><strong>@' + username + '</strong>:<br>' + content + '</blockquote><p></p>\\n';
                replyArea.value = replyArea.value + quote;
                replyArea.focus();
            }
        }
        </script>";

        return Response::html($this->renderLayout($thread['title'] . ' - Forux Forum', $html));
    }

    /**
     * Thread creation form.
     */
    public function createThreadForm(int $categoryId): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('../login');
        }

        $html = "
        <div class='card' style='max-width: 640px;'>
            <h2>Start a New Thread</h2>
            <form action='{$categoryId}' method='POST'>
                <div class='form-group'>
                    <label>Thread Title</label>
                    <input type='text' name='title' class='form-control' placeholder='Enter thread title' required autofocus>
                </div>
                <div class='form-group'>
                    <label>Post Body (Safe HTML allowed)</label>
                    <textarea name='body' class='form-control' rows='8' placeholder='Write your post here...' required></textarea>
                </div>
                <button type='submit' class='btn'>Create Thread</button>
            </form>
            <div style='margin-top:1.5rem; text-align:center;'>
                <a href='../index.php' style='color:var(--primary); font-size:0.85rem; text-decoration:none;'>Cancel</a>
            </div>
        </div>";

        return Response::html($this->renderLayout('New Thread - Forux', $html));
    }

    /**
     * Process new thread creation.
     */
    public function createThread(int $categoryId): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('../login');
        }

        $user = $this->auth->user();
        $title = $this->request->input('title', '');
        $body = $this->request->input('body', '');

        try {
            $slug = $this->forumService->createThread($categoryId, $user['id'], $title, $body);
            return Response::redirect('../thread/' . $slug);
        } catch (Throwable $e) {
            // Render back with alert error (simple inline template render is fine)
            return Response::html($this->renderLayout('Error', "<div class='card'><h2>Error</h2><div class='alert'>{$e->getMessage()}</div><a href=''>Try again</a></div>"));
        }
    }

    /**
     * Process new reply creation.
     */
    public function createReply(int $threadId): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('../../login');
        }

        $user = $this->auth->user();
        $body = $this->request->input('body', '');

        try {
            // Fetch thread slug to redirect back
            $stmt = $this->forumService->createReply($threadId, $user['id'], $body);
            // Quick query to find slug
            $thread = $this->forumService->getPostsByThread($threadId);
            
            // Go back
            return Response::redirect($_SERVER['HTTP_REFERER'] ?? '../../index.php');
        } catch (Throwable $e) {
            return Response::html($this->renderLayout('Error', "<div class='card'><h2>Error</h2><div class='alert'>{$e->getMessage()}</div><a href=''>Try again</a></div>"));
        }
    }

    /**
     * Render post editing form.
     */
    public function editPostForm(int $postId): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('../../login');
        }

        // Fetch post using PDO directly to ensure ownership
        $pdo = $this->auth->user() ? $this->forumService->getPostsByThread(0) : null; // dummy/null trick
        
        // Let's resolve the post manually
        $container = \Core\Kernel::class; // dummy
        // Resolve PDO connection from container
        $kernel = new \Core\Kernel();
        $pdo = $kernel->getContainer()->get(\PDO::class);

        $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$postId]);
        $post = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$post || $post['user_id'] != $this->auth->user()['id']) {
            return Response::html("Unauthorized post edit access.", 403);
        }

        $html = "
        <div class='card' style='max-width: 640px;'>
            <h2>Edit Post</h2>
            <form action='{$postId}' method='POST'>
                <div class='form-group'>
                    <label>Post Body</label>
                    <textarea name='body' class='form-control' rows='8' required>" . htmlspecialchars($post['body']) . "</textarea>
                </div>
                <button type='submit' class='btn'>Save Changes</button>
            </form>
            <div style='margin-top:1.5rem; text-align:center;'>
                <a href='../../index.php' style='color:var(--primary); font-size:0.85rem; text-decoration:none;'>Cancel</a>
            </div>
        </div>";

        return Response::html($this->renderLayout('Edit Post - Forux', $html));
    }

    /**
     * Process post edit update.
     */
    public function updatePost(int $postId): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('../../login');
        }

        $user = $this->auth->user();
        $body = $this->request->input('body', '');

        try {
            $this->forumService->updatePost($postId, $user['id'], $body);
            // Redirect back to thread (fetch thread slug)
            $kernel = new \Core\Kernel();
            $pdo = $kernel->getContainer()->get(\PDO::class);
            $stmt = $pdo->prepare("SELECT t.slug FROM posts p JOIN threads t ON p.thread_id = t.id WHERE p.id = ?");
            $stmt->execute([$postId]);
            $slug = $stmt->fetchColumn();

            return Response::redirect('../../thread/' . $slug);
        } catch (Throwable $e) {
            return Response::html($this->renderLayout('Error', "<div class='card'><h2>Error</h2><div class='alert'>{$e->getMessage()}</div><a href=''>Try again</a></div>"));
        }
    }

    /**
     * Process post soft-delete.
     */
    public function deletePost(int $postId): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('../../login');
        }

        $user = $this->auth->user();

        // Verify post owner
        $kernel = new \Core\Kernel();
        $pdo = $kernel->getContainer()->get(\PDO::class);
        $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        $authorId = $stmt->fetchColumn();

        if ($authorId != $user['id']) {
            return Response::html("Unauthorized delete access.", 403);
        }

        try {
            $this->forumService->deletePost($postId);
            return Response::redirect($_SERVER['HTTP_REFERER'] ?? '../../index.php');
        } catch (Throwable $e) {
            return Response::html($e->getMessage(), 500);
        }
    }

    /**
     * Process reaction submit.
     */
    public function react(int $postId): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('../../login');
        }

        $user = $this->auth->user();
        $reactionType = $this->request->input('reaction', 'like');

        try {
            $this->forumService->toggleReaction($user['id'], $postId, $reactionType);
            return Response::redirect($_SERVER['HTTP_REFERER'] ?? '../../index.php');
        } catch (Throwable $e) {
            return Response::html($e->getMessage(), 500);
        }
    }

    /**
     * Map reaction type to Unicode Emojis.
     */
    protected function getReactionEmoji(string $type): string
    {
        $map = [
            'like' => '👍',
            'love' => '❤️',
            'haha' => '😆',
            'sad' => '😢',
            'angry' => '😠',
        ];
        return $map[$type] ?? '👍';
    }

    /**
     * Helper to render the layout sidebar widget.
     */
    protected function renderSidebar(): string
    {
        $user = $this->auth->user();
        $html = "<div class='sidebar'>";
        
        // Welcome Card
        $html .= "<div class='sidebar-widget'>";
        if ($user) {
            $avatar = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user['email']))) . '?d=mp&s=60';
            $html .= "<h3>Welcome Back</h3>";
            $html .= "<div style='display:flex; align-items:center; gap:0.75rem; margin-bottom:1rem;'>";
            $html .= "<img src='{$avatar}' alt='Avatar' style='width:40px; height:40px; border-radius:50%; border:1px solid var(--card-border);'>";
            $html .= "<div>";
            $html .= "<div style='font-weight:700;'><a href='user/{$user['username']}'>@{$user['username']}</a></div>";
            $html .= "<div style='font-size:0.75rem; color:var(--primary);'>★ {$user['reputation_points']} points</div>";
            $html .= "</div>";
            $html .= "</div>";
            $html .= "<a href='settings' class='btn btn-small' style='text-align:center; display:block; text-decoration:none;'>Profile Settings</a>";
        } else {
            $html .= "<h3>Join the Community</h3>";
            $html .= "<p style='font-size:0.85rem; color:var(--text-muted); line-height:1.4;'>Join our discussions, share ideas, and connect with developers.</p>";
            $html .= "<a href='register' class='btn' style='text-align:center; display:block; text-decoration:none; margin-bottom:0.5rem;'>Register</a>";
            $html .= "<a href='login' class='btn btn-small' style='text-align:center; display:block; text-decoration:none; background:#1f2937;'>Log In</a>";
        }
        $html .= "</div>";

        // Stats Card
        $html .= "<div class='sidebar-widget'>";
        $html .= "<h3>Forum Stats</h3>";
        
        $kernel = new \Core\Kernel();
        $pdo = $kernel->getContainer()->get(\PDO::class);
        $usersCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $threadsCount = $pdo->query("SELECT COUNT(*) FROM threads WHERE deleted_at IS NULL")->fetchColumn();
        $postsCount = $pdo->query("SELECT COUNT(*) FROM posts WHERE deleted_at IS NULL")->fetchColumn();

        $html .= "<div class='stat-row'><span>Members</span><strong>{$usersCount}</strong></div>";
        $html .= "<div class='stat-row'><span>Threads</span><strong>{$threadsCount}</strong></div>";
        $html .= "<div class='stat-row'><span>Posts</span><strong>{$postsCount}</strong></div>";
        $html .= "</div>";

        $html .= "</div>"; // end sidebar
        return $html;
    }

    /**
     * HTML layout boilerplate.
     */
    protected function renderLayout(string $title, string $content): string
    {
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
            <style>
                :root {
                    --bg-color: #0b0f19;
                    --card-bg: #111827;
                    --card-border: #1f2937;
                    --text-main: #f3f4f6;
                    --text-muted: #9ca3af;
                    --primary: #10b981;
                    --primary-hover: #059669;
                    --error: #ef4444;
                    --success: #10b981;
                }
                body {
                    background-color: var(--bg-color);
                    color: var(--text-main);
                    font-family: system-ui, -apple-system, sans-serif;
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                    min-height: 100vh;
                }
                header.nav-header {
                    background: var(--card-bg);
                    border-bottom: 1px solid var(--card-border);
                    padding: 1rem 2rem;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                header.nav-header h1 {
                    margin: 0;
                    font-size: 1.5rem;
                    background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    letter-spacing: -0.05em;
                }
                .wrapper {
                    max-width: 1100px;
                    margin: 2rem auto;
                    padding: 0 1rem;
                }
                .forum-layout {
                    display: flex;
                    gap: 2rem;
                    align-items: flex-start;
                }
                .main-content {
                    flex: 3;
                }
                .sidebar {
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    gap: 1.5rem;
                }
                .sidebar-widget {
                    background: var(--card-bg);
                    border: 1px solid var(--card-border);
                    border-radius: 12px;
                    padding: 1.5rem;
                }
                .sidebar-widget h3 {
                    margin-top: 0;
                    margin-bottom: 1rem;
                    font-size: 1.1rem;
                    border-bottom: 1px solid var(--card-border);
                    padding-bottom: 0.5rem;
                }
                .stat-row {
                    display: flex;
                    justify-content: space-between;
                    font-size: 0.85rem;
                    padding: 0.4rem 0;
                    border-bottom: 1px solid #1f2937;
                }
                .stat-row:last-child {
                    border-bottom: none;
                }
                .category-group {
                    background: var(--card-bg);
                    border: 1px solid var(--card-border);
                    border-radius: 12px;
                    margin-bottom: 1.5rem;
                    overflow: hidden;
                }
                .category-header {
                    padding: 1.25rem;
                    background: rgba(31, 41, 55, 0.2);
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                }
                .cat-link {
                    color: var(--text-main);
                    text-decoration: none;
                    font-weight: 700;
                    transition: color 0.2s;
                }
                .cat-link:hover {
                    color: var(--primary);
                }
                .cat-desc {
                    margin: 0.25rem 0 0 0;
                    font-size: 0.85rem;
                    color: var(--text-muted);
                }
                .subcategories-list {
                    border-top: 1px solid var(--card-border);
                    padding: 0.5rem 0;
                }
                .subcategory-item {
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    padding: 0.75rem 1.25rem;
                    border-bottom: 1px solid #1f2937;
                }
                .subcategory-item:last-child {
                    border-bottom: none;
                }
                .sub-icon {
                    font-size: 1.2rem;
                }
                .threads-list {
                    background: var(--card-bg);
                    border: 1px solid var(--card-border);
                    border-radius: 12px;
                    overflow: hidden;
                }
                .thread-row {
                    display: flex;
                    align-items: center;
                    padding: 1.25rem;
                    border-bottom: 1px solid var(--card-border);
                    transition: background-color 0.2s;
                }
                .thread-row:hover {
                    background-color: rgba(31, 41, 55, 0.2);
                }
                .thread-row.pinned {
                    background: rgba(16, 185, 129, 0.03);
                }
                .thread-row a {
                    color: var(--text-main);
                    text-decoration: none;
                    font-weight: 600;
                }
                .thread-row a:hover {
                    color: var(--primary);
                }
                .thread-stats {
                    display: flex;
                    gap: 1.5rem;
                    font-size: 0.85rem;
                    color: var(--text-muted);
                    margin: 0 2rem;
                }
                .thread-last-post {
                    font-size: 0.8rem;
                    min-width: 160px;
                    text-align: right;
                }
                .posts-timeline {
                    display: flex;
                    flex-direction: column;
                    gap: 1.5rem;
                    margin-bottom: 2.5rem;
                }
                .post-card {
                    background: var(--card-bg);
                    border: 1px solid var(--card-border);
                    border-radius: 12px;
                    display: flex;
                }
                .post-user {
                    width: 130px;
                    padding: 1.5rem;
                    border-right: 1px solid var(--card-border);
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    text-align: center;
                }
                .post-avatar {
                    width: 64px;
                    height: 64px;
                    border-radius: 50%;
                    background: #1f2937;
                    margin-bottom: 0.75rem;
                    border: 2px solid var(--card-border);
                }
                .post-username a {
                    color: var(--text-main);
                    text-decoration: none;
                    font-weight: 700;
                    font-size: 0.85rem;
                }
                .post-reputation {
                    font-size: 0.75rem;
                    color: var(--primary);
                    margin-top: 0.25rem;
                }
                .post-content {
                    flex: 1;
                    padding: 1.5rem;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                }
                .post-meta {
                    font-size: 0.75rem;
                    color: var(--text-muted);
                    margin-bottom: 1rem;
                }
                .post-body {
                    line-height: 1.6;
                    font-size: 0.95rem;
                }
                .post-body blockquote {
                    background: #0f172a;
                    border-left: 3px solid var(--primary);
                    padding: 0.75rem 1rem;
                    margin: 1rem 0;
                    border-radius: 0 8px 8px 0;
                    font-style: italic;
                    color: #d1d5db;
                }
                .reactions-bar {
                    margin-top: 1.5rem;
                    display: flex;
                    gap: 0.5rem;
                }
                .reaction-pill {
                    background: #1f2937;
                    border: 1px solid var(--card-border);
                    padding: 0.25rem 0.6rem;
                    border-radius: 99px;
                    font-size: 0.75rem;
                }
                .post-footer {
                    border-top: 1px solid var(--card-border);
                    margin-top: 1.5rem;
                    padding-top: 0.75rem;
                    display: flex;
                    gap: 1.25rem;
                }
                .footer-action {
                    background: none;
                    border: none;
                    color: var(--text-muted);
                    font-size: 0.8rem;
                    cursor: pointer;
                    font-weight: 600;
                    padding: 0;
                    text-decoration: none;
                    transition: color 0.2s;
                }
                .footer-action:hover {
                    color: var(--primary);
                }
                .footer-action.action-danger:hover {
                    color: var(--error);
                }
                .reaction-picker {
                    position: relative;
                    display: inline-block;
                }
                .reaction-dropdown {
                    display: none;
                    position: absolute;
                    bottom: 100%;
                    left: 0;
                    background: #1f2937;
                    border: 1px solid var(--card-border);
                    border-radius: 20px;
                    padding: 0.25rem;
                    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.3);
                    z-index: 10;
                    white-space: nowrap;
                }
                .reaction-picker:hover .reaction-dropdown {
                    display: flex;
                    gap: 0.2rem;
                }
                .react-btn {
                    background: none;
                    border: none;
                    padding: 0.25rem;
                    font-size: 1.2rem;
                    cursor: pointer;
                    transition: transform 0.1s;
                }
                .react-btn:hover {
                    transform: scale(1.3);
                }
                .reply-box {
                    background: var(--card-bg);
                    border: 1px solid var(--card-border);
                    border-radius: 12px;
                    padding: 2rem;
                }
                .breadcrumbs {
                    font-size: 0.85rem;
                    color: var(--text-muted);
                    margin-bottom: 1.5rem;
                }
                .breadcrumbs a {
                    color: var(--primary);
                    text-decoration: none;
                }
                .empty-state {
                    background: var(--card-bg);
                    border: 1px dashed var(--card-border);
                    border-radius: 12px;
                    padding: 3rem;
                    text-align: center;
                    color: var(--text-muted);
                }
                .card {
                    width: 100%;
                    background: var(--card-bg);
                    border: 1px solid var(--card-border);
                    border-radius: 16px;
                    padding: 2.5rem;
                    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4);
                    box-sizing: border-box;
                    margin: 0 auto;
                }
                .form-group {
                    margin-bottom: 1.25rem;
                }
                .form-group label {
                    display: block;
                    margin-bottom: 0.5rem;
                    font-size: 0.875rem;
                    font-weight: 500;
                    color: #d1d5db;
                }
                .form-control {
                    width: 100%;
                    background: #0f172a;
                    border: 1px solid var(--card-border);
                    border-radius: 8px;
                    padding: 0.75rem 1rem;
                    color: var(--text-main);
                    font-size: 0.95rem;
                    box-sizing: border-box;
                    transition: border-color 0.2s;
                    font-family: inherit;
                }
                .form-control:focus {
                    outline: none;
                    border-color: var(--primary);
                }
                .btn {
                    width: 100%;
                    background: var(--primary);
                    color: #fff;
                    border: none;
                    border-radius: 8px;
                    padding: 0.85rem;
                    font-size: 1rem;
                    font-weight: 600;
                    cursor: pointer;
                    transition: background-color 0.2s;
                }
                .btn:hover {
                    background-color: var(--primary-hover);
                }
                .btn-small {
                    padding: 0.5rem 1rem;
                    font-size: 0.85rem;
                }
                .pagination {
                    display: flex;
                    gap: 0.5rem;
                    margin-top: 1.5rem;
                    justify-content: center;
                }
                .page-link {
                    background: var(--card-bg);
                    border: 1px solid var(--card-border);
                    color: var(--text-main);
                    padding: 0.5rem 0.75rem;
                    border-radius: 6px;
                    text-decoration: none;
                    font-size: 0.85rem;
                }
                .page-link.active {
                    background: var(--primary);
                    border-color: var(--primary);
                }
            </style>
        </head>
        <body>
            <header class='nav-header'>
                <h1>FORUX</h1>
                <div style='font-size:0.85rem;'>
                    <a href='../public/index.php' style='color:#fff; text-decoration:none; margin-right:1rem; font-weight:600;'>Forum</a>
                    " . ($this->auth->check() 
                        ? "<a href='../public/settings' style='color:var(--primary); text-decoration:none; font-weight:600;'>@" . $this->auth->user()['username'] . "</a>"
                        : "<a href='../public/login' style='color:var(--primary); text-decoration:none; font-weight:600;'>Login</a>"
                    ) . "
                </div>
            </header>
            <div class='wrapper'>
                {$content}
            </div>
        </body>
        </html>";
    }
}
