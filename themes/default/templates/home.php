<div class="forum-layout">
    <div class="main-content">
        <div class="section-header">
            <h2>Categories</h2>
        </div>

        <?php if (empty($categoriesTree)): ?>
            <div class="empty-state">
                <div class="empty-icon">🗀</div>
                <h3>No categories found</h3>
                <p>Configure categories in the Admin Control panel or run seed database migrations.</p>
            </div>
        <?php else: ?>
            <div class="categories-list">
                <?php foreach ($categoriesTree as $cat): ?>
                    <div class="category-card" style="--cat-color: <?= htmlspecialchars($cat['color']) ?>">
                        <div class="category-header">
                            <div class="category-info">
                                <h3 class="category-title">
                                    <a href="<?= $baseUrl ?>/category/<?= htmlspecialchars($cat['slug']) ?>">
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </a>
                                </h3>
                                <p class="category-description"><?= htmlspecialchars($cat['description']) ?></p>
                            </div>
                        </div>

                        <?php if (!empty($cat['children'])): ?>
                            <div class="subcategories-grid">
                                <?php foreach ($cat['children'] as $child): ?>
                                    <div class="subcategory-item">
                                        <span class="sub-icon" style="color: <?= htmlspecialchars($child['color']) ?>">🗁</span>
                                        <div class="sub-details">
                                            <h4 class="sub-title">
                                                <a href="<?= $baseUrl ?>/category/<?= htmlspecialchars($child['slug']) ?>">
                                                    <?= htmlspecialchars($child['name']) ?>
                                                </a>
                                            </h4>
                                            <p class="sub-desc"><?= htmlspecialchars($child['description']) ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Render Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>
</div>

<style>
    .forum-layout {
        display: flex;
        gap: 2rem;
        align-items: flex-start;
    }

    .main-content {
        flex: 3;
    }

    .section-header {
        margin-bottom: 1.5rem;
    }

    .section-header h2 {
        font-size: 1.75rem;
        font-weight: 800;
        margin: 0;
        letter-spacing: -0.02em;
    }

    .categories-list {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .category-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-top: 4px solid var(--cat-color);
        border-radius: 12px;
        overflow: hidden;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .category-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
    }

    .category-header {
        padding: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: rgba(31, 41, 55, 0.15);
        border-bottom: 1px solid var(--glass-border);
    }

    .category-title {
        margin: 0;
        font-size: 1.3rem;
        font-weight: 700;
    }

    .category-title a {
        color: var(--text-main);
        text-decoration: none;
        transition: color 0.2s;
    }

    .category-title a:hover {
        color: var(--cat-color);
    }

    .category-description {
        margin: 0.35rem 0 0 0;
        font-size: 0.88rem;
        color: var(--text-muted);
        line-height: 1.4;
    }

    .subcategories-grid {
        padding: 0.5rem 1rem;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 0.5rem;
        background: rgba(17, 24, 39, 0.3);
    }

    .subcategory-item {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 1rem;
        border-radius: 8px;
        transition: background-color 0.2s;
    }

    .subcategory-item:hover {
        background-color: rgba(31, 41, 55, 0.25);
    }

    .sub-icon {
        font-size: 1.3rem;
        line-height: 1;
        margin-top: 0.1rem;
    }

    .sub-details {
        display: flex;
        flex-direction: column;
    }

    .sub-title {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 600;
    }

    .sub-title a {
        color: var(--text-main);
        text-decoration: none;
        transition: color 0.2s;
    }

    .sub-title a:hover {
        color: var(--primary);
    }

    .sub-desc {
        margin: 0.25rem 0 0 0;
        font-size: 0.8rem;
        color: var(--text-muted);
        line-height: 1.4;
    }

    .empty-state {
        background: var(--card-bg);
        border: 1px dashed var(--card-border);
        border-radius: 16px;
        padding: 4rem 2rem;
        text-align: center;
        backdrop-filter: blur(8px);
    }

    .empty-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .empty-state h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
    }

    .empty-state p {
        margin: 0.5rem 0 0 0;
        font-size: 0.9rem;
        color: var(--text-muted);
    }

    @media (max-width: 768px) {
        .forum-layout {
            flex-direction: column;
        }
    }
</style>
