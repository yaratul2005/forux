<div class="settings-container font-standard">
    <div class="card settings-card">
        <h2><?= __('common.settings') ?></h2>
        
        <?php if (!empty($error)): ?>
            <div class="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <div class="settings-user-pill">
            <?php 
            $avatar = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user['email']))) . '?d=mp&s=80';
            ?>
            <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="settings-avatar">
            <div class="settings-user-info">
                <span class="settings-username">@<?= htmlspecialchars($user['username']) ?></span>
                <span class="settings-email"><?= htmlspecialchars($user['email']) ?></span>
            </div>
        </div>

        <form action="<?= $baseUrl ?>/settings" method="POST">
            <div class="form-group">
                <label for="location">Location</label>
                <input type="text" id="location" name="location" class="form-control" value="<?= htmlspecialchars($user['location'] ?? '') ?>" placeholder="e.g. London, UK">
            </div>
            
            <div class="form-group">
                <label for="bio">About Me (Bio)</label>
                <textarea id="bio" name="bio" class="form-control" rows="5" placeholder="Tell the community about yourself..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="language"><?= __('common.language') ?></label>
                <select id="language" name="language" class="form-control">
                    <option value="en" <?= ($user['language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                    <option value="es" <?= ($user['language'] ?? 'en') === 'es' ? 'selected' : '' ?>>Español</option>
                    <option value="fr" <?= ($user['language'] ?? 'en') === 'fr' ? 'selected' : '' ?>>Français</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block"><?= __('common.submit') ?></button>
        </form>

        <div class="settings-footer">
            <a href="<?= $baseUrl ?>/user/<?= htmlspecialchars($user['username']) ?>">View Public Profile</a>
        </div>
    </div>
</div>

<style>
    .font-standard {
        font-family: var(--font-family);
    }

    .settings-container {
        display: flex;
        justify-content: center;
        margin: 2rem 0;
    }
    
    .settings-card {
        width: 100%;
        max-width: 480px;
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 16px;
        padding: 2.5rem;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
    }
    
    .settings-card h2 {
        margin-top: 0;
        margin-bottom: 1.5rem;
        font-size: 1.6rem;
        font-weight: 800;
        background: linear-gradient(135deg, var(--primary) 0%, #3b82f6 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        letter-spacing: -0.04em;
        text-align: center;
    }
    
    .settings-user-pill {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        background: rgba(15, 23, 42, 0.4);
        padding: 0.85rem 1rem;
        border-radius: 12px;
        border: 1px solid var(--glass-border);
    }
    
    .settings-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        border: 1px solid var(--glass-border);
    }
    
    .settings-user-info {
        display: flex;
        flex-direction: column;
    }
    
    .settings-username {
        font-weight: 700;
        color: var(--text-main);
        font-size: 0.95rem;
    }
    
    .settings-email {
        font-size: 0.78rem;
        color: var(--text-muted);
        margin-top: 0.1rem;
    }
    
    .btn-block {
        width: 100%;
        margin-top: 0.5rem;
    }
    
    .settings-footer {
        display: flex;
        justify-content: center;
        margin-top: 2rem;
        border-top: 1px solid var(--glass-border);
        padding-top: 1.25rem;
        font-size: 0.85rem;
    }
    
    .settings-footer a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 600;
        transition: opacity 0.2s;
    }
    
    .settings-footer a:hover {
        opacity: 0.8;
    }
</style>
