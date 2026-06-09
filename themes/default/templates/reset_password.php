<div class="auth-wrapper">
    <div class="card auth-card">
        <h2>Reset Password</h2>
        <p class="auth-subtitle" style="color: var(--text-muted); font-size: 0.88rem; margin: -0.5rem 0 1.5rem 0; text-align: center;">Enter your new password below.</p>
        
        <?php if (!empty($error)): ?>
            <div class="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="<?= $baseUrl ?>/password/reset/<?= htmlspecialchars($token) ?>" method="POST">
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required autofocus>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm Password</label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-control" placeholder="••••••••" required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">Update Password</button>
        </form>
    </div>
</div>
