<div class="forum-layout">
    <div class="main-content">
        <div class="section-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem;">
            <h2>Private Messages</h2>
            <a href="<?= $baseUrl ?>/messages/new" class="btn btn-primary" style="padding: 0.5rem 1.25rem; border-radius: 6px; font-weight:600; text-decoration:none;">New Message</a>
        </div>

        <?php if (empty($conversations)): ?>
            <div class="empty-state">
                <div class="empty-icon">✉</div>
                <h3>No messages yet</h3>
                <p>Start a conversation with another community member.</p>
            </div>
        <?php else: ?>
            <div class="conversations-list">
                <?php foreach ($conversations as $conv): ?>
                    <div class="conversation-card">
                        <div class="avatar-col">
                            <?php 
                                $firstRecipient = explode(',', $conv['recipient_names'])[0] ?? 'System';
                                $avatar = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($firstRecipient))) . '?d=mp&s=48';
                            ?>
                            <img src="<?= htmlspecialchars($avatar) ?>" alt="Recipient Avatar" class="user-avatar-md">
                        </div>
                        <div class="conversation-details">
                            <h4 class="conversation-title">
                                <a href="<?= $baseUrl ?>/messages/<?= $conv['id'] ?>">
                                    Chat with @<?= htmlspecialchars($conv['recipient_names']) ?>
                                </a>
                            </h4>
                            <p class="last-snippet">
                                <?= htmlspecialchars(mb_strimwidth(strip_tags($conv['last_message_body'] ?? ''), 0, 100, '...')) ?>
                            </p>
                            <span class="meta-date">
                                <?= date('M d, Y H:i', strtotime($conv['last_message_at'] ?? $conv['created_at'])) ?>
                            </span>
                        </div>
                        <div class="action-col">
                            <a href="<?= $baseUrl ?>/messages/<?= $conv['id'] ?>" class="btn btn-secondary btn-small" style="text-decoration:none;">Open</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Render Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>
</div>

<style>
    .conversations-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .conversation-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 12px;
        padding: 1.25rem;
        display: flex;
        gap: 1.25rem;
        align-items: center;
        backdrop-filter: blur(8px);
        transition: transform 0.2s;
    }

    .conversation-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .conversation-details {
        flex: 1;
    }

    .conversation-title {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 700;
    }

    .conversation-title a {
        color: var(--text-main);
        text-decoration: none;
    }

    .conversation-title a:hover {
        color: var(--primary);
    }

    .last-snippet {
        margin: 0.35rem 0;
        font-size: 0.9rem;
        color: var(--text-muted);
    }

    .meta-date {
        font-size: 0.78rem;
        color: var(--text-muted);
    }
</style>
