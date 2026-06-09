<div class="forum-layout">
    <div class="main-content">
        <article class="static-page-card">
            <h1 class="page-title"><?= htmlspecialchars($page['title']) ?></h1>
            <div class="page-meta">
                Published on <?= date('F d, Y', strtotime($page['created_at'])) ?>
            </div>
            <hr class="page-divider">
            <div class="page-body">
                <!-- Static pages from admin panel allow rich text format -->
                <?= $page['body'] ?>
            </div>
        </article>
    </div>

    <!-- Render Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>
</div>

<style>
    .static-page-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 12px;
        padding: 2.5rem;
        backdrop-filter: blur(8px);
    }

    .page-title {
        margin: 0 0 0.5rem 0;
        font-size: 2.25rem;
        font-weight: 800;
        letter-spacing: -0.03em;
        color: var(--text-main);
    }

    .page-meta {
        font-size: 0.85rem;
        color: var(--text-muted);
        margin-bottom: 1.5rem;
    }

    .page-divider {
        border: 0;
        height: 1px;
        background: var(--card-border);
        margin-bottom: 2rem;
    }

    .page-body {
        font-size: 1.05rem;
        line-height: 1.7;
        color: var(--text-main);
    }

    .page-body p {
        margin: 0 0 1.25rem 0;
    }

    .page-body h2 {
        font-size: 1.5rem;
        margin: 2rem 0 1rem 0;
        font-weight: 700;
    }

    .page-body h3 {
        font-size: 1.25rem;
        margin: 1.5rem 0 1rem 0;
        font-weight: 600;
    }

    .page-body ul, .page-body ol {
        margin: 0 0 1.25rem 2rem;
    }

    .page-body li {
        margin-bottom: 0.5rem;
    }

    .page-body blockquote {
        margin: 1.5rem 0;
        padding: 0.5rem 1.5rem;
        border-left: 4px solid var(--primary);
        background: rgba(139, 92, 246, 0.05);
        border-radius: 0 8px 8px 0;
        font-style: italic;
    }
</style>
