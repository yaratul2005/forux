<?php

namespace Modules\Auth\Services;

use PDO;
use Core\Request;
use Core\Response;
use Core\Hook;
use Exception;

/**
 * Authentication and Session Management Service
 */
class AuthService
{
    protected PDO $pdo;
    protected Request $request;
    protected Hook $hook;
    protected array $config;
    protected ?array $currentUser = null;
    protected bool $resolvedUser = false;
    protected \Core\Container $container;

    /**
     * Clear resolved user cache to reload user data from DB.
     */
    public function refreshUser(): void
    {
        $this->resolvedUser = false;
        $this->currentUser = null;
    }

    /**
     * Create a new AuthService instance.
     */
    public function __construct(PDO $pdo, Request $request, Hook $hook, \Core\Container $container)
    {
        $this->pdo = $pdo;
        $this->request = $request;
        $this->hook = $hook;
        $this->container = $container;
        $this->config = $container->get('config') ?: [];
    }

    /**
     * Check if a user is currently logged in.
     *
     * @return bool
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the currently authenticated user.
     *
     * @return array|null
     */
    public function user(): ?array
    {
        if ($this->resolvedUser) {
            return $this->currentUser;
        }

        $this->resolvedUser = true;
        $sessionCookieName = $this->config['session']['name'] ?? 'forux_session';
        $token = $this->request->cookie($sessionCookieName);

        if (!$token) {
            return null;
        }

        try {
            // Find session in database
            $stmt = $this->pdo->prepare("
                SELECT s.*, u.* FROM user_sessions s
                JOIN users u ON s.user_id = u.id
                WHERE s.id = ? AND u.deleted_at IS NULL AND u.status = 'active'
            ");
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return null;
            }

            // Verify session is not expired
            $lifetime = $this->config['session']['lifetime'] ?? 86400;
            if ($user['last_activity'] + $lifetime < time()) {
                $this->destroySession($token);
                return null;
            }

            // Update session activity timestamp occasionally (throttled)
            if (time() - $user['last_activity'] > 300) { // every 5 minutes
                $update = $this->pdo->prepare("UPDATE user_sessions SET last_activity = ? WHERE id = ?");
                $update->execute([time(), $token]);
            }

            // Remove session properties from the user array before caching
            unset($user['payload'], $user['last_activity']);
            
            $this->currentUser = $user;
            return $this->currentUser;

        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Register a new user member.
     *
     * @param string $username
     * @param string $email
     * @param string $password
     * @return bool
     * @throws Exception
     */
    public function register(string $username, string $email, string $password): bool
    {
        // Check if username already exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            throw new Exception("Username is already taken.");
        }

        // Check if email already exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception("Email is already registered.");
        }

        // Hash password
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $this->pdo->beginTransaction();
        try {
            // Create user
            $insert = $this->pdo->prepare("
                INSERT INTO users (username, email, password_hash, status) 
                VALUES (?, ?, ?, 'active')
            ");
            $insert->execute([$username, $email, $hash]);
            $userId = $this->pdo->lastInsertId();

            // Associate with default Member role (ID = 2)
            $roleAssign = $this->pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, 2)");
            $roleAssign->execute([$userId]);

            $this->pdo->commit();

            // Trigger hook event
            $this->hook->doAction('auth.registered', ['id' => $userId, 'username' => $username, 'email' => $email]);

            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Authenticate and login a user.
     *
     * @param string $email
     * @param string $password
     * @return bool
     * @throws Exception
     */
    public function login(string $email, string $password): bool
    {
        // 1. IP Lockout Check
        $ip = $this->request->ip();
        $blockFile = ROOT_PATH . '/storage/logs/rate_limits/block_' . md5($ip) . '.json';
        if (file_exists($blockFile)) {
            $blockData = json_decode(file_get_contents($blockFile), true);
            if ($blockData && $blockData['blocked_until'] > time()) {
                throw new Exception("Too many failed login attempts. Your IP is temporarily blocked.");
            } else {
                @unlink($blockFile); // Block expired
            }
        }

        // Retrieve user
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ? AND deleted_at IS NULL");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            if ($user) {
                // Check if this is an admin account to track failed logins
                $stmtRole = $this->pdo->prepare("
                    SELECT r.name FROM user_roles ur
                    JOIN roles r ON ur.role_id = r.id
                    WHERE ur.user_id = ?
                ");
                $stmtRole->execute([$user['id']]);
                $roles = $stmtRole->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $isAdmin = in_array('Admin', $roles) || in_array('Super Admin', $roles);
                if ($isAdmin) {
                    $this->logFailedAdminLogin($email);
                }
            }
            throw new Exception("Invalid login credentials.");
        }

        if ($user['status'] !== 'active') {
            throw new Exception("Your account is currently " . $user['status'] . ".");
        }

        // 2. Session Rotation: Destroy previous session if any
        $cookieName = $this->config['session']['name'] ?? 'forux_session';
        $oldToken = $this->request->cookie($cookieName);
        if ($oldToken) {
            $this->destroySession($oldToken);
        }

        // Generate session token
        $token = bin2hex(random_bytes(32)); // 64 chars
        $csrfToken = bin2hex(random_bytes(16)); // Generate new CSRF token on login
        $payload = json_encode(['_csrf_token' => $csrfToken]);

        // Save session in DB
        $stmt = $this->pdo->prepare("
            INSERT INTO user_sessions (id, user_id, ip_address, user_agent, payload, last_activity)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $token,
            $user['id'],
            $this->request->ip(),
            substr($this->request->header('User-Agent', ''), 0, 255),
            $payload,
            time()
        ]);

        // Send Session Cookie
        $lifetime = $this->config['session']['lifetime'] ?? 86400;
        $expire = time() + $lifetime;
        
        $response = new Response();
        $response->setCookie(
            $cookieName,
            $token,
            $expire,
            '/',
            '',
            $this->config['session']['secure'] ?? false,
            $this->config['session']['httponly'] ?? true,
            $this->config['session']['samesite'] ?? 'Strict'
        );

        // Pre-cache resolved user
        $this->currentUser = $user;
        $this->resolvedUser = true;

        // Trigger hook
        $this->hook->doAction('auth.logged_in', $user);

        return true;
    }

