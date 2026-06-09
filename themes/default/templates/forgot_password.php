<div class="auth-wrapper">
    <div class="card auth-card">
        <h2>Recover Password</h2>
        <p class="auth-subtitle" style="color: var(--text-muted); font-size: 0.88rem; margin: -0.5rem 0 1.5rem 0; text-align: center;">Enter your email address and we'll send you a password recovery link.</p>
        
        <?php if (!empty($error)): ?>
            <div class="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #10b981; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem;">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form action="<?= $baseUrl ?>/password/reset" method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" required autofocus>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
        </form>

        <div class="auth-footer">
            Remembered your password? <a href="<?= $baseUrl ?>/login">Log In</a>
        </div>
    </div>
</div>
