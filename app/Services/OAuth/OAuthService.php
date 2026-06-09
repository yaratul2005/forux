<?php

namespace App\Services\OAuth;

use Exception;

/**
 * Standard OAuth2 Client driver handling Google, GitHub, and Discord login integrations.
 */
class OAuthService implements OAuthServiceInterface
{
    protected array $config;
    protected array $credentials;
    protected string $baseUrl;

    /**
     * Create a new OAuthService instance.
     *
     * @param array $config Application configuration
     * @param array $credentials Decrypted credentials from credentials vault
     */
    public function __construct(array $config, array $credentials)
    {
        $this->config = $config;
        $this->credentials = $credentials;
        
        $url = $config['app']['url'] ?? 'http://localhost';
        // Strip trailing slash and index.php if present
        $url = rtrim($url, '/');
        if (str_ends_with(strtolower($url), '/index.php')) {
            $url = substr($url, 0, -10);
        }
        $this->baseUrl = rtrim($url, '/');
    }

    /**
     * Get the authorization redirect URL for a provider.
     */
    public function getRedirectUrl(string $provider): string
    {
        $provider = strtolower($provider);
        $clientId = $this->getClientId($provider);
        
        if (empty($clientId)) {
            return '';
        }

        // Generate a cryptographically secure state token to prevent CSRF
        $state = bin2hex(random_bytes(16));
        $this->startSession();
        $_SESSION['oauth_state_' . $provider] = $state;

        $redirectUri = $this->getRedirectUri($provider);

        switch ($provider) {
            case 'google':
                return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                    'client_id' => $clientId,
                    'redirect_uri' => $redirectUri,
                    'response_type' => 'code',
                    'scope' => 'openid email profile',
                    'state' => $state,
                    'prompt' => 'select_account'
                ]);

            case 'github':
                return 'https://github.com/login/oauth/authorize?' . http_build_query([
                    'client_id' => $clientId,
                    'redirect_uri' => $redirectUri,
                    'scope' => 'read:user user:email',
                    'state' => $state
                ]);

