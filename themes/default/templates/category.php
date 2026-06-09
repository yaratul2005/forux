<div class="forum-layout">
    <div class="main-content">
        <!-- Breadcrumbs -->
        <div class="breadcrumbs">
            <a href="<?= $baseUrl ?>/">Home</a>
            <span class="separator">/</span>
            <span class="current"><?= htmlspecialchars($category['name']) ?></span>
        </div>

        <!-- Category Header Header -->
        <div class="category-view-header">
            <div class="category-meta-info">
                <h2><?= htmlspecialchars($category['name']) ?></h2>
                <p><?= htmlspecialchars($category['description']) ?></p>
            </div>
            <?php if ($currentUser): ?>
                <a href="<?= $baseUrl ?>/thread/new/<?= (int)$category['id'] ?>" class="btn">
                    <span>+</span> New Thread
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($threads)): ?>
            <div class="empty-state">
                <div class="empty-icon">💬</div>
                <h3>No discussions yet</h3>
                <p>Be the first one to start a conversation in <?= htmlspecialchars($category['name']) ?>!</p>
                <?php if ($currentUser): ?>
                    <a href="<?= $baseUrl ?>/thread/new/<?= (int)$category['id'] ?>" class="btn" style="margin-top: 1.5rem;">
                        Start a Thread
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="threads-card">
                <?php foreach ($threads as $t): 
                    $isPinned = (bool)($t['is_pinned'] ?? false);
                    $isLocked = (bool)($t['is_locked'] ?? false);
                ?>
                    <div class="thread-item <?= $isPinned ? 'pinned' : '' ?>">
                        <div class="thread-main">
                            <h4 class="thread-title">
                                <?php if ($isPinned): ?>
                                    <span class="pinned-tag">📌</span>
                                <?php endif; ?>
                                <a href="<?= $baseUrl ?>/thread/<?= htmlspecialchars($t['slug']) ?>">
                                    <?= htmlspecialchars($t['title']) ?>
                                </a>
                                <?php if ($isLocked): ?>
                                    <span class="locked-tag" title="Locked">🔒</span>
                                <?php endif; ?>
                            </h4>
                            <div class="thread-meta">
                                <span>Started by <a href="<?= $baseUrl ?>/user/<?= htmlspecialchars($t['author_name']) ?>" class="meta-author">@<?= htmlspecialchars($t['author_name']) ?></a></span>
                                <span class="bullet">&bull;</span>
                                <span><?= date('M d, Y', strtotime($t['created_at'])) ?></span>
                            </div>
                        </div>

                        <div class="thread-stats">
                            <div class="stat-box">
                                <span class="stat-count"><?= number_format($t['replies_count']) ?></span>
                                <span class="stat-label">replies</span>
                            </div>
                            <div class="stat-box">
                                <span class="stat-count"><?= number_format($t['views_count']) ?></span>
                                <span class="stat-label">views</span>
                            </div>
                        </div>

                        <div class="thread-last-reply">
                            <?php if ($t['last_post_author']): ?>
                                <span class="last-reply-label">Last reply by</span>
                                <a href="<?= $baseUrl ?>/user/<?= htmlspecialchars($t['last_post_author']) ?>" class="last-reply-user">
                                    @<?= htmlspecialchars($t['last_post_author']) ?>
                                </a>
                                <span class="last-reply-time"><?= date('M d, H:i', strtotime($t['last_post_at'])) ?></span>
                            <?php else: ?>
                                <span class="last-reply-label">No replies yet</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): 
                        $activeClass = $i === $page ? 'active' : '';
                    ?>
                        <a href="?page=<?= $i ?>" class="page-link <?= $activeClass ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Render Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>
</div>

<style>
    .breadcrumbs {
        font-size: 0.85rem;
        color: var(--text-muted);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .breadcrumbs a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 500;
        transition: opacity 0.2s;
    }

    .breadcrumbs a:hover {
        opacity: 0.8;
    }

    .breadcrumbs .separator {
        opacity: 0.5;
    }

    .breadcrumbs .current {
        color: var(--text-main);
    }

    .category-view-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1.5rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }

    .category-meta-info h2 {
        font-size: 1.75rem;
        font-weight: 800;
        margin: 0;
        letter-spacing: -0.02em;
    }

    .category-meta-info p {
        margin: 0.35rem 0 0 0;
        color: var(--text-muted);
        font-size: 0.95rem;
        line-height: 1.4;
    }

    .threads-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 12px;
        overflow: hidden;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }

    .thread-item {
        display: flex;
        align-items: center;
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--glass-border);
        transition: background-color 0.2s;
        gap: 1.5rem;
    }

    .thread-item:last-child {
        border-bottom: none;
    }

    .thread-item:hover {
        background-color: rgba(31, 41, 55, 0.2);
    }

    .thread-item.pinned {
        background-color: rgba(16, 185, 129, 0.02);
    }

    .thread-item.pinned:hover {
        background-color: rgba(16, 185, 129, 0.04);
    }

    .thread-main {
        flex: 1;
        min-width: 0;
    }

    .thread-title {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        line-height: 1.4;
    }

    .thread-title a {
        color: var(--text-main);
        text-decoration: none;
        transition: color 0.2s;
        word-break: break-word;
    }

    .thread-title a:hover {
        color: var(--primary);
    }

    .pinned-tag {
        font-size: 0.95rem;
    }

    .locked-tag {
        font-size: 0.85rem;
        opacity: 0.65;
    }

    .thread-meta {
        margin-top: 0.35rem;
        font-size: 0.8rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    .meta-author {
        color: var(--text-main);
        text-decoration: none;
        font-weight: 500;
    }

    .meta-author:hover {
        color: var(--primary);
    }

    .thread-stats {
        display: flex;
        gap: 1.5rem;
        text-align: center;
    }

    .stat-box {
        display: flex;
        flex-direction: column;
        min-width: 50px;
    }

    .stat-count {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--text-main);
    }

    .stat-label {
        font-size: 0.72rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-top: 0.1rem;
    }

    .thread-last-reply {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        min-width: 140px;
        font-size: 0.75rem;
        text-align: right;
    }

    .last-reply-label {
        color: var(--text-muted);
    }

    .last-reply-user {
        color: var(--text-main);
        text-decoration: none;
        font-weight: 600;
        margin-top: 0.1rem;
    }

    .last-reply-user:hover {
        color: var(--primary);
    }

    .last-reply-time {
        color: var(--text-muted);
        margin-top: 0.1rem;
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 0.4rem;
        margin-top: 2rem;
    }

    .page-link {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        color: var(--text-main);
        padding: 0.45rem 0.85rem;
        border-radius: 8px;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 600;
        transition: background-color 0.2s, border-color 0.2s;
    }

    .page-link:hover {
        background: rgba(31, 41, 55, 0.4);
        border-color: var(--primary);
    }

    .page-link.active {
        background: var(--primary);
        border-color: var(--primary);
        color: #fff;
    }

    @media (max-width: 768px) {
        .thread-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }

        .thread-stats {
            gap: 2rem;
        }

        .thread-last-reply {
            align-items: flex-start;
            text-align: left;
        }
    }
</style>
