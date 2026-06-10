<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <!-- Modern typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: rgba(17, 24, 39, 0.7);
            --card-border: rgba(31, 41, 55, 0.6);
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --primary: <?= $accentColor ?>;
            --primary-hover: <?= $accentHover ?>;
            --glass-bg: rgba(17, 24, 39, 0.65);
            --glass-border: rgba(255, 255, 255, 0.05);
            --error: #ef4444;
            --success: #10b981;
            --font-family: 'Outfit', system-ui, -apple-system, sans-serif;
        }

        *:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        body {
            background-color: var(--bg-color);
            background-image: radial-gradient(at 0% 0%, rgba(16, 185, 129, 0.05) 0px, transparent 50%),
                              radial-gradient(at 50% 0%, rgba(59, 130, 246, 0.05) 0px, transparent 50%);
            color: var(--text-main);
            font-family: var(--font-family);
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Glassmorphism Navigation */
        header.nav-header {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--glass-border);
            padding: 0.85rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        header.nav-header h1 {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary) 0%, #3b82f6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.05em;
        }

        header.nav-header h1 a {
            text-decoration: none;
            color: inherit;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .nav-links a {
            color: var(--text-main);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: color 0.2s, transform 0.2s;
            position: relative;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .nav-search {
            position: relative;
            max-width: 280px;
            width: 100%;
        }

        .nav-search input {
            width: 100%;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--glass-border);
            border-radius: 99px;
            padding: 0.45rem 1rem 0.45rem 2.2rem;
            color: var(--text-main);
            font-size: 0.85rem;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .nav-search input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
        }

        .nav-search svg {
            position: absolute;
            left: 0.8rem;
            top: 50%;
            transform: translateY(-50%);
            width: 14px;
            height: 14px;
            fill: var(--text-muted);
        }

        .badge {
            background: var(--error);
            color: white;
            border-radius: 99px;
            padding: 0.15rem 0.45rem;
            font-size: 0.7rem;
            font-weight: 700;
            position: absolute;
            top: -8px;
            right: -12px;
            box-shadow: 0 0 5px rgba(239, 68, 68, 0.5);
            display: inline-block;
        }

        .wrapper {
            max-width: 1150px;
            width: 100%;
            margin: 2rem auto;
            padding: 0 1.5rem;
            flex: 1 0 auto;
            box-sizing: border-box;
        }

        /* Common Elements styling */
        .btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.25rem;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: opacity 0.2s, transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background: rgba(31, 41, 55, 0.7);
            border: 1px solid var(--card-border);
            box-shadow: none;
        }

        .btn-secondary:hover {
            background: rgba(55, 65, 81, 0.8);
            box-shadow: none;
        }

        .btn-small {
            padding: 0.45rem 0.9rem;
            font-size: 0.8rem;
            border-radius: 6px;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 2rem;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.45rem;
            font-size: 0.85rem;
            font-weight: 500;
            color: #d1d5db;
        }

        .form-control {
            width: 100%;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            padding: 0.7rem 0.9rem;
            color: var(--text-main);
            font-size: 0.9rem;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.15);
        }

        .alert {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--error);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: var(--success);
        }
    </style>
</head>
<body>
    <header class="nav-header">
        <h1><a href="<?= $baseUrl ?>/">FORUX</a></h1>
        
        <form action="<?= $baseUrl ?>/search" method="GET" class="nav-search">
            <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
            <input type="text" name="q" placeholder="Search threads..." id="nav-search-input" aria-label="Search threads">
        </form>

        <div class="nav-links">
            <a href="<?= $baseUrl ?>/">Forum</a>
            <?php if ($currentUser): ?>
                <?php $avatar = $currentUser['avatar_url'] ?: 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($currentUser['email']))) . '?d=mp&s=32'; ?>
                <div style="display:flex; align-items:center; gap:0.5rem; background:rgba(255,255,255,0.05); padding:0.35rem 0.75rem; border-radius:30px;">
                    <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" style="width:24px; height:24px; border-radius:50%; object-fit:cover;">
                    <a href="<?= $baseUrl ?>/user/<?= htmlspecialchars($currentUser['username']) ?>" style="font-weight:600; font-size:0.85rem;">@<?= htmlspecialchars($currentUser['username']) ?></a>
                </div>
                <a href="#" style="position: relative;" id="notification-bell" aria-label="View notifications">
                    Notifications
                    <span class="badge" id="notification-badge" style="display: <?= $unreadNotificationsCount > 0 ? 'inline-block' : 'none' ?>;">
                        <?= $unreadNotificationsCount ?>
                    </span>
                </a>
                <?php if ($isAdmin): ?>
                    <a href="<?= $baseUrl ?>/<?= $adminPath ?>/dashboard" style="color: #60a5fa;">Admin Control</a>
                <?php endif; ?>
                <form action="<?= $baseUrl ?>/logout" method="POST" style="display: inline; margin: 0;">
                    <button type="submit" aria-label="Log out of account" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-family: inherit; font-size: 0.9rem; font-weight: 500; padding: 0; transition: color 0.2s;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'">
                        Logout
                    </button>
                </form>
            <?php else: ?>
                <a href="<?= $baseUrl ?>/login">Login</a>
                <a href="<?= $baseUrl ?>/register" class="btn btn-small" style="box-shadow: none;">Register</a>
            <?php endif; ?>
        </div>
    </header>

    <div class="wrapper">
        <?= $content ?>
    </div>

    <footer style="margin-top: auto; padding: 2.5rem 0; border-top: 1px solid var(--glass-border); text-align: center; font-size: 0.85rem; color: var(--text-muted); display: flex; flex-direction: column; align-items: center; gap: 0.65rem; background: rgba(11, 15, 25, 0.5); backdrop-filter: blur(8px);">
        <div>&copy; <?= date('Y') ?> Forux Forum. All rights reserved.</div>
        <div style="display: flex; gap: 1rem; align-items: center;">
            <span><?= __('common.language') ?>:</span>
            <?php
            $currentLocale = \Core\Language::getInstance()->getLocale();
            ?>
            <a href="?lang=en" style="color: <?= $currentLocale === 'en' ? 'var(--primary)' : 'var(--text-muted)' ?>; text-decoration: none; font-weight: <?= $currentLocale === 'en' ? '700' : 'normal' ?>;">English</a>
            <span style="color: var(--glass-border);">|</span>
            <a href="?lang=es" style="color: <?= $currentLocale === 'es' ? 'var(--primary)' : 'var(--text-muted)' ?>; text-decoration: none; font-weight: <?= $currentLocale === 'es' ? '700' : 'normal' ?>;">Español</a>
            <span style="color: var(--glass-border);">|</span>
            <a href="?lang=fr" style="color: <?= $currentLocale === 'fr' ? 'var(--primary)' : 'var(--text-muted)' ?>; text-decoration: none; font-weight: <?= $currentLocale === 'fr' ? '700' : 'normal' ?>;">Français</a>
        </div>
    </footer>

    <!-- Pass base URL dynamically to JavaScript -->
    <script>
        window.FORUX_BASE_URL = <?= json_encode($baseUrl, JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <!-- Theme JS for active theme components -->
    <script src="<?= $baseUrl ?>/themes/default/assets/theme.js"></script>
</body>
</html>