            case 'discord':
                return 'https://discord.com/api/oauth2/authorize?' . http_build_query([
                    'client_id' => $clientId,
                    'redirect_uri' => $redirectUri,
                    'response_type' => 'code',
                    'scope' => 'identify email',
                    'state' => $state
                ]);
        }

        return '';
    }

    /**
     * Handle the OAuth callback, exchanging the auth code for user information.
     */
    public function handleCallback(string $provider, array $requestData): ?array
    {
        $provider = strtolower($provider);
        $code = $requestData['code'] ?? '';
        $state = $requestData['state'] ?? '';

        if (empty($code) || empty($state)) {
            return null;
        }

        // 1. Verify CSRF state token
        $this->startSession();
        $savedState = $_SESSION['oauth_state_' . $provider] ?? '';
        
        // Clear the state so it is single-use
        unset($_SESSION['oauth_state_' . $provider]);

        if (empty($savedState) || $savedState !== $state) {
            return null; // CSRF token mismatch
        }

        // 2. Exchange code for access token
        $tokenData = $this->exchangeCodeForToken($provider, $code);
        if (empty($tokenData['access_token'])) {
            return null;
        }

        // 3. Fetch and normalize user profile info
        return $this->fetchUserProfile($provider, $tokenData['access_token']);
    }

    /**
     * Start standard PHP session if not already active.
     */
    protected function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }

    /**
     * Construct dynamic redirect URL matching router setup.
     */
    protected function getRedirectUri(string $provider): string
    {
        // For shared hosting, we might route through index.php
        $appUrl = $this->config['app']['url'] ?? $this->baseUrl;
        if (str_contains(strtolower($appUrl), 'index.php')) {
            return rtrim($appUrl, '/') . '/auth/oauth/callback/' . $provider;
        }
        return $this->baseUrl . '/auth/oauth/callback/' . $provider;
    }

    /**
     * Retrieve Client ID from credentials vault.
     */
    protected function getClientId(string $provider): string
    {
        $key = strtoupper($provider) . '_CLIENT_ID';
        return $this->credentials[$key] ?? '';
    }

    /**
     * Retrieve Client Secret from credentials vault.
     */
    protected function getClientSecret(string $provider): string
    {
        $key = strtoupper($provider) . '_CLIENT_SECRET';
        return $this->credentials[$key] ?? '';
    }

    /**
     * Exchange code for access token via cURL POST request.
     */
    protected function exchangeCodeForToken(string $provider, string $code): array
    {
        $clientId = $this->getClientId($provider);
        $clientSecret = $this->getClientSecret($provider);
        $redirectUri = $this->getRedirectUri($provider);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $postFields = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ];

        switch ($provider) {
            case 'google':
                curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
                curl_setopt($ch, CURLOPT_POST, true);
                $postFields['grant_type'] = 'authorization_code';
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
                break;

            case 'github':
                curl_setopt($ch, CURLOPT_URL, 'https://github.com/login/oauth/access_token');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Accept: application/json',
                    'Content-Type: application/x-www-form-urlencoded'
                ]);
                break;

            case 'discord':
                curl_setopt($ch, CURLOPT_URL, 'https://discord.com/api/oauth2/token');
                curl_setopt($ch, CURLOPT_POST, true);
                $postFields['grant_type'] = 'authorization_code';
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
                break;
        }

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return [];
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Retrieve user profile using access token and return normalized representation.
     */
    protected function fetchUserProfile(string $provider, string $accessToken): ?array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        switch ($provider) {
            case 'google':
                curl_setopt($ch, CURLOPT_URL, 'https://openidconnect.googleapis.com/v1/userinfo');
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $accessToken
                ]);
                $response = curl_exec($ch);
                curl_close($ch);

                if (!$response) return null;
                $data = json_decode($response, true);
                if (empty($data['sub'])) return null;

                return [
                    'provider' => 'google',
                    'provider_id' => $data['sub'],
                    'email' => $data['email'] ?? null,
                    'username' => $this->generateUsername($data['name'] ?? $data['given_name'] ?? 'user'),
                    'avatar' => $data['picture'] ?? null
                ];

            case 'github':
                curl_setopt($ch, CURLOPT_URL, 'https://api.github.com/user');
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $accessToken,
                    'User-Agent: Forux-OAuth-Client'
                ]);
                $response = curl_exec($ch);
                
                if (!$response) {
                    curl_close($ch);
                    return null;
                }
                $data = json_decode($response, true);
                if (empty($data['id'])) {
                    curl_close($ch);
                    return null;
                }

                $email = $data['email'] ?? null;

                // GitHub users might have a private email. If so, fetch emails list
                if (empty($email)) {
                    curl_setopt($ch, CURLOPT_URL, 'https://api.github.com/user/emails');
                    $emailResponse = curl_exec($ch);
                    if ($emailResponse) {
                        $emails = json_decode($emailResponse, true);
                        if (is_array($emails)) {
                            foreach ($emails as $emailObj) {
                                if (!empty($emailObj['primary']) && !empty($emailObj['verified'])) {
                                    $email = $emailObj['email'];
                                    break;
                                }
                            }
                        }
                    }
                }
                curl_close($ch);

                return [
                    'provider' => 'github',
                    'provider_id' => (string)$data['id'],
                    'email' => $email,
                    'username' => $this->generateUsername($data['login'] ?? 'user'),
                    'avatar' => $data['avatar_url'] ?? null
                ];

            case 'discord':
                curl_setopt($ch, CURLOPT_URL, 'https://discord.com/api/users/@me');
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $accessToken
                ]);
                $response = curl_exec($ch);
                curl_close($ch);

                if (!$response) return null;
                $data = json_decode($response, true);
                if (empty($data['id'])) return null;

                $avatarUrl = null;
                if (!empty($data['avatar'])) {
                    $avatarUrl = "https://cdn.discordapp.com/avatars/{$data['id']}/{$data['avatar']}.png";
                }

                return [
                    'provider' => 'discord',
                    'provider_id' => $data['id'],
                    'email' => $data['email'] ?? null,
                    'username' => $this->generateUsername($data['username'] ?? 'user'),
                    'avatar' => $avatarUrl
                ];
        }

        return null;
    }

    /**
     * Sanitize and format username.
     */
    protected function generateUsername(string $rawName): string
    {
        // Keep alphanumeric characters and underscores/dashes only
        $username = preg_replace('/[^a-zA-Z0-9_\-]/', '', $rawName);
        return trim($username) ?: 'oauth_user_' . bin2hex(random_bytes(3));
    }
}
