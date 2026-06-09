<?php

namespace App\Services\OAuth;

/**
 * Fallback dormant OAuth service active when no credentials exist.
 */
class NullOAuthService implements OAuthServiceInterface
{
    /**
     * Get redirect URL (No-op).
     */
    public function getRedirectUrl(string $provider): string
    {
        return '';
    }

    /**
     * Handle callback (No-op).
     */
    public function handleCallback(string $provider, array $requestData): ?array
    {
        return null;
    }
}
