<div class="forum-layout">
    <div class="main-content">
        <div class="section-header">
            <h2>Start New Conversation</h2>
        </div>

        <div class="new-conv-card">
            <?php if (!empty($error)): ?>
                <div class="alert" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: var(--error); padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="<?= $baseUrl ?>/messages/new" method="POST" class="new-conv-form">
                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label for="recipient" style="font-size:0.85rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; margin-bottom:0.5rem; display:block;">Recipient Username</label>
                    <input type="text" id="recipient" name="recipient" class="form-control" placeholder="Enter username..." value="<?= htmlspecialchars($to) ?>" required style="background: rgba(17, 24, 39, 0.5); border: 1px solid var(--card-border); border-radius: 6px; color: var(--text-main); padding: 0.75rem 1rem; width:100%; box-sizing:border-box;">
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="body" style="font-size:0.85rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; margin-bottom:0.5rem; display:block;">Message Body</label>
                    <textarea id="body" name="body" class="form-control" rows="6" placeholder="Write your message here..." required style="background: rgba(17, 24, 39, 0.5); border: 1px solid var(--card-border); border-radius: 6px; color: var(--text-main); padding: 0.75rem 1rem; width:100%; box-sizing:border-box; resize:vertical; font-family:inherit;"></textarea>
                </div>

                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn btn-primary" style="padding:0.75rem 1.5rem; border-radius:6px; font-weight:600;">Send Message</button>
                    <a href="<?= $baseUrl ?>/messages" class="btn btn-secondary" style="padding:0.75rem 1.5rem; border-radius:6px; font-weight:600; text-decoration:none; background:#4b5563; color:#fff; border:none; text-align:center;">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Render Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>
</div>

<style>
    .new-conv-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 12px;
        padding: 2rem;
        backdrop-filter: blur(8px);
    }
</style>
