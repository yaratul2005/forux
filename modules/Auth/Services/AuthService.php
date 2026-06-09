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

    /**
     * Create a new AuthService instance.
     */
    public function __construct(PDO $pdo, Request $request, Hook $hook, \Core\Container $container)
    {
        $this->pdo = $pdo;
        $this->request = $request;
        $this->hook = $hook;
        $this->config = $container->get('config');
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
        // Retrieve user
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ? AND deleted_at IS NULL");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new Exception("Invalid login credentials.");
        }

        if ($user['status'] !== 'active') {
            throw new Exception("Your account is currently " . $user['status'] . ".");
        }

        // Generate session token
        $token = bin2hex(random_bytes(32)); // 64 chars

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
            '{}',
            time()
        ]);

        // Send Session Cookie
        $cookieName = $this->config['session']['name'] ?? 'forux_session';
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
}