    /**
     * Log out the active user.
     */
    public function logout(): void
    {
        $cookieName = $this->config['session']['name'] ?? 'forux_session';
        $token = $this->request->cookie($cookieName);

        if ($token) {
            $this->destroySession($token);
        }

        // Expire cookie
        $response = new Response();
        $response->setCookie($cookieName, '', time() - 3600);

        $this->currentUser = null;
        $this->resolvedUser = true;
    }

    /**
     * Helper to destroy a session in DB.
     */
    protected function destroySession(string $token): void
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE id = ?");
            $stmt->execute([$token]);
        } catch (\Throwable $e) {
            // Fail silently
        }
    }

    /**
     * Get the active CSRF token for the session.
     * Generates one if it doesn't exist.
     *
     * @return string
     */
    public function getCsrfToken(): string
    {
        $sessionCookieName = $this->config['session']['name'] ?? 'forux_session';
        $token = $this->request->cookie($sessionCookieName);
        $now = time();

        if ($token) {
            $stmt = $this->pdo->prepare("SELECT id, payload, last_activity FROM user_sessions WHERE id = ?");
            $stmt->execute([$token]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($session) {
                $payload = json_decode($session['payload'], true) ?: [];
                if (!empty($payload['_csrf_token'])) {
                    return $payload['_csrf_token'];
                }
                
                $csrfToken = bin2hex(random_bytes(16));
                $payload['_csrf_token'] = $csrfToken;
                $stmtUpdate = $this->pdo->prepare("UPDATE user_sessions SET payload = ? WHERE id = ?");
                $stmtUpdate->execute([json_encode($payload), $token]);
                return $csrfToken;
            }
        }

        $token = bin2hex(random_bytes(32));
        $csrfToken = bin2hex(random_bytes(16));
        $payload = ['_csrf_token' => $csrfToken];

        $stmtInsert = $this->pdo->prepare("
            INSERT INTO user_sessions (id, user_id, ip_address, user_agent, payload, last_activity)
            VALUES (?, NULL, ?, ?, ?, ?)
        ");
        $stmtInsert->execute([
            $token,
            $this->request->ip(),
            substr($this->request->header('User-Agent', ''), 0, 255),
            json_encode($payload),
            $now
        ]);

        $lifetime = $this->config['session']['lifetime'] ?? 86400;
        $expire = $now + $lifetime;
        
        $response = new Response();
        $response->setCookie(
            $sessionCookieName,
            $token,
            $expire,
            '/',
            '',
            $this->config['session']['secure'] ?? false,
            $this->config['session']['httponly'] ?? true,
            $this->config['session']['samesite'] ?? 'Strict'
        );

        return $csrfToken;
    }

    /**
     * Log failed admin login attempt and temporarily block IP if threshold exceeded.
     */
    protected function logFailedAdminLogin(string $email): void
    {
        $ip = $this->request->ip();
        $logDir = ROOT_PATH . '/storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/security.log';
        $timestamp = date('Y-m-d H:i:s');
        $msg = "[{$timestamp}] SECURITY WARNING: Failed admin login attempt for {$email} from IP {$ip}\n";
        file_put_contents($logFile, $msg, FILE_APPEND);

        $rateLimitsDir = $logDir . '/rate_limits';
        if (!is_dir($rateLimitsDir)) {
            mkdir($rateLimitsDir, 0755, true);
        }
        $attemptsFile = $rateLimitsDir . '/attempts_' . md5($ip) . '.json';
        $attempts = [];
        if (file_exists($attemptsFile)) {
            $attempts = json_decode(file_get_contents($attemptsFile), true) ?: [];
        }
        $now = time();
        $attempts = array_filter($attempts, function($t) use ($now) {
            return $t > $now - 900;
        });
        $attempts[] = $now;
        file_put_contents($attemptsFile, json_encode(array_values($attempts)));

        if (count($attempts) >= 5) {
            $blockFile = $rateLimitsDir . '/block_' . md5($ip) . '.json';
            $blockData = ['blocked_until' => $now + 900];
            file_put_contents($blockFile, json_encode($blockData));
            
            $msgBlock = "[{$timestamp}] SECURITY ALERT: IP {$ip} has been temporarily blocked for 15 minutes due to excessive failed admin logins.\n";
            file_put_contents($logFile, $msgBlock, FILE_APPEND);
            
            throw new Exception("Too many failed login attempts. Your IP has been temporarily blocked.");
        }
    }

    /**
     * Send password reset link to user.
     */
    public function sendPasswordResetLink(string $email): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        $token = bin2hex(random_bytes(32));
        
        $this->pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
        $this->pdo->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)")->execute([$email, $token]);

        $mailService = $this->container->get(\App\Services\Mail\MailServiceInterface::class);
        
        $baseUrl = rtrim($this->config['app']['url'] ?? 'http://127.0.0.1:9095', '/');
        $resetUrl = "{$baseUrl}/password/reset/{$token}";

        $subject = "Forux Password Reset Link";
        $body = "Hi there,\n\nYou requested a password reset. Please click the link below to reset your password:\n\n{$resetUrl}\n\nIf you did not request this, please ignore this email.\n\nThanks,\nForux Team";

        return $mailService->send($email, $subject, $body);
    }

    /**
     * Validate password reset token and return associated email.
     */
    public function validateResetToken(string $token): ?string
    {
        $stmt = $this->pdo->prepare("SELECT email, created_at FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            return null;
        }

        // Token expires after 1 hour (3600 seconds)
        if (strtotime($record['created_at']) + 3600 < time()) {
            $this->pdo->prepare("DELETE FROM password_resets WHERE token = ?")->execute([$token]);
            return null;
        }

        return $record['email'];
    }

    /**
     * Perform password reset.
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        $email = $this->validateResetToken($token);
        if (!$email) {
            return false;
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
            $stmt->execute([$hash, $email]);

            $this->pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * Send email verification link.
     */
    public function sendEmailVerification(string $email): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        $token = bin2hex(random_bytes(32));

        $this->pdo->prepare("DELETE FROM email_verifications WHERE email = ?")->execute([$email]);
        $this->pdo->prepare("INSERT INTO email_verifications (email, token) VALUES (?, ?)")->execute([$email, $token]);

        $mailService = $this->container->get(\App\Services\Mail\MailServiceInterface::class);

        $baseUrl = rtrim($this->config['app']['url'] ?? 'http://127.0.0.1:9095', '/');
        $verifyUrl = "{$baseUrl}/verify-email/{$token}";

        $subject = "Verify Your Forux Email Account";
        $body = "Hi there,\n\nPlease click the link below to verify your email address:\n\n{$verifyUrl}\n\nThanks,\nForux Team";

        return $mailService->send($email, $subject, $body);
    }

    /**
     * Perform email verification by token.
     */
    public function verifyEmail(string $token): bool
    {
        $stmt = $this->pdo->prepare("SELECT email, created_at FROM email_verifications WHERE token = ?");
        $stmt->execute([$token]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            return false;
        }

        // Token expires after 24 hours (86400 seconds)
        if (strtotime($record['created_at']) + 86400 < time()) {
            $this->pdo->prepare("DELETE FROM email_verifications WHERE token = ?")->execute([$token]);
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("UPDATE users SET status = 'active' WHERE email = ?");
            $stmt->execute([$record['email']]);

            $this->pdo->prepare("DELETE FROM email_verifications WHERE email = ?")->execute([$record['email']]);
            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
}
