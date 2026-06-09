<div class="forum-layout">
    <div class="main-content">
        <div class="section-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem;">
            <h2>Conversation with @<?= htmlspecialchars($recipientName) ?></h2>
            <a href="<?= $baseUrl ?>/messages" class="btn btn-secondary" style="padding: 0.5rem 1rem; border-radius: 6px; font-weight:600; text-decoration:none; background:#4b5563; color:#fff; border:none;">Back to Inbox</a>
        </div>

        <div class="chat-wrapper">
            <div class="messages-container" id="messages-box">
                <?php foreach ($messages as $msg): ?>
                    <?php 
                        $avatar = $msg['sender_avatar'] ?: 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($msg['sender_name']))) . '?d=mp&s=40';
                        $isMe = $currentUser && (int)$msg['sender_id'] === (int)$currentUser['id'];
                    ?>
                    <div class="message-bubble-wrapper <?= $isMe ? 'message-me' : 'message-them' ?>">
                        <img src="<?= htmlspecialchars($avatar) ?>" alt="Sender Avatar" class="user-avatar-sm">
                        <div class="message-bubble">
                            <div class="message-sender">@<?= htmlspecialchars($msg['sender_name']) ?></div>
                            <div class="message-text"><?= nl2br(htmlspecialchars($msg['body'])) ?></div>
                            <div class="message-time"><?= date('M d, g:i A', strtotime($msg['created_at'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: var(--error); padding: 0.75rem 1rem; border-radius: 8px; margin: 1rem 0; font-size: 0.9rem;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="quick-reply-wrapper">
                <form action="<?= $baseUrl ?>/messages/<?= $conversationId ?>" method="POST" class="reply-form">
                    <textarea name="body" placeholder="Write a reply..." required rows="3"></textarea>
                    <button type="submit" class="btn btn-primary">Send</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Render Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>
</div>

<style>
    .chat-wrapper {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 12px;
        display: flex;
        flex-direction: column;
        height: 600px;
        backdrop-filter: blur(8px);
        overflow: hidden;
    }

    .messages-container {
        flex: 1;
        padding: 1.5rem;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
        background: rgba(17, 24, 39, 0.2);
    }

    .message-bubble-wrapper {
        display: flex;
        gap: 0.75rem;
        max-width: 75%;
    }

    .message-them {
        align-self: flex-start;
    }

    .message-me {
        align-self: flex-end;
        flex-direction: row-reverse;
    }

    .user-avatar-sm {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 2px solid var(--card-border);
        object-fit: cover;
    }

    .message-bubble {
        padding: 0.75rem 1rem;
        border-radius: 12px;
        font-size: 0.95rem;
        line-height: 1.4;
        position: relative;
    }

    .message-them .message-bubble {
        background: #1f2937;
        color: var(--text-main);
        border-top-left-radius: 0;
        border: 1px solid var(--card-border);
    }

    .message-me .message-bubble {
        background: var(--primary);
        color: #fff;
        border-top-right-radius: 0;
    }

    .message-sender {
        font-size: 0.75rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
        opacity: 0.75;
    }

    .message-time {
        font-size: 0.7rem;
        text-align: right;
        margin-top: 0.35rem;
        opacity: 0.6;
    }

    .quick-reply-wrapper {
        border-top: 1px solid var(--card-border);
        padding: 1rem;
        background: var(--card-bg);
    }

    .reply-form {
        display: flex;
        gap: 1rem;
    }

    .reply-form textarea {
        flex: 1;
        background: rgba(17, 24, 39, 0.5);
        border: 1px solid var(--card-border);
        border-radius: 6px;
        color: var(--text-main);
        padding: 0.75rem;
        font-size: 0.95rem;
        resize: none;
        font-family: inherit;
    }

    .reply-form textarea:focus {
        border-color: var(--primary);
        outline: none;
    }

    .reply-form button {
        padding: 0 1.5rem;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        border: none;
        background: var(--primary);
        color: #fff;
    }
</style>

<script>
    // Scroll chat to bottom on load
    document.addEventListener("DOMContentLoaded", function() {
        var box = document.getElementById("messages-box");
        if (box) {
            box.scrollTop = box.scrollHeight;
        }
    });
</script>
