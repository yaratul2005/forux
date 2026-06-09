<?php

namespace Modules\Pages\Controllers;

use Core\Response;
use Core\Request;
use Core\View;
use PDO;
use Throwable;

/**
 * Controller to display public static CMS pages
 */
class PagesController
{
    protected PDO $pdo;
    protected Request $request;

    /**
     * Create a new PagesController instance.
     */
    public function __construct(PDO $pdo, Request $request)
    {
        $this->pdo = $pdo;
        $this->request = $request;
    }

    /**
     * Display a published static page by its slug.
     */
    public function show(string $slug): Response
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM pages 
                WHERE slug = ? AND is_published = 1
            ");
            $stmt->execute([$slug]);
            $page = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$page) {
                return View::render('error', [
                    'title' => 'Page Not Found',
                    'message' => 'The requested page could not be found.'
                ], 404);
            }

            return View::render('page', [
                'page' => $page,
                'title' => $page['title'] . ' - Forux'
            ]);
        } catch (Throwable $e) {
            return View::render('error', [
                'title' => 'Database Error',
                'message' => 'An error occurred while loading this page.'
            ], 500);
        }
    }
}
