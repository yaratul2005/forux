<?php

namespace Modules\Forum\Controllers;

use Core\Response;
use Core\Request;
use Modules\Forum\Services\ForumService;
use Modules\Auth\Services\AuthService;
use Throwable;

/**
 * Controller managing Forum HTTP requests using the theme view engine.
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
        $cachedTree = \Core\Cache::remember('categories_tree', 3600, function() {
            return serialize($this->forumService->getCategoriesTree());
        });
        $categoriesTree = unserialize($cachedTree);
        
        return \Core\View::render('home', [
            'categoriesTree' => $categoriesTree,
            'title' => 'Home - Forux Forum'
        ]);
    }

    /**
     * Category view: Display threads inside a category with pagination.
     */
    public function showCategory(string $slug): Response
    {
        $category = $this->forumService->getCategoryBySlug($slug);
        if (!$category) {
            return \Core\View::render('error', [
                'title' => 'Category Not Found',
                'message' => 'The requested category does not exist.'
            ], 404);
        }

        $page = (int)$this->request->input('page', 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $threads = $this->forumService->getThreadsByCategory($category['id'], $limit, $offset);
        $totalThreads = $this->forumService->getThreadsCountByCategory($category['id']);
        $totalPages = (int)ceil($totalThreads / $limit) ?: 1;

        return \Core\View::render('category', [
            'category' => $category,
            'threads' => $threads,
            'page' => $page,
            'totalPages' => $totalPages,
            'title' => $category['name'] . ' - Forux Forum'
        ]);
    }

    /**
     * Thread view: Display posts timeline.
     */
    public function showThread(string $slug): Response
    {
        $thread = $this->forumService->getThreadBySlug($slug);
        if (!$thread) {
            return \Core\View::render('error', [
                'title' => 'Thread Not Found',
                'message' => 'The requested thread does not exist.'
            ], 404);
        }

        $this->forumService->incrementThreadViews($thread['id']);
        $posts = $this->forumService->getPostsByThread($thread['id']);

        return \Core\View::render('thread', [
            'thread' => $thread,
            'posts' => $posts,
            'title' => $thread['title'] . ' - Forux Forum'
        ]);
    }

    /**
     * Thread creation form.
     */
    public function createThreadForm(int $categoryId): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('../login');
        }

        return \Core\View::render('new_thread', [
            'categoryId' => $categoryId,
            'title' => 'New Thread - Forux'
        ]);
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
            return \Core\View::render('error', [
                'title' => 'Error Creating Thread',
                'message' => $e->getMessage()
            ]);
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
            $this->forumService->createReply($threadId, $user['id'], $body);
            return Response::redirect($_SERVER['HTTP_REFERER'] ?? '../../index.php');
        } catch (Throwable $e) {
            return \Core\View::render('error', [
                'title' => 'Error Submitting Reply',
                'message' => $e->getMessage()
            ]);
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

        $container = \Core\Container::getInstance();
        $pdo = $container->get(\PDO::class);

        $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$postId]);
        $post = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$post || $post['user_id'] != $this->auth->user()['id']) {
            return \Core\View::render('error', [
                'title' => 'Unauthorized',
                'message' => 'Unauthorized post edit access.'
            ], 403);
        }

        return \Core\View::render('edit_post', [
            'postId' => $postId,
            'post' => $post,
            'title' => 'Edit Post - Forux'
        ]);
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
            
            $container = \Core\Container::getInstance();
            $pdo = $container->get(\PDO::class);
            $stmt = $pdo->prepare("SELECT t.slug FROM posts p JOIN threads t ON p.thread_id = t.id WHERE p.id = ?");
            $stmt->execute([$postId]);
            $slug = $stmt->fetchColumn();

            return Response::redirect('../../thread/' . $slug);
        } catch (Throwable $e) {
            return \Core\View::render('error', [
                'title' => 'Error Updating Post',
                'message' => $e->getMessage()
            ]);
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

        $container = \Core\Container::getInstance();
        $pdo = $container->get(\PDO::class);
        $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        $authorId = $stmt->fetchColumn();

        if ($authorId != $user['id']) {
            return \Core\View::render('error', [
                'title' => 'Unauthorized',
                'message' => 'Unauthorized delete access.'
            ], 403);
        }

        try {
            $this->forumService->deletePost($postId);
            return Response::redirect($_SERVER['HTTP_REFERER'] ?? '../../index.php');
        } catch (Throwable $e) {
            return \Core\View::render('error', [
                'title' => 'Error',
                'message' => $e->getMessage()
            ], 500);
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
            return \Core\View::render('error', [
                'title' => 'Error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
