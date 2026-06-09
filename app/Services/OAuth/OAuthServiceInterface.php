<?php

namespace App\Services\OAuth;

/**
 * Interface for OAuth Login Service
 */
interface OAuthServiceInterface
{
    /**
     * Get the authorization redirect URL for a provider.
     *
     * @param string $provider The provider name (e.g., 'google', 'github', 'discord')
     * @return string Redirect URL
     */
    public function getRedirectUrl(string $provider): string;

    /**
     * Handle the OAuth callback, exchanging the auth code for user information.
     *
     * @param string $provider The provider name (e.g., 'google', 'github', 'discord')
     * @param array $requestData The incoming query parameters (containing 'code' and 'state')
     * @return array|null Normalized user info or null on failure
     */
    public function handleCallback(string $provider, array $requestData): ?array;
}
