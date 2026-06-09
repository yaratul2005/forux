<?php
$container = \Core\Container::getInstance();

$cachedStats = \Core\Cache::remember('forum_sidebar_stats', 300, function() use ($container) {
    $pdo = $container->get(\PDO::class);
    $usersCount = 0;
    $threadsCount = 0;
    $postsCount = 0;
    try {
        $usersCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $threadsCount = $pdo->query("SELECT COUNT(*) FROM threads WHERE deleted_at IS NULL")->fetchColumn();
        $postsCount = $pdo->query("SELECT COUNT(*) FROM posts WHERE deleted_at IS NULL")->fetchColumn();
    } catch (\Throwable $e) {
        // Fail silently
    }
    return serialize([
        'users' => $usersCount,
        'threads' => $threadsCount,
        'posts' => $postsCount
    ]);
});

$stats = unserialize($cachedStats);
$usersCount = $stats['users'] ?? 0;
$threadsCount = $stats['threads'] ?? 0;
$postsCount = $stats['posts'] ?? 0;
?>

<div class="sidebar">
    <!-- Welcome Card -->
    <div class="sidebar-widget">
        <?php if ($currentUser): 
            $avatar = $currentUser['avatar_url'] ?: 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($currentUser['email']))) . '?d=mp&s=80';
        ?>
            <h3>Welcome Back</h3>
            <div class="user-welcome-info">
                <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="welcome-avatar">
                <div class="welcome-details">
                    <span class="welcome-username"><a href="<?= $baseUrl ?>/user/<?= htmlspecialchars($currentUser['username']) ?>">@<?= htmlspecialchars($currentUser['username']) ?></a></span>
                    <span class="welcome-pts">★ <?= (int)($currentUser['reputation_points'] ?? 0) ?> pts</span>
                </div>
            </div>
            <a href="<?= $baseUrl ?>/settings" class="btn btn-secondary btn-small btn-block">Profile Settings</a>
        <?php else: ?>
            <h3>Join the Community</h3>
            <p class="widget-desc">Join our discussions, share ideas, and connect with developers around the world.</p>
            <div class="widget-auth-buttons">
                <a href="<?= $baseUrl ?>/register" class="btn btn-block" style="margin-bottom: 0.5rem;">Register</a>
                <a href="<?= $baseUrl ?>/login" class="btn btn-secondary btn-block">Log In</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Forum Stats Card -->
    <div class="sidebar-widget">
        <h3>Forum Stats</h3>
        <div class="stats-list">
            <div class="stat-row">
                <span>Members</span>
                <strong><?= number_format($usersCount) ?></strong>
            </div>
            <div class="stat-row">
                <span>Threads</span>
                <strong><?= number_format($threadsCount) ?></strong>
            </div>
            <div class="stat-row">
                <span>Posts</span>
                <strong><?= number_format($postsCount) ?></strong>
            </div>
        </div>
    </div>
</div>

<style>
    .sidebar {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        min-width: 280px;
    }
    
    .sidebar-widget {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 14px;
        padding: 1.5rem;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }
    
    .sidebar-widget h3 {
        margin-top: 0;
        margin-bottom: 1rem;
        font-size: 1.1rem;
        font-weight: 700;
        border-bottom: 1px solid var(--glass-border);
        padding-bottom: 0.6rem;
    }
    
    .user-welcome-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.25rem;
    }
    
    .welcome-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        border: 2px solid var(--glass-border);
        object-fit: cover;
    }
    
    .welcome-details {
        display: flex;
        flex-direction: column;
    }
    
    .welcome-username a {
        font-weight: 700;
        color: var(--text-main);
        text-decoration: none;
        transition: color 0.2s;
    }
    
    .welcome-username a:hover {
        color: var(--primary);
    }
    
    .welcome-pts {
        font-size: 0.8rem;
        color: var(--primary);
        font-weight: 500;
        margin-top: 0.15rem;
    }
    
    .btn-block {
        display: flex;
        width: 100%;
        box-sizing: border-box;
        text-align: center;
        justify-content: center;
    }
    
    .widget-desc {
        font-size: 0.85rem;
        color: var(--text-muted);
        line-height: 1.5;
        margin-bottom: 1.25rem;
    }
    
    .stats-list {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
    }
    
    .stat-row {
        display: flex;
        justify-content: space-between;
        font-size: 0.85rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--glass-border);
    }
    
    .stat-row:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }
    
    .stat-row span {
        color: var(--text-muted);
    }
    
    .stat-row strong {
        color: var(--text-main);
    }
</style>
