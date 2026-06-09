<div class="forum-layout">
    <div class="main-content">
        <div class="section-header">
            <h2>Search the Forum</h2>
        </div>

        <div class="search-form-card">
            <form action="<?= $baseUrl ?>/search" method="GET" class="search-form-grid">
                <div class="form-group query-group">
                    <label for="search-page-q">Search Query</label>
                    <input type="text" name="q" id="search-page-q" placeholder="Enter keywords..." value="<?= htmlspecialchars($q) ?>" required minlength="2">
                </div>

                <div class="form-group">
                    <label for="search-page-type">Search Type</label>
                    <select name="type" id="search-page-type">
                        <option value="threads" <?= $type === 'threads' ? 'selected' : '' ?>>Threads (Titles)</option>
                        <option value="posts" <?= $type === 'posts' ? 'selected' : '' ?>>Posts (Bodies)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="search-page-cat">Category Filter</label>
                    <select name="category_id" id="search-page-cat">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group submit-group">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
            </form>
        </div>

        <?php if ($hasSearched): ?>
            <div class="results-header">
                <h3>Found <?= count($results) ?> results for "<?= htmlspecialchars($q) ?>"</h3>
            </div>

            <?php if (empty($results)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🔍</div>
                    <h3>No results found</h3>
                    <p>Try different keywords or check your spelling.</p>
                </div>
            <?php else: ?>
                <div class="results-list">
                    <?php if ($type === 'threads'): ?>
                        <?php foreach ($results as $thread): ?>
                            <div class="result-item-card">
                                <div class="avatar-col">
                                    <?php 
                                        $avatar = $thread['author_avatar'] ?: 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($thread['author_name']))) . '?d=mp&s=48';
                                    ?>
                                    <img src="<?= htmlspecialchars($avatar) ?>" alt="Author Avatar" class="user-avatar-md">
                                </div>
                                <div class="result-details">
                                    <h4 class="result-title">
                                        <a href="<?= $baseUrl ?>/thread/<?= htmlspecialchars($thread['slug']) ?>">
                                            <?= htmlspecialchars($thread['title']) ?>
                                        </a>
                                    </h4>
                                    <div class="result-meta">
                                        by <a href="<?= $baseUrl ?>/user/<?= htmlspecialchars($thread['author_name']) ?>" class="meta-link">@<?= htmlspecialchars($thread['author_name']) ?></a>
                                        in <a href="<?= $baseUrl ?>/category/<?= htmlspecialchars($thread['category_slug']) ?>" class="meta-link"><?= htmlspecialchars($thread['category_name']) ?></a>
                                        • <?= date('M d, Y', strtotime($thread['created_at'])) ?>
                                    </div>
                                </div>
                                <div class="stats-col">
                                    <span class="stat-badge">💬 <?= $thread['replies_count'] ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php foreach ($results as $post): ?>
                            <div class="result-item-card post-result-card">
                                <div class="avatar-col">
                                    <?php 
                                        $avatar = $post['author_avatar'] ?: 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($post['author_name']))) . '?d=mp&s=48';
                                    ?>
                                    <img src="<?= htmlspecialchars($avatar) ?>" alt="Author Avatar" class="user-avatar-md">
                                </div>
                                <div class="result-details">
                                    <h4 class="result-title">
                                        <a href="<?= $baseUrl ?>/thread/<?= htmlspecialchars($post['thread_slug']) ?>#post-<?= $post['id'] ?>">
                                            Re: <?= htmlspecialchars($post['thread_title']) ?>
                                        </a>
                                    </h4>
                                    <div class="result-meta">
                                        by <a href="<?= $baseUrl ?>/user/<?= htmlspecialchars($post['author_name']) ?>" class="meta-link">@<?= htmlspecialchars($post['author_name']) ?></a>
                                        • <?= date('M d, Y', strtotime($post['created_at'])) ?>
                                    </div>
                                    <div class="post-snippet">
                                        <?= htmlspecialchars(mb_strimwidth(strip_tags($post['body']), 0, 180, '...')) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Render Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>
</div>

<style>
    .search-form-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        backdrop-filter: blur(8px);
    }

    .search-form-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr auto;
        gap: 1.25rem;
        align-items: flex-end;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-group label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .form-group input,
    .form-group select {
        background: rgba(17, 24, 39, 0.5);
        border: 1px solid var(--card-border);
        border-radius: 6px;
        color: var(--text-main);
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .form-group input:focus,
    .form-group select:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
    }

    .btn-primary {
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: 6px;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        cursor: pointer;
        transition: opacity 0.2s;
    }

    .btn-primary:hover {
        opacity: 0.9;
    }

    .results-header {
        margin-bottom: 1.25rem;
        border-bottom: 1px solid var(--card-border);
        padding-bottom: 0.5rem;
    }

    .results-header h3 {
        margin: 0;
        font-size: 1.1rem;
        color: var(--text-muted);
        font-weight: 500;
    }

    .results-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .result-item-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 10px;
        padding: 1.25rem;
        display: flex;
        gap: 1.25rem;
        align-items: center;
        transition: transform 0.2s;
    }

    .result-item-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .post-result-card {
        align-items: flex-start;
    }

    .user-avatar-md {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        border: 2px solid var(--card-border);
        object-fit: cover;
    }

    .result-details {
        flex: 1;
    }

    .result-title {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 700;
    }

    .result-title a {
        color: var(--text-main);
        text-decoration: none;
    }

    .result-title a:hover {
        color: var(--primary);
    }

    .result-meta {
        font-size: 0.85rem;
        color: var(--text-muted);
        margin-top: 0.35rem;
    }

    .meta-link {
        color: var(--text-main);
        text-decoration: none;
        font-weight: 500;
    }

    .meta-link:hover {
        color: var(--primary);
    }

    .post-snippet {
        margin-top: 0.5rem;
        font-size: 0.9rem;
        color: var(--text-muted);
        line-height: 1.4;
        background: rgba(17, 24, 39, 0.2);
        padding: 0.5rem;
        border-radius: 6px;
    }

    .stat-badge {
        background: rgba(31, 41, 55, 0.5);
        border: 1px solid var(--card-border);
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.85rem;
        color: var(--text-main);
    }

    @media (max-width: 900px) {
        .search-form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
