<div class="forum-layout font-standard">
    <div class="main-content timeline-container">
        <!-- Breadcrumbs -->
        <div class="breadcrumbs">
            <a href="<?= $baseUrl ?>/">Home</a>
            <span class="separator">/</span>
            <a href="<?= $baseUrl ?>/category/<?= htmlspecialchars($thread['category_slug']) ?>">
                <?= htmlspecialchars($thread['category_name']) ?>
            </a>
            <span class="separator">/</span>
            <span class="current"><?= htmlspecialchars($thread['title']) ?></span>
        </div>

        <div class="thread-header-title">
            <h2>
                <?= htmlspecialchars($thread['title']) ?>
                <?php if (!empty($thread['is_locked'])): ?>
                    <span class="locked-badge" title="Thread Locked">🔒</span>
                <?php endif; ?>
            </h2>
        </div>

        <!-- Timeline -->
        <div class="posts-timeline">
            <?php foreach ($posts as $post): 
                $avatar = $post['avatar_url'] ?: 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($post['email']))) . '?d=mp&s=80';
            ?>
                <div class="post-card" id="post-<?= $post['id'] ?>">
                    <!-- Post User Profile -->
                    <div class="post-author-sidebar">
                        <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="post-avatar">
                        <div class="post-author-name">
                            <a href="<?= $baseUrl ?>/user/<?= htmlspecialchars($post['username']) ?>">
                                @<?= htmlspecialchars($post['username']) ?>
                            </a>
                        </div>
                        <div class="post-reputation" title="Reputation Points">
                            ★ <?= (int)($post['reputation_points'] ?? 0) ?> pts
                        </div>
                    </div>

                    <!-- Post Body and Footer -->
                    <div class="post-details">
                        <div class="post-header">
                            <span class="post-date">Posted <?= date('M d, Y H:i', strtotime($post['created_at'])) ?></span>
                        </div>
                        
                        <!-- Rich Post Body Content -->
                        <div class="post-body-content" id="body-<?= $post['id'] ?>">
                            <?= $post['body'] ?>
                        </div>

                        <!-- Reactions Bar -->
                        <div class="reactions-container">
                            <div class="reactions-list">
                                <?php 
                                $reactionEmojis = ['like' => '👍', 'love' => '❤️', 'haha' => '😆', 'sad' => '😢', 'angry' => '😠'];
                                foreach ($reactionEmojis as $type => $emoji): 
                                    $count = (int)($post['reactions'][$type] ?? 0);
                                    if ($count > 0):
                                ?>
                                    <span class="reaction-pill"><?= $emoji ?> <?= $count ?></span>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                        </div>

                        <!-- Controls Footer -->
                        <div class="post-footer-controls">
                            <?php if ($currentUser): ?>
                                <!-- Reaction Picker -->
                                <div class="reaction-trigger">
                                    <button class="control-btn react-trigger-btn">☺ React</button>
                                    <div class="reaction-menu">
                                        <?php foreach ($reactionEmojis as $reactType => $emoji): ?>
                                            <form action="<?= $baseUrl ?>/post/react/<?= (int)$post['id'] ?>" method="POST" class="reaction-form">
                                                <input type="hidden" name="reaction" value="<?= $reactType ?>">
                                                <button type="submit" class="react-submit-btn"><?= $emoji ?></button>
                                            </form>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Quote Action -->
                                <button class="control-btn" onclick="quotePost('<?= htmlspecialchars($post['username'], ENT_QUOTES) ?>', 'body-<?= $post['id'] ?>')">
                                    ❝ Quote
                                </button>

                                <!-- Owner Actions -->
                                <?php if ((int)$post['user_id'] === (int)$currentUser['id']): ?>
                                    <a href="<?= $baseUrl ?>/post/edit/<?= (int)$post['id'] ?>" class="control-btn">✎ Edit</a>
                                    <?php if (empty($post['is_first_post'])): ?>
                                        <form action="<?= $baseUrl ?>/post/delete/<?= (int)$post['id'] ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to soft delete this post?');">
                                            <button type="submit" class="control-btn danger-btn">🗑 Delete</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Editor/Reply Box -->
        <?php if ($currentUser): ?>
            <?php if (!empty($thread['is_locked'])): ?>
                <div class="status-notice">
                    🔒 This thread has been locked. You cannot reply.
                </div>
            <?php else: ?>
                <div class="reply-editor-card">
                    <h3>Post a Reply</h3>
                    <form action="<?= $baseUrl ?>/post/reply/<?= (int)$thread['id'] ?>" method="POST" id="reply-form">
                        
                        <!-- WYSIWYG Toolbar -->
                        <div class="wysiwyg-toolbar" id="editor-toolbar">
                            <button type="button" class="toolbar-btn" data-tag="b" title="Bold"><strong>B</strong></button>
                            <button type="button" class="toolbar-btn" data-tag="i" title="Italic"><em>I</em></button>
                            <button type="button" class="toolbar-btn" data-tag="blockquote" title="Quote">❝</button>
                            <button type="button" class="toolbar-btn" data-tag="a" title="Insert Link">🔗</button>
                            <button type="button" class="toolbar-btn" data-tag="img" title="Insert Image">🖼</button>
                        </div>

                        <!-- Editor Textarea wrapper -->
                        <div class="form-group" style="position: relative;">
                            <textarea id="reply-textarea" name="body" class="form-control" rows="8" placeholder="Write your reply here... (Safe HTML & Image paste enabled)" required></textarea>
                            
                            <!-- Autocomplete Overlay Container -->
                            <div id="mention-autocomplete" class="mention-overlay" style="display: none;"></div>
                        </div>

                        <button type="submit" class="btn">Submit Reply</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="status-notice">
                Please <a href="<?= $baseUrl ?>/login">log in</a> to participate in this discussion.
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .font-standard {
        font-family: var(--font-family);
    }
    
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

    .thread-header-title {
        margin-bottom: 2rem;
    }

    .thread-header-title h2 {
        font-size: 1.85rem;
        font-weight: 800;
        margin: 0;
        letter-spacing: -0.02em;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .locked-badge {
        font-size: 1.25rem;
        opacity: 0.6;
    }

    .posts-timeline {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        margin-bottom: 3rem;
    }

    .post-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 14px;
        display: flex;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        overflow: hidden;
    }

    .post-author-sidebar {
        width: 140px;
        padding: 1.5rem;
        border-right: 1px solid var(--glass-border);
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        background: rgba(31, 41, 55, 0.1);
        flex-shrink: 0;
    }

    .post-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        border: 2px solid var(--glass-border);
        object-fit: cover;
        margin-bottom: 0.75rem;
    }

    .post-author-name a {
        color: var(--text-main);
        text-decoration: none;
        font-weight: 700;
        font-size: 0.88rem;
        word-break: break-all;
    }

    .post-author-name a:hover {
        color: var(--primary);
    }

    .post-reputation {
        font-size: 0.78rem;
        color: var(--primary);
        font-weight: 600;
        margin-top: 0.25rem;
    }

    .post-details {
        flex: 1;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .post-header {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-bottom: 1rem;
    }

    .post-body-content {
        line-height: 1.6;
        font-size: 0.95rem;
        color: var(--text-main);
        flex: 1;
    }

    .post-body-content blockquote {
        background: rgba(15, 23, 42, 0.5);
        border-left: 3px solid var(--primary);
        padding: 0.75rem 1.25rem;
        margin: 1rem 0;
        border-radius: 0 8px 8px 0;
        font-style: italic;
        color: #d1d5db;
    }

    .reactions-container {
        margin-top: 1.5rem;
    }

    .reactions-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
    }

    .reaction-pill {
        background: rgba(31, 41, 55, 0.5);
        border: 1px solid var(--glass-border);
        padding: 0.25rem 0.65rem;
        border-radius: 99px;
        font-size: 0.78rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    .post-footer-controls {
        border-top: 1px solid var(--glass-border);
        margin-top: 1.25rem;
        padding-top: 0.85rem;
        display: flex;
        gap: 1.25rem;
        align-items: center;
    }

    .control-btn {
        background: none;
        border: none;
        color: var(--text-muted);
        font-size: 0.82rem;
        cursor: pointer;
        font-weight: 600;
        padding: 0;
        font-family: inherit;
        text-decoration: none;
        transition: color 0.2s;
    }

    .control-btn:hover {
        color: var(--primary);
    }

    .control-btn.danger-btn:hover {
        color: var(--error);
    }

    /* Reactions dropdown */
    .reaction-trigger {
        position: relative;
        display: inline-block;
    }

    .reaction-menu {
        display: none;
        position: absolute;
        bottom: 100%;
        left: 0;
        background: rgba(17, 24, 39, 0.95);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border: 1px solid var(--card-border);
        border-radius: 30px;
        padding: 0.35rem 0.65rem;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
        z-index: 20;
        white-space: nowrap;
        margin-bottom: 0.35rem;
    }

    .reaction-trigger:hover .reaction-menu {
        display: flex;
        gap: 0.4rem;
        animation: floatUp 0.15s ease-out;
    }

    @keyframes floatUp {
        from { transform: translateY(5px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .reaction-form {
        display: inline;
        margin: 0;
        padding: 0;
    }

    .react-submit-btn {
        background: none;
        border: none;
        padding: 0.15rem;
        font-size: 1.35rem;
        cursor: pointer;
        transition: transform 0.15s;
    }

    .react-submit-btn:hover {
        transform: scale(1.35) rotate(-5deg);
    }

    /* Editor box styling */
    .reply-editor-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 14px;
        padding: 2rem;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }

    .reply-editor-card h3 {
        margin-top: 0;
        margin-bottom: 1.25rem;
        font-size: 1.25rem;
        font-weight: 700;
    }

    .status-notice {
        background: rgba(31, 41, 55, 0.5);
        border: 1px solid var(--card-border);
        border-radius: 12px;
        padding: 1.5rem;
        text-align: center;
        color: var(--text-muted);
        font-weight: 500;
    }

    .status-notice a {
        color: var(--primary);
        font-weight: 600;
        text-decoration: none;
    }

    /* Editor Toolbar */
    .wysiwyg-toolbar {
        background: rgba(15, 23, 42, 0.6);
        border: 1px solid var(--glass-border);
        border-bottom: none;
        border-radius: 8px 8px 0 0;
        padding: 0.5rem;
        display: flex;
        gap: 0.25rem;
    }

    .toolbar-btn {
        background: none;
        border: none;
        color: var(--text-muted);
        padding: 0.35rem 0.65rem;
        border-radius: 4px;
        cursor: pointer;
        font-family: inherit;
        font-size: 0.95rem;
        transition: background-color 0.2s, color 0.2s;
    }

    .toolbar-btn:hover {
        background-color: rgba(31, 41, 55, 0.6);
        color: var(--primary);
    }

    #reply-textarea {
        border-radius: 0 0 8px 8px;
    }

    /* Autocomplete mention popup overlay */
    .mention-overlay {
        position: absolute;
        background: rgba(17, 24, 39, 0.95);
        border: 1px solid var(--card-border);
        border-radius: 8px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        width: 200px;
        z-index: 1000;
        max-height: 160px;
        overflow-y: auto;
    }

    .mention-item {
        padding: 0.5rem 0.75rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.85rem;
        transition: background-color 0.15s;
    }

    .mention-item:hover, .mention-item.selected {
        background-color: var(--primary);
        color: white;
    }

    .mention-avatar {
        width: 20px;
        height: 20px;
        border-radius: 50%;
    }

    @media (max-width: 768px) {
        .post-card {
            flex-direction: column;
        }

        .post-author-sidebar {
            width: auto;
            border-right: none;
            border-bottom: 1px solid var(--glass-border);
            flex-direction: row;
            text-align: left;
            padding: 1rem;
            gap: 1rem;
        }

        .post-avatar {
            width: 40px;
            height: 40px;
            margin-bottom: 0;
        }
    }
</style>

<script>
    function quotePost(username, bodyId) {
        var bodyElement = document.getElementById(bodyId);
        var replyArea = document.getElementById('reply-textarea');
        if (bodyElement && replyArea) {
            // Get content text (or clean innerHTML)
            var content = bodyElement.innerHTML;
            // Clean quote nested blocks to prevent excessive depth
            var tempDiv = document.createElement('div');
            tempDiv.innerHTML = content;
            var blockquotes = tempDiv.getElementsByTagName('blockquote');
            while(blockquotes[0]) {
                blockquotes[0].parentNode.removeChild(blockquotes[0]);
            }
            var cleanedContent = tempDiv.innerHTML.trim();

            var quote = '<blockquote><strong>@' + username + '</strong>:<br>' + cleanedContent + '</blockquote><p></p>\n';
            replyArea.value = replyArea.value + quote;
            replyArea.focus();
            
            // Scroll to reply block
            document.getElementById('reply-form').scrollIntoView({ behavior: 'smooth' });
        }
    }
</script>
