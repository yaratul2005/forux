<?php
/**
 * Forux Installer Wizard
 * 
 * Safe and secure web installation script.
 */

define('ROOT_PATH', dirname(__DIR__));

// Start session to store step data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Enforce installed.lock security check
if (file_exists(ROOT_PATH . '/storage/installed.lock')) {
    http_response_code(403);
    die('<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Installation Locked</title>
        <style>
            body { background: #0b0f19; color: #f3f4f6; font-family: system-ui, -apple-system, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
            .card { background: #111827; border: 1px solid #1f2937; padding: 2.5rem; border-radius: 12px; max-width: 480px; text-align: center; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5); }
            h1 { color: #ef4444; margin-top: 0; font-size: 1.75rem; }
            p { color: #9ca3af; line-height: 1.5; font-size: 0.95rem; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>Installation Locked</h1>
            <p>Forux is already installed. If you wish to re-install, please delete the lock file at <code>storage/installed.lock</code>.</p>
        </div>
    </body>
    </html>');
}

// Determine active step
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// Helper: Check requirements
function getRequirements(): array
{
    $phpVersion = phpversion();
    $phpOk = version_compare($phpVersion, '8.2.0', '>=');

    $extensions = [
        'pdo' => extension_loaded('pdo'),
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'openssl' => extension_loaded('openssl'),
        'curl' => extension_loaded('curl'),
        'mbstring' => extension_loaded('mbstring'),
        'json' => extension_loaded('json'),
        'gd' => extension_loaded('gd') || extension_loaded('imagick'),
    ];

    $directories = [
        'storage' => is_writable(ROOT_PATH . '/storage'),
        'storage/cache' => is_writable(ROOT_PATH . '/storage/cache'),
        'storage/logs' => is_writable(ROOT_PATH . '/storage/logs'),
        'storage/sessions' => is_writable(ROOT_PATH . '/storage/sessions'),
        'storage/uploads' => is_writable(ROOT_PATH . '/storage/uploads'),
        'config' => is_writable(ROOT_PATH . '/config'),
    ];

    $allOk = $phpOk && !in_array(false, $extensions, true) && !in_array(false, $directories, true);

    return [
        'php_version' => $phpVersion,
        'php_ok' => $phpOk,
        'extensions' => $extensions,
        'directories' => $directories,
        'all_ok' => $allOk
    ];
}

$reqs = getRequirements();

// Process POST actions per step
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 2) {
        // Handle DB Connection
        $host = trim($_POST['db_host'] ?? '127.0.0.1');
        $port = trim($_POST['db_port'] ?? '3306');
        $database = trim($_POST['db_name'] ?? '');
        $username = trim($_POST['db_user'] ?? '');
        $password = $_POST['db_pass'] ?? '';

        if (empty($database) || empty($username)) {
            $error = 'Database Name and Username are required.';
        } else {
            try {
                // Test connection
                $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
                $pdo = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 3
                ]);

                // Store credentials in session
                $_SESSION['db'] = [
                    'host' => $host,
                    'port' => $port,
                    'database' => $database,
                    'username' => $username,
                    'password' => $password,
                    'charset' => 'utf8mb4'
                ];

                // Write config/config.php
                $encKey = bin2hex(random_bytes(16)); // Generate 32-char hex key
                $configArray = [
                    'app' => [
                        'name' => 'Forux Forum',
                        'env' => 'production',
                        'debug' => false,
                        'url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . str_replace('/install/index.php', '/public', $_SERVER['SCRIPT_NAME']),
                        'admin_path' => 'admin_' . bin2hex(random_bytes(4)),
                        'timezone' => 'UTC',
                    ],
                    'db' => $_SESSION['db'],
                    'security' => [
                        'encryption_key' => $encKey,
                    ],
                    'session' => [
                        'name' => 'forux_session',
                        'lifetime' => 86400,
                        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                        'httponly' => true,
                        'samesite' => 'Strict',
                    ]
                ];

                $configContent = "<?php\n\nreturn " . var_export($configArray, true) . ";\n";
                file_put_contents(ROOT_PATH . '/config/config.php', $configContent);

                header('Location: index.php?step=3');
                exit;

            } catch (PDOException $e) {
                $error = 'Database connection failed: ' . $e->getMessage();
            }
        }
    } elseif ($step === 3) {
        // Execute Migrations
        if (!isset($_SESSION['db'])) {
            header('Location: index.php?step=2');
            exit;
        }

        try {
            $db = $_SESSION['db'];
            $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset={$db['charset']}";
            $pdo = new PDO($dsn, $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            // Track migrations
            $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // Load and run each migration
            $files = glob(ROOT_PATH . '/install/migrations/*.php');
            sort($files);

            $stmt = $pdo->query("SELECT migration FROM migrations");
            $executed = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($files as $file) {
                $name = basename($file);
                if (!in_array($name, $executed)) {
                    $migration = require $file;
                    $migration['up']($pdo);
                    
                    $insert = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
                    $insert->execute([$name]);
                }
            }

            header('Location: index.php?step=4');
            exit;

        } catch (Throwable $e) {
            $error = 'Migration execution failed: ' . $e->getMessage();
        }
    } elseif ($step === 4) {
        // Setup Admin Account
        $adminUser = trim($_POST['admin_user'] ?? '');
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminPass = $_POST['admin_pass'] ?? '';
        $adminConfirm = $_POST['admin_confirm'] ?? '';

        if (empty($adminUser) || empty($adminEmail) || empty($adminPass)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($adminPass !== $adminConfirm) {
            $error = 'Passwords do not match.';
        } elseif (strlen($adminPass) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else {
            try {
                $db = $_SESSION['db'];
                $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset={$db['charset']}";
                $pdo = new PDO($dsn, $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

                // Hash password
                $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);

                // Insert User
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password_hash, email_verified_at) 
                    VALUES (?, ?, ?, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([$adminUser, $adminEmail, $hash]);
                $userId = $pdo->lastInsertId();

                // Associate with Super Admin role (ID = 6)
                $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, 6)");
                $stmt->execute([$userId]);

                // 2. Clear settings cache to force reload on next boot
                @unlink(ROOT_PATH . '/storage/cache/settings.php');

                // 3. Write lock file
                file_put_contents(ROOT_PATH . '/storage/installed.lock', date('Y-m-d H:i:s'));

                // Clear session
                session_destroy();

                header('Location: index.php?step=5');
                exit;

            } catch (Throwable $e) {
                $error = 'Admin registration failed: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forux Installer Wizard</title>
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: #111827;
            --card-border: #1f2937;
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --primary: #10b981; /* Emerald */
            --primary-hover: #059669;
            --error: #ef4444;
            --success: #10b981;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: 'Outfit', system-ui, -apple-system, sans-serif;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
            box-sizing: border-box;
        }

        .container {
            width: 100%;
            max-width: 580px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4), 0 10px 10px -5px rgba(0, 0, 0, 0.4);
            padding: 2.5rem;
            box-sizing: border-box;
        }

        header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        header h1 {
            margin: 0;
            font-size: 2.25rem;
            font-weight: 800;
            background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.05em;
        }

        header p {
            margin: 0.5rem 0 0 0;
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        /* Steps progress bar */
        .progress-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2.5rem;
            position: relative;
        }

        .progress-bar::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 5%;
            right: 5%;
            height: 2px;
            background: var(--card-border);
            z-index: 1;
        }

        .step-node {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #1f2937;
            border: 2px solid var(--card-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-muted);
            z-index: 2;
            position: relative;
        }

        .step-node.active {
            background: #065f46;
            border-color: var(--primary);
            color: #fff;
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.3);
        }

        .step-node.completed {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }

        /* Alert styling */
        .alert {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error);
            border-radius: 8px;
            color: #f87171;
            padding: 1rem;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            line-height: 1.4;
        }

        /* Form elements */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #d1d5db;
        }

        .form-control {
            width: 100%;
            background: #0f172a;
            border: 1px solid var(--card-border);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            color: var(--text-main);
            font-size: 0.95rem;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }

        .btn {
            width: 100%;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.85rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
        }

        .btn:hover:not(:disabled) {
            background-color: var(--primary-hover);
        }

        .btn:disabled {
            background: #1f2937;
            color: #4b5563;
            cursor: not-allowed;
        }

        /* Checklist style */
        .check-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #1f2937;
            font-size: 0.9rem;
        }

        .check-item:last-child {
            border-bottom: none;
        }

        .check-item .status {
            font-weight: 700;
        }

        .check-item .status.pass {
            color: var(--success);
        }

        .check-item .status.fail {
            color: var(--error);
        }

        .badge {
            font-size: 0.75rem;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-weight: 600;
        }

        .badge.pass { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .badge.fail { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        
        .code-box {
            background: #0f172a;
            border: 1px solid var(--card-border);
            border-radius: 8px;
            padding: 1rem;
            max-height: 180px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 0.8rem;
            margin-bottom: 1.5rem;
        }

        .code-box div {
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>FORUX</h1>
            <p>Community Forum Setup Wizard</p>
        </header>

        <div class="progress-bar">
            <div class="step-node <?php echo $step === 1 ? 'active' : ($step > 1 ? 'completed' : ''); ?>">1</div>
            <div class="step-node <?php echo $step === 2 ? 'active' : ($step > 2 ? 'completed' : ''); ?>">2</div>
            <div class="step-node <?php echo $step === 3 ? 'active' : ($step > 3 ? 'completed' : ''); ?>">3</div>
            <div class="step-node <?php echo $step === 4 ? 'active' : ($step > 4 ? 'completed' : ''); ?>">4</div>
            <div class="step-node <?php echo $step === 5 ? 'active' : ''; ?>">5</div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- STEP 1: Readiness Check -->
        <?php if ($step === 1): ?>
            <h3>1. Environment Verification</h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top:-0.5rem; margin-bottom:1.5rem;">We verify if your host meets the platform specifications.</p>
            
            <div style="margin-bottom: 2rem;">
                <div class="check-item">
                    <span>PHP Version (>= 8.2.0)</span>
                    <span class="status <?php echo $reqs['php_ok'] ? 'pass' : 'fail'; ?>">
                        <span class="badge <?php echo $reqs['php_ok'] ? 'pass' : 'fail'; ?>"><?php echo $reqs['php_version']; ?></span>
                    </span>
                </div>
                <?php foreach ($reqs['extensions'] as $ext => $loaded): ?>
                    <div class="check-item">
                        <span>Extension: <code><?php echo $ext; ?></code></span>
                        <span class="status <?php echo $loaded ? 'pass' : 'fail'; ?>">
                            <span class="badge <?php echo $loaded ? 'pass' : 'fail'; ?>"><?php echo $loaded ? 'Available' : 'Missing'; ?></span>
                        </span>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($reqs['directories'] as $dir => $writable): ?>
                    <div class="check-item">
                        <span>Directory write: <code><?php echo $dir; ?></code></span>
                        <span class="status <?php echo $writable ? 'pass' : 'fail'; ?>">
                            <span class="badge <?php echo $writable ? 'pass' : 'fail'; ?>"><?php echo $writable ? 'Writable' : 'Locked'; ?></span>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <button onclick="window.location.href='index.php?step=2'" class="btn" <?php echo !$reqs['all_ok'] ? 'disabled' : ''; ?>>
                <?php echo $reqs['all_ok'] ? 'Continue Setup' : 'Resolve Issues to Continue'; ?>
            </button>

        <!-- STEP 2: Database Credentials -->
        <?php elseif ($step === 2): ?>
            <h3>2. Database Configuration</h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top:-0.5rem; margin-bottom:1.5rem;">Enter database connection details. These will be encrypted and written to <code>config/config.php</code>.</p>
            
            <form method="POST">
                <div class="form-group">
                    <label>Database Host</label>
                    <input type="text" name="db_host" class="form-control" value="127.0.0.1" required>
                </div>
                <div class="form-group" style="display: flex; gap: 1rem;">
                    <div style="flex: 2;">
                        <label>Database Name</label>
                        <input type="text" name="db_name" class="form-control" placeholder="forux_db" required>
                    </div>
                    <div style="flex: 1;">
                        <label>Port</label>
                        <input type="text" name="db_port" class="form-control" value="3306" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Database Username</label>
                    <input type="text" name="db_user" class="form-control" placeholder="root" required>
                </div>
                <div class="form-group">
                    <label>Database Password</label>
                    <input type="password" name="db_pass" class="form-control" placeholder="Leave empty if none">
                </div>
                <button type="submit" class="btn" style="margin-top: 1rem;">Test Connection & Save</button>
            </form>

        <!-- STEP 3: Migrations -->
        <?php elseif ($step === 3): ?>
            <h3>3. Database Initialization</h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top:-0.5rem; margin-bottom:1.5rem;">We are executing the SQL migrations to build the tables, indices, and seed baseline settings.</p>
            
            <div class="code-box">
                <div style="color: #34d399;">✔ Database connection established.</div>
                <div>Ready to run 8 migration steps.</div>
                <div style="color: #9ca3af;">- 001_create_identity_tables.php</div>
                <div style="color: #9ca3af;">- 002_create_forum_tables.php</div>
                <div style="color: #9ca3af;">- 003_create_interaction_tables.php</div>
                <div style="color: #9ca3af;">- 004_create_messaging_tables.php</div>
                <div style="color: #9ca3af;">- 005_create_moderation_tables.php</div>
                <div style="color: #9ca3af;">- 006_create_notification_tables.php</div>
                <div style="color: #9ca3af;">- 007_create_cms_config_tables.php</div>
                <div style="color: #9ca3af;">- 008_create_credentials_vault_table.php</div>
            </div>

            <form method="POST">
                <button type="submit" class="btn">Execute Migrations</button>
            </form>

        <!-- STEP 4: Admin Setup -->
        <?php elseif ($step === 4): ?>
            <h3>4. Super Admin Configuration</h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top:-0.5rem; margin-bottom:1.5rem;">Create the primary administrative account which will have full owner privileges.</p>
            
            <form method="POST">
                <div class="form-group">
                    <label>Admin Username</label>
                    <input type="text" name="admin_user" class="form-control" placeholder="e.g. admin" required>
                </div>
                <div class="form-group">
                    <label>Admin Email</label>
                    <input type="email" name="admin_email" class="form-control" placeholder="admin@domain.com" required>
                </div>
                <div class="form-group">
                    <label>Password (Min. 8 chars)</label>
                    <input type="password" name="admin_pass" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="admin_confirm" class="form-control" required>
                </div>
                <button type="submit" class="btn" style="margin-top: 1rem;">Finalize Setup</button>
            </form>

        <!-- STEP 5: Complete -->
        <?php elseif ($step === 5): ?>
            <div style="text-align: center; padding: 1rem 0;">
                <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(16, 185, 129, 0.1); border: 2px solid var(--primary); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem auto;">
                    <span style="color: var(--primary); font-size: 2rem; font-weight: 700;">✔</span>
                </div>
                <h3>5. Installation Complete!</h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; line-height: 1.6; margin-bottom: 2rem;">
                    Forux Forum has been successfully installed on your host. For security, the installer has been locked out.
                </p>
                <button onclick="window.location.href='index.php'" class="btn">Go to Forum Homepage</button>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
