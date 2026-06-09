<div class="profile-container font-standard">
    <div class="card profile-card">
        <div class="profile-header">
            <?php 
            $avatar = $user['avatar_url'] ?: 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user['email']))) . '?d=mp&s=120';
            ?>
            <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="profile-avatar">
            <h2>@<?= htmlspecialchars($user['username']) ?></h2>
            <div class="profile-date">Member since <?= date('M Y', strtotime($user['created_at'])) ?></div>
        </div>
        
        <div class="profile-body">
            <div class="profile-meta-row">
                <span class="meta-label">REPUTATION POINTS</span>
                <span class="meta-value">★ <?= (int)($user['reputation_points'] ?? 0) ?></span>
            </div>
            
            <div class="profile-meta-row">
                <span class="meta-label">LOCATION</span>
                <span class="meta-value"><?= $user['location'] ? htmlspecialchars($user['location']) : '<em>Not specified</em>' ?></span>
            </div>
            
            <div class="profile-meta-row bio-row">
                <span class="meta-label">ABOUT ME</span>
                <div class="bio-text"><?= $user['bio'] ? htmlspecialchars($user['bio']) : '<em>No bio provided yet.</em>' ?></div>
            </div>
        </div>
        
        <div class="profile-footer">
            <?php if ($currentUser && (int)$currentUser['id'] === (int)$user['id']): ?>
                <a href="<?= $baseUrl ?>/settings" class="btn btn-secondary btn-small">Edit Profile</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .font-standard {
        font-family: var(--font-family);
    }

    .profile-container {
        display: flex;
        justify-content: center;
        margin: 2rem 0;
    }
    
    .profile-card {
        width: 100%;
        max-width: 500px;
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 16px;
        padding: 3rem 2.5rem 2.5rem 2.5rem;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
    }
    
    .profile-header {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-bottom: 2rem;
        text-align: center;
    }
    
    .profile-avatar {
        width: 96px;
        height: 96px;
        border-radius: 50%;
        border: 3px solid var(--primary);
        background: #1f2937;
        margin-bottom: 1rem;
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.2);
    }
    
    .profile-header h2 {
        margin: 0 0 0.25rem 0;
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--text-main);
    }
    
    .profile-date {
        font-size: 0.85rem;
        color: var(--text-muted);
    }
    
    .profile-body {
        border-top: 1px solid var(--glass-border);
        padding-top: 1.5rem;
    }
    
    .profile-meta-row {
        margin-bottom: 1.25rem;
        display: flex;
        flex-direction: column;
    }
    
    .meta-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--text-muted);
        letter-spacing: 0.05em;
        margin-bottom: 0.25rem;
    }
    
    .meta-value {
        font-size: 1rem;
        color: var(--text-main);
    }
    
    .meta-value strong {
        color: var(--primary);
    }
    
    .bio-text {
        font-size: 0.9rem;
        color: #d1d5db;
        line-height: 1.5;
        background: rgba(15, 23, 42, 0.3);
        border: 1px solid var(--glass-border);
        border-radius: 8px;
        padding: 0.75rem 1rem;
    }
    
    .profile-footer {
        margin-top: 2rem;
        display: flex;
        justify-content: center;
    }
</style>
