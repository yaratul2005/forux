<div class="auth-wrapper">
    <div class="card auth-card">
        <h2>Create an Account</h2>
        
        <?php if (!empty($error)): ?>
            <div class="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="<?= $baseUrl ?>/register" method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="choose_username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password (Min. 8 chars)</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">Register Account</button>
        </form>

        <div class="auth-footer">
            Already have an account? <a href="<?= $baseUrl ?>/login">Login here</a>
        </div>
    </div>
</div>

<style>
    .auth-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 70vh;
    }
    
    .auth-card {
        width: 100%;
        max-width: 420px;
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 16px;
        padding: 2.5rem;
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
    }
    
    .auth-card h2 {
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
    
    .btn-block {
        width: 100%;
        margin-top: 0.5rem;
    }
    
    .auth-footer {
        text-align: center;
        font-size: 0.85rem;
        margin-top: 1.5rem;
        color: var(--text-muted);
    }
    
    .auth-footer a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 600;
        transition: opacity 0.2s;
    }
    
    .auth-footer a:hover {
        opacity: 0.8;
    }
</style>
